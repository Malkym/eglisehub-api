<?php

namespace App\Traits;

use App\Models\Ministere;
use Illuminate\Http\Request;

trait HasMinistereScope
{
    protected function getMinistereId(Request $request): ?int
    {
        if ($request->user()?->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()?->ministere_id;
    }

    protected function resolveSubdomain(Request $request): string
    {
        $subdomain = $request->header('X-Subdomain')
            ?? $request->query('subdomain');

        if (!$subdomain) {
            abort(400, 'Le sous-domaine du ministère est requis (header X-Subdomain ou paramètre subdomain).');
        }

        return $subdomain;
    }

    protected function resolveMinistereFromSubdomain(Request $request): Ministere
    {
        $subdomain = $this->resolveSubdomain($request);

        $ministere = Ministere::where('sous_domaine', $subdomain)
            ->where('statut', 'actif')
            ->first();

        if (!$ministere) {
            abort(404, 'Ministère introuvable ou inactif.');
        }

        return $ministere;
    }

    protected function findForUser(Request $request, string $id, string $modelClass)
    {
        $record = $modelClass::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($record->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $record;
    }
}
