# Makefile for LDAP OU Filter

app_name=ldapoufilter
nextcloud_dir=/var/www/nextcloud

# Install the app
install:
	@echo "Installing $(app_name)..."
	@sudo cp -r . $(nextcloud_dir)/apps/$(app_name)
	@sudo chown -R www-data:www-data $(nextcloud_dir)/apps/$(app_name)
	@sudo -u www-data php $(nextcloud_dir)/occ app:enable $(app_name)
	@echo "✓ Installation complete"

# Uninstall the app
uninstall:
	@echo "Uninstalling $(app_name)..."
	@sudo -u www-data php $(nextcloud_dir)/occ app:disable $(app_name)
	@sudo rm -rf $(nextcloud_dir)/apps/$(app_name)
	@echo "✓ Uninstallation complete"

# Reinstall (uninstall then install)
reinstall: uninstall install

# Enable the app
enable:
	@sudo -u www-data php $(nextcloud_dir)/occ app:enable $(app_name)
	@echo "✓ App enabled"

# Disable the app
disable:
	@sudo -u www-data php $(nextcloud_dir)/occ app:disable $(app_name)
	@echo "✓ App disabled"

# Show logs
logs:
	@tail -f $(nextcloud_dir)/data/nextcloud.log | grep $(app_name)

# Test LDAP connection
test-ldap:
	@sudo -u www-data php $(nextcloud_dir)/occ ldap:test-config s01

# Help
help:
	@echo "Available commands:"
	@echo "  make install    - Install the app"
	@echo "  make uninstall  - Uninstall the app"
	@echo "  make reinstall  - Reinstall the app"
	@echo "  make enable     - Enable the app"
	@echo "  make disable    - Disable the app"
	@echo "  make logs       - Show app logs"
	@echo "  make test-ldap  - Test LDAP connection"

.PHONY: install uninstall reinstall enable disable logs test-ldap help