# Collaborator Plugin Fix for LDAP OU Filter

## 🎯 What Was Wrong

**Problem:** The `SearchResultEvent` approach doesn't work for the sharees API in Nextcloud 31.

**Evidence from logs:**
```
✅ /ocs/v2.php/apps/files_sharing/api/v1/sharees?search=bebo  ← Searches happening
✅ LDAP OU Filter app booted successfully  ← App loading
✅ Event listener registered  ← Listener registered
❌ SearchResultEvent detected! ← NEVER APPEARED (This is the problem!)
❌ OU EXTRACTION DEBUG ← Never triggered
```

**Root Cause:** Nextcloud's sharees API doesn't fire the `SearchResultEvent`. It uses the Collaborators system instead.

---

## ✅ The Solution: Collaborator Search Plugin

### What Changed:

#### 1. **New File: `lib/Collaboration/OuFilterPlugin.php`**
- Implements `ISearchPlugin` interface
- Hooks directly into Nextcloud's Collaborator Search system
- Called automatically when users search for people to share with
- Filters results in real-time based on OU matching

#### 2. **Updated: `lib/AppInfo/Application.php`**
- Registers `OuFilterPlugin` as a service
- Registers the plugin with the Collaborators Manager in `boot()`
- Keeps the event listener for compatibility with other search contexts

---

## 📋 Complete File List

```
lib/
├── AppInfo/
│   └── Application.php          ← Updated (plugin registration)
├── Collaboration/
│   └── OuFilterPlugin.php       ← NEW (main filtering logic)
├── Service/
│   └── LdapOuService.php        ← Existing (OU comparison)
└── Listener/
    └── UserSearchListener.php   ← Existing (kept for compatibility)
```

---

## 🚀 Deployment Steps

### Option 1: Full Update (Recommended)
```bash
# On server:
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash update.sh
```

### Option 2: Manual Upload
```bash
# Upload these files to server:
1. lib/Collaboration/OuFilterPlugin.php  ← NEW
2. lib/AppInfo/Application.php           ← UPDATED

# Then:
sudo chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

---

## 🧪 Testing

### 1. Watch Logs
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | \
  grep "ldapoufilter" | \
  grep -E "OU Filter Plugin|OU EXTRACTION|Filtered"
```

### 2. In Nextcloud UI
1. Log in as `hunter1` (cyberfirst OU)
2. Go to Files
3. Click Share on any folder
4. Type "bebo" in the search box

### 3. Expected Log Output
```
✓ LDAP OU Filter app booted successfully
✓ OU Filter Plugin registered with Collaborators Manager
=== OU Filter Plugin ACTIVATED ===
Search query: bebo, limit: 25, offset: 0
Current user: hunter1
=== OU EXTRACTION DEBUG ===
DN: CN=hunter1,OU=cyberfirst,OU=Mail,DC=Frist,DC=loc
FINAL SELECTED OU: OU=cyberfirst
=== OU EXTRACTION DEBUG ===
DN: CN=bebo 01,OU=bebo,OU=Mail,DC=Frist,DC=loc
FINAL SELECTED OU: OU=bebo
✗ User bebo 01 filtered out (different OU)
==> Filtered users: 10 -> 0 users
```

---

## 📊 How It Works

### Before (SearchResultEvent - Didn't Work):
```
User searches → Sharees API → Results returned
                   ↓ 
              (No event fired!)
```

### After (Collaborator Plugin - Works!):
```
User searches → Sharees API → Collaborators Manager
                                    ↓
                              OuFilterPlugin.search()
                                    ↓
                              Filter by OU matching
                                    ↓
                              Return filtered results
```

---

## 🔍 Key Code Sections

### OuFilterPlugin::search()
```php
public function search($search, $limit, $offset, ISearchResult $searchResult): bool {
    $this->logger->info('=== OU Filter Plugin ACTIVATED ===');
    
    // Get current user
    $currentUser = $this->userSession->getUser();
    
    // Filter users based on OU
    $this->filterSearchResultType($searchResult, 'users', $currentUserId);
    
    return false; // Allow other plugins to run too
}
```

### Application::boot()
```php
// Register plugin with Collaborators Manager
$collaboratorsManager = $server->get(\OCP\Collaboration\Collaborators\ISearch::class);
$collaboratorsManager->registerPlugin([
    'shareType' => 'SHARE_TYPE_USER',
    'class' => OuFilterPlugin::class
]);
```

---

## ⚠️ Important Notes

1. **Both approaches are kept:**
   - `OuFilterPlugin` → For sharees API (file sharing)
   - `UserSearchListener` → For other search contexts (Talk, etc.)

2. **Debug logging is verbose:**
   - Useful for troubleshooting
   - Can be reduced later in production

3. **OU matching logic unchanged:**
   - Still uses `LdapOuService::areUsersInSameOu()`
   - Still extracts specific sub-OUs (cyberfirst, bebo, etc.)

---

## 🎉 Success Criteria

After deployment, you should see:
- ✅ "OU Filter Plugin registered with Collaborators Manager" in logs
- ✅ "OU Filter Plugin ACTIVATED" when searching for users
- ✅ OU extraction debug messages for both current user and search results
- ✅ **Only users from same OU appear in search results**
- ✅ Users from different OUs are filtered out

---

## 🐛 Troubleshooting

### Plugin not registering
```bash
# Check logs for error:
sudo bash check_logs.sh -e | grep "Failed to register OU Filter Plugin"

# Common causes:
# - Missing ISearch service
# - Wrong plugin registration syntax
# - Permission issues
```

### Plugin registered but not activating
```bash
# Look for:
sudo bash check_logs.sh -f | grep "OU Filter Plugin ACTIVATED"

# If not appearing:
# - Plugin might not be called by Collaborators Manager
# - Check shareType parameter
```

### Filtering not working
```bash
# Debug OU extraction:
sudo bash check_logs.sh -f | grep "OU EXTRACTION"

# Verify:
# - Both users have DN with OU
# - OU extraction is returning correct values
# - areUsersInSameOu() is being called
```

---

## 📚 References

- Nextcloud Collaborators API: `OCP\Collaboration\Collaborators\ISearchPlugin`
- Sharees endpoint: `/ocs/v2.php/apps/files_sharing/api/v1/sharees`
- Share types: User (0), Group (1), Remote (6), etc.

---

**Ready to deploy!** 🚀

