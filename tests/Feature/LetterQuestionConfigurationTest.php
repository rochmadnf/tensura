<?php

namespace Tests\Feature;

use App\Models\MasterData\LetterTemplateVersion;
use App\Services\Letter\SpellLetterNumber;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LetterQuestionConfigurationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function radio(): array
    {
        return ['name' => 'layanan', 'label' => 'Layanan', 'type' => 5, 'is_required' => true, 'option_source' => null, 'reference_group' => null, 'is_multiple' => false, 'default_text' => null, 'employee_attributes' => [], 'columns' => [], 'radio' => ['selected_text' => '✔', 'options' => [['code' => 'jalan', 'label' => 'Rawat jalan'], ['code' => 'inap', 'label' => 'Rawat inap']]], 'numeric' => null, 'date_range' => null];
    }

    public function test_bulk_and_item_save_preserve_radio_identity_and_reject_foreign_options(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [$this->radio()], 'signer_slots' => []])->assertSessionHasNoErrors();
        $variable = $version->fresh()->variables->sole();
        $options = $variable->radioOptions;
        $this->assertCount(2, $options);
        $item = [...$this->radio(), 'id' => $variable->uuid, 'radio' => ['selected_text' => 'X', 'options' => $options->map(fn($o) => ['id' => $o->uuid, 'code' => $o->code, 'label' => $o->label])->reverse()->values()->all()]];
        $this->put(route('lt.configuration.variables.update', [$version->template->uuid, $version->uuid, $variable->uuid]), ['item' => $item])->assertSessionHasNoErrors();
        $this->assertSame('inap', $variable->fresh()->radioOptions->first()->code);
        $this->assertSame($options->last()->uuid, $variable->fresh()->radioOptions->first()->uuid);
        $item['radio']['options'][0]['id'] = '00000000-0000-4000-8000-000000000099';
        $this->put(route('lt.configuration.variables.update', [$version->template->uuid, $version->uuid, $variable->uuid]), ['item' => $item])->assertSessionHasErrors();
        $this->assertSame('X', $variable->fresh()->radio['selected_text']);
    }

    public function test_invalid_radio_and_numeric_configuration_cannot_be_saved(): void
    {
        $version = LetterTemplateVersion::factory()->create();
        $this->actingAs(UserFactory::new()->create());
        $item = $this->radio();
        $item['radio']['options'][1]['code'] = 'jalan';
        $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [$item], 'signer_slots' => []])->assertSessionHasErrors('variables.0.radio.options.1.code');
        $item = [...$this->radio(), 'type' => 7, 'radio' => null, 'numeric' => ['mode' => 'decimal', 'scale' => 3, 'allow_negative' => false]];
        $this->put(route('lt.configuration.update', [$version->template->uuid, $version->uuid]), ['variables' => [$item], 'signer_slots' => []])->assertSessionHasErrors('variables.0.numeric.scale');
        $this->assertDatabaseCount('letter_template_variables', 0);
    }

    #[DataProvider('numbers')]
    public function test_number_words_keep_decimal_precision(string $input, bool $money, string $value, string $display, string $words): void
    {
        $result = app(SpellLetterNumber::class)->resolve($input, ['mode' => 'decimal', 'scale' => $money ? 2 : 6, 'allow_negative' => true], $money);
        $this->assertSame(['value' => $value, 'display' => $display, 'words' => $words], $result);
    }

    public static function numbers(): array
    {
        return [['12.50', false, '12.5', '12,5', 'Dua Belas Koma Lima'], ['1250000.50', true, '1250000.50', 'Rp1.250.000,50', 'Satu Juta Dua Ratus Lima Puluh Ribu Rupiah Dan Lima Puluh Sen'], ['0', true, '0.00', 'Rp0,00', 'Nol Rupiah'], ['-12.05', false, '-12.05', '-12,05', 'Minus Dua Belas Koma Nol Lima'], ['999999999999.01', true, '999999999999.01', 'Rp999.999.999.999,01', 'Sembilan Ratus Sembilan Puluh Sembilan Miliar Sembilan Ratus Sembilan Puluh Sembilan Juta Sembilan Ratus Sembilan Puluh Sembilan Ribu Sembilan Ratus Sembilan Puluh Sembilan Rupiah Dan Satu Sen']];
    }
}
