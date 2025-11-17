<?php

namespace Kantui\Support\Enums;

enum TodoType: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    /**
     * Get the opposite type (swap between TODO and IN_PROGRESS).
     */
    public function opposite(): self
    {
        return match ($this) {
            self::TODO => self::IN_PROGRESS,
            self::IN_PROGRESS => self::TODO,
            self::DONE => self::DONE,
        };
    }
}
