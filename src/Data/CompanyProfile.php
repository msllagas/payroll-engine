<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionMode;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionSchedule;
use QuillBytes\PayrollEngine\Enums\TaxStrategy;

final readonly class CompanyProfile
{
    /**
     * @param  array<int, string>  $preparedBy
     * @param  array<int, string>  $approvers
     * @param  array<int, string>  $administrators
     * @param  array<int, array<string, mixed>>  $payrollSchedules
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $name,
        public ?string $code,
        public string $clientCode,
        public PayrollSchedule $schedule,
        public int $eemrFactor,
        public bool $manualOvertimePay,
        public bool $fixedPerDayRate,
        public bool $separateAllowancePayout,
        public bool $externalLeaveManagement,
        public bool $splitMonthlyStatutoryAcrossPeriods,
        public PagIbigContributionMode $pagIbigContributionMode,
        public PagIbigContributionSchedule $pagIbigContributionSchedule,
        public TaxStrategy $taxStrategy,
        public Money $annualBonusTaxShield,
        public float $workDayOtPremium,
        public float $restDayOtPremium,
        public float $regularHolidayOtPremium,
        public float $specialNonWorkingDayOtPremium,
        public float $specialWorkingHolidayOtPremium,
        public float $nightShiftDifferentialPremium,
        public array $preparedBy = [],
        public array $approvers = [],
        public array $administrators = [],
        public array $payrollSchedules = [],
        public ?string $logo = null,
        public array $metadata = [],
    ) {}

    public function periodsPerYear(): int
    {
        return $this->schedule->periodsPerYear();
    }

    public function allowsPreparer(string $actor): bool
    {
        return $this->preparedBy === [] || in_array($actor, $this->preparedBy, true);
    }

    public function allowsApprover(string $actor): bool
    {
        return $this->approvers === [] || in_array($actor, $this->approvers, true);
    }

    public function allowsAdministrator(string $actor): bool
    {
        return $this->administrators === [] || in_array($actor, $this->administrators, true);
    }
}
