# Configuration Reference

The package config lives in:

- package source: `config/config.php`
- published app config: `config/payroll-engine.php`

Publish it with:

```bash
php artisan vendor:publish --tag=payroll-engine-config
```

## Config Structure

The published config has four main sections:

- `defaults`
- `presets`
- `strategies`
- `edge_case_policies`

## `defaults`

These are the package-wide baseline policy values.

### Schedule And Rate Basis

| Key | Type | Default | Description | When to change |
| --- | --- | --- | --- | --- |
| `frequency` | `string` | `semi_monthly` | Payroll frequency used to derive periods per year | Change when a client runs monthly or weekly payroll |
| `hours_per_day` | `int` | `8` | Standard hours per work day used in hourly rate derivation | Change when the company uses a non-8-hour day |
| `work_days_per_year` | `int` | `313` | Annual work-day divisor used by PH payroll conventions | Change for company-specific labor basis or alternative divisor |
| `eemr_factor` | `int` | `313` | Divisor used to compute daily rate from monthly salary | Change when daily rate should follow a different annual basis |
| `release_lead_days` | `int` | `0` | Number of days before the period end used to derive release date | Change when payroll is released ahead of cutoff end |

### Payout And Handling Rules

| Key | Type | Default | Description | When to change |
| --- | --- | --- | --- | --- |
| `manual_overtime_pay` | `bool` | `false` | Use manual OT amount instead of OT hours x hourly rate x premium | Change when the client encodes approved OT amounts directly |
| `fixed_per_day_rate` | `bool` | `false` | Respect employee fixed daily rate when provided | Change when daily-paid or client-fixed daily rates should override computed daily rate |
| `separate_allowance_payout` | `bool` | `false` | Pay allowances outside the normal net-pay flow | Change when allowances are released separately from salary |
| `external_leave_management` | `bool` | `false` | Indicates leave handling is managed by another module | Change when attendance or leave deductions are driven by an external system |

### Statutory And Tax Rules

| Key | Type | Default | Description | When to change |
| --- | --- | --- | --- | --- |
| `split_monthly_statutory_across_periods` | `bool` | `true` | Split monthly statutory contributions across payroll periods | Change when the company deducts the full monthly amount in one run |
| `pagibig_mode` | `string` | `standard_mandatory` | Base Pag-IBIG contribution mode | Change when voluntary or separated loan handling is required |
| `pagibig_schedule` | `string|null` | `null` | Explicit Pag-IBIG deduction schedule | Change when Pag-IBIG should always be monthly or split per cutoff |
| `tax_strategy` | `string` | `current_period_annualized` | Withholding strategy for annualizing taxable income | Change when the client uses projected annual taxable income |
| `annual_bonus_tax_shield` | `int|float` | `90000` | Non-taxable annual bonus ceiling before bonus tax applies | Change when legal or client policy uses a different shield |

### Premium Multipliers

| Key | Type | Default | Description | When to change |
| --- | --- | --- | --- | --- |
| `work_day_ot_premium` | `float` | `1.25` | Overtime multiplier for regular work days | Change when client OT premium differs |
| `rest_day_ot_premium` | `float` | `1.69` | Overtime multiplier for rest days | Change for client-specific rest day OT rules |
| `holiday_ot_premium` | `float` | `2.60` | Overtime multiplier for holidays | Change for holiday-specific policies |
| `rest_day_holiday_ot_premium` | `float` | `3.38` | Overtime multiplier for rest-day holidays | Change for company-specific combined-premium logic |
| `night_shift_differential_premium` | `float` | `0.10` | Night differential premium | Change when the company uses a different NSD premium |

## `presets`

`presets` applies overrides by `client_code`.

Shape:

```php
'presets' => [
    'tenant-a' => [
        'release_lead_days' => 1,
        'manual_overtime_pay' => true,
        'tax_strategy' => 'projected_annualized',
    ],
],
```

Use `presets` when:

- the formulas stay the same
- the client needs different defaults
- you want to avoid repeating company-level policy values in every payload

Built-in preset keys currently include:

- `krbs`
- `krbs-rohq`

## `strategies`

`strategies` swaps the classes used during payroll computation.

### `strategies.default`

These are the package defaults used when no client-specific override is registered.

| Key | Contract | Default class | Responsibility |
| --- | --- | --- | --- |
| `workflow` | `PayrollWorkflow` | `PayrollCalculator` | Full payroll workflow |
| `rate` | `RateCalculator` | `RateCalculator` | Scheduled basic pay, daily, and hourly rate |
| `overtime` | `OvertimeCalculator` | `OvertimeCalculator` | Overtime earning computation |
| `variable_earnings` | `VariableEarningCalculator` | `VariableEarningCalculator` | Variable earnings lines |
| `withholding` | `WithholdingTaxCalculator` | `WithholdingTaxCalculator` | Regular withholding and bonus tax |
| `pagibig` | `PagIbigContributionCalculator` | `PagIbigContributionCalculator` | Pag-IBIG contribution logic |

### `strategies.clients.{client_code}`

Use client-specific strategy overrides when one tenant needs a different calculator or workflow:

```php
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

Use `strategies` when:

- the formulas themselves need to change
- a client has a different sequencing or workflow
- the package defaults are not enough even after `presets`

## `edge_case_policies`

This config key replaces the default edge-case policy pipeline.

Expected value:

- array of class strings implementing `QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy`
- or policy instances that implement that contract

Example:

```php
'edge_case_policies' => [
    \App\Payroll\Policies\CustomAttendancePolicy::class,
    \App\Payroll\Policies\CustomNetPayPolicy::class,
],
```

Default package pipeline when omitted:

1. `RuleConflictPolicy`
2. `AttendanceDataPolicy`
3. `DeductionOverlapPolicy`
4. `NetPayResolutionPolicy`

## Runtime `edge_case_policy` Metadata

Separate from `edge_case_policies`, the runtime `edge_case_policy` metadata controls how the active policies behave during a computation.

These values can be attached to:

- company payload
- employee payload
- payroll input payload

Merge precedence:

1. company metadata
2. employee metadata
3. input metadata

### Supported Runtime Keys

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `attendance_required` | `bool` | `false` | Marks attendance as required for the current context |
| `no_attendance_data` | `string` | `allow` | What to do when attendance is required but missing: `allow`, `warn`, `error` |
| `overlapping_deductions` | `string` | `allow` | How to handle duplicate manual or loan deductions: `allow`, `error`, `merge` |
| `negative_net_pay` | `string` | `allow` | How to handle insufficient net pay: `allow`, `error`, `defer_deductions` |
| `minimum_take_home_pay` | `int|float` | `0` | Minimum take-home amount to preserve |
| `partial_payout_limit` | `int|float|null` | `null` | Maximum released take-home amount when partial payout is enabled |

## When To Change What

Use this decision guide:

- change `defaults` when the baseline should apply package-wide
- change `presets` when the baseline should apply only to one client code
- change company payload fields when one concrete company record should override config
- change `strategies` when computation logic must change
- change runtime `edge_case_policy` when the policy is contextual to one company, employee, or payroll run
- change `edge_case_policies` when you need a different prepare/finalize policy pipeline

## Related Guides

- [Policies Guide](policies.md)
- [Extending the Package](extending.md)
- [Laravel Implementation Guide](laravel-implementation.md)

