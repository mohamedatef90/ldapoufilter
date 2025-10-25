<?php
/**
 * Test script to verify LDAP OU Service fix
 * Run this from the Nextcloud root directory
 */

require_once __DIR__ . '/../../../../lib/base.php';

use OCA\LdapOuFilter\Service\LdapOuService;

echo "=== Testing LDAP OU Service Fix ===\n\n";

try {
    // Get the service from Nextcloud's container
    $server = \OC::$server;
    $ldapOuService = $server->get(LdapOuService::class);
    
    echo "✓ LdapOuService loaded successfully\n";
    
    // Test with a known user
    $testUserId = 'hunter1';
    echo "\nTesting with user: $testUserId\n";
    
    $ou = $ldapOuService->getUserOu($testUserId);
    
    if ($ou !== null) {
        echo "✓ Found OU for user $testUserId: $ou\n";
    } else {
        echo "⚠ No OU found for user $testUserId\n";
    }
    
    // Test OU comparison
    echo "\nTesting OU comparison:\n";
    $user1 = 'hunter1';
    $user2 = 'Younis_admin';
    
    $sameOu = $ldapOuService->areUsersInSameOu($user1, $user2);
    echo "Users $user1 and $user2 in same OU: " . ($sameOu ? 'Yes' : 'No') . "\n";
    
    echo "\n=== Test completed successfully ===\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
