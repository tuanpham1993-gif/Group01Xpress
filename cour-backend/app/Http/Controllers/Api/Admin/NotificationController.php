<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NotificationTemplate;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    //
    public function index()
    {
        $templates = NotificationTemplate::all();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    // Customer: lấy notification của chính mình
    public function myNotifications()
    {
        $notifications = Notification::with('shipment')
            ->where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // Xem chi tiết 1 notification
    public function show($id)
    {
        $notification = Notification::with('shipment')
            ->where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        return response()->json($notification);
    }

    // Đánh dấu đã đọc
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->update([
            'is_read' => true
        ]);

        return response()->json([
            'message' => 'Notification marked as read'
        ]);
    }

    // Đánh dấu tất cả đã đọc
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update([
                'is_read' => true
            ]);

        return response()->json([
            'message' => 'All notifications marked as read'
        ]);
    }

    // Xóa notification
    public function destroy($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted'
        ]);
    }

    public function updateTemplate(Request $request, $id)
    {
        $template = NotificationTemplate::findOrFail($id);

        // Validate dữ liệu gửi lên
        $request->validate([
            'subject' => 'required|string',
            'content' => 'required|string',
        ]);

        // Cập nhật
        $template->update([
            'subject' => $request->subject,
            'content' => $request->content,
        ]);

        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    // Thêm mới template
    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_name' => 'required|string',
            'subject' => 'required|string',
            'content' => 'required|string',
            'is_active' => 'boolean',
        ]);

        $template = NotificationTemplate::create($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function toggle(Request $request, $id)
    {
        $template = NotificationTemplate::findOrFail($id);
        $willEnable = $request->boolean('is_active');

        // Không cho disable nếu đây là template active duy nhất của nhóm
        if (!$willEnable) {
            $activeCount = NotificationTemplate::where('template_name', $template->template_name)
                ->where('is_active', true)
                ->count();

            if ($activeCount <= 1 && $template->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Must have at least 1 active template in the group "' . $template->template_name . '".',
                ], 422);
            }
        }

        // Nếu enable: disable tất cả cùng tên trước
        if ($willEnable) {
            NotificationTemplate::where('template_name', $template->template_name)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
        }

        $template->is_active = $willEnable;
        $template->save();

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    public function destroyTemplate($id)
    {
        $template = NotificationTemplate::findOrFail($id);

        // Không cho xóa nếu đang active
        if ($template->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active template. Please disable it first.',
            ], 422);
        }

        // Không cho xóa nếu đây là template duy nhất của nhóm
        $totalCount = NotificationTemplate::where('template_name', $template->template_name)
            ->count();

        if ($totalCount <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete — must have at least 1 template in the group "' . $template->template_name . '".',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully.',
        ]);
    }

}