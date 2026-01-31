# AI Agent Quick Reference - aegir-provision

> **⚠️ IMPORTANT**: Before working on this codebase, **READ** [.github/AI-INSTRUCTIONS.md](.github/AI-INSTRUCTIONS.md)  
> That file contains comprehensive architectural documentation, development patterns, and critical guidelines specific to this project.

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
- Drush 13.7+

## Development Guidelines

**Critical Rules**:
1. ⚠️ **Breaking changes allowed** - We ignore backward compatibility
2. ⚠️ **No update hooks** - Do NOT create update hooks unless explicitly requested
3. 📝 **Documentation on request only** - Update docs only when specifically asked
4. 🎯 **Modern PHP only** - Use PHP 8.3+ features freely (typed properties, attributes, enums)
5. 🔄 **Infrastructure first** - Prioritize correct infrastructure operations over migrations
6. 🔒 **Idempotent commands** - All provision commands must be safe to run multiple times

## ⚠️ CRITICAL ARCHITECTURE UNDERSTANDING

### This is a Standalone Drush Command Package (NOT a Drupal Module)

**What this means**:
- ✅ Installed as a Composer dependency: `composer require argopecten/aegir-provision`
- ✅ Commands run **outside** of Drupal bootstrap (can operate on multiple sites)
- ❌ **CANNOT** use Drupal APIs, entities, hooks, or database abstraction
- ❌ **CANNOT** access Drupal's configuration, state, or cache systems
- ❌ **CANNOT** be enabled/disabled like a Drupal module

**✅ Drush 13.7+ Migration Completed**:
The codebase now uses **modern Drush 13.7+ patterns**:
- ✅ Symfony Console `Command` classes with `#[AsCommand]`
- ✅ One command per file under `src/Drush/Commands`
- ✅ Namespace: `Aegir\Provision\Drush\Commands`
- ✅ `ProvisionAutowireTrait` (wraps `AutowireTrait`) for constructor injection
- ✅ `ProvisionServiceRegistry` registers Provision services in the Drush container
- ✅ PSR-4 auto-discovery (no `drush.services.yml`)

See [README.md](../README.md) for current expectations.

**Why this architecture exists**:
- Provision performs **server-level operations** (Apache config, MySQL admin, filesystem)
- Must operate **before** Drupal sites exist (e.g., provision-install creates the site)
- Needs to manage **multiple Drupal sites** from a single command context
- Requires **root/sudo privileges** for webserver and database operations

**Common mistakes to AVOID**:
- ❌ DO NOT try to use `\Drupal::service()`, `\Drupal::database()`, or entity API
- ❌ DO NOT expect Drupal module hooks to work
- ❌ DO NOT use `drush.services.yml` for Drush 13.7+ command registration (deprecated)
- ❌ DO NOT use global command registration via `drush.commands` configuration
- ❌ DO NOT bootstrap Drupal unless explicitly invoking commands on a specific site
- ❌ DO NOT assume Drupal is available - commands can run on bare servers

**What you CAN do**:
- ✅ Use Drush commands to interact with Drupal sites: `drush @site status`
- ✅ Use Symfony components (Process, Filesystem, Yaml)
- ✅ Execute shell commands via ProcessRunner for system operations
- ✅ Read/write YAML alias files in `~/.drush/sites/`
- ✅ Generate configuration files (Apache vhosts, settings.php)

### Command Registration (Drush 13.7+ Standard)

- PSR-4 auto-discovery: commands in `src/Drush/Commands`
- Namespace: `Aegir\Provision\Drush\Commands`
- One command per file, `#[AsCommand]`, `configure()` + `execute()`
- `ProvisionAutowireTrait` (wraps `AutowireTrait`) for dependency injection
- Site-wide commandfiles are installed via Composer or committed under `$PROJECT_ROOT/drush/Commands`
- Valid paths/namespaces (no `src` in the path):
  - `$PROJECT_ROOT/drush/Commands/ExampleCommands.php` → `Drush\Commands`
  - `$PROJECT_ROOT/drush/Commands/example/ExampleCommands.php` → `Drush\Commands\example`
  - `$PROJECT_ROOT/drush/Commands/contrib/dev_modules/ExampleCommands.php` → `Drush\Commands\dev_modules`

See official docs: https://www.drush.org/13.x/commands/

## 📝 Documentation Update Guidelines

**When to update documentation**:
- ✅ User explicitly requests: "update docs", "document this", "update README"
- ✅ After significant architectural changes that affect usage
- ✅ When adding new commands or changing command signatures
- ❌ After routine bug fixes or internal refactoring
- ❌ After every small code change

**Files to update** (when requested):
1. **README.md** - Project overview, quick start, installation
2. **doc/Home.md** - User-friendly guide with examples
3. **doc/provision-d11.md** - Complete technical architecture
4. **.github/AI-INSTRUCTIONS.md** - This file (technical details for AI agents)
5. **.github/AGENTS.md** - Quick reference (you're reading it now)

**How to update docs**:
1. Read existing docs first to understand structure and tone
2. Update all related sections consistently (don't leave stale info)
3. Preserve examples but update paths/commands if changed
4. Keep README.md concise - detailed info goes in doc/
5. Use absolute paths in examples: `/var/aegir/platforms/drupal-11`
6. Test commands before documenting them
7. Update version numbers, PHP requirements, dependency versions

**Documentation sync workflow**:
- doc/*.md files are **automatically synced to GitHub Wiki** via `.github/workflows/sync-wiki.yml`
- Changes to doc/ on push to main/master/dev branches trigger wiki sync
- Never manually edit wiki - always edit doc/ in the repo

**Style guidelines**:
- Use concrete examples: `drush provision-install @example.com`
- Include full context data: `--data='{"uri":"example.com","platform":"platform_d11"}'`
- Explain **why** not just **how** (architecture decisions)
- Keep AI-INSTRUCTIONS.md technical and comprehensive
- Keep AGENTS.md concise and scannable
- Keep Home.md user-friendly for humans

## Essential Documentation

**Start here for this component**:
- **[AI-INSTRUCTIONS.md](AI-INSTRUCTIONS.md)** - Complete technical guide for this component (990+ lines)
- **[doc/Home.md](../doc/Home.md)** - User-facing documentation

**Drush 13 Official Documentation** (AUTHORITATIVE SOURCE):
- **https://www.drush.org/13.x/** - Complete Drush 13 reference
- When working with Drush commands, attributes, or APIs, always analyze and incorporate guidance from this official documentation
- Key sections:
  - Command API: https://www.drush.org/13.x/commands/
  - Attributes: https://www.drush.org/13.x/commands/#attributes
  - Dependency Injection: https://www.drush.org/13.x/dependency-injection/
  - Site Aliases: https://www.drush.org/13.x/site-aliases/
  - Creating Commands: https://www.drush.org/13.x/commands/#creating-custom-drush-commands

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

- `src/Drush/Commands/` - Drush 13.7+ command classes (one command per file)
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
