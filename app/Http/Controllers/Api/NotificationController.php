<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->when($request->lu !== null, fn($q) => $q->where('lu', $request->lu === 'true'))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return $this->respondPaginated($notifications);
    }

    public function show(Request $request, string $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        if (!$notification->lu) {
            $notification->update(['lu' => true, 'lu_le' => now()]);
        }

        return $this->respondSuccess($notification);
    }

    public function markAsRead(Request $request, string $id)
    {
        $notification = Notification::where('user_id', $request->user()->id)->findOrFail($id);

        $notification->update(['lu' => true, 'lu_le' => now()]);

        return $this->respondSuccess(null, 'Notification marquée comme lue.');
    }

    public function markAllAsRead(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->where('lu', false)
            ->update(['lu' => true, 'lu_le' => now()]);

        return $this->respondSuccess(null, "{$count} notifications marquées comme lues.");
    }

    public function destroy(Request $request, string $id)
    {
        Notification::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return $this->respondSuccess(null, 'Notification supprimée.');
    }

    public function unreadCount(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)->where('lu', false)->count();

        return $this->respondSuccess(['count' => $count]);
    }

    public function clearRead(Request $request)
    {
        $count = Notification::where('user_id', $request->user()->id)->where('lu', true)->delete();

        return $this->respondSuccess(null, "{$count} notifications lues supprimées.");
    }
}