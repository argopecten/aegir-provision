# AI Agent Quick Reference - aegir-provision

**Repository**: Backend Component (Drush Provision System)  
**Current Location**: `/var/aegir/aegir-2601/drush/Commands/contrib/aegir-provision/`  
**GitHub**: https://github.com/argopecten/aegir-provision  
**Part of**: Aegir Hostmaster (multi-repository project)

## You Are Here

This is the **Backend Component** - a standalone Git repository that is also a submodule of the main aegir-hostmaster project.

**This component handles**:
- ✅ Drush commands (provision-install, provision-verify, etc.)
- ✅ Context system (Server, Platform, Site YAML aliases)
- ✅ Service layer (ApacheService, MySqlService, SslManager)
- ✅ Infrastructure automation (Apache, MySQL, file operations)
- ❌ Drupal entities (that's aegir-hosting)
- ❌ Task queue and UI (that's aegir-hosting)
- ❌ Theme/presentation (that's aegir-eldir)

## Development Environment

**Target Platform**: Ubuntu 24.04 LTS or later versions  
**LAMP Stack**:
- Apache 2.4+ with PHP-FPM
- PHP 8.3+
- MySQL 8.0+
- Drush 13.x

## Development Guidelines

**Critical Rules**:
1. ⚠️ **Breaking changes allowed** - We ignore backward compatibility
2. ⚠️ **No update hooks** - Do NOT create update hooks unless explicitly requested
3. 📝 **Documentation on request only** - Update docs only when specifically asked
4. 🎯 **Modern PHP only** - Use PHP 8.3+ features freely (typed properties, attributes, enums)
5. 🔄 **Infrastructure first** - Prioritize correct infrastructure operations over migrations
6. 🔒 **Idempotent commands** - All provision commands must be safe to run multiple times

## Essential Documentation

**Start here for this component**:
- **[AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md)** - Complete technical guide for this component (990+ lines)
- **[doc/Home.md](../doc/Home.md)** - User-facing documentation

**For cross-component work**:
- **[Parent Repo AI Guide](../../../../.github/AI-AGENT-GUIDE.md)** - Navigation across all 4 repositories
- **[Parent Repo Architecture](../../../../.github/ARCHITECTURE.md)** - Integration architecture

## Related Components

**Frontend** (when you need to understand entity operations):
- Path: `../../web/modules/contrib/aegir-hosting/`
- AI Docs: [../../../web/modules/contrib/aegir-hosting/.github/AI-INSTRUCTIONS.md](../../../web/modules/contrib/aegir-hosting/.github/AI-INSTRUCTIONS.md)

**Theme** (when you need to understand UI rendering):
- Path: `../../web/themes/contrib/aegir-eldir/`
- AI Docs: [../../../web/themes/contrib/aegir-eldir/.github/AI-INSTRUCTIONS.md](../../../web/themes/contrib/aegir-eldir/.github/AI-INSTRUCTIONS.md)

## Quick Navigation

```bash
# Check context
pwd                    # Should show: .../aegir-provision
git remote -v          # Should show: argopecten/aegir-provision

# Work in this component
git status             # Shows changes in provision only
git checkout -b feat   # Creates branch in provision repo

# Commit workflow
git add . && git commit -m "message"  # Commit in provision
cd ../../../../                        # Go to parent repo
git add drush/Commands/contrib/aegir-provision  # Stage submodule update
git commit -m "Update provision"       # Commit in parent
```

## Component Boundaries

**You should modify files here when**:
- Adding/changing Drush commands
- Implementing service operations (Apache, MySQL, SSL)
- Updating context definitions
- Changing config templates (vhosts, settings.php)
- Modifying infrastructure automation

**You should NOT modify files here when**:
- Adding entity fields → Use aegir-hosting
- Changing forms/validation → Use aegir-hosting
- Creating task queue items → Use aegir-hosting
- Modifying templates/CSS → Use aegir-eldir

## Integration Points

**This component reads**:
- Drush alias YAML: `~/.drush/sites/*.site.yml` (written by ContextRegistry)

**This component is invoked by**:
- Frontend's BackendInvoker: `exec drush provision-install @site`

**This component operates on**:
- Apache configs: `/etc/apache2/sites-available/*.conf`
- MySQL databases: CREATE/DROP/GRANT operations
- File system: Site directories, settings.php
- Drupal: Via `drush site:install` and other core commands

## Key Files

- `src/Commands/ProvisionCommands.php` - All provision-* Drush commands
- `src/Core/Context.php` - Immutable context data structure
- `src/Service/Http/ApacheService.php` - Apache vhost management
- `src/Service/Db/MySqlService.php` - MySQL operations
- `src/Provision/ProvisionManager.php` - Task orchestration

## Critical Principles

- ✅ Commands must be **idempotent** (safe to run multiple times)
- ✅ Always validate context before operating
- ✅ Services must handle errors gracefully
- ✅ Never assume frontend state - read from contexts only
- ✅ Template rendering must escape properly

---

**Need more context?** Read [AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md) for complete technical details.
