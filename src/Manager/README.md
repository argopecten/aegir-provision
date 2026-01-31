# Provision Manager Architecture

This directory contains specialized operation managers that handle different aspects of Aegir Provision operations.

## Design Principles

1. **Default Multi-Context Support**: By default, all managers should handle servers, platforms, and sites unless there's a specific reason not to.

2. **Explicit Exceptions**: When an operation is context-specific, this must be explicitly stated in the class documentation.

3. **Single Responsibility**: Each manager focuses on one type of operation.

## Manager Classes

### Universal Context Support (Sites, Platforms, Servers)

- **VerificationManager**: Verifies configuration and prerequisites for all context types
- **DeleteManager**: Handles deletion with cleanup for all context types  
- **LockManager**: Manages lock/unlock operations for all context types
- **InstallationManager**: Installs and configures all context types (enable/disable/loginReset are site-specific)
- **BackupRestoreManager**: Backup/restore for sites and platforms (servers optional/future)

### Site-Specific Operations

These managers currently only support site contexts, with explicit documentation:

- **CloneManager**: Clone sites to same or different platforms (could support platform cloning)
- **MigrationManager**: Migrate sites between platforms (site-to-platform operation)

## Shared Utilities

- **ContextLoader**: Loads related contexts (platform from site, server from platform, etc.)
- **PathResolver**: Resolves filesystem paths for all context types
- **DatabaseManager**: Manages database operations and credentials

## Operation Distinctions

### Enable/Disable vs Lock/Unlock

These operations serve different purposes and should not be confused:

**Enable/Disable** (Site-Specific, InstallationManager)
- **Purpose**: Control web traffic to sites
- **Scope**: Sites only
- **Effect**: Modifies Apache vhost configuration and reloads web server
- **Enforcement**: Hard - physically blocks/allows web access
- **Use case**: Taking a site offline for users
- **Files**: Manages vhost config files in active/disabled directories

**Lock/Unlock** (Universal, LockManager)
- **Purpose**: Prevent Aegir operations on contexts
- **Scope**: All context types (sites, platforms, servers)
- **Effect**: Creates/removes `.aegir.lock` filesystem marker
- **Enforcement**: Soft - advisory flag for Aegir workflows
- **Use case**: Protecting contexts from automated or accidental changes
- **Files**: Simple marker file at context root

**Combined Usage**: For critical maintenance, you might lock a context (prevent Aegir operations), disable it (take offline), perform work, then enable and unlock.

## Extending Operations

When adding new operations or extending existing ones:

1. Check if the operation logically applies to multiple context types
2. If yes, implement support for all applicable types
3. If no, document why in the class docblock with "Note: ..."
4. Use explicit type checks only when behavior differs significantly between types
