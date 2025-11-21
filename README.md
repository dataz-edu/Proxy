# Dataz Proxy Automation

This repository contains the WHMCS provisioning module and PHP 7.4 backend required to automate HTTP (Squid) and SOCKS5 (Dante) proxy deployment on Ubuntu 22.04 with MariaDB 10.6.x.

## Installation
Refer to [INSTALL.md](INSTALL.md) for full deployment instructions covering database setup, backend configuration, and WHMCS module installation.

## Running Tests
Ensure PHP 7.4 CLI is available and configuration files exist, then run:

```bash
php tests/run_all_tests.php
```

## Building the WHMCS Module ZIP
To package the WHMCS module into a distributable archive:

```bash
./scripts/build_module.sh
```

The ZIP is created under `dist/` with the current version.

## Releasing to GitHub
Publish a versioned release, tag, and push artifacts using:

```bash
./scripts/release_module.sh            # uses VERSION file
./scripts/release_module.sh 1.0.1      # overrides VERSION
```

The release script builds the module, commits the versioned artifact, tags the repository, pushes to Git, and optionally creates a GitHub release when the `gh` CLI is installed.
