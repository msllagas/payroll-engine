# Troubleshooting Guide

This guide is organized by symptom.

## Installation Issues

### Symptom: `Class "QuillBytes\PayrollEngine\PayrollEngine" not found`

Likely cause:

- Composer autoload is stale

How to diagnose:

- verify the package exists in `vendor/`
- run `composer show quillbytes/payroll-engine`

Fix:

```bash
composer dump-autoload
```

### Symptom: Laravel cannot resolve the engine from the container

Likely causes:

- package discovery disabled
- service provider not manually registered

How to diagnose:

- check `composer.json` package discovery in the package
- check the host app's `config/app.php` if discovery is disabled

Fix:

- manually register `QuillBytes\PayrollEngine\PayrollEngineServiceProvider`

## Config Issues

### Symptom: config changes do not affect payroll results

Likely cause:

- config cache still contains old values

Fix:

```bash
php artisan config:clear
php artisan config:cache
```

### Symptom: fixed daily rate is being ignored

Likely cause:

- employee has `daily_rate`, but company `fixed_per_day_rate` is `false`

How to diagnose:

- inspect `company.fixed_per_day_rate`
- inspect `$result->rates->fixedPerDayApplied`

Fix:

- set `fixed_per_day_rate` to `true` for the company or client preset

## Computation Mismatch

### Symptom: withholding tax is higher or lower than expected

Likely causes:

- `tax_strategy` is `projected_annualized`
- projected annual taxable income was supplied on the input or employee
- run type does not use regular withholding

How to diagnose:

- inspect the `Withholding Tax` trace metadata in the result
- inspect `tax_strategy`
- inspect `projected_annual_taxable_income`

Fix:

- verify whether `current_period_annualized` or `projected_annualized` is intended
- verify projected annual taxable income values coming from the host app

### Symptom: bonus tax does not match expectation

Likely causes:

- annual bonus shield already partly used
- employee-level bonus shield override exists
- the entire bonus remains shielded

How to diagnose:

- inspect `used_annual_bonus_shield`
- inspect `employee.tax_shield_amount_for_bonuses`
- inspect `company.annual_bonus_tax_shield`

Fix:

- verify the remaining shield and projected annual taxable income baseline

### Symptom: net pay is unexpectedly capped or deductions are missing

Likely causes:

- runtime `edge_case_policy` enabled `defer_deductions`
- runtime `edge_case_policy` enabled `partial_payout_limit`

How to diagnose:

- inspect `$result->issues`
- inspect runtime `edge_case_policy`

Fix:

- verify whether `negative_net_pay`, `minimum_take_home_pay`, or `partial_payout_limit` was intentionally set

## Runtime Policy Issues

### Symptom: `Attendance data is required by policy but was not provided.`

Likely cause:

- `attendance_required` is true and `no_attendance_data` is `error`

How to diagnose:

- inspect company, employee, and input `edge_case_policy`
- inspect attendance-related metadata in the input

Fix:

- provide attendance data
- or relax the runtime rule to `warn` or `allow`

### Symptom: `Edge case policies conflict: negative net pay cannot be allowed...`

Likely cause:

- `negative_net_pay = allow` was combined with `minimum_take_home_pay` or `partial_payout_limit`

Fix:

- use `error` or `defer_deductions` for `negative_net_pay`
- or remove the threshold rule

## Batch Payroll Issues

### Symptom: `No active payroll entries were generated for the supplied period.`

Likely cause:

- all employees were inactive during the payroll period

How to diagnose:

- check employee hire and resignation dates
- check `PayrollPeriod`

Fix:

- verify host-app employee filtering
- verify the cutoff dates passed to `run()`

## Reporting Issues

### Symptom: Payslip generation is blocked

Likely cause:

- payroll run is not yet in a valid lifecycle state or release date window

Fix:

- move the run through the expected lifecycle
- check `assertCanGeneratePayslips()` requirements

### Symptom: Payroll register rows do not match expected release state

Likely cause:

- register generation was attempted before the run was ready

Fix:

- process the run first
- use `generatePayrollFiles()` only after the run passes its lifecycle checks

## Prevention Checklist

- keep config and host payload mappings explicit
- add regression tests before changing payroll rules
- inspect audit metadata when diagnosing a mismatch
- verify `client_code` before chasing formula bugs
- clear config cache after changing published config

