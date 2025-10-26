# LDAP OU Filter - Complete Documentation

## 📖 Table of Contents

1. [Quick Start](#quick-start)
2. [How It Works](#how-it-works)
3. [Installation](#installation)
4. [Testing](#testing)
5. [Troubleshooting](#troubleshooting)
6. [Development](#development)

---

## ⚡ Quick Start

### What This App Does

**Problem**: Nextcloud shows ALL LDAP users to everyone, regardless of organizational boundaries.

**Solution**: This app automatically filters user search results to show only users from the same Organizational Unit (OU).

**Example**:
- User in `OU=cyberfirst` sees only `cyberfirst` users
- User in `OU=bebo` sees only `bebo` users
- User in `OU=mail` sees only `mail` users

### Installation (3 Steps)

```bash
# 1. Upload to server
scp -r ldapoufilter root@SERVER:/var/www/nextcloud/apps/

# 2. Set permissions
ssh root@SERVER "chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter"

# 3. Enable app
ssh root@SERVER "sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"
```

### Verify It Works

```bash
# Check logs
tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter

# Expected output:
# ✓ LDAP OU Filter app booted successfully
# ✓ OU Filter Plugin registered with Collaborators Manager

# Test in UI
# Login → Files → Share → Search for users
# You should ONLY see users from your OU
```

---

## 🔧 How It Works

### Architecture Overview

```
┌──────────────────────────────────────┐
│     Nextcloud Sharees API            │
│   (User Search & Sharing Dialog)      │
└──────────────┬───────────────────────┘
                │
                ▼
┌──────────────────────────────────────┐
│      OuFilterPlugin                  │
│  • Intercepts search requests         │
│  • Gets current user's OU             │
│  • Filters results by OU match        │
└──────────────┬───────────────────────┘
                │
                ▼
┌──────────────────────────────────────┐
│      LdapOuService                    │
│  • Queries database for user DN       │
│  • Extracts OU from DN                │
│  • Caches results for performance     │
└──────────────┬───────────────────────┘
                │
                ▼
┌──────────────────────────────────────┐
│  PostgreSQL/MySQL Database            │
│  Table: ldap_user_mapping             │
└───────────────────────────────────────┘
```

### Data Flow

1. **User Action**: User types "john" in share dialog
2. **Nextcloud**: Calls `/ocs/v2.php/apps/files_sharing/api/v1/sharees?search=john`
3. **OuFilterPlugin**: Intercepts the request
4. **Get Current User OU**:
   ```php
   $currentUserId = $userSession->getUser()->getUID();
   $currentUserOu = $ldapOuService->getUserOu($currentUserId);
   // Returns: "OU=cyberfirst"
   ```
5. **Filter Results**:
   ```php
   foreach ($results as $result) {
       $userOu = $ldapOuService->getUserOu($userId);
       if ($userOu === $currentUserOu) {
           $filteredResults[] = $result; // Keep
       }
       // else: remove
   }
   ```
6. **Return**: Only users in same OU are returned to UI

---

## 📁 Key Files Explained

### 1. `lib/Collaboration/OuFilterPlugin.php`

**Purpose**: Main filtering logic - hooks into Nextcloud's search system

**Key Method**: `search()`
```php
public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
    // Get current user
    $currentUser = $this->userSession->getUser();
    
    // Get current user's OU
    $currentUserOu = $this->ldapOuService->getUserOu($currentUserId);
    
    // Filter results
    return $this->filterSearchResultType($searchResult, 'users', $currentUserId, $currentUserOu);
}
```

**What It Does**:
- Receives all search results from Nextcloud
- Compares each user's OU with current user's OU
- Returns only matching users

### 2. `lib/Service/LdapOuService.php`

**Purpose**: Retrieves OU information from database

**Key Method**: `getUserOu()`
```php
public function getUserOu(string $userId): ?string {
    // Check cache first
    if (isset($this->ouCache[$userId])) {
        return $this->ouCache[$userId];
    }
    
    // Query database for LDAP DN
    $dn = $this->getLdapDnViaNextcloud($userId);
    // Example: "cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc"
    
    // Extract OU from DN
    $ou = $this->extractOuFromDn($dn);
    // Returns: "OU=cyberfirst"
    
    return $ou;
}
```

**Database Query**:
```php
// lib/Service/LdapOuService.php line 75-78
$query = $connection->getQueryBuilder();
$query->select('ldap_dn')
    ->from('ldap_user_mapping')  // ⚠️ Table name
    ->where($query->expr()->eq('owncloud_name', $userId));
```

**OU Extraction Logic**:
```php
// lib/Service/LdapOuService.php line 114-166
private function extractOuFromDn(string $dn): string {
    // Parse DN: "cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc"
    $dnParts = explode(',', $dn);
    
    // Find all OUs
    $ouParts = [];
    foreach ($dnParts as $part) {
        if (stripos($part, 'OU=') === 0) {
            $ouParts[] = $part;  // ["OU=cyberfirst", "OU=mail"]
        }
    }
    
    // Filter out generic "Mail" OU
    $specificOus = array_filter($ouParts, function($ou) {
        return strtolower(trim(substr($ou, 3))) !== 'mail';
    });
    
    // Return most specific OU: "OU=cyberfirst"
    return reset($specificOus);
}
```

### 3. `lib/AppInfo/Application.php`

**Purpose**: Registers the plugin with Nextcloud

**Key Method**: `boot()`
```php
public function boot(IBootContext $context): void {
    $collaboratorsManager = $server->get(\OCP\Collaboration\Collaborators\ISearch::class);
    
    // Register plugin with Nextcloud
    $collaboratorsManager->registerPlugin([
        'shareType' => 'SHARE_TYPE_USER',
        'class' => OuFilterPlugin::class
    ]);
}
```

---

## 📦 Installation

### Prerequisites

- Nextcloud 31.0+
- LDAP/Active Directory backend configured
- Users synced from LDAP
- PHP 8.0+

### Step-by-Step

#### 1. Upload Files

```bash
# Option A: SCP
scp -r ldapoufilter root@YOUR_SERVER:/var/www/nextcloud/apps/

# Option B: Git
ssh root@YOUR_SERVER
cd /var/www/nextcloud/apps
git clone YOUR_REPO_URL ldapoufilter
```

#### 2. Set Permissions

```bash
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
chmod -R 755 /var/www/nextcloud/apps/ldapoufilter
```

#### 3. Enable App

```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

#### 4. Verify

```bash
# Check app status
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# Check logs
tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

---

## 🧪 Testing

### Test 1: OU Detection

Create a test script:

```bash
cat > /var/www/nextcloud/apps/ldapoufilter/test_ou.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';

$service = \OC::$server->get(\OCA\LdapOuFilter\Service\LdapOuService::class);

// Test with known users
$user1 = 'EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B'; // hunter1
$user2 = '8162AF93-5C6E-4DA9-84EC-C3BF7BFFA736'; // bebo 01

$ou1 = $service->getUserOu($user1);
$ou2 = $service->getUserOu($user2);

echo "User 1 OU: $ou1\n";
echo "User 2 OU: $ou2\n";
echo "Same OU? " . ($service->areUsersInSameOu($user1, $user2) ? 'YES' : 'NO') . "\n";
EOF

# Run test
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php
```

**Expected Output**:
```
User 1 OU: OU=cyberfirst
User 2 OU: OU=bebo
Same OU? NO
```

### Test 2: UI Test

1. Login as user in `cyberfirst` OU
2. Navigate to Files
3. Click Share on any file
4. Search for users
5. **Verify**: Only users from `cyberfirst` OU appear

---

## 🐛 Troubleshooting

### Problem: All Users Visible (No Filtering)

**Debug Steps**:

1. **Check if app is enabled**:
   ```bash
   sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter
   ```

2. **Check logs for plugin activation**:
   ```bash
   tail -100 /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin"
   ```
   Look for: `"✓ OU Filter Plugin registered"`

3. **Test OU detection**:
   ```bash
   sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php
   ```

**Solution**: If plugin not in logs, re-enable the app:
```bash
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

### Problem: 500 Internal Server Error

**Symptoms**: Search returns 500 error

**Debug**:
```bash
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -i "error\|exception" | grep ldapoufilter
```

**Solutions**:
- Plugin now handles errors gracefully
- Check for database connection issues
- Verify `ldap_user_mapping` table exists

### Problem: Wrong Table Name

**Symptoms**: `SQLSTATE[42P01]: Undefined table: ldap_user_mapping`

**Find Correct Table**:
```bash
cat > /tmp/find_table.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';
$connection = \OC::$server->get(\OCP\IDBConnection::class);
$result = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%ldap%'");
while ($row = $result->fetch()) {
    echo $row['table_name'] . "\n";
}
EOF

sudo -u www-data php /tmp/find_table.php
```

**Fix**: Update `lib/Service/LdapOuService.php` line 77:
```php
->from('your_actual_table_name')
```

### Problem: Wrong OU Extracted

**Symptoms**: Users see wrong OU or all users still visible

**Debug**:
```bash
# Check actual DN format
cat > /tmp/check_dns.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';
$connection = \OC::$server->get(\OCP\IDBConnection::class);
$query = $connection->getQueryBuilder();
$query->select('*')->from('ldap_user_mapping')->setMaxResults(5);
$result = $query->executeQuery();
while ($row = $result->fetch()) {
    echo $row['owncloud_name'] . " -> " . $row['ldap_dn'] . "\n";
}
EOF

sudo -u www-data php /tmp/check_dns.php
```

**Fix**: Adjust `extractOuFromDn()` logic in `lib/Service/LdapOuService.php` based on your DN structure.

---

## 🚀 Development

### Project Structure

```
ldapoufilter/
├── appinfo/
│   ├── info.xml          # App metadata
│   └── routes.php        # Routes (empty)
├── lib/
│   ├── AppInfo/
│   │   └── Application.php        # Bootstrap & service registration
│   ├── Collaboration/
│   │   └── OuFilterPlugin.php     # Main filtering plugin
│   ├── Hooks/
│   │   └── TalkHooks.php          # Talk-specific hooks
│   ├── Listener/
│   │   └── UserSearchListener.php # Event listener
│   └── Service/
│       └── LdapOuService.php      # OU retrieval service
├── install.sh           # Installation script
├── test.sh              # Test script
└── README.md
```

### Adding New Features

To add filtering for groups:

1. **Extend OuFilterPlugin**:
   ```php
   // lib/Collaboration/OuFilterPlugin.php
   public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
       // ... existing code ...
       
       // Add group filtering
       $this->filterSearchResultType($searchResult, 'groups', $currentUserId, $currentUserOu);
       
       return false;
   }
   ```

2. **Add Group OU Retrieval**:
   ```php
   // lib/Service/LdapOuService.php
   public function getGroupOu(string $groupId): ?string {
       // Similar to getUserOu but for groups
   }
   ```

### Modifying OU Extraction

Current logic in `extractOuFromDn()`:
- Filters out generic "Mail" OU
- Returns most specific sub-OU

To change:
```php
// lib/Service/LdapOuService.php line 141-144
$specificOus = array_filter($ouParts, function($ou) {
    $ouValue = strtolower(trim(substr($ou, 3)));
    return $ouValue !== 'mail';  // Change this filter
});
```

---

## 📊 Performance

### Caching

Results are cached per request in `$this->ouCache`:
- ✅ No repeated database queries for same user
- ✅ Instant OU lookups after first query
- ⚠️ Cache cleared after each request

### Database Queries

- One query per unique user per request
- Index on `owncloud_name` improves performance
- Use QueryBuilder for SQL injection protection

---

## ✅ Success Checklist

- [ ] App enabled (`occ app:list`)
- [ ] Plugin registered (check logs)
- [ ] OU detection working (test script)
- [ ] UI filtering working (test in share dialog)
- [ ] No errors in logs
- [ ] Users only see same-OU users

---

## 📚 References

- **Nextcloud API**: https://docs.nextcloud.com/server/latest/developer_manual/
- **LDAP Integration**: Nextcloud LDAP app configuration
- **Collaborators System**: `OCP\Collaboration\Collaborators\ISearch`

---

## 📝 License

Provided as-is for domain isolation in Nextcloud environments.

**Version**: 1.0  
**Nextcloud**: 31.0+  
**Last Updated**: October 2025

