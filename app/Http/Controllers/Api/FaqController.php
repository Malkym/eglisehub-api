<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $faqs = Faq::where('ministere_id', $ministereId)
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->when($request->actif !== null, fn($q) => $q->where('actif', $request->actif === 'true'))
            ->orderBy('ordre')
            ->orderBy('created_at')
            ->get();

        return $this->respondSuccess($faqs);
    }

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

        return $this->respondSuccess($faq, 'FAQ créée.', 201);
    }

    public function show(Request $request, string $id)
    {
        $faq = $this->findForMinistere($request, Faq::class, $id);
        return $this->respondSuccess($faq);
    }

    public function update(Request $request, string $id)
    {
        $faq = $this->findForMinistere($request, Faq::class, $id);

        $request->validate([
            'question'  => 'sometimes|string|max:500',
            'reponse'   => 'sometimes|string',
            'categorie' => 'nullable|string|max:100',
            'ordre'     => 'integer',
            'actif'     => 'boolean',
        ]);

        $faq->update($request->only(['question', 'reponse', 'categorie', 'ordre', 'actif']));

        $this->log($request, 'update_faq', 'faq', "Modification FAQ: {$faq->question}");

        return $this->respondSuccess($faq->fresh(), 'FAQ mise à jour.');
    }

    public function destroy(Request $request, string $id)
    {
        $faq = $this->findForMinistere($request, Faq::class, $id);
        $question = $faq->question;
        $faq->delete();

        $this->log($request, 'delete_faq', 'faq', "Suppression FAQ: {$question}");

        return $this->respondSuccess(null, 'FAQ supprimée.');
    }

    public function toggle(Request $request, string $id)
    {
        $faq = $this->findForMinistere($request, Faq::class, $id);
        $faq->update(['actif' => !$faq->actif]);
        $etat = $faq->fresh()->actif ? 'activée' : 'désactivée';

        return $this->respondSuccess($faq->fresh(), "FAQ {$etat}.");
    }

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

        return $this->respondSuccess(null, 'Ordre des FAQs mis à jour.');
    }

    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $faqs = Faq::whereHas('ministere', fn($q) =>
                $q->where('sous_domaine', $subdomain)->where('statut', 'actif')
            )
            ->where('actif', true)
            ->when($request->categorie, fn($q) => $q->where('categorie', $request->categorie))
            ->orderBy('ordre')
            ->get(['id', 'question', 'reponse', 'categorie']);

        return $this->respondSuccess($faqs);
    }
}