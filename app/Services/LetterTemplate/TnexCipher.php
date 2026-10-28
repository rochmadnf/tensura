<?php

namespace App\Services\LetterTemplate;

use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class TnexCipher
{
    private const string MAGIC = "TNEX\x01";

    private const int CHUNK = 65536;

    /** Fixed format-v1 parameters; changing these requires a new format version. */
    private const int V1_OPERATIONS = 3;

    private const int V1_MEMORY_BYTES = 67108864;

    public function encrypt(string $payload, #[SensitiveParameter] string $password): string
    {
        $this->assertSize(strlen($payload));
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = $this->key($password, $salt);
        try {
            [$state, $streamHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            $header = self::MAGIC . $salt . $streamHeader;
            $encrypted = $header;
            for ($offset = 0; $offset < strlen($payload); $offset += self::CHUNK) {
                $chunk = substr($payload, $offset, self::CHUNK);
                $tag = $offset + self::CHUNK >= strlen($payload) ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $encrypted .= sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, $header, $tag);
            }

            return $encrypted;
        } finally {
            sodium_memzero($key);
            if (isset($state)) {
                sodium_memzero($state);
            }
        }
    }

    public function decrypt(string $encrypted, #[SensitiveParameter] string $password): string
    {
        $this->assertSize(strlen($encrypted));
        $headerLength = strlen(self::MAGIC) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES;
        if (!str_starts_with($encrypted, self::MAGIC) || strlen($encrypted) <= $headerLength + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) {
            $this->reject();
        }
        $header = substr($encrypted, 0, $headerLength);
        $salt = substr($header, strlen(self::MAGIC), SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $key = $this->key($password, $salt);
        try {
            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull(substr($header, -SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES), $key);
            $payload = '';
            $final = false;
            $frameLength = self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;
            for ($offset = $headerLength; $offset < strlen($encrypted); $offset += $frameLength) {
                $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, substr($encrypted, $offset, $frameLength), $header);
                if ($result === false || $final) {
                    $this->reject();
                }
                [$chunk, $tag] = $result;
                $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
                if (!$final && ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE || strlen($chunk) !== self::CHUNK)) {
                    $this->reject();
                }
                $payload .= $chunk;
            }
            if (!$final) {
                $this->reject();
            }

            return $payload;
        } finally {
            sodium_memzero($key);
            if (isset($state)) {
                sodium_memzero($state);
            }
        }
    }

    private function key(#[SensitiveParameter] string $password, string $salt): string
    {
        if (mb_strlen($password) < 12 || strlen($password) > 1024) {
            throw ValidationException::withMessages(['password' => 'Kata sandi harus berisi minimal 12 karakter dan maksimal 1024 byte.']);
        }

        return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $password, $salt, self::V1_OPERATIONS, self::V1_MEMORY_BYTES, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    private function assertSize(int $size): void
    {
        if ($size < 1 || $size > config('letter-templates.exchange.max_bytes')) {
            throw ValidationException::withMessages(['package' => 'Ukuran paket melewati batas yang diizinkan.']);
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['package' => 'Paket tidak valid, versi format belum didukung, atau kata sandi salah.']);
    }
}
