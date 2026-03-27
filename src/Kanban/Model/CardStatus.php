<?php

declare(strict_types=1);

namespace App\Kanban\Model;

/**
 * Kanban card statuses matching the default board columns.
 */
enum CardStatus: string
{
    case BACKLOG = 'backlog';
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case REVIEW = 'review';
    case DONE = 'done';
}
