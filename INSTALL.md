# تعليمات التثبيت - LDAP OU Filter

## 🚀 التثبيت السريع

```bash
# 1. انتقل للمجلد على Desktop
cd ~/Desktop/ldapoufilter

# 2. شغل سكريبت التثبيت
sudo bash install.sh

# أو استخدم Makefile
sudo make install
```

## 📝 التثبيت اليدوي

### 1. نسخ الملفات للسيرفر

```bash
# انسخ المجلد للسيرفر (استخدم الطريقة المناسبة لك)
scp -r ~/Desktop/ldapoufilter root@your-server:/tmp/

# على السيرفر
ssh root@your-server
cd /tmp/ldapoufilter
```

### 2. نقل للـ Nextcloud

```bash
# انقل المجلد لمجلد تطبيقات Nextcloud
cp -r /tmp/ldapoufilter /var/www/nextcloud/apps/

# صحح الصلاحيات
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
chmod -R 755 /var/www/nextcloud/apps/ldapoufilter
```

### 3. تفعيل التطبيق

```bash
# فعّل التطبيق
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# تأكد من التفعيل
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter
```

## ⚙️ التخصيص

### تغيير مستوى OU للفلترة

افتح ملف `lib/Service/LdapOuService.php` وعدل دالة `extractOuFromDn()`:

#### الإعداد الافتراضي (OU المباشر فقط)
```php
// مثال: CN=bebo,OU=Mail,OU=cyberfirst,DC=first,DC=loc
// النتيجة: OU=Mail
return $ouParts[0];
```

#### للفلترة حسب OU الأب
```php
// مثال: CN=bebo,OU=Mail,OU=cyberfirst,DC=first,DC=loc
// النتيجة: OU=cyberfirst
return isset($ouParts[1]) ? $ouParts[1] : $ouParts[0];
```

#### للفلترة حسب كل مسار OU
```php
// مثال: CN=bebo,OU=Mail,OU=cyberfirst,DC=first,DC=loc
// النتيجة: OU=Mail,OU=cyberfirst
return implode(',', $ouParts);
```

## 🔍 اختبار التطبيق

### 1. تسجيل الدخول كمستخدمين مختلفين

```bash
# User 1: bebo (في OU=Mail)
# User 2: user01 (في OU=Builtin)
```

### 2. اختبر المشاركة
- سجل دخول كـ bebo
- حاول مشاركة ملف
- يجب أن تظهر فقط المستخدمين من OU=Mail

### 3. مراجعة السجلات

```bash
# شاهد السجلات المباشرة
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter

# أو باستخدام Makefile
make logs
```

## 🐛 حل المشاكل

### المشكلة: التطبيق لا يعمل

```bash
# 1. تأكد من تفعيل LDAP
sudo -u www-data php /var/www/nextcloud/occ app:enable user_ldap

# 2. اختبر اتصال LDAP
sudo -u www-data php /var/www/nextcloud/occ ldap:test-config s01

# 3. أعد تثبيت التطبيق
make reinstall
```

### المشكلة: كل المستخدمين يظهرون

```bash
# 1. تحقق من السجلات
grep "Could not determine OU" /var/www/nextcloud/data/nextcloud.log

# 2. تأكد من bind credentials في LDAP
sudo -u www-data php /var/www/nextcloud/occ ldap:show-config s01 | grep bind
```

### المشكلة: لا أحد يظهر

```bash
# 1. تأكد من إعدادات OU
# افتح lib/Service/LdapOuService.php وتأكد من extractOuFromDn()

# 2. فعّل debug logging
sudo -u www-data php /var/www/nextcloud/occ config:system:set loglevel --value=0
```

## 📊 الأوامر المفيدة

```bash
# تفعيل/تعطيل
make enable
make disable

# إعادة التثبيت
make reinstall

# مشاهدة السجلات
make logs

# اختبار LDAP
make test-ldap
```

## 🔧 إعدادات متقدمة

### استخدام Redis للـ Caching (اختياري)

```bash
# 1. تثبيت Redis
apt install redis-server

# 2. تكوين Nextcloud
sudo -u www-data php /var/www/nextcloud/occ config:system:set redis host --value=localhost
sudo -u www-data php /var/www/nextcloud/occ config:system:set redis port --value=6379
```

### تخصيص الفلترة لمجموعات معينة

في `lib/Service/LdapOuService.php`:

```php
public function areUsersInSameOu(string $userId1, string $userId2): bool {
    // السماح للمدراء برؤية الجميع
    if ($this->isAdmin($userId1)) {
        return true;
    }
    
    // الكود الأصلي...
}
```

## 📝 ملاحظات

- التطبيق يعمل مع Nextcloud 31+
- يتطلب PHP 8.1 أو أعلى
- يجب أن يكون LDAP مُعد بشكل صحيح
- التطبيق يحفظ cache في الذاكرة فقط (لا database)

## 🆘 الدعم

لأي مشاكل:
1. راجع السجلات
2. تأكد من إعدادات LDAP
3. جرب إعادة التثبيت