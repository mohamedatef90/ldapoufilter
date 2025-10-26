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
     * Get LDAP DN for a user by using Nextcloud's LDAP user backend
     * This retrieves the DN from the LDAP user object that Nextcloud already loaded
     */
    private function getLdapDnViaNextcloud(string $userId): ?string {
        try {
            // Get the user from Nextcloud's user manager
            $user = $this->userManager->get($userId);
            if (!$user) {
                $this->logger->debug("User not found in Nextcloud: $userId");
                return null;
            }
            
            // Check if this is an LDAP user
            $backend = $user->getBackend();
            if (!$backend instanceof \OCA\User_LDAP\User_Proxy) {
                $this->logger->debug("User $userId is not an LDAP user");
                return null;
            }
            
            // Try to get the LDAP user's DN using the backend's getHome() method
            // For LDAP users, the home path contains LDAP information
            $homeDir = $user->getHome();
            
            // The DN can be found in the home directory path for LDAP users
            // Format is usually like: /home/ldap/dn=cn=user,ou=org,dc=example,dc=com
            if ($homeDir && strpos($homeDir, 'ldap') !== false) {
                // Try to extract DN from home path
                $parts = explode('dn=', $homeDir);
                if (count($parts) > 1) {
                    $dn = 'dn=' . $parts[1];
                    $this->logger->debug("Found DN from home path for user $userId: $dn");
                    return $dn;
                }
            }
            
            // Try alternative: get from displayName or other attributes
            $displayName = $user->getDisplayName();
            $this->logger->debug("User $userId home: $homeDir, displayName: $displayName");
            
            // Try to use reflection to access protected LDAP user properties
            try {
                $reflection = new \ReflectionClass($user);
                $this->logger->debug("User class: " . get_class($user));
                
                // Try to get all properties
                $properties = $reflection->getProperties();
                foreach ($properties as $property) {
                    $property->setAccessible(true);
                    $value = $property->getValue($user);
                    if (is_string($value) && strpos($value, 'CN=') !== false && strpos($value, 'OU=') !== false) {
                        $this->logger->debug("Found potential DN in property {$property->getName()}: $value");
                        return $value;
                    }
                }
            } catch (\Exception $reflectionEx) {
                $this->logger->debug("Reflection failed: " . $reflectionEx->getMessage());
            }
            
            $this->logger->debug("Could not extract DN for user $userId from user object");
            return null;
            
        } catch (\Exception $e) {
            $this->logger->error('Error getting LDAP DN from user object', [
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