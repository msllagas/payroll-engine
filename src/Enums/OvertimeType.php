<?php

namespace QuillBytes\PayrollEngine\Enums;

enum OvertimeType: string
{
    case Regular = 'regular';
    case RestDay = 'rest_day';
    case RegularHoliday = 'regular_holiday';
    case SpecialNonWorkingDay = 'special_non_working_day';
    case SpecialWorkingHoliday = 'special_working_holiday';
    case NightDifferential = 'night_differential';

    public static function normalize(string $type): ?self
    {
        return self::tryFrom(strtolower(trim($type)));
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type) => $type->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'Overtime Pay',
            self::RestDay => 'Rest Day Overtime',
            self::RegularHoliday => 'Regular Holiday Overtime',
            self::SpecialNonWorkingDay => 'Special Non-Working Day Overtime',
            self::SpecialWorkingHoliday => 'Special Working Holiday Overtime',
            self::NightDifferential => 'Night Differential',
        };
    }
}
