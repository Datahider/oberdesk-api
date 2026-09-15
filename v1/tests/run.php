<?php

declare(strict_types=1);

use losthost\OberdeskAPIv1\Controller\PacerTimeEntriesController;

require dirname(__DIR__) . '/src/Controller/PacerTimeEntriesController.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function assertThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

$loader_calls = [];
$loader = static function (int $timer_id, array $project_groups, DateTimeImmutable $from, DateTimeImmutable $to) use (&$loader_calls): array {
    $loader_calls[] = [$timer_id, $project_groups, $from->format(DATE_ATOM), $to->format(DATE_ATOM)];
    return [
        [
            'id' => 10,
            'task_id' => '501',
            'task_type' => 2,
            'started_at' => new DateTimeImmutable('2026-09-02T10:00:00+00:00'),
            'ended_at' => new DateTimeImmutable('2026-09-02T11:30:00+00:00'),
            'duration_seconds' => 5400,
            'is_running' => false,
        ],
    ];
};

$resolver_calls = [];
$resolver = static function (int $subject) use (&$resolver_calls): int {
    $resolver_calls[] = $subject;
    return 17;
};
$controller = new PacerTimeEntriesController($loader, $resolver);
$result = $controller->handle('203645978', ['comm', 'personal'], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00');
assertSameValue(true, $result['ok'], 'Response is successful');
assertSameValue(1, count($result['entries']), 'Response contains entries');
assertSameValue(2, $result['entries'][0]['task_type'], 'Task type is preserved');
assertSameValue('2026-09-02T10:00:00+03:00', $result['entries'][0]['started_at'], 'Database wall time is interpreted as Moscow time');
assertSameValue('2026-09-02T11:30:00+03:00', $result['entries'][0]['ended_at'], 'End uses Moscow time');
assertSameValue(5400, $result['entries'][0]['duration_seconds'], 'Duration uses seconds');
assertSameValue([203645978], $resolver_calls, 'Authenticated subject is passed to timer resolver');
assertSameValue([[17, ['comm', 'personal'], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00']], $loader_calls, 'Resolved timer, filters and period are passed to loader');

assertThrows(static fn () => $controller->handle('', ['comm'], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00'), 'Subject is required');
assertThrows(static fn () => $controller->handle('0', ['comm'], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00'), 'Subject must be positive');
assertThrows(static fn () => $controller->handle('1', [], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00'), 'Project groups are required');
assertThrows(static fn () => $controller->handle('1', ['comm', 'comm'], '2026-09-01T00:00:00+03:00', '2026-10-01T00:00:00+03:00'), 'Project groups must be unique');
assertThrows(static fn () => $controller->handle('1', ['comm'], 'invalid', '2026-10-01T00:00:00+03:00'), 'From must be ISO-8601');
assertThrows(static fn () => $controller->handle('1', ['comm'], '2026-10-01T00:00:00+03:00', '2026-09-01T00:00:00+03:00'), 'To must follow from');

$index = file_get_contents(dirname(__DIR__) . '/index.php');
assertSameValue(true, str_contains($index, "#^/pacer/time-entries$#"), 'Router exposes pacer endpoint');

echo "OK\n";
