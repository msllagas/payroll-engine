<?php

namespace QuillBytes\PayrollEngine\Enums;

enum StatutoryContributionSchedule: string
{
    case Monthly = 'monthly';
    case SplitPerCutoff = 'split_per_cutoff';
}
