<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Services\Notification\NotificationEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'required', 'in:all,unread'],
            'cursor' => ['nullable', 'string', 'max:2048'],
        ]);
        $notifications = $request->user()->notifications()->getQuery()->reorder()
            ->when(($data['filter'] ?? 'all') === 'unread', fn(Builder $query): Builder => $query->whereNull('read_at'))
            ->orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(20, ['*'], 'cursor', $this->cursor($data['cursor'] ?? null));

        return response()->json([
            'data' => NotificationResource::collection($notifications->items())->resolve(),
            'next_cursor' => $notifications->nextCursor()?->encode(),
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()])
            ->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $notification, NotificationEvents $events): JsonResponse
    {
        DB::transaction(function () use ($request, $notification, $events): void {
            $entry = $request->user()->notifications()->findOrFail($notification);
            if ($request->user()->unreadNotifications()->whereKey($entry->id)->update(['read_at' => now()]) > 0) {
                $events->changed($request->user());
            }
        }, 5);

        return $this->summary($request);
    }

    public function readAll(Request $request, NotificationEvents $events): JsonResponse
    {
        DB::transaction(function () use ($request, $events): void {
            if ($request->user()->unreadNotifications()->update(['read_at' => now()]) > 0) {
                $events->changed($request->user());
            }
        }, 5);

        return $this->summary($request);
    }

    private function cursor(?string $encoded): ?Cursor
    {
        if ($encoded === null) {
            return null;
        }

        $cursor = Cursor::fromEncoded($encoded);
        if ($cursor === null || Validator::make($cursor->toArray(), [
            'id' => ['required', 'uuid'],
            'created_at' => ['required', 'date_format:Y-m-d H:i:s'],
            '_pointsToNextItems' => ['required', 'boolean'],
        ])->fails()) {
            throw ValidationException::withMessages(['cursor' => 'Posisi daftar notifikasi tidak valid. Muat ulang daftar.']);
        }

        return $cursor;
    }
}
