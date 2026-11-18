<?php

declare(strict_types=1);

use losthost\OberdeskAPI\functions\get\getDashboardData;

require dirname(__DIR__) . '/src/functions/AbstractFunctionImplementation.php';
require dirname(__DIR__) . '/src/functions/get/getDashboardData.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

assertSameValue(true, method_exists(getDashboardData::class, 'loadAgents'), 'Dashboard exposes a bulk agent loader');
assertSameValue(true, method_exists(getDashboardData::class, 'loadTickets'), 'Dashboard exposes a bulk ticket loader');
assertSameValue(true, method_exists(getDashboardData::class, 'loadTicketAgents'), 'Dashboard exposes a bulk ticket-agent loader');

final class TestDashboardData extends getDashboardData
{
    public array $calls = [];

    protected function loadAgents(array $params): array
    {
        $this->calls[] = 'agents';
        return [['id' => 1, 'name' => 'Agent', 'sbna_month' => 2]];
    }

    protected function loadTickets(array $params): array
    {
        $this->calls[] = 'tickets';
        return [
            ['id' => 10, 'title' => 'First'],
            ['id' => 20, 'title' => 'Second'],
        ];
    }

    protected function loadTicketAgents(array $params): array
    {
        $this->calls[] = 'ticket_agents';
        return [
            ['ticket_id' => 10, 'id' => 1, 'seconds_total' => 100, 'seconds_today' => 40],
            ['ticket_id' => 10, 'id' => 2, 'seconds_total' => 50, 'seconds_today' => null],
        ];
    }
}

$dashboard = new TestDashboardData();
$params = ['groups' => ['-1001'], 'tz' => 'Asia/Tashkent'];
$dashboard->checkParams($params);
$result = $dashboard->run($params);

assertSameValue(['agents', 'tickets', 'ticket_agents'], $dashboard->calls, 'Each bulk loader is called exactly once');
assertSameValue($params, $result['params'], 'Request params are preserved');
assertSameValue(1, count($result['agents']), 'Agents are preserved');
assertSameValue(2, count($result['tickets']), 'Tickets are preserved');
assertSameValue(2, count($result['tickets'][0]['agents']), 'Agents are grouped under their ticket');
assertSameValue(150, $result['tickets'][0]['seconds_total'], 'Total time is summed for the ticket');
assertSameValue(40, $result['tickets'][0]['seconds_today'], 'Today time treats null as zero');
assertSameValue([], $result['tickets'][1]['agents'], 'Ticket without agents has an empty agent list');
assertSameValue(0, $result['tickets'][1]['seconds_total'], 'Ticket without agents has zero total time');
assertSameValue(0, $result['tickets'][1]['seconds_today'], 'Ticket without agents has zero today time');

$source = file_get_contents(dirname(__DIR__) . '/src/functions/get/getDashboardData.php');
assertSameValue(false, str_contains($source, 'TEMPORARY TABLE'), 'Dashboard does not use temporary tables');
assertSameValue(false, str_contains($source, 'getTicketAgentData'), 'Dashboard does not load agents per ticket');

$migration_path = dirname(__DIR__) . '/bin/add-dashboard-indexes.php';
assertSameValue(true, is_file($migration_path), 'Dashboard index migration exists');
$migration = file_get_contents($migration_path);
foreach (['dashboard_object_timer', 'dashboard_timer_start', 'dashboard_end_comment_timer', 'dashboard_subject', 'dashboard_chat_role_user'] as $index_name) {
    assertSameValue(true, str_contains($migration, $index_name), "Migration creates {$index_name}");
}

echo "OK\n";
