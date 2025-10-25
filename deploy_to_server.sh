#!/bin/bash
# Quick deployment script - Run from LOCAL machine

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}  LDAP OU Filter - Quick Deploy to Server${NC}"
echo -e "${BLUE}==================================================${NC}\n"

# Get server details
echo -e "${YELLOW}Enter your server details:${NC}"
read -p "Server IP or hostname: " SERVER_IP
read -p "SSH user (default: root): " SSH_USER
SSH_USER=${SSH_USER:-root}

echo -e "\n${YELLOW}Deploying to $SSH_USER@$SERVER_IP...${NC}\n"

# Get the directory of this script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Step 1: Upload files
echo -e "${BLUE}Step 1:${NC} Uploading files to server..."
rsync -avz --progress --exclude '.git' --exclude '*.md' --exclude 'deploy_to_server.sh' \
  "$SCRIPT_DIR/" \
  "$SSH_USER@$SERVER_IP:/tmp/ldapoufilter/"

if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to upload files${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Files uploaded${NC}\n"

# Step 2: Run deployment on server
echo -e "${BLUE}Step 2:${NC} Running deployment on server..."
ssh "$SSH_USER@$SERVER_IP" << 'EOF'
#!/bin/bash
set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

NC_PATH="/var/www/nextcloud"
APP_PATH="$NC_PATH/apps/ldapoufilter"

echo -e "${YELLOW}Installing/Updating app...${NC}"

# Backup if exists
if [ -d "$APP_PATH" ]; then
    echo "Creating backup..."
    cp -r "$APP_PATH" "${APP_PATH}.backup.$(date +%Y%m%d_%H%M%S)"
    rm -rf "$APP_PATH"
fi

# Copy files
echo "Copying files..."
cp -r /tmp/ldapoufilter "$APP_PATH"

# Set permissions
echo "Setting permissions..."
chown -R www-data:www-data "$APP_PATH"
chmod -R 755 "$APP_PATH"
chmod +x "$APP_PATH"/*.sh

# Disable and re-enable app
echo "Restarting app..."
sudo -u www-data php "$NC_PATH/occ" app:disable ldapoufilter 2>/dev/null || true
sudo -u www-data php "$NC_PATH/occ" app:enable ldapoufilter

# Clear cache
echo "Clearing cache..."
sudo -u www-data php "$NC_PATH/occ" cache:clear

# Enable debug logging
echo "Enabling debug logging..."
sudo -u www-data php "$NC_PATH/occ" config:system:set loglevel --value=0

echo -e "\n${GREEN}✓ Deployment completed!${NC}\n"
echo -e "${YELLOW}The app is now ready to test.${NC}\n"

EOF

if [ $? -ne 0 ]; then
    echo -e "${RED}Deployment failed${NC}"
    exit 1
fi

echo -e "${GREEN}==================================================${NC}"
echo -e "${GREEN}  Deployment Successful!${NC}"
echo -e "${GREEN}==================================================${NC}\n"

echo -e "${YELLOW}Next steps on the SERVER:${NC}"
echo ""
echo "1. SSH into your server:"
echo "   ${BLUE}ssh $SSH_USER@$SERVER_IP${NC}"
echo ""
echo "2. Navigate to app directory:"
echo "   ${BLUE}cd /var/www/nextcloud/apps/ldapoufilter${NC}"
echo ""
echo "3. Run the test script:"
echo "   ${BLUE}bash test_filter.sh${NC}"
echo ""
echo "4. Monitor logs (in a separate terminal):"
echo "   ${BLUE}bash check_logs.sh -f${NC}"
echo ""
echo "5. Test by sharing a file in Nextcloud"
echo ""
echo -e "${GREEN}See DEPLOYMENT_GUIDE.md for detailed instructions${NC}\n"

# Ask if user wants to SSH now
read -p "Would you like to SSH into the server now? (y/n): " CONNECT
if [[ "$CONNECT" =~ ^[Yy]$ ]]; then
    ssh "$SSH_USER@$SERVER_IP"
fi

