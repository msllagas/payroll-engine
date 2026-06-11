<?php

namespace QuillBytes\PayrollEngine\Normalizers;

use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\PayrollSchedule;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionMode;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionSchedule;
use QuillBytes\PayrollEngine\Enums\PayrollFrequency;
use QuillBytes\PayrollEngine\Enums\TaxStrategy;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;
use QuillBytes\PayrollEngine\Policies\ClientPolicyRegistry;
use QuillBytes\PayrollEngine\Support\AttributeReader;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final readonly class CompanyProfileNormalizer
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        ?AttributeReader $reader = null,
        ?ClientPolicyRegistry $registry = null,
        private readonly array $config = [],
    ) {
        $this->reader = $reader ?? new AttributeReader;
        $this->registry = $registry ?? new ClientPolicyRegistry;
    }

    private readonly AttributeReader $reader;

    private readonly ClientPolicyRegistry $registry;

    public function normalize(mixed $company): CompanyProfile
    {
        if ($company instanceof CompanyProfile) {
            return $company;
        }

        $clientCode = strtolower((string) $this->reader->get($company, [
            'client_code',
            'clientCode',
            'policy_key',
            'policyKey',
            'company_code',
            'companyCode',
        ], 'base'));

        $defaults = array_merge(
            $this->registry->defaultsFor($clientCode),
            $this->config['defaults'] ?? [],
            $this->config['presets'][$clientCode] ?? [],
        );

        $frequency = PayrollFrequency::from((string) ($this->reader->get($company, [
            'frequency',
            'payroll_frequency',
            'payrollFrequency',
        ], $defaults['frequency']) ?: $defaults['frequency']));

        $schedule = new PayrollSchedule(
            frequency: $frequency,
            hoursPerDay: (int) $this->reader->get($company, ['hours_per_day', 'hoursPerDay'], $defaults['hours_per_day']),
            workDaysPerYear: (int) $this->reader->get($company, ['work_days_per_year', 'workDaysPerYear'], $defaults['work_days_per_year']),
            releaseLeadDays: (int) $this->reader->get($company, ['release_lead_days', 'releaseLeadDays'], $defaults['release_lead_days']),
        );
        $splitMonthlyStatutoryAcrossPeriods = (bool) $this->reader->get(
            $company,
            ['split_monthly_statutory_across_periods', 'splitMonthlyStatutoryAcrossPeriods'],
            $defaults['split_monthly_statutory_across_periods']
        );
        $pagIbigContributionMode = PagIbigContributionMode::from((string) $this->reader->get(
            $company,
            ['pagibig_mode', 'pagIbigMode'],
            $defaults['pagibig_mode'] ?? 'standard_mandatory'
        ));

        $approvers = $this->reader->get($company, ['approvers', 'payroll_approvers', 'payrollApprovers'], []);

        if (is_string($approvers)) {
            $approvers = array_values(array_filter(array_map('trim', explode(',', $approvers))));
        }

        $preparedBy = $this->normalizeUsers($this->reader->get($company, [
            'prepared_by',
            'preparedBy',
            'prepared_users',
            'preparedUsers',
        ], []));
        $administrators = $this->normalizeUsers($this->reader->get($company, [
            'administrators',
            'admins',
            'admin_users',
            'adminUsers',
        ], []));
        $workDayOtPremium = $this->premium($company, ['work_day_ot_premium', 'workDayOtPremium'], $defaults, 'work_day_ot_premium', 1.25);
        $restDayOtPremium = $this->premium($company, ['rest_day_ot_premium', 'restDayOtPremium'], $defaults, 'rest_day_ot_premium', 1.69);
        $regularHolidayOtPremium = $this->premium(
            $company,
            ['regular_holiday_ot_premium', 'regularHolidayOtPremium', 'holiday_ot_premium', 'holidayOtPremium'],
            $defaults,
            ['regular_holiday_ot_premium', 'holiday_ot_premium'],
            2.60,
        );
        $specialNonWorkingDayOtPremium = $this->premium(
            $company,
            ['special_non_working_day_ot_premium', 'specialNonWorkingDayOtPremium'],
            $defaults,
            'special_non_working_day_ot_premium',
            1.69,
        );
        $specialWorkingHolidayOtPremium = $this->premium(
            $company,
            ['special_working_holiday_ot_premium', 'specialWorkingHolidayOtPremium'],
            $defaults,
            'special_working_holiday_ot_premium',
            1.25,
        );
        $nightShiftDifferentialPremium = $this->premium($company, ['night_shift_differential_premium', 'nightShiftDifferentialPremium'], $defaults, 'night_shift_differential_premium', 0.10);

        return new CompanyProfile(
            name: (string) $this->reader->get($company, ['name', 'company_name', 'companyName'], 'Payroll Company'),
            code: $this->reader->get($company, ['code', 'company_code', 'companyCode']),
            clientCode: $clientCode === '' ? 'base' : $clientCode,
            schedule: $schedule,
            eemrFactor: (int) $this->reader->get($company, ['eemr_factor', 'eemrFactor', 'eemr'], $defaults['eemr_factor']),
            manualOvertimePay: (bool) $this->reader->get($company, ['manual_overtime_pay', 'manualOvertimePay'], $defaults['manual_overtime_pay']),
            fixedPerDayRate: (bool) $this->reader->get($company, ['fixed_per_day_rate', 'fixedPerDayRate'], $defaults['fixed_per_day_rate']),
            separateAllowancePayout: (bool) $this->reader->get($company, ['separate_allowance_payout', 'separateAllowancePayout'], $defaults['separate_allowance_payout']),
            externalLeaveManagement: (bool) $this->reader->get($company, ['external_leave_management', 'externalLeaveManagement'], $defaults['external_leave_management']),
            splitMonthlyStatutoryAcrossPeriods: $splitMonthlyStatutoryAcrossPeriods,
            pagIbigContributionMode: $pagIbigContributionMode,
            pagIbigContributionSchedule: $this->resolvePagIbigContributionSchedule($company, $defaults, $splitMonthlyStatutoryAcrossPeriods, $pagIbigContributionMode),
            taxStrategy: TaxStrategy::from((string) $this->reader->get($company, ['tax_strategy', 'taxStrategy'], $defaults['tax_strategy'])),
            annualBonusTaxShield: MoneyHelper::fromNumeric($this->reader->get($company, ['annual_bonus_tax_shield', 'annualBonusTaxShield'], $defaults['annual_bonus_tax_shield'])),
            workDayOtPremium: $workDayOtPremium,
            restDayOtPremium: $restDayOtPremium,
            regularHolidayOtPremium: $regularHolidayOtPremium,
            specialNonWorkingDayOtPremium: $specialNonWorkingDayOtPremium,
            specialWorkingHolidayOtPremium: $specialWorkingHolidayOtPremium,
            nightShiftDifferentialPremium: $nightShiftDifferentialPremium,
            preparedBy: $preparedBy,
            approvers: is_array($approvers) ? $approvers : [],
            administrators: $administrators,
            payrollSchedules: $this->normalizePayrollSchedules($company),
            logo: $this->reader->get($company, ['logo', 'company_logo', 'companyLogo']),
            metadata: is_array($company) ? $company : [],
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePayrollSchedules(mixed $company): array
    {
        $schedules = $this->reader->get($company, ['payroll_schedules', 'payrollSchedules'], []);

        if (is_array($schedules) && $schedules !== []) {
            return array_values(array_filter($schedules, 'is_array'));
        }

        $normalized = [];

        foreach ([1, 2] as $index) {
            $payDate = $this->reader->get($company, ["payroll_schedule_{$index}", "payrollSchedule{$index}"]);
            $start = $this->reader->get($company, ["period_covered_start_{$index}", "periodCoveredStart{$index}"]);
            $end = $this->reader->get($company, ["period_covered_end_{$index}", "periodCoveredEnd{$index}"]);

            if ($payDate === null && $start === null && $end === null) {
                continue;
            }

            $normalized[] = [
                'pay_date' => $payDate,
                'period_start' => $start,
                'period_end' => $end,
            ];
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeUsers(mixed $users): array
    {
        if (is_string($users)) {
            $users = array_values(array_filter(array_map('trim', explode(',', $users))));
        }

        if (! is_array($users)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($user) => trim((string) $user), $users)));
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function resolvePagIbigContributionSchedule(
        mixed $company,
        array $defaults,
        bool $splitMonthlyStatutoryAcrossPeriods,
        PagIbigContributionMode $pagIbigContributionMode,
    ): PagIbigContributionSchedule {
        $configured = $this->reader->get($company, [
            'pagibig_schedule',
            'pagIbigSchedule',
            'pagibig_contribution_schedule',
            'pagIbigContributionSchedule',
        ], $defaults['pagibig_schedule'] ?? null);

        if (is_string($configured) && trim($configured) !== '') {
            return PagIbigContributionSchedule::from($configured);
        }

        if ($pagIbigContributionMode === PagIbigContributionMode::SplitPerCutoff) {
            return PagIbigContributionSchedule::SplitPerCutoff;
        }

        return $splitMonthlyStatutoryAcrossPeriods
            ? PagIbigContributionSchedule::SplitPerCutoff
            : PagIbigContributionSchedule::Monthly;
    }

    private function asPremium(mixed $value): float
    {
        if ($value === null || $value === '') {
            throw new InvalidPayrollData('Payroll premium configuration is required.');
        }

        $premium = (float) $value;

        if ($premium > 10) {
            $premium /= 100;
        }

        return $premium;
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $defaults
     * @param  array<int, string>|string  $defaultKeys
     */
    private function premium(mixed $company, array $keys, array $defaults, array|string $defaultKeys, float $fallback): float
    {
        $value = $this->reader->get($company, $keys);

        foreach ((array) $defaultKeys as $defaultKey) {
            if ($value !== null && $value !== '') {
                break;
            }

            $value = $defaults[$defaultKey] ?? null;
        }

        if ($value === null || $value === '') {
            $value = $fallback;
        }

        return $this->asPremium($value);
    }
}
