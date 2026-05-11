<?php

namespace App\Http\Controllers\Api;

use App\Helpers\NotifHelper;
use App\Http\Controllers\Controller;
use App\Models\MessageContact;
use App\Models\MessageReponse;
use App\Models\Ministere;
use Illuminate\Http\Request;

class MessageContactController extends Controller
{
    public function allReplies(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $reponses = MessageReponse::with(['message', 'user'])
            ->when($ministereId, function ($q) use ($ministereId) {
                $q->whereHas('message', function ($sub) use ($ministereId) {
                    $sub->where('ministere_id', $ministereId);
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondPaginated($reponses);
    }

    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $query = MessageContact::query();

        if ($ministereId !== null) {
            $query->where('ministere_id', $ministereId);
        }

        $messages = $query
            ->when($request->statut, fn($q) => $q->where('statut', $request->statut))
            ->when($request->search, fn($q) =>
                $q->where('nom_expediteur', 'like', "%{$request->search}%")
                    ->orWhere('sujet', 'like', "%{$request->search}%")
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondPaginated($messages);
    }

    public function show(Request $request, string $id)
    {
        $message = MessageContact::with(['reponses.user:id,name,prenom'])->findOrFail($id);

        if ($message->statut === 'non_lu') {
            $message->update(['statut' => 'lu', 'lu_le' => now()]);
        }

        return $this->respondSuccess($message);
    }

    public function markRead(Request $request, string $id)
    {
        $message = $this->findForMinistere($request, MessageContact::class, $id);
        $message->update(['statut' => 'lu', 'lu_le' => now()]);

        return $this->respondSuccess(null, 'Message marqué comme lu.');
    }

    public function markUnread(Request $request, string $id)
    {
        $message = $this->findForMinistere($request, MessageContact::class, $id);
        $message->update(['statut' => 'non_lu', 'lu_le' => null]);

        return $this->respondSuccess(null, 'Message marqué comme non lu.');
    }

    public function reply(Request $request, string $id)
    {
        $request->validate(['reponse' => 'required|string|min:1']);

        $message = $this->findForMinistere($request, MessageContact::class, $id);

        $reponse = MessageReponse::create([
            'message_id' => $message->id,
            'user_id'    => $request->user()->id,
            'contenu'    => $request->reponse,
        ]);

        $message->update(['statut' => 'repondu']);

        if (!$message->lu_le) {
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

        $this->log($request, 'reply_message', 'messages', "Réponse au message #{$message->id} de {$message->nom_expediteur}");

        return $this->respondSuccess($reponse, 'Réponse enregistrée.');
    }

    public function destroy(Request $request, string $id)
    {
        $message = $this->findForMinistere($request, MessageContact::class, $id);
        $nom = $message->nom_expediteur;
        $message->delete();

        $this->log($request, 'delete_message', 'messages', "Suppression message de: {$nom}");

        return $this->respondSuccess(null, 'Message supprimé.');
    }

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

        return $this->respondSuccess(null, 'Message envoyé. Nous vous répondrons bientôt.', 201);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom_expediteur' => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'sujet'          => 'nullable|string|max:255',
            'message'        => 'required|string|min:5',
            'telephone'      => 'nullable|string|max:20',
        ]);

        $ministere = $this->resolveMinistereFromSubdomain($request);

        $message = MessageContact::create([
            'ministere_id'   => $ministere->id,
            'nom_expediteur' => $request->nom_expediteur,
            'email'          => $request->email,
            'telephone'      => $request->telephone,
            'sujet'          => $request->sujet ?? 'Message depuis le site',
            'message'        => $request->message,
            'statut'         => 'non_lu',
        ]);

        NotifHelper::notifyMinistryAdmins(
            $ministere->id,
            'Nouveau message de ' . $request->nom_expediteur,
            substr($request->message, 0, 80) . '...',
            'message',
            '/messages/' . $message->id,
            'messages'
        );

        NotifHelper::notifySuperAdmins(
            'Nouveau message - ' . $ministere->nom,
            $request->nom_expediteur . ' a envoyé un message',
            'message',
            '/messages/' . $message->id,
            'messages'
        );

        return $this->respondSuccess(['id' => $message->id], 'Message envoyé avec succès.');
    }
}