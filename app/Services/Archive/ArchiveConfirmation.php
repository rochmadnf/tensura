<?php

namespace App\Services\Archive;

use App\Exceptions\ArchiveChangedException;
use App\Models\Archive;
use App\Models\MasterData\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class ArchiveConfirmation
{
    public function __construct(private PreviewArchive $preview) {}

    /** @param array<string, mixed> $plan */
    public function issue(Archive $archive, User $actor, array $plan): ?string
    {
        if (!$plan['can_confirm']) {
            return null;
        }

        return Crypt::encryptString(PreviewArchive::canonicalJson([
            'version' => 1, 'archive_uuid' => $archive->uuid, 'actor_id' => (string) $actor->id,
            'selection' => $plan['selection'], 'fingerprint' => $plan['fingerprint'],
        ]));
    }

    /**
     * Execution callers must hold archive and relevant active-group locks in their transaction until writes complete.
     *
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    public function verify(string $token, Archive $archive, User $actor, array $selection): array
    {
        try {
            $proof = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['confirmation_token' => 'Token konfirmasi tidak valid. Buat pratinjau kembali.']);
        }
        $selection = ArchiveSelection::normalize($selection);
        if (!is_array($proof) || ($proof['version'] ?? null) !== 1
            || ($proof['archive_uuid'] ?? null) !== $archive->uuid
            || ($proof['actor_id'] ?? null) !== (string) $actor->id
            || ($proof['selection'] ?? null) !== json_decode(PreviewArchive::canonicalJson($selection), true)
            || !is_string($proof['fingerprint'] ?? null)) {
            throw ValidationException::withMessages(['confirmation_token' => 'Token tidak sesuai pengguna, aksi, atau pilihan saat ini.']);
        }
        $current = Archive::query()->whereKey($archive->id)->where('uuid', $archive->uuid)->first();
        if ($current === null) {
            throw new ArchiveChangedException();
        }
        try {
            $plan = $this->preview->build($current, $selection);
        } catch (ValidationException) {
            throw new ArchiveChangedException();
        }
        if (!hash_equals($proof['fingerprint'], $plan['fingerprint']) || !$plan['can_confirm']) {
            throw new ArchiveChangedException();
        }

        return $plan;
    }
}
