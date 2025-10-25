#!/bin/bash
# Script to test LDAP OU Filter functionality

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Nextcloud paths
NC_PATH="/var/www/nextcloud"
OCC="sudo -u www-data php $NC_PATH/occ"

echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}  LDAP OU Filter - Test & Verification${NC}"
echo -e "${BLUE}==================================================${NC}\n"

# Function to show status
show_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓ PASS${NC}"
    else
        echo -e "${RED}✗ FAIL${NC}"
    fi
}

# 1. Check if app is installed
echo -e "${YELLOW}1. Checking if app is installed...${NC}"
if [ -d "$NC_PATH/apps/ldapoufilter" ]; then
    echo -e "   ${GREEN}✓${NC} App directory exists"
else
    echo -e "   ${RED}✗${NC} App directory not found at $NC_PATH/apps/ldapoufilter"
    exit 1
fi

# 2. Check if app is enabled
echo -e "\n${YELLOW}2. Checking if app is enabled...${NC}"
APP_STATUS=$($OCC app:list | grep -A 1 "ldapoufilter")
if echo "$APP_STATUS" | grep -q "ldapoufilter"; then
    if echo "$APP_STATUS" | grep -q "Enabled"; then
        echo -e "   ${GREEN}✓${NC} App is enabled"
    else
        echo -e "   ${RED}✗${NC} App is disabled"
        echo -e "   ${YELLOW}Run: sudo -u www-data php $NC_PATH/occ app:enable ldapoufilter${NC}"
    fi
else
    echo -e "   ${RED}✗${NC} App not found in Nextcloud"
fi

# 3. Check LDAP configuration
echo -e "\n${YELLOW}3. Checking LDAP configuration...${NC}"
LDAP_CONFIGS=$($OCC ldap:show-config 2>/dev/null)
if [ $? -eq 0 ]; then
    echo -e "   ${GREEN}✓${NC} LDAP is configured"
    echo -e "   ${BLUE}LDAP Configurations:${NC}"
    echo "$LDAP_CONFIGS" | grep -E "(ldapHost|ldapPort|ldapBase|ldapAgentName)" | head -10
else
    echo -e "   ${RED}✗${NC} LDAP not configured or user_ldap app not enabled"
fi

# 4. Test LDAP connection
echo -e "\n${YELLOW}4. Testing LDAP connection...${NC}"
LDAP_TEST=$($OCC ldap:test-config s01 2>&1)
if echo "$LDAP_TEST" | grep -q "success\|valid\|OK"; then
    echo -e "   ${GREEN}✓${NC} LDAP connection successful"
else
    echo -e "   ${RED}✗${NC} LDAP connection failed"
    echo -e "   ${BLUE}Details:${NC} $LDAP_TEST"
fi

# 5. Check PHP LDAP extension
echo -e "\n${YELLOW}5. Checking PHP LDAP extension...${NC}"
if php -m | grep -q ldap; then
    echo -e "   ${GREEN}✓${NC} PHP LDAP extension is installed"
else
    echo -e "   ${RED}✗${NC} PHP LDAP extension is NOT installed"
    echo -e "   ${YELLOW}Install with: sudo apt-get install php-ldap${NC}"
fi

# 6. Check log level
echo -e "\n${YELLOW}6. Checking Nextcloud log level...${NC}"
LOG_LEVEL=$($OCC config:system:get loglevel)
echo -e "   Current log level: ${BLUE}$LOG_LEVEL${NC}"
if [ "$LOG_LEVEL" = "0" ]; then
    echo -e "   ${GREEN}✓${NC} Debug mode is enabled (level 0)"
else
    echo -e "   ${YELLOW}!${NC} For debugging, set to 0 with:"
    echo -e "   ${YELLOW}sudo -u www-data php $NC_PATH/occ config:system:set loglevel --value=0${NC}"
fi

# 7. Check recent logs
echo -e "\n${YELLOW}7. Checking recent logs for ldapoufilter...${NC}"
LOG_FILE="$NC_PATH/data/nextcloud.log"
if [ -f "$LOG_FILE" ]; then
    RECENT_LOGS=$(grep "ldapoufilter" "$LOG_FILE" 2>/dev/null | tail -5)
    if [ -n "$RECENT_LOGS" ]; then
        echo -e "   ${GREEN}✓${NC} Found ldapoufilter activity in logs"
        echo -e "   ${BLUE}Recent entries:${NC}"
        echo "$RECENT_LOGS" | while IFS= read -r line; do
            MESSAGE=$(echo "$line" | jq -r '.message // ""' 2>/dev/null)
            if [ -n "$MESSAGE" ]; then
                echo "   - $MESSAGE"
            fi
        done
    else
        echo -e "   ${YELLOW}!${NC} No ldapoufilter activity found in logs"
        echo -e "   ${BLUE}This might mean:${NC}"
        echo "   - App just installed and not used yet"
        echo "   - Event listener not triggering"
        echo "   - No search operations performed"
    fi
else
    echo -e "   ${RED}✗${NC} Log file not found at $LOG_FILE"
fi

# 8. Test user search
echo -e "\n${YELLOW}8. Testing LDAP user search...${NC}"
echo -e "   ${BLUE}Enter a username to search (or press Enter to skip):${NC}"
read -r TEST_USER
if [ -n "$TEST_USER" ]; then
    SEARCH_RESULT=$($OCC ldap:search "$TEST_USER" 2>&1)
    if [ $? -eq 0 ]; then
        echo -e "   ${GREEN}✓${NC} User search completed"
        echo "$SEARCH_RESULT" | head -20
    else
        echo -e "   ${RED}✗${NC} User search failed"
        echo "$SEARCH_RESULT"
    fi
else
    echo -e "   ${YELLOW}Skipped${NC}"
fi

# Summary
echo -e "\n${BLUE}==================================================${NC}"
echo -e "${BLUE}  Test Summary${NC}"
echo -e "${BLUE}==================================================${NC}"
echo -e "${GREEN}Next Steps:${NC}"
echo "1. Make sure all checks above pass"
echo "2. Clear cache: sudo -u www-data php $NC_PATH/occ cache:clear"
echo "3. Disable and re-enable the app:"
echo "   sudo -u www-data php $NC_PATH/occ app:disable ldapoufilter"
echo "   sudo -u www-data php $NC_PATH/occ app:enable ldapoufilter"
echo "4. Test by sharing a file and see if filtering works"
echo "5. Monitor logs: bash check_logs.sh -f"
echo -e "${BLUE}==================================================${NC}"

