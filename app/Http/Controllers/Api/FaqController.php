<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\LogAction;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    // GET /api/ministry/faq
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $faqs = Faq::where('ministere_id', $ministereId)
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->actif !== null, fn($q) => $q->where('actif', $request->actif === 'true'))
            ->orderBy('ordre')
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    // POST /api/ministry/faq
    public function store(Request $request)
    {
        $request->validate([
            'question'  => 'required|string|max:500',
            'reponse'   => 'required|string',
            'categorie' => 'nullable|string|max:100',
            'ordre'     => 'integer',
            'actif'     => 'boolean',
        ]);

        $ministereId = $this->getMinistereId($request);

        $faq = Faq::create([
            'ministere_id' => $ministereId,
            'question'     => $request->question,
            'reponse'      => $request->reponse,
            'categorie'    => $request->categorie,
            'ordre'        => $request->ordre ?? 0,
            'actif'        => $request->actif ?? true,
        ]);

        $this->log($request, 'create_faq', 'faq', "Création FAQ: {$faq->question}");

        return response()->json([
            'success' => true,
            'message' => 'FAQ créée.',
            'data'    => $faq,
        ], 201);
    }

    // GET /api/ministry/faq/{id}
    public function show(Request $request, string $id)
    {
        $faq = $this->findForUser($request, $id);
        return response()->json(['success' => true, 'data' => $faq]);
    }

    // PUT /api/ministry/faq/{id}
    public function update(Request $request, string $id)
    {
        $faq = $this->findForUser($request, $id);

        $request->validate([
            'question'  => 'sometimes|string|max:500',
            'reponse'   => 'sometimes|string',
            'categorie' => 'nullable|string|max:100',
            'ordre'     => 'integer',
            'actif'     => 'boolean',
        ]);

        $faq->update($request->only(['question', 'reponse', 'categorie', 'ordre', 'actif']));

        $this->log($request, 'update_faq', 'faq', "Modification FAQ: {$faq->question}");

        return response()->json([
            'success' => true,
            'message' => 'FAQ mise à jour.',
            'data'    => $faq->fresh(),
        ]);
    }

    // DELETE /api/ministry/faq/{id}
    public function destroy(Request $request, string $id)
    {
        $faq = $this->findForUser($request, $id);
        $question = $faq->question;
        $faq->delete();

        $this->log($request, 'delete_faq', 'faq', "Suppression FAQ: {$question}");

        return response()->json(['success' => true, 'message' => 'FAQ supprimée.']);
    }

    // PATCH /api/ministry/faq/{id}/toggle
    public function toggle(Request $request, string $id)
    {
        $faq = $this->findForUser($request, $id);
        $faq->update(['actif' => ! $faq->actif]);
        $etat = $faq->fresh()->actif ? 'activée' : 'désactivée';

        return response()->json([
            'success' => true,
            'message' => "FAQ {$etat}.",
            'data'    => $faq->fresh(),
        ]);
    }

    // POST /api/ministry/faq/reorder
    public function reorder(Request $request)
    {
        $request->validate([
            'items'         => 'required|array',
            'items.*.id'    => 'required|integer',
            'items.*.ordre' => 'required|integer',
        ]);

        foreach ($request->items as $item) {
            Faq::where('id', $item['id'])->update(['ordre' => $item['ordre']]);
        }

        return response()->json(['success' => true, 'message' => 'Ordre des FAQs mis à jour.']);
    }

    // GET /api/public/faq — Route publique
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain') ?? 'crc';

        $faqs = Faq::whereHas('ministere', fn($q) =>
                        $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
                    )
                    ->where('actif', true)
                    ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
                    ->orderBy('ordre')
                    ->get(['id', 'question', 'reponse', 'categorie']);

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    private function getMinistereId(Request $request): int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }
        return $request->user()->ministere_id ?? 1;
    }

    private function findForUser(Request $request, string $id): Faq
    {
        $faq = Faq::findOrFail($id);
        if (! $request->user()->isSuperAdmin()) {
            if ($faq->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }
        return $faq;
    }

private function log(Request $request, string $action, string $module, string $details, ?string $lien = null): void
{
    $log = LogAction::create([
        'user_id'      => $request->user()->id,
        'ministere_id' => $request->user()->ministere_id,
        'action'       => $action,
        'module'       => $module,
        'details'      => $details,
        'ip'           => $request->ip(),
        'date_action'  => now(),
    ]);

    // Envoyer les notifications
    $ministere = $request->user()->ministere;
    LogAction::notifyForAction($action, [
        'ministere_id' => $request->user()->ministere_id,
        'ministere_nom' => $ministere?->nom,
        'details' => $details,
        'lien' => $lien,
    ]);
}
}