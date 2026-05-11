<?php

namespace App\Traits;

use App\Models\LogAction;
use Illuminate\Http\Request;

trait HasActivityLogging
{
    protected function logAction(Request $request, string $action, string $module, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => $module,
            'details'      => $details,
            'ip'           => $request->getClientIp(),
            'date_action'  => now(),
        ]);
    }
}
