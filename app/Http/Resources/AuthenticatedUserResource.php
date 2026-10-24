<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuthenticatedUserResource extends JsonResource
{
    public static $wrap = null;

    protected function gravatarUrl(string $email, int $size = 200): string
    {
        $email = strtolower(trim($email));
        $hash = hash('sha256', $email);

        return "https://gravatar.com/avatar/{$hash}?s={$size}&d=404";
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'avatar' => is_null($this->avatar)
                ? $this->gravatarUrl(email: $this->email)
                : (str_starts_with($this->avatar, 'avatars/') ? Storage::disk('public')->url($this->avatar) : $this->avatar),
        ];
    }
}
