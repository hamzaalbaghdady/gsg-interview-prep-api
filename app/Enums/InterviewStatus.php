<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case READY = 'ready';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
