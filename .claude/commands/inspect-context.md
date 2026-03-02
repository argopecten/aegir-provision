# Inspect / Debug Context

Inspect, validate, or repair a Drush context alias in aegir-provision.

## Quick Inspection

```bash
# Show context as Drush sees it (merges all data)
drush site:alias @example.com
drush site:alias @server_master --format=yaml

# List all known aliases
drush site:alias
drush site:alias --filter=example

# Raw YAML on disk (what AliasStore reads)
cat drush/sites/aegir/example.com.site.yml
cat drush/sites/aegir/server_master.site.yml

# All alias files
ls drush/sites/aegir/
```

## Context YAML Format Reference

### Server context (`drush/sites/aegir/server_{name}.site.yml`)
```yaml
server_master:
  provision:
    context_type: server
    aegir_root: /home/aegir
    remote_host: hostname.example.com
    script_user: aegir
    http_service_type: apache
    http_port: 80
    db_service_type: mysql
    db_port: 3306
    master_db_user: aegir_user
    master_db_passwd: secret
  host: hostname.example.com
  user: aegir
```

### Platform context (`drush/sites/aegir/platform_{name}.site.yml`)
```yaml
platform_d11:
  provision:
    context_type: platform
    aegir_root: /home/aegir
    server: "@server_master"
    web_server: "@server_master"
    root: /var/aegir/platforms/drupal11
  root: /var/aegir/platforms/drupal11
  host: hostname.example.com
  user: aegir
```

### Site context (`drush/sites/aegir/example.com.site.yml`)
```yaml
example.com:
  provision:
    context_type: site
    aegir_root: /home/aegir
    platform: "@platform_d11"
    db_server: "@server_master"
    uri: example.com
    profile: standard
    language: en
  uri: http://example.com
  root: /var/aegir/platforms/drupal11
  host: hostname.example.com
  user: aegir
```

## Context Hierarchy Resolution

```
Site (@example.com)
  └── provision.platform → @platform_d11
        └── provision.server → @server_master (HTTP)
  └── provision.db_server → @server_master (DB)
```

References use `@` prefix in YAML values. File names and canonical names do NOT use `@`.

## Common Problems and Fixes

| Symptom | Cause | Fix |
|---------|-------|-----|
| `Context not found: @example.com` | No YAML file | `drush provision:save @example.com --type=site ...` |
| `Service not registered` | Missing `*_service_type` key | Add `http_service_type: apache` to server YAML |
| `Platform root not found` | Wrong `root` path | Verify path exists: `ls /var/aegir/platforms/drupal11` |
| `DB connection refused` | Wrong host/port/creds | Check `master_db_user`, `master_db_passwd`, `db_port` |
| `@` in context name when stored | ContextRepository strips `@` on load | Always pass `@name` to commands, `name` internally |
| YAML parse error | Bad indentation or special chars | Use `python3 -c "import yaml; yaml.safe_load(open('file.yml'))"` |

## Recreate a Context via provision:save

```bash
# Server
drush provision:save @server_master --type=server \
  --data='{"http_service_type":"apache","db_service_type":"mysql","http_port":80}'

# Platform
drush provision:save @platform_d11 --type=platform \
  --data='{"root":"/var/aegir/platforms/drupal11","server":"@server_master","web_server":"@server_master"}'

# Site
drush provision:save @example.com --type=site \
  --data='{"platform":"@platform_d11","db_server":"@server_master","uri":"example.com","profile":"standard"}'
```

## Validate Context Manually (PHP)

```bash
drush php:eval "
  \$pm = \\Aegir\\Provision\\Drush\\Commands\\ProvisionAutowireTrait::createManager();
  \$ctx = \$pm->contextRepository()->load('@example.com');
  print_r(\$ctx->all());
"
```

## Debug Commands

```bash
# Full debug trace for verify
drush provision:verify @example.com --debug -vvv 2>&1 | tee /tmp/provision-debug.log

# Check Apache config generated
ls -la /var/aegir/config/apache/vhost.d/
cat /var/aegir/config/apache/vhost.d/example.com.conf
sudo apache2ctl -t

# Check MySQL
drush sql:query "SHOW DATABASES LIKE 'example%';"
```

Inspect the context described by the user. Read the YAML file, compare to the expected format, and identify missing or incorrect fields. Suggest fixes using `provision:save` or direct YAML edits.
