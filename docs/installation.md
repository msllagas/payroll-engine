# Installation Guide

This guide covers the complete first-time setup path for `quillbytes/payroll-engine`.

## Requirements

- PHP `^8.2`
- Laravel / Illuminate Support `^12.0|^13.0`
- Composer

Optional but useful for package development:

- Node.js and npm for release automation
- Pest and Pint are already installed through `composer install`

## Install Via Composer

```bash
composer require quillbytes/payroll-engine
```

## Package Discovery

Laravel package discovery is enabled through `composer.json`, so in a normal Laravel app you do not need to manually register the service provider or facade alias.

The package registers:

- `QuillBytes\PayrollEngine\PayrollEngineServiceProvider`
- `PayrollEngine` facade alias

If package discovery is disabled in the host application, manually register:

```php
// config/app.php

'providers' => [
    QuillBytes\PayrollEngine\PayrollEngineServiceProvider::class,
],

'aliases' => [
    'PayrollEngine' => QuillBytes\PayrollEngine\PayrollEngineFacade::class,
],
```

## Publish Config

Publish the package config when you want to:

- override package defaults
- define client presets
- register custom strategies
- replace the edge-case policy pipeline

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

Published destination:

```text
config/payroll-engine.php
```

## What Gets Published Today

As of `1.2.x`, the package publishes:

- config only

The package does not currently publish:

- migrations
- views
- assets
- translations
- routes
- commands

That means there are no package database tables to migrate and no package seeders to run.

## Environment Variables

The package does not require package-specific environment variables by default.

If your host Laravel app wraps package behavior in its own feature flags or tenant-specific settings, document those in the host application rather than in the package config.

## Clear Config Cache

If your Laravel app caches config, refresh it after publishing or editing `config/payroll-engine.php`:

```bash
php artisan config:clear
php artisan config:cache
```

## Verify Installation

### 1. Verify the container binding

Open Tinker:

```bash
php artisan tinker
```

Resolve the service:

```php
app(\QuillBytes\PayrollEngine\PayrollEngine::class);
app('payroll-engine');
\PayrollEngine::class;
```

Expected result:

- the engine resolves from the container without errors
- the alias `payroll-engine` resolves to the same service
- the facade alias exists in the application

### 2. Verify config is available

In Tinker:

```php
config('payroll-engine.defaults.frequency');
```

Expected result:

```php
"semi_monthly"
```

### 3. Run a smoke computation

Use the sample in [Quick Start Guide](quick-start.md). A successful compute call confirms:

- package discovery works
- config loaded correctly
- container resolution works
- the package can normalize payloads and return a `PayrollResult`

## First-Time Setup Checklist

- composer install completed successfully
- package discovery is enabled or manual registration is in place
- `config/payroll-engine.php` is published when overrides are needed
- config cache has been refreshed if the app uses cached config
- the service resolves from the Laravel container
- a sample payroll computation completes successfully

## Notes For Maintainers

Before a release, re-check this guide whenever any of the following changes:

- supported PHP or Laravel versions
- publish tags
- service provider behavior
- package discovery metadata
- setup prerequisites such as migrations, assets, or env vars

