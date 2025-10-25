#!/bin/bash

# Quick deployment script for LDAP OU Filter fix
# This script uploads the fixed version to your Nextcloud server

echo "=== LDAP OU Filter Fix Deployment ==="
echo ""

# Check if we're in the right directory
if [ ! -f "lib/Service/LdapOuService.php" ]; then
    echo "❌ Error: Please run this script from the ldapoufilter directory"
    exit 1
fi

# Server details (update these for your server)
SERVER="192.168.2.10"
SERVER_PATH="/var/www/nextcloud/apps/ldapoufilter"
USER="root"

echo "📁 Uploading fixed files to server..."
echo "   Server: $SERVER"
echo "   Path: $SERVER_PATH"
echo ""

# Upload the fixed files
echo "🔄 Uploading LdapOuService.php..."
scp lib/Service/LdapOuService.php $USER@$SERVER:$SERVER_PATH/lib/Service/

echo "🔄 Uploading Application.php..."
scp lib/AppInfo/Application.php $USER@$SERVER:$SERVER_PATH/lib/AppInfo/

echo "🔄 Uploading test script..."
scp test_ldap_fix.php $USER@$SERVER:$SERVER_PATH/

echo "🔄 Uploading documentation..."
scp FIX_SUMMARY.md $USER@$SERVER:$SERVER_PATH/
scp README.md $USER@$SERVER:$SERVER_PATH/

echo ""
echo "🔧 Setting permissions on server..."
ssh $USER@$SERVER "chown -R www-data:www-data $SERVER_PATH && chmod -R 755 $SERVER_PATH"

echo ""
echo "🔄 Restarting Nextcloud services..."
ssh $USER@$SERVER "systemctl reload apache2"

echo ""
echo "✅ Deployment completed!"
echo ""
echo "🧪 To test the fix, run on the server:"
echo "   cd $SERVER_PATH"
echo "   php test_ldap_fix.php"
echo ""
echo "📊 To check logs:"
echo "   tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter"
echo ""
echo "🎉 The LDAP OU Filter should now work without binding errors!"
