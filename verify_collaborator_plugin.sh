#!/bin/bash

# Verification script for Collaborator Plugin deployment

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}  Collaborator Plugin Verification${NC}"
echo -e "${BLUE}===================================================${NC}"
echo ""

LOG_FILE="/var/www/nextcloud/data/nextcloud.log"
APP_PATH="/var/www/nextcloud/apps/ldapoufilter"

# Check 1: File exists
echo -e "${YELLOW}1. Checking if OuFilterPlugin.php exists...${NC}"
if [ -f "$APP_PATH/lib/Collaboration/OuFilterPlugin.php" ]; then
    echo -e "${GREEN}✓ File exists${NC}"
else
    echo -e "${RED}✗ File NOT found${NC}"
    echo "   Expected: $APP_PATH/lib/Collaboration/OuFilterPlugin.php"
    exit 1
fi

# Check 2: Plugin registered
echo ""
echo -e "${YELLOW}2. Checking if plugin is registered...${NC}"
if grep -q "OU Filter Plugin registered with Collaborators Manager" "$LOG_FILE"; then
    echo -e "${GREEN}✓ Plugin registered${NC}"
    echo "   Last registration:"
    tail -1000 "$LOG_FILE" | grep "OU Filter Plugin registered" | tail -1 | jq -r '"\(.time) \(.message)"' 2>/dev/null || \
    tail -1000 "$LOG_FILE" | grep "OU Filter Plugin registered" | tail -1
else
    echo -e "${RED}✗ Plugin NOT registered${NC}"
    echo "   Checking for errors..."
    if grep -q "Failed to register OU Filter Plugin" "$LOG_FILE"; then
        echo -e "${RED}   Found registration error:${NC}"
        tail -1000 "$LOG_FILE" | grep "Failed to register OU Filter Plugin" | tail -5
    fi
fi

# Check 3: Recent activity
echo ""
echo -e "${YELLOW}3. Checking for recent plugin activity...${NC}"
ACTIVATIONS=$(tail -1000 "$LOG_FILE" | grep -c "OU Filter Plugin ACTIVATED" || echo "0")
if [ "$ACTIVATIONS" -gt 0 ]; then
    echo -e "${GREEN}✓ Plugin has been activated $ACTIVATIONS time(s)${NC}"
    echo "   Last activation:"
    tail -1000 "$LOG_FILE" | grep "OU Filter Plugin ACTIVATED" | tail -1 | jq -r '"\(.time) User: \(.user)"' 2>/dev/null || \
    tail -1000 "$LOG_FILE" | grep "OU Filter Plugin ACTIVATED" | tail -1
else
    echo -e "${YELLOW}⚠ Plugin has not been activated yet${NC}"
    echo "   This is normal if you haven't searched for users yet"
fi

# Check 4: OU Extraction
echo ""
echo -e "${YELLOW}4. Checking OU extraction...${NC}"
OU_EXTRACTIONS=$(tail -1000 "$LOG_FILE" | grep -c "OU EXTRACTION DEBUG" || echo "0")
if [ "$OU_EXTRACTIONS" -gt 0 ]; then
    echo -e "${GREEN}✓ OU extraction working (found $OU_EXTRACTIONS occurrences)${NC}"
    echo "   Sample:"
    tail -1000 "$LOG_FILE" | grep "FINAL SELECTED OU" | tail -3
else
    echo -e "${YELLOW}⚠ No OU extraction found in recent logs${NC}"
    echo "   This is normal if no searches have been performed"
fi

# Check 5: Filtering
echo ""
echo -e "${YELLOW}5. Checking filtering results...${NC}"
FILTERED=$(tail -1000 "$LOG_FILE" | grep -c "Filtered.*users:" || echo "0")
if [ "$FILTERED" -gt 0 ]; then
    echo -e "${GREEN}✓ Filtering active (found $FILTERED occurrences)${NC}"
    echo "   Recent filtering:"
    tail -1000 "$LOG_FILE" | grep "Filtered.*users:" | tail -3 | jq -r '"\(.time) \(.message)"' 2>/dev/null || \
    tail -1000 "$LOG_FILE" | grep "Filtered.*users:" | tail -3
else
    echo -e "${YELLOW}⚠ No filtering results in recent logs${NC}"
    echo "   Perform a user search to see filtering in action"
fi

# Summary
echo ""
echo -e "${BLUE}===================================================${NC}"
echo -e "${BLUE}  Summary${NC}"
echo -e "${BLUE}===================================================${NC}"
echo ""

# Count checks
PASSED=0
TOTAL=5

[ -f "$APP_PATH/lib/Collaboration/OuFilterPlugin.php" ] && ((PASSED++))
grep -q "OU Filter Plugin registered with Collaborators Manager" "$LOG_FILE" && ((PASSED++))
[ "$ACTIVATIONS" -gt 0 ] && ((PASSED++))
[ "$OU_EXTRACTIONS" -gt 0 ] && ((PASSED++))
[ "$FILTERED" -gt 0 ] && ((PASSED++))

if [ $PASSED -eq 5 ]; then
    echo -e "${GREEN}✓ All checks passed ($PASSED/$TOTAL)${NC}"
    echo ""
    echo -e "${GREEN}Your plugin is fully deployed and working!${NC}"
elif [ $PASSED -ge 2 ]; then
    echo -e "${YELLOW}⚠ Partial deployment ($PASSED/$TOTAL checks passed)${NC}"
    echo ""
    echo "Next steps:"
    if [ "$ACTIVATIONS" -eq 0 ]; then
        echo "  1. Try searching for users in Nextcloud"
        echo "  2. Monitor logs: bash check_logs.sh -f"
    fi
else
    echo -e "${RED}✗ Deployment incomplete ($PASSED/$TOTAL checks passed)${NC}"
    echo ""
    echo "Troubleshooting:"
    echo "  1. Run: sudo bash update.sh"
    echo "  2. Check errors: sudo bash check_logs.sh -e"
    echo "  3. Verify app enabled: sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter"
fi

echo ""
echo -e "${BLUE}To test filtering:${NC}"
echo "  1. Open terminal: tail -f /var/www/nextcloud/data/nextcloud.log | grep 'OU Filter Plugin'"
echo "  2. Open Nextcloud in browser"
echo "  3. Try to share a file and search for users"
echo "  4. Watch the terminal for plugin activity"
echo ""

