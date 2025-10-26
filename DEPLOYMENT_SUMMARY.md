# LDAP OU Filter - Deployment Summary

## 🎯 Purpose

This app restricts Nextcloud sharing to users within the same Organizational Unit (OU) in Active Directory/LDAP.

---

## 📁 Critical Files & Their Roles

### 1. **lib/Collaboration/OuFilterPlugin.php** ⭐ PRIMARY FILTER
**Role**: Intercepts user searches and filters results based on OU

**Key Functions**:
- `search()` - Main entry point for filtering
- `filterSearchResultType()` - Filters users by OU match

**What it does**:
1. Gets current user's OU from `LdapOuService`
2. Iterates through search results
3. Keeps only users in the same OU
4. Returns filtered results to Nextcloud UI

**Critical Lines**:
```php
52: $currentUserOu = $this->ldapOuService->getUserOu($currentUserId);
120: $userOu = $this->ldapOuService->getUserOu($userId);
123: if ($userOu && $userOu === $currentUserOu) {
```

---

### 2. **lib/Service/LdapOuService.php** ⭐ OU RETRIEVAL
**Role**: Queries database to get user OUs from LDAP DNs

**Key Functions**:
- `getUserOu()` - Get OU for a user (cached)
- `getLdapDnViaNextcloud()` - Query database for DN
- `extractOuFromDn()` - Extract OU from DN string
- `areUsersInSameOu()` - Compare two users' OUs

**What it does**:
1. Queries `ldap_user_mapping` table
2. Retrieves LDAP DN (e.g., `cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc`)
3. Extracts specific OU (filters out generic "Mail")
4. Caches results for performance

**Critical Lines**:
```php
77: ->from('ldap_user_mapping')  // Database table
114-166: // Intelligent OU extraction logic
```

**Database Table**: `ldap_user_mapping`
- Column: `owncloud_name` (user UUID)
- Column: `ldap_dn` (Full DN)

---

### 3. **lib/AppInfo/Application.php** ⭐ REGISTRATION & BOOTSTRAP
**Role**: Registers services and hooks plugin into Nextcloud

**Key Functions**:
- `register()` - Register dependency injection
- `boot()` - Register plugin with Collaborators Manager

**What it does**:
1. Registers `OuFilterPlugin`, `LdapOuService`, `UserSearchListener`
2. Hooks `OuFilterPlugin` into Nextcloud's search system
3. Logs boot status

**Critical Lines**:
```php
71: $collaboratorsManager = $server->get(\OCP\Collaboration\Collaborators\ISearch::class);
75-78: // Register plugin with Nextcloud
```

---

### 4. **appinfo/info.xml** - App Metadata
**Role**: Defines app name, version, dependencies

---

### 5. **appinfo/routes.php** - Routing (Empty)
**Role**: No routes needed (app is service-based)

---

## 🔄 How Data Flows

```
User searches for "john" in share dialog
         ↓
Nextcloud calls OuFilterPlugin.search()
         ↓
OuFilterPlugin gets current user's OU
         ↓ (queries database)
LdapOuService.getUserOu(currentUserId)
         ↓ (extracts from DN)
Returns: "OU=cyberfirst"
         ↓
OuFilterPlugin filters results
         ↓
For each user in results:
  - Get user's OU
  - If OU matches current user's OU → keep
  - If OU different → remove
         ↓
Return filtered list to Nextcloud UI
         ↓
User only sees people in their OU!
```

---

## 🗄️ Database Dependencies

**Required Table**: `ldap_user_mapping`

**Structure**:
- `owncloud_name` (VARCHAR) - Nextcloud user UUID
- `ldap_dn` (VARCHAR) - LDAP Distinguished Name
- `directory_uuid` (VARCHAR) - Directory UUID
- `ldap_dn_hash` (VARCHAR) - DN hash

**Example Row**:
```
owncloud_name: EE52A1C2-9BA9-45C4-B5B2-3AD30E2BB96B
ldap_dn: cn=hunter1,ou=cyberfirst,ou=mail,dc=frist,dc=loc
```

**Created By**: Nextcloud LDAP app
**Used By**: This app to get user OU

---

## 🚨 Common Issues & Solutions

### Issue 1: Users see all users (not filtered)

**Symptoms**:
- All LDAP users appear in search
- No filtering happening

**Debug**:
```bash
# Check if app is enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# Check logs for plugin activation
tail -100 /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin"

# Test OU detection
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php
```

**Solution**: Verify plugin is registered in logs. If not, re-enable app.

---

### Issue 2: 500 Error when searching

**Symptoms**:
- Search returns 500 Internal Server Error
- No results shown

**Debug**:
```bash
# Check for exceptions
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -A 10 "Error filtering"

# Test database query
sudo -u www-data php /tmp/check_db.php
```

**Solution**: Plugin now handles errors gracefully. Check logs for specific exceptions.

---

### Issue 3: Wrong table name

**Symptoms**:
```
SQLSTATE[42P01]: Undefined table: ldap_user_mapping
```

**Solution**: Find your actual table name:
```bash
cat > /tmp/find_ldap_table.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';
$connection = \OC::$server->get(\OCP\IDBConnection::class);
$result = $connection->query("SELECT table_name FROM information_schema.tables WHERE table_name LIKE '%ldap%'");
while ($row = $result->fetch()) {
    echo $row['table_name'] . "\n";
}
EOF

sudo -u www-data php /tmp/find_ldap_table.php
```

Then update `lib/Service/LdapOuService.php` line 77 to match your table name.

---

## 🧪 Testing Scripts

### test_ou_server.php
Tests OU detection for specific users.
Run: `sudo -u www-data php test_ou_server.php`

### test_ldap_direct.php
Tests direct database queries.
Run: `sudo -u www-data php test_ldap_direct.php`

### test_ou.php (from installation guide)
Quick OU check for specific users.
Run: `sudo -u www-data php test_ou.php`

---

## 📊 Performance Optimization

1. **Caching**: OU results cached in `LdapOuService::$ouCache`
2. **Single DB Query**: One query per user per request
3. **Early Returns**: Exits early if no current user

---

## 🔧 Customization

### To change OU extraction logic:

Edit `lib/Service/LdapOuService.php` function `extractOuFromDn()` (lines 114-166)

Current logic:
- Filters out "Mail" OU
- Returns most specific OU

To modify: Adjust the filtering logic based on your LDAP structure.

---

## 📝 Deployment Command

```bash
# Quick deployment
scp -r lib/Collaboration/OuFilterPlugin.php lib/Service/LdapOuService.php lib/AppInfo/Application.php root@SERVER:/var/www/nextcloud/apps/ldapoufilter/
ssh root@SERVER "cd /var/www/nextcloud/apps/ldapoufilter && chown -R www-data:www-data lib/"
ssh root@SERVER "sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter && sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"
```

---

## ✅ Verification Commands

```bash
# 1. Check app is enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# 2. Check logs
tail -100 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter

# 3. Test OU detection
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php

# 4. Test in UI
# Login → Files → Share button → Search for users
```

---

## 🎯 Success Criteria

✅ App is enabled (`app:list` shows it)
✅ Logs show "LDAP OU Filter app booted successfully"
✅ Logs show "✓ OU Filter Plugin registered"
✅ User searches only show same-OU users
✅ No 500 errors in logs or UI
✅ Test script shows correct OU extraction

---

**Version**: 1.0  
**Last Updated**: October 2025  
**Nextcloud Version**: 31.0+

