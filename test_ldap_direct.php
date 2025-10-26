<?php
/**
 * Direct LDAP DN Test - Extract DN from database
 */
require_once '/var/www/nextcloud/lib/base.php';

echo "=== Direct LDAP DN Test ===\n\n";

try {
    $connection = \OC::$server->get(\OCP\IDBConnection::class);
    
    // Try to find the LDAP DN from the database
    $userId = 'EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B'; // hunter1
    
    echo "Testing for user: $userId\n\n";
    
    // Try different table names
    $tables = [
        'oc_user_ldap_users_mapping',
        'user_ldap_users_mapping',
        'ldap_user_mapping',
    ];
    
    foreach ($tables as $table) {
        echo "Trying table: $table\n";
        try {
            $query = $connection->getQueryBuilder();
            $query->select('*')
                ->from($table)
                ->where($query->expr()->eq('owncloud_name', $query->createNamedParameter($userId)))
                ->setMaxResults(5);
            
            $result = $query->executeQuery();
            $rows = $result->fetchAll();
            $result->closeCursor();
            
            if (!empty($rows)) {
                echo "✓ Found " . count($rows) . " rows\n";
                foreach ($rows as $row) {
                    echo "Row data:\n";
                    foreach ($row as $key => $value) {
                        if (is_string($value) && strlen($value) < 500) {
                            echo "  $key: $value\n";
                        }
                    }
                    echo "\n";
                }
            } else {
                echo "No rows found\n\n";
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Show table schema
    echo "\n--- Database Schema ---\n";
    $schemaManager = $connection->getSchemaManager();
    $tables = $schemaManager->listTableNames();
    
    foreach ($tables as $table) {
        if (strpos($table, 'ldap') !== false || strpos($table, 'user') !== false) {
            echo "Table: $table\n";
            try {
                $tableDetails = $schemaManager->listTableDetails($table);
                $columns = $tableDetails->getColumns();
                foreach ($columns as $column) {
                    echo "  - " . $column->getName() . " (" . $column->getType()->getName() . ")\n";
                }
            } catch (\Exception $e) {
                echo "  Error: " . $e->getMessage() . "\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
