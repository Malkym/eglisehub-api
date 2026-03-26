<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // GET /api/notifications
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->when($request->lu !== null, fn($q) => $q->where('lu', $request->lu === 'true'))
            ->when($request->type,        fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    // GET /api/notifications/{id}
    public function show(Request $request, string $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
                                    ->findOrFail($id);

        // Marquer comme lue automatiquement
        if (! $notification->lu) {
            $notification->update(['lu' => true, 'lu_le' => now()]);
        }

        return response()->json(['success' => true, 'data' => $notification]);
    }

    // PATCH /api/notifications/{id}/read
    public function markAsRead(Request $request, string $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)
                                    ->findOrFail($id);

        $notification->update(['lu' => true, 'lu_le' => now()]);

        return response()->json(['success' => true, 'message' => 'Notification marquée comme lue.']);
    }

    // POST /api/notifications/mark-all-read
    public function markAllAsRead(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
                             ->where('lu', false)
                             ->update(['lu' => true, 'lu_le' => now()]);

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications marquées comme lues.",
        ]);
    }

    // DELETE /api/notifications/{id}
    public function destroy(Request $request, string $id)
    {
        Notification::where('user_id', $request->user()->id)
                    ->findOrFail($id)
                    ->delete();

        return response()->json(['success' => true, 'message' => 'Notification supprimée.']);
    }

    // GET /api/notifications/unread-count
    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
                             ->where('lu', false)
                             ->count();

        return response()->json(['success' => true, 'data' => ['count' => $count]]);
    }

    // DELETE all lues — bonus utile
    public function clearRead(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
                             ->where('lu', true)
                             ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} notifications lues supprimées.",
        ]);
    }
}