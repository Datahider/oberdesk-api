<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\OberdeskAPIv1\StubData;

class ThreadMessagesController
{
    public function handle(string $threadId): array
    {
        return [
            'ok' => true,
            'items' => StubData::getMessages($threadId),
        ];
    }
}
