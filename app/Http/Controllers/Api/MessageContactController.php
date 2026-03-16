<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MessageContact;
use App\Models\Ministere;
use App\Models\LogAction;
use Illuminate\Http\Request;

class MessageContactController extends Controller
{
    // GET /api/ministry/contact-messages
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $query = MessageContact::query();

        // Si ministereId est null = super admin voit tout
        if ($ministereId !== null) {
            $query->where('ministere_id', $ministereId);
        }

        $messages = $query
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when(
                $request->search,
                fn($q) =>
                $q->where('nom_expediteur', 'like', "%{$request->search}%")
                    ->orWhere('sujet', 'like', "%{$request->search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $messages]);
    }

    // GET /api/ministry/contact-messages/{id}
    public function show(Request $request, string $id)
    {
        $message = $this->findForUser($request, $id);

        // Marquer automatiquement comme lu à l'ouverture
        if ($message->statut === 'non_lu') {
            $message->update([
                'statut' => 'lu',
                'lu_le'  => now(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $message]);
    }

    // PATCH /api/ministry/contact-messages/{id}/read
    public function markRead(Request $request, string $id)
    {
        $message = $this->findForUser($request, $id);
        $message->update(['statut' => 'lu', 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Message marqué comme lu.']);
    }

    // PATCH /api/ministry/contact-messages/{id}/unread
    public function markUnread(Request $request, string $id)
    {
        $message = $this->findForUser($request, $id);
        $message->update(['statut' => 'non_lu', 'lu_le' => null]);

        return response()->json(['success' => true, 'message' => 'Message marqué comme non lu.']);
    }

    // PATCH /api/ministry/contact-messages/{id}/reply
    public function reply(Request $request, string $id)
    {
        $message = $this->findForUser($request, $id);

        $request->validate([
            'reponse' => 'required|string',
        ]);

        $message->update(['statut' => 'repondu']);

        // (En production : envoyer un email ici avec Mail::to())

        $this->log($request, 'reply_message', 'messages', "Réponse à: {$message->nom_expediteur}");

        return response()->json([
            'success' => true,
            'message' => 'Réponse enregistrée.',
        ]);
    }

    // DELETE /api/ministry/contact-messages/{id}
    public function destroy(Request $request, string $id)
    {
        $message = $this->findForUser($request, $id);
        $nom = $message->nom_expediteur;
        $message->delete();

        $this->log($request, 'delete_message', 'messages', "Suppression message de: {$nom}");

        return response()->json(['success' => true, 'message' => 'Message supprimé.']);
    }

    // POST /api/public/contact — formulaire public
    public function publicStore(Request $request)
    {
        $request->validate([
            'subdomain'      => 'required|string',
            'nom_expediteur' => 'required|string|max:255',
            'email'          => 'required|email',
            'telephone'      => 'nullable|string|max:20',
            'sujet'          => 'nullable|string|max:255',
            'message'        => 'required|string|min:10|max:2000',
        ]);

        $ministere = Ministere::where('sous_domaine', $request->subdomain)
            ->where('statut', 'actif')
            ->firstOrFail();

        MessageContact::create([
            'ministere_id'   => $ministere->id,
            'nom_expediteur' => $request->nom_expediteur,
            'email'          => $request->email,
            'telephone'      => $request->telephone,
            'sujet'          => $request->sujet,
            'message'        => $request->message,
            'statut'         => 'non_lu',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé. Nous vous répondrons bientôt.',
        ], 201);
    }

    // Helpers
    private function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin() && $request->has('ministere_id')) {
            return (int) $request->ministere_id;
        }

        if ($request->user()->isSuperAdmin()) {
            return null; // Super admin voit tout
        }

        return $request->user()->ministere_id;
    }

    private function findForUser(Request $request, string $id): MessageContact
    {
        $message = MessageContact::findOrFail($id);

        if (! $request->user()->isSuperAdmin()) {
            if ($message->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $message;
    }

    private function log(Request $request, string $action, string $module, string $details): void
    {
        LogAction::create([
            'user_id'      => $request->user()->id,
            'ministere_id' => $request->user()->ministere_id,
            'action'       => $action,
            'module'       => $module,
            'details'      => $details,
            'ip'           => $request->ip(),
            'date_action'  => now(),
        ]);
    }
}
