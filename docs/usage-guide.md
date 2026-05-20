# Usage Guide

This guide explains how to use the package in real application flows.

## Core Usage Pattern

The package is designed to sit inside your Laravel payroll module.

The normal usage loop is:

1. Read company, employee, attendance, and adjustment data from your app.
2. Map that data into package-friendly payloads.
3. Call `compute()` or `run()`.
4. Persist the result or transform it into reports and exports.

## Accepted Input Types

You can pass:

- arrays
- Eloquent models
- DTO-like objects
- already-normalized package data objects

If your host models already expose compatible attribute names, you can often pass them directly.

## Building The Company Payload

At minimum, include:

- `name`
- `prepared_by`
- `approvers`
- `administrators`
- `payroll_schedules`

Useful company-level overrides include:

- `client_code`
- `frequency`
- `eemr_factor`
- `hours_per_day`
- `release_lead_days`
- `manual_overtime_pay`
- `fixed_per_day_rate`
- `tax_strategy`
- `edge_case_policy`

## Building The Employee Payload

At minimum, include:

- `employee_number`
- `full_name`
- `employment_status`
- `date_hired`
- `department`
- `email`
- `monthly_basic_salary`
- `tin`
- `account_number`
- `bank`
- `branch`

Useful additions include:

- `daily_rate`
- `hourly_rate`
- `representation`
- `allowances`
- `projected_annual_taxable_income`
- manual statutory contribution overrides
- allocation fields such as `project_code`, `cost_center`, and `vessel`
- `edge_case_policy`

## Building The Payroll Input

A practical minimum input is:

- `period.start_date`
- `period.end_date`

Common optional inputs:

- `period.key`
- `period.release_date`
- `period.run_type`
- `overtime`
- `manual_overtime_pay`
- `variable_earnings`
- `sales_commissions`
- `production_incentives`
- `quota_bonuses`
- `adjustments`
- `manual_deductions`
- `loan_deductions`
- `absence_deduction`
- `late_deduction`
- `undertime_deduction`
- `bonus_amount`
- `used_annual_bonus_shield`
- `projected_annual_taxable_income`
- `edge_case_policy`

## Compute One Employee

Use `compute()` for:

- payroll preview
- single-employee recalculation
- adjustment review
- employee-specific final pay

Example:

```php
$result = $engine->compute($company, $employee, $input);
```

Useful result areas:

- `$result->rates`
- `$result->earnings`
- `$result->deductions`
- `$result->employeeContributions`
- `$result->employerContributions`
- `$result->grossPay`
- `$result->taxableIncome`
- `$result->netPay`
- `$result->takeHomePay`
- `$result->issues`
- `$result->audit`

## Compute A Payroll Cutoff

Use `run()` for many employees in one payroll period.

Example:

```php
$run = $engine->run($company, $period, $items);
```

Each item may be:

- just an employee payload
- or an array with `employee` and `input`

Use the second form when each employee needs different overtime, adjustments, or deductions within the same payroll period.

## Earnings

The default workflow can include:

- scheduled basic pay
- representation allowance
- other allowances
- overtime earnings
- variable earnings
- taxable and non-taxable adjustments
- bonus amounts

Variable earnings can be passed through:

- `variable_earnings`
- `sales_commissions`
- `production_incentives`
- `quota_bonuses`

## Deductions

The package supports:

- manual deductions
- loan deductions
- absence deduction
- leave deduction
- late deduction
- undertime deduction
- withholding tax
- bonus tax withheld

The package also supports runtime edge-case handling for:

- overlapping deductions
- insufficient net pay
- partial payout

## Statutory Contributions

In the default workflow, the engine computes:

- SSS
- PhilHealth
- Pag-IBIG

Behavior is affected by:

- company config
- employee-level manual overrides
- payroll period split rules

## Overtime, Tardiness, And Attendance Effects

The default workflow supports:

- overtime entries by type and hours
- manual overtime pay override
- tardiness deductions
- undertime deductions
- absence deductions

If attendance must be present for a client or employee, use runtime `edge_case_policy` values such as:

```php
'edge_case_policy' => [
    'attendance_required' => true,
    'no_attendance_data' => 'error',
],
```

## Monthly, Daily, Hourly, And Custom Compensation Schemes

### Monthly employee

This is the standard package flow:

- `monthly_basic_salary` drives scheduled basic pay
- daily rate is derived from salary and EEMR factor
- hourly rate is derived from daily rate and hours per day

### Daily-paid employee

If a client uses fixed daily rates:

- enable `fixed_per_day_rate` at the company level
- provide `daily_rate` on the employee payload

The rate calculator will use the fixed daily rate instead of the computed divisor formula.

### Hourly employee

If you need a fixed hourly rate:

- provide `hourly_rate` on the employee payload

The default rate calculator uses this fixed hourly rate instead of deriving it from the daily rate.

### Custom compensation scheme

If neither the default monthly/daily/hourly flow nor the fixed-rate toggles are enough, replace the `rate` strategy for the client code.

## Adjustments, Loans, And Manual Corrections

Use:

- `adjustments` for earning-side corrections
- `manual_deductions` for deduction-side corrections
- `loan_deductions` for recoveries tied to loan reference or label

Common cases:

- retro correction
- manual payroll adjustment
- recovery of advances
- one-time deduction

## Reports And Output Builders

### Payslip payload

```php
$payslip = $engine->payslip($result);
```

Use for:

- Blade
- PDF
- JSON
- stored output snapshots

### Payroll register

```php
$rows = $engine->payrollRegister($run->results);
```

Use for:

- CSV export
- finance upload preparation
- bank payout source files

### Allocation summary

```php
$summary = $engine->allocationSummary($run->results, 'cost_center');
```

Use for:

- project costing
- department labor reporting
- branch or vessel allocations

## Audit Trail And Explainability

Every computed result includes audit-oriented metadata such as:

- applied strategies
- policy class names
- rate details
- payroll line trace metadata
- issues and warnings

This is useful for:

- payroll review
- support investigation
- dispute handling
- export validation

## Persistence Integration

The package does not create database tables.

Common host-application persistence patterns:

- store a JSON snapshot of the entire `PayrollResult`
- store payroll run header data plus child employee results
- store summary totals in columns and detail in JSON
- store generated payslip or register payloads as frozen release artifacts

## Recommended Reading

- [Quick Start Guide](quick-start.md)
- [Use Cases Guide](use-cases.md)
- [Policies Guide](policies.md)
- [Laravel Implementation Guide](laravel-implementation.md)
- [Extending the Package](extending.md)

