<?php

namespace App\Services\Release;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

class ReadReleaseNotes
{
    public function __construct(private ?string $directory = null) {}

    /** @return list<array{version: string, content_html: string}> */
    public function read(): array
    {
        $directory = $this->directory ?? resource_path('markdown/releases');
        if (!File::isDirectory($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn(SplFileInfo $file): bool => preg_match('/\A(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)\.md\z/', $file->getFilename()) === 1)
            ->map(fn(SplFileInfo $file): array => [
                'version' => $file->getBasename('.md'),
                'content_html' => Str::markdown(File::get($file->getPathname()), [
                    'html_input' => 'strip',
                    'allow_unsafe_links' => false,
                ]),
            ])
            ->sort(fn(array $left, array $right): int => version_compare($right['version'], $left['version']))
            ->values()->all();
    }
}
