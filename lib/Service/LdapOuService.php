<?php
namespace OCA\LdapOuFilter\Service;

use OCP\IUserManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class LdapOuService {
    private IUserManager $userManager;
    private IConfig $config;
    private LoggerInterface $logger;
    private array $ouCache = [];
    private $ldapConnection = null;
    
    public function __construct(
        IUserManager $userManager,
        IConfig $config,
        LoggerInterface $logger
    ) {
        $this->userManager = $userManager;
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Get the OU for a user
     */
    public function getUserOu(string $userId): ?string {
        // Check cache first
        if (isset($this->ouCache[$userId])) {
            return $this->ouCache[$userId];
        }
        
        try {
            // Get LDAP DN for user
            $userDn = $this->getLdapDn($userId);
            if (!$userDn) {
                $this->logger->debug("Could not find DN for user: $userId");
                return null;
            }
            
            // Extract OU from DN
            $ou = $this->extractOuFromDn($userDn);
            
            // Cache the result
            $this->ouCache[$userId] = $ou;
            
            $this->logger->debug("User $userId is in OU: $ou");
            
            return $ou;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting OU for user ' . $userId, [
                'exception' => $e->getMessage(),
                'app' => 'ldapoufilter'
            ]);
            return null;
        }
    }
    
    /**
     * Get LDAP connection
     */
    private function getLdapConnection() {
        if ($this->ldapConnection !== null) {
            return $this->ldapConnection;
        }
        
        try {
            // Get first LDAP configuration (s01)
            $configPrefix = 's01';
            
            $ldapHost = $this->config->getAppValue('user_ldap', $configPrefix . 'ldap_host', '');
            $ldapPort = $this->config->getAppValue('user_ldap', $configPrefix . 'ldap_port', '389');
            
            if (empty($ldapHost)) {
                $this->logger->warning('LDAP host not configured');
                return null;
            }
            
            // Connect to LDAP
            $this->ldapConnection = @ldap_connect($ldapHost, intval($ldapPort));
            
            if (!$this->ldapConnection) {
                $this->logger->error('Failed to connect to LDAP server');
                return null;
            }
            
            // Set LDAP options
            ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->ldapConnection, LDAP_OPT_NETWORK_TIMEOUT, 10);
            
            return $this->ldapConnection;
            
        } catch (\Exception $e) {
            $this->logger->error('Error connecting to LDAP', [
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get LDAP DN for a user
     */
    private function getLdapDn(string $userId): ?string {
        try {
            $conn = $this->getLdapConnection();
            if (!$conn) {
                return null;
            }
            
            // Get LDAP configuration
            $configPrefix = 's01';
            $ldapBaseDn = $this->config->getAppValue('user_ldap', $configPrefix . 'ldap_base', '');
            $ldapBindDn = $this->config->getAppValue('user_ldap', $configPrefix . 'ldap_dn', '');
            $ldapBindPassword = $this->config->getAppValue('user_ldap', $configPrefix . 'ldap_agent_password', '');
            
            // Bind to LDAP
            if (!@ldap_bind($conn, $ldapBindDn, $ldapBindPassword)) {
                $this->logger->error('Failed to bind to LDAP');
                return null;
            }
            
            // Search for user - try multiple attributes
            // Escape special LDAP characters in userId
            $escapedUserId = ldap_escape($userId, '', LDAP_ESCAPE_FILTER);
            
            $filters = [
                "(uid=$escapedUserId)",
                "(sAMAccountName=$escapedUserId)",
                "(cn=$escapedUserId)",
                "(mail=$escapedUserId)",
                "(userPrincipalName=$escapedUserId)"
            ];
            
            foreach ($filters as $filter) {
                $search = @ldap_search($conn, $ldapBaseDn, $filter, ['dn']);
                
                if ($search) {
                    $entries = ldap_get_entries($conn, $search);
                    if ($entries['count'] > 0) {
                        return $entries[0]['dn'];
                    }
                }
            }
            
            // Try with combined filter
            $combinedFilter = "(|(uid=$escapedUserId)(sAMAccountName=$escapedUserId)(cn=$escapedUserId)(userPrincipalName=$escapedUserId))";
            $this->logger->debug("Searching LDAP with filter: $combinedFilter in base: $ldapBaseDn");
            
            $search = @ldap_search($conn, $ldapBaseDn, $combinedFilter, ['dn']);
            
            if ($search) {
                $entries = ldap_get_entries($conn, $search);
                if ($entries['count'] > 0) {
                    return $entries[0]['dn'];
                }
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting LDAP DN for user', [
                'userId' => $userId,
                'exception' => $e->getMessage()
            ]);
        }
        
        return null;
    }
    
/**
     * Extract OU from DN string
     * For nested OUs, returns the MOST SPECIFIC (immediate parent) OU
     * 
     * Example DN structures:
     * - CN=hunter1,OU=cyberfirst,OU=Mail,DC=Frist,DC=loc
     * - CN=user,OU=Mail,OU=first,DC=Frist,DC=loc
     * 
     * We want to extract the specific sub-OU (cyberfirst, first, elzoz, bebo)
     * NOT the parent "Mail" OU
     */
    private function extractOuFromDn(string $dn): string {
        $this->logger->info("=== OU EXTRACTION DEBUG ===", ['app' => 'ldapoufilter']);
        $this->logger->info("DN: $dn", ['app' => 'ldapoufilter']);
        
        // Parse DN to get OU parts
        $dnParts = explode(',', $dn);
        $ouParts = [];
        
        foreach ($dnParts as $part) {
            $part = trim($part);
            if (stripos($part, 'OU=') === 0) {
                $ouParts[] = $part;
            }
        }
        
        $this->logger->info("Found " . count($ouParts) . " OU levels: " . json_encode($ouParts), ['app' => 'ldapoufilter']);
        
        if (empty($ouParts)) {
            $this->logger->warning("No OU found in DN: $dn");
            return '';
        }
        
        // STRATEGY: Find the most specific OU (not "Mail")
        // We'll use the LAST OU that is NOT "Mail"
        $selectedOu = '';
        
        // Filter out "Mail" OU and use the most specific one
        $specificOus = array_filter($ouParts, function($ou) {
            $ouValue = strtolower(trim(substr($ou, 3))); // Remove "OU=" prefix
            return $ouValue !== 'mail';
        });
        
        if (!empty($specificOus)) {
            // Use the first specific OU (closest to the user)
            $selectedOu = reset($specificOus);
            $this->logger->info("Selected specific OU (filtered out 'Mail'): $selectedOu", ['app' => 'ldapoufilter']);
        } else {
            // Fallback: If we can't filter out Mail, try using the second OU if available
            if (count($ouParts) > 1) {
                // Try second OU (might be the specific one)
                $selectedOu = $ouParts[1];
                $this->logger->info("Selected second OU as fallback: $selectedOu", ['app' => 'ldapoufilter']);
            } else {
                // Last resort: use first OU
                $selectedOu = $ouParts[0];
                $this->logger->info("Selected first OU as last resort: $selectedOu", ['app' => 'ldapoufilter']);
            }
        }
        
        $this->logger->info("=== FINAL SELECTED OU: $selectedOu ===", ['app' => 'ldapoufilter']);
        
        return $selectedOu;
    }
    
    /**
     * Check if two users are in the same OU
     */
    public function areUsersInSameOu(string $userId1, string $userId2): bool {
        $ou1 = $this->getUserOu($userId1);
        $ou2 = $this->getUserOu($userId2);
        
        if ($ou1 === null || $ou2 === null || $ou1 === '' || $ou2 === '') {
            // If we can't determine OU for either user, allow by default
            $this->logger->debug("Could not determine OU for users: $userId1 or $userId2");
            return true;
        }
        
        $result = strcasecmp($ou1, $ou2) === 0;
        
        $this->logger->debug("OU comparison: $userId1 ($ou1) vs $userId2 ($ou2) = " . ($result ? 'same' : 'different'));
        
        return $result;
    }
    
    /**
     * Filter users array by OU
     */
    public function filterUsersByOu(array $users, string $currentUserId): array {
        $currentUserOu = $this->getUserOu($currentUserId);
        
        if ($currentUserOu === null || $currentUserOu === '') {
            // If we can't determine current user's OU, return all users
            $this->logger->debug("Could not determine OU for current user: $currentUserId");
            return $users;
        }
        
        $this->logger->debug("Filtering users for OU: $currentUserOu");
        
        $filteredUsers = [];
        
        foreach ($users as $user) {
            // Handle different user array structures
            $userId = null;
            
            if (is_string($user)) {
                $userId = $user;
            } elseif (is_array($user)) {
                if (isset($user['value']['shareWith'])) {
                    $userId = $user['value']['shareWith'];
                } elseif (isset($user['id'])) {
                    $userId = $user['id'];
                } elseif (isset($user['uid'])) {
                    $userId = $user['uid'];
                }
            }
            
            if ($userId) {
                if ($this->areUsersInSameOu($currentUserId, $userId)) {
                    $filteredUsers[] = $user;
                }
            }
        }
        
        $this->logger->debug("Filtered " . count($users) . " users to " . count($filteredUsers) . " users");
        
        return $filteredUsers;
    }
    
    /**
     * Clear cache for a user or all users
     */
    public function clearCache(string $userId = null): void {
        if ($userId === null) {
            $this->ouCache = [];
            $this->logger->info("Cleared all OU cache");
        } else {
            unset($this->ouCache[$userId]);
            $this->logger->info("Cleared OU cache for user: $userId");
        }
    }
    
    /**
     * Close LDAP connection
     */
    public function __destruct() {
        if ($this->ldapConnection !== null) {
            @ldap_close($this->ldapConnection);
        }
    }
}