# OU Filter Fix - Nested OU Support

## Problem Identified

Your Active Directory has **nested OUs** under `Mail`:
```
Mail/
  ├── cyberfirst/ (users like hunter1)
  ├── first/ (users)
  ├── elzoz/ (users)
  └── bebo/ (users)
```

The previous code was extracting **only** the top-level OU (`Mail`), which meant ALL users appeared in the same OU, so filtering wasn't working.

## Solution

Updated the OU extraction logic to:
1. **Filter out the "Mail" parent OU**
2. **Extract the specific sub-OU** (cyberfirst, first, elzoz, bebo)
3. **Add comprehensive debugging** to see exactly what's being extracted

## What Changed

### 1. `lib/Service/LdapOuService.php`
- Updated `extractOuFromDn()` method
- Now filters out "Mail" OU automatically
- Extracts the most specific sub-OU
- Added extensive debug logging to track OU extraction

### 2. `lib/AppInfo/Application.php`
- Already updated with dual event listener registration
- Enhanced logging

### 3. `lib/Listener/UserSearchListener.php`
- Already updated with comprehensive filtering logic
- Enhanced error handling

### 4. New `diagnose.sh` script
- Helps diagnose OU extraction issues
- Shows recent OU-related log entries
- Tests user lookup

## Deployment Steps

### On Your Local Machine:
```bash
cd /Users/roaya/Roaya-files/Development/nxtcloud/NC-Domain isolation/ldapoufilter

# Make scripts executable
chmod +x diagnose.sh update.sh deploy_to_server.sh

# Deploy to server
./deploy_to_server.sh
```

### On The Server:
```bash
cd /var/www/nextcloud/apps/ldapoufilter

# Make all scripts executable
sudo chmod +x *.sh

# Run the update
sudo bash update.sh

# Run diagnostics
sudo bash diagnose.sh

# Monitor logs to see OU extraction
sudo bash check_logs.sh -f
```

## Testing Process

### Step 1: Check OU Extraction
1. Log in as a user from `cyberfirst` OU (e.g., hunter1)
2. Search for users in file sharing
3. Check logs for these messages:
```
=== OU EXTRACTION DEBUG ===
DN: CN=hunter1,OU=cyberfirst,OU=Mail,DC=Frist,DC=loc
Found 2 OU levels: ["OU=cyberfirst","OU=Mail"]
Selected specific OU (filtered out 'Mail'): OU=cyberfirst
=== FINAL SELECTED OU: OU=cyberfirst ===
```

### Step 2: Verify Filtering
When user `hunter1` (from `cyberfirst`) searches for "bebo":
- ✅ **Should see**: Users from `cyberfirst` OU only
- ❌ **Should NOT see**: Users from `bebo`, `first`, `elzoz` OUs

### Step 3: Check Logs
```bash
sudo bash check_logs.sh -f
```

Look for:
- `OU EXTRACTION DEBUG` - Shows DN being parsed
- `Found X OU levels` - Shows all OUs in hierarchy
- `FINAL SELECTED OU` - Shows which OU is being used for comparison
- `Filtered search results: X -> Y users` - Shows filtering in action

## Expected DN Structures

The code handles both possible DN formats:

### Format 1 (Most Likely):
```
CN=hunter1,OU=cyberfirst,OU=Mail,DC=Frist,DC=loc
                 ↑            ↑
            (specific)    (parent)
```
Result: Uses `OU=cyberfirst`

### Format 2 (Alternative):
```
CN=hunter1,OU=Mail,OU=cyberfirst,DC=Frist,DC=loc
              ↑          ↑
          (parent)  (specific)
```
Result: Still filters out `Mail` and uses `OU=cyberfirst`

## Troubleshooting

### If filtering still doesn't work:

1. **Check the actual DN structure:**
```bash
sudo -u www-data php /var/www/nextcloud/occ user:info hunter1
```

2. **Look at the logs** to see what OUs are being extracted:
```bash
sudo bash diagnose.sh
```

3. **Verify the event listener is firing:**
Look for `SearchResultEvent detected!` in logs when you search

4. **Check if users are actually in LDAP:**
```bash
sudo bash test_filter.sh
```

### If logs show "Mail" for all users:

This means the DN structure is different. The code has fallback logic, but you might need to adjust which OU index to use. Look at the logs showing:
```
Found X OU levels: ["OU=...", "OU=...", ...]
```

Then we can adjust the selection logic if needed.

## Quick Commands

```bash
# Deploy updates
./deploy_to_server.sh

# On server - full update
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash update.sh

# Monitor logs
sudo bash check_logs.sh -f

# Run diagnostics
sudo bash diagnose.sh

# Test the filter
sudo bash test_filter.sh
```

## What Should Happen Now

1. When you log in as `hunter1` (cyberfirst OU)
2. And search for users when sharing a file
3. You should **ONLY** see users from the `cyberfirst` OU
4. Users from `bebo`, `first`, `elzoz` should be **filtered out**

## Next Steps

1. Deploy the updated code
2. Run diagnostics to see OU extraction
3. Test searching for users
4. Check logs to verify filtering
5. Let me know what the logs show!

