# Upgrade Summary (Aegir Provision)

This document summarizes the major upgrade changes made in this repository to target modern Drupal hosting (Drupal 8-11), PHP 8.3+, and Drush 13+.

## Major changes

- Dropped Drupal 6 and 7 support across platform scripts, templates, and legacy routing.
- Dropped Hostmaster profile install/migrate/uninstall support.
- Removed Debian packaging, legacy CI scripts, and test scaffolding.
- Removed non-Composer platform deployment paths (drush-make/makefiles), so platforms are Composer-only.

## Platform model refactor

- Introduced an object-oriented platform model in `Provision/Platform/` with per-major-version handlers:
  - `Drupal8`, `Drupal9`, `Drupal10`, `Drupal11`, and `DrupalBase`.
- Platform operations (install, import, deploy, verify, cron key, packages) now route through platform classes.
- Added dedicated scripts for Drupal 10 and 11 (`*_10.inc`, `*_11.inc`) to capture version-specific behaviors.

## Drush and PHP version targeting

- Each platform class declares supported PHP and Drush versions:
  - Drupal 8: PHP >=7.3 <8.2, Drush ^10
  - Drupal 9: PHP >=8.0 <8.3, Drush ^10/^11/^12
  - Drupal 10: PHP >=8.1 <8.4, Drush ^11/^12/^13
  - Drupal 11: PHP >=8.3, Drush ^13

## Drush 13 compatibility improvements

- Added Drush 13-compatible shell helpers in `provision.inc` to replace deprecated Drush 8 APIs:
  - `provision_shell_exec()` / `provision_shell_exec_output()`
  - `provision_drush_server_home()`
  - `provision_drush_command_name()`
  - `provision_core_call_rsync()`
  - `provision_get_context()`
- `provision_process()` now uses Symfony Process in a Drush 13-safe way for string commands.
- db/http/server operations that invoked deprecated Drush shell/rsync APIs have been routed through the new helpers.

## Central Dispatcher behavior

- Platform operations run with the backend Drush (Drush 13+).
- Site operations on a hosted platform run with the platform's own Drush (resolved via `vendor/bin/drush` in the platform root).

## Drupal 10/11 script tailoring

- Drupal 10/11 import scripts use modern APIs (`Database::getConnectionInfo()` and language manager).
- Drupal 10/11 install scripts no longer set `clean_url`.
- Package discovery for Drupal 10/11 uses `Extension::getPathname()` with a `drupal_get_filename()` fallback.

