<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorshipSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Worship Schedules",
 *     description="Gestion des horaires de cultes"
 * )
 */
class WorshipScheduleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/ministry/worship-schedules",
     *     summary="Liste des horaires de cultes (Admin)",
     *     description="Récupère tous les horaires de cultes du ministère authentifié",
     *     operationId="getWorshipSchedules",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filtrer uniquement les horaires actifs",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des horaires",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/WorshipSchedule")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);
        
        $schedules = WorshipSchedule::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->active_only, fn($q) => $q->active())
            ->orderBy('ordre')
            ->orderByRaw("FIELD(jour, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    /**
     * @OA\Get(
     *     path="/api/public/worship-schedules",
     *     summary="Liste publique des horaires de cultes",
     *     description="Récupère les horaires de cultes publics d'un ministère",
     *     operationId="getPublicWorshipSchedules",
     *     tags={"Worship Schedules"},
     *     @OA\Parameter(
     *         name="subdomain",
     *         in="query",
     *         description="Sous-domaine du ministère",
     *         required=false,
     *         @OA\Schema(type="string", example="crc")
     *     ),
     *     @OA\Parameter(
     *         name="X-Subdomain",
     *         in="header",
     *         description="Sous-domaine du ministère (header)",
     *         required=false,
     *         @OA\Schema(type="string", example="crc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des horaires publics",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/WorshipSchedule")
     *             )
     *         )
     *     )
     * )
     */
    public function publicIndex(Request $request)
    {
        $subdomain = $request->header('X-Subdomain') ?? $request->query('subdomain');
        
        $schedules = WorshipSchedule::whereHas('ministere', function($q) use ($subdomain) {
                $q->where('sous_domaine', $subdomain);
            })
            ->active()
            ->orderBy('ordre')
            ->orderByRaw("FIELD(jour, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return response()->json(['success' => true, 'data' => $schedules]);
    }

    /**
     * @OA\Post(
     *     path="/ministry/worship-schedules",
     *     summary="Créer un horaire de culte",
     *     description="Ajoute un nouvel horaire de culte pour le ministère",
     *     operationId="storeWorshipSchedule",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"jour", "heure_debut", "heure_fin"},
     *             @OA\Property(property="jour", type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"}, example="sunday"),
     *             @OA\Property(property="heure_debut", type="string", format="time", example="09:00"),
     *             @OA\Property(property="heure_fin", type="string", format="time", example="12:00"),
     *             @OA\Property(property="is_highlight", type="boolean", example=false),
     *             @OA\Property(property="note", type="string", nullable=true, example="Culte de louange"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="ordre", type="integer", example=0),
     *             @OA\Property(property="ministere_id", type="integer", description="Uniquement pour super admin", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Horaire créé avec succès",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Horaire ajouté avec succès."),
     *             @OA\Property(property="data", ref="#/components/schemas/WorshipSchedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="jour", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="heure_debut", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="heure_fin", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Non authentifié"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Nettoyer et formater les heures avant validation
        $this->prepareTimeFields($request);
        
        $validator = Validator::make($request->all(), [
            'jour' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'heure_debut' => 'required|date_format:H:i',
            'heure_fin' => 'required|date_format:H:i|after:heure_debut',
            'is_highlight' => 'boolean',
            'note' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'ordre' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ministereId = $this->getMinistereId($request);
        
        $schedule = WorshipSchedule::create([
            'ministere_id' => $ministereId,
            'jour' => $request->jour,
            'heure_debut' => $request->heure_debut,
            'heure_fin' => $request->heure_fin,
            'is_highlight' => $request->is_highlight ?? false,
            'note' => $request->note,
            'is_active' => $request->is_active ?? true,
            'ordre' => $request->ordre ?? 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Horaire ajouté avec succès.',
            'data' => $schedule
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/ministry/worship-schedules/{id}",
     *     summary="Détails d'un horaire",
     *     description="Récupère les détails d'un horaire spécifique",
     *     operationId="getWorshipSchedule",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'horaire",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de l'horaire",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/WorshipSchedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $schedule = $this->findForUser($request, $id);
        return response()->json(['success' => true, 'data' => $schedule]);
    }

    /**
     * @OA\Put(
     *     path="/ministry/worship-schedules/{id}",
     *     summary="Modifier un horaire",
     *     description="Met à jour les informations d'un horaire existant",
     *     operationId="updateWorshipSchedule",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'horaire",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="jour", type="string", enum={"monday","tuesday","wednesday","thursday","friday","saturday","sunday"}, example="sunday"),
     *             @OA\Property(property="heure_debut", type="string", format="time", example="09:00"),
     *             @OA\Property(property="heure_fin", type="string", format="time", example="12:00"),
     *             @OA\Property(property="is_highlight", type="boolean", example=true),
     *             @OA\Property(property="note", type="string", nullable=true, example="Culte principal"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="ordre", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Horaire mis à jour",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Horaire mis à jour."),
     *             @OA\Property(property="data", ref="#/components/schemas/WorshipSchedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $schedule = $this->findForUser($request, $id);
        
        // Nettoyer et formater les heures avant validation
        $this->prepareTimeFields($request);

        $validator = Validator::make($request->all(), [
            'jour' => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'heure_debut' => 'sometimes|date_format:H:i',
            'heure_fin' => 'sometimes|date_format:H:i|after:heure_debut',
            'is_highlight' => 'boolean',
            'note' => 'nullable|string|max:500',
            'is_active' => 'boolean',
            'ordre' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $schedule->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Horaire mis à jour.',
            'data' => $schedule
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/ministry/worship-schedules/{id}",
     *     summary="Supprimer un horaire",
     *     description="Supprime définitivement un horaire de culte",
     *     operationId="deleteWorshipSchedule",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'horaire",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Horaire supprimé",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Horaire supprimé.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Accès non autorisé"
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $schedule = $this->findForUser($request, $id);
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Horaire supprimé.'
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/ministry/worship-schedules/{id}/toggle-active",
     *     summary="Activer/Désactiver un horaire",
     *     description="Bascule le statut actif/inactif d'un horaire",
     *     operationId="toggleWorshipScheduleActive",
     *     tags={"Worship Schedules"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID de l'horaire",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Statut modifié",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Statut modifié."),
     *             @OA\Property(property="data", ref="#/components/schemas/WorshipSchedule")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Horaire non trouvé"
     *     )
     * )
     */
    public function toggleActive(Request $request, $id)
    {
        $schedule = $this->findForUser($request, $id);
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        return response()->json([
            'success' => true,
            'message' => $schedule->is_active ? 'Horaire activé.' : 'Horaire désactivé.',
            'data' => $schedule
        ]);
    }

    /**
     * Prépare et formate les champs d'heure avant validation
     */
    private function prepareTimeFields(Request $request): void
    {
        // Formater l'heure de début
        if ($request->has('heure_debut')) {
            $heureDebut = $this->normalizeTime($request->heure_debut);
            $request->merge(['heure_debut' => $heureDebut]);
        }
        
        // Formater l'heure de fin
        if ($request->has('heure_fin')) {
            $heureFin = $this->normalizeTime($request->heure_fin);
            $request->merge(['heure_fin' => $heureFin]);
        }
    }

    /**
     * Normalise une chaîne d'heure au format H:i
     */
    private function normalizeTime($time): string
    {
        if (empty($time)) {
            return '';
        }
        
        // Si c'est déjà au format H:i (ex: 09:00)
        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return $time;
        }
        
        // Si c'est au format H:i:s (ex: 09:00:00)
        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $time)) {
            return substr($time, 0, 5);
        }
        
        // Si c'est une date/heure complète (ex: 2024-01-01T09:00:00)
        try {
            $date = new \DateTime($time);
            return $date->format('H:i');
        } catch (\Exception $e) {
            // Si la conversion échoue, retourner la valeur originale
            return $time;
        }
    }

    // Helpers
    private function getMinistereId(Request $request): ?int
    {
        if ($request->user()->isSuperAdmin()) {
            if ($request->has('ministere_id')) {
                return (int) $request->ministere_id;
            }
            return null;
        }
        return $request->user()->ministere_id;
    }

    private function findForUser(Request $request, $id): WorshipSchedule
    {
        $schedule = WorshipSchedule::findOrFail($id);

        if (!$request->user()->isSuperAdmin()) {
            if ($schedule->ministere_id !== $request->user()->ministere_id) {
                abort(403, 'Accès refusé.');
            }
        }

        return $schedule;
    }
}