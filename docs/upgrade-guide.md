# Upgrade Guide

This guide explains how to safely upgrade the package in a host Laravel application.

## Current Release Line

Current documented release line:

- `1.2.x`

At the time of writing, the package does not publish migrations and uses config-only publishing.

## Upgrade Philosophy

Treat package upgrades as code and rule changes, not just dependency bumps.

For every upgrade:

- review the changelog
- compare config defaults
- rerun a known-good payroll scenario
- verify outputs, not just install success

## Before You Upgrade

Capture:

- current package version
- host Laravel version
- PHP version
- current `config/payroll-engine.php`
- one or more known-good payroll scenarios with expected outputs

Recommended baseline:

- the sample in [Quick Start Guide](quick-start.md)
- at least one tenant-specific scenario if your app uses presets or strategies

## Standard Upgrade Procedure

### 1. Read the release notes

Review:

- [Changelog](../CHANGELOG.md)
- any package docs updated in the release

### 2. Update the package

```bash
composer update quillbytes/payroll-engine
```

Or pin a target version:

```bash
composer require quillbytes/payroll-engine:^1.2
```

### 3. Review config differences

Compare:

- your app's `config/payroll-engine.php`
- the package's latest `config/config.php`

Pay special attention to:

- new config keys
- renamed or removed config keys
- default behavior changes
- new strategy or policy sections

### 4. Refresh config cache

```bash
php artisan config:clear
php artisan config:cache
```

### 5. Run package and app tests

Package maintainer workflow:

```bash
composer test
composer format:check
composer run release:check
```

Host app workflow:

- run your payroll integration tests
- run your smoke payroll scenario
- verify tenant-specific overrides still activate correctly

## Post-Upgrade Verification Checklist

- package resolves from the Laravel container
- published config still loads
- sample `compute()` output matches expectations
- client presets still apply correctly
- custom strategies still resolve through the container
- no lifecycle regressions in `PayrollRun`
- payslip and register generation still work

## Handling Breaking Changes

When future releases introduce breaking changes, document them here under a versioned section such as:

- upgrading from `1.x` to `2.x`
- renamed config keys
- removed contracts or classes
- changed payroll formulas
- changed audit metadata structure

For now, if you maintain a private downstream app, create an internal upgrade checklist for each release even when the public package has not yet introduced a formal major-version migration path.

## Rollback Procedure

If the upgrade causes a regression:

1. revert the version bump in `composer.json` or lockfile
2. run `composer install`
3. restore any changed config values if needed
4. clear config cache
5. rerun your payroll smoke tests

## Recommended Release Discipline

Before publishing a new package version:

- update [Changelog](../CHANGELOG.md)
- update docs when public behavior changes
- call out config changes clearly
- include regression tests for computation changes

