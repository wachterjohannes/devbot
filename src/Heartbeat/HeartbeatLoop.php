<?php

declare(strict_types=1);

namespace App\Heartbeat;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

/**
 * Fiber-based heartbeat loop that ticks alongside the TUI.
 * Checks for due skills and scheduled tasks on each tick.
 */
final class HeartbeatLoop
{
    private ?string $callbackId = null;
    private bool $running = false;

    public function __construct(
        private readonly TaskScheduler $scheduler,
        private readonly TaskExecutor $executor,
        private readonly int $tickInterval = 30,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Start the heartbeat loop in the Revolt event loop.
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }

        $this->running = true;
        $this->callbackId = EventLoop::repeat($this->tickInterval, function (): void {
            $this->tick();
        });
    }

    /**
     * Stop the heartbeat loop.
     */
    public function stop(): void
    {
        $this->running = false;

        if ($this->callbackId !== null) {
            EventLoop::cancel($this->callbackId);
            $this->callbackId = null;
        }
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Run one heartbeat tick — check and execute due tasks.
     */
    public function tick(): void
    {
        // Execute due skills
        foreach ($this->scheduler->getDueSkills() as $skill) {
            try {
                $this->executor->executeSkill($skill);
            } catch (\Throwable $e) {
                $this->logger->error('Heartbeat skill "{name}" failed: {error}', [
                    'name' => $skill->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Execute due scheduled tasks
        foreach ($this->scheduler->getDueScheduledTasks() as $task) {
            try {
                $this->executor->executeScheduledTask($task);
            } catch (\Throwable $e) {
                $this->logger->error('Heartbeat scheduled task failed: {error}', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
