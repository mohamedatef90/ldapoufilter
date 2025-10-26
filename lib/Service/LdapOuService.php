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
     * Get LDAP DN for a user by querying Nextcloud's database
     * The DN is stored in the ldap_user_mapping table
     */
    private function getLdapDnViaNextcloud(string $userId): ?string {
        try {
            // Get the database connection
            $connection = $this->serverContainer->get(\OCP\IDBConnection::class);
            
            // Query the ldap_user_mapping table for the DN
            // Note: The table name is 'ldap_user_mapping' (not 'oc_user_ldap_users_mapping')
            $query = $connection->getQueryBuilder();
            $query->select('ldap_dn')
                ->from('ldap_user_mapping')
                ->where($query->expr()->eq('owncloud_name', $query->createNamedParameter($userId)));
            
            $result = $query->executeQuery();
            $row = $result->fetch();
            $result->closeCursor();
            
            if ($row && isset($row['ldap_dn']) && !empty($row['ldap_dn'])) {
                $dn = $row['ldap_dn'];
                $this->logger->debug("Found DN for user $userId in database: $dn");
                return $dn;
            }
            
            $this->logger->debug("Could not find DN for user $userId in database");
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting LDAP DN from database for user', [
                'userId' => $userId,
                'exception' => $e->getMessage()
            ]);
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
        
        // STRATEGY: Find the most specific OU
        // Filter out generic/parent OU names that are commonly used as containers
        // This makes the app work with ANY AD structure without hardcoding OU names
        $selectedOu = '';
        
        // List of generic OU names to ignore (these are common parent/container names)
        $genericOus = [
            'mail',           // Common email container
            'users',          // Generic user container
            'departments',    // Generic department container
            'ou',             // Generic OU container
            'organization',   // Generic organization container
            'organizational', // Generic organizational unit
            'containers',     // Generic container
            'computers',      // Generic computer container
            'groups',         // Generic group container
        ];
        
        // Filter out generic OUs to get the specific ones
        $specificOus = array_filter($ouParts, function($ou) use ($genericOus) {
            $ouValue = strtolower(trim(substr($ou, 3))); // Remove "OU=" prefix
            return !in_array($ouValue, $genericOus);
        });
        
        if (!empty($specificOus)) {
            // Use the first specific OU (closest to the user, most specific)
            $selectedOu = reset($specificOus);
            $this->logger->info("Selected specific OU (filtered out generic OUs): $selectedOu", ['app' => 'ldapoufilter']);
        } else {
            // Fallback: If ALL OUs are generic, use the closest one to the user
            $selectedOu = reset($ouParts);
            $this->logger->info("All OUs are generic, using first OU as fallback: $selectedOu", ['app' => 'ldapoufilter']);
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