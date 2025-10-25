#!/bin/bash

# LDAP OU Filter Debug Script
# ============================

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
NEXTCLOUD_PATH="/var/www/nextcloud"
NEXTCLOUD_USER="www-data"
OCC="sudo -u $NEXTCLOUD_USER php $NEXTCLOUD_PATH/occ"

echo "========================================"
echo "  LDAP OU Filter Debug Tool"
echo "========================================"
echo ""

# Function to check status
check_status() {
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $1"
    else
        echo -e "${RED}✗${NC} $1"
        return 1
    fi
}

# 1. Check Nextcloud installation
echo -e "${BLUE}1. Checking Nextcloud installation...${NC}"
if [ -d "$NEXTCLOUD_PATH" ]; then
    check_status "Nextcloud found at $NEXTCLOUD_PATH"
else
    echo -e "${RED}✗ Nextcloud not found at $NEXTCLOUD_PATH${NC}"
    exit 1
fi

# 2. Check PHP version
echo -e "\n${BLUE}2. Checking PHP version...${NC}"
PHP_VERSION=$(php -v | head -n1)
echo "   $PHP_VERSION"

# 3. Check LDAP app
echo -e "\n${BLUE}3. Checking LDAP app status...${NC}"
if $OCC app:list | grep -q "user_ldap:"; then
    check_status "LDAP app is enabled"
else
    echo -e "${RED}✗ LDAP app is not enabled${NC}"
    echo "   Run: $OCC app:enable user_ldap"
fi

# 4. Check LDAP OU Filter app
echo -e "\n${BLUE}4. Checking LDAP OU Filter app...${NC}"
if $OCC app:list | grep -q "ldapoufilter:"; then
    check_status "LDAP OU Filter is enabled"
    VERSION=$($OCC config:app:get ldapoufilter installed_version 2>/dev/null || echo "unknown")
    echo "   Version: $VERSION"
else
    echo -e "${RED}✗ LDAP OU Filter is not enabled${NC}"
    echo "   Run: $OCC app:enable ldapoufilter"
fi

# 5. Test LDAP connection
echo -e "\n${BLUE}5. Testing LDAP connection...${NC}"
if $OCC ldap:test-config s01 2>/dev/null; then
    check_status "LDAP connection successful"
else
    echo -e "${YELLOW}⚠ LDAP connection test failed or no config${NC}"
fi

# 6. Show LDAP configuration
echo -e "\n${BLUE}6. LDAP Configuration Summary:${NC}"
echo "   Host: $($OCC ldap:show-config s01 | grep 'ldapHost' | cut -d':' -f2 | xargs)"
echo "   Base DN: $($OCC ldap:show-config s01 | grep 'ldapBase' | cut -d':' -f2 | xargs)"
echo "   User Filter: $($OCC ldap:show-config s01 | grep 'ldapUserFilter' | cut -d':' -f2 | xargs)"

# 7. Count LDAP users
echo -e "\n${BLUE}7. Counting LDAP users...${NC}"
USER_COUNT=$($OCC ldap:count-users 2>/dev/null | grep -oP '\d+' | head -1)
if [ -z "$USER_COUNT" ]; then
    USER_COUNT="0"
fi
echo "   Found $USER_COUNT users"

# 8. Check recent logs
echo -e "\n${BLUE}8. Recent LDAP OU Filter logs:${NC}"
if [ -f "$NEXTCLOUD_PATH/data/nextcloud.log" ]; then
    LOGS=$(grep "ldapoufilter" "$NEXTCLOUD_PATH/data/nextcloud.log" | tail -5)
    if [ -z "$LOGS" ]; then
        echo "   No recent logs found"
    else
        echo "$LOGS" | while IFS= read -r line; do
            echo "   $line"
        done
    fi
else
    echo "   Log file not found"
fi

# 9. Test with specific user
echo -e "\n${BLUE}9. Test user OU detection:${NC}"
read -p "   Enter username to test (or press Enter to skip): " TEST_USER
if [ ! -z "$TEST_USER" ]; then
    echo "   Testing user: $TEST_USER"
    
    # Try to find user in LDAP (simulation)
    LDAP_SEARCH=$(ldapsearch -x -H ldap://localhost:389 \
        -b "$($OCC ldap:show-config s01 | grep 'ldapBase' | cut -d':' -f2 | xargs)" \
        "(uid=$TEST_USER)" dn 2>/dev/null | grep "^dn:" || echo "")
    
    if [ ! -z "$LDAP_SEARCH" ]; then
        echo -e "   ${GREEN}User found in LDAP:${NC}"
        echo "   $LDAP_SEARCH"
    else
        echo -e "   ${YELLOW}User not found in LDAP${NC}"
    fi
fi

# 10. Recommendations
echo -e "\n${BLUE}10. Recommendations:${NC}"

if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    echo -e "   ${YELLOW}⚠ No LDAP users found. Check LDAP configuration.${NC}"
fi

# Check log level
LOG_LEVEL=$($OCC config:system:get loglevel 2>/dev/null || echo "2")
if [ "$LOG_LEVEL" -ne "0" ]; then
    echo -e "   ${YELLOW}ℹ Enable debug logging for more details:${NC}"
    echo "     $OCC config:system:set loglevel --value=0"
fi

echo -e "\n${GREEN}Debug check completed!${NC}"
echo ""
echo "For real-time monitoring, run:"
echo "  tail -f $NEXTCLOUD_PATH/data/nextcloud.log | grep ldapoufilter"
echo ""