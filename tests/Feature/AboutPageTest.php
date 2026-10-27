<?php

namespace Tests\Feature;

use App\Services\Release\ReadReleaseNotes;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ?string $temporaryResources = null;

    protected function tearDown(): void
    {
        if ($this->temporaryResources !== null) {
            File::deleteDirectory($this->temporaryResources);
        }

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('about'))->assertRedirectToRoute('login.index');
    }

    public function test_authenticated_users_can_read_the_current_markdown_release_and_preserved_initial_notes(): void
    {
        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->component('about/index')
            ->where('page.id', 'about')
            ->where('app.version', '0.3.0')
            ->has('releases', 3)
            ->where('releases.0.version', '0.3.0')
            ->where('releases.0.content_html', fn(string $html): bool => str_contains($html, 'Blok berulang vertikal') && str_contains($html, 'Pemeriksaan melalui PDF'))
            ->where('releases.1.version', '0.2.0')
            ->where('releases.1.content_html', fn(string $html): bool => str_contains($html, 'Pusat notifikasi') && str_contains($html, 'Animasi dan suara'))
            ->where('releases.2.version', '0.1.0')
            ->where('releases.2.content_html', fn(string $html): bool => str_contains($html, 'Pengelolaan surat') && str_contains($html, 'Aplikasi masih dalam pengembangan awal.')));
    }

    public function test_releases_are_sorted_numerically_and_keep_their_identity_independent_of_the_current_version(): void
    {
        $this->releases([
            '0.2.0.md' => 'Versi kedua.',
            '0.10.0.md' => 'Versi kesepuluh.',
            '0.1.9.md' => 'Versi sebelumnya.',
            'README.md' => 'Panduan internal, bukan catatan rilis.',
            '1.0.md' => 'Nama file tidak sesuai.',
            '01.0.0.md' => 'Nomor versi tidak sesuai.',
        ]);
        config(['app.version' => '0.3.0']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('app.version', '0.3.0')
            ->has('releases', 3)
            ->where('releases.0.version', '0.10.0')
            ->where('releases.0.content_html', "<p>Versi kesepuluh.</p>\n")
            ->where('releases.1.version', '0.2.0')
            ->where('releases.2.version', '0.1.9'));
    }

    #[DataProvider('emptyReleaseDirectories')]
    public function test_missing_or_empty_release_directories_preserve_the_current_version(bool $createDirectory): void
    {
        $this->releases([], $createDirectory);
        config(['app.version' => '0.3.0']);

        $this->actingAs(UserFactory::new()->create())->withoutVite()
            ->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('app.version', '0.3.0')
            ->has('releases', 0));
    }

    public static function emptyReleaseDirectories(): array
    {
        return ['empty' => [true], 'missing' => [false]];
    }

    public function test_markdown_formatting_is_rendered_while_raw_html_and_unsafe_links_are_removed(): void
    {
        $this->releases(['0.2.0.md' => <<<'MARKDOWN'
**Pusat notifikasi** dengan *format Markdown*.

- Daftar perubahan

1. Urutan penggunaan

Gunakan `Aktifkan suara` atau [panduan](https://example.com/panduan).

<script>alert('unsafe')</script>

<img src="invalid" onerror="alert('unsafe')">

[tautan berbahaya](javascript:alert%281%29)
MARKDOWN]);

        $response = $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('about'))->assertOk();

        $response->assertInertia(function (Assert $page): void {
            $page->where('releases.0.content_html', function (string $html): bool {
                $this->assertStringContainsString('<strong>Pusat notifikasi</strong>', $html);
                $this->assertStringContainsString('<em>format Markdown</em>', $html);
                $this->assertStringContainsString('<ul>', $html);
                $this->assertStringContainsString('<ol>', $html);
                $this->assertStringContainsString('<code>Aktifkan suara</code>', $html);
                $this->assertStringContainsString('href="https://example.com/panduan"', $html);
                $this->assertStringNotContainsString('<script', $html);
                $this->assertStringNotContainsString('<img', $html);
                $this->assertStringNotContainsString('onerror=', $html);
                $this->assertStringNotContainsString('href="javascript:', $html);

                return true;
            });
        });
    }

    public function test_markdown_edits_are_visible_on_the_next_request_without_changing_configuration(): void
    {
        $this->releases(['0.2.0.md' => 'Catatan pertama.']);
        $this->actingAs(UserFactory::new()->create())->withoutVite()->get(route('about'))
            ->assertOk()->assertInertia(fn(Assert $page) => $page->where('releases.0.content_html', "<p>Catatan pertama.</p>\n"));

        File::put($this->temporaryResources . '/0.2.0.md', 'Catatan diperbarui.');

        $this->get(route('about'))->assertOk()->assertInertia(fn(Assert $page) => $page
            ->where('releases.0.version', '0.2.0')
            ->where('releases.0.content_html', "<p>Catatan diperbarui.</p>\n"));
    }

    /** @param array<string, string> $notes */
    private function releases(array $notes, bool $createDirectory = true): void
    {
        $this->temporaryResources = sys_get_temp_dir() . '/tensura-release-notes-' . Str::uuid();
        $this->app->instance(ReadReleaseNotes::class, new ReadReleaseNotes($this->temporaryResources));
        if ($createDirectory) {
            File::ensureDirectoryExists($this->temporaryResources);
        }
        foreach ($notes as $filename => $content) {
            File::put($this->temporaryResources . '/' . $filename, $content);
        }
    }
}
