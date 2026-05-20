# Extending the Package

This guide explains how to customize the package without forking it.

## Choose The Smallest Extension Point First

Use the narrowest possible customization layer:

- `defaults` for package-wide baseline rules
- `presets` for client-specific default values
- company payload fields for one concrete company
- `strategies` for formula or workflow replacement
- runtime `edge_case_policy` for per-company, per-employee, or per-run behavior
- `edge_case_policies` for replacing the policy pipeline itself

## Extending Through `presets`

Use `presets` when the formulas stay the same but a client needs different defaults.

Examples:

- release one day earlier
- projected annualized withholding
- separate allowance payout
- fixed daily-rate handling

```php
'presets' => [
    'tenant-a' => [
        'release_lead_days' => 1,
        'manual_overtime_pay' => true,
        'tax_strategy' => 'projected_annualized',
    ],
],
```

## Extending Through `strategies`

Use `strategies` when you need to replace part of the payroll math.

Available strategy keys:

- `rate`
- `overtime`
- `variable_earnings`
- `withholding`
- `pagibig`
- `workflow`

Example:

```php
'strategies' => [
    'clients' => [
        'tenant-a' => [
            'rate' => \App\Payroll\Strategies\TenantARateCalculator::class,
            'withholding' => \App\Payroll\Strategies\TenantAWithholdingTaxCalculator::class,
        ],
    ],
],
```

## Strategy Contracts

The main contracts are:

- `QuillBytes\PayrollEngine\Contracts\PayrollWorkflow`
- `QuillBytes\PayrollEngine\Contracts\RateCalculator`
- `QuillBytes\PayrollEngine\Contracts\OvertimeCalculator`
- `QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator`
- `QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator`
- `QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator`

## Example: Custom Rate Strategy

```php
<?php

namespace App\Payroll\Strategies;

use QuillBytes\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollPeriod;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class TenantARateCalculator implements RateCalculatorContract
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollPeriod $period,
    ): RateSnapshot {
        return new RateSnapshot(
            monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
            scheduledBasicPay: MoneyHelper::fromNumeric(1000),
            dailyRate: MoneyHelper::fromNumeric(500),
            hourlyRate: MoneyHelper::fromNumeric(100),
            fixedPerDayApplied: true,
        );
    }
}
```

## Example: Custom Workflow

Use a full custom workflow only when replacing one narrow strategy is not enough.

```php
<?php

namespace App\Payroll\Strategies;

use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollResult;

final class TenantPayrollWorkflow implements PayrollWorkflow
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
    ): PayrollResult {
        throw new \LogicException('Build a custom workflow result here.');
    }
}
```

Use a custom workflow when:

- earnings and deductions are sequenced differently
- the client uses a non-standard payroll model
- narrow strategy replacement still cannot express the business rules

## Extending Runtime Behavior With `edge_case_policy`

Runtime edge-case behavior belongs in payload metadata rather than strategy classes when the rule is contextual.

Example:

```php
'edge_case_policy' => [
    'attendance_required' => true,
    'no_attendance_data' => 'error',
    'negative_net_pay' => 'defer_deductions',
    'minimum_take_home_pay' => 500,
],
```

This is a good fit when the behavior differs by:

- company
- employee
- one payroll run

## Replacing The Edge-Case Pipeline

Use `edge_case_policies` when you want to add or replace the prepare/finalize policy objects themselves.

Example:

```php
'edge_case_policies' => [
    \App\Payroll\Policies\CustomAttendancePolicy::class,
    \App\Payroll\Policies\CustomNetPayPolicy::class,
],
```

Each class must implement `QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy`.

## Multi-Tenant Pattern

A strong multi-tenant setup usually looks like this:

1. store `client_code` on the company record
2. use `presets.{client_code}` for client defaults
3. use `strategies.clients.{client_code}` only where formulas differ
4. use runtime `edge_case_policy` for context-specific exceptions

This prevents tenant branching from leaking into controllers and jobs.

## Laravel Container Notes

Strategy and policy class strings are resolved through Laravel's container when the package is used inside Laravel.

This means custom classes can use constructor injection for:

- repositories
- services
- calculators
- tenant context providers

## Extension Checklist

Before publishing a customization:

- choose the smallest extension point that solves the problem
- add tests for the new rule
- update the relevant config and docs
- verify the audit trail still explains the result clearly

## Related Guides

- [Policies Guide](policies.md)
- [Architecture Overview](architecture.md)
- [API Reference](api-reference.md)

