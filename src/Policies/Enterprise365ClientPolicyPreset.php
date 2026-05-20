<?php

namespace QuillBytes\PayrollEngine\Policies;

use QuillBytes\PayrollEngine\Contracts\ClientPolicyPreset;

final class Enterprise365ClientPolicyPreset implements ClientPolicyPreset
{
    public function supports(string $clientCode): bool
    {
        return in_array($clientCode, ['enterprise-365', 'regional-hq-365'], true);
    }

    public function defaults(): array
    {
        return [
            'client_code' => 'enterprise-365',
            'eemr_factor' => 365,
            'hours_per_day' => 8,
            'work_days_per_year' => 365,
            'frequency' => 'semi_monthly',
            'release_lead_days' => 1,
            'manual_overtime_pay' => true,
            'fixed_per_day_rate' => true,
            'separate_allowance_payout' => true,
            'external_leave_management' => true,
            'split_monthly_statutory_across_periods' => true,
            'tax_strategy' => 'projected_annualized',
            'annual_bonus_tax_shield' => 90000,
        ];
    }
}
