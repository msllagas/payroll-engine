<?php

namespace QuillBytes\PayrollEngine\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use QuillBytes\PayrollEngine\Enums\PayrollRunStatus;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function engine(): PayrollEngine
{
    return new PayrollEngine;
}

function testPayrollModel(array $attributes = []): Model
{
    return new class($attributes) extends Model
    {
        protected $guarded = [];

        public $timestamps = false;

        protected $casts
            = [
                'approvers' => 'array',
                'prepared_by' => 'array',
                'administrators' => 'array',
                'minimum_wage_earner' => 'boolean',
                'payroll_schedules' => 'array',
            ];
    };
}

/**
 * @return array<string, mixed>
 */
function baseCompany(array $overrides = []): array
{
    return array_replace_recursive([
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
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function baseEmployee(array $overrides = []): array
{
    return array_replace_recursive([
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
    ], $overrides);
}

it('computes base payroll for a regular semi-monthly run', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'representation' => 2000,
            'allowances' => 1000,
        ]),
        [
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
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(15000.00)
        ->and(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(1150.16)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(143.77)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(19398.56)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(15223.56)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(15952.53)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(15952.53)
        ->and($result->employeeContributions)->toHaveCount(3)
        ->and($result->separatePayouts)->toBeEmpty();
});

it('applies enterprise 365 overrides for manual overtime, separate payouts, and projected tax', function () {
    $result = engine()->compute(
        baseCompany([
            'name' => 'Enterprise 365',
            'client_code' => 'enterprise-365',
            'prepared_by' => ['enterprise365.preparer'],
            'approvers' => ['enterprise365.approver'],
            'administrators' => ['enterprise365.admin'],
        ]),
        baseEmployee([
            'employee_number' => 'EMP-002',
            'full_name' => 'Mark Dela Cruz',
            'monthly_basic_salary' => 40000,
            'daily_rate' => 2000,
            'representation' => 3000,
            'allowances' => 1500,
            'projected_annual_taxable_income' => 520000,
        ]),
        [
            'period' => [
                'key' => '2026-04-B',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-30',
            ],
            'manual_overtime_pay' => 1200,
            'adjustments' => [
                [
                    'label' => 'Enterprise 365 Taxable Adjustment',
                    'amount' => 800,
                    'taxable' => true,
                ],
            ],
            'loan_deductions' => [
                [
                    'label' => 'Salary Loan',
                    'amount' => 500,
                ],
            ],
        ],
    );

    expect($result->period->releaseDate->toDateString())->toBe('2026-04-29')
        ->and(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(2000.00)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(250.00)
        ->and($result->rates->fixedPerDayApplied)->toBeTrue()
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(22000.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(18137.50)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(22637.50)
        ->and($result->separatePayouts)->toHaveCount(2)
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(0.00);
});

it('ignores employee fixed daily rate when the company does not enable fixed-per-day pricing', function () {
    $result = engine()->compute(
        baseCompany([
            'fixed_per_day_rate' => false,
            'eemr_factor' => 300,
        ]),
        baseEmployee([
            'monthly_basic_salary' => 30000,
            'daily_rate' => 2000,
        ]),
        [
            'period' => [
                'key' => '2026-04-FIXED-CHECK',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(1200.00)
        ->and(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(150.00)
        ->and($result->rates->fixedPerDayApplied)->toBeFalse();
});

it('supports split-per-cutoff pagibig mode even when general statutory splitting is disabled', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => false,
            'pagibig_mode' => 'split_per_cutoff',
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-SPLIT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect($result->employeeContributions[2]->label)->toBe('Pag-IBIG Contribution')
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(50.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('supports upgraded voluntary pagibig contribution mode', function () {
    $result = engine()->compute(
        baseCompany([
            'pagibig_mode' => 'upgraded_voluntary',
        ]),
        baseEmployee([
            'upgraded_pagibig_contribution' => 3000,
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-UPGRADE',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(1500.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('keeps pagibig loan amortization separate when loan-amortization-separated mode is enabled', function () {
    $result = engine()->compute(
        baseCompany([
            'pagibig_mode' => 'loan_amortization_separated',
        ]),
        baseEmployee([
            'upgraded_pagibig_contribution' => 3000,
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-LOAN',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'pagibig_loan_amortization' => 1400,
        ],
    );

    $separatedLoanDeductions = array_values(array_filter(
        $result->deductions,
        static fn ($line) => $line->label === 'Pag-IBIG Loan Amortization' && MoneyHelper::toFloat($line->amount) === 1400.00,
    ));

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(1500.00)
        ->and($separatedLoanDeductions)->toHaveCount(1);
});

it('lets a monthly pagibig employee defer deduction until the monthly due run even when company statutory defaults split', function () {
    $firstCutoff = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    $secondCutoff = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-B',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-30',
                'release_date' => '2026-04-30',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($firstCutoff->employeeContributions[2]->amount))->toBe(0.00)
        ->and(MoneyHelper::toFloat($firstCutoff->employerContributions[2]->amount))->toBe(0.00)
        ->and(MoneyHelper::toFloat($secondCutoff->employeeContributions[2]->amount))->toBe(100.00)
        ->and(MoneyHelper::toFloat($secondCutoff->employerContributions[2]->amount))->toBe(100.00);
});

it('lets an employee split pagibig by cutoff even when the company default is monthly', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => false,
            'pagibig_schedule' => 'monthly',
        ]),
        baseEmployee([
            'pagibig_schedule' => 'split_per_cutoff',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-EMPLOYEE-SPLIT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(50.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(50.00);
});

it('allows payroll input to explicitly mark a monthly pagibig deduction as due for the current run', function () {
    $result = engine()->compute(
        baseCompany([
            'split_monthly_statutory_across_periods' => true,
        ]),
        baseEmployee([
            'pagibig_schedule' => 'monthly',
        ]),
        [
            'period' => [
                'key' => '2026-04-PAGIBIG-MONTHLY-OVERRIDE',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
            'pagibig_due_this_run' => true,
        ],
    );

    expect(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(100.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(100.00);
});

it('computes special payroll bonus tax using the employee tax shield override', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-003',
            'full_name' => 'Leah Reyes',
            'projected_annual_taxable_income' => 600000,
            'tax_shield_amount_for_bonuses' => 70000,
        ]),
        [
            'period' => [
                'key' => '2026-BONUS',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-01',
                'release_date' => '2026-12-05',
                'run_type' => 'special',
            ],
            'bonus_amount' => 120000,
            'used_annual_bonus_shield' => 20000,
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(0.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(120000.00)
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(14000.00)
        ->and($result->employeeContributions)->toBeEmpty()
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(106000.00)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(106000.00);
});

it('runs payroll from laravel models and enforces processed state before files and payslips', function () {
    $company = testPayrollModel(baseCompany(['name' => 'Workflow Client']));

    $activeEmployee = testPayrollModel(baseEmployee([
        'employee_number' => 'EMP-004',
        'full_name' => 'Paolo Ramos',
        'email' => 'paolo@example.com',
        'monthly_basic_salary' => 25000,
    ]));

    $inactiveEmployee = testPayrollModel(baseEmployee([
        'employee_number' => 'EMP-005',
        'full_name' => 'Inactive User',
        'employment_status' => 'inactive',
        'monthly_basic_salary' => 25000,
        'date_resigned' => '2026-03-31',
    ]));

    $run = engine()->run(
        $company,
        [
            'key' => '2026-04-A',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'release_date' => '2026-04-15',
        ],
        [
            [
                'employee' => $activeEmployee,
                'input' => [
                    'adjustments' => [
                        [
                            'label' => 'Attendance Incentive',
                            'amount' => 1000,
                            'taxable' => true,
                        ],
                    ],
                ],
            ],
            [
                'employee' => $inactiveEmployee,
                'input' => [],
            ],
        ],
    );

    expect(fn () => engine()->generatePayrollFiles($run))
        ->toThrow(InvalidPayrollData::class, 'processed');

    $run->prepare('payroll.preparer', CarbonImmutable::parse('2026-04-10'))
        ->approve('chief.approver', CarbonImmutable::parse('2026-04-11'))
        ->process('payroll.preparer', CarbonImmutable::parse('2026-04-12'));

    expect(fn () => engine()->generatePayslips($run, CarbonImmutable::parse('2026-04-14')))
        ->toThrow(InvalidPayrollData::class, 'on or after');

    $register = engine()->generatePayrollFiles($run);
    $payslips = engine()->generatePayslips($run, CarbonImmutable::parse('2026-04-15'));

    $run->release('treasury.release', CarbonImmutable::parse('2026-04-15'));

    expect($run->results)->toHaveCount(1)
        ->and($run->status)->toBe(PayrollRunStatus::Released)
        ->and($run->auditTrail)->toHaveCount(4)
        ->and($register)->toHaveCount(1)
        ->and($register[0]['employee_number'])->toBe('EMP-004')
        ->and($register[0]['account_number'])->toBe('001234567890')
        ->and($payslips)->toHaveCount(1)
        ->and($payslips[0]['employee']['full_name'])->toBe('Paolo Ramos')
        ->and($payslips[0]['company']['name'])->toBe('Workflow Client');
});

it('generates payslips in batches of 100 employees across 10 payroll batches', function () {
    $engine = engine();
    $company = baseCompany([
        'name' => 'Batch Payslip Client',
    ]);
    $employeesPerBatch = 100;
    $batchCount = 10;
    $allPayslips = [];

    for ($batchNumber = 1; $batchNumber <= $batchCount; $batchNumber++) {
        $items = [];

        for ($employeeNumber = 1; $employeeNumber <= $employeesPerBatch; $employeeNumber++) {
            $sequence = (($batchNumber - 1) * $employeesPerBatch) + $employeeNumber;
            $items[] = [
                'employee' => baseEmployee([
                    'employee_number' => sprintf('EMP-B%02d-%03d', $batchNumber, $employeeNumber),
                    'full_name' => sprintf('Batch %02d Employee %03d', $batchNumber, $employeeNumber),
                    'email' => sprintf('batch%02d.employee%03d@example.com', $batchNumber, $employeeNumber),
                    'monthly_basic_salary' => 25000 + $sequence,
                ]),
                'input' => [
                    'adjustments' => [
                        [
                            'label' => 'Attendance Incentive',
                            'amount' => 500,
                            'taxable' => true,
                        ],
                    ],
                ],
            ];
        }

        $releaseDate = CarbonImmutable::create(2026, 5, 15)->addMonths($batchNumber - 1);
        $run = $engine->run(
            $company,
            [
                'key' => sprintf('2026-BATCH-%02d', $batchNumber),
                'start_date' => $releaseDate->startOfMonth()->toDateString(),
                'end_date' => $releaseDate->startOfMonth()->addDays(14)->toDateString(),
                'release_date' => $releaseDate->toDateString(),
            ],
            $items,
        );

        $run->prepare('payroll.preparer', $releaseDate->subDays(5))
            ->approve('chief.approver', $releaseDate->subDays(4))
            ->process('payroll.preparer', $releaseDate->subDays(3));

        $payslips = $engine->generatePayslips($run, $releaseDate);

        expect($run->results)->toHaveCount($employeesPerBatch)
            ->and($payslips)->toHaveCount($employeesPerBatch)
            ->and($payslips[0]['period']['key'])->toBe(sprintf('2026-BATCH-%02d', $batchNumber))
            ->and($payslips[0]['company']['name'])->toBe('Batch Payslip Client')
            ->and($payslips[0]['employee']['employee_number'])->toBe(sprintf('EMP-B%02d-001', $batchNumber))
            ->and($payslips[$employeesPerBatch - 1]['employee']['employee_number'])->toBe(sprintf('EMP-B%02d-100', $batchNumber));

        $allPayslips = [...$allPayslips, ...$payslips];
    }

    $employeeNumbers = array_map(
        static fn (array $payslip): string => $payslip['employee']['employee_number'],
        $allPayslips,
    );

    expect($allPayslips)->toHaveCount($batchCount * $employeesPerBatch)
        ->and(array_unique($employeeNumbers))->toHaveCount($batchCount * $employeesPerBatch);
});

it('computes fixed salary payroll for monthly employees', function () {
    $result = engine()->compute(
        baseCompany([
            'frequency' => 'monthly',
            'payroll_schedules' => [
                [
                    'pay_date' => '31',
                    'period_start' => '1',
                    'period_end' => '31',
                ],
            ],
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-05',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-31',
                'release_date' => '2026-05-31',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(30000.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(30000.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(27650.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(26627.50)
        ->and(MoneyHelper::toFloat($result->employeeContributions[0]->amount))->toBe(1500.00)
        ->and(MoneyHelper::toFloat($result->employeeContributions[1]->amount))->toBe(750.00)
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(100.00);
});

it('computes fixed salary payroll for semi-monthly employees', function () {
    $result = engine()->compute(
        baseCompany([
            'frequency' => 'semi_monthly',
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-05-A',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-15',
                'release_date' => '2026-05-15',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(15000.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(15000.00)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(13825.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(13313.75)
        ->and(MoneyHelper::toFloat($result->employeeContributions[0]->amount))->toBe(750.00)
        ->and(MoneyHelper::toFloat($result->employeeContributions[1]->amount))->toBe(375.00)
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(50.00);
});

it('computes fixed salary payroll for weekly employees', function () {
    $result = engine()->compute(
        baseCompany([
            'frequency' => 'weekly',
            'payroll_schedules' => [
                [
                    'pay_date' => '07',
                    'period_start' => '1',
                    'period_end' => '7',
                ],
            ],
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-W1',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-07',
                'release_date' => '2026-05-07',
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(6923.08)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(6923.08)
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(6335.58)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(6106.40)
        ->and(MoneyHelper::toFloat($result->employeeContributions[0]->amount))->toBe(375.00)
        ->and(MoneyHelper::toFloat($result->employeeContributions[1]->amount))->toBe(187.50)
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(25.00);
});

it('computes daily-rated payroll with daily rate absences holidays and rest day work', function () {
    $result = engine()->compute(
        baseCompany([
            'fixed_per_day_rate' => true,
        ]),
        baseEmployee([
            'employee_number' => 'EMP-DAILY',
            'full_name' => 'Daily Rated Employee',
            'monthly_basic_salary' => 22000,
            'daily_rate' => 1000,
        ]),
        [
            'period' => [
                'key' => '2026-06-DAILY',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'absence_deduction' => 2000,
            'adjustments' => [
                [
                    'label' => 'Holiday Pay',
                    'amount' => 1000,
                    'taxable' => true,
                ],
                [
                    'label' => 'Rest Day Work',
                    'amount' => 1500,
                    'taxable' => true,
                ],
            ],
        ],
    );

    $earningLabels = array_map(static fn ($line) => $line->label, $result->earnings);
    $deductionLabels = array_map(static fn ($line) => $line->label, $result->deductions);

    expect(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(1000.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(13500.00)
        ->and($earningLabels)->toBe(['Basic Pay', 'Holiday Pay', 'Rest Day Work'])
        ->and($deductionLabels)->toBe(['Absence Deduction', 'Withholding Tax'])
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(10293.75);
});

it('computes hourly payroll with regular hours overtime night differential and undertime', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-HOURLY',
            'full_name' => 'Hourly Employee',
            'monthly_basic_salary' => 18000,
            'hourly_rate' => 200,
        ]),
        [
            'period' => [
                'key' => '2026-06-HOURLY',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'undertime_deduction' => 300,
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 3,
                ],
                [
                    'type' => 'night_differential',
                    'hours' => 4,
                ],
            ],
        ],
    );

    $earningLabels = array_map(static fn ($line) => $line->label, $result->earnings);
    $deductionLabels = array_map(static fn ($line) => $line->label, $result->deductions);

    expect(MoneyHelper::toFloat($result->rates->hourlyRate))->toBe(200.00)
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(9830.00)
        ->and($earningLabels)->toBe(['Basic Pay', 'Overtime Pay', 'Night Differential'])
        ->and($deductionLabels)->toBe(['Undertime Deduction'])
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(8805.00);
});

it('computes core earning components in one payroll scenario', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-EARN',
            'full_name' => 'Earnings Employee',
            'representation' => 2000,
            'allowances' => 1000,
        ]),
        [
            'period' => [
                'key' => '2026-06-EARN',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'bonus_amount' => 20000,
            'adjustments' => [
                [
                    'label' => 'Holiday Pay',
                    'amount' => 1200,
                    'taxable' => true,
                ],
                [
                    'label' => 'Incentive',
                    'amount' => 800,
                    'taxable' => true,
                ],
                [
                    'label' => 'Adjustment',
                    'amount' => 500,
                    'taxable' => true,
                ],
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 2,
                ],
                [
                    'type' => 'night_differential',
                    'hours' => 4,
                ],
            ],
        ],
    );

    $earnings = [];

    foreach ($result->earnings as $line) {
        $earnings[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    expect($earnings)->toMatchArray([
        'Basic Pay' => 15000.00,
        'Representation Allowance' => 2000.00,
        'Allowance' => 1000.00,
        'Holiday Pay' => 1200.00,
        'Incentive' => 800.00,
        'Adjustment' => 500.00,
        'Overtime Pay' => 359.43,
        'Night Differential' => 57.51,
        'Bonus' => 20000.00,
    ])
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(40916.94)
        ->and(MoneyHelper::toFloat($result->bonusTaxWithheld))->toBe(0.00);
});

it('computes built-in deduction components in one payroll scenario', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-DED',
            'full_name' => 'Deduction Employee',
            'monthly_basic_salary' => 40000,
        ]),
        [
            'period' => [
                'key' => '2026-06-DED',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'loan_deductions' => [
                [
                    'label' => 'Salary Loan',
                    'amount' => 1200,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Cash Advance',
                    'amount' => 600,
                ],
                [
                    'label' => 'Penalty',
                    'amount' => 300,
                ],
                [
                    'label' => 'Other Recurring Deduction',
                    'amount' => 450,
                ],
            ],
        ],
    );

    $deductions = [];
    $employeeShares = [];
    $employerShares = [];

    foreach ($result->deductions as $line) {
        $deductions[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    foreach ($result->employeeContributions as $line) {
        $employeeShares[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    foreach ($result->employerContributions as $line) {
        $employerShares[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    expect($deductions)->toMatchArray([
        'Salary Loan' => 1200.00,
        'Cash Advance' => 600.00,
        'Penalty' => 300.00,
        'Other Recurring Deduction' => 450.00,
        'Withholding Tax' => 1319.17,
    ])
        ->and($employeeShares)->toMatchArray([
            'SSS Contribution' => 875.00,
            'PhilHealth Contribution' => 500.00,
            'Pag-IBIG Contribution' => 50.00,
        ])
        ->and($employerShares)->toMatchArray([
            'Employer SSS Contribution' => 1765.00,
            'Employer PhilHealth Contribution' => 500.00,
            'Employer Pag-IBIG Contribution' => 50.00,
        ])
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(14705.83);
});

it('applies attendance-based adjustments from timekeeping inputs', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-ATT',
            'full_name' => 'Attendance Employee',
        ]),
        [
            'period' => [
                'key' => '2026-06-ATT',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'absence_deduction' => 1000,
            'late_deduction' => 250,
            'undertime_deduction' => 300,
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 4,
                ],
            ],
        ],
    );

    $deductions = [];

    foreach ($result->deductions as $line) {
        $deductions[$line->label] = MoneyHelper::toFloat($line->amount);
    }

    expect(MoneyHelper::toFloat($result->grossPay))->toBe(15718.85)
        ->and($deductions)->toMatchArray([
            'Absence Deduction' => 1000.00,
            'Late Deduction' => 250.00,
            'Undertime Deduction' => 300.00,
            'Withholding Tax' => 619.08,
        ])
        ->and($result->earnings[1]->label)->toBe('Overtime Pay')
        ->and(MoneyHelper::toFloat($result->earnings[1]->amount))->toBe(718.85);
});

it('produces consistent final payable totals for gross earnings deductions shares and net pay', function () {
    $result = engine()->compute(
        baseCompany(),
        baseEmployee([
            'employee_number' => 'EMP-FINAL',
            'full_name' => 'Final Payable Employee',
            'representation' => 1500,
            'allowances' => 500,
        ]),
        [
            'period' => [
                'key' => '2026-06-FINAL',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-15',
                'release_date' => '2026-06-15',
            ],
            'adjustments' => [
                [
                    'label' => 'Taxable Adjustment',
                    'amount' => 1000,
                    'taxable' => true,
                ],
            ],
            'manual_deductions' => [
                [
                    'label' => 'Union Dues',
                    'amount' => 250,
                ],
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 2,
                ],
            ],
        ],
    );

    $grossFromEarnings = MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->earnings));
    $deductionsTotal = MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->deductions));
    $employeeShare = MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->employeeContributions));
    $employerShare = MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->employerContributions));
    $taxableFromLines = MoneyHelper::max(
        MoneyHelper::sum(array_map(
            static fn ($line) => $line->taxable ? $line->amount : MoneyHelper::zero($line->amount),
            $result->earnings
        ))->subtract($employeeShare),
        MoneyHelper::zero($result->grossPay),
    );
    $netFromLines = $grossFromEarnings->subtract($employeeShare)->subtract($deductionsTotal);

    expect(MoneyHelper::toFloat($result->grossPay))->toBe(MoneyHelper::toFloat($grossFromEarnings))
        ->and(MoneyHelper::toFloat($result->taxableIncome))->toBe(MoneyHelper::toFloat($taxableFromLines))
        ->and(MoneyHelper::toFloat(MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->employeeContributions))))->toBe(MoneyHelper::toFloat($employeeShare))
        ->and(MoneyHelper::toFloat(MoneyHelper::sum(array_map(static fn ($line) => $line->amount, $result->employerContributions))))->toBe(MoneyHelper::toFloat($employerShare))
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(MoneyHelper::toFloat($netFromLines));
});

it('rejects incomplete employee setup that violates required capability fields', function () {
    expect(fn () => engine()->compute(
        baseCompany(),
        baseEmployee([
            'email' => null,
        ]),
        [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    ))->toThrow(InvalidPayrollData::class, 'email address');
});

it('rejects invalid payroll setup and workflow dates', function () {
    expect(fn () => engine()->compute(
        baseCompany([
            'prepared_by' => ['p1', 'p2', 'p3', 'p4', 'p5', 'p6'],
        ]),
        baseEmployee(),
        [
            'period' => [
                'key' => '2026-04-A',
                'start_date' => '2026-04-16',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    ))->toThrow(InvalidPayrollData::class);
});
