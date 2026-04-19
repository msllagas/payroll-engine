# Workflow Reference

This guide documents the package's public workflows method by method.

It is intended for maintainers, integrators, and future debuggers who need to answer questions like:

- which engine method should this feature call
- which defaults or runtime policies affect that method
- what shape of input and output should I expect
- where should I look when behavior changes

## At A Glance

| Workflow | Entry point | Primary output | Best fit |
| --- | --- | --- | --- |
| Single employee payroll | `compute()` | `PayrollResult` | preview, recalculation, final pay, one-off release |
| Batch cutoff payroll | `run()` | `PayrollRun` | cutoff processing for many employees |
| Single payslip payload | `payslip()` | array | Blade, PDF, API serialization |
| Register/export rows | `payrollRegister()` or `register()` | array of rows | CSV, bank file sources, finance export |
| Run-scoped export rows | `generatePayrollFiles()` | array of rows | gated export after run processing |
| Allocation summary | `allocationSummary()` | grouped rows | costing and dimension reporting |
| Retro delta input | `retroAdjustmentInput()` | `PayrollInput` | release only the difference from recomputation |
| Run-scoped payslips | `generatePayslips()` | array of payslip arrays | gated employee artifact generation |
| Batch lifecycle | `PayrollRun` methods | mutated `PayrollRun` | prepare, approve, process, reopen, release |

## Shared Assumptions

All examples below assume:

- `$engine` is an instance of `QuillBytes\PayrollEngine\PayrollEngine`
- `$company`, `$employee`, `$period`, and `$input` are valid package-compatible payloads
- examples are intentionally small so the workflow behavior is easier to see

For a fuller smoke-test payload with expected totals, see [Quick Start Guide](quick-start.md).

## `compute()`

### Purpose

`compute($company, $employee, $input)` is the main single-employee execution path.

It normalizes input, validates it, applies edge-case policies, resolves the active strategy set, runs the workflow, enriches trace metadata, and returns a `PayrollResult`.

### Typical Uses

- payroll preview in UI or API
- employee recalculation after attendance or adjustment changes
- final pay computation for one employee
- off-cycle or bonus release for one employee

### Sample

```php
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

$engine = app(PayrollEngine::class);

$result = $engine->compute($company, $employee, [
    'period' => [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
        'release_date' => '2026-04-15',
        'run_type' => 'regular',
    ],
    'overtime' => [
        ['type' => 'regular', 'hours' => 2],
    ],
    'adjustments' => [
        ['label' => 'Taxable Adjustment', 'amount' => 500, 'taxable' => true],
    ],
    'manual_deductions' => [
        ['label' => 'Uniform Deduction', 'amount' => 250],
    ],
]);

[
    'gross_pay' => MoneyHelper::toFloat($result->grossPay),
    'taxable_income' => MoneyHelper::toFloat($result->taxableIncome),
    'net_pay' => MoneyHelper::toFloat($result->netPay),
    'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
    'issues' => $result->issues,
    'audit' => $result->audit,
];
```

### Inputs That Commonly Change Behavior

Defaults, presets, company fields, or runtime metadata that most often affect `compute()`:

- `client_code`
- `frequency`
- `hours_per_day`
- `work_days_per_year`
- `eemr_factor`
- `release_lead_days`
- `manual_overtime_pay`
- `fixed_per_day_rate`
- `separate_allowance_payout`
- `split_monthly_statutory_across_periods`
- `pagibig_mode`
- `pagibig_schedule`
- `tax_strategy`
- `annual_bonus_tax_shield`
- overtime premium keys
- `edge_case_policy`

Employee-level fields that commonly change behavior:

- `monthly_basic_salary`
- `daily_rate`
- `hourly_rate`
- `projected_annual_taxable_income`
- `manual_sss_contribution`
- `manual_philhealth_contribution`
- `manual_pagibig_contribution`
- `upgraded_pagibig_contribution`
- `tax_shield_amount_for_bonuses`
- employee `pagibig_schedule`

### Output You Usually Care About

Important parts of `PayrollResult`:

- `$result->rates`
- `$result->earnings`
- `$result->deductions`
- `$result->employeeContributions`
- `$result->employerContributions`
- `$result->separatePayouts`
- `$result->grossPay`
- `$result->taxableIncome`
- `$result->netPay`
- `$result->takeHomePay`
- `$result->issues`
- `$result->audit`

### Debugging Notes

When `compute()` looks wrong, inspect in this order:

1. `$result->audit['applied_rules']` to confirm the actual strategies, policies, tax strategy, and allowance behavior used.
2. `$result->rates` to confirm monthly, daily, and hourly rate derivation.
3. each line's `metadata` to confirm source, rule, formula, and basis.
4. `$result->issues` to confirm whether a policy changed the final result.

## `run()`

### Purpose

`run($company, $period, $items)` computes one payroll cutoff for many employees.

Internally it normalizes the company once, normalizes the shared period once, filters out employees who are not active during that period, and then calls `compute()` for each remaining item.

### Typical Uses

- semi-monthly payroll generation
- weekly or monthly cutoff processing
- queued batch payroll jobs
- pre-release payroll review runs

### Accepted Item Shapes

Each item can be either:

- an employee payload by itself
- an array with `employee` and optional `input`

The second form is the usual choice because it lets each employee carry different overtime, deductions, bonuses, and adjustments within the same shared cutoff period.

### Sample

```php
$period = [
    'key' => '2026-04-A',
    'start_date' => '2026-04-01',
    'end_date' => '2026-04-15',
    'release_date' => '2026-04-15',
];

$items = [
    [
        'employee' => $employeeA,
        'input' => [
            'overtime' => [
                ['type' => 'regular', 'hours' => 4],
            ],
        ],
    ],
    [
        'employee' => $employeeB,
        'input' => [
            'manual_deductions' => [
                ['label' => 'Late Filing Penalty', 'amount' => 150],
            ],
        ],
    ],
];

$run = $engine->run($company, $period, $items);
```

### Inputs That Commonly Change Behavior

Everything that affects `compute()` also affects `run()`, plus:

- employee hire date
- employee resignation or separation date
- `period.start_date`
- `period.end_date`
- `period.release_date`
- `period.run_type`

### Output You Usually Care About

Important parts of `PayrollRun`:

- `$run->company`
- `$run->period`
- `$run->results`
- `$run->status`
- `$run->auditTrail`
- `$run->totalNetPay()`
- `$run->totalTakeHomePay()`

### Debugging Notes

When an employee is unexpectedly missing from a batch:

1. check the employee's hire and resignation dates
2. confirm the shared cutoff dates
3. confirm the employee is active during that period

If every employee is skipped, `run()` throws `No active payroll entries were generated for the supplied period.`

## `PayrollRun` Lifecycle

### Purpose

`PayrollRun` models the operational states around a batch payroll.

These lifecycle methods do not recompute payroll. They enforce release timing and actor permissions around already-computed results.

### State Progression

Normal path:

1. `Draft`
2. `Prepared`
3. `Approved`
4. `Processed`
5. `Released`

Administrative edit path:

1. `Processed`
2. `reopen()`
3. back to `Draft`

### Sample

```php
use Carbon\CarbonImmutable;

$run
    ->prepare('payroll.preparer', CarbonImmutable::parse('2026-04-13 09:00:00'))
    ->approve('chief.approver', CarbonImmutable::parse('2026-04-13 13:00:00'))
    ->process('system.payroll-job', CarbonImmutable::parse('2026-04-14 08:00:00'))
    ->release('system.release-job', CarbonImmutable::parse('2026-04-15 09:00:00'));
```

### What Each Method Enforces

- `prepare()` requires draft status and an allowed preparer
- `approve()` requires prepared status and an allowed approver
- `process()` requires approved status and must happen before `release_date`
- `reopen()` requires processed status, an allowed administrator, and a timestamp before `release_date`
- `release()` requires processed status and cannot happen before `release_date`
- `assertEditable()` blocks edits to processed or released runs
- `assertCanGeneratePayrollFiles()` requires processed or released status
- `assertCanGeneratePayslips()` requires processed or released status and a timestamp on or after `release_date`

### Debugging Notes

When a lifecycle call fails, check:

- `$run->status`
- `$run->period->releaseDate`
- company `prepared_by`, `approvers`, or `administrators`
- the timestamp passed into the lifecycle call

## `payslip()`

### Purpose

`payslip($result)` converts a single `PayrollResult` into an array payload intended for rendering or serialization.

### Sample

```php
$result = $engine->compute($company, $employee, $input);
$payslip = $engine->payslip($result);
```

### Output Shape

The payload includes:

- `company`
- `period`
- `employee`
- `allocation`
- `rates`
- `earnings`
- `employee_contributions`
- `deductions`
- `separate_payouts`
- `issues`
- `audit`
- `totals`

### Debugging Notes

If the payslip looks wrong, inspect the original `PayrollResult` first. `payslip()` is a serializer, not a second computation layer.

## `payrollRegister()` And `register()`

### Purpose

`payrollRegister(array $results)` converts many `PayrollResult` objects into flat export rows.

`register()` is a backward-compatible alias for the same builder.

### Sample

```php
$run = $engine->run($company, $period, $items);
$rows = $engine->payrollRegister($run->results);

// Equivalent legacy alias:
$sameRows = $engine->register($run->results);
```

### Output Shape

Each row includes:

- employee identity fields
- allocation fields
- payroll account and bank fields
- gross, taxable, net, and take-home totals
- bonus tax withheld
- release date
- run type
- issue codes

### Debugging Notes

If a register row is missing data, inspect the matching `PayrollResult` first. The register builder only flattens existing result data.

## `allocationSummary()`

### Purpose

`allocationSummary(array $results, string $dimension)` groups payroll totals by an allocation dimension.

### Sample

```php
$run = $engine->run($company, $period, $items);

$summary = $engine->allocationSummary($run->results, 'cost_center');
```

### Supported Dimension Inputs

Common values:

- `project_code`
- `project_name`
- `department`
- `branch`
- `cost_center`
- `vessel`

Custom dimensions also work when present in `allocation_dimensions`.

### Output Shape

Each summary row includes:

- `dimension`
- `value`
- `employee_count`
- `gross_pay`
- `taxable_income`
- `net_pay`
- `take_home_pay`

### Debugging Notes

If rows collapse under `unassigned`, check the employee allocation payload and any custom `allocation_dimensions`.

## `retroAdjustmentInput()`

### Purpose

`retroAdjustmentInput($original, $recomputed, $releasePeriod)` compares two payroll results for the same employee and same historical period, then returns a `PayrollInput` that contains only the delta.

Positive earning differences become adjustments. Negative earning differences become recovery deductions. Deduction and contribution differences are inverted into the correct recovery or refund direction.

### Sample

```php
$original = $engine->compute($company, $employee, $originalInput);
$recomputed = $engine->compute($company, $employee, $correctedInput);

$retroInput = $engine->retroAdjustmentInput($original, $recomputed, [
    'key' => '2026-05-ADJ',
    'start_date' => '2026-05-01',
    'end_date' => '2026-05-15',
    'release_date' => '2026-05-15',
    'run_type' => 'adjustment',
]);

$release = $engine->compute($company, $employee, $retroInput);
```

### Preconditions

The original and recomputed results must share:

- the same employee number
- the same client code
- the same historical start and end dates

### Debugging Notes

If this throws `No retroactive payroll differences were found`, compare the original and recomputed line totals first. If it throws a comparability exception, confirm you are not mixing employees, client policies, or periods.

## `generatePayrollFiles()`

### Purpose

`generatePayrollFiles($run)` is the guarded version of register generation for a `PayrollRun`.

It first checks lifecycle state with `assertCanGeneratePayrollFiles()` and then delegates to the register builder.

### Sample

```php
$run
    ->prepare('payroll.preparer')
    ->approve('chief.approver')
    ->process('system.job');

$rows = $engine->generatePayrollFiles($run);
```

### Debugging Notes

If this fails, the issue is usually operational rather than computational. Check the run status first.

## `generatePayslips()`

### Purpose

`generatePayslips($run, ?CarbonImmutable $generatedAt = null)` is the guarded batch payslip generator.

It verifies the run status and the release-date gate before serializing each result into a payslip payload.

### Sample

```php
use Carbon\CarbonImmutable;

$run
    ->prepare('payroll.preparer')
    ->approve('chief.approver')
    ->process('system.job');

$payslips = $engine->generatePayslips(
    $run,
    CarbonImmutable::parse('2026-04-15 08:00:00'),
);
```

### Debugging Notes

If this throws before release day, that is expected. The guard intentionally blocks payslip generation until the configured release date is reached.

## Quick Selection Guide

Use this rule of thumb:

- choose `compute()` when you are working on one employee
- choose `run()` when one period applies to many employees
- choose `payslip()` or `payrollRegister()` when you already have results
- choose `generatePayslips()` or `generatePayrollFiles()` when lifecycle gates matter
- choose `retroAdjustmentInput()` when a historical recomputation should release only the difference
- choose `allocationSummary()` when the consumer cares more about grouped totals than employee-level detail

## Related Guides

- [Default Pipeline Guide](default-pipeline.md)
- [Configuration Reference](configuration-reference.md)
- [Use Cases Guide](use-cases.md)
- [API Reference](api-reference.md)
- [Runbook](runbook.md)
