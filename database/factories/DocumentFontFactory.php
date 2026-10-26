<?php

namespace Database\Factories;

use App\Models\DocumentFont;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DocumentFont> */
class DocumentFontFactory extends Factory
{
    public function definition(): array
    {
        $hash = hash('sha256', fake()->uuid());
        $family = 'Test Font ' . fake()->uuid();

        return ['family' => $family, 'style' => 'Regular', 'face_key' => hash('sha256', mb_strtolower($family) . '|regular'), 'is_bold' => false, 'is_italic' => false, 'sha256' => $hash, 'status' => 'ready', 'disk' => 'local', 'path' => 'document-fonts/files/' . $hash . '.ttf', 'extension' => 'ttf', 'size' => 100, 'uploaded_by' => null];
    }
}
