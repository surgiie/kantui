<?php

namespace Kantui\Support\Enums;

enum TodoType: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
}
