<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotifHelper;
use App\Http\Controllers\Controller;
use App\Models\LogAction;
use App\Models\MessageContact;
use App\Models\Ministere;
use Illuminate\Http\Request;

class MessageContactController extends Controller
{
    public function allReplies(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $reponses = \App\Models\MessageReponse::with(['message', 'user'])
            ->when($ministereId, function ($q) use ($ministereId) {
                $q->whereHas('message', function ($sub) use ($ministereId) {
                    $sub->where('ministere_id', $ministereId);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $reponses]);
    }

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
        $message = MessageContact::with([
            'reponses.user:id,name,prenom'
        ])->findOrFail($id);

        // Auto-marquer comme lu
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
        $request->validate([
            'reponse' => 'required|string|min:1',
        ]);

        $message = MessageContact::findOrFail($id);

        // Vérifier ownership
        if (! $request->user()->isSuperAdmin()) {
            if ($message->ministere_id !== $request->user()->ministere_id) {
                return response()->json(['success' => false, 'message' => 'Accès refusé.'], 403);
            }
        }

        // Sauvegarder la réponse dans messages_reponses
        $reponse = \App\Models\MessageReponse::create([
            'message_id'  => $message->id,
            'user_id'     => $request->user()->id,
            'contenu'     => $request->reponse,
        ]);

        // Mettre à jour le statut du message
        $message->update(['statut' => 'repondu']);

        // Marquer comme lu aussi
        if (! $message->lu_le) {
            $message->update(['lu_le' => now()]);
        }

        NotifHelper::send(
            $request->user()->id,
            'Réponse envoyée',
            "Votre réponse à {$message->nom_expediteur} a été enregistrée.",
            'success',
            '/messages/' . $message->id,
            'messages'
        );

        $this->log(
            $request,
            'reply_message',
            'messages',
            "Réponse au message #{$message->id} de {$message->nom_expediteur}"
        );

        return response()->json([
            'success' => true,
            'message' => 'Réponse enregistrée.',
            'data'    => $reponse,
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

    // POST /api/public/contact — Route publique
    public function store(Request $request)
    {
        $request->validate([
            'nom_expediteur' => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'sujet'          => 'nullable|string|max:255',
            'message'        => 'required|string|min:5',
            'telephone'      => 'nullable|string|max:20',
        ]);

        // Trouver le ministère via subdomain
        $subdomain = $request->query('subdomain')
            ?? $request->input('subdomain')
            ?? 'crc';

        $ministere = Ministere::where('sous_domaine', $subdomain)
            ->where('statut', 'actif')
            ->first();

        if (!$ministere) {
            return response()->json([
                'success' => false,
                'message' => 'Ministère introuvable.',
            ], 404);
        }

        $message = MessageContact::create([
            'ministere_id'   => $ministere->id,
            'nom_expediteur' => $request->nom_expediteur,
            'email'          => $request->email,
            'telephone'      => $request->telephone,
            'sujet'          => $request->sujet ?? 'Message depuis le site',
            'message'        => $request->message,
            'statut'         => 'non_lu',
        ]);

        // NOTIFICATION EN TEMPS RÉEL
        NotifHelper::notifyMinistryAdmins(
            $ministere->id,
            'Nouveau message de ' . $request->nom_expediteur,
            substr($request->message, 0, 80) . '...',
            'message',
            '/messages/' . $message->id,
            'messages'
        );

        // Optionnel : Notifier les super admins aussi
        NotifHelper::notifySuperAdmins(
            'Nouveau message - ' . $ministere->nom,
            $request->nom_expediteur . ' a envoyé un message',
            'message',
            '/messages/' . $message->id,
            'messages'
        );

        return response()->json([
            'success' => true,
            'message' => 'Message envoyé avec succès.',
            'data'    => ['id' => $message->id],
        ]);
    }
}
