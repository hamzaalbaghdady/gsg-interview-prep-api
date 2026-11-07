<?php

namespace App\Enums;

enum InterviewStatus: string
{
    case PREPARING = 'preparing';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}
