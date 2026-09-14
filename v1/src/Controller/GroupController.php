<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\OberdeskAPIv1\StubData;

class GroupController
{
    public function handle(string $groupId): array
    {
        return [
            'ok' => true,
            'group' => StubData::getGroup($groupId),
        ];
    }
}
