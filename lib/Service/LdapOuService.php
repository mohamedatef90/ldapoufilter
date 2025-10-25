<?php
namespace OCA\LdapOuFilter\Service;

use OCP\IUserManager;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use OCP\IServerContainer;

class LdapOuService {
    private IUserManager $userManager;
    private IConfig $config;
    private LoggerInterface $logger;
    private IServerContainer $serverContainer;
    private array $ouCache = [];
    
    public function __construct(
        IUserManager $userManager,
        IConfig $config,
        LoggerInterface $logger,
        IServerContainer $serverContainer
    ) {
        $this->userManager = $userManager;
        $this->config = $config;
        $this->logger = $logger;
        $this->serverContainer = $serverContainer;
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
            // Get LDAP DN for user using Nextcloud's LDAP user manager
            $userDn = $this->getLdapDnViaNextcloud($userId);
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
     * Get LDAP DN for a user using direct LDAP connection
     */
    private function getLdapDnViaNextcloud(string $userId): ?string {
        try {
            // Get LDAP configuration from Nextcloud
            $ldapConfig = $this->getLdapConfig();
            
            if (!$ldapConfig) {
                $this->logger->debug("LDAP not configured in Nextcloud");
                return null;
            }
            
            // Connect to LDAP
            $conn = @ldap_connect($ldapConfig['host'], $ldapConfig['port']);
            if (!$conn) {
                $this->logger->debug("Failed to connect to LDAP");
                return null;
            }
            
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 10);
            
            // Bind with Nextcloud's LDAP credentials
            if (!@ldap_bind($conn, $ldapConfig['bindDN'], $ldapConfig['bindPassword'])) {
                $this->logger->debug("Failed to bind to LDAP");
                return null;
            }
            
            // Search for user with multiple possible attributes
            $escapedUserId = ldap_escape($userId, '', LDAP_ESCAPE_FILTER);
            
            // Try common LDAP attributes
            $filters = [
                "(uid=$escapedUserId)",
                "(sAMAccountName=$escapedUserId)",
                "(cn=$escapedUserId)",
                "(mail=$escapedUserId)",
                "(userPrincipalName=$escapedUserId)"
            ];
            
            foreach ($filters as $filter) {
                $search = @ldap_search($conn, $ldapConfig['base'], $filter, ['dn']);
                
                if ($search) {
                    $entries = ldap_get_entries($conn, $search);
                    if ($entries['count'] > 0 && isset($entries[0]['dn'])) {
                        $dn = $entries[0]['dn'];
                        @ldap_close($conn);
                        $this->logger->debug("Found DN for user $userId: $dn");
                        return $dn;
                    }
                }
            }
            
            @ldap_close($conn);
            $this->logger->debug("Could not find DN for user: $userId");
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting LDAP DN for user', [
                'userId' => $userId,
                'exception' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Get LDAP configuration from Nextcloud
     */
    private function getLdapConfig(): ?array {
        try {
            // Try to get LDAP configuration from Nextcloud
            // First, try to find which LDAP server is active
            $activeConfig = null;
            
            // Loop through possible LDAP server IDs (s01, s02, etc.)
            for ($i = 1; $i <= 10; $i++) {
                $prefix = 's0' . $i;
                $ldapHost = $this->config->getAppValue('user_ldap', $prefix . 'ldap_host', '');
                
                if (!empty($ldapHost)) {
                    $ldapPort = $this->config->getAppValue('user_ldap', $prefix . 'ldap_port', '389');
                    $ldapBase = $this->config->getAppValue('user_ldap', $prefix . 'ldap_base', '');
                    $ldapBindDN = $this->config->getAppValue('user_ldap', $prefix . 'ldap_dn', '');
                    $ldapBindPassword = $this->config->getAppValue('user_ldap', $prefix . 'ldap_agent_password', '');
                    
                    // Check if this is the active configuration
                    $isActive = $this->config->getAppValue('user_ldap', $prefix . 'ldap_configuration_active', '0');
                    
                    if ($isActive === '1' && !empty($ldapBindDN) && !empty($ldapBindPassword)) {
                        $activeConfig = [
                            'host' => $ldapHost,
                            'port' => $ldapPort,
                            'base' => $ldapBase,
                            'bindDN' => $ldapBindDN,
                            'bindPassword' => $ldapBindPassword
                        ];
                        break;
                    }
                }
            }
            
            return $activeConfig;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting LDAP config: ' . $e->getMessage());
            return null;
        }
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
    
}