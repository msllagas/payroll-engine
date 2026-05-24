<?php

namespace QuillBytes\PayrollEngine\Normalizers;

use Carbon\CarbonImmutable;
use Money\Money;
use QuillBytes\PayrollEngine\Data\AllocationProfile;
use QuillBytes\PayrollEngine\Data\CompensationProfile;
use QuillBytes\PayrollEngine\Data\EmployeeProfile;
use QuillBytes\PayrollEngine\Data\EmploymentProfile;
use QuillBytes\PayrollEngine\Data\PayrollDetails;
use QuillBytes\PayrollEngine\Data\StatutoryProfile;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionSchedule;
use QuillBytes\PayrollEngine\Enums\StatutoryContributionSchedule;
use QuillBytes\PayrollEngine\Support\AttributeReader;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class EmployeeProfileNormalizer
{
    public function __construct(
        ?AttributeReader $reader = null,
    ) {
        $this->reader = $reader ?? new AttributeReader;
    }

    private readonly AttributeReader $reader;

    public function normalize(mixed $employee): EmployeeProfile
    {
        if ($employee instanceof EmployeeProfile) {
            return $employee;
        }

        $firstName = (string) $this->reader->get($employee, ['first_name', 'firstName'], '');
        $lastName = (string) $this->reader->get($employee, ['last_name', 'lastName'], '');
        $fullName = (string) $this->reader->get($employee, ['full_name', 'fullName', 'name'], trim($firstName.' '.$lastName));

        $employment = new EmploymentProfile(
            dateHired: $this->asDate($this->reader->get($employee, ['date_hired', 'dateHired', 'employment.date_hired', 'employment.dateHired'])),
            dateRegularized: $this->asDate($this->reader->get($employee, ['date_regularized', 'dateRegularized', 'employment.date_regularized', 'employment.dateRegularized'])),
            dateResigned: $this->asDate($this->reader->get($employee, ['date_resigned', 'dateResigned', 'employment.date_resigned', 'employment.dateResigned'])),
            reasonForResignation: $this->reader->get($employee, ['reason_for_resignation', 'reasonForResignation', 'employment.reason_for_resignation', 'employment.reasonForResignation']),
            employmentStatus: (string) $this->reader->get($employee, ['employment_status', 'employmentStatus', 'status', 'employment.status'], 'active'),
            department: $this->reader->get($employee, ['department', 'employment.department']),
        );

        $compensation = new CompensationProfile(
            monthlyBasicSalary: $this->monthlyBasicSalary($employee),
            fixedDailyRate: $this->nullableMoney($this->reader->get($employee, ['daily_rate', 'dailyRate', 'compensation.daily_rate', 'compensation.dailyRate'])),
            fixedHourlyRate: $this->nullableMoney($this->reader->get($employee, ['hourly_rate', 'hourlyRate', 'compensation.hourly_rate', 'compensation.hourlyRate'])),
            projectedAnnualTaxableIncome: $this->nullableMoney($this->reader->get($employee, ['projected_annual_taxable_income', 'projectedAnnualTaxableIncome', 'compensation.projected_annual_taxable_income', 'compensation.projectedAnnualTaxableIncome'])),
            representationAllowance: MoneyHelper::fromNumeric($this->reader->get($employee, ['representation', 'representation_allowance', 'representationAllowance', 'compensation.representation', 'compensation.representation_allowance'], 0)),
            otherAllowances: $this->otherAllowances($employee),
        );

        $statutoryContributionSchedule = $this->statutoryContributionSchedule($employee);

        $statutory = new StatutoryProfile(
            tin: $this->reader->get($employee, ['tin', 'statutory.tin']),
            sssNumber: $this->reader->get($employee, ['sss_number', 'sssNumber', 'statutory.sss_number', 'statutory.sssNumber']),
            hdmfNumber: $this->reader->get($employee, ['hdmf_number', 'hdmfNumber', 'pagibig_number', 'pagibigNumber', 'statutory.hdmf_number', 'statutory.hdmfNumber']),
            phicNumber: $this->reader->get($employee, ['phic_number', 'phicNumber', 'philhealth_number', 'philhealthNumber', 'statutory.phic_number', 'statutory.phicNumber']),
            minimumWageEarner: (bool) $this->reader->get($employee, ['minimum_wage_earner', 'minimumWageEarner', 'statutory.minimum_wage_earner', 'statutory.minimumWageEarner'], false),
            manualSssContribution: $this->nullableMoney($this->reader->get($employee, ['manual_sss_contribution', 'manualSssContribution', 'statutory.manual_sss_contribution', 'statutory.manualSssContribution'])),
            manualPhilHealthContribution: $this->nullableMoney($this->reader->get($employee, ['manual_philhealth_contribution', 'manualPhilHealthContribution', 'statutory.manual_philhealth_contribution', 'statutory.manualPhilHealthContribution'])),
            manualPagIbigContribution: $this->nullableMoney($this->reader->get($employee, ['manual_pagibig_contribution', 'manualPagIbigContribution', 'statutory.manual_pagibig_contribution', 'statutory.manualPagIbigContribution'])),
            upgradedPagIbigContribution: $this->nullableMoney($this->reader->get($employee, ['upgraded_pagibig_contribution', 'upgradedPagIbigContribution', 'voluntary_pagibig_contribution', 'voluntaryPagIbigContribution', 'statutory.upgraded_pagibig_contribution', 'statutory.upgradedPagIbigContribution', 'statutory.voluntary_pagibig_contribution', 'statutory.voluntaryPagIbigContribution'])),
            pagIbigContributionSchedule: $this->pagIbigContributionSchedule($employee, $statutoryContributionSchedule),
            metadata: [],
            statutoryContributionSchedule: $statutoryContributionSchedule,
            sssContributionSchedule: $this->sssContributionSchedule($employee, $statutoryContributionSchedule),
            philHealthContributionSchedule: $this->philHealthContributionSchedule($employee, $statutoryContributionSchedule),
        );

        $payrollDetails = new PayrollDetails(
            accountNumber: $this->reader->get($employee, ['account_number', 'accountNumber', 'payroll.account_number', 'payroll.accountNumber']),
            bank: $this->reader->get($employee, ['bank', 'payroll.bank']),
            branch: $this->reader->get($employee, ['branch', 'payroll.branch']),
        );
        $allocation = new AllocationProfile(
            projectCode: $this->reader->get($employee, ['project_code', 'projectCode', 'allocation.project_code', 'allocation.projectCode']),
            projectName: $this->reader->get($employee, ['project_name', 'projectName', 'allocation.project_name', 'allocation.projectName']),
            costCenter: $this->reader->get($employee, ['cost_center', 'costCenter', 'cost_centre', 'costCentre', 'allocation.cost_center', 'allocation.costCenter', 'allocation.cost_centre', 'allocation.costCentre']),
            branch: $payrollDetails->branch,
            department: $employment->department,
            vessel: $this->reader->get($employee, ['vessel', 'vessel_name', 'vesselName', 'allocation.vessel', 'allocation.vessel_name', 'allocation.vesselName']),
            dimensions: $this->allocationDimensions($employee),
        );

        return new EmployeeProfile(
            employeeNumber: (string) $this->reader->get($employee, ['employee_number', 'employeeNumber', 'number'], 'EMP-UNKNOWN'),
            fullName: $fullName !== '' ? $fullName : 'Unknown Employee',
            email: $this->reader->get($employee, ['email', 'email_address', 'emailAddress']),
            position: $this->reader->get($employee, ['position', 'rank', 'rank_position', 'rankPosition']),
            employment: $employment,
            compensation: $compensation,
            statutory: $statutory,
            payrollDetails: $payrollDetails,
            allocation: $allocation,
            bonusTaxShieldAmount: MoneyHelper::fromNumeric($this->reader->get($employee, ['tax_shield_amount_for_bonuses', 'taxShieldAmountForBonuses', 'annual_bonus_tax_shield', 'annualBonusTaxShield'], 0)),
            metadata: is_array($employee) ? $employee : [],
        );
    }

    private function monthlyBasicSalary(mixed $employee): Money
    {
        $monthly = $this->reader->get($employee, [
            'monthly_basic_salary',
            'monthlyBasicSalary',
            'compensation.monthly_basic_salary',
            'compensation.monthlyBasicSalary',
        ]);

        if ($monthly !== null && $monthly !== '') {
            return MoneyHelper::fromNumeric($monthly);
        }

        return MoneyHelper::fromNumeric($this->reader->get($employee, [
            'basic_salary',
            'basicSalary',
            'compensation.basic_salary',
            'compensation.basicSalary',
        ], 0));
    }

    private function otherAllowances(mixed $employee): Money
    {
        $allowanceKeys = [
            'allowances',
            'other_allowances',
            'otherAllowances',
            'meal_allowance',
            'mealAllowance',
            'rice_allowance',
            'riceAllowance',
            'medical_allowance',
            'medicalAllowance',
            'laundry_allowance',
            'laundryAllowance',
            'transportation_allowance',
            'transportationAllowance',
            'license_allowance',
            'licenseAllowance',
        ];

        $total = MoneyHelper::zero();

        foreach ($allowanceKeys as $key) {
            $value = $this->reader->get($employee, [$key, 'compensation.'.$key]);

            if ($value !== null && $value !== '') {
                $total = $total->add(MoneyHelper::fromNumeric($value));
            }
        }

        return $total;
    }

    private function asDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function nullableMoney(mixed $value): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        return MoneyHelper::fromNumeric($value);
    }

    private function pagIbigContributionSchedule(mixed $employee, ?StatutoryContributionSchedule $fallback = null): ?PagIbigContributionSchedule
    {
        $value = $this->reader->get($employee, [
            'pagibig_schedule',
            'pagIbigSchedule',
            'pagibig_contribution_schedule',
            'pagIbigContributionSchedule',
            'pagibig_payment_schedule',
            'pagIbigPaymentSchedule',
            'statutory.pagibig_schedule',
            'statutory.pagIbigSchedule',
            'statutory.pagibig_contribution_schedule',
            'statutory.pagIbigContributionSchedule',
            'statutory.pagibig_payment_schedule',
            'statutory.pagIbigPaymentSchedule',
        ]);

        if (! is_string($value) || trim($value) === '') {
            return $fallback !== null
                ? PagIbigContributionSchedule::from($fallback->value)
                : null;
        }

        return PagIbigContributionSchedule::from($value);
    }

    private function statutoryContributionSchedule(mixed $employee): ?StatutoryContributionSchedule
    {
        $value = $this->reader->get($employee, [
            'statutory_schedule',
            'statutorySchedule',
            'statutory_contribution_schedule',
            'statutoryContributionSchedule',
            'contribution_schedule',
            'contributionSchedule',
            'statutory.statutory_schedule',
            'statutory.statutorySchedule',
            'statutory.statutory_contribution_schedule',
            'statutory.statutoryContributionSchedule',
            'statutory.contribution_schedule',
            'statutory.contributionSchedule',
        ]);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return StatutoryContributionSchedule::from($value);
    }

    private function sssContributionSchedule(mixed $employee, ?StatutoryContributionSchedule $fallback = null): ?StatutoryContributionSchedule
    {
        $value = $this->reader->get($employee, [
            'sss_schedule',
            'sssSchedule',
            'sss_contribution_schedule',
            'sssContributionSchedule',
            'sss_payment_schedule',
            'sssPaymentSchedule',
            'statutory.sss_schedule',
            'statutory.sssSchedule',
            'statutory.sss_contribution_schedule',
            'statutory.sssContributionSchedule',
            'statutory.sss_payment_schedule',
            'statutory.sssPaymentSchedule',
        ]);

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return StatutoryContributionSchedule::from($value);
    }

    private function philHealthContributionSchedule(mixed $employee, ?StatutoryContributionSchedule $fallback = null): ?StatutoryContributionSchedule
    {
        $value = $this->reader->get($employee, [
            'philhealth_schedule',
            'philHealthSchedule',
            'philhealth_contribution_schedule',
            'philHealthContributionSchedule',
            'philhealth_payment_schedule',
            'philHealthPaymentSchedule',
            'phic_schedule',
            'phicSchedule',
            'phic_contribution_schedule',
            'phicContributionSchedule',
            'statutory.philhealth_schedule',
            'statutory.philHealthSchedule',
            'statutory.philhealth_contribution_schedule',
            'statutory.philHealthContributionSchedule',
            'statutory.philhealth_payment_schedule',
            'statutory.philHealthPaymentSchedule',
            'statutory.phic_schedule',
            'statutory.phicSchedule',
            'statutory.phic_contribution_schedule',
            'statutory.phicContributionSchedule',
        ]);

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        return StatutoryContributionSchedule::from($value);
    }

    /**
     * @return array<string, string>
     */
    private function allocationDimensions(mixed $employee): array
    {
        $value = $this->reader->get($employee, [
            'allocation_dimensions',
            'allocationDimensions',
            'allocation.dimensions',
        ], []);

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $key => $dimensionValue) {
            if (! is_string($key) || trim($key) === '' || $dimensionValue === null || $dimensionValue === '') {
                continue;
            }

            $normalized[strtolower(trim($key))] = trim((string) $dimensionValue);
        }

        return $normalized;
    }
}
