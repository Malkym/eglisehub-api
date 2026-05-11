<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorshipSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WorshipScheduleController extends Controller
{
    public function index(Request $request)
    {
        $ministereId = $this->getMinistereId($request);

        $schedules = WorshipSchedule::when($ministereId, fn($q) => $q->where('ministere_id', $ministereId))
            ->when($request->active_only, fn($q) => $q->active())
            ->orderBy('ordre')
            ->orderByRaw("FIELD(jour, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return $this->respondSuccess($schedules);
    }

    public function publicIndex(Request $request)
    {
        $subdomain = $this->resolveSubdomain($request);

        $schedules = WorshipSchedule::whereHas('ministere', fn($q) => $q->where('sous_domaine', $subdomain))
            ->active()
            ->orderBy('ordre')
            ->orderByRaw("FIELD(jour, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')")
            ->get();

        return $this->respondSuccess($schedules);
    }

    public function store(Request $request)
    {
        $this->prepareTimeFields($request);

        $validator = Validator::make($request->all(), [
            'jour'         => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'heure_debut'  => 'required|date_format:H:i',
            'heure_fin'    => 'required|date_format:H:i|after:heure_debut',
            'is_highlight' => 'boolean',
            'note'         => 'nullable|string|max:500',
            'is_active'    => 'boolean',
            'ordre'        => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError('Validation échouée', 422, $validator->errors()->toArray());
        }

        $ministereId = $this->getMinistereId($request);

        $schedule = WorshipSchedule::create([
            'ministere_id' => $ministereId,
            'jour'         => $request->jour,
            'heure_debut'  => $request->heure_debut,
            'heure_fin'    => $request->heure_fin,
            'is_highlight' => $request->is_highlight ?? false,
            'note'         => $request->note,
            'is_active'    => $request->is_active ?? true,
            'ordre'        => $request->ordre ?? 0,
        ]);

        return $this->respondSuccess($schedule, 'Horaire ajouté avec succès.', 201);
    }

    public function show(Request $request, $id)
    {
        $schedule = $this->findForMinistere($request, WorshipSchedule::class, $id);
        return $this->respondSuccess($schedule);
    }

    public function update(Request $request, $id)
    {
        $schedule = $this->findForMinistere($request, WorshipSchedule::class, $id);

        $this->prepareTimeFields($request);

        $validator = Validator::make($request->all(), [
            'jour'         => 'sometimes|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'heure_debut'  => 'sometimes|date_format:H:i',
            'heure_fin'    => 'sometimes|date_format:H:i|after:heure_debut',
            'is_highlight' => 'boolean',
            'note'         => 'nullable|string|max:500',
            'is_active'    => 'boolean',
            'ordre'        => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->respondWithError('Validation échouée', 422, $validator->errors()->toArray());
        }

        $schedule->update($request->all());

        return $this->respondSuccess($schedule, 'Horaire mis à jour.');
    }

    public function destroy(Request $request, $id)
    {
        $schedule = $this->findForMinistere($request, WorshipSchedule::class, $id);
        $schedule->delete();

        return $this->respondSuccess(null, 'Horaire supprimé.');
    }

    public function toggleActive(Request $request, $id)
    {
        $schedule = $this->findForMinistere($request, WorshipSchedule::class, $id);
        $schedule->is_active = !$schedule->is_active;
        $schedule->save();

        return $this->respondSuccess($schedule, $schedule->is_active ? 'Horaire activé.' : 'Horaire désactivé.');
    }

    private function prepareTimeFields(Request $request): void
    {
        if ($request->has('heure_debut')) {
            $request->merge(['heure_debut' => $this->normalizeTime($request->heure_debut)]);
        }
        if ($request->has('heure_fin')) {
            $request->merge(['heure_fin' => $this->normalizeTime($request->heure_fin)]);
        }
    }

    private function normalizeTime($time): string
    {
        if (empty($time)) return '';

        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
            return $time;
        }

        if (preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $time)) {
            return substr($time, 0, 5);
        }

        try {
            $date = new \DateTime($time);
            return $date->format('H:i');
        } catch (\Exception $e) {
            return $time;
        }
    }
}