<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountAvatarRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AccountAvatarController extends Controller
{
    public function store(StoreAccountAvatarRequest $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatar = $user->avatar;
        $path = $request->validated('avatar')->store('avatars', 'public');

        if ($path === false) {
            throw ValidationException::withMessages(['avatar' => 'Foto profil gagal disimpan. Silakan coba lagi.']);
        }

        try {
            $user->update(['avatar' => $path]);
        } catch (Throwable $exception) {
            $this->deleteLocalAvatar($path);

            throw $exception;
        }

        $this->deleteLocalAvatar($oldAvatar);

        return to_route('account.edit');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $oldAvatar = $user->avatar;
        $user->update(['avatar' => null]);
        $this->deleteLocalAvatar($oldAvatar);

        return to_route('account.edit');
    }

    private function deleteLocalAvatar(?string $path): void
    {
        if ($path === null || !preg_match('~\Aavatars/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z~', $path)) {
            return;
        }

        try {
            if (!Storage::disk('public')->delete($path)) {
                report(new RuntimeException('Gagal menghapus foto profil lama.'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
