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
    
    // Get ALL users (the actual UUIDs are what we need)
    $userManager = $container->getUserManager();
    
    // Search for users using a wildcard to get all users
    $allUsers = $userManager->search('', 100, 0); // Get up to 100 users
    
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
        echo "\nWARNING: No LDAP users found!\n";
        echo "Please make sure:\n";
        echo "1. LDAP is configured in Nextcloud (Settings > Administration > LDAP/AD)\n";
        echo "2. Users have been synced from LDAP\n";
        echo "3. The user_ldap app is enabled\n";
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
