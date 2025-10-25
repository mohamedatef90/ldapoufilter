# 🔧 تعليمات الإصلاح - Fix Instructions

## المشكلة الأساسية
كان هناك خطأ في تسجيل الخدمة في Nextcloud 31:
- `registerService()` يحتاج factory function كـ parameter ثاني

## التحديثات المطلوبة

### 1. على السيرفر - حدث الملفات:
```bash
cd /var/www/nextcloud/apps/ldapoufilter

# احذف الملفات القديمة وانسخ الجديدة من Desktop
# أو استخدم update.sh
```

### 2. أعد تفعيل التطبيق:
```bash
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

### 3. فعّل Debug Mode:
```bash
sudo -u www-data php /var/www/nextcloud/occ config:system:set loglevel --value=0
```

### 4. امسح الـ Cache:
```bash
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

### 5. اختبر:
```bash
# شغل debug script
sudo bash debug.sh

# شاهد السجلات
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

## التحققات المهمة

### تأكد من LDAP يعمل:
```bash
ldapsearch -x -H ldap://192.168.2.200:389 \
  -D "Administrator@Frist.loc" \
  -W -b "DC=Frist,DC=loc" \
  "(sAMAccountName=bebo)"
```

### اختبر المستخدمين:
1. سجل دخول كـ user من OU=Mail (مثل bebo)
2. حاول المشاركة
3. يجب أن تظهر فقط المستخدمين من نفس OU

## لو لسه مش شغال

### 1. تأكد من DN format:
```bash
# شوف DN للمستخدمين
ldapsearch -x -H ldap://192.168.2.200:389 \
  -D "Administrator@Frist.loc" -W \
  -b "DC=Frist,DC=loc" \
  "(objectClass=user)" dn | grep "^dn:"
```

### 2. عدل extractOuFromDn() في LdapOuService.php:
```php
// لو الـ OU structure مختلف
// غير السطر 195:
$selectedOu = $ouParts[1]; // بدل [0]
```

### 3. Debug المستخدمين:
```bash
# في Nextcloud console
sudo -u www-data php /var/www/nextcloud/occ ldap:search "bebo"
```

## السجلات المفيدة

ابحث في السجلات عن:
- "UserSearchListener triggered"
- "Starting to filter"
- "Extracting OU from DN"
- "Selected OU"
- "Filtered search results"

## النصائح

1. تأكد أن bind user له صلاحية قراءة كل المستخدمين
2. تأكد أن Base DN صحيح (DC=Frist,DC=loc)
3. جرب مع مستخدمين من OUs مختلفة للتأكد