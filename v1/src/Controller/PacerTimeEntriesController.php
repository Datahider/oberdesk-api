<?php

declare(strict_types=1);

namespace losthost\OberdeskAPIv1\Controller;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use losthost\DB\DBView;
use RuntimeException;

final class PacerTimeEntriesController
{
    private readonly Closure $loader;

    public function __construct(?callable $loader = null)
    {
        $this->loader = $loader === null
            ? $this->loadFromDatabase(...)
            : Closure::fromCallable($loader);
    }

    public function handle(
        ?string $timer_id = null,
        ?string $project_group = null,
        ?string $from = null,
        ?string $to = null,
    ): array
    {
        $timer_id = $timer_id ?? ($_GET['timer_id'] ?? null);
        if (!is_string($timer_id) || filter_var($timer_id, FILTER_VALIDATE_INT) === false || (int) $timer_id <= 0) {
            throw new InvalidArgumentException('timer_id must be a positive integer');
        }
        $project_group = $project_group ?? ($_GET['project_group'] ?? null);
        if (!is_string($project_group) || $project_group === '' || strlen($project_group) > 100) {
            throw new InvalidArgumentException('project_group is required and must not exceed 100 bytes');
        }
        $from_date = $this->parseDate($from ?? ($_GET['from'] ?? null), 'from');
        $to_date = $this->parseDate($to ?? ($_GET['to'] ?? null), 'to');
        if ($to_date <= $from_date) {
            throw new InvalidArgumentException('to must be later than from');
        }

        $entries = [];
        foreach (($this->loader)((int) $timer_id, $project_group, $from_date, $to_date) as $entry) {
            if (!isset(
                $entry['id'],
                $entry['task_id'],
                $entry['task_type'],
                $entry['started_at'],
                $entry['ended_at'],
                $entry['duration_seconds'],
                $entry['is_running'],
            )) {
                throw new RuntimeException('Invalid time entry loaded from database');
            }
            if (!$entry['started_at'] instanceof DateTimeImmutable || !$entry['ended_at'] instanceof DateTimeImmutable) {
                throw new RuntimeException('Time entry dates must be DateTimeImmutable');
            }

            $started_at = $this->asMoscowWallTime($entry['started_at']);
            $ended_at = $this->asMoscowWallTime($entry['ended_at']);
            $entries[] = [
                'id' => (int) $entry['id'],
                'task_id' => (string) $entry['task_id'],
                'task_type' => (int) $entry['task_type'],
                'started_at' => $started_at->format(DATE_ATOM),
                'ended_at' => $ended_at->format(DATE_ATOM),
                'duration_seconds' => (int) $entry['duration_seconds'],
                'is_running' => (bool) $entry['is_running'],
            ];
        }

        return ['ok' => true, 'entries' => $entries];
    }

    private function parseDate(mixed $value, string $name): DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            throw new InvalidArgumentException("{$name} is required");
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw new InvalidArgumentException("{$name} must be ISO-8601", previous: $exception);
        }
        if ($date->format(DATE_ATOM) !== $value) {
            throw new InvalidArgumentException("{$name} must be ISO-8601");
        }

        return $date;
    }

    private function asMoscowWallTime(DateTimeImmutable $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d H:i:s'), new DateTimeZone('Europe/Moscow'));
    }

    private function loadFromDatabase(
        int $timer_id,
        string $project_group,
        DateTimeImmutable $from,
        DateTimeImmutable $to,
    ): array
    {
        $sql = <<<'SQL'
            SELECT
                events.id,
                events.object AS task_id,
                topics.type AS task_type,
                events.start_time AS started_at,
                CASE WHEN events.started = 1 THEN NOW() ELSE events.end_time END AS ended_at,
                CASE
                    WHEN events.started = 1 THEN TIMESTAMPDIFF(SECOND, events.start_time, NOW())
                    ELSE events.duration
                END AS duration_seconds,
                events.started AS is_running
            FROM [timer_events] AS events
            INNER JOIN [topics] AS topics
                ON topics.id = events.object
            WHERE events.timer = ?
                AND events.start_time >= ?
                AND events.start_time < ?
                AND EXISTS (
                    SELECT 1
                    FROM [chat_groups] AS groups
                    WHERE groups.chat_id = CAST(events.project AS SIGNED)
                        AND groups.chat_group = ?
                )
            ORDER BY events.start_time, events.id
            SQL;

        $view = new DBView($sql, [$timer_id, $from, $to, $project_group]);
        $entries = [];
        while ($view->next()) {
            if ($view->task_type === null) {
                throw new RuntimeException("Topic not found for timer event {$view->id}");
            }
            $entries[] = [
                'id' => (int) $view->id,
                'task_id' => (string) $view->task_id,
                'task_type' => (int) $view->task_type,
                'started_at' => $view->started_at,
                'ended_at' => $view->ended_at,
                'duration_seconds' => (int) $view->duration_seconds,
                'is_running' => (bool) $view->is_running,
            ];
        }

        return $entries;
    }
}
