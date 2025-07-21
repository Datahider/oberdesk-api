<?php

namespace losthost\OberdeskAPI\functions;

use losthost\DB\DB;
use losthost\DB\DBView;

class getDashboardData extends AbstractFunctionImplementation {
    
    protected \DateTimeZone $tz;
    
    public function run(array $params): array {
    
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
            'agents' => $this->getAgents($params),
            'tickets' => $this->getTickets($params),
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


    protected function getAgents(array $params): array {
     
        $sql = <<<FIN
            SELECT DISTINCT 
                roles.user_id AS id,
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
                END) AS total_seconds_today
            FROM 
                [user_chat_role] AS roles
                INNER JOIN [telle_users] AS tg_users 
                    ON tg_users.id = roles.user_id
                LEFT JOIN [timers] AS timers
                    ON tg_users.id = timers.subject
                LEFT JOIN [timer_events] AS events
                    ON timers.id = events.timer AND events.start_time >= :current_date
                LEFT JOIN [timer_events] AS e2
                    ON timers.id = e2.timer AND e2.started = 1
            WHERE
                roles.chat_id IN ({{groups}})
                AND roles.role = 'agent'
            GROUP BY
                id, username, name, current_task_id
            FIN;
        
        $sql_groups = [];
        foreach ($params['groups'] as $value) {
            $sql_groups[] = (int)$value;
        }
        $sql = str_replace('{{groups}}', implode(',', $sql_groups), $sql);
        
        $today = date_create('today', $this->tz);
        $today->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $agents = new DBView($sql, ['current_date' => $today->format(DB::DATE_FORMAT)]);
        
        $agents_array = [];
        while ($agents->next()) {
            $agents_array[] = [
                'id' => $agents->id,
                'username' => $agents->username,
                'name' => $agents->name,
                'current_task_id' => $agents->current_task_id,
                'total_seconds_today' => $agents->total_seconds_today
            ];
        }
        return $agents_array;
    }

    protected function getTickets(array $params): array {
        $sql = <<<FIN
                SELECT 
                    id,
                    type,
                    CASE
                        WHEN type = 1 THEN '🎓️'
                        WHEN type = 2 THEN '⭐️'
                        WHEN type = 3 THEN '❗️'
                        WHEN type = 4 THEN '🗣'
                        WHEN type = 5 THEN '👑'
                        WHEN type = 6 THEN '‼️'
                        WHEN type = 7 THEN '🔥'
                        WHEN type = 8 THEN '🤖'
                        WHEN type = 9 THEN '🔞'
                        ELSE ''
                    END AS type_emoji, 
                    topic_title AS title,
                    chat_id,
                    status,
                    0 AS seconds_total,
                    0 AS seconds_today,
                    CASE 
                        WHEN last_admin_activity = 0 THEN NULL
                        ELSE DATE_FORMAT(CONVERT_TZ(DATE_ADD('1970-01-01 00:00:00', INTERVAL last_admin_activity SECOND), '+00:00', '{{correct_timezone}}'), '%Y-%m-%d %H:%i:%s')
                    END AS last_admin_activity,
                    CASE
                        WHEN last_activity = 0 THEN NULL
                        ELSE DATE_FORMAT(CONVERT_TZ(DATE_ADD('1970-01-01 00:00:00', INTERVAL last_activity SECOND), '+00:00', '{{correct_timezone}}'), '%Y-%m-%d %H:%i:%s') 
                    END AS last_activity
                FROM 
                    [topics] as t
                WHERE 
                    t.chat_id  IN ({{groups}}) 
                    AND (t.status NOT IN (111, 120)
                            OR t.last_activity >= :day_start_unix
                            OR t.last_admin_activity >= :day_start_unix
                    )
                FIN;

        $sql_groups = [];
        foreach ($params['groups'] as $value) {
            $sql_groups[] = (int)$value;
        }
        $sql = str_replace('{{groups}}', implode(',', $sql_groups), $sql);
        
        $sql_tz = date_create('now', $this->tz)->format('P');
        $sql = str_replace('{{correct_timezone}}', $sql_tz, $sql);
        
        $day_start_unix = date_create('today', $this->tz)->getTimestamp();
        $tickets = new DBView($sql, ['day_start_unix' => $day_start_unix]);
        
        $tickets_array = [];
        while ($tickets->next()) {
            $tickets_array[] = [
                'id' => $tickets->id,
                'type' => $tickets->type,
                'type_emoji' => $tickets->type_emoji,
                'title' => $tickets->title,
                'chat_id' => $tickets->chat_id,
                'status' => $tickets->status,
                'seconds_total' => $tickets->seconds_total,
                'seconds_today' => $tickets->seconds_today,
                'last_admin_activity' => $tickets->last_admin_activity,
                'last_activity' => $tickets->last_activity,
            ];
        }
        return $tickets_array;
        
    }
}
