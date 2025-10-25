#!/bin/bash

# LDAP OU Filter Installation Script
# ==================================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Default values
NEXTCLOUD_PATH="/var/www/nextcloud"
NEXTCLOUD_USER="www-data"
NEXTCLOUD_GROUP="www-data"
APP_NAME="ldapoufilter"

echo "========================================"
echo "  LDAP OU Filter Installation"
echo "========================================"
echo ""

# Check if running as root
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}This script must be run as root (use sudo)${NC}"
   exit 1
fi

# Check if Nextcloud exists
if [ ! -d "$NEXTCLOUD_PATH" ]; then
    echo -e "${RED}Nextcloud not found at $NEXTCLOUD_PATH${NC}"
    exit 1
fi

# Copy app to Nextcloud
echo -e "${YELLOW}1. Copying app files...${NC}"
cp -r $(dirname "$0") $NEXTCLOUD_PATH/apps/$APP_NAME

# Set permissions
echo -e "${YELLOW}2. Setting permissions...${NC}"
chown -R $NEXTCLOUD_USER:$NEXTCLOUD_GROUP $NEXTCLOUD_PATH/apps/$APP_NAME
chmod -R 755 $NEXTCLOUD_PATH/apps/$APP_NAME

# Enable the app
echo -e "${YELLOW}3. Enabling app...${NC}"
sudo -u $NEXTCLOUD_USER php $NEXTCLOUD_PATH/occ app:enable $APP_NAME

# Check if app is enabled
if sudo -u $NEXTCLOUD_USER php $NEXTCLOUD_PATH/occ app:list | grep -q "$APP_NAME"; then
    echo -e "${GREEN}✓ App installed and enabled successfully!${NC}"
else
    echo -e "${RED}✗ Failed to enable app${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}Installation completed!${NC}"
echo ""
echo "Next steps:"
echo "1. Make sure LDAP is configured in Nextcloud"
echo "2. Test with different user accounts"
echo "3. Check logs if needed: tail -f $NEXTCLOUD_PATH/data/nextcloud.log | grep $APP_NAME"
echo ""