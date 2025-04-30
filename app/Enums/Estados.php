<?php

namespace App\Enums;

enum Estados: string
{
    case PENDING = 'pending';
    case ASSIGNED = 'assigned';
    case ACCEPTED = 'accepted';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}