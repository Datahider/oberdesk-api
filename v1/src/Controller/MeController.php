<?php

namespace losthost\OberdeskAPIv1\Controller;

use losthost\OberdeskAPIv1\Auth;

class MeController
{
    public function handle(): array
    {
        return [
            'ok' => true,
            'user' => Auth::get()
        ];
    }
}