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
    
    // Test users from your environment
    $testUsers = ['hunter1', 'bebo 01', 'Younis_admin'];
    
    echo "--- Testing Individual Users ---\n";
    foreach ($testUsers as $user) {
        echo "\nUser: $user\n";
        echo "  Getting OU...\n";
        
        try {
            // Check logs to see what's happening
            echo "  Checking logs for details...\n";
            
            $ou = $service->getUserOu($user);
            if ($ou) {
                echo "  ✓ OU found: $ou\n";
            } else {
                echo "  ✗ No OU found\n";
                echo "  (Check logs above for debug info)\n";
            }
        } catch (\Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n--- Testing OU Comparison ---\n";
    echo "Are 'hunter1' and 'bebo 01' in the same OU?\n";
    try {
        $sameOu = $service->areUsersInSameOu('hunter1', 'bebo 01');
        echo "  Result: " . ($sameOu ? 'YES' : 'NO') . "\n";
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n--- Testing User Filter ---\n";
    $testUserList = ['hunter1', 'bebo 01', 'Younis_admin'];
    echo "Current user: hunter1\n";
    echo "Users to filter: " . implode(', ', $testUserList) . "\n";
    
    try {
        $filtered = $service->filterUsersByOu($testUserList, 'hunter1');
        echo "  Filtered users: " . count($filtered) . " out of " . count($testUserList) . "\n";
        
        if (count($filtered) > 0) {
            echo "  Users in same OU as hunter1:\n";
            foreach ($filtered as $user) {
                echo "    - $user\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Test completed ===\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
