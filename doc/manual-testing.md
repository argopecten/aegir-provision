# Manual Testing Guide

This guide provides step-by-step instructions for manually testing core Aegir Provision operations for platforms and sites.

## Prerequisites

- Ubuntu 24.04 LTS or compatible Linux distribution
- PHP 8.3+ installed with required extensions (mysql, xml, gd, curl, mbstring)
- MySQL 8.0+ or MariaDB installed and running
- Apache 2.4+ installed with mod_rewrite, mod_ssl enabled
- Drush 13.7+ installed globally
- Aegir Provision installed via Composer
- Root or sudo access for Apache/MySQL operations

## Test Environment Setup

### 1. Create Test Server Context

```bash
drush provision-save server_master \
  --context_type=server \
  --web_service_type=apache \
  --db_service_type=mysql \
  --http_port=80 \
  --https_port=443 \
  --web_group=www-data \
  --db_host=localhost \
  --db_port=3306 \
  --db_root_password=YOURROOTPASSWORD
```

**Expected Result:**
- Server context file created at `~/.drush/provision/server_master.yml`
- No error messages
- Success confirmation message displayed

**Validation:**
```bash
cat ~/.drush/provision/server_master.yml
drush provision-status server_master
```

### 2. Verify Test Database Connection

```bash
mysql -uroot -pYOURROOTPASSWORD -e "SELECT VERSION();"
```

**Expected Result:**
- MySQL/MariaDB version displayed
- No connection errors

---

## Platform Operations Testing

### Test 1: Platform Install

**Objective:** Verify that a Drupal platform can be installed successfully.

#### Step 1.1: Download Drupal

```bash
cd /var/aegir/platforms
composer create-project drupal/recommended-project:^11.0 test-platform-01 --no-interaction
cd test-platform-01
composer require drush/drush
```

**Expected Result:**
- Drupal 11 codebase downloaded
- `web/` directory contains Drupal files
- `composer.json` present

#### Step 1.2: Create Platform Context

```bash
drush provision-save platform_test01 \
  --context_type=platform \
  --root=/var/aegir/platforms/test-platform-01/web \
  --server=server_master \
  --drupal_version=11
```

**Expected Result:**
- Platform context file created at `~/.drush/provision/platform_test01.yml`
- Success message displayed

**Validation:**
```bash
cat ~/.drush/provision/platform_test01.yml
drush provision-status platform_test01
```

#### Step 1.3: Run Platform Install

```bash
drush @platform_test01 provision-install
```

**Expected Result:**
- Command completes without errors
- Install events dispatched (check output for event names)
- No PHP errors or warnings

**Validation:**
```bash
# Verify platform directory exists and is readable
ls -la /var/aegir/platforms/test-platform-01/web
stat /var/aegir/platforms/test-platform-01/web

# Check ownership
ls -ld /var/aegir/platforms/test-platform-01/web
```

### Test 2: Platform Verify

**Objective:** Verify that platform verification detects platform status and issues.

#### Step 2.1: Run Platform Verify

```bash
drush @platform_test01 provision-verify
```

**Expected Result:**
- Verification completes successfully
- Platform root path validated
- Drupal version detected correctly (11.x)
- Services (Apache) verified
- Success message displayed

**Validation:**
```bash
# Check verify event was dispatched
drush @platform_test01 provision-verify -vvv 2>&1 | grep -i "event"

# Verify platform context updated
cat ~/.drush/provision/platform_test01.yml | grep verify_date
```

#### Step 2.2: Test Verify with Invalid Path

```bash
# Temporarily rename the platform directory
sudo mv /var/aegir/platforms/test-platform-01/web /var/aegir/platforms/test-platform-01/web.backup

# Run verify (should fail)
drush @platform_test01 provision-verify

# Restore directory
sudo mv /var/aegir/platforms/test-platform-01/web.backup /var/aegir/platforms/test-platform-01/web
```

**Expected Result:**
- Verification fails with clear error message
- Error indicates platform root not found
- No PHP fatal errors

### Test 3: Platform Delete

**Objective:** Verify that platform deletion removes context and cleans up properly.

#### Step 3.1: Create Disposable Platform

```bash
# Create a minimal platform for deletion test
mkdir -p /var/aegir/platforms/test-delete-platform/web
echo "<?php" > /var/aegir/platforms/test-delete-platform/web/index.php

drush provision-save platform_delete_test \
  --context_type=platform \
  --root=/var/aegir/platforms/test-delete-platform/web \
  --server=server_master \
  --drupal_version=11
```

#### Step 3.2: Run Platform Delete

```bash
drush @platform_delete_test provision-delete
```

**Expected Result:**
- Delete event dispatched
- Context removed from `~/.drush/provision/`
- Command completes successfully
- Confirmation message displayed

**Validation:**
```bash
# Verify context file removed
ls ~/.drush/provision/platform_delete_test.yml
# Should show: No such file or directory

# Verify platform directory still exists (Provision doesn't delete files)
ls -la /var/aegir/platforms/test-delete-platform/web
# Should still exist (manual cleanup required)

# Manual cleanup
sudo rm -rf /var/aegir/platforms/test-delete-platform
```

---

## Site Operations Testing

### Test 4: Site Install

**Objective:** Verify that a Drupal site can be installed on a platform.

#### Step 4.1: Create Database User for Site

```bash
# Create test database credentials
SITE_DB_USER="site_test01_user"
SITE_DB_PASS="SecurePass123!"
SITE_DB_NAME="site_test01_db"

mysql -uroot -pYOURROOTPASSWORD <<EOF
CREATE DATABASE IF NOT EXISTS ${SITE_DB_NAME};
CREATE USER IF NOT EXISTS '${SITE_DB_USER}'@'localhost' IDENTIFIED BY '${SITE_DB_PASS}';
GRANT ALL PRIVILEGES ON ${SITE_DB_NAME}.* TO '${SITE_DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF
```

**Expected Result:**
- Database created successfully
- User created with appropriate permissions
- No MySQL errors

**Validation:**
```bash
mysql -u${SITE_DB_USER} -p${SITE_DB_PASS} -e "SHOW DATABASES;" | grep ${SITE_DB_NAME}
```

#### Step 4.2: Create Site Context

```bash
drush provision-save site_test01 \
  --context_type=site \
  --platform=platform_test01 \
  --uri=test01.local.example.com \
  --db_name=${SITE_DB_NAME} \
  --db_user=${SITE_DB_USER} \
  --db_passwd=${SITE_DB_PASS} \
  --db_host=localhost \
  --db_port=3306
```

**Expected Result:**
- Site context created at `~/.drush/provision/site_test01.yml`
- All database credentials stored
- Success message displayed

**Validation:**
```bash
cat ~/.drush/provision/site_test01.yml
drush provision-status site_test01
```

#### Step 4.3: Add Hosts Entry

```bash
sudo bash -c 'echo "127.0.0.1 test01.local.example.com" >> /etc/hosts'
```

#### Step 4.4: Run Site Install

```bash
drush @site_test01 provision-install \
  --site_name="Test Site 01" \
  --site_mail=admin@example.com \
  --account_name=admin \
  --account_mail=admin@example.com \
  --account_pass=admin123
```

**Expected Result:**
- Drupal installation proceeds
- Database tables created
- Apache virtual host configuration generated
- Settings.php file created
- Files directory created with proper permissions
- Site installed successfully
- Success message with login URL

**Validation:**
```bash
# Check Apache vhost configuration
ls -la /etc/apache2/sites-available/ | grep test01
cat /etc/apache2/sites-enabled/*test01*.conf

# Check settings.php created
cat /var/aegir/platforms/test-platform-01/web/sites/test01.local.example.com/settings.php | head -20

# Check files directory
ls -ld /var/aegir/platforms/test-platform-01/web/sites/test01.local.example.com/files

# Check database tables
mysql -u${SITE_DB_USER} -p${SITE_DB_PASS} ${SITE_DB_NAME} -e "SHOW TABLES;" | wc -l
# Should show 70+ tables for Drupal 11

# Reload Apache
sudo systemctl reload apache2

# Test site access
curl -I http://test01.local.example.com
# Should return HTTP 200 OK or 302 (redirect)
```

### Test 5: Site Verify

**Objective:** Verify that site verification validates site configuration and status.

#### Step 5.1: Run Site Verify

```bash
drush @site_test01 provision-verify
```

**Expected Result:**
- Verification completes successfully
- Database connection validated
- Site root directory validated
- Apache vhost configuration verified
- Settings.php verified
- Files directory permissions checked
- Drupal bootstrap successful
- Success message displayed

**Validation:**
```bash
# Check verify updated context
cat ~/.drush/provision/site_test01.yml | grep verify_date

# Verify vhost symlink exists
ls -la /etc/apache2/sites-enabled/ | grep test01

# Test verbose output
drush @site_test01 provision-verify -vvv 2>&1 | grep -E "(event|service|verify)"
```

#### Step 5.2: Test Verify with Configuration Issues

```bash
# Break database credentials
drush provision-save site_test01 --db_passwd=WRONGPASSWORD

# Run verify (should detect issue)
drush @site_test01 provision-verify

# Restore correct credentials
drush provision-save site_test01 --db_passwd=${SITE_DB_PASS}

# Verify again (should succeed)
drush @site_test01 provision-verify
```

**Expected Result:**
- First verify fails with database connection error
- Error message is clear and actionable
- Second verify succeeds after credentials restored

#### Step 5.3: Test Apache Configuration Regeneration

```bash
# Remove Apache vhost file
sudo rm /etc/apache2/sites-available/*test01*.conf
sudo rm /etc/apache2/sites-enabled/*test01*.conf

# Run verify (should regenerate)
drush @site_test01 provision-verify

# Reload Apache
sudo systemctl reload apache2

# Test site still accessible
curl -I http://test01.local.example.com
```

**Expected Result:**
- Vhost configuration regenerated automatically
- Apache config valid (no syntax errors)
- Site accessible after verification

### Test 6: Site Delete

**Objective:** Verify that site deletion removes all site components properly.

#### Step 6.1: Create Disposable Site

```bash
# Create database
DISPOSABLE_DB="site_delete_test_db"
DISPOSABLE_USER="site_delete_user"
DISPOSABLE_PASS="TempPass123!"

mysql -uroot -pYOURROOTPASSWORD <<EOF
CREATE DATABASE IF NOT EXISTS ${DISPOSABLE_DB};
CREATE USER IF NOT EXISTS '${DISPOSABLE_USER}'@'localhost' IDENTIFIED BY '${DISPOSABLE_PASS}';
GRANT ALL PRIVILEGES ON ${DISPOSABLE_DB}.* TO '${DISPOSABLE_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

# Create site context
drush provision-save site_delete_test \
  --context_type=site \
  --platform=platform_test01 \
  --uri=delete-test.local.example.com \
  --db_name=${DISPOSABLE_DB} \
  --db_user=${DISPOSABLE_USER} \
  --db_passwd=${DISPOSABLE_PASS} \
  --db_host=localhost \
  --db_port=3306

# Install site
drush @site_delete_test provision-install \
  --site_name="Delete Test Site" \
  --account_name=admin \
  --account_pass=admin123
```

**Expected Result:**
- Database and site created successfully
- Apache vhost generated

#### Step 6.2: Run Site Delete

```bash
drush @site_delete_test provision-delete
```

**Expected Result:**
- Delete event dispatched
- Site context removed from `~/.drush/provision/`
- Apache vhost disabled and removed
- Database dropped (verify this behavior)
- Success message displayed

**Validation:**
```bash
# Verify context removed
ls ~/.drush/provision/site_delete_test.yml
# Should show: No such file or directory

# Verify Apache vhost removed
ls -la /etc/apache2/sites-available/ | grep delete-test
ls -la /etc/apache2/sites-enabled/ | grep delete-test
# Should show no results

# Verify database status
mysql -uroot -pYOURROOTPASSWORD -e "SHOW DATABASES;" | grep ${DISPOSABLE_DB}
# May still exist depending on delete implementation

# If database still exists, clean up manually
mysql -uroot -pYOURROOTPASSWORD <<EOF
DROP DATABASE IF EXISTS ${DISPOSABLE_DB};
DROP USER IF EXISTS '${DISPOSABLE_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

# Verify site directory cleanup
ls -la /var/aegir/platforms/test-platform-01/web/sites/delete-test.local.example.com
# May still exist - manual cleanup may be required

# Reload Apache
sudo systemctl reload apache2
```

---

## Integration Tests

### Test 7: Full Site Lifecycle

**Objective:** Test complete workflow from platform creation to site deletion.

```bash
# 1. Create platform
drush provision-save platform_lifecycle \
  --context_type=platform \
  --root=/var/aegir/platforms/test-lifecycle/web \
  --server=server_master

# 2. Install platform
drush @platform_lifecycle provision-install

# 3. Verify platform
drush @platform_lifecycle provision-verify

# 4. Create site
drush provision-save site_lifecycle \
  --context_type=site \
  --platform=platform_lifecycle \
  --uri=lifecycle.local.example.com \
  --db_name=site_lifecycle_db \
  --db_user=site_lifecycle_user \
  --db_passwd=LifecyclePass123

# 5. Install site
drush @site_lifecycle provision-install --account_name=admin --account_pass=admin

# 6. Verify site
drush @site_lifecycle provision-verify

# 7. Delete site
drush @site_lifecycle provision-delete

# 8. Delete platform
drush @platform_lifecycle provision-delete
```

**Expected Result:**
- All operations complete successfully
- No orphaned resources
- Clean removal of all contexts

### Test 8: Multiple Sites on Same Platform

**Objective:** Verify multiple sites can coexist on one platform.

```bash
# Create site 1
drush provision-save site_multi_01 \
  --context_type=site \
  --platform=platform_test01 \
  --uri=multi01.local.example.com \
  --db_name=multi01_db \
  --db_user=multi01_user \
  --db_passwd=Multi01Pass

drush @site_multi_01 provision-install --account_name=admin --account_pass=admin

# Create site 2
drush provision-save site_multi_02 \
  --context_type=site \
  --platform=platform_test01 \
  --uri=multi02.local.example.com \
  --db_name=multi02_db \
  --db_user=multi02_user \
  --db_passwd=Multi02Pass

drush @site_multi_02 provision-install --account_name=admin --account_pass=admin

# Verify both sites
drush @site_multi_01 provision-verify
drush @site_multi_02 provision-verify
```

**Expected Result:**
- Both sites install successfully
- Separate databases created
- Separate Apache vhosts created
- Both sites verify successfully
- No conflicts between sites

**Validation:**
```bash
# Check separate vhosts
ls -la /etc/apache2/sites-enabled/ | grep multi

# Check separate settings files
ls -la /var/aegir/platforms/test-platform-01/web/sites/multi*.local.example.com/

# Test both sites accessible
curl -I http://multi01.local.example.com
curl -I http://multi02.local.example.com
```

---

## Error Scenario Testing

### Test 9: Invalid Database Credentials

```bash
# Create site with wrong DB credentials
drush provision-save site_bad_db \
  --context_type=site \
  --platform=platform_test01 \
  --uri=baddb.local.example.com \
  --db_name=nonexistent_db \
  --db_user=fake_user \
  --db_passwd=wrong_password

# Attempt install (should fail gracefully)
drush @site_bad_db provision-install
```

**Expected Result:**
- Installation fails with clear database error message
- No partial installation artifacts left behind
- Error message indicates database connection problem

### Test 10: Missing Platform Root

```bash
# Create platform with non-existent path
drush provision-save platform_missing \
  --context_type=platform \
  --root=/nonexistent/path/to/drupal \
  --server=server_master

# Attempt install (should fail)
drush @platform_missing provision-install

# Attempt verify (should fail)
drush @platform_missing provision-verify
```

**Expected Result:**
- Operations fail with clear path error
- Error message indicates directory not found
- No PHP fatal errors

### Test 11: Insufficient Permissions

```bash
# Create directory without write permissions
sudo mkdir -p /var/aegir/platforms/no-write-platform/web
sudo chmod 555 /var/aegir/platforms/no-write-platform/web
sudo chown root:root /var/aegir/platforms/no-write-platform/web

# Create platform
drush provision-save platform_no_write \
  --context_type=platform \
  --root=/var/aegir/platforms/no-write-platform/web \
  --server=server_master

# Attempt operations
drush @platform_no_write provision-verify
```

**Expected Result:**
- Operations fail with permission error
- Clear error message about write permissions
- No fatal errors

**Cleanup:**
```bash
sudo chmod 755 /var/aegir/platforms/no-write-platform/web
sudo rm -rf /var/aegir/platforms/no-write-platform
```

---

## Cleanup After Testing

```bash
# Remove test sites
drush @site_test01 provision-delete
drush @site_multi_01 provision-delete
drush @site_multi_02 provision-delete

# Remove test platforms
drush @platform_test01 provision-delete

# Remove test databases
mysql -uroot -pYOURROOTPASSWORD <<EOF
DROP DATABASE IF EXISTS site_test01_db;
DROP DATABASE IF EXISTS multi01_db;
DROP DATABASE IF EXISTS multi02_db;
DROP USER IF EXISTS 'site_test01_user'@'localhost';
DROP USER IF EXISTS 'multi01_user'@'localhost';
DROP USER IF EXISTS 'multi02_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Remove platform directories
sudo rm -rf /var/aegir/platforms/test-platform-01
sudo rm -rf /var/aegir/platforms/test-lifecycle
sudo rm -rf /var/aegir/platforms/test-delete-platform

# Remove hosts entries
sudo sed -i '/test01.local.example.com/d' /etc/hosts
sudo sed -i '/delete-test.local.example.com/d' /etc/hosts
sudo sed -i '/lifecycle.local.example.com/d' /etc/hosts
sudo sed -i '/multi01.local.example.com/d' /etc/hosts
sudo sed -i '/multi02.local.example.com/d' /etc/hosts

# Reload Apache
sudo systemctl reload apache2
```

---

## Test Results Template

Use this template to document test results:

```
Test Date: YYYY-MM-DD
Tester: [Name]
Environment: [Ubuntu version, PHP version, MySQL version]

| Test # | Test Name                    | Status | Notes |
|--------|------------------------------|--------|-------|
| 1      | Platform Install             | ✓/✗    |       |
| 2      | Platform Verify              | ✓/✗    |       |
| 3      | Platform Delete              | ✓/✗    |       |
| 4      | Site Install                 | ✓/✗    |       |
| 5      | Site Verify                  | ✓/✗    |       |
| 6      | Site Delete                  | ✓/✗    |       |
| 7      | Full Site Lifecycle          | ✓/✗    |       |
| 8      | Multiple Sites Same Platform | ✓/✗    |       |
| 9      | Invalid Database Credentials | ✓/✗    |       |
| 10     | Missing Platform Root        | ✓/✗    |       |
| 11     | Insufficient Permissions     | ✓/✗    |       |

Issues Found:
1. [Description]
2. [Description]

Overall Assessment: [PASS/FAIL]
```

---

## Common Issues and Troubleshooting

### Apache Not Reloading

**Symptom:** Changes don't take effect after verify.

**Solution:**
```bash
sudo apache2ctl configtest
sudo systemctl reload apache2
# Or restart if reload doesn't work
sudo systemctl restart apache2
```

### Database Connection Errors

**Symptom:** "Access denied" or "Unknown database" errors.

**Solution:**
```bash
# Verify database exists
mysql -uroot -pROOTPASS -e "SHOW DATABASES;"

# Verify user permissions
mysql -uroot -pROOTPASS -e "SHOW GRANTS FOR 'username'@'localhost';"

# Recreate if needed
mysql -uroot -pROOTPASS <<EOF
CREATE DATABASE IF NOT EXISTS dbname;
GRANT ALL ON dbname.* TO 'username'@'localhost' IDENTIFIED BY 'password';
FLUSH PRIVILEGES;
EOF
```

### Permission Errors

**Symptom:** "Permission denied" when writing files.

**Solution:**
```bash
# Fix ownership
sudo chown -R $USER:www-data /var/aegir/platforms/platform-name

# Fix permissions
sudo chmod -R 755 /var/aegir/platforms/platform-name
sudo chmod -R 775 /var/aegir/platforms/platform-name/web/sites/*/files
```

### Context Not Found

**Symptom:** "Context not found" errors.

**Solution:**
```bash
# List all contexts
drush provision-list

# Check context file exists
ls -la ~/.drush/provision/

# Recreate context with provision-save
```

### Drupal Not Bootstrapping

**Symptom:** Verify fails with bootstrap error.

**Solution:**
```bash
# Check settings.php exists and is readable
cat /var/aegir/platforms/platform-name/web/sites/uri/settings.php

# Check database connection in settings.php
grep -A 10 "databases" /var/aegir/platforms/platform-name/web/sites/uri/settings.php

# Test Drush directly
cd /var/aegir/platforms/platform-name/web
drush --uri=uri status
```

---

## Notes

- All tests should be performed on a dedicated test environment, not production
- Replace `YOURROOTPASSWORD` with your actual MySQL root password
- Adjust paths if your Aegir installation uses different directories
- Some tests intentionally create failures to verify error handling
- Document all unexpected behaviors or errors encountered
- Apache reload/restart may be required after configuration changes
- Always verify Apache configuration syntax before reloading: `apache2ctl configtest`
