<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\NotifHelper;

class LogAction extends Model
{
    protected $table = 'logs_actions';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'ministere_id',
        'action',
        'module',
        'details',
        'ip',
        'date_action',
    ];

    protected $casts = [
        'date_action' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ministere()
    {
        return $this->belongsTo(Ministere::class);
    }

    // Méthode pour envoyer des notifications selon l'action
    public static function notifyForAction(string $action, array $data)
    {
        $notifications = [
            // Actions qui notifient les admins du ministère
            'create_article' => [
                'admins' => true,
                'super' => false,
                'titre' => ' Nouvel article créé',
                'type' => 'info',
                'module' => 'articles'
            ],
            'create_page' => [
                'admins' => true,
                'super' => false,
                'titre' => ' Nouvelle page créée',
                'type' => 'info',
                'module' => 'pages'
            ],
            'create_event' => [
                'admins' => true,
                'super' => false,
                'titre' => ' Nouvel événement créé',
                'type' => 'info',
                'module' => 'events'
            ],
            'upload_media' => [
                'admins' => true,
                'super' => false,
                'titre' => ' Nouveau média uploadé',
                'type' => 'info',
                'module' => 'media'
            ],
            
            // Actions critiques qui notifient les super admins
            'create_ministere' => [
                'admins' => false,
                'super' => true,
                'titre' => ' Nouveau ministère créé',
                'type' => 'success',
                'module' => 'ministeres'
            ],
            'update_ministere' => [
                'admins' => true,
                'super' => true,
                'titre' => ' Ministère modifié',
                'type' => 'info',
                'module' => 'ministeres'
            ],
            'toggle_ministere' => [
                'admins' => true,
                'super' => true,
                'titre' => ' Statut ministère changé',
                'type' => 'warning',
                'module' => 'ministeres'
            ],
            'impersonate_user' => [
                'admins' => false,
                'super' => true,
                'titre' => ' Impersonation utilisateur',
                'type' => 'warning',
                'module' => 'users'
            ],
            'delete_article' => [
                'admins' => true,
                'super' => true,
                'titre' => ' Article supprimé',
                'type' => 'error',
                'module' => 'articles'
            ],
            'delete_page' => [
                'admins' => true,
                'super' => true,
                'titre' => ' Page supprimée',
                'type' => 'error',
                'module' => 'pages'
            ],
            'delete_event' => [
                'admins' => true,
                'super' => true,
                'titre' => ' Événement supprimé',
                'type' => 'error',
                'module' => 'events'
            ],
        ];

        if (isset($notifications[$action])) {
            $config = $notifications[$action];
            
            // Notifier les admins du ministère
            if ($config['admins'] && isset($data['ministere_id'])) {
                NotifHelper::notifyMinistryAdmins(
                    $data['ministere_id'],
                    $config['titre'],
                    $data['details'] ?? 'Une action a été effectuée',
                    $config['type'],
                    $data['lien'] ?? null,
                    $config['module']
                );
            }
            
            // Notifier les super admins
            if ($config['super']) {
                NotifHelper::notifySuperAdmins(
                    $config['titre'],
                    ($data['ministere_nom'] ?? 'Ministère') . ' - ' . ($data['details'] ?? ''),
                    $config['type'],
                    $data['lien'] ?? null,
                    $config['module']
                );
            }
        }
    }
}