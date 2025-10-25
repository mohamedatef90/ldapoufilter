# URGENT FIX - Event Listener Not Firing

## Problem
The `SearchResultEvent` listener is registered but not being triggered when users search for other users.

## Solution
I've added **dual registration** of the event listener:
1. Bootstrap registration (standard method)
2. Direct Event Dispatcher registration with high priority (fallback/aggressive method)

## What Changed

### 1. `lib/AppInfo/Application.php`
- Added direct registration via `IEventDispatcher` in the `boot()` method
- Added comprehensive logging to track when events are received
- Set high priority (100) to ensure early execution

### 2. `lib/Listener/UserSearchListener.php`
- Enhanced logging throughout the filtering process
- Added exception handling with detailed error messages
- Added event class verification logging

### 3. New `diagnose.sh` script
- Helps diagnose why events aren't firing
- Shows recent app logs
- Checks event registration status

## Deployment Steps

**On your local machine:**
```bash
# Upload the files
chmod +x deploy_to_server.sh
./deploy_to_server.sh
```

**On the server:**
```bash
cd /var/www/nextcloud/apps/ldapoufilter

# Make scripts executable
chmod +x *.sh

# Run the update
sudo bash update.sh

# Run diagnostics
sudo bash diagnose.sh

# Clear everything and start fresh
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter

# Monitor logs in real-time
sudo bash check_logs.sh -f
```

## Testing

1. Open Nextcloud in your browser
2. Go to Files
3. Select a folder
4. Click "Share"
5. Type "bebo" in the share field

**What to look for in logs:**
- "SearchResultEvent detected! Calling listener..." - Event was received!
- "UserSearchListener::handle called" - Listener is processing
- "Starting to filter search results for user: hunter" - Filtering started
- "Filtered search results: X -> Y users" - Filtering completed

## If Still Not Working

If you still don't see the SearchResultEvent messages, it means Nextcloud 31 may use a different event system. In that case, we'll need to:
1. Implement a Collaborator Search Plugin instead
2. Hook directly into the Sharees API controller

Let me know what the logs show after deployment!
