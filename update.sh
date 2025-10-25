#!/bin/bash

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}      LDAP OU Filter - Update Script${NC}"
echo -e "${BLUE}===================================================${NC}"
echo ""

# App configuration
APP_NAME="ldapoufilter"
APP_PATH="/var/www/nextcloud/apps/$APP_NAME"
NEXTCLOUD_PATH="/var/www/nextcloud"
WEB_USER="www-data"

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo -e "${RED}Please run as root (use sudo)${NC}"
    exit 1
fi

echo -e "${YELLOW}1. Backing up current app...${NC}"
if [ -d "$APP_PATH" ]; then
    BACKUP_DIR="$APP_PATH.backup.$(date +%Y%m%d_%H%M%S)"
    cp -r "$APP_PATH" "$BACKUP_DIR"
    echo -e "${GREEN}✓ Backup created at: $BACKUP_DIR${NC}"
else
    echo -e "${YELLOW}App directory not found, fresh install${NC}"
    mkdir -p "$APP_PATH"
fi

echo ""
echo -e "${YELLOW}2. Copying new files...${NC}"
# Copy all files from current directory
rsync -av --exclude='.git' --exclude='*.backup.*' ./ "$APP_PATH/"
echo -e "${GREEN}✓ Files copied${NC}"

echo ""
echo -e "${YELLOW}3. Setting correct permissions...${NC}"
chown -R $WEB_USER:$WEB_USER "$APP_PATH"
chmod -R 755 "$APP_PATH"
echo -e "${GREEN}✓ Permissions set${NC}"

# Make scripts executable
echo "Making helper scripts executable..."
chmod +x $APP_PATH/*.sh

echo ""
echo -e "${YELLOW}4. Disabling app...${NC}"
sudo -u $WEB_USER php $NEXTCLOUD_PATH/occ app:disable $APP_NAME
echo -e "${GREEN}✓ App disabled${NC}"

echo ""
echo -e "${YELLOW}5. Enabling app...${NC}"
sudo -u $WEB_USER php $NEXTCLOUD_PATH/occ app:enable $APP_NAME
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ App enabled successfully${NC}"
else
    echo -e "${RED}✗ Failed to enable app${NC}"
    echo -e "${YELLOW}Check logs for errors${NC}"
    exit 1
fi

echo ""
echo -e "${GREEN}===================================================${NC}"
echo -e "${GREEN}         Update completed successfully!${NC}"
echo -e "${GREEN}===================================================${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Run diagnostics: bash diagnose.sh"
echo "  2. Monitor logs:    bash check_logs.sh -f"
echo "  3. Test filtering:  bash test_filter.sh"
echo ""
echo -e "${BLUE}📖 See OU_FIX_GUIDE.md for nested OU troubleshooting${NC}"
echo -e "${BLUE}📖 See DEPLOYMENT_GUIDE.md for detailed testing instructions${NC}"
echo ""