<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;
use QuillBytes\PayrollEngine\Enums\PagIbigContributionSchedule;
use QuillBytes\PayrollEngine\Enums\StatutoryContributionSchedule;

final readonly class StatutoryProfile
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $tin,
        public ?string $sssNumber,
        public ?string $hdmfNumber,
        public ?string $phicNumber,
        public bool $minimumWageEarner,
        public ?Money $manualSssContribution = null,
        public ?Money $manualPhilHealthContribution = null,
        public ?Money $manualPagIbigContribution = null,
        public ?Money $upgradedPagIbigContribution = null,
        public ?PagIbigContributionSchedule $pagIbigContributionSchedule = null,
        public array $metadata = [],
        public ?StatutoryContributionSchedule $statutoryContributionSchedule = null,
        public ?StatutoryContributionSchedule $sssContributionSchedule = null,
        public ?StatutoryContributionSchedule $philHealthContributionSchedule = null,
    ) {}
}
