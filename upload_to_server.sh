#!/bin/bash
# Upload to Server Script
# =======================

# تعديل هذه المتغيرات حسب السيرفر بتاعك
SERVER_IP="192.168.2.200"  # أو your-server.com
SERVER_USER="root"
SERVER_PATH="/tmp"

echo "Uploading LDAP OU Filter to server..."
echo "Server: $SERVER_USER@$SERVER_IP"
echo ""

# Create tar archive
echo "Creating archive..."
tar -czf ldapoufilter.tar.gz \
    appinfo/ \
    lib/ \
    *.md \
    *.sh \
    *.json \
    Makefile \
    .gitignore

# Upload to server
echo "Uploading to server..."
scp ldapoufilter.tar.gz $SERVER_USER@$SERVER_IP:$SERVER_PATH/

# Extract on server and install
echo "Installing on server..."
ssh $SERVER_USER@$SERVER_IP << 'EOF'
cd /tmp
tar -xzf ldapoufilter.tar.gz
mkdir -p ldapoufilter_new
mv appinfo lib *.md *.sh *.json Makefile .gitignore ldapoufilter_new/ 2>/dev/null

# Disable old app
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter

# Backup and replace
if [ -d /var/www/nextcloud/apps/ldapoufilter ]; then
    mv /var/www/nextcloud/apps/ldapoufilter /var/www/nextcloud/apps/ldapoufilter_backup
fi

# Install new version
mv ldapoufilter_new /var/www/nextcloud/apps/ldapoufilter
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
chmod -R 755 /var/www/nextcloud/apps/ldapoufilter

# Enable app
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# Clear cache
sudo -u www-data php /var/www/nextcloud/occ cache:clear

echo "✓ Installation completed!"
EOF

# Clean up
rm -f ldapoufilter.tar.gz

echo ""
echo "✓ Done! App has been updated on the server."
echo ""
echo "Now test it:"
echo "ssh $SERVER_USER@$SERVER_IP"
echo "tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter"