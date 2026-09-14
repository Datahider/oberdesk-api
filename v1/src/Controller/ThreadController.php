<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\OberdeskAPIv1\StubData;

class ThreadController
{
    public function handle(string $threadId): array
    {
        return [
            'ok' => true,
            'thread' => StubData::getThread($threadId),
        ];
    }
}
