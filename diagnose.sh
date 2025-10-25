#!/bin/bash

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}  LDAP OU Filter - Diagnostic Script${NC}"
echo -e "${BLUE}===================================================${NC}"
echo ""

NEXTCLOUD_PATH="/var/www/nextcloud"
LOG_FILE="/var/www/nextcloud/data/nextcloud.log"

echo -e "${YELLOW}1. Checking if event dispatcher is working...${NC}"
echo "Creating test event listener..."

# Check recent logs for our app
echo ""
echo -e "${YELLOW}2. Recent app logs:${NC}"
if [ -f "$LOG_FILE" ]; then
    echo "Last 30 lines related to ldapoufilter:"
    tail -2000 "$LOG_FILE" | grep -i "ldapoufilter" | tail -30 | jq -r '"\(.time) [\(.level)] \(.message)"' 2>/dev/null || tail -2000 "$LOG_FILE" | grep -i "ldapoufilter" | tail -30
else
    echo -e "${RED}Log file not found at $LOG_FILE${NC}"
fi

echo ""
echo -e "${YELLOW}3. Checking OU extraction debug logs...${NC}"
echo "Looking for OU extraction in logs..."
tail -2000 "$LOG_FILE" | grep -i "OU EXTRACTION\|FINAL SELECTED OU\|Found.*OU levels" | tail -20 | jq -r '"\(.time) \(.message)"' 2>/dev/null || tail -2000 "$LOG_FILE" | grep -i "OU EXTRACTION\|FINAL SELECTED OU" | tail -20

echo ""
echo -e "${YELLOW}4. Checking for SearchResultEvent...${NC}"
echo "Looking for event registration and triggers..."
tail -2000 "$LOG_FILE" | grep -i "SearchResultEvent\|Event listener registered" | tail -10 | jq -r '"\(.time) \(.message)"' 2>/dev/null || tail -2000 "$LOG_FILE" | grep -i "SearchResultEvent\|Event listener" | tail -10

echo ""
echo -e "${YELLOW}5. App status:${NC}"
sudo -u www-data php $NEXTCLOUD_PATH/occ app:list | grep -A5 -B5 ldapoufilter

echo ""
echo -e "${YELLOW}6. Testing OU extraction for sample users...${NC}"
echo "Enter a username to check their OU (or press Enter to skip):"
read -r test_user

if [ -n "$test_user" ]; then
    echo "Checking user info..."
    sudo -u www-data php $NEXTCLOUD_PATH/occ user:info "$test_user" 2>/dev/null || echo "User not found"
fi

echo ""
echo -e "${GREEN}===================================================${NC}"
echo -e "${GREEN}  Diagnostic complete${NC}"
echo -e "${GREEN}===================================================${NC}"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo "  1. Try searching for users in Nextcloud"
echo "  2. Monitor logs: bash check_logs.sh -f"
echo "  3. Check if you see OU EXTRACTION DEBUG messages"
echo ""
