<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\SssContributionCalculator as SssContributionCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\SssContributionResult;
use QuillBytes\PayrollEngine\Enums\StatutoryContributionSchedule;
use QuillBytes\PayrollEngine\Support\ContributionScheduleResolver;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

final class SssContributionCalculator implements SssContributionCalculatorContract
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        int $periodDivisor = 1,
    ): SssContributionResult {
        $schedule = $this->effectiveSchedule($employee);
        $amounts = $this->monthlyAmounts(
            $employee->compensation->monthlyBasicSalary,
            $employee->statutory->manualSssContribution
        );

        $employeeContribution = $amounts['employee'];
        $employerContribution = $amounts['employer'];
        $dueThisRun = $input->sssDueThisRun ?? $input->statutoryDueThisRun;
        $employeeContribution = ContributionScheduleResolver::apply($employeeContribution, $company, $input, $schedule?->value, $periodDivisor, $dueThisRun);
        $employerContribution = ContributionScheduleResolver::apply($employerContribution, $company, $input, $schedule?->value, $periodDivisor, $dueThisRun);

        return new SssContributionResult(
            employee: new PayrollLine(
                'employee_contribution',
                'SSS Contribution',
                $employeeContribution,
                false,
                TraceMetadata::line(
                    source: 'sss_calculator',
                    appliedRule: 'sss_employee_share',
                    formula: $employee->statutory->manualSssContribution !== null
                        ? 'manual employee contribution'
                        : 'salary_base * 5%, then scheduled by cutoff',
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'salary_base' => $amounts['base'],
                        'period_divisor' => $periodDivisor,
                        'schedule' => $schedule?->value,
                    ],
                    extra: [
                        'manual_override' => $employee->statutory->manualSssContribution !== null,
                    ],
                ),
            ),
            employer: new PayrollLine(
                'employer_contribution',
                'Employer SSS Contribution',
                $employerContribution,
                false,
                TraceMetadata::line(
                    source: 'sss_calculator',
                    appliedRule: 'sss_employer_share',
                    formula: 'salary_base * 10% + ec contribution, then scheduled by cutoff',
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'salary_base' => $amounts['base'],
                        'period_divisor' => $periodDivisor,
                        'schedule' => $schedule?->value,
                    ],
                ),
            ),
        );
    }

    /**
     * @return array{employee: Money, employer: Money, base: Money}
     */
    private function monthlyAmounts(Money $monthlySalary, ?Money $manualEmployeeContribution): array
    {
        $salaryBase = max(5000, min(35000, MoneyHelper::toFloat($monthlySalary)));
        $base = MoneyHelper::fromNumeric($salaryBase, $monthlySalary);

        return [
            'employee' => $manualEmployeeContribution ?? MoneyHelper::percentage($base, 5),
            'employer' => MoneyHelper::percentage($base, 10)->add(MoneyHelper::fromNumeric($salaryBase <= 14500 ? 10 : 30, $monthlySalary)),
            'base' => $base,
        ];
    }

    private function effectiveSchedule(EmployeeProfile $employee): ?StatutoryContributionSchedule
    {
        return $employee->statutory->sssContributionSchedule
            ?? $employee->statutory->statutoryContributionSchedule;
    }
}
