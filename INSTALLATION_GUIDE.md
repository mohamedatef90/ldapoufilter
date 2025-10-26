# LDAP OU Filter - Complete Installation & Operation Guide

## 📋 Overview

The **LDAP OU Filter** app restricts Nextcloud sharing to users within the same Organizational Unit (OU) in Active Directory / LDAP. This ensures users can only see and share with others in their specific OU.

### What This App Does
- **Filters user search results** to show only users from the same OU
- **Prevents cross-OU sharing** in file sharing, Talk conversations, and all sharing features
- **Works transparently** without modifying user behavior
- **Automatic OU detection** from LDAP Distinguished Names (DN)

---

## 🔧 Prerequisites

- Nextcloud 31.0 or later
- LDAP/Active Directory backend configured
- PHP 8.0 or later
- PostgreSQL or MySQL/MariaDB

---

## 📦 Installation

### Step 1: Upload the App

```bash
# Option A: Using SCP (from your local machine)
scp -r ldapoufilter root@YOUR_SERVER_IP:/var/www/nextcloud/apps/

# Option B: Using Git (on the server)
cd /var/www/nextcloud/apps
git clone YOUR_REPO_URL ldapoufilter
```

### Step 2: Set Permissions

```bash
cd /var/www/nextcloud/apps
chown -R www-data:www-data ldapoufilter
chmod -R 755 ldapoufilter
```

### Step 3: Enable the App

```bash
# Enable the app
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# Check if enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter
```

Expected output:
```
  - ldapoufilter        0.1.0          enabled
```

### Step 4: Verify Installation

Check the Nextcloud log to confirm the app booted successfully:

```bash
tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

Expected output:
```
{"message":"LDAP OU Filter app booted successfully","app":"ldapoufilter"}
{"message":"✓ OU Filter Plugin registered with Collaborators Manager","app":"ldapoufilter"}
```

---

## ✅ Testing & Verification

### Test 1: Check OU Detection

Create a test script on the server:

```bash
cat > /var/www/nextcloud/apps/ldapoufilter/test_ou.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';

$service = \OC::$server->get(\OCA\LdapOuFilter\Service\LdapOuService::class);
$logger = \OC::$server->get(\Psr\Log\LoggerInterface::class);

// Test with known LDAP users
$testUsers = [
    'EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B', // Example: hunter1
    '8162AF93-5C6E-4DA9-84EC-C3BF7BFFA736', // Example: bebo 01
];

echo "=== OU Detection Test ===\n\n";

foreach ($testUsers as $userId) {
    $ou = $service->getUserOu($userId);
    echo "User: $userId\n";
    echo "  OU: " . ($ou ?: 'Not found') . "\n\n";
}

// Test OU comparison
if (count($testUsers) >= 2) {
    $same = $service->areUsersInSameOu($testUsers[0], $testUsers[1]);
    echo "Are users in same OU? " . ($same ? 'YES' : 'NO') . "\n";
}
EOF

# Run the test
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php
```

Expected output:
```
=== OU Detection Test ===

User: EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B
  OU: OU=cyberfirst

User: 8162AF93-5C6E-4DA9-84EC-C3BF7BFFA736
  OU: OU=bebo

Are users in same OU? NO
```

### Test 2: Check Database Connection

Verify the app can query the LDAP mapping table:

```bash
cat > /tmp/check_db.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';

$connection = \OC::$server->get(\OCP\IDBConnection::class);

$query = $connection->getQueryBuilder();
$query->select('owncloud_name', 'ldap_dn')
    ->from('ldap_user_mapping')
    ->setMaxResults(5);

$result = $query->executeQuery();
$rows = $result->fetchAll();
$result->closeCursor();

echo "=== LDAP User Mapping (first 5 records) ===\n\n";
foreach ($rows as $row) {
    echo "User: " . $row['owncloud_name'] . "\n";
    echo "  DN: " . $row['ldap_dn'] . "\n\n";
}
EOF

sudo -u www-data php /tmp/check_db.php
```

Expected output (shows LDAP users and their DNs):
```
=== LDAP User Mapping (first 5 records) ===

User: EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B
  DN: cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc

User: 8162AF93-5C6E-4DA9-84EC-C3BF7BFFA736
  DN: cn=bebo 01,ou=bebo,ou=mail,dc=frist,dc=loc
...
```

### Test 3: UI Verification

1. **Log into Nextcloud** as a user (e.g., `hunter1`)
2. **Navigate to Files** and try to share a file
3. **Search for users** in different OUs
4. **Verify** that only users from the same OU appear

What to expect:
- ✅ Users from same OU: **Visible**
- ❌ Users from different OUs: **Hidden**

---

## 🔍 How It Works

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Nextcloud Sharees API                     │
│              (/ocs/v2.php/apps/files_sharing/api/v1/sharees) │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│              OuFilterPlugin (ISearchPlugin)                 │
│  • Intercepts search results                                │
│  • Gets current user's OU                                   │
│  • Filters users based on OU match                          │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                   LdapOuService                              │
│  • Queries ldap_user_mapping table                          │
│  • Extracts OU from DN                                      │
│  • Caches results for performance                           │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│              PostgreSQL/MySQL Database                       │
│         (ldap_user_mapping table)                           │
└─────────────────────────────────────────────────────────────┘
```

### Key Components

#### 1. **OuFilterPlugin** (`lib/Collaboration/OuFilterPlugin.php`)

**Purpose**: Directly intercepts and filters user search results

**How it works**:
1. Receives search results from Nextcloud's sharees API
2. Extracts current user's OU using `LdapOuService`
3. Iterates through each search result
4. Checks if each user's OU matches current user's OU
5. Returns only matching users

**Important Code Sections**:
```php
// Line 38-67: Main search filtering logic
public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
    // Get current user and their OU
    $currentUserId = $this->userSession->getUser()->getUID();
    $currentUserOu = $this->ldapOuService->getUserOu($currentUserId);
    
    // Filter results based on OU match
    return $this->filterSearchResultType($searchResult, 'users', $currentUserId, $currentUserOu);
}
```

#### 2. **LdapOuService** (`lib/Service/LdapOuService.php`)

**Purpose**: Retrieves OU information for users from the database

**How it works**:
1. Queries `ldap_user_mapping` table for user's LDAP DN
2. Extracts OU from DN (e.g., `cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc` → `OU=cyberfirst`)
3. Caches results for performance
4. Handles nested OUs intelligently (skips generic "Mail" OU)

**Important Code Sections**:
```php
// Line 68-100: Database query for LDAP DN
private function getLdapDnViaNextcloud(string $userId): ?string {
    $connection = $this->serverContainer->get(\OCP\IDBConnection::class);
    
    $query = $connection->getQueryBuilder();
    $query->select('ldap_dn')
        ->from('ldap_user_mapping')  // ⚠️ IMPORTANT: Table name
        ->where($query->expr()->eq('owncloud_name', $query->createNamedParameter($userId)));
    
    $result = $query->executeQuery();
    $row = $result->fetch();
    
    return $row['ldap_dn'] ?? null;
}

// Line 114-166: Intelligent OU extraction
private function extractOuFromDn(string $dn): string {
    $dnParts = explode(',', $dn);
    $ouParts = [];
    
    foreach ($dnParts as $part) {
        if (stripos($part, 'OU=') === 0) {
            $ouParts[] = $part;
        }
    }
    
    // Filter out generic "Mail" OU to get specific sub-OU
    $specificOus = array_filter($ouParts, function($ou) {
        $ouValue = strtolower(trim(substr($ou, 3)));
        return $ouValue !== 'mail';
    });
    
    return reset($specificOus);
}
```

#### 3. **Application Bootstrap** (`lib/AppInfo/Application.php`)

**Purpose**: Registers services and hooks the plugin into Nextcloud

**Important Code Sections**:
```php
// Line 61-86: Register the filter plugin
public function boot(IBootContext $context): void {
    $server = $context->getServerContainer();
    
    // Get Collaborators Manager
    $collaboratorsManager = $server->get(\OCP\Collaboration\Collaborators\ISearch::class);
    $ouFilterPlugin = $server->get(OuFilterPlugin::class);
    
    // CRITICAL: Register plugin to intercept searches
    $collaboratorsManager->registerPlugin([
        'shareType' => 'SHARE_TYPE_USER',
        'class' => OuFilterPlugin::class
    ]);
}
```

---

## 🔧 Troubleshooting

### Problem: Users see all users (not filtered)

**Check 1**: Verify the app is enabled
```bash
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter
```

**Check 2**: Check logs for plugin activation
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin"
```

**Check 3**: Test OU detection manually
```bash
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php
```

### Problem: 500 Internal Server Error when searching

**Cause**: The plugin is trying to filter but encountering errors

**Check**: Look for exceptions in the log
```bash
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -i "error\|exception" | grep ldapoufilter
```

**Fix**: The plugin now handles errors gracefully and returns empty results instead of throwing exceptions.

### Problem: "Undefined table: ldap_user_mapping"

**Cause**: Wrong database table name for your database type

**Fix**: The app uses `ldap_user_mapping` (without `oc_` prefix) for PostgreSQL. If you're using MySQL, the table might be different.

Check your actual table:
```bash
cat > /tmp/check_tables.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';
$connection = \OC::$server->get(\OCP\IDBConnection::class);
$result = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%ldap%'");
while ($row = $result->fetch()) {
    echo $row['table_name'] . "\n";
}
EOF

sudo -u www-data php /tmp/check_tables.php
```

If your table has a different name, update `lib/Service/LdapOuService.php` line 77:
```php
->from('your_table_name')  // Change this
```

### Problem: Users in different OUs can still see each other

**Check**: Verify OU extraction is working
```bash
# Test OU extraction for specific users
cat > /tmp/test_ou_extraction.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';

$service = \OC::$server->get(\OCA\LdapOuFilter\Service\LdapOuService::class);

// Test with two known users
$user1 = 'USER_ID_1';
$user2 = 'USER_ID_2';

$ou1 = $service->getUserOu($user1);
$ou2 = $service->getUserOu($user2);

echo "User 1 OU: $ou1\n";
echo "User 2 OU: $ou2\n";
echo "Same OU? " . ($service->areUsersInSameOu($user1, $user2) ? 'YES' : 'NO') . "\n";
EOF

sudo -u www-data php /tmp/test_ou_extraction.php
```

If the OUs are incorrectly extracted, check the DN format in your database and adjust the extraction logic in `extractOuFromDn()`.

---

## 📊 Database Schema Reference

### Table: `ldap_user_mapping`

This table is maintained by Nextcloud's LDAP app and contains the mapping between Nextcloud user IDs and LDAP DNs.

**Structure**:
```sql
- owncloud_name    VARCHAR  -- Nextcloud user ID (UUID)
- ldap_dn          VARCHAR  -- LDAP Distinguished Name
- directory_uuid   VARCHAR  -- Directory UUID
- ldap_dn_hash     VARCHAR  -- Hash of the DN
```

**Example Data**:
```
owncloud_name: EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B
ldap_dn: cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc
```

---

## 🔐 Security Considerations

1. **OU Extraction Logic**: The app intelligently filters out generic OUs like "Mail" to focus on specific sub-OUs. Ensure this matches your LDAP structure.

2. **Error Handling**: The plugin now gracefully handles errors and returns empty results rather than crashing.

3. **Performance**: Results are cached per request to avoid repeated database queries.

4. **Compatibility**: Tested with PostgreSQL. MySQL/MariaDB should work but verify table names.

---

## 📝 Configuration

No configuration needed! The app automatically:
- ✅ Detects users' OUs from LDAP DN
- ✅ Applies filtering to all share-related features
- ✅ Caches results for performance
- ✅ Handles errors gracefully

---

## 🚀 Deployment Checklist

- [ ] Upload app files to `/var/www/nextcloud/apps/ldapoufilter`
- [ ] Set correct permissions (`www-data:www-data`)
- [ ] Enable the app (`occ app:enable ldapoufilter`)
- [ ] Verify app booted successfully (check logs)
- [ ] Test OU detection with test script
- [ ] Test in UI (share a file and search for users)
- [ ] Monitor logs for any errors

---

## 📞 Support

For issues or questions:
1. Check the troubleshooting section
2. Review Nextcloud logs: `/var/www/nextcloud/data/nextcloud.log`
3. Run test scripts to isolate the problem
4. Verify LDAP configuration in Nextcloud Admin settings

---

## 📜 License

This app is provided as-is for domain isolation in Nextcloud environments.

---

**Version**: 1.0  
**Last Updated**: October 2025  
**Compatible With**: Nextcloud 31.0+

