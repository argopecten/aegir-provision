# Provision Documentation

This directory contains documentation for the provision manager and orchestration layer.

## Overview

The `Provision/` directory contains:

- **ProvisionManager** - Central orchestration for all provision operations (verify, install, migrate, backup, restore, etc.)

The ProvisionManager coordinates:
- Context loading via ContextRepository
- Service initialization (Apache, MySQL, SSL)
- Config generation and deployment
- Database operations
- File backups and restoration
- Site cloning and migration

All `provision-*` commands delegate to ProvisionManager methods.

See: [../doc/provision-d11.md](../doc/provision-d11.md) for complete provisioning workflows.
