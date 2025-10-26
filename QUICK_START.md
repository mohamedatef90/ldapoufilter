# LDAP OU Filter - Quick Start Guide

## 🚀 Installation (5 minutes)

```bash
# 1. Upload app
scp -r ldapoufilter root@SERVER_IP:/var/www/nextcloud/apps/

# 2. Set permissions
ssh root@SERVER_IP "chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter"

# 3. Enable app
ssh root@SERVER_IP "sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"
```

## ✅ Verify It's Working

```bash
# Check logs
ssh root@SERVER_IP "tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter"

# Expected output:
# {"message":"LDAP OU Filter app booted successfully"}
# {"message":"✓ OU Filter Plugin registered with Collaborators Manager"}
```

## 🧪 Quick Test

```bash
# Create test script
cat > test_quick.php << 'EOF'
<?php
require_once '/var/www/nextcloud/lib/base.php';
$service = \OC::$server->get(\OCA\LdapOuFilter\Service\LdapOuService::class);
echo "User OU: " . $service->getUserOu('USER_ID_HERE') . "\n";
EOF

# Run test
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_quick.php
```

## 🎯 What It Does

- ✅ Users in same OU: Can see each other
- ❌ Users in different OUs: Cannot see each other
- 🔒 Automatic: No user action needed

## 📋 Important Files

| File | Purpose |
|------|---------|
| `lib/Collaboration/OuFilterPlugin.php` | Main filtering logic |
| `lib/Service/LdapOuService.php` | OU detection from database |
| `lib/AppInfo/Application.php` | Service registration |

## 🔍 How It Works

1. User searches in Nextcloud share dialog
2. Plugin intercepts search results
3. Checks each user's OU from database
4. Filters out users from different OUs
5. Returns only same-OU users

## 🛠️ Troubleshooting

**Problem**: All users visible
```bash
# Check if plugin is activated
tail -100 /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin ACTIVATED"
```

**Problem**: 500 errors
```bash
# Check for exceptions
tail -100 /var/www/nextcloud/data/nextcloud.log | grep -i error | grep ldapoufilter
```

**Problem**: Wrong OUs extracted
```bash
# Check actual LDAP DNs
sudo -u www-data php /tmp/check_db.php
```

## 📞 Support

- Full guide: `INSTALLATION_GUIDE.md`
- Deployment details: `DEPLOYMENT_SUMMARY.md`
- Logs: `/var/www/nextcloud/data/nextcloud.log`

---

**TL;DR**: Upload → Enable → Done! Users only see others in their OU.

