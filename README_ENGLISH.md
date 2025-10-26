# LDAP OU Filter for Nextcloud

## 🎯 What This App Does

Automatically restricts Nextcloud sharing to users within the same **Organizational Unit (OU)** in LDAP/Active Directory.

**Problem**: By default, Nextcloud shows ALL LDAP users to everyone.

**Solution**: This app filters search results so users only see others from their specific OU.

**Example**:
- User in `cyberfirst` OU → sees only `cyberfirst` users
- User in `bebo` OU → sees only `bebo` users  
- User in `mail` OU → sees only `mail` users

---

## ⚡ Quick Installation

```bash
# 1. Upload to server
scp -r ldapoufilter root@SERVER:/var/www/nextcloud/apps/

# 2. Set permissions
ssh root@SERVER "chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter"

# 3. Enable
ssh root@SERVER "sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"
```

**Verify**:
```bash
tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
# Should show: "LDAP OU Filter app booted successfully"
```

---

## 📚 Documentation

### For End Users
- **QUICK_START.md** - Get running in 5 minutes
- **INSTALLATION_GUIDE.md** - Complete installation instructions
- **DEPLOYMENT_SUMMARY.md** - Technical details and file explanations

### For Developers
- **README_COMPLETE.md** - Full development guide
- Code comments in all PHP files

---

## 🔧 How It Works

### Architecture

```
User searches in share dialog
         ↓
OuFilterPlugin intercepts
         ↓
Gets current user's OU from database
         ↓
Filters results: same OU → keep, different OU → remove
         ↓
Returns filtered list to UI
```

### Key Components

1. **OuFilterPlugin.php** - Main filtering logic
2. **LdapOuService.php** - OU retrieval from database
3. **Application.php** - Service registration

### Database

Uses table `ldap_user_mapping` (created by Nextcloud LDAP app):
- `owncloud_name` - User UUID
- `ldap_dn` - LDAP Distinguished Name
- Extracts OU from DN automatically

---

## 🧪 Testing

### Quick Test
```bash
# Test OU detection
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou.php

# Expected output:
# User 1 OU: OU=cyberfirst
# User 2 OU: OU=bebo
# Same OU? NO
```

### UI Test
1. Login as user in specific OU
2. Navigate to Files → Share
3. Search for users
4. Verify: Only same-OU users appear

---

## 🐛 Troubleshooting

### All users visible?
```bash
# Check app is enabled
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter

# Check plugin registration
tail -100 /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin"
```

### 500 errors?
```bash
# Check logs
tail -100 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

### See documentation
- **INSTALLATION_GUIDE.md** - Full troubleshooting section
- **DEPLOYMENT_SUMMARY.md** - Common issues and solutions

---

## 📋 Requirements

- Nextcloud 31.0+
- PHP 8.0+
- LDAP/Active Directory configured
- PostgreSQL or MySQL/MariaDB
- LDAP users synced to Nextcloud

---

## 🚀 Quick Deploy Script

```bash
#!/bin/bash
SERVER="your-server-ip"

# Upload
scp -r ldapoufilter root@$SERVER:/var/www/nextcloud/apps/

# Permissions
ssh root@$SERVER "chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter"

# Enable
ssh root@$SERVER "sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"

# Verify
ssh root@$SERVER "tail -20 /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter"
```

---

## 📁 Project Structure

```
ldapoufilter/
├── lib/
│   ├── AppInfo/Application.php         # Bootstrap
│   ├── Collaboration/OuFilterPlugin.php # Main filter
│   └── Service/LdapOuService.php       # OU retrieval
├── appinfo/
│   ├── info.xml                        # App metadata
│   └── routes.php                      # Routes
├── INSTALLATION_GUIDE.md               # Complete guide
├── DEPLOYMENT_SUMMARY.md                # Technical details
├── QUICK_START.md                       # 5-minute setup
└── README_ENGLISH.md                    # This file
```

---

## 📞 Support

1. Check **INSTALLATION_GUIDE.md** for detailed troubleshooting
2. Review logs: `/var/www/nextcloud/data/nextcloud.log`
3. Test OU detection with provided test scripts
4. Verify LDAP configuration in Nextcloud Admin

---

## 📝 Changelog

### v1.1 (Latest)
- ✅ Fixed database table name (`ldap_user_mapping`)
- ✅ Improved error handling (no more 500 errors)
- ✅ Better OU extraction logic
- ✅ Works with Talk autocomplete
- ✅ Handles empty search results gracefully

### v1.0
- Initial release
- OU-based filtering
- Database-backed OU detection

---

## 📜 License

AGPL-3.0

---

**Need help?** See INSTALLATION_GUIDE.md for comprehensive documentation.

