# 🚀 Quick Start Guide

## The Problem is Fixed! 

The issue was that the `UserSearchListener` wasn't receiving its dependencies, so it couldn't filter users. This has been fixed.

---

## ⚡ Super Quick Deploy (Recommended)

From your **LOCAL MACHINE** (where you have these files):

```bash
cd "/Users/roaya/Roaya-files/Development/nxtcloud/NC-Domain isolation/ldapoufilter"
chmod +x deploy_to_server.sh
./deploy_to_server.sh
```

This will:
1. Ask for your server IP
2. Upload all files
3. Install/update the app
4. Enable debug logging
5. Ready to test!

---

## 🔧 Manual Deploy (Alternative)

### On Your Local Machine

```bash
# Upload files to server
scp -r "/Users/roaya/Roaya-files/Development/nxtcloud/NC-Domain isolation/ldapoufilter" \
  root@YOUR_SERVER_IP:/tmp/
```

### On Your Server

```bash
# SSH into server
ssh root@YOUR_SERVER_IP

# Navigate to temp folder
cd /tmp/ldapoufilter

# Run update script
bash update.sh
```

---

## ✅ Test It

### On your server:

```bash
cd /var/www/nextcloud/apps/ldapoufilter

# Run test script
bash test_filter.sh
```

### Monitor logs (open in another terminal):

```bash
cd /var/www/nextcloud/apps/ldapoufilter
bash check_logs.sh -f
```

### Test in Nextcloud:

1. Login to Nextcloud
2. Go to Files
3. Try to share a file
4. Search for users
5. You should ONLY see users from your OU!

---

## 📊 What You Should See in Logs

```
[INFO] LDAP OU Filter app booted successfully
[INFO] UserSearchListener triggered
[INFO] Starting to filter search results for user: bebo
[DEBUG] User bebo is in OU: OU=Mail
[DEBUG] Filtered search results: 20 -> 7 users
```

---

## ❓ Troubleshooting

### No logs appearing?

```bash
# Re-enable the app
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

### Still seeing all users?

```bash
# Check LDAP connection
sudo -u www-data php /var/www/nextcloud/occ ldap:test-config s01

# Check PHP LDAP extension
php -m | grep ldap

# View errors only
bash check_logs.sh -e
```

---

## 📖 More Help

- **Detailed instructions**: See `DEPLOYMENT_GUIDE.md`
- **Check logs**: `bash check_logs.sh -h`
- **Run tests**: `bash test_filter.sh`

---

## 🎯 What Was Fixed

**The Problem:**
```php
// Old code - dependencies not injected ❌
$context->registerEventListener(
    SearchResultEvent::class,
    UserSearchListener::class
);
```

**The Fix:**
```php
// New code - proper dependency injection ✅
$context->registerService(UserSearchListener::class, function($c) {
    return new UserSearchListener(
        $c->get(LdapOuService::class),
        $c->get(\OCP\IUserSession::class),
        $c->get(\Psr\Log\LoggerInterface::class)
    );
});

$context->registerEventListener(
    SearchResultEvent::class,
    UserSearchListener::class
);
```

Now the listener gets all its dependencies and can properly filter users! 🎉

