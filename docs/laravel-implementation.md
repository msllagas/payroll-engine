# Laravel Implementation Guide

This guide shows how to integrate `quillbytes/payroll-engine` into a real Laravel application without letting package concerns leak into the rest of your codebase.

## Installation

Install the package:

```bash
composer require quillbytes/payroll-engine
```

Publish the config when you need policy overrides or custom strategies:

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

If your application caches config, refresh it after changes:

```bash
php artisan config:clear
php artisan config:cache
```

## What The Package Registers In Laravel

Through package discovery, Laravel registers:

- `QuillBytes\PayrollEngine\PayrollEngineServiceProvider`
- the `PayrollEngine` facade alias

The service provider also:

- recursively merges package config into `config('payroll-engine')`
- binds `QuillBytes\PayrollEngine\PayrollEngine` as a singleton
- aliases the singleton as `payroll-engine`
- publishes `config/config.php` to `config/payroll-engine.php`

That means you can resolve the engine in three normal ways:

```php
use QuillBytes\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);
$sameEngine = app('payroll-engine');
$result = \PayrollEngine::compute($company, $employee, $input);
```

## Recommended Application Structure

A clean Laravel integration usually keeps the package at the application boundary.

Example structure:

```text
app/
  Actions/
    Payroll/
      ComputeEmployeePayroll.php
      RunPayrollCutoff.php
  Http/
    Controllers/
      PayrollPreviewController.php
  Payroll/
    Mappers/
      CompanyPayloadBuilder.php
      EmployeePayloadBuilder.php
      PayrollInputBuilder.php
    Strategies/
      TenantAOvertimeCalculator.php
      TenantBPayrollWorkflow.php
    Policies/
      MissingCostCenterPolicy.php
  Models/
    Company.php
    Employee.php
    PayrollRun.php
    PayrollResult.php
```

The goal is simple:

- Eloquent models stay responsible for persistence
- mappers transform app data into engine-friendly payloads
- actions or services call the engine
- controllers, jobs, and exports consume the resulting payloads

## End-To-End Flow

The normal Laravel flow looks like this:

1. Load company, employee, attendance, and adjustment data from your own models.
2. Map those models into package-friendly arrays or DTOs.
3. Call `compute()` or `run()`.
4. Persist the result or transform it into your own tables.
5. Render payslips, build exports, or trigger finance workflows from the result payload.

This separation keeps the package focused on payroll math while Laravel stays responsible for application orchestration.

## Mapping Data Into The Engine

The engine accepts:

- arrays
- Eloquent models
- DTO-like objects
- package data objects

If your model attributes already match the expected aliases, you can often pass models directly.

If your schema uses different field names, add mapper classes instead of forcing package-oriented naming into your database.

Example mapper:

```php
<?php

namespace App\Payroll\Mappers;

use App\Models\Company;

final class CompanyPayloadBuilder
{
    public function fromModel(Company $company): array
    {
        return [
            'name' => $company->name,
            'client_code' => $company->policy_code,
            'prepared_by' => $company->preparedByUsers()->pluck('email')->all(),
            'approvers' => $company->approvers()->pluck('email')->all(),
            'administrators' => $company->administrators()->pluck('email')->all(),
            'payroll_schedules' => $company->payrollSchedules->map(fn ($schedule) => [
                'pay_date' => $schedule->pay_date,
                'period_start' => $schedule->period_start,
                'period_end' => $schedule->period_end,
            ])->all(),
            'edge_case_policy' => [
                'no_attendance_data' => $company->attendance_policy,
            ],
        ];
    }
}
```

## Compute One Employee In An Action

```php
<?php

namespace App\Actions\Payroll;

use App\Models\Company;
use App\Models\Employee;
use App\Payroll\Mappers\CompanyPayloadBuilder;
use App\Payroll\Mappers\EmployeePayloadBuilder;
use App\Payroll\Mappers\PayrollInputBuilder;
use QuillBytes\PayrollEngine\PayrollEngine;

final class ComputeEmployeePayroll
{
    public function __construct(
        private PayrollEngine $engine,
        private CompanyPayloadBuilder $companyPayloads,
        private EmployeePayloadBuilder $employeePayloads,
        private PayrollInputBuilder $inputPayloads,
    ) {}

    public function handle(Company $company, Employee $employee, array $context = [])
    {
        return $this->engine->compute(
            $this->companyPayloads->fromModel($company),
            $this->employeePayloads->fromModel($employee),
            $this->inputPayloads->fromContext($company, $employee, $context),
        );
    }
}
```

This is a good default pattern because it keeps controllers and jobs thin while still using Laravel dependency injection.

## Example Controller

```php
<?php

namespace App\Http\Controllers;

use App\Actions\Payroll\ComputeEmployeePayroll;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class PayrollPreviewController
{
    public function __invoke(
        Company $company,
        Employee $employee,
        ComputeEmployeePayroll $action,
    ): JsonResponse {
        $result = $action->handle($company, $employee, [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
                'run_type' => 'regular',
            ],
        ]);

        return response()->json([
            'employee_number' => $result->employee->employeeNumber,
            'gross_pay' => MoneyHelper::toFloat($result->grossPay),
            'net_pay' => MoneyHelper::toFloat($result->netPay),
            'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
            'issues' => array_map(fn ($issue) => [
                'code' => $issue->code,
                'message' => $issue->message,
                'severity' => $issue->severity,
            ], $result->issues),
        ]);
    }
}
```

`Money` values should usually be converted before leaving the application boundary. `MoneyHelper::toFloat()` is the simplest way to prepare totals for JSON or Blade views.

## Process A Full Cutoff In A Job Or Service

Use `run()` for batch payroll.

Example action:

```php
<?php

namespace App\Actions\Payroll;

use App\Models\Company;
use Illuminate\Support\Collection;
use QuillBytes\PayrollEngine\PayrollEngine;

final class RunPayrollCutoff
{
    public function __construct(
        private PayrollEngine $engine,
    ) {}

    public function handle(Company $company, array $period, Collection $employees)
    {
        $items = $employees->map(fn ($employee) => [
            'employee' => $employee,
            'input' => [
                'overtime' => [],
                'manual_deductions' => [],
            ],
        ]);

        return $this->engine->run($company, $period, $items);
    }
}
```

This is a good place for:

- queued jobs
- payroll batch services
- admin commands
- scheduled payroll preparation

When using `run()`, pass the shared cutoff period as the second argument and keep each item's `input` array focused on employee-specific additions such as overtime, adjustments, deductions, or bonuses.

## Persisting Results

The package does not create database tables, so decide in Laravel how much payroll history you want to keep.

Common persistence patterns:

- store a JSON snapshot of the full result
- store summary totals in columns and keep detail lines in JSON
- store one payroll run record plus child result records
- keep exported register rows or payslip payloads as frozen snapshots

A practical approach is:

1. persist your own `payroll_runs` record
2. persist one row per employee result
3. store the normalized result payload as JSON for audit and re-rendering

## Generating Payslips And Register Exports

After computation:

- call `payslip($result)` for a single employee payload
- call `payrollRegister($results)` for row-style export data

After a payroll run has been processed:

- call `generatePayslips($run)`
- call `generatePayrollFiles($run)`

These run-based methods enforce lifecycle checks, which helps prevent premature release artifacts.

## Working With Policies In Laravel

Use these layers in Laravel:

- `config/payroll-engine.php` for package-wide and client-wide defaults
- company payload fields for tenant or company records stored in your database
- employee or input metadata for run-specific edge-case rules
- `App\Payroll\Strategies\...` classes for custom formulas
- `App\Payroll\Policies\...` classes for custom edge-case pipeline behavior

Because strategy and policy class strings are resolved through the container, you can inject your own services into them.

## Multi-Tenant Laravel Applications

A good multi-tenant pattern is:

1. store a `client_code` or policy code on the company
2. define `presets.{client_code}` in config
3. add `strategies.clients.{client_code}` only where formulas differ
4. keep the rest of the application integration the same

This avoids tenant-specific branching across controllers and jobs.

## Testing Your Laravel Integration

A reliable test strategy usually includes:

- unit tests for mappers that translate Eloquent models into package payloads
- feature tests for payroll preview or payroll processing endpoints
- tests that verify the right `client_code` activates the expected strategies
- tests that assert serialized totals and issues in your API responses

You do not need to re-test every package calculation in your app. Focus on your mapping, workflow, persistence, and presentation logic.

## Implementation Checklist

Use this checklist when wiring the package into a Laravel app:

- install the package
- publish and review `config/payroll-engine.php`
- define your tenant presets and strategy overrides
- build company, employee, and payroll input mappers
- create actions for `compute()` and `run()`
- decide how payroll results are persisted
- add payslip and export rendering
- add tests around your integration boundary

With that structure in place, the package stays easy to evolve even as payroll rules become more tenant-specific.
