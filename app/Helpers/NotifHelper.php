<?php

namespace App\Helpers;

use App\Models\Notification;

class NotifHelper
{
    public static function send(
        int $userId,
        string $titre,
        string $message,
        string $type = 'info',
        ?string $lien = null,
        ?string $module = null
    ): void {
        Notification::create([
            'user_id' => $userId,
            'type'    => $type,
            'titre'   => $titre,
            'message' => $message,
            'lien'    => $lien,
            'module'  => $module,
            'lu'      => false,
        ]);
    }
}