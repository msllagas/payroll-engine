<?php

use QuillBytes\PayrollEngine\Calculators\OvertimeCalculator;
use QuillBytes\PayrollEngine\Calculators\PagIbigContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\PayrollCalculator;
use QuillBytes\PayrollEngine\Calculators\PhilHealthContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\RateCalculator;
use QuillBytes\PayrollEngine\Calculators\SssContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\VariableEarningCalculator;
use QuillBytes\PayrollEngine\Calculators\WithholdingTaxCalculator;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payroll Policy Keys
    |--------------------------------------------------------------------------
    |
    | These keys are the package-wide baseline policy values.
    | Each client preset under "presets" uses the exact same counterpart key
    | names and overrides the matching default when that preset is selected.
    |
    | frequency:
    |   Payroll frequency. Supported values: monthly, semi_monthly, weekly
    |
    | hours_per_day:
    |   Standard working hours in a day used for hourly-rate computation
    |
    | work_days_per_year:
    |   Annual working-day divisor used for PH daily-rate computation
    |
    | eemr_factor:
    |   Company payroll factor used as the base divisor for daily-rate logic
    |
    | release_lead_days:
    |   Number of days before the period end/release date that payroll is set
    |   to be released. Example: 1 means release one day earlier
    |
    | manual_overtime_pay:
    |   true  = use manual OT amount input instead of computed OT hours
    |   false = compute OT based on hours x hourly rate x premium
    |
    | fixed_per_day_rate:
    |   true  = respect employee fixed daily-rate behavior
    |   false = compute daily rate from salary and EEMR
    |
    | separate_allowance_payout:
    |   true  = allowances are excluded from regular net pay and treated as a
    |           separate payout
    |   false = allowances are included in the normal payroll earning flow
    |
    | external_leave_management:
    |   true  = leave is expected to come from a separate module/integration
    |   false = payroll may handle leave-driven deductions internally
    |
    | split_monthly_statutory_across_periods:
    |   true  = split monthly statutory deductions across payroll periods
    |   false = apply the full monthly deduction in one run
    |
    | pagibig_mode:
    |   standard_mandatory         = use the default mandatory Pag-IBIG share
    |   split_per_cutoff           = backward-compatible alias for mandatory
    |                                 mode with split-per-cutoff scheduling
    |   upgraded_voluntary         = use upgraded employee Pag-IBIG savings
    |   loan_amortization_separated = keep Pag-IBIG loan amortization as a
    |                                 separate deduction line
    |
    | pagibig_schedule:
    |   split_per_cutoff = divide the monthly Pag-IBIG amount across cutoffs
    |   monthly          = deduct the full monthly amount only on the due run
    |   null / omitted   = follow the company statutory split rule, or the
    |                      legacy `pagibig_mode = split_per_cutoff` alias
    |
    | tax_strategy:
    |   current_period_annualized = annualize current period taxable income
    |   projected_annualized      = use projected annual taxable income
    |
    | annual_bonus_tax_shield:
    |   Non-taxable bonus ceiling before excess bonus becomes taxable
    |
    | OT / premium keys:
    |   Stored as decimal multipliers, not percentages.
    |   Example: 1.25 = 125% pay, 0.10 = 10% night differential premium
    |
    */
    'defaults' => [
        'frequency' => 'semi_monthly',
        'hours_per_day' => 8,
        'work_days_per_year' => 313,
        'eemr_factor' => 313,
        'release_lead_days' => 0,
        'manual_overtime_pay' => false,
        'fixed_per_day_rate' => false,
        'separate_allowance_payout' => false,
        'external_leave_management' => false,
        'split_monthly_statutory_across_periods' => true,
        'pagibig_mode' => 'standard_mandatory',
        'pagibig_schedule' => null,
        'tax_strategy' => 'current_period_annualized',
        'annual_bonus_tax_shield' => 90000,
        'work_day_ot_premium' => 1.25,
        'rest_day_ot_premium' => 1.69,
        'holiday_ot_premium' => 2.60,
        'rest_day_holiday_ot_premium' => 3.38,
        'night_shift_differential_premium' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Presets
    |--------------------------------------------------------------------------
    |
    | Each preset key must match its counterpart key in "defaults".
    | Only put values here that should override the package baseline for that
    | specific client/company policy.
    |
    */
    'presets' => [
        'enterprise-365' => [
            'frequency' => 'semi_monthly',
            'hours_per_day' => 8,
            'work_days_per_year' => 365,
            'eemr_factor' => 365,
            'release_lead_days' => 1,
            'manual_overtime_pay' => true,
            'fixed_per_day_rate' => true,
            'separate_allowance_payout' => true,
            'external_leave_management' => true,
            'split_monthly_statutory_across_periods' => true,
            'pagibig_mode' => 'standard_mandatory',
            'pagibig_schedule' => null,
            'tax_strategy' => 'projected_annualized',
            'annual_bonus_tax_shield' => 90000,
            'work_day_ot_premium' => 1.25,
            'rest_day_ot_premium' => 1.69,
            'holiday_ot_premium' => 2.60,
            'rest_day_holiday_ot_premium' => 3.38,
            'night_shift_differential_premium' => 0.10,
        ],
        'regional-hq-365' => [
            'frequency' => 'semi_monthly',
            'hours_per_day' => 8,
            'work_days_per_year' => 365,
            'eemr_factor' => 365,
            'release_lead_days' => 1,
            'manual_overtime_pay' => true,
            'fixed_per_day_rate' => true,
            'separate_allowance_payout' => true,
            'external_leave_management' => true,
            'split_monthly_statutory_across_periods' => true,
            'pagibig_mode' => 'standard_mandatory',
            'pagibig_schedule' => null,
            'tax_strategy' => 'projected_annualized',
            'annual_bonus_tax_shield' => 90000,
            'work_day_ot_premium' => 1.25,
            'rest_day_ot_premium' => 1.69,
            'holiday_ot_premium' => 2.60,
            'rest_day_holiday_ot_premium' => 3.38,
            'night_shift_differential_premium' => 0.10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Strategy Overrides
    |--------------------------------------------------------------------------
    |
    | These class mappings control how payroll is computed for each client.
    |
    | default:
    |   Package strategies used when no client-specific override is configured.
    |
    | clients.<client_code>.rate:
    |   Override only the rate computation logic.
    |
    | clients.<client_code>.overtime:
    |   Override only the overtime earning computation logic.
    |
    | clients.<client_code>.variable_earnings:
    |   Override variable earning logic such as sales commissions, production
    |   incentives, or quota-based bonuses.
    |
    | clients.<client_code>.withholding:
    |   Override only the withholding and bonus tax logic.
    |
    | clients.<client_code>.sss:
    |   Override only the SSS contribution logic.
    |
    | clients.<client_code>.philhealth:
    |   Override only the PhilHealth contribution logic.
    |
    | clients.<client_code>.pagibig:
    |   Override only the Pag-IBIG contribution and related deduction logic.
    |
    | clients.<client_code>.workflow:
    |   Override the whole payroll workflow when a client needs a different
    |   business flow, sequencing, or result-building process.
    |
    | All custom classes should implement the matching contract and may be
    | resolved through Laravel's container when the package runs inside Laravel.
    |
    */
    'strategies' => [
        'default' => [
            'workflow' => PayrollCalculator::class,
            'rate' => RateCalculator::class,
            'overtime' => OvertimeCalculator::class,
            'variable_earnings' => VariableEarningCalculator::class,
            'withholding' => WithholdingTaxCalculator::class,
            'sss' => SssContributionCalculator::class,
            'philhealth' => PhilHealthContributionCalculator::class,
            'pagibig' => PagIbigContributionCalculator::class,
        ],
        'clients' => [
            /*
            'client-code' => [
                'rate' => \App\Payroll\Strategies\ClientRateCalculator::class,
                'overtime' => \App\Payroll\Strategies\ClientOvertimeCalculator::class,
                'variable_earnings' => \App\Payroll\Strategies\ClientVariableEarningCalculator::class,
                'withholding' => \App\Payroll\Strategies\ClientWithholdingTaxCalculator::class,
                'sss' => \App\Payroll\Strategies\ClientSssCalculator::class,
                'philhealth' => \App\Payroll\Strategies\ClientPhilHealthCalculator::class,
                'pagibig' => \App\Payroll\Strategies\ClientPagIbigCalculator::class,
                'workflow' => \App\Payroll\Strategies\ClientPayrollWorkflow::class,
            ],
            */
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Edge Case Policies
    |--------------------------------------------------------------------------
    |
    | Optional policy objects that prepare payroll input and/or finalize payroll
    | results for edge scenarios such as:
    | - missing attendance data
    | - overlapping deductions
    | - conflicting rule sets
    | - insufficient net pay
    | - partial payout handling
    |
    | When omitted, the package uses its default policy pipeline.
    | You may replace the whole pipeline by supplying class strings or policy
    | instances that implement \QuillBytes\PayrollEngine\Contracts\PayrollEdgeCasePolicy.
    |
    */
    'edge_case_policies' => [
        /*
        \App\Payroll\Policies\CustomAttendancePolicy::class,
        \App\Payroll\Policies\CustomDeductionPriorityPolicy::class,
        \App\Payroll\Policies\CustomNetPayPolicy::class,
        */
    ],
];
