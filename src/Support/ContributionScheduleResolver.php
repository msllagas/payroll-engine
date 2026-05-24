<?php

namespace QuillBytes\PayrollEngine\Support;

use Money\Money;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Enums\PayrollFrequency;

final class ContributionScheduleResolver
{
    public static function apply(
        Money $amount,
        CompanyProfile $company,
        PayrollInput $input,
        ?string $schedule,
        int $periodDivisor,
        ?bool $dueThisRun = null,
    ): Money {
        if ($schedule === 'monthly') {
            return self::monthlyDueThisRun($company, $input, $dueThisRun)
                ? $amount
                : MoneyHelper::zero($amount);
        }

        $effectiveDivisor = $schedule === 'split_per_cutoff'
            ? max(1, $periodDivisor, self::cutoffDivisor($company))
            : max(1, $periodDivisor);

        return $effectiveDivisor > 1
            ? MoneyHelper::divide($amount, $effectiveDivisor)
            : $amount;
    }

    public static function monthlyDueThisRun(CompanyProfile $company, PayrollInput $input, ?bool $dueThisRun = null): bool
    {
        if ($dueThisRun !== null) {
            return $dueThisRun;
        }

        return match ($company->schedule->frequency) {
            PayrollFrequency::Monthly => true,
            PayrollFrequency::SemiMonthly => $input->period->startDate->day > 15 || $input->period->endDate->isSameDay($input->period->endDate->endOfMonth()),
            PayrollFrequency::Weekly => $input->period->releaseDate->addWeek()->month !== $input->period->releaseDate->month,
        };
    }

    public static function cutoffDivisor(CompanyProfile $company): int
    {
        return match ($company->schedule->frequency) {
            PayrollFrequency::Monthly => 1,
            PayrollFrequency::SemiMonthly => 2,
            PayrollFrequency::Weekly => 4,
        };
    }
}
