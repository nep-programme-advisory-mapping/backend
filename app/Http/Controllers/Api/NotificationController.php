<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->take(30)
            ->get()
            ->map(fn($n) => [
                'id'                 => $n->id,
                'type'               => $n->data['type'] ?? 'programme_sent',
                'title'              => $n->data['title'] ?? $n->data['message'] ?? '',
                'message'            => $n->data['message'] ?? '',
                'programme_entry_id' => $n->data['programme_entry_id'] ?? null,
                'advisory_note_id'   => $n->data['advisory_note_id'] ?? null,
                'read_at'            => $n->read_at?->toISOString(),
                'created_at'         => $n->created_at->toISOString(),
            ]);

        return response()->json(['data' => $notifications]);
    }

    public function markRead(Request $request, string $id)
    {
        $request->user()->notifications()->where('id', $id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'Marked as read.']);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['message' => 'All marked as read.']);
    }
}
