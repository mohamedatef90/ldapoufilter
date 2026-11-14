# LDAP OU Filter for Nextcloud

A Nextcloud app that filters user search results (sharing dialogs and mentions) so a user only sees other users from the same LDAP / Active Directory organizational unit (OU).

For example, a user in the `sales` OU only sees and can share with other users in `sales`, not users from other OUs.

## How it works

1. A user searches for people in a share dialog (or another place that uses the collaborators search).
2. `OuFilterPlugin` (`lib/Collaboration/OuFilterPlugin.php`) intercepts the results.
3. `LdapOuService` (`lib/Service/LdapOuService.php`) reads each user's LDAP DN from the `ldap_user_mapping` table that Nextcloud's LDAP integration maintains, and extracts the `OU=` parts of the DN. Results are cached in memory for the request.
4. Users whose OU differs from the current user's are removed from the results.

If the current user is not signed in or has no OU, the plugin leaves the results unchanged. `UserSearchListener` and `TalkHooks` also hook into search and Talk events (`lib/Listener/`, `lib/Hooks/`). The app registers no routes.

## Requirements

- Nextcloud 31 or 32 (`appinfo/info.xml`)
- PHP 8.1 or later (`composer.json`)
- Nextcloud's LDAP user integration configured, with LDAP users already synced

## Installation

Copy the app into Nextcloud's `apps` folder and enable it. With the included Makefile (adjust `nextcloud_dir` at the top if your Nextcloud is not in `/var/www/nextcloud`):

```bash
make install     # copy to apps/, set ownership, run occ app:enable
make logs        # follow the Nextcloud log filtered to this app
make uninstall   # disable and remove the app
```

Or manually:

```bash
cp -r ldapoufilter /var/www/nextcloud/apps/
chown -R www-data:www-data /var/www/nextcloud/apps/ldapoufilter
sudo -u www-data php /var/www/nextcloud/occ app:enable ldapoufilter
```

Other Makefile targets: `reinstall`, `enable`, `disable`, `test-ldap`, `help`.

## Verifying

Check the Nextcloud log for the app's messages, then search for users in a share dialog and confirm only users from your OU appear. `test_ou_server.php` can be run from the Nextcloud server for a direct check:

```bash
sudo -u www-data php /var/www/nextcloud/apps/ldapoufilter/test_ou_server.php
```

## Project structure

```
appinfo/info.xml, routes.php          App metadata and (empty) routes
lib/AppInfo/Application.php           Bootstrap and registration
lib/Collaboration/OuFilterPlugin.php  Collaborators search plugin that filters results
lib/Service/LdapOuService.php         DN lookup and OU extraction
lib/Listener/UserSearchListener.php   Search result listener
lib/Hooks/TalkHooks.php               Talk event hook
Makefile, *.sh, test_*.php            Install, deploy, diagnostic, and test helpers
START_HERE.md, INSTALLATION_GUIDE.md, ...   Additional guides
```

## License

AGPL-3.0, as declared in `composer.json` and `appinfo/info.xml`. The repository has no separate LICENSE file.
