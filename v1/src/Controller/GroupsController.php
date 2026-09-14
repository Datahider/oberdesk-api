<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\DB\DBView;
use losthost\OberdeskAPIv1\Auth;

class GroupsController
{
    private const SQL_GET_GROUPS = <<<SQL
        SELECT 
            c.id,
            c.title,
            COUNT(t.id) AS total_topics,
            SUM(CASE WHEN t.status = 88 THEN 1 ELSE 0 END) AS waiting_user
        FROM [chat_user] cu
        JOIN [telle_chats] c 
            ON c.id = cu.chat_id
        LEFT JOIN [topics] t 
            ON t.chat_id = c.id
            AND t.status != 111
        WHERE 
            cu.user_id = ?
            AND c.type = 'supergroup'
        GROUP BY c.id, c.title
        ORDER BY c.title
    SQL;

    public function handle(): array
    {
        $user = Auth::get();
        $myGroups = new DBView(self::SQL_GET_GROUPS, $user['telegram_user_id']);

        $groups = [];

        while ($myGroups->next()) {
            $groups[] = [
                'id' => (int)$myGroups->id,
                'title' => (string)$myGroups->title,
                'total_topics' => (int)$myGroups->total_topics,
                'waiting_user' => (int)$myGroups->waiting_user,
                'avatar_url' => null,
            ];
        }

        return [
            'ok' => true,
            'items' => $groups,
        ];
    }
}