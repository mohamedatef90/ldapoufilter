# LDAP OU Filter for Nextcloud

## الوصف
تطبيق Nextcloud يقوم بفلترة المستخدمين المقترحين في عمليات المشاركة بناءً على الوحدة التنظيمية (OU) في LDAP/Active Directory.

## 🚀 Quick Start

**NEW! The app has been fixed and is ready to deploy.**

- **Super Quick Deploy**: See [QUICKSTART.md](QUICKSTART.md) - Deploy in 1 command!
- **Detailed Guide**: See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) - Complete instructions and troubleshooting

### One-Command Deploy:
```bash
chmod +x deploy_to_server.sh && ./deploy_to_server.sh
```

## 🔧 Recent Fix (v1.1)

**Fixed LDAP Connection Issues** - The app now uses Nextcloud's existing LDAP configuration instead of creating its own connection, eliminating authentication errors and improving reliability.

### What was fixed:
- ❌ **Before**: App tried to create separate LDAP connections with wrong credentials
- ✅ **After**: App uses Nextcloud's LDAP user manager for seamless integration
- ✅ **Result**: No more "Failed to bind to LDAP" errors
- ✅ **Result**: Proper OU detection and filtering

### Testing the fix:
```bash
# Test the LDAP OU service
php test_ldap_fix.php

# Check app logs
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

## التثبيت

### 1. نقل المجلد للسيرفر
```bash
# انسخ المجلد ldapoufilter إلى مجلد التطبيقات في Nextcloud
cp -r ldapoufilter /var/www/nextcloud/apps/
```

### 2. تعيين الصلاحيات
```bash
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
chmod -R 755 /var/www/nextcloud/apps/ldapoufilter
```

### 3. تفعيل التطبيق
```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

## التكوين

### تعديل مستوى الـ OU المطلوب
في ملف `lib/Service/LdapOuService.php` في دالة `extractOuFromDn()`:

```php
// للحصول على OU الأول (المباشر) - مثال: OU=Mail
return $ouParts[0];

// للحصول على OU الثاني (الأب) - مثال: OU=cyberfirst  
return isset($ouParts[1]) ? $ouParts[1] : $ouParts[0];

// للحصول على كل OUs
return implode(',', $ouParts);
```

## استكشاف الأخطاء

### تفعيل Debug Mode
```bash
# في ملف config.php
'loglevel' => 0,
```

### مراجعة السجلات
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

### التحقق من اتصال LDAP
```bash
sudo -u www-data php /var/www/nextcloud/occ ldap:test-config s01
```

## المتطلبات
- Nextcloud 31+
- PHP 8.1+
- LDAP/Active Directory مُعد ومتصل
- تطبيق user_ldap مُفعّل

## الترخيص
AGPL-3.0