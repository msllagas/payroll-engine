# Payroll Engine

[![Latest Version on Packagist](https://img.shields.io/packagist/v/quillbytes/payroll-engine.svg?style=flat-square)](https://packagist.org/packages/quillbytes/payroll-engine)
[![Total Downloads](https://img.shields.io/packagist/dt/quillbytes/payroll-engine.svg?style=flat-square)](https://packagist.org/packages/quillbytes/payroll-engine)
![GitHub Actions](https://github.com/jdclzn/payroll-engine/actions/workflows/main.yml/badge.svg)

`quillbytes/payroll-engine` is a Laravel-friendly payroll computation library for applications that need a reusable, auditable, and customizable payroll core.

It ships with a Philippines-oriented default payroll model, but the package is intentionally strategy-based so tenant, client, company, or country-specific rules can be replaced without forking the package.

This package is a good fit when your Laravel application needs to:
- compute payroll from Eloquent models, arrays, or DTO-like objects
- normalize host-application data before payroll math runs
- support regular, off-cycle, retro, and final-settlement payroll scenarios
- generate payslip-ready, register-ready, and allocation-ready payloads
- keep payroll computations traceable for audit, review, and debugging
- customize formulas per client or company through configuration and contracts

## Table of Contents

- [What This Package Does](#what-this-package-does)
- [Requirements](#requirements)
- [Installation In Laravel](#installation-in-laravel)
- [How To Use This In A Laravel Application](#how-to-use-this-in-a-laravel-application)
- [Accepted Input Sources](#accepted-input-sources)
- [Required Data](#required-data)
- [Quick Start](#quick-start)
- [Laravel Use Cases](#laravel-use-cases)
- [Payroll Run Lifecycle](#payroll-run-lifecycle)
- [Outputs](#outputs)
- [Configuration](#configuration)
- [Extending For Client-Specific Rules](#extending-for-client-specific-rules)
- [Auditability](#auditability)
- [Testing](#testing)
- [Release Workflow](#release-workflow)
- [Changelog](#changelog)
- [Contributing](#contributing)
- [Security](#security)
- [Credits](#credits)
- [License](#license)

## What This Package Does

This package focuses on payroll computation and payroll output building.

It does:
- compute payroll for one employee or a whole payroll run
- return structured `PayrollResult` and `PayrollRun` objects
- build report payloads for payslips, registers, and allocation summaries
- support workflow gates such as prepare, approve, process, and release
- support strategy overrides for rate, overtime, tax, variable earnings, Pag-IBIG, or the whole workflow

It does not:
- create database tables for your payroll module
- import attendance logs for you
- generate PDF, Excel, or bank files by itself
- provide UI screens, approval pages, or Eloquent models
- replace your HRIS, attendance, loans, or leave modules

In a Laravel app, the normal pattern is:

1. Read company, employee, attendance, allowance, loan, and adjustment data from your own models.
2. Map them into payroll-engine-friendly payloads.
3. Call the engine.
4. Store the result, serialize it, render it in Blade, convert it to PDF, or export it to Excel.

## Requirements

- PHP `^8.2`
- Laravel / Illuminate Support `^12.0|^13.0`

## Installation In Laravel

Install the package:

```bash
composer require quillbytes/payroll-engine
```

The package uses Laravel package discovery, so the service provider and facade alias are registered automatically.

If you want to override defaults or register client-specific strategies, publish the config file:

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

This publishes the package config to:

```text
config/payroll-engine.php
```

If your application uses cached config, refresh the cache after making config changes:

```bash
php artisan config:clear
php artisan config:cache
```

## How To Use This In A Laravel Application

The package is designed to sit inside your existing Laravel payroll module.

A typical Laravel integration looks like this:

- `Company` or `PayrollPolicy` model stores company-level rules and payroll schedules.
- `Employee` model stores employee profile, compensation, statutory, and banking data.
- Attendance, overtime, bonuses, adjustments, and deductions come from your own modules.
- An action, service class, job, or controller builds the payroll input and calls the engine.
- The result is stored in your own database tables or transformed into report payloads.

Example application-layer service:

```php
<?php

namespace App\Actions\Payroll;

use QuillBytes\PayrollEngine\PayrollEngine;

final class ComputeEmployeePayroll
{
    public function __construct(
        private PayrollEngine $engine,
    ) {}

    public function handle(mixed $company, mixed $employee, array $input)
    {
        return $this->engine->compute($company, $employee, $input);
    }
}
```

That keeps the package responsible only for payroll computation while your Laravel app remains responsible for persistence, approvals, exports, and UI.

## Accepted Input Sources

You can pass:

- arrays
- Eloquent models
- DTO-like objects
- already-normalized package data objects

The engine normalizes common snake_case and camelCase attribute names. It also supports nested employee structures such as:

- `employment.*`
- `compensation.*`
- `statutory.*`
- `payroll.*`
- `allocation.*`

This means you can often pass Eloquent models directly if their attribute names already match the expected aliases. If your database columns differ, map them first in a transformer or action class before calling the engine.

## Required Data

### Company Data

At minimum, the company payload should include:

- `name`
- `prepared_by`
- `approvers`
- `administrators`
- `payroll_schedules`

Each payroll schedule must define:

- `pay_date`
- `period_start`
- `period_end`

Useful optional company fields:

- `client_code`
- `frequency`
- `hours_per_day`
- `work_days_per_year`
- `eemr_factor`
- `release_lead_days`
- `manual_overtime_pay`
- `fixed_per_day_rate`
- `separate_allowance_payout`
- `external_leave_management`
- `split_monthly_statutory_across_periods`
- `pagibig_mode`
- `pagibig_schedule`
- `tax_strategy`
- overtime premium overrides

### Employee Data

At minimum, the employee payload should include:

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

Useful optional employee fields:

- `date_regularized`
- `date_resigned`
- `position`
- `daily_rate`
- `hourly_rate`
- `projected_annual_taxable_income`
- `representation`
- `allowances`
- `sss_number`
- `hdmf_number`
- `phic_number`
- `minimum_wage_earner`
- manual statutory contribution overrides
- `statutory_schedule` for employee-specific monthly or split timing across SSS, PhilHealth, and Pag-IBIG
- `sss_schedule` for employee-specific monthly or split SSS deductions
- `philhealth_schedule` for employee-specific monthly or split PhilHealth deductions
- upgraded or voluntary Pag-IBIG contribution
- `pagibig_schedule` for employee-specific monthly or split Pag-IBIG deductions
- `project_code`
- `project_name`
- `cost_center`
- `vessel`
- `allocation_dimensions`

### Payroll Input Data

For a normal compute call, the minimum practical input is:

- `period.start_date`
- `period.end_date`

Useful optional payroll input fields:

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
- `leave_deduction`
- `absence_deduction`
- `late_deduction`
- `undertime_deduction`
- `bonus_amount`
- `used_annual_bonus_shield`
- `pagibig_loan_amortization`
- `pagibig_due_this_run`
- `statutory_due_this_run`
- `sss_due_this_run`
- `philhealth_due_this_run`
- `projected_annual_taxable_income`

### Important Input Notes

- Monetary values are passed in major units such as `1500`, `1500.50`, or `"1500.50"`.
- Internally, the package converts amounts to `moneyphp/money` objects for safe payroll math.
- `release_date` defaults from the payroll period end date minus the company's `release_lead_days`.
- Both snake_case and camelCase equivalents are accepted for many fields.

## Quick Start

Resolve the service from Laravel's container:

```php
use QuillBytes\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);
```

You can also resolve it by alias:

```php
$engine = app('payroll-engine');
```

Or use the facade alias registered by Laravel package discovery:

```php
$result = \PayrollEngine::compute($company, $employee, $input);
```

## Laravel Use Cases

### 1. Preview Payroll For One Employee

Use `compute()` when your Laravel app needs to preview or finalize payroll for one employee.

Common places to use this:

- payroll preview screens
- approval review pages
- recalculation after overtime approval
- manual adjustment screens
- API endpoints that return a payslip preview

```php
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

$engine = app(PayrollEngine::class);

$company = [
    'name' => 'Acme Payroll Services',
    'client_code' => 'base',
    'prepared_by' => ['payroll.preparer'],
    'approvers' => ['finance.manager'],
    'administrators' => ['system.admin'],
    'payroll_schedules' => [
        [
            'pay_date' => '15',
            'period_start' => '1',
            'period_end' => '15',
        ],
        [
            'pay_date' => '30',
            'period_start' => '16',
            'period_end' => '30',
        ],
    ],
];

$employee = [
    'employee_number' => 'EMP-001',
    'full_name' => 'Ana Santos',
    'employment_status' => 'active',
    'date_hired' => '2024-01-10',
    'department' => 'Finance',
    'email' => 'ana.santos@example.com',
    'monthly_basic_salary' => 30000,
    'tax_shield_amount_for_bonuses' => 90000,
    'tin' => '123-456-789',
    'sss_number' => '11-1111111-1',
    'hdmf_number' => '123456789012',
    'phic_number' => '12-345678901-2',
    'account_number' => '001234567890',
    'bank' => 'Payroll Bank',
    'branch' => 'Makati Main',
];

$input = [
    'period' => [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
        'release_date' => '2026-04-15',
        'run_type' => 'regular',
    ],
    'overtime' => [
        [
            'type' => 'regular',
            'hours' => 5,
        ],
    ],
    'adjustments' => [
        [
            'label' => 'Taxable Adjustment',
            'amount' => 500,
            'taxable' => true,
        ],
    ],
    'manual_deductions' => [
        [
            'label' => 'Uniform Deduction',
            'amount' => 250,
        ],
    ],
];

$result = $engine->compute($company, $employee, $input);

$netPay = MoneyHelper::toFloat($result->netPay);
$takeHomePay = MoneyHelper::toFloat($result->takeHomePay);
$payslip = $engine->payslip($result);
```

### 2. Use Eloquent Models Directly

If your Eloquent attributes already match the expected aliases, you can pass your models directly:

```php
use App\Models\Company;
use App\Models\Employee;
use QuillBytes\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);

$company = Company::findOrFail($companyId);
$employee = Employee::findOrFail($employeeId);

$result = $engine->compute($company, $employee, [
    'period' => [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
    ],
]);
```

If your column names do not match, transform them before calling the engine:

```php
$employeePayload = [
    'employee_number' => $employee->code,
    'full_name' => $employee->name,
    'employment_status' => $employee->status,
    'date_hired' => $employee->date_hired,
    'department' => $employee->department_name,
    'email' => $employee->email,
    'monthly_basic_salary' => $employee->basic_salary,
    'tin' => $employee->tin,
    'account_number' => $employee->bank_account_number,
    'bank' => $employee->bank_name,
    'branch' => $employee->bank_branch,
];
```

### 3. Compute An Entire Payroll Cutoff

Use `run()` when your Laravel app needs to process a payroll period for many employees.

```php
use QuillBytes\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);

$period = [
    'key' => '2026-04-A',
    'start_date' => '2026-04-01',
    'end_date' => '2026-04-15',
    'release_date' => '2026-04-15',
    'run_type' => 'regular',
];

$items = [
    [
        'employee' => $employeeA,
        'input' => [
            'overtime' => [
                ['type' => 'regular', 'hours' => 3],
            ],
            'manual_deductions' => [
                ['label' => 'Cash Advance', 'amount' => 500],
            ],
        ],
    ],
    [
        'employee' => $employeeB,
        'input' => [
            'adjustments' => [
                ['label' => 'Allowance Adjustment', 'amount' => 750, 'taxable' => false],
            ],
        ],
    ],
];

$run = $engine->run($company, $period, $items);
```

The `run()` method:

- normalizes the company and period once
- computes each employee result
- skips employees who are not active during the supplied period
- returns a `PayrollRun` object with aggregated helpers

### 4. Drive A Payroll Approval Workflow

`PayrollRun` includes a built-in lifecycle you can use inside your Laravel module:

- `draft`
- `prepared`
- `approved`
- `processed`
- `released`

Example:

```php
use Carbon\CarbonImmutable;

$run->prepare('payroll.preparer', CarbonImmutable::parse('2026-04-13 09:00:00'));
$run->approve('finance.manager', CarbonImmutable::parse('2026-04-13 11:00:00'));
$run->process('system.admin', CarbonImmutable::parse('2026-04-14 08:00:00'));
$run->release('system.admin', CarbonImmutable::parse('2026-04-15 09:00:00'));
```

Important workflow rules:

- only allowed preparers can prepare
- only allowed approvers can approve
- only processed runs can be released
- payroll files can only be generated after processing
- payslips can only be generated on or after the configured release date

### 5. Generate Payslips In Your Laravel App

Use `payslip()` for one employee result or `generatePayslips()` for a processed payroll run.

```php
$payslip = $engine->payslip($result);

$run->prepare('payroll.preparer');
$run->approve('finance.manager');
$run->process('system.admin');

$payslips = $engine->generatePayslips(
    $run,
    \Carbon\CarbonImmutable::parse('2026-04-15 09:00:00')
);
```

The payslip payload is already shaped for:

- Blade views
- PDF generation
- API responses
- employee self-service portals

### 6. Generate A Payroll Register Or Bank Export Source

Use `payrollRegister()` for computed results or `generatePayrollFiles()` for a processed payroll run.

```php
$register = $engine->payrollRegister($run->results);

$run->prepare('payroll.preparer');
$run->approve('finance.manager');
$run->process('system.admin');

$rows = $engine->generatePayrollFiles($run);
```

The register payload includes values such as:

- employee number
- employee name
- project and cost center
- bank and account number
- gross pay
- taxable income
- net pay
- take-home pay
- release date
- run type
- issue codes

### 7. Build Allocation Summaries

If your Laravel app needs payroll totals by project, branch, vessel, department, cost center, or a custom dimension, use `allocationSummary()`.

```php
$summaryByProject = $engine->allocationSummary($run->results, 'project_code');
$summaryByBranch = $engine->allocationSummary($run->results, 'branch');
$summaryByDepartment = $engine->allocationSummary($run->results, 'department');
$summaryByCluster = $engine->allocationSummary($run->results, 'cluster');
```

Custom dimensions come from employee `allocation_dimensions`, for example:

```php
'allocation_dimensions' => [
    'cluster' => 'north',
    'client_group' => 'enterprise',
],
```

This is useful for:

- project payroll billing
- branch-level payroll reports
- vessel payroll breakdowns
- cost-center summaries
- client or tenant profitability analysis

### 8. Handle Off-Cycle Payroll In The Same Engine

The package supports the following run types:

- `regular`
- `special`
- `adjustment`
- `correction`
- `emergency`
- `bonus_release`
- `retro_pay`
- `final_pay`
- `resignation`
- `termination`
- `retirement`

Off-cycle payroll is especially useful for:

- missed payroll adjustments
- correction releases
- emergency payroll payouts
- 13th-month or bonus releases
- difference-only retro releases

Example adjustment payroll:

```php
$result = $engine->compute($company, $employee, [
    'period' => [
        'key' => '2026-ADJUSTMENT',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-01',
        'release_date' => '2026-09-05',
        'run_type' => 'adjustment',
    ],
    'adjustments' => [
        [
            'label' => 'Payroll Adjustment',
            'amount' => 2500,
            'taxable' => true,
        ],
    ],
]);
```

Behavior summary:

- regular runs use scheduled basic pay, regular allowances, regular statutory contributions, and regular withholding rules
- off-cycle runs do not use scheduled basic pay unless the run type is a final-settlement type
- off-cycle runs are ideal for pure adjustment or correction releases

### 9. Process Final Pay And Separation Payroll

Use final-settlement run types when the employee has separated and your Laravel app needs last-pay computation:

- `final_pay`
- `resignation`
- `termination`
- `retirement`

Typical Laravel use cases:

- last cutoff for resigned employees
- final deductions such as cash advances or accountability holds
- leave conversion
- retirement settlement releases
- separation-specific approval and release workflows

Example resignation final pay:

```php
$result = $engine->compute($company, [
    'employee_number' => 'EMP-FS-RESIGN',
    'full_name' => 'Resigned Employee',
    'employment_status' => 'resigned',
    'date_hired' => '2024-01-10',
    'date_resigned' => '2026-09-10',
    'department' => 'Finance',
    'email' => 'final.pay@example.com',
    'monthly_basic_salary' => 30000,
    'tax_shield_amount_for_bonuses' => 90000,
    'tin' => '123-456-789',
    'sss_number' => '11-1111111-1',
    'hdmf_number' => '123456789012',
    'phic_number' => '12-345678901-2',
    'account_number' => '001234567890',
    'bank' => 'Payroll Bank',
    'branch' => 'Makati Main',
], [
    'period' => [
        'key' => '2026-RESIGN',
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-15',
        'release_date' => '2026-09-20',
        'run_type' => 'resignation',
    ],
    'adjustments' => [
        ['label' => 'Leave Conversion', 'amount' => 2000, 'taxable' => true],
        ['label' => 'Final Settlement Adjustment', 'amount' => 500, 'taxable' => true],
    ],
    'loan_deductions' => [
        ['label' => 'Last Salary Loan', 'amount' => 600],
    ],
    'manual_deductions' => [
        ['label' => 'Clearance Hold', 'amount' => 1200],
    ],
]);
```

Final-settlement behavior is different from normal payroll:

- scheduled basic pay is still used
- regular withholding still applies
- regular mandatory contributions are not applied

### 10. Release Retroactive Differences Only

Use `retroAdjustmentInput()` when a historical payroll result needs to be recomputed and you only want to release the difference.

This is useful for:

- salary increase back pay
- late overtime approvals
- holiday pay corrections
- retroactive allowance changes

Example retro workflow:

```php
$historicalPeriod = [
    'key' => '2026-02-A',
    'start_date' => '2026-02-01',
    'end_date' => '2026-02-15',
    'release_date' => '2026-02-15',
];

$original = $engine->compute($company, [
    ...$employee,
    'monthly_basic_salary' => 30000,
], [
    'period' => $historicalPeriod,
]);

$recomputed = $engine->compute($company, [
    ...$employee,
    'monthly_basic_salary' => 36000,
], [
    'period' => $historicalPeriod,
]);

$retroInput = $engine->retroAdjustmentInput(
    $original,
    $recomputed,
    [
        'key' => '2026-05-RETRO-SALARY',
        'start_date' => '2026-05-05',
        'end_date' => '2026-05-05',
        'release_date' => '2026-05-05',
        'run_type' => 'adjustment',
    ],
);

$release = $engine->compute($company, [
    ...$employee,
    'monthly_basic_salary' => 36000,
], $retroInput);
```

The generated retro input is difference-only. That means the release run contains only the payroll delta, not the whole historical payroll again.

### 11. Support Multi-Client Or Multi-Tenant Rules

If your Laravel application serves multiple payroll clients, you can swap strategies by `client_code`.

This is useful when:

- one tenant uses manual overtime pay while another computes OT from hours
- one client uses projected annualized withholding while another uses current-period annualized tax
- one company needs a custom payroll workflow or special benefit logic

Example:

```php
// config/payroll-engine.php

'strategies' => [
    'clients' => [
        'tenant-a' => [
            'overtime' => \App\Payroll\Strategies\TenantAOvertimeCalculator::class,
            'withholding' => \App\Payroll\Strategies\TenantAWithholdingTaxCalculator::class,
        ],
        'tenant-b' => [
            'workflow' => \App\Payroll\Strategies\TenantBPayrollWorkflow::class,
        ],
    ],
],
```

## Payroll Run Lifecycle

`PayrollRun` includes lifecycle helpers and audit entries.

Available methods:

- `prepare()`
- `approve()`
- `process()`
- `reopen()`
- `release()`
- `assertEditable()`
- `assertCanGeneratePayrollFiles()`
- `assertCanGeneratePayslips()`
- `totalNetPay()`
- `totalTakeHomePay()`

In a Laravel app, this makes it easy to connect the package to:

- approval workflows
- queued processing jobs
- release scheduling
- payroll audit logs
- admin reopen flows before release day

## Outputs

### `compute()` Returns `PayrollResult`

`PayrollResult` contains:

- normalized company, employee, and period objects
- rate snapshot
- earnings lines
- deduction lines
- employee contributions
- employer contributions
- separate payouts
- gross pay
- taxable income
- net pay
- take-home pay
- bonus tax withheld
- payroll issues
- audit metadata

Important note:

- the monetary totals in `PayrollResult` are `Money` objects
- use `QuillBytes\PayrollEngine\Support\MoneyHelper::toFloat()` for output formatting

### `run()` Returns `PayrollRun`

`PayrollRun` contains:

- company
- period
- array of `PayrollResult` items
- lifecycle status
- audit trail
- prepared, approved, processed, and released timestamps

### `payslip()` Returns An Array

The payslip payload contains:

- company info
- period info
- employee info
- allocation info
- rates
- earnings
- employee contributions
- deductions
- separate payouts
- issues
- audit metadata
- totals

### `payrollRegister()` Returns An Array Of Rows

Each register row contains:

- employee identifiers
- allocation fields
- bank payout fields
- gross, taxable, net, and take-home totals
- release date
- run type
- issue codes

## Configuration

The package config lives at [`config/config.php`](config/config.php) in the package and is published to `config/payroll-engine.php` in your Laravel app.

The most important sections are:

### `defaults`

Global payroll policy defaults such as:

- frequency
- hours per day
- work days per year
- EEMR factor
- release lead days
- overtime handling
- fixed per-day rate behavior
- separate allowance payout behavior
- statutory split rules
- Pag-IBIG mode and schedule
- tax strategy
- annual bonus tax shield
- overtime premium multipliers

### `presets`

Client-level policy overrides keyed by `client_code`.

Example built-in preset keys:

- `enterprise-365`
- `regional-hq-365`

### `strategies`

Replaceable strategy classes for:

- `workflow`
- `rate`
- `overtime`
- `variable_earnings`
- `withholding`
- `pagibig`

### `edge_case_policies`

Replaceable policy objects or class strings for edge-case handling such as:

- rule conflicts
- attendance data handling
- deduction overlap
- insufficient net-pay handling

## Extending For Client-Specific Rules

The engine resolves strategy overrides through Laravel's container, so class strings in your config can depend on your own services.

Available contracts:

- `QuillBytes\PayrollEngine\Contracts\PayrollWorkflow`
- `QuillBytes\PayrollEngine\Contracts\RateCalculator`
- `QuillBytes\PayrollEngine\Contracts\OvertimeCalculator`
- `QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator`
- `QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator`
- `QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator`

Example custom workflow:

```php
<?php

namespace App\Payroll\Strategies;

use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollResult;

final class TenantBPayrollWorkflow implements PayrollWorkflow
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input
    ): PayrollResult {
        throw new \LogicException('Implement custom workflow result building here.');
    }
}
```

Recommended Laravel pattern:

- keep package inputs and outputs at the application boundary
- put mapping logic in actions or transformers
- keep custom policy math inside dedicated strategy classes
- select strategies by `client_code`

## Auditability

Every computed result is enriched with audit metadata so the payroll output remains explainable.

This includes:

- applied strategies
- policy names
- rates and basis amounts
- payroll line metadata
- issues and warnings

This is useful for:

- payroll review
- finance sign-off
- dispute resolution
- support investigation
- post-release debugging

## Testing

The package includes scenario coverage for:

- regular payroll computation
- Laravel package integration
- variable earnings
- off-cycle run types
- retroactive changes
- final settlement flows
- allocation summaries
- tenant and company-specific extensions
- payroll edge-case policies

Run the test suite with:

```bash
composer test
```

## Release Workflow

This package uses [standard-version](https://github.com/conventional-changelog/standard-version) for semantic versioning based on conventional commits.

Available commands:

- First tagged release:
  `composer run release:first`
- Normal release:
  `composer run release`
- Preflight checks only:
  `composer run release:check`

`composer run release` runs package checks, updates `CHANGELOG.md`, bumps package metadata, creates the release commit, and creates the git tag.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on recent changes.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for contribution guidelines.

## Security

If you discover any security related issues, please email `jovanie.daclizon@gmail.com` instead of using the issue tracker.

## Credits

- [Jovanie Daclizon](https://github.com/jdclzn)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [LICENSE.md](LICENSE.md) for more information.
