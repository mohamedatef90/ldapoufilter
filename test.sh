#!/bin/bash

# Quick Test Script
# =================

NEXTCLOUD_PATH="/var/www/nextcloud"
OCC="sudo -u www-data php $NEXTCLOUD_PATH/occ"

echo "LDAP OU Filter - Quick Test"
echo "============================"
echo ""

# Enable debug logging
echo "1. Enabling debug logging..."
$OCC config:system:set loglevel --value=0

# Clear logs
echo "2. Clearing old logs..."
echo "" > $NEXTCLOUD_PATH/data/nextcloud.log

# Test LDAP connection
echo "3. Testing LDAP connection..."
$OCC ldap:test-config s01

# Show LDAP users
echo "4. Listing some LDAP users..."
$OCC ldap:search "" 5

echo ""
echo "Now:"
echo "1. Open Nextcloud in browser"
echo "2. Login as a user (e.g., bebo)"
echo "3. Try to share a file"
echo "4. Type a username in the share field"
echo "5. Check if filtering works"
echo ""
echo "Watch logs in another terminal:"
echo "tail -f $NEXTCLOUD_PATH/data/nextcloud.log | grep -E 'ldapoufilter|OU'"