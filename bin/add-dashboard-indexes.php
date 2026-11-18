<?php

declare(strict_types=1);

use losthost\DB\DB;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/etc/db.php';

DB::exec(<<<SQL
    ALTER TABLE [timer_events]
        ADD INDEX dashboard_object_timer (object, timer),
        ADD INDEX dashboard_timer_start (timer, start_time),
        ADD INDEX dashboard_end_comment_timer (end_time, comment, timer)
    SQL);

DB::exec(<<<SQL
    ALTER TABLE [timers]
        ADD INDEX dashboard_subject (subject, id)
    SQL);

DB::exec(<<<SQL
    ALTER TABLE [user_chat_role]
        ADD INDEX dashboard_chat_role_user (chat_id, role, user_id)
    SQL);

echo "OK\n";
