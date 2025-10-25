#!/bin/bash
# Script to check Nextcloud logs for LDAP OU Filter activity

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Nextcloud log file location
LOG_FILE="/var/www/nextcloud/data/nextcloud.log"

echo -e "${BLUE}==================================================${NC}"
echo -e "${BLUE}  Nextcloud LDAP OU Filter - Log Checker${NC}"
echo -e "${BLUE}==================================================${NC}\n"

# Check if log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo -e "${RED}Error: Log file not found at $LOG_FILE${NC}"
    echo "Please check your Nextcloud installation path"
    exit 1
fi

# Function to show usage
show_usage() {
    echo "Usage: $0 [option]"
    echo ""
    echo "Options:"
    echo "  -f, --follow     Follow logs in real-time (like tail -f)"
    echo "  -e, --errors     Show only errors"
    echo "  -l, --last N     Show last N lines (default: 50)"
    echo "  -a, --all        Show all ldapoufilter logs"
    echo "  -h, --help       Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0                  # Show last 50 lines"
    echo "  $0 -f               # Follow logs in real-time"
    echo "  $0 -e               # Show only errors"
    echo "  $0 -l 100           # Show last 100 lines"
}

# Parse log line and format nicely
format_log() {
    while IFS= read -r line; do
        # Check if it's a valid JSON log entry
        if echo "$line" | jq empty 2>/dev/null; then
            LEVEL=$(echo "$line" | jq -r '.level // "INFO"')
            TIME=$(echo "$line" | jq -r '.time // ""')
            MESSAGE=$(echo "$line" | jq -r '.message // ""')
            
            # Color based on level
            case $LEVEL in
                "FATAL"|"ERROR"|3)
                    COLOR=$RED
                    LEVEL_TEXT="ERROR"
                    ;;
                "WARN"|"WARNING"|2)
                    COLOR=$YELLOW
                    LEVEL_TEXT="WARN "
                    ;;
                "INFO"|1)
                    COLOR=$GREEN
                    LEVEL_TEXT="INFO "
                    ;;
                "DEBUG"|0)
                    COLOR=$BLUE
                    LEVEL_TEXT="DEBUG"
                    ;;
                *)
                    COLOR=$NC
                    LEVEL_TEXT="$LEVEL"
                    ;;
            esac
            
            echo -e "${COLOR}[${LEVEL_TEXT}]${NC} ${TIME} - ${MESSAGE}"
        else
            echo "$line"
        fi
    done
}

# Default values
LINES=50
MODE="last"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -f|--follow)
            MODE="follow"
            shift
            ;;
        -e|--errors)
            MODE="errors"
            shift
            ;;
        -l|--last)
            LINES="$2"
            MODE="last"
            shift 2
            ;;
        -a|--all)
            MODE="all"
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}\n"
            show_usage
            exit 1
            ;;
    esac
done

# Execute based on mode
case $MODE in
    follow)
        echo -e "${GREEN}Following logs in real-time... (Press Ctrl+C to stop)${NC}\n"
        tail -f "$LOG_FILE" | grep --line-buffered "ldapoufilter" | format_log
        ;;
    errors)
        echo -e "${YELLOW}Showing only errors...${NC}\n"
        grep "ldapoufilter" "$LOG_FILE" | grep -E '"level":(3|"ERROR"|"FATAL")' | format_log
        ;;
    all)
        echo -e "${GREEN}Showing all ldapoufilter logs...${NC}\n"
        grep "ldapoufilter" "$LOG_FILE" | format_log
        ;;
    last)
        echo -e "${GREEN}Showing last $LINES lines...${NC}\n"
        grep "ldapoufilter" "$LOG_FILE" | tail -n "$LINES" | format_log
        ;;
esac

# Show summary
echo -e "\n${BLUE}==================================================${NC}"
echo -e "${BLUE}Tip: Run with -f to follow logs in real-time${NC}"
echo -e "${BLUE}     Run with -e to see only errors${NC}"
echo -e "${BLUE}==================================================${NC}"

