<?php
/**
 * Test LDAP OU Filter from Nextcloud server
 * Run: sudo -u www-data php test_ou_server.php
 */
require_once '/var/www/nextcloud/lib/base.php';

use OCA\LdapOuFilter\Service\LdapOuService;

echo "=== LDAP OU Filter Test ===\n\n";

try {
    $container = \OC::$server;
    $logger = $container->get(\Psr\Log\LoggerInterface::class);
    
    // Create the service manually
    $service = new LdapOuService(
        $container->getUserManager(),
        $container->getConfig(),
        $logger,
        $container
    );
    
    echo "✓ Service created successfully\n\n";
    
    // Check LDAP app status
    $appManager = $container->get(\OCP\App\IAppManager::class);
    $ldapEnabled = $appManager->isEnabledForUser('user_ldap');
    echo "LDAP app enabled: " . ($ldapEnabled ? 'YES' : 'NO') . "\n\n";
    
    // Get ALL users - fetch all backends
    $userManager = $container->getUserManager();
    $backends = $userManager->getBackends();
    echo "Found " . count($backends) . " user backends:\n";
    foreach ($backends as $backend) {
        echo "  - " . get_class($backend) . "\n";
    }
    echo "\n";
    
    // Try to get users from LDAP backend directly
    echo "Attempting to fetch users from LDAP backend...\n";
    $allUsers = [];
    foreach ($backends as $backend) {
        if ($backend instanceof \OCA\User_LDAP\User_Proxy || $backend instanceof \OCA\User_LDAP\Group_Proxy) {
            echo "Found LDAP backend: " . get_class($backend) . "\n";
            try {
                // Try to get users from the backend
                if (method_exists($backend, 'users')) {
                    $ldapUsers = $backend->users('', 100, 0);
                    echo "LDAP backend returned " . count($ldapUsers) . " users\n";
                    foreach ($ldapUsers as $uid) {
                        $user = $userManager->get($uid);
                        if ($user) {
                            $allUsers[] = $user;
                        }
                    }
                }
            } catch (\Exception $e) {
                echo "Error getting users from LDAP: " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Fallback: Try regular search
    if (empty($allUsers)) {
        echo "Trying regular search method...\n";
        $allUsers = $userManager->search('', 100, 0);
    }
    
    echo "Total users found: " . count($allUsers) . "\n\n";
    
    echo "Found " . count($allUsers) . " users total\n\n";
    
    // Show all users with their backends
    $testUsers = [];
    echo "--- All Users Found ---\n";
    foreach ($allUsers as $user) {
        $uuid = $user->getUID();
        $displayName = $user->getDisplayName();
        $backend = $user->getBackend();
        $backendClass = get_class($backend);
        
        echo "User: $displayName -> $uuid\n";
        echo "  Backend: $backendClass\n";
        echo "  Home: " . $user->getHome() . "\n";
        
        // Check if this is an LDAP user
        if ($backend instanceof \OCA\User_LDAP\User_Proxy) {
            $testUsers[$displayName] = $uuid;
            echo "  ✓ LDAP User\n";
        } else {
            echo "  ✗ Not an LDAP user (class: $backendClass)\n";
        }
        echo "\n";
    }
    
    if (empty($testUsers)) {
        echo "\nWARNING: No LDAP users found via search!\n";
        echo "This might be a backend registration issue. Testing direct DN extraction...\n\n";
        
        // Test specific known LDAP users from the occ user:list output
        // Even if they're not found via search, we can still try to get their DN
        $knownLdapUsers = [
            'EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B' => 'hunter1',
            '8162AF93-5C6E-4DA9-84EC-C3BF7BFFA736' => 'bebo 01',
            '2FC5042C-DA3B-4AB3-A22B-79BE6CBF534C' => 'Younis',
        ];
        
        echo "Testing direct OU extraction for known users...\n";
        foreach ($knownLdapUsers as $uuid => $displayName) {
            echo "\nUser: $displayName ($uuid)\n";
            
            // First check if the user can be found by userManager
            $user = $userManager->get($uuid);
            if ($user) {
                echo "  ✓ User found\n";
                echo "  Backend: " . get_class($user->getBackend()) . "\n";
                echo "  Display Name: " . $user->getDisplayName() . "\n";
                
                // Now try to extract OU
                echo "  Attempting to extract OU...\n";
                try {
                    $ou = $service->getUserOu($uuid);
                    if ($ou) {
                        echo "  ✓ OU found: $ou\n";
                        $testUsers[$displayName] = $uuid;
                    } else {
                        echo "  ✗ No OU found\n";
                    }
                } catch (\Exception $e) {
                    echo "  ✗ Error extracting OU: " . $e->getMessage() . "\n";
                }
            } else {
                echo "  ✗ User not found in userManager\n";
                echo "  This user may not exist in the current session\n";
            }
        }
    }
    echo "\n";
    
    echo "--- Testing Individual Users ---\n";
    foreach ($testUsers as $displayName => $uuid) {
        echo "\nUser: $displayName (UUID: $uuid)\n";
        echo "  Getting OU...\n";
        
        try {
            $ou = $service->getUserOu($uuid);
            if ($ou) {
                echo "  ✓ OU found: $ou\n";
            } else {
                echo "  ✗ No OU found\n";
            }
        } catch (\Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    // Test OU comparison with first two users
    $userList = array_values($testUsers);
    if (count($userList) >= 2) {
        echo "\n--- Testing OU Comparison ---\n";
        $user1 = $userList[0];
        $user2 = $userList[1];
        
        $userObj1 = $userManager->get($user1);
        $userObj2 = $userManager->get($user2);
        
        if ($userObj1 && $userObj2) {
            echo "Are '{$userObj1->getDisplayName()}' and '{$userObj2->getDisplayName()}' in the same OU?\n";
            try {
                $sameOu = $service->areUsersInSameOu($user1, $user2);
                echo "  Result: " . ($sameOu ? 'YES' : 'NO') . "\n";
            } catch (\Exception $e) {
                echo "  ✗ Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n=== Test completed ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
