#!/bin/bash
# QUICK COMMANDS - Copy & Paste on Server
# ========================================

# 1. DISABLE APP FIRST
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter

# 2. BACKUP OLD FILES
mv /var/www/nextcloud/apps/ldapoufilter /var/www/nextcloud/apps/ldapoufilter_old

# 3. COPY NEW FILES (after uploading to /tmp/ldapoufilter)
cp -r /tmp/ldapoufilter /var/www/nextcloud/apps/

# 4. FIX PERMISSIONS
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
chmod -R 755 /var/www/nextcloud/apps/ldapoufilter

# 5. ENABLE APP
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# 6. ENABLE DEBUG
sudo -u www-data php /var/www/nextcloud/occ config:system:set loglevel --value=0

# 7. CLEAR CACHE
sudo -u www-data php /var/www/nextcloud/occ cache:clear

# 8. TEST
sudo -u www-data php /var/www/nextcloud/occ ldap:test-config s01

# 9. WATCH LOGS
tail -f /var/www/nextcloud/data/nextcloud.log | grep -E "ldapoufilter|UserSearchListener|OU"

echo "✓ Done! Test by sharing a file as different users"