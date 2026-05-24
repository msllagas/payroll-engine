<?php

namespace QuillBytes\PayrollEngine\Data;

final readonly class PhilHealthContributionResult
{
    public function __construct(
        public PayrollLine $employee,
        public PayrollLine $employer,
    ) {}
}
