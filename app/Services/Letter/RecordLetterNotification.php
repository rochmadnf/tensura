<?php

namespace App\Services\Letter;

use App\Enums\LetterOperationStatusEnum as Status;
use App\Models\LetterOperation;
use App\Models\MasterData\User;
use App\Services\Notification\NotificationEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordLetterNotification
{
    public function __construct(private NotificationEvents $events) {}

    /** Record once per recipient, in the transaction that finalizes or shares the result. */
    public function record(LetterOperation $operation, ?User $recipient = null): void
    {
        if (!$operation->status->terminal() || ($operation->kind === 'preview' && $operation->status === Status::SUCCEEDED)) {
            return;
        }

        $recipients = $recipient === null
            ? User::query()->whereIn('uuid', collect([$operation->actor_uuid])->merge(
                $operation->kind === 'pdf' ? DB::table('letter_pdf_requests')->where('letter_operation_id', $operation->id)->pluck('actor_uuid') : [],
            )->unique())->get()
            : collect([$recipient]);
        $operation->loadMissing('letter');
        $label = match ($operation->kind) {
            'issue' => 'Penerbitan surat',
            'pdf' => 'Konversi PDF',
            default => 'Preview surat',
        };
        $title = $operation->status === Status::SUCCEEDED
            ? ($operation->kind === 'issue' ? 'Surat berhasil diterbitkan' : 'PDF surat siap')
            : $label . ($operation->status === Status::FAILED ? ' gagal' : ' perlu diulang');

        foreach ($recipients as $user) {
            $notification = $user->notifications()->firstOrCreate(
                ['event_key' => 'letter-operation:' . $operation->uuid],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'letter-operation',
                    'data' => [
                        'schema_version' => 1,
                        'title' => $title,
                        'subject' => $operation->letter->subject,
                        'message' => $operation->message,
                        'letter_id' => $operation->letter->uuid,
                        'operation_id' => $operation->uuid,
                        'status' => strtolower($operation->status->name),
                        'kind' => $operation->kind,
                    ],
                ],
            );
            if ($notification->wasRecentlyCreated) {
                $this->events->created($user, $notification->id);
            }
        }
    }
}
