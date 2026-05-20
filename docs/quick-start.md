# Quick Start Guide

This guide shows the fastest path to a working payroll computation.

## Goal

In less than five minutes, you should be able to:

- install the package
- compute payroll for one employee
- inspect key totals
- generate a payslip-ready payload

## 1. Install The Package

```bash
composer require quillbytes/payroll-engine
php artisan vendor:publish --tag=payroll-engine-config
```

Publishing config is optional for a simple smoke test, but recommended if you plan to customize defaults or strategies.

## 2. Resolve The Engine

```php
use QuillBytes\PayrollEngine\PayrollEngine;

$engine = app(PayrollEngine::class);
```

You can also resolve it via:

```php
$engine = app('payroll-engine');
$result = \PayrollEngine::compute($company, $employee, $input);
```

## 3. Prepare Minimal Input

### Company payload

```php
$company = [
    'name' => 'Base Client',
    'client_code' => 'base',
    'prepared_by' => ['payroll.preparer'],
    'approvers' => ['chief.approver'],
    'administrators' => ['admin.user'],
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
```

### Employee payload

```php
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
    'representation' => 2000,
    'allowances' => 1000,
];
```

### Payroll input

```php
$input = [
    'period' => [
        'key' => '2026-04-A',
        'start_date' => '2026-04-01',
        'end_date' => '2026-04-15',
        'release_date' => '2026-04-15',
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
    'loan_deductions' => [
        [
            'label' => 'Loan Payment',
            'amount' => 1000,
        ],
    ],
    'absence_deduction' => 300,
];
```

## 4. Compute Payroll

```php
use QuillBytes\PayrollEngine\Support\MoneyHelper;

$result = $engine->compute($company, $employee, $input);

[
    'scheduled_basic_pay' => MoneyHelper::toFloat($result->rates->scheduledBasicPay),
    'daily_rate' => MoneyHelper::toFloat($result->rates->dailyRate),
    'hourly_rate' => MoneyHelper::toFloat($result->rates->hourlyRate),
    'gross_pay' => MoneyHelper::toFloat($result->grossPay),
    'taxable_income' => MoneyHelper::toFloat($result->taxableIncome),
    'net_pay' => MoneyHelper::toFloat($result->netPay),
    'take_home_pay' => MoneyHelper::toFloat($result->takeHomePay),
];
```

## 5. Expected Output For This Example

These values come from the package test suite and are a good smoke-test baseline for the default `base` client flow:

```php
[
    'scheduled_basic_pay' => 15000.00,
    'daily_rate' => 1150.16,
    'hourly_rate' => 143.77,
    'gross_pay' => 19398.56,
    'taxable_income' => 15223.56,
    'net_pay' => 15952.53,
    'take_home_pay' => 15952.53,
]
```

## 6. Generate A Payslip Payload

```php
$payslip = $engine->payslip($result);
```

The payslip payload is array-based, so it can be:

- rendered in Blade
- converted to PDF
- returned from an API
- stored as a snapshot

## 7. What This Proves

If the example above works, the package is correctly:

- installed
- resolved through Laravel's container
- normalizing your payloads
- computing rates, earnings, contributions, deductions, and taxes
- returning report-ready output

## Next Steps

After the quick start:

1. Review [Configuration Reference](configuration-reference.md) to understand defaults and overrides.
2. Read [Usage Guide](usage-guide.md) for real application patterns.
3. Read [Laravel Implementation Guide](laravel-implementation.md) to wire the package into actions, jobs, and controllers.
4. Read [Policies Guide](policies.md) if you need tenant-specific rules or runtime edge-case handling.

