# Manual Deployment Guide

## Issue Fixed
- ❌ `getLDAPUserByLoginName()` method doesn't exist in User_Proxy class
- ✅ Now uses direct LDAP connection with proper configuration
- ✅ Should fix both user search and Talk errors

## Files to Update

### 1. Upload LdapOuService.php

On your server, run:

```bash
# Navigate to the app directory
cd /var/www/nextcloud/apps/ldapoufilter

# Backup the current file
cp lib/Service/LdapOuService.php lib/Service/LdapOuService.php.backup

# Create the new file
nano lib/Service/LdapOuService.php
```

Replace the entire file with the fixed version (the file has already been updated).

Or copy from the fixed file:

```bash
# If you have the fixed file from your development machine
scp lib/Service/LdapOuService.php root@your-server:/var/www/nextcloud/apps/ldapoufilter/lib/Service/
```

### 2. Clear Cache

```bash
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

### 3. Restart Services

```bash
systemctl reload apache2
```

## What Changed

The `getLdapDnViaNextcloud()` method now:

1. **Gets LDAP configuration properly** - Reads from Nextcloud's config using the correct format (`s01ldap_host`, etc.)
2. **Finds the active configuration** - Loops through LDAP server IDs to find the active one
3. **Uses direct LDAP connection** - Connects directly to LDAP with proper credentials
4. **Searches for users** - Tries multiple LDAP attributes (uid, sAMAccountName, cn, mail, etc.)
5. **Closes connections properly** - Ensures LDAP connections are closed after use

## Testing

After deployment, test with:

```bash
# Check logs
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter

# Try searching in Nextcloud UI
# - Share a file and type a username
# - Search in Talk for users
```

## Expected Results

- ✅ No more "Call to undefined method" errors
- ✅ Users should appear in search results when in same OU
- ✅ No more 500 errors in Talk
- ✅ OU filtering should work correctly

## If Still Having Issues

Check the logs for specific errors:

```bash
# Check for LDAP connection issues
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -i ldap

# Check for configuration issues
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -i "ldap config"
```
