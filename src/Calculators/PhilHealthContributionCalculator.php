<?php

namespace QuillBytes\PayrollEngine\Calculators;

use Money\Money;
use QuillBytes\PayrollEngine\Contracts\PhilHealthContributionCalculator as PhilHealthContributionCalculatorContract;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollLine;
use QuillBytes\PayrollEngine\Data\PhilHealthContributionResult;
use QuillBytes\PayrollEngine\Enums\StatutoryContributionSchedule;
use QuillBytes\PayrollEngine\Support\ContributionScheduleResolver;
use QuillBytes\PayrollEngine\Support\MoneyHelper;
use QuillBytes\PayrollEngine\Support\TraceMetadata;

final class PhilHealthContributionCalculator implements PhilHealthContributionCalculatorContract
{
    public function calculate(
        CompanyProfile $company,
        EmployeeProfile $employee,
        PayrollInput $input,
        int $periodDivisor = 1,
    ): PhilHealthContributionResult {
        $schedule = $this->effectiveSchedule($employee);
        $amounts = $this->monthlyAmounts(
            $employee->compensation->monthlyBasicSalary,
            $employee->statutory->manualPhilHealthContribution
        );

        $employeeContribution = $amounts['employee'];
        $employerContribution = $amounts['employer'];
        $dueThisRun = $input->philHealthDueThisRun ?? $input->statutoryDueThisRun;
        $employeeContribution = ContributionScheduleResolver::apply($employeeContribution, $company, $input, $schedule?->value, $periodDivisor, $dueThisRun);
        $employerContribution = ContributionScheduleResolver::apply($employerContribution, $company, $input, $schedule?->value, $periodDivisor, $dueThisRun);

        return new PhilHealthContributionResult(
            employee: new PayrollLine(
                'employee_contribution',
                'PhilHealth Contribution',
                $employeeContribution,
                false,
                TraceMetadata::line(
                    source: 'philhealth_calculator',
                    appliedRule: 'philhealth_employee_share',
                    formula: $employee->statutory->manualPhilHealthContribution !== null
                        ? 'manual employee contribution'
                        : 'monthly_premium / 2, then scheduled by cutoff',
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'salary_base' => $amounts['salary_base'],
                        'monthly_premium' => $amounts['monthly_premium'],
                        'period_divisor' => $periodDivisor,
                        'schedule' => $schedule?->value,
                    ],
                    extra: [
                        'manual_override' => $employee->statutory->manualPhilHealthContribution !== null,
                    ],
                ),
            ),
            employer: new PayrollLine(
                'employer_contribution',
                'Employer PhilHealth Contribution',
                $employerContribution,
                false,
                TraceMetadata::line(
                    source: 'philhealth_calculator',
                    appliedRule: 'philhealth_employer_share',
                    formula: 'monthly_premium / 2, then scheduled by cutoff',
                    basis: [
                        'monthly_salary' => $employee->compensation->monthlyBasicSalary,
                        'salary_base' => $amounts['salary_base'],
                        'monthly_premium' => $amounts['monthly_premium'],
                        'period_divisor' => $periodDivisor,
                        'schedule' => $schedule?->value,
                    ],
                ),
            ),
        );
    }

    /**
     * @return array{employee: Money, employer: Money, salary_base: Money, monthly_premium: Money}
     */
    private function monthlyAmounts(Money $monthlySalary, ?Money $manualEmployeeContribution): array
    {
        $salaryBase = max(10000, min(100000, MoneyHelper::toFloat($monthlySalary)));
        $base = MoneyHelper::fromNumeric($salaryBase, $monthlySalary);
        $monthlyPremium = MoneyHelper::percentage($base, 5);

        return [
            'employee' => $manualEmployeeContribution ?? MoneyHelper::divide($monthlyPremium, 2),
            'employer' => MoneyHelper::divide($monthlyPremium, 2),
            'salary_base' => $base,
            'monthly_premium' => $monthlyPremium,
        ];
    }

    private function effectiveSchedule(EmployeeProfile $employee): ?StatutoryContributionSchedule
    {
        return $employee->statutory->philHealthContributionSchedule
            ?? $employee->statutory->statutoryContributionSchedule;
    }
}
