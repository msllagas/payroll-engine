<?php

namespace QuillBytes\PayrollEngine\Tests;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator as PagIbigContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow as PayrollWorkflowContract;
use QuillBytes\PayrollEngine\Contracts\PhilHealthContributionCalculator as PhilHealthContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\SssContributionCalculator as SssContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PagIbigContributionResult;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PayrollPeriod;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Data\PhilHealthContributionResult;
use QuillBytes\PayrollEngine\Data\RateSnapshot;
use QuillBytes\PayrollEngine\Data\SssContributionResult;
use QuillBytes\PayrollEngine\PayrollEngine;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

function clientSpecificRateCalculator(): RateCalculatorContract
{
    return new class implements RateCalculatorContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollPeriod $period): RateSnapshot
        {
            return new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: MoneyHelper::fromNumeric(1000),
                dailyRate: MoneyHelper::fromNumeric(500),
                hourlyRate: MoneyHelper::fromNumeric(100),
                fixedPerDayApplied: true,
            );
        }
    };
}

function clientSpecificOvertimeCalculator(): OvertimeCalculatorContract
{
    return new class implements OvertimeCalculatorContract
    {
        public function calculate(CompanyProfile $company, PayrollInput $input, RateSnapshot $rates): array
        {
            return [
                new PayrollLine('earning', 'Client Overtime', MoneyHelper::fromNumeric(200), true, ['strategy' => 'custom']),
            ];
        }
    };
}

function clientSpecificWithholdingCalculator(): WithholdingTaxCalculatorContract
{
    return new class implements WithholdingTaxCalculatorContract
    {
        public function calculateRegular(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            Money $taxableIncomeAfterMandatory,
        ): PayrollLine {
            return new PayrollLine('deduction', 'Client Withholding', MoneyHelper::fromNumeric(50));
        }

        public function calculateBonusTax(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            Money $projectedAnnualTaxableIncome,
        ): PayrollLine {
            return new PayrollLine('deduction', 'Client Bonus Tax', MoneyHelper::zero($projectedAnnualTaxableIncome));
        }

        public function annualTax(Money $annualTaxableIncome): Money
        {
            return MoneyHelper::fromNumeric(50, $annualTaxableIncome);
        }
    };
}

function clientSpecificWorkflow(): PayrollWorkflowContract
{
    return new class implements PayrollWorkflowContract
    {
        public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
        {
            $grossPay = MoneyHelper::fromNumeric(777, $employee->compensation->monthlyBasicSalary);
            $rates = new RateSnapshot(
                monthlyBasicSalary: $employee->compensation->monthlyBasicSalary,
                scheduledBasicPay: $grossPay,
                dailyRate: MoneyHelper::fromNumeric(250, $grossPay),
                hourlyRate: MoneyHelper::fromNumeric(31.25, $grossPay),
                fixedPerDayApplied: true,
            );
            $earning = new PayrollLine('earning', 'Workflow Gross Pay', $grossPay, true, ['workflow' => 'client-specific']);

            return new PayrollResult(
                company: $company,
                employee: $employee,
                period: $input->period,
                rates: $rates,
                earnings: [$earning],
                deductions: [],
                employeeContributions: [],
                employerContributions: [],
                separatePayouts: [],
                grossPay: $grossPay,
                taxableIncome: $grossPay,
                netPay: $grossPay,
                takeHomePay: $grossPay,
                bonusTaxWithheld: MoneyHelper::zero($grossPay),
            );
        }
    };
}

function clientSpecificPagIbigCalculator(): PagIbigContributionCalculatorContract
{
    return new class implements PagIbigContributionCalculatorContract
    {
        public function calculate(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            int $periodDivisor = 1,
        ): PagIbigContributionResult {
            return new PagIbigContributionResult(
                employee: new PayrollLine('employee_contribution', 'Client Pag-IBIG Contribution', MoneyHelper::fromNumeric(333)),
                employer: new PayrollLine('employer_contribution', 'Client Employer Pag-IBIG Contribution', MoneyHelper::fromNumeric(111)),
                separateDeductions: [
                    new PayrollLine('deduction', 'Client Pag-IBIG Loan', MoneyHelper::fromNumeric(75)),
                ],
            );
        }
    };
}

function clientSpecificSssCalculator(): SssContributionCalculatorContract
{
    return new class implements SssContributionCalculatorContract
    {
        public function calculate(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            int $periodDivisor = 1,
        ): SssContributionResult {
            return new SssContributionResult(
                employee: new PayrollLine('employee_contribution', 'Client SSS Contribution', MoneyHelper::fromNumeric(444)),
                employer: new PayrollLine('employer_contribution', 'Client Employer SSS Contribution', MoneyHelper::fromNumeric(222)),
            );
        }
    };
}

function clientSpecificPhilHealthCalculator(): PhilHealthContributionCalculatorContract
{
    return new class implements PhilHealthContributionCalculatorContract
    {
        public function calculate(
            CompanyProfile $company,
            EmployeeProfile $employee,
            PayrollInput $input,
            int $periodDivisor = 1,
        ): PhilHealthContributionResult {
            return new PhilHealthContributionResult(
                employee: new PayrollLine('employee_contribution', 'Client PhilHealth Contribution', MoneyHelper::fromNumeric(555)),
                employer: new PayrollLine('employer_contribution', 'Client Employer PhilHealth Contribution', MoneyHelper::fromNumeric(333)),
            );
        }
    };
}

function strategyEngine(array $config = []): PayrollEngine
{
    return new PayrollEngine($config);
}

/**
 * @return array<string, mixed>
 */
function strategyCompany(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Strategy Client',
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
        ],
    ], $overrides);
}

/**
 * @return array<string, mixed>
 */
function strategyEmployee(array $overrides = []): array
{
    return array_replace_recursive([
        'employee_number' => 'EMP-201',
        'full_name' => 'Custom Strategy User',
        'employment_status' => 'active',
        'date_hired' => '2024-01-10',
        'department' => 'Finance',
        'email' => 'custom.strategy@example.com',
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

it('allows client-specific rate overtime and withholding strategies without changing the core workflow', function () {
    $result = strategyEngine([
        'strategies' => [
            'clients' => [
                'flex-client' => [
                    'rate' => clientSpecificRateCalculator(),
                    'overtime' => clientSpecificOvertimeCalculator(),
                    'withholding' => clientSpecificWithholdingCalculator(),
                ],
            ],
        ],
    ])->compute(
        strategyCompany([
            'client_code' => 'flex-client',
        ]),
        strategyEmployee(),
        [
            'period' => [
                'key' => '2026-FLEX',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-01',
                'release_date' => '2026-04-01',
                'run_type' => 'special',
            ],
            'overtime' => [
                [
                    'type' => 'regular',
                    'hours' => 2,
                ],
            ],
        ],
    );

    expect(MoneyHelper::toFloat($result->rates->scheduledBasicPay))->toBe(1000.00)
        ->and(MoneyHelper::toFloat($result->rates->dailyRate))->toBe(500.00)
        ->and($result->earnings)->toHaveCount(2)
        ->and($result->earnings[1]->label)->toBe('Client Overtime')
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(1200.00)
        ->and($result->deductions)->toHaveCount(1)
        ->and($result->deductions[0]->label)->toBe('Client Withholding')
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(1150.00);
});

it('allows a client to replace the entire payroll workflow through configuration', function () {
    $result = strategyEngine([
        'strategies' => [
            'clients' => [
                'workflow-client' => [
                    'workflow' => clientSpecificWorkflow(),
                ],
            ],
        ],
    ])->compute(
        strategyCompany([
            'client_code' => 'workflow-client',
        ]),
        strategyEmployee(),
        [
            'period' => [
                'key' => '2026-WORKFLOW',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect($result->earnings)->toHaveCount(1)
        ->and($result->earnings[0]->label)->toBe('Workflow Gross Pay')
        ->and($result->earnings[0]->metadata['workflow'])->toBe('client-specific')
        ->and(MoneyHelper::toFloat($result->grossPay))->toBe(777.00)
        ->and(MoneyHelper::toFloat($result->netPay))->toBe(777.00)
        ->and(MoneyHelper::toFloat($result->takeHomePay))->toBe(777.00);
});

it('allows a client to replace only the pagibig strategy through configuration', function () {
    $result = strategyEngine([
        'strategies' => [
            'clients' => [
                'pagibig-client' => [
                    'pagibig' => clientSpecificPagIbigCalculator(),
                ],
            ],
        ],
    ])->compute(
        strategyCompany([
            'client_code' => 'pagibig-client',
        ]),
        strategyEmployee(),
        [
            'period' => [
                'key' => '2026-PAGIBIG-CLIENT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    $clientPagIbigLoans = array_values(array_filter(
        $result->deductions,
        static fn ($line) => $line->label === 'Client Pag-IBIG Loan' && MoneyHelper::toFloat($line->amount) === 75.00,
    ));

    expect($result->employeeContributions[2]->label)->toBe('Client Pag-IBIG Contribution')
        ->and(MoneyHelper::toFloat($result->employeeContributions[2]->amount))->toBe(333.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[2]->amount))->toBe(111.00)
        ->and($clientPagIbigLoans)->toHaveCount(1);
});

it('allows a client to replace sss and philhealth strategies through configuration', function () {
    $result = strategyEngine([
        'strategies' => [
            'clients' => [
                'statutory-client' => [
                    'sss' => clientSpecificSssCalculator(),
                    'philhealth' => clientSpecificPhilHealthCalculator(),
                ],
            ],
        ],
    ])->compute(
        strategyCompany([
            'client_code' => 'statutory-client',
        ]),
        strategyEmployee(),
        [
            'period' => [
                'key' => '2026-STATUTORY-CLIENT',
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-15',
                'release_date' => '2026-04-15',
            ],
        ],
    );

    expect($result->employeeContributions[0]->label)->toBe('Client SSS Contribution')
        ->and(MoneyHelper::toFloat($result->employeeContributions[0]->amount))->toBe(444.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[0]->amount))->toBe(222.00)
        ->and($result->employeeContributions[1]->label)->toBe('Client PhilHealth Contribution')
        ->and(MoneyHelper::toFloat($result->employeeContributions[1]->amount))->toBe(555.00)
        ->and(MoneyHelper::toFloat($result->employerContributions[1]->amount))->toBe(333.00);
});
