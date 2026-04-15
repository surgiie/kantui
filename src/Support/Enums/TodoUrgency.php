<?php

namespace Kantui\Support\Enums;

/**
 * Represents the urgency levels for todo items.
 *
 * This enum defines four urgency levels: LOW, NORMAL, IMPORTANT, and URGENT.
 * Each urgency level has a corresponding color representation and label for display.
 */
enum TodoUrgency: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    /**
     * Get display label for the urgency level.
     *
     * Returns the urgency level as an uppercase string for display in the UI.
     *
     * @return string The uppercase urgency label
     */
    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Get a value => label map of all urgency options for use in select prompts.
     *
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn ($case) => $case->label(), self::cases())
        );
    }
}
