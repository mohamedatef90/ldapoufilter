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
    
    // Test users from your environment - use UUIDs!
    // First, get the actual UUIDs by searching
    $userManager = $container->getUserManager();
    
    // Search for users by display name to get their UUIDs
    $testUsers = [];
    $displayNames = ['hunter1', 'bebo 01', 'Younis_admin'];
    
    foreach ($displayNames as $displayName) {
        $users = $userManager->searchDisplayName($displayName);
        if (!empty($users)) {
            $uuid = $users[0]->getUID();
            $testUsers[$displayName] = $uuid;
            echo "Found user: $displayName -> $uuid\n";
        } else {
            echo "WARNING: User not found: $displayName\n";
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
    
    // Get UUIDs for testing
    $hunter1_uuid = $testUsers['hunter1'] ?? null;
    $bebo_uuid = $testUsers['bebo 01'] ?? null;
    $younis_uuid = $testUsers['Younis_admin'] ?? null;
    
    echo "\n--- Testing OU Comparison ---\n";
    if ($hunter1_uuid && $bebo_uuid) {
        echo "Are 'hunter1' and 'bebo 01' in the same OU?\n";
        try {
            $sameOu = $service->areUsersInSameOu($hunter1_uuid, $bebo_uuid);
            echo "  Result: " . ($sameOu ? 'YES' : 'NO') . "\n";
        } catch (\Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n--- Testing User Filter ---\n";
    if ($hunter1_uuid) {
        $testUserUuids = array_filter([$hunter1_uuid, $bebo_uuid, $younis_uuid]);
        echo "Current user: hunter1 ($hunter1_uuid)\n";
        echo "Users to filter: " . count($testUserUuids) . " users\n";
        
        try {
            $filtered = $service->filterUsersByOu($testUserUuids, $hunter1_uuid);
            echo "  Filtered users: " . count($filtered) . " out of " . count($testUserUuids) . "\n";
            
            if (count($filtered) > 0) {
                echo "  Users in same OU as hunter1:\n";
                foreach ($filtered as $userUuid) {
                    $userObj = $userManager->get($userUuid);
                    $displayName = $userObj ? $userObj->getDisplayName() : $userUuid;
                    echo "    - $displayName ($userUuid)\n";
                }
            }
        } catch (\Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n=== Test completed ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
