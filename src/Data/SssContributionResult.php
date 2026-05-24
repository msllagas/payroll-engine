<?php

namespace QuillBytes\PayrollEngine\Data;

final readonly class SssContributionResult
{
    public function __construct(
        public PayrollLine $employee,
        public PayrollLine $employer,
    ) {}
}
