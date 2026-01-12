# Aegir Provision D11

This directory contains the Drush 13 extension that implements the Provision backend for Drupal 10+ platforms (PHP 8.3+). It is designed to be installed as a Composer package in a Drupal project and invoked by the Aegir frontend (aegir-hosting) via Drush commands.

Key points:
- Drush 13 command surface: `provision-*` commands used by the hosting frontend.
- Contexts are stored as Drush YAML aliases under `~/.drush/sites`.
- Supported services: Apache HTTP + SSL (self-signed, LetsEncrypt, Cloudflare) and MySQL 8.0+.
- Platforms are Composer-based Drupal 10+ codebases with `/web` (or other docroot) layouts.

Architecture and integration details:
- `../architecture/provision-d11.md`

Install (example):
```
composer require aegir/provision-d11
```

The `drush.services.yml` file registers the command classes automatically when this package is installed.
