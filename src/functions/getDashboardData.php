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

        $today = date_create('today', $this->tz);
        $today->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $day_start_unix = date_create('today', $this->tz)->getTimestamp();
        
        $tickets = new DBView($sql, [
            'day_start_unix' => $day_start_unix,
            'current_date' => $today->format(DB::DATE_FORMAT)    
        ]);
        
        $tickets_array = [];
        while ($tickets->next()) {
            $ticket_data = [
                'id' => $tickets->id,
                'type' => $tickets->type,
                'type_emoji' => $tickets->type_emoji,
                'title' => $tickets->title,
                'link' => $tickets->topic_link,
                'chat_id' => $tickets->chat_id,
                'status' => $tickets->status,
                'seconds_total' => 0,
                'seconds_today' => 0,
                'last_admin_activity' => $tickets->last_admin_activity,
                'last_activity' => $tickets->last_activity,
                'agents' => $this->getTicketAgentData($tickets->id),
            ];
            
            foreach ($ticket_data['agents'] as $agent_data) {
                $ticket_data['seconds_total'] += $agent_data['seconds_total'] ?? 0;
                $ticket_data['seconds_today'] += $agent_data['seconds_today'] ?? 0;
            }
            $tickets_array[] = $ticket_data;
        }
        
        
        return $tickets_array;
        
    }
    
    protected function getTicketAgentData(int $ticket_id): array {
        
        $sql = <<<FIN
                DROP TEMPORARY TABLE IF EXISTS vt_bound_agents;
                
                CREATE TEMPORARY TABLE vt_bound_agents SELECT
                    user_id AS agent_id
                FROM 
                    [topic_admins]
                WHERE
                    topic_number = :ticket_id
                
                UNION
                
                SELECT DISTINCT
                    subject
                FROM 
                    [timers] AS timers
                    INNER JOIN [timer_events] AS events ON timers.id = events.timer
                WHERE
                    events.object = :ticket_id;
                
                SELECT
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
                    SUM(DISTINCT CASE
                        WHEN e0.started = 0 THEN e0.duration
                        ELSE TIMESTAMPDIFF(SECOND, e0.start_time, NOW()) 
                    END) AS seconds_total,
                    SUM(DISTINCT CASE
                        WHEN e1.started = 0 THEN e1.duration
                        ELSE TIMESTAMPDIFF(SECOND, e1.start_time, NOW()) 
                    END) AS seconds_today
                    
                FROM
                    vt_bound_agents AS bound
                    LEFT JOIN [topic_admins] AS ta ON ta.user_id = bound.agent_id AND ta.topic_number = :ticket_id
                    INNER JOIN [telle_users] AS tg_users ON tg_users.id = bound.agent_id
                    LEFT JOIN [timers] AS timers ON tg_users.id = timers.subject
                    LEFT JOIN [timer_events] AS e0 ON timers.id = e0.timer AND e0.object = :ticket_id 
                    LEFT JOIN [timer_events] AS e1 ON timers.id = e1.timer AND e1.object = :ticket_id AND e1.start_time >= :current_date
                
                GROUP BY
                    id, bound, username, name
                ;

                DROP TEMPORARY TABLE vt_bound_agents;
                
                FIN;
        
        $today = date_create('today', $this->tz);
        $today->setTimezone(new \DateTimeZone(date_default_timezone_get()));

        $sth = DB::prepare($sql);
        $sth->execute([
            'ticket_id' => $ticket_id,
            'current_date' => $today->format(DB::DATE_FORMAT)    
        ]);
        
        $sth->nextRowset();
        $sth->nextRowset();
        
        return $sth->fetchAll(\PDO::FETCH_ASSOC);
    }
}
