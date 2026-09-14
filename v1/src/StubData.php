<?php

namespace losthost\OberdeskAPIv1;

class StubData
{
    public static function getGroup(string $groupId): array
    {
        return [
            'id' => $groupId,
            'title' => 'Группа ' . self::getShortId($groupId),
            'subtitle' => 'Тестовые данные для экрана группы',
            'avatar_url' => null,
        ];
    }

    public static function getThreads(string $groupId): array
    {
        return [
            self::makeThread($groupId, 1, 'Первый диалог', 'Клиент ждет ответа', 3),
            self::makeThread($groupId, 2, 'Новый заказ', 'Нужно уточнить детали', 0),
            self::makeThread($groupId, 3, 'Оплата и документы', 'Последнее сообщение 10 минут назад', 1),
            self::makeThread($groupId, 4, 'Монтаж', 'Согласование даты выезда', 0),
        ];
    }

    public static function getThread(string $threadId): array
    {
        [$groupId, $threadNumber] = self::parseThreadId($threadId);

        return [
            'id' => $threadId,
            'group_id' => $groupId,
            'title' => 'Тема #' . $threadNumber,
            'subtitle' => 'Тестовый тред для проверки интерфейса',
            'avatar_url' => null,
        ];
    }

    public static function getMessages(string $threadId): array
    {
        [$groupId, $threadNumber] = self::parseThreadId($threadId);
        $shortGroupId = self::getShortId($groupId);

        return [
            [
                'id' => $threadId . '--m1',
                'author_name' => 'Клиент',
                'direction' => 'incoming',
                'text' => 'Добрый день. Это тестовая переписка по группе ' . $shortGroupId . '.',
                'created_at' => 'Сегодня, 10:15',
            ],
            [
                'id' => $threadId . '--m2',
                'author_name' => 'Oberdesk',
                'direction' => 'outgoing',
                'text' => 'Здравствуйте. Видим сообщение и проверяем, как выглядит интерфейс треда.',
                'created_at' => 'Сегодня, 10:17',
            ],
            [
                'id' => $threadId . '--m3',
                'author_name' => 'Клиент',
                'direction' => 'incoming',
                'text' => 'Отлично. Тред #' . $threadNumber . ' тоже должен показываться нормально.',
                'created_at' => 'Сегодня, 10:19',
            ],
        ];
    }

    private static function makeThread(
        string $groupId,
        int $number,
        string $title,
        string $subtitle,
        int $unreadCount
    ): array {
        return [
            'id' => self::buildThreadId($groupId, $number),
            'group_id' => $groupId,
            'title' => $title,
            'subtitle' => $subtitle,
            'avatar_url' => null,
            'unread_count' => $unreadCount,
        ];
    }

    private static function buildThreadId(string $groupId, int $threadNumber): string
    {
        return $groupId . '--' . $threadNumber;
    }

    private static function parseThreadId(string $threadId): array
    {
        if (preg_match('/^(.*)--(\d+)$/', $threadId, $matches)) {
            return [$matches[1], (int)$matches[2]];
        }

        return ['0', 1];
    }

    private static function getShortId(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === '') {
            return $value;
        }

        return substr($digits, -4);
    }
}
