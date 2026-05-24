<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator as PagIbigContributionCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PagIbigContributionResult;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionMode;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionSchedule;
use QuillBytes\PayrollEngine\Support\ContributionScheduleResolver;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

/**
 * Default Pag-IBIG contribution calculator for the payroll engine.
 *
 * Responsibility:
 * - compute the employee Pag-IBIG deduction for a payroll run
 * - compute the matching employer Pag-IBIG share
 * - support manual employee contribution overrides when a client provides the
 *   statutory amount from an upstream payroll or HR source
 * - honor either a company default or employee-level Pag-IBIG deduction
 *   schedule so some employees can split across cutoffs while others deduct
 *   once a month inside the same company
 *
 * Default behavior:
 * - the salary base is capped at PHP 5,000
 * - the employee share uses:
 *   1% when the salary base is PHP 1,500 or below
 *   2% when the salary base is above PHP 1,500
 * - the employer share uses 2% of the capped salary base
 * - Pag-IBIG deduction schedule is resolved in this order:
 *   employee override -> company Pag-IBIG schedule -> legacy fallback behavior
 * - monthly schedules deduct the full monthly amount only on the due run
 * - split schedules prorate employee and employer shares evenly across payroll
 *   runs such as semi-monthly periods
 *
 * Use case:
 * - use this calculator for the default Philippine payroll flow where Pag-IBIG
 *   may run in one of the packaged modes:
 *   `standard_mandatory`, `split_per_cutoff`, `upgraded_voluntary`, or
 *   `loan_amortization_separated`
 * - replace this strategy entirely when a client needs a different Pag-IBIG
 *   business flow, remittance rule, or imported external contribution source
 */
final class PagIbigContributionCalculator implements PagIbigContributionCalculatorContract
{
    /**
     * Calculates employee and employer Pag-IBIG results for the current run.
     *
     * Input behavior:
     * - `company->pagIbigContributionMode` controls which packaged mode is used
     * - `employee->statutory->pagIbigContributionSchedule` can override the
     *   company default so different employees in the same company can use monthly
     *   or split Pag-IBIG deduction handling
     * - `employee->statutory->manualPagIbigContribution` overrides the computed
     *   mandatory employee share
     * - `employee->statutory->upgradedPagIbigContribution` supplies an employee
     *   savings amount for upgraded voluntary mode
     * - `input->pagIbigDueThisRun` explicitly marks whether a monthly schedule
     *   should deduct on the current run when the payroll flow cannot be safely
     *   inferred from the dates alone
     * - `input->pagIbigLoanAmortization` is emitted as a separate deduction line
     *   only when the loan-amortization-separated mode is enabled
     * - `periodDivisor` remains the general statutory divisor and is only used
     *   when the effective Pag-IBIG deduction schedule is split
     *
     * Returned values:
     * - `employee`: payroll line for the employee Pag-IBIG deduction
     * - `employer`: payroll line for the employer Pag-IBIG contribution
     * - `separateDeductions`: optional additional deduction lines such as a
     *   separated Pag-IBIG loan amortization
     */
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        int $periodDivisor = 1,
    ): PagIbigContributionResult {
        $effectiveMode = $this->effectiveMode($company->pagIbigContributionMode);
        $schedule = $this->effectiveSchedule($company, $employee);
        $mandatory = $this->mandatoryMonthlyAmounts(
            $employee->compensation->monthlyBasicSalary,
            $employee->statutory->manualPagIbigContribution
        );

        $employeeContribution = match ($effectiveMode) {
            PagIbigContributionMode::UpgradedVoluntary,
            PagIbigContributionMode::LoanAmortizationSeparated => $this->resolveEmployeeMonthlyAmount($employee, $mandatory['employee']),
            default => $mandatory['employee'],
        };
        $employerContribution = $mandatory['employer'];

        $dueThisRun = $input->pagIbigDueThisRun ?? $input->statutoryDueThisRun;
        $employeeContribution = ContributionScheduleResolver::apply($employeeContribution, $company, $input, $schedule->value, $periodDivisor, $dueThisRun);
        $employerContribution = ContributionScheduleResolver::apply($employerContribution, $company, $input, $schedule->value, $periodDivisor, $dueThisRun);

        return new PagIbigContributionResult(
            employee: new PayrollLine(
                'employee_contribution',
                'Pag-IBIG Contribution',
                $employeeContribution,
                false,
                TraceMetadata::line(
                    source: 'pagibig_calculator',
                    appliedRule: 'pagibig_employee_share',
                    formula: $employee->statutory->manualPagIbigContribution !== null
                        ? 'manual employee contribution'
                        : ($effectiveMode === PagIbigContributionMode::UpgradedVoluntary
                            || $effectiveMode === PagIbigContributionMode::LoanAmortizationSeparated
                            ? 'upgraded or mandatory employee contribution, then scheduled by cutoff'
                            : 'mandatory employee contribution, then scheduled by cutoff'),
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'mode' => $effectiveMode->value,
                        'schedule' => $schedule->value,
                        'period_divisor' => $periodDivisor,
                    ],
                ),
            ),
            employer: new PayrollLine(
                'employer_contribution',
                'Employer Pag-IBIG Contribution',
                $employerContribution,
                false,
                TraceMetadata::line(
                    source: 'pagibig_calculator',
                    appliedRule: 'pagibig_employer_share',
                    formula: 'mandatory employer contribution, then scheduled by cutoff',
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'mode' => $effectiveMode->value,
                        'schedule' => $schedule->value,
                        'period_divisor' => $periodDivisor,
                    ],
                ),
            ),
            separateDeductions: $this->separateDeductions($company, $input),
        );
    }

    /**
     * Calculates the standard monthly mandatory Pag-IBIG employee and employer amounts.
     *
     * @return array{employee: Money, employer: Money}
     */
    private function mandatoryMonthlyAmounts(Money $monthlySalary, ?Money $manualEmployeeContribution): array
    {
        $salaryBase = min(5000, max(0, MoneyHelper::toFloat($monthlySalary)));
        $base = MoneyHelper::fromNumeric($salaryBase, $monthlySalary);
        $employeeRate = $salaryBase <= 1500 ? 1 : 2;

        return [
            'employee' => $manualEmployeeContribution ?? MoneyHelper::percentage($base, $employeeRate),
            'employer' => MoneyHelper::percentage($base, 2),
        ];
    }

    /**
     * Resolves the monthly employee savings amount for upgraded or separated modes.
     */
    private function resolveEmployeeMonthlyAmount(EmployeeProfile $employee, Money $mandatoryEmployeeAmount): Money
    {
        return $employee->statutory->upgradedPagIbigContribution
            ?? $employee->statutory->manualPagIbigContribution
            ?? $mandatoryEmployeeAmount;
    }

    /**
     * Resolves the effective contribution mode used for amount computation.
     */
    private function effectiveMode(PagIbigContributionMode $mode): PagIbigContributionMode
    {
        return $mode === PagIbigContributionMode::SplitPerCutoff
            ? PagIbigContributionMode::StandardMandatory
            : $mode;
    }

    /**
     * Resolves whether the current employee should be treated as monthly or split.
     */
    private function effectiveSchedule(CompanyProfile $company, EmployeeProfile $employee): PagIbigContributionSchedule
    {
        if ($employee->statutory->pagIbigContributionSchedule !== null) {
            return $employee->statutory->pagIbigContributionSchedule;
        }

        if ($employee->statutory->statutoryContributionSchedule !== null) {
            return PagIbigContributionSchedule::from($employee->statutory->statutoryContributionSchedule->value);
        }

        return $company->pagIbigContributionSchedule;
    }

    /**
     * Builds optional separate deductions produced by Pag-IBIG-specific modes.
     *
     * @return array<int, PayrollLine>
     */
    private function separateDeductions(CompanyProfile $company, PayrollInput $input): array
    {
        if (
            $this->effectiveMode($company->pagIbigContributionMode) !== PagIbigContributionMode::LoanAmortizationSeparated
            || $input->pagIbigLoanAmortization === null
            || $input->pagIbigLoanAmortization->isZero()
        ) {
            return [];
        }

        return [
            new PayrollLine(
                type: 'deduction',
                label: 'Pag-IBIG Loan Amortization',
                amount: $input->pagIbigLoanAmortization,
                taxable: false,
                metadata: TraceMetadata::line(
                    source: 'payroll_input.pagibig_loan_amortization',
                    appliedRule: 'pagibig_loan_amortization',
                    formula: 'input amount',
                    basis: [
                        'amount' => $input->pagIbigLoanAmortization,
                    ],
                ),
            ),
        ];
    }
}
