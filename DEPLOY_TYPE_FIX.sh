#!/bin/bash

# Quick deployment script for the Type Hint Fix
# This deploys ONLY the critical Application.php fix

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================================${NC}"
echo -e "${BLUE}  Type Hint Fix - Quick Deploy${NC}"
echo -e "${BLUE}================================================${NC}"
echo ""

# Server details (modify if needed)
SERVER="your-server"
SERVER_USER="root"
APP_PATH="/var/www/nextcloud/apps/ldapoufilter"

echo -e "${YELLOW}This script will:${NC}"
echo "  1. Copy fixed Application.php to server"
echo "  2. Set correct permissions"
echo "  3. Reload the app"
echo "  4. Show diagnostics"
echo ""
echo -e "${YELLOW}Server: ${SERVER}${NC}"
echo -e "${YELLOW}Path: ${APP_PATH}${NC}"
echo ""

read -p "Press Enter to continue or Ctrl+C to abort..."

echo ""
echo -e "${BLUE}Step 1: Copying Application.php to server...${NC}"

# Method 1: Using SCP (uncomment and modify if you use SCP)
# scp lib/AppInfo/Application.php ${SERVER_USER}@${SERVER}:${APP_PATH}/lib/AppInfo/

# Method 2: Display commands to run manually
echo ""
echo -e "${YELLOW}=== Manual Deployment Steps ===${NC}"
echo ""
echo "Run these commands:"
echo ""
echo -e "${GREEN}# On your server:${NC}"
echo "cd ${APP_PATH}"
echo ""
echo "# Backup current file"
echo "sudo cp lib/AppInfo/Application.php lib/AppInfo/Application.php.backup"
echo ""
echo "# Upload the new file (use your method: SCP, SFTP, etc.)"
echo "# Then:"
echo ""
echo "# Fix permissions"
echo "sudo chown www-data:www-data lib/AppInfo/Application.php"
echo "sudo chmod 644 lib/AppInfo/Application.php"
echo ""
echo "# Reload app"
echo "sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter"
echo "sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter"
echo ""
echo "# Check diagnostics"
echo "sudo bash diagnose.sh"
echo ""
echo -e "${GREEN}=== End of commands ===${NC}"
echo ""
echo -e "${YELLOW}Or use the full update script:${NC}"
echo "sudo bash update.sh"
echo ""

