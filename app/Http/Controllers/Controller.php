<?php

namespace App\Http\Controllers;

use App\Models\LogAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

abstract class Controller
{
    protected function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }

        return $request->user()->ministere_id;
    }

    protected function findForMinistere(Request $request, string $modelClass, string $id, ?int $ministereIdOverride = null)
    {
        $model = $modelClass::findOrFail($id);
        $ministereId = $ministereIdOverride ?? $this->getMinistereId($request);

        if ($ministereId && $model->ministere_id !== $ministereId) {
            abort(403, 'Accès refusé à cette ressource.');
        }

        return $model;
    }

    protected function log(Request $request, string $action, string $module, string $details, ?string $lien = null): void
    {
        if (!$request->user()) {
            return;
        }

        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'     => $action,
            'module'     => $module,
            'details'    => $details,
            'ip'        => $request->getClientIp(),
            'date_action' => now(),
        ]);

        $ministere = $request->user()->ministere;
        if ($ministere) {
            LogAction::notifyForAction($action, [
                'ministere_id' => $request->user()->ministere_id,
                'ministere_nom'  => $ministere->nom,
                'details'      => $details,
                'lien'        => $lien,
            ]);
        }
    }

    protected function validateRateLimit(Request $request, string $key, int $maxAttempts = 5, int $decaySeconds = 60): bool
    {
        $key = $key . ':' . ($request->ip() ?? 'unknown');
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return false;
        }

        RateLimiter::hit($key, $decaySeconds);
        return true;
    }

    protected function getRateLimitRemaining(Request $request, string $key, int $maxAttempts = 5): int
    {
        return RateLimiter::remaining($key . ':' . ($request->ip() ?? 'unknown'), $maxAttempts);
    }

    protected function respondWithError(string $message, int $code = 422, array $errors = []): \Illuminate\Http\JsonResponse
    {
        $response = ['success' => false, 'message' => $message];
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }

    protected function respondSuccess($data, string $message = 'Succès', int $code = 200): \Illuminate\Http\JsonResponse
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

    protected function respondPaginated($paginator): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page'  => $paginator->currentPage(),
                'last_page'   => $paginator->lastPage(),
                'per_page'   => $paginator->perPage(),
                'total'     => $paginator->total(),
                'from'      => $paginator->firstItem(),
                'to'        => $paginator->lastItem(),
            ],
        ]);
    }
}
