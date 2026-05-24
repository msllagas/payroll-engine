<?php

namespace QuillBytes\PayrollEngine\Normalizers;

use Money\Money;
use QuillBytes\PayrollEngine\Data\Adjustment;
use QuillBytes\PayrollEngine\Data\CompanyProfile;
use QuillBytes\PayrollEngine\Data\Deduction;
use QuillBytes\PayrollEngine\Data\LoanDeduction;
use QuillBytes\PayrollEngine\Data\OvertimeEntry;
use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Data\PayrollPeriod;
use QuillBytes\PayrollEngine\Data\VariableEarningEntry;
use QuillBytes\PayrollEngine\Support\AttributeReader;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final class PayrollInputNormalizer
{
    public function __construct(
        ?AttributeReader $reader = null,
        ?PayrollPeriodNormalizer $periodNormalizer = null,
    ) {
        $this->reader = $reader ?? new AttributeReader;
        $this->periodNormalizer = $periodNormalizer ?? new PayrollPeriodNormalizer($this->reader);
    }

    private readonly AttributeReader $reader;

    private readonly PayrollPeriodNormalizer $periodNormalizer;

    public function normalize(mixed $input, CompanyProfile $company): PayrollInput
    {
        if ($input instanceof PayrollInput) {
            return $input;
        }

        $period = $this->reader->get($input, ['period']);

        if (! $period instanceof PayrollPeriod) {
            $period = $this->periodNormalizer->normalize($period ?? $input, $company);
        }

        return new PayrollInput(
            period: $period,
            overtimeEntries: $this->normalizeOvertimeEntries($this->reader->get($input, ['overtime', 'overtime_entries', 'overtimeEntries'], [])),
            variableEarningEntries: $this->normalizeVariableEarningEntries($input),
            adjustments: $this->normalizeAdjustments($this->reader->get($input, ['adjustments', 'earnings_adjustments', 'earningsAdjustments'], [])),
            manualDeductions: $this->normalizeDeductions($this->reader->get($input, ['manual_deductions', 'manualDeductions', 'deductions'], [])),
            loanDeductions: $this->normalizeLoanDeductions($this->reader->get($input, ['loan_deductions', 'loanDeductions', 'loans'], [])),
            manualOvertimePay: $this->nullableMoney($this->reader->get($input, ['manual_overtime_pay', 'manualOvertimePay'])),
            leaveDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['leave_deduction', 'leaveDeduction'], 0)),
            absenceDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['absence_deduction', 'absenceDeduction'], 0)),
            lateDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['late_deduction', 'lateDeduction'], 0)),
            undertimeDeduction: MoneyHelper::fromNumeric($this->reader->get($input, ['undertime_deduction', 'undertimeDeduction'], 0)),
            bonus: MoneyHelper::fromNumeric($this->reader->get($input, ['bonus', 'bonus_amount', 'bonusAmount'], 0)),
            usedAnnualBonusShield: MoneyHelper::fromNumeric($this->reader->get($input, ['used_annual_bonus_shield', 'usedAnnualBonusShield'], 0)),
            pagIbigLoanAmortization: $this->nullableMoney($this->reader->get($input, ['pagibig_loan_amortization', 'pagIbigLoanAmortization', 'hdmf_loan_amortization', 'hdmfLoanAmortization'])),
            pagIbigDueThisRun: $this->nullableBool($this->reader->get($input, ['pagibig_due_this_run', 'pagIbigDueThisRun'])),
            projectedAnnualTaxableIncome: $this->nullableMoney($this->reader->get($input, ['projected_annual_taxable_income', 'projectedAnnualTaxableIncome'])),
            metadata: is_array($input) ? $input : [],
            statutoryDueThisRun: $this->nullableBool($this->reader->get($input, ['statutory_due_this_run', 'statutoryDueThisRun', 'contributions_due_this_run', 'contributionsDueThisRun'])),
            sssDueThisRun: $this->nullableBool($this->reader->get($input, ['sss_due_this_run', 'sssDueThisRun'])),
            philHealthDueThisRun: $this->nullableBool($this->reader->get($input, ['philhealth_due_this_run', 'philHealthDueThisRun', 'phic_due_this_run', 'phicDueThisRun'])),
        );
    }

    /**
     * @return array<int, OvertimeEntry>
     */
    private function normalizeOvertimeEntries(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof OvertimeEntry) {
                $normalized[] = $entry;

                continue;
            }

            $normalized[] = new OvertimeEntry(
                type: (string) $this->reader->get($entry, ['type'], 'regular'),
                hours: (float) $this->reader->get($entry, ['hours'], 0),
                multiplier: ($multiplier = $this->reader->get($entry, ['multiplier'])) !== null ? (float) $multiplier : null,
                taxable: (bool) $this->reader->get($entry, ['taxable'], true),
                nightDifferential: (bool) $this->reader->get($entry, ['night_differential', 'nightDifferential'], false),
                manualAmount: $this->nullableMoney($this->reader->get($entry, ['manual_amount', 'manualAmount'])),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, VariableEarningEntry>
     */
    private function normalizeVariableEarningEntries(mixed $input): array
    {
        $normalized = [];

        $this->appendVariableEarningEntries(
            $normalized,
            $this->reader->get($input, ['variable_earnings', 'variableEarnings']),
            'variable_earning',
            'Variable Earning',
        );
        $this->appendVariableEarningEntries(
            $normalized,
            $this->reader->get($input, ['sales_commissions', 'salesCommissions']),
            'sales_commission',
            'Sales Commission',
        );
        $this->appendVariableEarningEntries(
            $normalized,
            $this->reader->get($input, ['production_incentives', 'productionIncentives']),
            'production_incentive',
            'Production Incentive',
        );
        $this->appendVariableEarningEntries(
            $normalized,
            $this->reader->get($input, ['quota_bonuses', 'quotaBonuses']),
            'quota_bonus',
            'Quota Bonus',
        );

        return $normalized;
    }

    /**
     * @return array<int, Adjustment>
     */
    private function normalizeAdjustments(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof Adjustment) {
                $normalized[] = $entry;

                continue;
            }

            $normalized[] = new Adjustment(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Adjustment'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
                taxable: (bool) $this->reader->get($entry, ['taxable'], true),
                separatePayout: (bool) $this->reader->get($entry, ['separate_payout', 'separatePayout'], false),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, Deduction>
     */
    private function normalizeDeductions(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof Deduction) {
                $normalized[] = $entry;

                continue;
            }

            $normalized[] = new Deduction(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Deduction'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
            );
        }

        return $normalized;
    }

    /**
     * @return array<int, LoanDeduction>
     */
    private function normalizeLoanDeductions(mixed $entries): array
    {
        if (! is_iterable($entries)) {
            return [];
        }

        $normalized = [];

        foreach ($entries as $entry) {
            if ($entry instanceof LoanDeduction) {
                $normalized[] = $entry;

                continue;
            }

            $normalized[] = new LoanDeduction(
                label: (string) $this->reader->get($entry, ['label', 'name'], 'Loan'),
                amount: MoneyHelper::fromNumeric($this->reader->get($entry, ['amount'], 0)),
                loanReference: $this->reader->get($entry, ['loan_reference', 'loanReference', 'reference']),
            );
        }

        return $normalized;
    }

    /**
     * @param  array<int, VariableEarningEntry>  $normalized
     */
    private function appendVariableEarningEntries(
        array &$normalized,
        mixed $entries,
        string $defaultType,
        string $defaultLabel,
    ): void {
        if ($entries === null || $entries === '') {
            return;
        }

        if (
            $entries instanceof VariableEarningEntry
            || $entries instanceof Money
            || is_int($entries)
            || is_float($entries)
            || is_string($entries)
        ) {
            $normalized[] = $this->normalizeVariableEarningEntry($entries, $defaultType, $defaultLabel);

            return;
        }

        if (is_array($entries) && $this->looksLikeSingleVariableEarningEntry($entries)) {
            $normalized[] = $this->normalizeVariableEarningEntry($entries, $defaultType, $defaultLabel);

            return;
        }

        if (! is_iterable($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $normalized[] = $this->normalizeVariableEarningEntry($entry, $defaultType, $defaultLabel);
        }
    }

    private function normalizeVariableEarningEntry(
        mixed $entry,
        string $defaultType,
        string $defaultLabel,
    ): VariableEarningEntry {
        if ($entry instanceof VariableEarningEntry) {
            return $entry;
        }

        if ($entry instanceof Money || is_int($entry) || is_float($entry) || is_string($entry)) {
            return new VariableEarningEntry(
                type: $defaultType,
                label: $defaultLabel,
                amount: $this->moneyValue($entry),
                taxable: true,
                metadata: [],
            );
        }

        $metadata = $this->reader->get($entry, ['metadata'], []);

        return new VariableEarningEntry(
            type: (string) $this->reader->get($entry, ['type'], $defaultType),
            label: (string) $this->reader->get($entry, ['label', 'name'], $defaultLabel),
            amount: $this->moneyValue($this->reader->get($entry, ['amount'], 0)),
            taxable: (bool) $this->reader->get($entry, ['taxable'], true),
            metadata: is_array($metadata) ? $metadata : [],
        );
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function looksLikeSingleVariableEarningEntry(array $entry): bool
    {
        return array_key_exists('amount', $entry)
            || array_key_exists('label', $entry)
            || array_key_exists('name', $entry)
            || array_key_exists('type', $entry)
            || array_key_exists('taxable', $entry);
    }

    private function moneyValue(mixed $value): Money
    {
        if ($value instanceof Money) {
            return $value;
        }

        return MoneyHelper::fromNumeric(is_int($value) || is_float($value) || is_string($value) || $value === null ? $value : 0);
    }

    private function nullableMoney(mixed $value): ?Money
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->moneyValue($value);
    }

    private function nullableBool(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return (bool) $value;
    }
}
