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
    
    // Get ALL LDAP users (the actual UUIDs are what we need)
    $userManager = $container->getUserManager();
    
    // Search for users using a wildcard to get LDAP users
    $allUsers = $userManager->search('', 10, 0); // Empty search gets users
    
    echo "Found " . count($allUsers) . " users total\n";
    
    // Show all users
    $testUsers = [];
    foreach ($allUsers as $user) {
        $uuid = $user->getUID();
        $displayName = $user->getDisplayName();
        $backend = $user->getBackend();
        
        // Only test LDAP users
        if ($backend instanceof \OCA\User_LDAP\User_Proxy) {
            $testUsers[$displayName] = $uuid;
            echo "Found LDAP user: $displayName -> $uuid\n";
            
            // Also show the home directory to debug
            echo "  Home: " . $user->getHome() . "\n";
        }
    }
    
    if (empty($testUsers)) {
        echo "\nERROR: No LDAP users found!\n";
        echo "Make sure LDAP users are configured in Nextcloud.\n";
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
