<?php

namespace App\Services\Notification;

use App\Models\MasterData\User;
use Illuminate\Support\Facades\DB;

class NotificationEvents
{
    public const string PREFIX = 'notifications:';

    /** Publish a newly persisted notification within the same database transaction. */
    public function created(User $user, string $id): void
    {
        DB::select('select pg_notify(?, ?)', [config('letters.sse.channel'), json_encode([
            'kind' => 'notification-created',
            'actor_uuid' => $user->uuid,
            'notification_id' => $id,
        ], JSON_THROW_ON_ERROR)]);
    }

    /** Publish on the existing SSE transport within the same database transaction. */
    public function changed(User $user): void
    {
        DB::select('select pg_notify(?, ?)', [config('letters.sse.channel'), self::PREFIX . $user->uuid]);
    }
}
