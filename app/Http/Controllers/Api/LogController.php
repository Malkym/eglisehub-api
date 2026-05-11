<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LogAction;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $logs = LogAction::with('user:id,name,prenom,email')
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->when($request->action, fn($q) => $q->where('action', $request->action))
            ->when($request->user_id, fn($q) => $q->where('user_id', $request->user_id))
            ->when($request->ministere_id, fn($q) => $q->where('ministere_id', $request->ministere_id))
            ->when($request->date_debut, fn($q) => $q->whereDate('date_action', '>=', $request->date_debut))
            ->when($request->date_fin, fn($q) => $q->whereDate('date_action', '<=', $request->date_fin))
            ->when($request->search, fn($q) => $q->where('details', 'like', "%{$request->search}%"))
            ->orderBy('date_action', 'desc')
            ->paginate(30);

        return $this->respondPaginated($logs);
    }

    public function show(string $id)
    {
        $log = LogAction::with('user:id,name,prenom,email', 'ministere:id,nom')->findOrFail($id);

        return $this->respondSuccess($log);
    }

    public function export(Request $request)
    {
        $logs = LogAction::with('user:id,name,email')
            ->when($request->date_debut, fn($q) => $q->whereDate('date_action', '>=', $request->date_debut))
            ->when($request->date_fin, fn($q) => $q->whereDate('date_action', '<=', $request->date_fin))
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->orderBy('date_action', 'desc')
            ->get();

        $csv = "ID,Utilisateur,Email,Action,Module,Details,IP,Date\n";
        foreach ($logs as $log) {
            $nom   = $log->user ? "{$log->user->name} {$log->user->prenom}" : 'Système';
            $email = $log->user ? $log->user->email : '-';
            $csv  .= "{$log->id},\"{$nom}\",{$email},{$log->action},{$log->module},\"{$log->details}\",{$log->ip},{$log->date_action}\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="logs_' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    public function clean(Request $request)
    {
        $request->validate(['jours' => 'required|integer|min:7|max:365']);

        $count = LogAction::where('date_action', '<', now()->subDays($request->jours))->delete();

        return $this->respondSuccess(null, "{$count} logs supprimés (plus vieux que {$request->jours} jours).");
    }

    public function userActivity(string $id)
    {
        $logs = LogAction::where('user_id', $id)
            ->with('ministere:id,nom')
            ->orderBy('date_action', 'desc')
            ->paginate(20);

        return $this->respondPaginated($logs);
    }

    public function ministryLogs(Request $request)
    {
        $ministereId = $request->user()->isSuperAdmin() && $request->has('ministere_id')
            ? (int) $request->ministere_id
            : $request->user()->ministere_id;

        $logs = LogAction::where('ministere_id', $ministereId)
            ->with('user:id,name,prenom')
            ->when($request->module, fn($q) => $q->where('module', $request->module))
            ->orderBy('date_action', 'desc')
            ->paginate(20);

        return $this->respondPaginated($logs);
    }
}