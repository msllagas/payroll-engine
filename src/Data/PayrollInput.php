<?php

namespace QuillBytes\PayrollEngine\Data;

use Money\Money;
use QuillBytes\PayrollEngine\Support\MoneyHelper;

final readonly class PayrollInput
{
    public Money $leaveDeduction;

    public Money $absenceDeduction;

    public Money $lateDeduction;

    public Money $undertimeDeduction;

    public Money $bonus;

    public Money $usedAnnualBonusShield;

    /**
     * @param  array<int, OvertimeEntry>  $overtimeEntries
     * @param  array<int, VariableEarningEntry>  $variableEarningEntries
     * @param  array<int, Adjustment>  $adjustments
     * @param  array<int, Deduction>  $manualDeductions
     * @param  array<int, LoanDeduction>  $loanDeductions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public PayrollPeriod $period,
        public array $overtimeEntries = [],
        public array $variableEarningEntries = [],
        public array $adjustments = [],
        public array $manualDeductions = [],
        public array $loanDeductions = [],
        public ?Money $manualOvertimePay = null,
        ?Money $leaveDeduction = null,
        ?Money $absenceDeduction = null,
        ?Money $lateDeduction = null,
        ?Money $undertimeDeduction = null,
        ?Money $bonus = null,
        ?Money $usedAnnualBonusShield = null,
        public ?Money $pagIbigLoanAmortization = null,
        public ?bool $pagIbigDueThisRun = null,
        public ?Money $projectedAnnualTaxableIncome = null,
        public array $metadata = [],
        public ?bool $statutoryDueThisRun = null,
        public ?bool $sssDueThisRun = null,
        public ?bool $philHealthDueThisRun = null,
    ) {
        $this->leaveDeduction = $leaveDeduction ?? MoneyHelper::zero();
        $this->absenceDeduction = $absenceDeduction ?? MoneyHelper::zero();
        $this->lateDeduction = $lateDeduction ?? MoneyHelper::zero();
        $this->undertimeDeduction = $undertimeDeduction ?? MoneyHelper::zero();
        $this->bonus = $bonus ?? MoneyHelper::zero();
        $this->usedAnnualBonusShield = $usedAnnualBonusShield ?? MoneyHelper::zero();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides = []): self
    {
        return new self(
            period: $overrides['period'] ?? $this->period,
            overtimeEntries: $overrides['overtimeEntries'] ?? $this->overtimeEntries,
            variableEarningEntries: $overrides['variableEarningEntries'] ?? $this->variableEarningEntries,
            adjustments: $overrides['adjustments'] ?? $this->adjustments,
            manualDeductions: $overrides['manualDeductions'] ?? $this->manualDeductions,
            loanDeductions: $overrides['loanDeductions'] ?? $this->loanDeductions,
            manualOvertimePay: $overrides['manualOvertimePay'] ?? $this->manualOvertimePay,
            leaveDeduction: $overrides['leaveDeduction'] ?? $this->leaveDeduction,
            absenceDeduction: $overrides['absenceDeduction'] ?? $this->absenceDeduction,
            lateDeduction: $overrides['lateDeduction'] ?? $this->lateDeduction,
            undertimeDeduction: $overrides['undertimeDeduction'] ?? $this->undertimeDeduction,
            bonus: $overrides['bonus'] ?? $this->bonus,
            usedAnnualBonusShield: $overrides['usedAnnualBonusShield'] ?? $this->usedAnnualBonusShield,
            pagIbigLoanAmortization: $overrides['pagIbigLoanAmortization'] ?? $this->pagIbigLoanAmortization,
            pagIbigDueThisRun: $overrides['pagIbigDueThisRun'] ?? $this->pagIbigDueThisRun,
            projectedAnnualTaxableIncome: $overrides['projectedAnnualTaxableIncome'] ?? $this->projectedAnnualTaxableIncome,
            metadata: $overrides['metadata'] ?? $this->metadata,
            statutoryDueThisRun: $overrides['statutoryDueThisRun'] ?? $this->statutoryDueThisRun,
            sssDueThisRun: $overrides['sssDueThisRun'] ?? $this->sssDueThisRun,
            philHealthDueThisRun: $overrides['philHealthDueThisRun'] ?? $this->philHealthDueThisRun,
        );
    }
}
