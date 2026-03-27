<?php

declare(strict_types=1);

namespace App\Heartbeat;

use App\Heartbeat\Model\ScheduledTask;

/**
 * Manages one-off scheduled tasks stored in heartbeat/scheduled.json.
 */
final class ScheduledTaskManager
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function __construct(
        private readonly string $filePath,
    ) {
        $this->load();
    }

    public function schedule(string $description, \DateTimeImmutable $runAt): ScheduledTask
    {
        $task = new ScheduledTask(
            id: 'sched-' . bin2hex(random_bytes(6)),
            description: $description,
            runAt: $runAt,
        );

        $this->tasks[] = $task;
        $this->save();

        return $task;
    }

    /**
     * @return list<ScheduledTask> Tasks that are due now
     */
    public function getDueTasks(): array
    {
        return array_values(array_filter(
            $this->tasks,
            static fn (ScheduledTask $t) => $t->isDue(),
        ));
    }

    /**
     * @return list<ScheduledTask>
     */
    public function getAll(): array
    {
        return $this->tasks;
    }

    public function remove(string $id): bool
    {
        $count = \count($this->tasks);
        $this->tasks = array_values(array_filter(
            $this->tasks,
            static fn (ScheduledTask $t) => $t->id !== $id,
        ));

        if (\count($this->tasks) < $count) {
            $this->save();

            return true;
        }

        return false;
    }

    private function load(): void
    {
        if (!is_file($this->filePath)) {
            return;
        }

        $json = file_get_contents($this->filePath) ?: '[]';
        $data = json_decode($json, true) ?: [];

        $this->tasks = array_map(
            static fn (array $d) => ScheduledTask::fromArray($d),
            $data,
        );
    }

    private function save(): void
    {
        $dir = \dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $this->filePath,
            json_encode(
                array_map(static fn (ScheduledTask $t) => $t->jsonSerialize(), $this->tasks),
                \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE,
            ),
        );
    }
}
