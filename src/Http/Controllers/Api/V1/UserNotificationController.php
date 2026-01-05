<?php

namespace AlyIcom\MyPackage\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class UserNotificationController extends Controller
{
    protected $guard;
    protected $translationNamespace;

    public function __construct()
    {
        $this->guard = config('my-package.api.guard', 'api');
        $this->translationNamespace = config('my-package.translations.namespace', 'user_validation');
        
        $this->middleware('auth:' . $this->guard);
    }

    public function all(Request $request)
    {
        $user = Auth::guard($this->guard)->user();

        $perPageUnread = (int) $request->input('per_page_unread', 15);
        $perPageRead = (int) $request->input('per_page_read', 15);

        $perPageUnread = max(1, min(100, $perPageUnread));
        $perPageRead = max(1, min(100, $perPageRead));

        $unread = $user
            ->notifications()
            ->whereNull('read_at')
            ->latest()
            ->paginate($perPageUnread, ['*'], 'unread_page');

        $read = $user
            ->notifications()
            ->whereNotNull('read_at')
            ->latest('read_at')
            ->paginate($perPageRead, ['*'], 'read_page');

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'read_count' => $user->notifications()->whereNotNull('read_at')->count(),
            'unread' => $unread,
            'read' => $read,
        ]);
    }

    public function unread(Request $request)
    {
        $user = Auth::guard($this->guard)->user();

        $perPage = (int) $request->input('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $notifications = $user
            ->notifications()
            ->whereNull('read_at')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, string $id)
    {
        $user = Auth::guard($this->guard)->user();

        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'message' => trans($this->translationNamespace . '.Notification not found'),
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => trans($this->translationNamespace . '.Notification marked as read'),
            'notification_id' => $notification->id,
            'read_at' => $notification->read_at ?? now(),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $user = Auth::guard($this->guard)->user();

        if ($user->unreadNotifications->isNotEmpty()) {
            $user->unreadNotifications->markAsRead();
        }

        return response()->json([
            'success' => true,
            'message' => trans($this->translationNamespace . '.All notifications marked as read'),
        ]);
    }
}

