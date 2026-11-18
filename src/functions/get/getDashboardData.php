<?php

namespace losthost\OberdeskAPI\functions\get;

use losthost\DB\DBView;
use losthost\OberdeskAPI\functions\AbstractFunctionImplementation;
use RuntimeException;

class getDashboardData extends AbstractFunctionImplementation {
    
    protected \DateTimeZone $tz; 
    
    public function run(array $params): array {
        $agents = $this->loadAgents($params);
        $tickets = $this->loadTickets($params);
        $ticket_indexes = [];
        foreach ($tickets as $ticket_index => $ticket) {
            $tickets[$ticket_index]['seconds_total'] = 0;
            $tickets[$ticket_index]['seconds_today'] = 0;
            $tickets[$ticket_index]['agents'] = [];
            $ticket_indexes[(string) $ticket['id']] = $ticket_index;
        }

        foreach ($this->loadTicketAgents($params) as $agent) {
            $ticket_id = (string) $agent['ticket_id'];
            if (!array_key_exists($ticket_id, $ticket_indexes)) {
                throw new RuntimeException("Agent data returned for unknown ticket {$ticket_id}");
            }
            $ticket_index = $ticket_indexes[$ticket_id];
            unset($agent['ticket_id']);
            $tickets[$ticket_index]['seconds_total'] += $agent['seconds_total'] ?? 0;
            $tickets[$ticket_index]['seconds_today'] += $agent['seconds_today'] ?? 0;
            $tickets[$ticket_index]['agents'][] = $agent;
        }

        $result = [
            'params' => $params,
            'statuses' => [
                0 => 'Новый',
                1 => 'В работе',
                88 => 'Ожидает ответа',
                89 => 'Пользователь ответил',
                101 => 'Создается',
                102 => 'Переоткрыт',
                111 => 'Закрыт',
                120 => 'Архивный',
            ],
            'agents' => $agents,
            'tickets' => $tickets,
        ];
        return $result;
    }

    public function checkParams(array $params): true {
        
        $this->checkParamGroups($params);
        $this->checkParamTZ($params);
        return true;
    }
    
    protected function checkParamGroups(array $params): true {
        if (!isset($params['groups'])) {
            throw new \Exception('Не задан обязательный параметр "groups"');
        } elseif (!is_array ($params['groups'])) {
            error_log($params['groups']);
            throw new \Exception('Параметр "groups" должен быть задан массивом индентификаторов групп');
        }
        return true;
    }
    
    protected function checkParamTZ(array $params): true {
        if (isset($params['tz'])) {
            $this->tz = new \DateTimeZone($params['tz']);
        } else {
            $this->tz = new \DateTimeZone('Europe/Moscow');
        }
        
        return true;
    }


    protected function loadAgents(array $params): array {
        $sql = <<<FIN
            SELECT 
                agents.agent_id AS id,
                CASE
                    WHEN tg_users.username IS NULL THEN NULL
                    ELSE CONCAT('@', tg_users.username)
                END AS username,
                CASE 
                    WHEN tg_users.last_name IS NULL THEN tg_users.first_name
                    ELSE CONCAT(tg_users.first_name, ' ', tg_users.last_name)
                END AS name,
                e2.object AS current_task_id,
                SUM(CASE
                    WHEN events.started = 0 THEN events.duration
                    ELSE TIMESTAMPDIFF(SECOND, events.start_time, NOW()) 
                END) AS total_seconds_today,
                SUM(CASE
                    WHEN events.comment = 'No AcTiViTy' THEN 1
                    ELSE 0
                END) AS sbna_today,
                COALESCE(monthly_sbna.total, 0) AS sbna_month
            FROM 
                (
                    SELECT DISTINCT user_id AS agent_id
                    FROM sprt_user_chat_role
                    WHERE chat_id IN ({{groups}})
                        AND role = 'agent'
                ) AS agents
                INNER JOIN [telle_users] AS tg_users 
                    ON tg_users.id = agents.agent_id
                LEFT JOIN [timers] AS timers
                    ON tg_users.id = timers.subject
                LEFT JOIN [timer_events] AS events
                    ON timers.id = events.timer AND events.start_time >= :current_date
                LEFT JOIN [timer_events] AS e2
                    ON timers.id = e2.timer AND e2.started = 1
                LEFT JOIN (
                    SELECT monthly_timers.subject, COUNT(monthly_events.comment) AS total
                    FROM [timers] AS monthly_timers
                    INNER JOIN [timer_events] AS monthly_events
                        ON monthly_events.timer = monthly_timers.id
                    WHERE monthly_events.end_time >= :month_start
                        AND monthly_events.comment = 'No AcTiViTy'
                    GROUP BY monthly_timers.subject
                ) AS monthly_sbna
                    ON monthly_sbna.subject = agents.agent_id
            GROUP BY
                id, username, name, current_task_id, monthly_sbna.total
            FIN;
        
        $sql_groups = $this->normalizeGroups($params);
        $sql = str_replace('{{groups}}', implode(',', $sql_groups), $sql);
        
        $today = date_create('today', $this->tz);
        $today->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        
        $agents = new DBView($sql, [
            'current_date' => $today->format('Y-m-d H:i:s'),
            'month_start' => $today->modify('first day of this month')->setTime(0, 0, 0)->format('Y-m-d H:i:s'),
        ]);
        $result = [];
        while ($agents->next()) {
            $result[] = [
                'id' => $agents->id,
                'username' => $agents->username,
                'name' => $agents->name,
                'current_task_id' => $agents->current_task_id,
                'total_seconds_today' => $agents->total_seconds_today,
                'sbna_today' => $agents->sbna_today,
                'sbna_month' => $agents->sbna_month,
            ];
        }
        return $result;
    }

    protected function loadTickets(array $params): array {
        $sql = <<<FIN
                SELECT 
                    t.id,
                    t.type,
                    CASE
                        WHEN t.type = 1 THEN '🎓️'
                        WHEN t.type = 2 THEN '⭐️'
                        WHEN t.type = 3 THEN '❗️'
                        WHEN t.type = 4 THEN '🗣'
                        WHEN t.type = 5 THEN '👑'
                        WHEN t.type = 6 THEN '‼️'
                        WHEN t.type = 7 THEN '🔥'
                        WHEN t.type = 8 THEN '🤖'
                        WHEN t.type = 9 THEN '🔞'
                        ELSE ''
                    END AS type_emoji, 
                    t.topic_title AS title,
                    CONCAT('https://t.me/c/', SUBSTRING(t.chat_id, 5), '/', t.topic_id) AS topic_link,
                    t.chat_id,
                    t.status,
                    CASE 
                        WHEN t.last_admin_activity = 0 THEN NULL
                        ELSE DATE_FORMAT(CONVERT_TZ(DATE_ADD('1970-01-01 00:00:00', INTERVAL t.last_admin_activity SECOND), '+00:00', '{{correct_timezone}}'), '%Y-%m-%d %H:%i:%s')
                    END AS last_admin_activity,
                    CASE
                        WHEN t.last_activity = 0 THEN NULL
                        ELSE DATE_FORMAT(CONVERT_TZ(DATE_ADD('1970-01-01 00:00:00', INTERVAL t.last_activity SECOND), '+00:00', '{{correct_timezone}}'), '%Y-%m-%d %H:%i:%s') 
                    END AS last_activity,
                    DATE_FORMAT(CONVERT_TZ(t.created, '+03:00', '{{correct_timezone}}'), '%Y-%m-%d %H:%i:%s') AS created
                FROM 
                    [topics] as t
                WHERE 
                    t.chat_id  IN ({{groups}}) 
                    AND (t.status NOT IN (111, 120)
                            OR t.last_activity >= :day_start_unix
                            OR t.last_admin_activity >= :day_start_unix
                    )
                FIN;

        $sql_groups = $this->normalizeGroups($params);
        $sql = str_replace('{{groups}}', implode(',', $sql_groups), $sql);
        
        $sql_tz = date_create('now', $this->tz)->format('P');
        $sql = str_replace('{{correct_timezone}}', $sql_tz, $sql);
        
        $day_start_unix = date_create('today', $this->tz)->getTimestamp();
        
        $tickets = new DBView($sql, [
            'day_start_unix' => $day_start_unix,
        ]);
        
        $result = [];
        while ($tickets->next()) {
            $result[] = [
                'id' => $tickets->id,
                'type' => $tickets->type,
                'type_emoji' => $tickets->type_emoji,
                'title' => $tickets->title,
                'link' => $tickets->topic_link,
                'chat_id' => $tickets->chat_id,
                'status' => $tickets->status,
                'last_admin_activity' => $tickets->last_admin_activity,
                'last_activity' => $tickets->last_activity,
                'created' => $tickets->created,
            ];
        }
        return $result;
    }

    protected function loadTicketAgents(array $params): array {
        $sql = <<<FIN
                SELECT
                    bound.ticket_id,
                    bound.agent_id AS id,
                    CASE 
                        WHEN ta.user_id IS NULL THEN FALSE
                        ELSE TRUE
                    END AS bound,
                    CASE
                        WHEN tg_users.username IS NULL THEN NULL
                        ELSE CONCAT('@', tg_users.username)
                    END AS username,
                    CASE 
                        WHEN tg_users.last_name IS NULL THEN tg_users.first_name
                        ELSE CONCAT(tg_users.first_name, ' ', tg_users.last_name)
                    END AS name,
                    SUM(CASE
                        WHEN e0.started = 0 THEN e0.duration
                        ELSE TIMESTAMPDIFF(SECOND, e0.start_time, NOW()) 
                    END) AS seconds_total,
                    SUM(CASE
                        WHEN e0.start_time < :current_date THEN 0
                        WHEN e0.started = 0 THEN e0.duration
                        ELSE TIMESTAMPDIFF(SECOND, e0.start_time, NOW()) 
                    END) AS seconds_today
                FROM
                    (
                        SELECT topics.id AS ticket_id, admins.user_id AS agent_id
                        FROM [topics] AS topics
                        INNER JOIN [topic_admins] AS admins
                            ON admins.topic_number = topics.id
                        WHERE topics.chat_id IN ({{groups}})
                            AND (
                                topics.status NOT IN (111, 120)
                                OR topics.last_activity >= :admin_day_start_unix
                                OR topics.last_admin_activity >= :admin_day_start_unix
                            )

                        UNION

                        SELECT topics.id AS ticket_id, event_timers.subject AS agent_id
                        FROM [topics] AS topics
                        INNER JOIN [timer_events] AS bound_events
                            ON bound_events.object = topics.id
                        INNER JOIN [timers] AS event_timers
                            ON event_timers.id = bound_events.timer
                        WHERE topics.chat_id IN ({{groups}})
                            AND (
                                topics.status NOT IN (111, 120)
                                OR topics.last_activity >= :event_day_start_unix
                                OR topics.last_admin_activity >= :event_day_start_unix
                            )
                    ) AS bound
                    LEFT JOIN [topic_admins] AS ta ON ta.user_id = bound.agent_id AND ta.topic_number = bound.ticket_id
                    INNER JOIN [telle_users] AS tg_users ON tg_users.id = bound.agent_id
                    LEFT JOIN [timers] AS timers ON tg_users.id = timers.subject
                    LEFT JOIN [timer_events] AS e0 ON timers.id = e0.timer AND e0.object = bound.ticket_id
                
                GROUP BY
                    bound.ticket_id, id, bound, username, name
                FIN;

        $sql_groups = $this->normalizeGroups($params);
        $sql = str_replace('{{groups}}', implode(',', $sql_groups), $sql);
        $today = date_create('today', $this->tz);
        $today->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $day_start_unix = date_create('today', $this->tz)->getTimestamp();

        $agents = new DBView($sql, [
            'admin_day_start_unix' => $day_start_unix,
            'event_day_start_unix' => $day_start_unix,
            'current_date' => $today->format('Y-m-d H:i:s'),
        ]);
        $result = [];
        while ($agents->next()) {
            $result[] = [
                'ticket_id' => $agents->ticket_id,
                'id' => $agents->id,
                'bound' => $agents->bound,
                'username' => $agents->username,
                'name' => $agents->name,
                'seconds_total' => $agents->seconds_total,
                'seconds_today' => $agents->seconds_today,
            ];
        }
        return $result;
    }

    private function normalizeGroups(array $params): array
    {
        $groups = [];
        foreach ($params['groups'] as $value) {
            $groups[] = (int) $value;
        }
        return $groups;
    }
}
