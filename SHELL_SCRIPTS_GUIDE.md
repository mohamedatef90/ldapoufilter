# Shell Scripts Guide - LDAP OU Filter

## 📋 Overview

This document describes all shell scripts included in the LDAP OU Filter project. Each script serves a specific purpose for installation, deployment, testing, and troubleshooting.

---

## 🚀 Deployment Scripts

### 1. `install.sh`
**Purpose**: Install the app on the server  
**Run Location**: On the server  
**Requires**: Root privileges

**What it does**:
- Copies app files to `/var/www/nextcloud/apps/ldapoufilter`
- Sets correct permissions (`www-data:www-data`)
- Enables the app using Nextcloud OCC
- Verifies the installation

**Usage**:
```bash
# On the server
sudo bash install.sh
```

**Dependencies**:
- Nextcloud installed at `/var/www/nextcloud`
- Root access
- PHP with Nextcloud OCC

---

### 2. `deploy_to_server.sh`
**Purpose**: Upload and deploy app to remote server from local machine  
**Run Location**: On your local machine  
**Requires**: SSH access to server

**What it does**:
- Prompts for server IP and SSH credentials
- Uploads files using `rsync` (excludes .git, .md files, and deployment scripts)
- Runs deployment commands on the server
- Creates backup of existing installation
- Sets permissions, enables app, clears cache
- Enables debug logging (level 0)
- Optionally opens SSH connection to server

**Usage**:
```bash
# On your local machine
bash deploy_to_server.sh
```

**Features**:
- Interactive: Prompts for server details
- Progress: Shows upload progress
- Backup: Automatically backs up existing installation
- Cache clear: Clears Nextcloud cache after deployment

---

### 3. `upload_to_server.sh`
**Purpose**: Upload app files to server using SCP  
**Run Location**: On your local machine  
**Requires**: SSH access to server

**What it does**:
- Creates tar.gz archive of app files
- Uploads archive via SCP
- Extracts and installs on remote server
- Disables/re-enables app
- Creates backup

**Usage**:
```bash
# Edit SERVER_IP and SERVER_USER at the top of the file
bash upload_to_server.sh
```

**Configuration**:
Edit these variables at the top of the script:
```bash
SERVER_IP="192.168.2.200"
SERVER_USER="root"
SERVER_PATH="/tmp"
```

---

### 4. `deploy_fix.sh`
**Purpose**: Deploy specific fixes to server  
**Run Location**: On your local machine  
**Requires**: SSH access to server

**What it does**:
- Uploads fixed files via SCP
- Updates permissions
- Restarts Apache
- Clears Nextcloud cache

**Usage**:
```bash
# On your local machine
bash deploy_fix.sh
```

**Configuration**:
Edit `SERVER` variable in the script:
```bash
SERVER="192.168.2.68"
```

---

### 5. `update.sh`
**Purpose**: Update existing installation on server  
**Run Location**: On the server  
**Requires**: Root privileges

**What it does**:
- Creates backup of current app (`app.backup.YYYYMMDD_HHMMSS`)
- Copies new files from current directory
- Sets permissions
- Disables and re-enables app
- Provides next steps for testing

**Usage**:
```bash
# On the server
cd /path/to/updated/ldapoufilter
sudo bash update.sh
```

**Safety**:
- ✅ Creates backup before updating
- ✅ Can rollback using backup directory

---

## 🧪 Testing Scripts

### 6. `test_filter.sh`
**Purpose**: Comprehensive test and verification of app functionality  
**Run Location**: On the server  
**Requires**: Nextcloud access

**What it does**:
1. Checks if app is installed
2. Checks if app is enabled
3. Checks LDAP configuration
4. Tests LDAP connection
5. Checks PHP LDAP extension
6. Checks Nextcloud log level
7. Shows recent logs for ldapoufilter
8. Tests user search
9. Provides summary and next steps

**Usage**:
```bash
# On the server
bash test_filter.sh
```

**Output**: Shows ✓/✗ status for each check

---

### 7. `test.sh`
**Purpose**: Quick test of app installation  
**Run Location**: On the server

**Usage**:
```bash
bash test.sh
```

---

## 🔍 Diagnostic Scripts

### 8. `debug.sh`
**Purpose**: Detailed diagnostics of app and LDAP setup  
**Run Location**: On the server  
**Requires**: Root privileges

**What it checks**:
1. Nextcloud installation
2. PHP version
3. LDAP app status
4. LDAP OU Filter app status
5. LDAP connection
6. LDAP configuration summary
7. LDAP user count
8. Recent logs
9. Test specific user OU detection
10. Recommendations

**Usage**:
```bash
# On the server
sudo bash debug.sh
```

**Interactive**: Prompts for username to test OU detection

---

### 9. `diagnose.sh`
**Purpose**: Diagnostic tool for troubleshooting  
**Run Location**: On the server

**What it does**:
- Checks event dispatcher functionality
- Shows recent app logs
- Checks OU extraction debug logs
- Checks SearchResultEvent registration
- Shows app status
- Allows testing specific user OU

**Usage**:
```bash
# On the server
bash diagnose.sh
```

**Interactive**: Prompts for username to diagnose

---

### 10. `check_logs.sh`
**Purpose**: Monitor Nextcloud logs for ldapoufilter activity  
**Run Location**: On the server

**What it does**:
- Shows recent ldapoufilter logs
- Can follow logs in real-time (like `tail -f`)
- Can filter for errors only
- Colors output by log level
- Formats JSON log entries nicely

**Usage**:
```bash
# Show last 50 lines
bash check_logs.sh

# Follow logs in real-time (Ctrl+C to stop)
bash check_logs.sh -f

# Show only errors
bash check_logs.sh -e

# Show last 100 lines
bash check_logs.sh -l 100

# Show all logs (no limit)
bash check_logs.sh -a

# Help
bash check_logs.sh -h
```

**Options**:
- `-f, --follow`: Follow logs in real-time
- `-e, --errors`: Show only errors
- `-l, --last N`: Show last N lines
- `-a, --all`: Show all logs
- `-h, --help`: Show help

**Features**:
- Color-coded by log level (ERROR=red, WARN=yellow, INFO=green, DEBUG=blue)
- Parses JSON log format
- Clean, readable output

---

## 📦 Utility Scripts

### 11. `QUICK_COMMANDS.sh`
**Purpose**: Quick reference of useful commands  
**Run Location**: Read-only reference

**Contains**:
- OCC commands
- Log checking commands
- App management commands
- Testing commands

**Usage**:
```bash
# Just read it
cat QUICK_COMMANDS.sh
```

---

### 12. `DEPLOY_TYPE_FIX.sh` ⚠️
**Purpose**: Deploy specific type hint fixes  
**Run Location**: On local machine

**Note**: This was used for a specific fix and may not be needed for new installations.

---

### 13. `verify_collaborator_plugin.sh` ⚠️
**Purpose**: Verify Collaborator Plugin registration  
**Run Location**: On the server

**Note**: This was used to verify a specific fix.

---

## 📊 Script Summary Table

| Script | Location | Purpose | Run As |
|--------|----------|---------|--------|
| `install.sh` | Server | Install app | Root |
| `deploy_to_server.sh` | Local | Deploy to server | User |
| `upload_to_server.sh` | Local | Upload files | User |
| `deploy_fix.sh` | Local | Deploy fixes | User |
| `update.sh` | Server | Update app | Root |
| `test_filter.sh` | Server | Test app | User |
| `test.sh` | Server | Quick test | User |
| `debug.sh` | Server | Diagnose issues | Root |
| `diagnose.sh` | Server | Troubleshoot | User |
| `check_logs.sh` | Server | Monitor logs | User |

---

## 🎯 Recommended Workflow

### Fresh Installation
```bash
# On your local machine
bash deploy_to_server.sh
```

### Update Existing Installation
```bash
# On your local machine
bash deploy_to_server.sh
# OR if you already uploaded files to server
ssh root@server
cd /var/www/nextcloud/apps/ldapoufilter
bash update.sh
```

### Troubleshooting
```bash
# On the server
# 1. Check app status
bash test_filter.sh

# 2. Detailed diagnostics
sudo bash debug.sh

# 3. Monitor logs in real-time
bash check_logs.sh -f

# 4. Check specific issues
bash diagnose.sh
```

---

## 🛠️ Script Dependencies

### Common Requirements
- **Bash 4+**: All scripts use bash
- **Color support**: Scripts use ANSI color codes
- **Nextcloud OCC**: Most scripts use `occ` commands
- **Root access**: Installation/update scripts need root
- **SSH**: Deployment scripts need SSH access

### Optional Tools
- **jq**: For parsing JSON log files (used in some scripts)
- **rsync**: For efficient file transfers (used in `deploy_to_server.sh`)
- **scp**: For file uploads (used in `upload_to_server.sh`)

---

## 🔧 Customization

### Changing Server IP
Most scripts have the server IP/hostname defined at the top. Edit the `SERVER_IP` or `SERVER` variable:
```bash
# In deploy_fix.sh, upload_to_server.sh, etc.
SERVER="your-server-ip"
```

### Changing Nextcloud Path
If your Nextcloud is installed in a different location:
```bash
# Edit scripts with NEXTCLOUD_PATH variable
NEXTCLOUD_PATH="/your/custom/path"
```

### Changing Web User
If your web server runs as a different user:
```bash
# Edit scripts with WEB_USER variable
WEB_USER="your-web-user"
```

---

## ⚠️ Important Notes

1. **Backup**: Update scripts (`update.sh`, `deploy_to_server.sh`) create backups automatically
2. **Permissions**: All scripts set `www-data:www-data` ownership
3. **Testing**: Always run `test_filter.sh` after installation
4. **Logs**: Use `check_logs.sh -f` to monitor in real-time
5. **Root Access**: Some scripts require sudo/root

---

## 🆘 Troubleshooting Scripts

### Script won't run
```bash
# Make sure it's executable
chmod +x script_name.sh

# Run with bash
bash script_name.sh
```

### Permission denied
```bash
# Run with appropriate user
sudo bash install.sh

# Or check ownership
ls -l script_name.sh
```

### Connection failed (deployment scripts)
```bash
# Test SSH connection
ssh user@server

# Check SSH key authentication
ssh-copy-id user@server
```

### Script hangs
- Check SSH connection
- Check server resources
- Look for error messages
- Try running commands manually

---

## 📚 Quick Reference

### Check if app is enabled
```bash
sudo -u www-data php /var/www/nextcloud/occ app:list | grep ldapoufilter
```

### Enable app
```bash
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

### Check logs
```bash
tail -f /var/www/nextcloud/data/nextcloud.log | grep ldapoufilter
```

### Clear cache
```bash
sudo -u www-data php /var/www/nextcloud/occ cache:clear
```

---

**For detailed installation instructions, see [INSTALLATION_GUIDE.md](INSTALLATION_GUIDE.md)**

