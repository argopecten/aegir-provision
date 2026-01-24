# Core Documentation

This directory contains documentation for core abstractions and utilities.

## Overview

The `Core/` directory provides foundational classes:

- **Context** - Immutable context data structure (server/platform/site)
- **ContextRepository** - Load/save contexts to Drush YAML aliases
- **AliasStore** - Interface to Drush site alias system
- **Filesystem** - Safe file operations with error handling
- **ProcessRunner** - Execute shell commands via Symfony Process
- **ConfigPaths** - Path resolution for aegir_root, config_path, etc.

These classes abstract low-level operations and provide consistent error handling throughout the system.

See: [../doc/provision-d11.md](../doc/provision-d11.md) for context system architecture.
