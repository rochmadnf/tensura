<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentFontRequest;
use App\Models\DocumentFont;
use App\Services\Letter\DocumentFontRegistry;
use App\Services\Letter\InstallDocumentFont;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentFontController extends Controller
{
    public function index(Request $request, DocumentFontRegistry $registry): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000']]);
        $inventory = $registry->inventory();
        $query = mb_strtolower($data['q'] ?? '');
        $faces = collect($inventory['faces'])->filter(fn(array $face): bool => str_contains(mb_strtolower($face['family'] . ' ' . $face['style']), $query))->unique(fn(array $face): string => $registry->faceKey($face['family'], $face['style']))->values();
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['available' => $inventory['available'], 'warning' => $inventory['warning'], 'data' => $faces->slice(($page - 1) * 30, 30)->map(fn(array $face): array => ['family' => $face['family'], 'style' => $face['style']])->values(), 'page' => $page, 'total' => $faces->count(), 'last_page' => max(1, (int) ceil($faces->count() / 30)), 'max_upload_kb' => config('document-fonts.max_upload_kb')])->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreDocumentFontRequest $request, InstallDocumentFont $installer): RedirectResponse
    {
        $installer->install($request->file('font'), $request->user());

        return back(303);
    }

    public function file(DocumentFont $documentFont): BinaryFileResponse
    {
        abort_unless($documentFont->status === 'ready', 404);
        $disk = Storage::disk($documentFont->disk);
        abort_unless($disk->exists($documentFont->path) && hash_file('sha256', $disk->path($documentFont->path)) === $documentFont->sha256, 404);

        $response = response()->file($disk->path($documentFont->path), ['Content-Type' => 'font/' . $documentFont->extension, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
        $response->setPrivate();

        return $response;
    }
}
