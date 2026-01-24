# Commands Documentation

This directory contains documentation for Drush 13 command implementations.

## Overview

The `Commands/` directory implements the `provision-*` command surface using Drush 13 attributes and the `DrushCommands` base class.

Key commands:
- `provision-save` - Create/update contexts
- `provision-verify` - Regenerate configs and verify state
- `provision-install` - Install Drupal sites
- `provision-migrate` - Move sites between platforms
- `provision-backup` / `provision-restore` - Site backup operations
- `provision-clone` - Clone sites with optional platform change

## Architecture

Commands are registered in `drush.services.yml` and delegate to `ProvisionManager` for orchestration.

See: [../doc/provision-d11.md](../doc/provision-d11.md) for detailed command specifications.
