<?php

namespace Kantui\Support\Enums;

/**
 * Represents the different states a todo item can be in.
 *
 * This enum defines the three possible states for a todo item in the kanban board:
 * TODO, IN_PROGRESS, and DONE. Each state is stored as a lowercase string value.
 */
enum TodoType: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';

    /**
     * Get the opposite type (swap between TODO and IN_PROGRESS).
     *
     * Returns the opposite todo type for toggling between states.
     * TODO returns IN_PROGRESS, IN_PROGRESS returns TODO, and DONE returns itself.
     *
     * @return self The opposite todo type
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
