<?php

namespace App\Http\Requests;

use App\Models\Letter;
use App\Models\MasterData\LetterTemplate;
use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\LetterDraftRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(LetterDraftRules $rules): array
    {
        $version = $this->version();

        return [...$rules->identity(), ...($version === null ? [] : $rules->fields($version)), 'template' => ['required', 'uuid']];
    }

    public function attributes(): array
    {
        return app(LetterDraftRules::class)->attributes($this->version());
    }

    public function after(): array
    {
        return [fn($validator) => app(LetterDraftRules::class)->checkPeriod($validator, $this->all())];
    }

    protected function version(): ?LetterTemplateVersion
    {
        $letter = $this->route('letter');
        if ($letter instanceof Letter) {
            return $letter->draft?->templateVersion;
        }
        $template = $this->input('template');
        $version = $this->input('template_version');
        if (!is_string($template) || !is_string($version) || !Str::isUuid($template) || !Str::isUuid($version)) {
            return null;
        }

        return LetterTemplate::query()->where('uuid', $template)->first()?->versions()->where('uuid', $version)->first();
    }
}
