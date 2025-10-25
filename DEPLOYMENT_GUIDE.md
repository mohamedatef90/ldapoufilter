# LDAP OU Filter - Deployment Guide

## 🎯 Quick Fix Deployment

This guide will help you deploy the fixed version of the LDAP OU Filter app to your Nextcloud server.

---

## 📋 Prerequisites

Before starting, ensure you have:
- SSH access to your Nextcloud server
- Root or sudo privileges
- Nextcloud 31 installed at `/var/www/nextcloud`
- LDAP/Active Directory configured and connected
- PHP LDAP extension installed

---

## 🚀 Deployment Steps

### Step 1: Upload Fixed Files to Server

From your local machine (where you have the files):

```bash
# Option A: Using SCP
scp -r /Users/roaya/Roaya-files/Development/nxtcloud/NC-Domain\ isolation/ldapoufilter root@YOUR_SERVER_IP:/tmp/

# Option B: Using rsync (recommended - faster for updates)
rsync -avz --exclude '.git' \
  /Users/roaya/Roaya-files/Development/nxtcloud/NC-Domain\ isolation/ldapoufilter/ \
  root@YOUR_SERVER_IP:/tmp/ldapoufilter/
```

### Step 2: SSH into Your Server

```bash
ssh root@YOUR_SERVER_IP
```

### Step 3: Backup Existing App (if installed)

```bash
# Backup current version
cd /var/www/nextcloud/apps/
if [ -d "ldapoufilter" ]; then
    sudo cp -r ldapoufilter ldapoufilter.backup.$(date +%Y%m%d_%H%M%S)
    echo "Backup created"
fi
```

### Step 4: Install/Update the App

```bash
# Remove old version
sudo rm -rf /var/www/nextcloud/apps/ldapoufilter

# Copy new version
sudo cp -r /tmp/ldapoufilter /var/www/nextcloud/apps/

# Set correct permissions
sudo chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
sudo chmod -R 755 /var/www/nextcloud/apps/ldapoufilter

# Make scripts executable
sudo chmod +x /var/www/nextcloud/apps/ldapoufilter/*.sh
```

### Step 5: Restart the App

```bash
# Navigate to app directory
cd /var/www/nextcloud/apps/ldapoufilter

# Disable the app
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter

# Clear cache
sudo -u www-data php /var/www/nextcloud/occ cache:clear

# Enable the app
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

### Step 6: Enable Debug Logging

```bash
# Set log level to debug (0)
sudo -u www-data php /var/www/nextcloud/occ config:system:set loglevel --value=0
```

---

## ✅ Verification

### Run the Test Script

```bash
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash test_filter.sh
```

This will check:
- ✓ App installation
- ✓ App enabled status
- ✓ LDAP configuration
- ✓ LDAP connection
- ✓ PHP LDAP extension
- ✓ Log level settings
- ✓ Recent app activity

### Monitor Logs in Real-Time

Open a second terminal and run:

```bash
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash check_logs.sh -f
```

Keep this running while you test the app.

---

## 🧪 Testing the Filter

### Test 1: File Sharing

1. Log in to Nextcloud as a user from OU A (e.g., user from OU=Mail)
2. Go to Files and select any file
3. Click Share → Share with users
4. Start typing to search for users
5. **Expected Result**: You should ONLY see users from the same OU (OU=Mail)

### Test 2: Nextcloud Talk Mentions

1. Open Nextcloud Talk
2. Start a conversation
3. Type `@` to mention someone
4. **Expected Result**: You should ONLY see users from the same OU

### Test 3: Check Logs

In the terminal where logs are running, you should see:

```
[INFO] LDAP OU Filter app booted successfully
[INFO] UserSearchListener triggered
[INFO] Starting to filter search results for user: username
[DEBUG] User username is in OU: OU=Mail
[DEBUG] Filtered search results: 15 -> 5 users
```

---

## 🔍 Troubleshooting

### Problem: No logs appear

**Solution:**
```bash
# Check if app is properly enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# Re-enable the app
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# Clear all caches
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

### Problem: All users still visible (no filtering)

**Check 1 - Verify LDAP connection:**
```bash
sudo -u www-data php /var/www/nextcloud/occ ldap:test-config s01
```

**Check 2 - Verify PHP LDAP extension:**
```bash
php -m | grep ldap
# If not found, install it:
sudo apt-get install php-ldap
sudo systemctl restart apache2  # or php-fpm
```

**Check 3 - Check OU extraction:**
```bash
# Look in logs for OU information
sudo bash check_logs.sh -a | grep "Selected OU"
```

If you see "No OU found in DN", you may need to adjust the OU extraction logic.

### Problem: PHP errors in logs

**Solution:**
```bash
# Check PHP error logs
sudo tail -f /var/log/apache2/error.log
# or
sudo tail -f /var/log/php*.log

# Check Nextcloud logs for errors
sudo bash check_logs.sh -e
```

---

## 🔧 Configuration Options

### Changing OU Level

By default, the app filters by the **first (immediate) OU**. To change this:

Edit `/var/www/nextcloud/apps/ldapoufilter/lib/Service/LdapOuService.php`:

```php
// Around line 198, find:
$selectedOu = $ouParts[0];  // First OU (e.g., OU=Mail)

// To use parent OU instead:
$selectedOu = isset($ouParts[1]) ? $ouParts[1] : $ouParts[0];  // e.g., OU=cyberfirst

// To use full OU path:
$selectedOu = implode(',', $ouParts);  // e.g., OU=Mail,OU=cyberfirst
```

After changing, restart the app:
```bash
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

---

## 📊 Useful Commands

### View all logs
```bash
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash check_logs.sh -a
```

### View last 100 log entries
```bash
sudo bash check_logs.sh -l 100
```

### View only errors
```bash
sudo bash check_logs.sh -e
```

### Follow logs in real-time
```bash
sudo bash check_logs.sh -f
```

### Test LDAP user search
```bash
sudo -u www-data php /var/www/nextcloud/occ ldap:search "username"
```

### Clear OU cache (if needed)
```bash
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

---

## 📝 What Was Fixed

### Main Issue
The `UserSearchListener` event listener wasn't receiving its dependencies (`LdapOuService`, `IUserSession`, `LoggerInterface`), causing it to fail silently without any error messages.

### The Fix
Registered `UserSearchListener` as a service with proper dependency injection in `lib/AppInfo/Application.php`:

```php
// Added factory function for UserSearchListener
$context->registerService(UserSearchListener::class, function(IServerContainer $c) {
    return new UserSearchListener(
        $c->get(LdapOuService::class),
        $c->get(\OCP\IUserSession::class),
        $c->get(\Psr\Log\LoggerInterface::class)
    );
});
```

This ensures Nextcloud properly injects all required dependencies when the listener is instantiated.

---

## 🎉 Success Indicators

You'll know it's working when:

1. ✅ Logs show "UserSearchListener triggered"
2. ✅ Logs show "Starting to filter search results"
3. ✅ Logs show "Filtered search results: X -> Y users"
4. ✅ Search results only show users from the same OU
5. ✅ File sharing suggestions are filtered
6. ✅ Talk mentions are filtered

---

## 📞 Need Help?

If you encounter issues:

1. Run the test script: `sudo bash test_filter.sh`
2. Check logs: `sudo bash check_logs.sh -e`
3. Review the troubleshooting section above
4. Check Nextcloud logs: `/var/www/nextcloud/data/nextcloud.log`

---

## 🔐 Security Notes

- Always test on a staging environment first if possible
- Keep backups before making changes
- Review logs for any sensitive information before sharing
- Set log level back to 2 (warnings) after debugging:
  ```bash
  sudo -u www-data php /var/www/nextcloud/occ config:system:set loglevel --value=2
  ```

