<?php

namespace Kantui\Support\Enums;

enum TodoUrgency: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case IMPORTANT = 'important';
    case URGENT = 'urgent';

    /**
     * Get display label for the urgency level.
     */
    public function label(): string
    {
        return strtoupper($this->value);
    }
}
