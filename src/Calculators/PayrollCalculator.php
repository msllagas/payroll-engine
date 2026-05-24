<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator as PagIbigContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow;
use QuillBytes\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\LoanDeduction;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PayrollResult;
use QuillBytes\PayrollEngine\Enums\PayrollFrequency;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

final readonly class PayrollCalculator implements PayrollWorkflow
{
    public function __construct(
        private RateCalculatorContract $rateCalculator,
        private OvertimeCalculatorContract $overtimeCalculator,
        private VariableEarningCalculatorContract $variableEarningCalculator,
        private SssContributionCalculator $sssCalculator,
        private PhilHealthContributionCalculator $philHealthCalculator,
        private PagIbigContributionCalculatorContract $pagIbigCalculator,
        private WithholdingTaxCalculatorContract $withholdingTaxCalculator,
    ) {}

    public function calculate(CompanyProfile $company, EmployeeProfile $employee, PayrollInput $input): PayrollResult
    {
        $runType = $input->period->normalizedRunType();
        $rates = $this->rateCalculator->calculate($company, $employee, $input->period);
        $earnings = [];
        $separatePayouts = [];

        if (! $rates->scheduledBasicPay->isZero()) {
            $earnings[] = new PayrollLine(
                'earning',
                'Basic Pay',
                $rates->scheduledBasicPay,
                true,
                TraceMetadata::line(
                    source: 'rate_calculator',
                    appliedRule: 'scheduled_basic_pay',
                    formula: $runType->isFinalSettlement()
                        ? 'regular_scheduled_basic_pay * payable_days / covered_days'
                        : 'monthly_basic_salary * 12 / periods_per_year',
                    basis: [
                        'monthly_basic_salary' => $rates->monthlyBasicSalary,
                        'periods_per_year' => $company->periodsPerYear(),
                    ],
                ),
            );
        }

        if ($runType->usesRegularAllowances()) {
            $this->appendAllowanceLine($company, $earnings, $separatePayouts, 'Representation Allowance', $employee->compensation->representationAllowance);
            $this->appendAllowanceLine($company, $earnings, $separatePayouts, 'Allowance', $employee->compensation->otherAllowances);
        }

        foreach ($input->adjustments as $adjustment) {
            $line = new PayrollLine(
                type: $adjustment->separatePayout ? 'separate_payout' : 'earning',
                label: $adjustment->label,
                amount: $adjustment->amount,
                taxable: $adjustment->taxable,
                metadata: TraceMetadata::line(
                    source: 'payroll_input.adjustments',
                    appliedRule: 'manual_adjustment',
                    formula: 'input amount',
                    basis: [
                        'amount' => $adjustment->amount,
                    ],
                    extra: [
                        'separate_payout' => $adjustment->separatePayout,
                    ],
                ),
            );

            if ($line->type === 'separate_payout') {
                $separatePayouts[] = $line;

                continue;
            }

            $earnings[] = $line;
        }

        foreach ($this->overtimeCalculator->calculate($company, $input, $rates) as $line) {
            $earnings[] = $line;
        }

        foreach ($this->variableEarningCalculator->calculate($company, $employee, $input, $rates) as $line) {
            $earnings[] = $line;
        }

        if (! $input->bonus->isZero()) {
            $earnings[] = new PayrollLine(
                type: 'earning',
                label: 'Bonus',
                amount: $input->bonus,
                taxable: true,
                metadata: TraceMetadata::line(
                    source: 'payroll_input.bonus',
                    appliedRule: 'bonus_amount',
                    formula: 'input amount',
                    basis: [
                        'bonus_amount' => $input->bonus,
                        'used_annual_bonus_shield' => $input->usedAnnualBonusShield,
                    ],
                ),
            );
        }

        $employeeContributions = [];
        $employerContributions = [];

        if ($runType->usesMandatoryContributions()) {
            $periodDivisor = $this->statutoryPeriodDivisor($company);
            $sss = $this->sssCalculator->calculate($employee->compensation->monthlyBasicSalary, $employee->statutory->manualSssContribution, $periodDivisor);
            $philHealth = $this->philHealthCalculator->calculate($employee->compensation->monthlyBasicSalary, $employee->statutory->manualPhilHealthContribution, $periodDivisor);
            $pagIbig = $this->pagIbigCalculator->calculate($company, $employee, $input, $periodDivisor);

            $employeeContributions = [$sss['employee'], $philHealth['employee'], $pagIbig->employee];
            $employerContributions = [$sss['employer'], $philHealth['employer'], $pagIbig->employer];
        }

        $deductions = array_map(
            static fn ($deduction) => new PayrollLine(
                'deduction',
                $deduction->label,
                $deduction->amount,
                false,
                TraceMetadata::line(
                    source: $deduction instanceof LoanDeduction ? 'payroll_input.loan_deductions' : 'payroll_input.manual_deductions',
                    appliedRule: $deduction instanceof LoanDeduction ? 'loan_deduction' : 'manual_deduction',
                    formula: 'input amount',
                    basis: [
                        'amount' => $deduction->amount,
                    ],
                    extra: $deduction instanceof LoanDeduction
                        ? ['loan_reference' => $deduction->loanReference]
                        : [],
                ),
            ),
            [
                ...$input->loanDeductions,
                ...$input->manualDeductions,
            ]
        );

        if (isset($pagIbig) && $pagIbig->separateDeductions !== []) {
            foreach ($pagIbig->separateDeductions as $line) {
                $deductions[] = $line;
            }
        }

        foreach ([
            ['Leave Deduction', $input->leaveDeduction],
            ['Absence Deduction', $input->absenceDeduction],
            ['Late Deduction', $input->lateDeduction],
            ['Undertime Deduction', $input->undertimeDeduction],
        ] as [$label, $amount]) {
            if (! $amount->isZero()) {
                $deductions[] = new PayrollLine(
                    'deduction',
                    $label,
                    $amount,
                    false,
                    TraceMetadata::line(
                        source: 'payroll_input.attendance_adjustments',
                        appliedRule: strtolower(str_replace(' ', '_', $label)),
                        formula: 'input amount',
                        basis: [
                            'amount' => $amount,
                        ],
                    ),
                );
            }
        }

        $taxableIncome = MoneyHelper::sum(array_map(
            static fn (PayrollLine $line) => $line->taxable ? $line->amount : MoneyHelper::zero(),
            $earnings
        ))->subtract(MoneyHelper::sum(array_map(
            static fn (PayrollLine $line) => $line->amount,
            $employeeContributions
        )));
        $taxableIncome = MoneyHelper::max($taxableIncome, MoneyHelper::zero());

        $withholdingTax = $this->withholdingTaxCalculator->calculateRegular($company, $employee, $input, $taxableIncome);
        $bonusTax = $this->withholdingTaxCalculator->calculateBonusTax(
            $company,
            $employee,
            $input,
            $input->projectedAnnualTaxableIncome
                ?? $employee->compensation->projectedAnnualTaxableIncome
                ?? MoneyHelper::multiply($taxableIncome, $company->periodsPerYear())
        );

        if (! $withholdingTax->amount->isZero()) {
            $deductions[] = $withholdingTax;
        }

        if (! $bonusTax->amount->isZero()) {
            $deductions[] = $bonusTax;
        }

        $grossPay = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $earnings));
        $totalEmployeeContributions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $employeeContributions));
        $totalDeductions = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $deductions));
        $separatePayoutTotal = MoneyHelper::sum(array_map(static fn (PayrollLine $line) => $line->amount, $separatePayouts));
        $netPay = $grossPay->subtract($totalEmployeeContributions)->subtract($totalDeductions);
        $takeHomePay = $netPay->add($separatePayoutTotal);

        return new PayrollResult(
            company: $company,
            employee: $employee,
            period: $input->period,
            rates: $rates,
            earnings: $earnings,
            deductions: $deductions,
            employeeContributions: $employeeContributions,
            employerContributions: $employerContributions,
            separatePayouts: $separatePayouts,
            grossPay: $grossPay,
            taxableIncome: $taxableIncome,
            netPay: $netPay,
            takeHomePay: $takeHomePay,
            bonusTaxWithheld: $bonusTax->amount,
        );
    }

    /**
     * @param  array<int, PayrollLine>  $earnings
     * @param  array<int, PayrollLine>  $separatePayouts
     */
    private function appendAllowanceLine(CompanyProfile $company, array &$earnings, array &$separatePayouts, string $label, Money $amount): void
    {
        if ($amount->isZero()) {
            return;
        }

        $line = new PayrollLine(
            type: $company->separateAllowancePayout ? 'separate_payout' : 'earning',
            label: $label,
            amount: $amount,
            taxable: false,
            metadata: TraceMetadata::line(
                source: 'employee_compensation.allowances',
                appliedRule: strtolower(str_replace(' ', '_', $label)),
                formula: 'configured allowance amount',
                basis: [
                    'amount' => $amount,
                    'separate_allowance_payout' => $company->separateAllowancePayout,
                ],
            ),
        );

        if ($line->type === 'separate_payout') {
            $separatePayouts[] = $line;

            return;
        }

        $earnings[] = $line;
    }

    private function statutoryPeriodDivisor(CompanyProfile $company): int
    {
        if (! $company->splitMonthlyStatutoryAcrossPeriods) {
            return 1;
        }

        return ContributionScheduleResolver::cutoffDivisor($company);
    }
}
