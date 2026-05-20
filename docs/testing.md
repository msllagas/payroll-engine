# Testing Guide

Payroll packages need test clarity because small rule changes can create major output differences.

This guide describes how to run, extend, and maintain the package test suite.

## Test Stack

The package uses:

- [Pest](https://pestphp.com/) for tests
- Laravel / Illuminate test dependencies where needed for integration scenarios

## Install Dependencies

```bash
composer install
```

## Run The Full Test Suite

```bash
composer test
```

Equivalent direct command:

```bash
vendor/bin/pest --configuration phpunit.xml.dist --ci
```

## Run Formatting Checks

```bash
composer format:check
```

Auto-format:

```bash
composer format
```

## Release Preflight

Before publishing:

```bash
composer run release:check
```

This validates:

- Composer metadata
- optimized autoload generation
- test suite execution unless skipped

## Current Scenario Coverage

The current suite covers:

- regular payroll computation
- Laravel package integration
- rate behavior and fixed-rate rules
- overtime handling
- variable earnings
- withholding and bonus tax behavior
- off-cycle run types
- final settlement flows
- retroactive payroll adjustments
- allocation summaries
- tenant- and client-specific strategy overrides
- edge-case policy handling

## Writing New Tests

When adding a new payroll rule:

1. add or update a test before changing the implementation
2. keep the scenario focused on one business rule
3. assert the most important totals and line labels
4. assert trace or audit metadata when the feature depends on explainability

## Test Design Recommendations

For this package, good tests usually assert:

- gross pay
- taxable income
- net pay
- take-home pay
- rate snapshot values when rates are part of the rule
- contribution labels and amounts when statutory logic is involved
- issue codes and metadata when edge-case policies apply

## Where To Add Tests

Suggested placement:

- calculator or workflow behavior in the most relevant scenario file
- Laravel wiring in `tests/PayrollEngineLaravelIntegrationTest.php`
- edge-case logic in `tests/PayrollEdgeExceptionPolicyTest.php`
- client-specific strategy behavior in `tests/PayrollStrategyTest.php` or tenant-specific scenario files

## Regression Test Checklist

Before merging a behavioral change:

- add a regression test for the rule you changed
- run the full suite
- re-check any sample outputs in docs that depend on the changed logic
- update docs if the public behavior changed

## Debugging Failed Payroll Tests

When a payroll test fails:

- inspect the changed totals first
- inspect the rate snapshot next
- inspect payroll line labels and metadata
- inspect issues and audit payloads
- confirm whether a config default or preset changed unexpectedly

## Maintainer Note

If you change:

- config defaults
- public payload expectations
- tax formulas
- contribution formulas
- lifecycle rules

then update the relevant docs in addition to the tests.

