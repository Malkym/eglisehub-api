<?php

namespace App\Helpers;

use App\Models\Notification;
use App\Models\User;

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

    // Nouvelle méthode pour notifier tous les admins d'un ministère
    public static function notifyMinistryAdmins(
        int $ministereId,
        string $titre,
        string $message,
        string $type = 'info',
        ?string $lien = null,
        ?string $module = null
    ): void {
        $admins = User::where('ministere_id', $ministereId)
                      ->where('role', 'admin_ministere')
                      ->where('actif', true)
                      ->get();

        foreach ($admins as $admin) {
            self::send($admin->id, $titre, $message, $type, $lien, $module);
        }
    }

    // Nouvelle méthode pour notifier tous les super admins
    public static function notifySuperAdmins(
        string $titre,
        string $message,
        string $type = 'info',
        ?string $lien = null,
        ?string $module = null
    ): void {
        $superAdmins = User::where('role', 'super_admin')
                           ->where('actif', true)
                           ->get();

        foreach ($superAdmins as $admin) {
            self::send($admin->id, $titre, $message, $type, $lien, $module);
        }
    }
}