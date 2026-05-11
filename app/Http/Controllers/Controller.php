<?php

namespace App\Http\Controllers;

use App\Models\LogAction;
use App\Models\Ministere;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

abstract class Controller
{
    public function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()->ministere_id;
    }

    public function resolveSubdomain(Request $request): string
    {
        $subdomain = $request->header('X-Subdomain')
            ?? $request->query('subdomain');

        if (!$subdomain) {
            abort(400, 'Le sous-domaine du ministère est requis (header X-Subdomain ou paramètre subdomain).');
        }

        return $subdomain;
    }

    public function resolveMinistereFromSubdomain(Request $request): Ministere
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

    public function findForMinistere(Request $request, string $modelClass, string $id, ?int $ministereIdOverride = null): Model
    {
        $model = $modelClass::findOrFail($id);
        $ministereId = $ministereIdOverride ?? $this->getMinistereId($request);

        if ($ministereId && $model->ministere_id !== $ministereId) {
            abort(403, 'Accès refusé à cette ressource.');
        }

        return $model;
    }

    public function generateUniqueSlug(string $titre, int $ministereId, string $modelClass, ?string $exceptId = null, string $column = 'slug'): string
    {
        $slug = Str::slug($titre);
        $original = $slug;
        $count = 1;

        while (
            $modelClass::where('ministere_id', $ministereId)
                ->where($column, $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = $original . '-' . $count++;
        }

        return $slug;
    }

    public function log(Request $request, string $action, string $module, string $details, ?string $lien = null): void
    {
        if (!$request->user()) {
            return;
        }

        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => $module,
            'details'      => $details,
            'ip'           => $request->getClientIp(),
            'date_action'  => now(),
        ]);

        $ministere = $request->user()->ministere;
        if ($ministere) {
            LogAction::notifyForAction($action, [
                'ministere_id'  => $request->user()->ministere_id,
                'ministere_nom' => $ministere->nom,
                'details'       => $details,
                'lien'          => $lien,
            ]);
        }
    }

    public function validateRateLimit(Request $request, string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $key = $key . ':' . ($request->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $decaySeconds);
        return true;
    }

    public function getRateLimitRemaining(Request $request, string $key, int $maxAttempts = 5): int
    {
        return RateLimiter::remaining($key . ':' . ($request->ip() ?? 'unknown'), $maxAttempts);
    }

    public function respondWithError(string $message, int $code = 422, array $errors = []): \Illuminate\Http\JsonResponse
    {
        $response = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    public function respondSuccess($data, string $message = 'Succès', int $code = 200): \Illuminate\Http\JsonResponse
    {
        $response = ['success' => true];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    public function respondPaginated($paginator): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }
}