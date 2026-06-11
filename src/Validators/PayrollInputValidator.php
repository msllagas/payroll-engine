<?php

namespace QuillBytes\PayrollEngine\Validators;

use QuillBytes\PayrollEngine\Data\PayrollInput;
use QuillBytes\PayrollEngine\Enums\OvertimeType;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;

final class PayrollInputValidator
{
    public function validate(PayrollInput $input): void
    {
        if ($input->period->startDate->greaterThan($input->period->endDate)) {
            throw new InvalidPayrollData('Payroll period start date cannot be later than the end date.');
        }

        foreach ($input->overtimeEntries as $entry) {
            if ($entry->hours < 0) {
                throw new InvalidPayrollData('Overtime hours cannot be negative.');
            }

            if ($entry->manualAmount !== null && $entry->manualAmount->isNegative()) {
                throw new InvalidPayrollData('Manual overtime amount cannot be negative.');
            }

            if (OvertimeType::normalize($entry->type) === null) {
                throw new InvalidPayrollData(
                    sprintf(
                        'Unsupported overtime type "%s". Supported overtime types: %s.',
                        $entry->type,
                        implode(', ', OvertimeType::values()),
                    )
                );
            }
        }

        foreach ([
            $input->manualOvertimePay,
            $input->leaveDeduction,
            $input->absenceDeduction,
            $input->lateDeduction,
            $input->undertimeDeduction,
            $input->bonus,
            $input->usedAnnualBonusShield,
        ] as $money) {
            if ($money !== null && $money->isNegative()) {
                throw new InvalidPayrollData('Payroll input monetary values cannot be negative.');
            }
        }

        foreach ([$input->variableEarningEntries, $input->adjustments, $input->manualDeductions, $input->loanDeductions] as $entries) {
            foreach ($entries as $entry) {
                if ($entry->amount->isNegative()) {
                    throw new InvalidPayrollData('Payroll input variable earnings, adjustments, and deductions cannot be negative.');
                }
            }
        }
    }
}
