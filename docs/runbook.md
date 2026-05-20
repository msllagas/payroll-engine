# Runbook

This runbook is the operational manual for installing, verifying, upgrading, and supporting `quillbytes/payroll-engine` in live Laravel systems.

## Purpose

The package provides the payroll computation core used by a Laravel application.

It is responsible for:

- payroll normalization and validation
- payroll computation
- audit-friendly results
- payslip, register, and allocation payload generation

It is not responsible for:

- host application database schema
- attendance imports
- payroll UI
- PDF or spreadsheet rendering

## Ownership

Default public support path:

- package repository issues for bugs, feature requests, and non-security support
- maintainer contact for security issues through [SECURITY.md](../SECURITY.md)

Before internal rollout, replace or supplement this section with your team owner, on-call rotation, or internal support channel.

## Preconditions

Before rollout or support verification, confirm:

- PHP version is compatible with the package requirement
- Laravel / Illuminate Support version is compatible
- package is installed through Composer
- `config/payroll-engine.php` is published if overrides are required
- config cache is refreshed if the host app uses cached config
- the package service resolves from the Laravel container

Current package assumptions:

- no package migrations
- no package views
- no package assets
- no package env vars required by default
- no queue or cache dependency required by the package itself

## Deployment / Release Steps

### Install Or Update The Package Version

```bash
composer require quillbytes/payroll-engine:^1.2
```

Or update an existing lockfile:

```bash
composer update quillbytes/payroll-engine
```

### Publish Config If Needed

If the package config is not yet published:

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

If the config was already published, compare your current app config with the package's latest `config/config.php` before copying changes.

### Refresh Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

### Run Smoke Tests

Minimum smoke test:

1. Resolve the engine from the container.
2. Verify `config('payroll-engine.defaults.frequency')`.
3. Run the sample in [Quick Start Guide](quick-start.md).

Recommended package maintainer preflight:

```bash
composer test
composer format:check
composer run release:check
```

## Verification Checklist

After install, deployment, or upgrade, verify:

- the package resolves via `app(PayrollEngine::class)`
- the alias `payroll-engine` resolves correctly
- the facade alias `PayrollEngine` is available
- published config is readable
- a sample `compute()` call succeeds
- expected totals look correct for the smoke payload
- logs contain no binding or config errors
- payslip and register generation still work in the host app

## Suggested Smoke Payload

Use the sample and expected output from [Quick Start Guide](quick-start.md).

This is a good operational baseline because it exercises:

- rates
- overtime
- adjustments
- deductions
- contributions
- taxes
- result totals

## Common Issues And Fixes

### Package service will not resolve

Likely causes:

- package discovery disabled
- manual service provider registration missing
- Composer autoload not refreshed

Fix:

- verify `composer install` completed
- run `composer dump-autoload`
- manually register the provider if discovery is disabled

### Config changes do not take effect

Likely causes:

- config cache not cleared

Fix:

```bash
php artisan config:clear
php artisan config:cache
```

### No active payroll entries were generated

Likely cause:

- all employees in a `run()` call were filtered out as inactive during the payroll period

Fix:

- verify employment status and separation dates
- verify the period dates being passed to `run()`

### Net pay or take-home pay looks wrong

Likely causes:

- unexpected client preset
- projected annual tax behavior
- separate allowance payout enabled
- edge-case policy deferred deductions or partial payout

Fix:

- inspect the result audit metadata
- verify `client_code`
- verify `tax_strategy`
- verify runtime `edge_case_policy`

### Payslip or payroll file generation is blocked

Likely cause:

- payroll run lifecycle is not yet in a valid state

Fix:

- inspect `PayrollRun` status and release date rules
- move the run through prepare/approve/process/release in the host workflow

## Rollback Procedure

If a release introduces a regression:

1. revert the Composer version constraint or lockfile change
2. run `composer install`
3. restore any changed config values if the release required config edits
4. clear and rebuild Laravel config cache
5. rerun the smoke test
6. verify sample payroll output matches the previous known-good baseline

Package note:

- there are no package migrations to roll back in `1.2.x`

## Support / Escalation

For a support ticket or issue report, capture:

- package version
- PHP version
- Laravel version
- `client_code`
- relevant config overrides
- sanitized company payload
- sanitized employee payload
- sanitized payroll input
- expected result
- actual result
- any thrown exception message
- relevant logs

For security issues, use the private reporting path in [SECURITY.md](../SECURITY.md) instead of a public issue.

