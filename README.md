# LDAP OU Filter for Nextcloud

## 🎯 What This Does

Automatically filters Nextcloud user search results to show **only users from the same Organizational Unit (OU)** in LDAP/Active Directory.

**Example**: Users in `cyberfirst` OU will only see and be able to share with other `cyberfirst` users, not users from `bebo` or other OUs.

---

## ⚡ Quick Start (3 Steps)

```bash
# 1. Upload to server
scp -r ldapoufilter root@YOUR_SERVER:/var/www/nextcloud/apps/

# 2. Set permissions and enable
ssh root@YOUR_SERVER "chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter && sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"

# 3. Verify
ssh root@YOUR_SERVER "tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter"
```

Expected output:
```
LDAP OU Filter app booted successfully
✓ OU Filter Plugin registered with Collaborators Manager
```

**Done!** The app is now filtering users by OU.

---

## 📚 Documentation

| Document | Purpose | Read Time |
|----------|---------|-----------|
| **[START_HERE.md](START_HERE.md)** | Overview & navigation | 2 min |
| **[QUICK_START.md](QUICK_START.md)** | Fast installation | 5 min |
| **[INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)** | Complete setup | 15 min |
| **[DEPLOYMENT_SUMMARY.md](DEPLOYMENT_SUMMARY.md)** | Technical details | 30 min |
| **[README_COMPLETE.md](README_COMPLETE.md)** | Full reference | 60 min |
| **[DELIVERY_PACKAGE.md](DELIVERY_PACKAGE.md)** | What's included | 10 min |

**Start with**: [START_HERE.md](START_HERE.md)

---

## ✅ Features

- ✅ **Automatic**: No configuration needed
- ✅ **Transparent**: Works in background
- ✅ **Fast**: Database-backed with caching
- ✅ **Secure**: OU-based organizational boundaries
- ✅ **Error-Resilient**: Handles edge cases gracefully
- ✅ **Universal**: Works in Files, Talk, and all sharing features

---

## 🔧 How It Works

```
User searches for "john" in share dialog
         ↓
Plugin intercepts search results
         ↓
Queries database for user OUs
         ↓
Filters: same OU = keep, different OU = remove
         ↓
Returns filtered list to UI
```

**Database**: Uses `ldap_user_mapping` table (created by Nextcloud LDAP app)  
**Query**: Gets LDAP DN, extracts OU from DN  
**Filter**: Compares current user's OU with searched users' OUs

---

## 📋 Requirements

- Nextcloud 31.0 or later
- PHP 8.0 or later  
- LDAP/Active Directory configured
- PostgreSQL or MySQL/MariaDB
- LDAP users already synced to Nextcloud

---

## 🧪 Testing

```bash
# Quick test
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou_server.php

# UI test
# Login → Files → Share → Search for users
# You should only see users from your OU
```

---

## 🐛 Troubleshooting

### All users still visible?
```bash
# Check app is enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# Check plugin activation
tail -100 /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin"
```

### 500 errors?
```bash
# Check logs
tail -100 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

**For detailed troubleshooting**: See [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md) - Troubleshooting section

---

## 📁 Project Structure

```
ldapoufilter/
├── lib/
│   ├── AppInfo/Application.php         # Bootstrap
│   ├── Collaboration/OuFilterPlugin.php  # Main filter ⭐
│   └── Service/LdapOuService.php       # OU retrieval ⭐
├── appinfo/
│   ├── info.xml
│   └── routes.php
└── Documentation (6 files)
```

---

## 🎯 Key Files

**lib/Collaboration/OuFilterPlugin.php**
- Intercepts search results
- Filters users by OU match
- Returns filtered list to UI

**lib/Service/LdapOuService.php**
- Queries `ldap_user_mapping` table
- Extracts OU from LDAP DN
- Caches results for performance

**lib/AppInfo/Application.php**
- Registers services
- Hooks plugin into Nextcloud's Collaborators Manager

---

## 📝 Installation Checklist

- [ ] Upload files to `/var/www/nextcloud/apps/ldapoufilter`
- [ ] Set permissions (`chown -R www-data:www-data`)
- [ ] Enable app (`occ app:enable ldapoufilter`)
- [ ] Verify in logs
- [ ] Test in UI

---

## 🚀 What Was Fixed (v1.1)

- ✅ Fixed database table name (`ldap_user_mapping`)
- ✅ Proper error handling (no 500 errors)
- ✅ Works with Talk autocomplete
- ✅ Handles empty search results gracefully
- ✅ Improved OU extraction logic

---

## 📞 Support

1. **Quick issues**: [QUICK_START.md](QUICK_START.md)
2. **Detailed help**: [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)
3. **Technical details**: [DEPLOYMENT_SUMMARY.md](DEPLOYMENT_SUMMARY.md)

---

## 📜 License

AGPL-3.0

---

**Version**: 1.1  
**Status**: ✅ Production Ready  
**Tested**: Nextcloud 31.0+  
**Last Updated**: October 2025

---

**Need help?** Start with [START_HERE.md](START_HERE.md)
