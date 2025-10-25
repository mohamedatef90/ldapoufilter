# 🚀 DEPLOY COLLABORATOR PLUGIN FIX - Quick Guide

## ⚡ TL;DR

The `SearchResultEvent` doesn't work for user searches in file sharing. We've implemented a **Collaborator Plugin** that hooks directly into the sharees API.

---

## 📦 What's New

### New Files Created:
```
lib/Collaboration/OuFilterPlugin.php  ← Main filtering plugin
```

### Updated Files:
```
lib/AppInfo/Application.php  ← Plugin registration
```

### Documentation:
```
COLLABORATOR_PLUGIN_FIX.md  ← Technical details
DEPLOY_COLLABORATOR_FIX.md  ← This file
```

---

## 🎯 3-Step Deployment

### **Step 1: Upload Files**

Upload these files to your server at `/var/www/nextcloud/apps/ldapoufilter/`:

```
lib/Collaboration/OuFilterPlugin.php
lib/AppInfo/Application.php
```

Or use the full update script:
```bash
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash update.sh
```

---

### **Step 2: Restart App**

```bash
sudo -u www-data php /var/www/nextcloud/occ app:disable ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

---

### **Step 3: Verify**

#### Watch logs in real-time:
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | \
  grep "ldapoufilter" | \
  grep -E "OU Filter Plugin|OU EXTRACTION|Filtered"
```

#### Test in browser:
1. Log in as user from `cyberfirst` OU (e.g., `hunter1`)
2. Go to Files → Share a folder
3. Search for "bebo" (different OU)
4. **Result:** Should see NO bebo users (they're in different OU)

---

## ✅ Success Indicators

You should see these in logs:

```
✅ LDAP OU Filter app booted successfully
✅ OU Filter Plugin registered with Collaborators Manager
✅ === OU Filter Plugin ACTIVATED ===  ← When you search
✅ Current user: hunter1
✅ === OU EXTRACTION DEBUG ===
✅ FINAL SELECTED OU: OU=cyberfirst
✅ ✗ User bebo 01 filtered out (different OU)
✅ ==> Filtered users: 10 -> 0 users
```

---

## 🐛 If It Doesn't Work

### Check 1: Is plugin registered?
```bash
grep "OU Filter Plugin registered" /var/www/nextcloud/data/nextcloud.log | tail -1
```

**Should show:** `✓ OU Filter Plugin registered with Collaborators Manager`

**If not:** Check for errors:
```bash
grep "Failed to register OU Filter Plugin" /var/www/nextcloud/data/nextcloud.log
```

---

### Check 2: Is plugin activated during search?
```bash
# Watch logs, then search for users in Nextcloud
tail -f /var/www/nextcloud/data/nextcloud.log | grep "OU Filter Plugin ACTIVATED"
```

**Should show:** `=== OU Filter Plugin ACTIVATED ===` when you search

**If not:** The plugin isn't being called. Check Nextcloud version compatibility.

---

### Check 3: Is OU extraction working?
```bash
grep "OU EXTRACTION DEBUG" /var/www/nextcloud/data/nextcloud.log | tail -10
```

**Should show:** DN and OU for both current user and search results

**If not:** LDAP connection issue or DN format problem.

---

## 📊 Comparison: Before vs After

### Before (Event Listener - Didn't Work):
```
User searches for "bebo"
  ↓
/api/v1/sharees?search=bebo
  ↓
SearchResultEvent ← NEVER FIRED!
  ↓
All users returned (NO FILTERING)
```

### After (Collaborator Plugin - Works!):
```
User searches for "bebo"
  ↓
/api/v1/sharees?search=bebo
  ↓
Collaborators Manager calls OuFilterPlugin.search()
  ↓
Plugin filters by OU
  ↓
Only same-OU users returned ✅
```

---

## 🎨 Visual Test

**Test Scenario:**
- Logged in as: `hunter1` (OU=cyberfirst)
- Search for: `bebo`
- bebo users are in: `OU=bebo`

**Expected Result:**
```
Search results: (empty)
```

**Why?** Because hunter1 (cyberfirst) and bebo users (bebo) are in different OUs!

**To verify filtering is working:**
- Search for other `cyberfirst` users → Should appear ✅
- Search for `bebo` users → Should NOT appear ✅
- Search for `elzoz` users → Should NOT appear ✅

---

## 🔍 Debug Commands

### See all ldapoufilter activity:
```bash
tail -100 /var/www/nextcloud/data/nextcloud.log | grep "ldapoufilter"
```

### See only important messages:
```bash
tail -100 /var/www/nextcloud/data/nextcloud.log | \
  grep "ldapoufilter" | \
  grep -E "registered|ACTIVATED|OU EXTRACTION|Filtered"
```

### See sharees API calls:
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | grep "sharees"
```

### See errors only:
```bash
sudo bash check_logs.sh -e
```

---

## 📝 Quick Troubleshooting Checklist

- [ ] Files uploaded to correct location
- [ ] Permissions set (chown www-data:www-data)
- [ ] App disabled and re-enabled
- [ ] "OU Filter Plugin registered" appears in logs
- [ ] "OU Filter Plugin ACTIVATED" appears when searching
- [ ] OU extraction shows correct OUs for both users
- [ ] Filtered count shows reduction in results
- [ ] UI shows only same-OU users

---

## 🎯 Commands Summary

```bash
# Deploy
cd /var/www/nextcloud/apps/ldapoufilter
sudo bash update.sh

# Monitor logs
tail -f /var/www/nextcloud/data/nextcloud.log | \
  grep "ldapoufilter" | \
  grep -E "Plugin|OU EXTRACTION|Filtered"

# Check status
sudo bash diagnose.sh

# Test
# 1. Login to Nextcloud
# 2. Files → Share → Search for users
# 3. Verify filtering works
```

---

## 💡 Pro Tips

1. **Keep logs running while testing** - You'll see exactly what's happening
2. **Test with users from different OUs** - Makes filtering obvious
3. **Check both exact and partial searches** - Both should be filtered
4. **Clear browser cache** - If results seem cached

---

## 🎉 Success!

Once you see filtered results and the plugin activation messages, **you're done!** The app is working correctly and filtering users by OU.

---

**Need help?** Check `COLLABORATOR_PLUGIN_FIX.md` for technical details.

**Questions about OUs?** Check `OU_FIX_GUIDE.md` for OU extraction troubleshooting.

