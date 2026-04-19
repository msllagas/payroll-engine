# Policies Guide

`quillbytes/payroll-engine` has multiple policy layers because not every payroll rule should be customized in the same way.

Some rules are company defaults, some are per-client computation strategies, and some are runtime edge-case decisions that only apply to a specific employee or payroll input.

This guide explains which policy surface to use, how values are resolved, and how to extend the package safely.

## Policy Surfaces

Use the policy surface that matches the kind of change you need:

| Surface | Use it for | Typical examples |
| --- | --- | --- |
| `defaults` | Package-wide baseline values | frequency, hours per day, tax strategy, overtime premiums |
| `presets.{client_code}` | Client or tenant defaults | company-specific statutory handling, release lead time, manual OT mode |
| `strategies.default` | Application-wide formula replacement | custom overtime, withholding, Pag-IBIG, workflow |
| `strategies.clients.{client_code}` | Client-specific formula replacement | one tenant uses a different workflow or tax calculator |
| `edge_case_policies` | Replace the edge-case policy pipeline | add or swap prepare/finalize policy objects |
| `edge_case_policy` metadata | Runtime policy decisions for a company, employee, or payroll input | missing attendance mode, deduction overlap handling, partial payout limit |

## Resolution Order

### Company policy values

For fields such as `frequency`, `manual_overtime_pay`, `tax_strategy`, or premium multipliers, the resolved company policy is built in this order:

1. Built-in package preset defaults from the client policy registry.
2. Published config values from `defaults`.
3. Published config values from `presets.{client_code}`.
4. Explicit fields on the company payload passed to `compute()` or `run()`.

That means the company payload always wins for direct company fields.

## Strategy resolution order

For replaceable calculators and workflows, the engine resolves in this order:

1. `strategies.clients.{client_code}.{key}`
2. `strategies.default.{key}`
3. Internal package fallback class

The available strategy keys are:

- `workflow`
- `rate`
- `overtime`
- `variable_earnings`
- `withholding`
- `pagibig`

When you provide a class string in Laravel, the package resolves it through the container. That means your custom strategy can use constructor injection for repositories, services, or other application dependencies.

## Edge Case Policy Resolution Order

Edge-case behavior is driven by `edge_case_policy` metadata, not by `edge_case_policies`.

The merge order is:

1. `company.edge_case_policy`
2. `employee.edge_case_policy`
3. `input.edge_case_policy`

Later layers override earlier ones. In practice:

- company-level metadata sets the default operating rule
- employee-level metadata overrides the rule for a specific employee
- input-level metadata overrides both for a specific payroll run or adjustment

Example:

```php
$company = [
    'client_code' => 'base',
    'edge_case_policy' => [
        'no_attendance_data' => 'warn',
        'negative_net_pay' => 'defer_deductions',
        'minimum_take_home_pay' => 500,
    ],
];

$employee = [
    'employee_number' => 'EMP-001',
    'edge_case_policy' => [
        'minimum_take_home_pay' => 1000,
    ],
];

$input = [
    'period' => [
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
    ],
    'edge_case_policy' => [
        'partial_payout_limit' => 3000,
    ],
];
```

The effective runtime policy becomes:

- `no_attendance_data = warn`
- `negative_net_pay = defer_deductions`
- `minimum_take_home_pay = 1000`
- `partial_payout_limit = 3000`

## Default Policy Keys

The published config file at `config/payroll-engine.php` exposes the main company-level policy defaults.

### Scheduling and rate basis

- `frequency`
- `hours_per_day`
- `work_days_per_year`
- `eemr_factor`
- `release_lead_days`

These fields control payroll scheduling and rate normalization. They affect how the company profile is normalized before payroll math begins.

### Overtime and payout behavior

- `manual_overtime_pay`
- `fixed_per_day_rate`
- `separate_allowance_payout`
- `external_leave_management`

Use these when a client wants the same formula flow but a different interpretation of attendance, allowances, or rate behavior.

### Statutory and tax defaults

- `split_monthly_statutory_across_periods`
- `pagibig_mode`
- `pagibig_schedule`
- `tax_strategy`
- `annual_bonus_tax_shield`

These keys define the default statutory and tax handling that gets applied unless the company payload overrides them.

### Premium multipliers

- `work_day_ot_premium`
- `rest_day_ot_premium`
- `holiday_ot_premium`
- `rest_day_holiday_ot_premium`
- `night_shift_differential_premium`

Premium values are stored as decimal multipliers.

Examples:

- `1.25` means 125 percent pay
- `0.10` means a 10 percent premium

## When To Use `presets`

Use `presets.{client_code}` when the tenant or client still uses the package's normal workflow and calculators, but needs different default values.

Good examples:

- Client A always uses projected annualized withholding.
- Client B always separates allowance payout.
- Client C has a different release lead time and EEMR factor.

Example:

```php
// config/payroll-engine.php

'presets' => [
    'tenant-a' => [
        'release_lead_days' => 1,
        'manual_overtime_pay' => true,
        'tax_strategy' => 'projected_annualized',
    ],
],
```

This is the lightest-weight customization path and should be your first choice when the formulas themselves do not need to change.

## When To Use `strategies`

Use `strategies` when the formulas, sequencing, or result building need to change.

Examples:

- a client computes overtime using a special attendance source
- a client applies a different withholding or bonus tax policy
- a client needs custom variable earning treatment
- a client needs a different overall payroll workflow

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

The package supports either:

- class strings
- already-instantiated objects that implement the matching contract

## Strategy Contracts

The main extension contracts are:

- `QuillBytes\PayrollEngine\Contracts\PayrollWorkflow`
- `QuillBytes\PayrollEngine\Contracts\RateCalculator`
- `QuillBytes\PayrollEngine\Contracts\OvertimeCalculator`
- `QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator`
- `QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator`
- `QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator`

Use `PayrollWorkflow` only when you need to take over the whole payroll calculation flow.

Use the narrower contracts when you only want to change one part of the pipeline and keep the package's standard workflow.

## Default Edge-Case Pipeline

If `edge_case_policies` is empty, the package uses this default pipeline:

1. `RuleConflictPolicy`
2. `AttendanceDataPolicy`
3. `DeductionOverlapPolicy`
4. `NetPayResolutionPolicy`

The pipeline runs in two phases:

- `prepare()` before payroll computation
- `finalize()` after the main workflow returns a `PayrollResult`

That lets a policy either reshape input before payroll math or adjust the result after the workflow has finished.

## Supported Runtime Edge-Case Keys

These keys are used by the package's default edge-case policies.

| Key | Used by | Meaning |
| --- | --- | --- |
| `attendance_required` | `AttendanceDataPolicy` | Marks attendance as required for the company, employee, or input |
| `no_attendance_data` | `AttendanceDataPolicy` | `allow`, `warn`, or `error` when required attendance data is missing |
| `overlapping_deductions` | `DeductionOverlapPolicy` | `allow`, `error`, or `merge` duplicate manual or loan deductions |
| `negative_net_pay` | `NetPayResolutionPolicy` | `allow`, `error`, or `defer_deductions` when net pay falls below the threshold |
| `minimum_take_home_pay` | `RuleConflictPolicy`, `NetPayResolutionPolicy` | Minimum take-home amount the engine should preserve |
| `partial_payout_limit` | `RuleConflictPolicy`, `NetPayResolutionPolicy` | Cap the released take-home pay and emit a partial-payout issue |

## Runtime Edge-Case Example

```php
$company = [
    'client_code' => 'base',
    'edge_case_policy' => [
        'no_attendance_data' => 'error',
        'overlapping_deductions' => 'merge',
        'negative_net_pay' => 'defer_deductions',
        'minimum_take_home_pay' => 500,
    ],
];

$employee = [
    'employee_number' => 'EMP-001',
    'edge_case_policy' => [
        'attendance_required' => true,
    ],
];

$input = [
    'period' => [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
        'release_date' => '2026-04-15',
    ],
    'manual_deductions' => [
        ['label' => 'Cash Advance', 'amount' => 300],
        ['label' => 'Cash Advance', 'amount' => 200],
    ],
];
```

In this example:

- payroll fails if attendance is required but missing
- overlapping `Cash Advance` deductions are merged
- if deductions push the employee below the minimum take-home amount, deferrable deductions are held back

## Replacing The Edge-Case Pipeline

Use `edge_case_policies` when you want to replace or extend the package's default policy objects.

Example:

```php
// config/payroll-engine.php

'edge_case_policies' => [
    \App\Payroll\Policies\CustomAttendancePolicy::class,
    \App\Payroll\Policies\CustomDeductionPriorityPolicy::class,
    \App\Payroll\Policies\CustomNetPayPolicy::class,
],
```

Each configured class must implement `QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy`.

## Writing A Custom Edge-Case Policy

```php
<?php

namespace App\Payroll\Policies;

use QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollIssue;
use QuillBytes\PayrollEngine\Data\PayrollResult;

final class MissingCostCenterPolicy implements PayrollEdgeCasePolicy
{
    public function prepare(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollInput {
        return $input;
    }

    public function finalize(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        PayrollResult $result,
    ): PayrollResult {
        if ($employee->allocation->costCenter !== null) {
            return $result;
        }

        return $result->with([
            'issues' => [
                ...$result->issues,
                new PayrollIssue(
                    code: 'missing_cost_center',
                    message: 'Payroll was computed without a cost center assignment.',
                    severity: 'warning',
                ),
            ],
        ]);
    }
}
```

Register it in the pipeline:

```php
'edge_case_policies' => [
    \QuillBytes\PayrollEngine\Policies\RuleConflictPolicy::class,
    \QuillBytes\PayrollEngine\Policies\AttendanceDataPolicy::class,
    \App\Payroll\Policies\MissingCostCenterPolicy::class,
    \QuillBytes\PayrollEngine\Policies\NetPayResolutionPolicy::class,
],
```

When running inside Laravel, class strings in the policy pipeline are resolved from the container, so this class may also depend on application services.

## Choosing The Right Customization Level

Use this decision guide:

- Change `defaults` when the rule should apply package-wide.
- Change `presets` when the rule should apply only to a tenant or client code.
- Change company payload fields when the rule belongs to one concrete company record.
- Change `strategies` when the computation logic itself must change.
- Change `edge_case_policy` metadata when the rule is runtime-specific to a company, employee, or payroll input.
- Change `edge_case_policies` when you need new prepare/finalize policy objects in the pipeline.

## Audit And Traceability

Every computed `PayrollResult` is enriched with audit metadata. That audit payload includes:

- applied strategy classes
- edge-case policy class names
- payroll line metadata
- issues and warnings

This makes policy decisions visible in downstream review tools, exports, and debugging flows.
