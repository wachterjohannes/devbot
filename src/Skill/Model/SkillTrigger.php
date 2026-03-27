<?php

declare(strict_types=1);

namespace App\Skill\Model;

/**
 * How a skill is triggered.
 */
enum SkillTrigger: string
{
    /** Run only when explicitly requested. */
    case MANUAL = 'manual';

    /** Run on a cron schedule via heartbeat. */
    case CRON = 'cron';

    /** Run at a fixed interval via heartbeat. */
    case INTERVAL = 'interval';

    /** Run when a specific event occurs. */
    case EVENT = 'event';
}
