<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\OberdeskAPIv1\StubData;

class GroupThreadsController
{
    public function handle(string $groupId): array
    {
        return [
            'ok' => true,
            'items' => StubData::getThreads($groupId),
        ];
    }
}
