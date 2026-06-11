<?php

namespace QuillBytes\PayrollEngine\Validators;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;

final class CompanyProfileValidator
{
    public function validate(CompanyProfile $company): void
    {
        if (trim($company->name) === '') {
            throw new InvalidPayrollData('Company name is required.');
        }

        if ($company->eemrFactor <= 0) {
            throw new InvalidPayrollData('Company EEMR factor must be greater than zero.');
        }

        if ($company->schedule->hoursPerDay <= 0) {
            throw new InvalidPayrollData('Company hours per day must be greater than zero.');
        }

        if ($company->schedule->workDaysPerYear <= 0) {
            throw new InvalidPayrollData('Company work days per year must be greater than zero.');
        }

        foreach ([
            'work day OT premium' => $company->workDayOtPremium,
            'rest day OT premium' => $company->restDayOtPremium,
            'regular holiday OT premium' => $company->regularHolidayOtPremium,
            'special non-working day OT premium' => $company->specialNonWorkingDayOtPremium,
            'special working holiday OT premium' => $company->specialWorkingHolidayOtPremium,
            'night differential premium' => $company->nightShiftDifferentialPremium,
        ] as $label => $value) {
            if ($value < 0) {
                throw new InvalidPayrollData("Company {$label} cannot be negative.");
            }
        }

        $this->assertUserList($company->preparedBy, 'prepared by');
        $this->assertUserList($company->approvers, 'approvers');

        if (count($company->payrollSchedules) > 2) {
            throw new InvalidPayrollData('Company payroll schedules cannot contain more than two configured schedules.');
        }

        if ($company->payrollSchedules === []) {
            throw new InvalidPayrollData('Company payroll schedule configuration is required.');
        }

        foreach ($company->payrollSchedules as $index => $schedule) {
            foreach (['pay_date', 'period_start', 'period_end'] as $field) {
                if (! array_key_exists($field, $schedule) || $schedule[$field] === null || trim((string) $schedule[$field]) === '') {
                    $position = $index + 1;
                    throw new InvalidPayrollData("Company payroll schedule {$position} must define {$field}.");
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $users
     */
    private function assertUserList(array $users, string $label): void
    {
        if ($users === []) {
            throw new InvalidPayrollData("Company {$label} configuration is required.");
        }

        if (count($users) > 5) {
            throw new InvalidPayrollData("Company {$label} configuration cannot contain more than five users.");
        }
    }
}
