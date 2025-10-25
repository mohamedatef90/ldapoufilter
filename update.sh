#!/bin/bash

# Update Script - يحدث الملفات على السيرفر
# =========================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

NEXTCLOUD_PATH="/var/www/nextcloud"
APP_PATH="$NEXTCLOUD_PATH/apps/ldapoufilter"

echo "========================================"
echo "  تحديث LDAP OU Filter"
echo "========================================"
echo ""

# Check if app exists
if [ ! -d "$APP_PATH" ]; then
    echo -e "${RED}App not found at $APP_PATH${NC}"
    echo "Please install the app first using install.sh"
    exit 1
fi

echo -e "${YELLOW}Updating app files...${NC}"

# Backup current files
echo "Creating backup..."
cp -r $APP_PATH ${APP_PATH}_backup_$(date +%Y%m%d_%H%M%S)

# Copy updated files
echo "Copying updated files..."
cp -r ./* $APP_PATH/

# Fix permissions
echo "Fixing permissions..."
chown -R www-data:www-data $APP_PATH
chmod -R 755 $APP_PATH

# Make scripts executable
echo "Making helper scripts executable..."
chmod +x $APP_PATH/*.sh

# Disable and re-enable app to reload
echo "Reloading app..."
sudo -u www-data php $NEXTCLOUD_PATH/occ app:disable ldapoufilter
sudo -u www-data php $NEXTCLOUD_PATH/occ app:enable ldapoufilter

# Clear cache
echo "Clearing cache..."
sudo -u www-data php $NEXTCLOUD_PATH/occ cache:clear

echo -e "\n${GREEN}✓ Update completed!${NC}"
echo ""
echo "Next steps:"
echo "  1. Test the app: bash test_filter.sh"
echo "  2. Monitor logs:  bash check_logs.sh -f"
echo "  3. View errors:   bash check_logs.sh -e"
echo ""
echo "See DEPLOYMENT_GUIDE.md for detailed testing instructions"
echo ""