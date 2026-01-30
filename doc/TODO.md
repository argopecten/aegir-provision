# Aegir Provision - TODO & Roadmap

**Last Updated**: January 30, 2026

---

## ⚠️ HIGH PRIORITY: Drush 13.7+ Migration

**Status**: Not Started  
**Urgency**: Critical - Current implementation uses deprecated Drush 12 patterns  
**Reference**: https://www.drush.org/13.x/commands/

### Overview

The codebase currently uses Drush 12 command patterns which are deprecated in Drush 13.7+ but still functional. This migration is essential for long-term maintainability and compliance with modern Drush standards.

### Current Implementation (DEPRECATED)

```php
// src/Commands/ProvisionCommands.php - USING DEPRECATED PATTERNS
namespace Aegir\ProvisionD11\Commands;

use Drush\Commands\DrushCommands;  // ⚠️ DEPRECATED base class
use Drush\Attributes as CLI;

class ProvisionCommands extends DrushCommands {  // ⚠️ Should extend Symfony Command
  #[CLI\Command(name: 'provision:install')]  // ⚠️ DEPRECATED attribute
  #[CLI\Argument(name: 'site', description: 'Site context name')]
  public function install(string $site): void {
    // Manual dependency instantiation in every method ⚠️ DEPRECATED
    $manager = new ProvisionManager(...);
  }
}
```

```yaml
# drush.services.yml - ⚠️ DEPRECATED in Drush 13.7+
services:
  aegir_provision_d11.commands:
    class: Aegir\ProvisionD11\Commands\ProvisionCommands
    tags:
      - { name: drush.command }
```

### Target Implementation (Drush 13.7+ Standard)

```php
// src/Commands/ProvisionInstallCommand.php - ONE COMMAND PER FILE
namespace Aegir\ProvisionD11\Commands;

use Symfony\Component\Console\Command\Command;  // ✅ Modern base class
use Symfony\Component\Console\Attribute\AsCommand;  // ✅ Modern attribute
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Drush\Commands\AutowireTrait;  // ✅ Dependency injection

#[AsCommand(
    name: 'provision:install',
    description: 'Install a Drupal site',
    aliases: ['pvi']
)]
class ProvisionInstallCommand extends Command {
    use AutowireTrait;  // ✅ Enables constructor injection

    public function __construct(
        private readonly ProvisionManager $manager,  // ✅ Auto-injected
        private readonly LoggerInterface $logger     // ✅ Auto-injected
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this->addArgument('site', InputArgument::REQUIRED, 'Site context name');
        $this->addOption('profile', null, InputOption::VALUE_OPTIONAL, 'Installation profile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $site = $input->getArgument('site');
        $this->manager->install($site);
        $this->logger->success('Site installed: ' . $site);
        return Command::SUCCESS;
    }
}
```

**No drush.services.yml needed** - PSR-4 auto-discovery handles command registration.

---

## Migration Task Breakdown

### Phase 1: Command Structure (16 commands to split)

**Estimated effort**: 3-5 days

Split `src/Commands/ProvisionCommands.php` into separate command classes:

- [ ] `ProvisionSaveCommand.php` - Save/update context
- [ ] `ProvisionVerifyCommand.php` - Verify server/platform/site
- [ ] `ProvisionInstallCommand.php` - Install Drupal site
- [ ] `ProvisionImportCommand.php` - Import existing site
- [ ] `ProvisionBackupCommand.php` - Backup site
- [ ] `ProvisionRestoreCommand.php` - Restore from backup
- [ ] `ProvisionDeployCommand.php` - Deploy backup to site
- [ ] `ProvisionMigrateCommand.php` - Migrate to different platform
- [ ] `ProvisionCloneCommand.php` - Clone site
- [ ] `ProvisionEnableCommand.php` - Enable site
- [ ] `ProvisionDisableCommand.php` - Disable site
- [ ] `ProvisionLockCommand.php` - Lock site
- [ ] `ProvisionUnlockCommand.php` - Unlock site
- [ ] `ProvisionDeleteCommand.php` - Delete context
- [ ] `ProvisionLoginResetCommand.php` - Reset admin login
- [ ] `BackendParseCommand.php` - Parse backend output

**Each command file should**:
- Extend `Symfony\Component\Console\Command\Command`
- Use `#[AsCommand]` attribute with name, description, aliases
- Implement `configure()` method for arguments/options
- Implement `execute()` method with proper signature
- Use `AutowireTrait` for dependency injection

### Phase 2: Base Class & Attributes

**Estimated effort**: 1 day (done alongside Phase 1)

For each command class:

- [ ] Replace `extends DrushCommands` with `extends Command`
- [ ] Replace `#[CLI\Command(name: '...')]` with `#[AsCommand(name: '...', aliases: [...])]`
- [ ] Move `#[CLI\Argument]` to `addArgument()` in `configure()`
- [ ] Move `#[CLI\Option]` to `addOption()` in `configure()`
- [ ] Update method signature from `public function commandName()` to `protected function execute()`

### Phase 3: Dependency Injection

**Estimated effort**: 2-3 days

- [ ] Add `use AutowireTrait` to all command classes
- [ ] Implement constructor-based dependency injection:
  ```php
  public function __construct(
      private readonly ProvisionManager $manager,
      private readonly ContextRepository $contexts,
      private readonly Filesystem $filesystem,
      private readonly ProcessRunner $runner,
      private readonly TemplateRenderer $templates,
      private readonly LoggerInterface $logger
  ) {
      parent::__construct();
  }
  ```
- [ ] Remove manual `new ProvisionManager(...)` instantiation from all methods
- [ ] Update ProvisionManager to be injectable as a service
- [ ] Test dependency resolution and autowiring

### Phase 4: Service Registration & Auto-Discovery

**Estimated effort**: 1 day

- [ ] Update `composer.json` PSR-4 mapping if needed for auto-discovery
- [ ] Verify commands are in correct namespace for auto-discovery
- [ ] Remove `drush.services.yml` file entirely
- [ ] Test command discovery: `drush list | grep provision`
- [ ] Verify all commands are discovered and executable

### Phase 5: Execution Method Updates

**Estimated effort**: 2-3 days

For each command:

- [ ] Implement `execute(InputInterface $input, OutputInterface $output): int`
- [ ] Get arguments: `$input->getArgument('name')`
- [ ] Get options: `$input->getOption('name')`
- [ ] Use `$this->logger` instead of `$this->logger()`
- [ ] Return `Command::SUCCESS` (0) or `Command::FAILURE` (1)
- [ ] Handle exceptions and return appropriate exit codes
- [ ] Update output formatting to use OutputInterface
- [ ] Optional: Implement `interact()` for user prompts

### Phase 6: Testing & Validation

**Estimated effort**: 2-3 days

- [ ] Test each command individually
- [ ] Verify command discovery and help output
- [ ] Test argument/option parsing
- [ ] Test dependency injection works correctly
- [ ] Integration tests with actual Apache/MySQL
- [ ] Verify backward compatibility (alias names)
- [ ] Test error handling and exit codes
- [ ] Performance testing (command startup time)

### Phase 7: Documentation Updates

**Estimated effort**: 1 day

- [ ] Update README.md examples with new command patterns
- [ ] Update doc/Home.md with modern Drush 13.7+ examples
- [ ] Update doc/provision-d11.md architecture section
- [ ] Update .github/AI-INSTRUCTIONS.md code examples
- [ ] Update .github/AGENTS.md quick reference
- [ ] Remove all "deprecated pattern" warnings
- [ ] Add migration notes to CHANGELOG

---

## HIGH PRIORITY: Additional Tasks

### Hook System Implementation

**Status**: Not Started  
**Estimated effort**: 5-7 days

Implement extension points for custom service implementations:

- [ ] Define hook/event interfaces
- [ ] Create event dispatcher integration
- [ ] Document hook system for third-party extensions
- [ ] Add examples of custom service plugins
- [ ] Test hook invocation performance

### Automated Testing Suite

**Status**: Not Started  
**Estimated effort**: 7-10 days

- [ ] Set up PHPUnit test infrastructure
- [ ] Unit tests for core classes (Context, ContextRepository, etc.)
- [ ] Integration tests for services (Apache, MySQL, SSL)
- [ ] Command execution tests
- [ ] Mock filesystem/process operations for unit tests
- [ ] Docker-based integration test environment
- [ ] CI/CD pipeline configuration
- [ ] Code coverage reporting

### Documentation Enhancement

**Status**: Ongoing  
**Estimated effort**: 3-5 days

- [ ] Add comprehensive command usage examples
- [ ] Create troubleshooting guide
- [ ] Document common workflows (backup/restore, migration)
- [ ] Add architecture diagrams
- [ ] Create video tutorials or screencasts
- [ ] Document best practices and anti-patterns

---

## OPTIONAL / LOW PRIORITY: Feature Enhancements

These features are nice-to-have but not critical for core functionality.

### Nginx Support

**Status**: Not Started  
**Estimated effort**: 5-7 days  
**Priority**: Low - Apache-only is sufficient for most use cases

- [ ] Create `NginxService` class
- [ ] Implement Nginx vhost templates
- [ ] Handle PHP-FPM socket/port configuration
- [ ] Support Nginx-specific directives
- [ ] Test with Nginx + PHP-FPM setup

### Multi-Server / Remote Operations (SSH/rsync)

**Status**: Partial - Infrastructure exists but not functional  
**Estimated effort**: 7-10 days  
**Priority**: Low - Local-only operations sufficient currently

- [ ] Implement SSH integration in ProcessRunner
- [ ] Add rsync support for file synchronization
- [ ] Handle remote command execution
- [ ] Manage SSH key authentication
- [ ] Support jump hosts / bastion servers
- [ ] Test remote Apache/MySQL operations

### Cluster & Pack Services

**Status**: Not Started  
**Estimated effort**: 10-14 days  
**Priority**: Low - Enterprise feature, not needed for standard deployments

- [ ] Multi-webserver configuration support
- [ ] Load balancer integration
- [ ] Master/slave server coordination
- [ ] Distributed configuration synchronization

### Advanced Backup Options

**Status**: Basic implementation exists  
**Estimated effort**: 3-5 days  
**Priority**: Low - Current backup/restore works well

- [ ] Incremental backups
- [ ] Compression algorithms (beyond gzip)
- [ ] Backup encryption
- [ ] Cloud storage integration (S3, etc.)
- [ ] Backup scheduling and retention policies

### Additional Database Backends

**Status**: Not Started  
**Estimated effort**: 5-7 days per backend  
**Priority**: Low - MySQL/MariaDB covers 99% of use cases

- [ ] PostgreSQL support (`PostgreSqlService`)
- [ ] MongoDB support (for special use cases)
- [ ] Database adapter interface

---

## Migration Timeline Estimate

**Total estimated effort for High Priority items**: 15-25 days

### Recommended Approach

**Sprint 1 (Week 1-2)**: Drush 13.7+ Migration
- Phases 1-5: Command restructuring and dependency injection
- Daily testing and validation

**Sprint 2 (Week 3)**: Testing & Documentation
- Phase 6: Comprehensive testing
- Phase 7: Documentation updates

**Sprint 3 (Week 4+)**: Hook System & Testing Suite
- Implement extension points
- Build automated test infrastructure

---

## Notes

- All deprecated patterns currently **work correctly** but violate modern standards
- Migration can be done incrementally (command by command)
- Backward compatibility maintained through command aliases
- Reference: https://www.drush.org/13.x/commands/ for official patterns
- See [provision-d11.md](provision-d11.md) for current architecture details

---

**Questions or suggestions?** Open an issue at https://github.com/argopecten/aegir-provision/issues
