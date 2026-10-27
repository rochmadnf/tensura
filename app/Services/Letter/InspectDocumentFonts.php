<?php

namespace App\Services\Letter;

use App\Services\LetterTemplate\InspectLetterTemplateDocx;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class InspectDocumentFonts
{
    private const string DRAWING_NAMESPACE = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /**
     * Inspect effective formatting on actual text runs; fontTable and unused styles are not usage.
     *
     * @param  array<string, DOMDocument>  $parts  Validated, size-bounded OOXML from InspectLetterTemplateDocx.
     * @return array{requirements: list<array{family: string, bold: bool, italic: bool}>, limitations: list<string>}
     */
    public function inspect(array $parts): array
    {
        $styles = [];
        $defaultParagraph = null;
        $defaultCharacter = null;
        $defaults = [];
        $limitations = [];
        if (isset($parts['word/styles.xml'])) {
            $xpath = $this->xpath($parts['word/styles.xml']);
            $defaults = $this->properties($xpath->query('//w:docDefaults/w:rPrDefault/w:rPr')->item(0));
            foreach ($xpath->query('//w:style') as $style) {
                $id = $style->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'styleId');
                $styles[$id] = $style;
                if ($style->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'type') === 'character' && in_array($style->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'default'), ['1', 'true', 'on'], true)) {
                    $defaultCharacter = $id;
                }
                if ($style->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'type') === 'paragraph' && in_array($style->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'default'), ['1', 'true', 'on'], true)) {
                    $defaultParagraph = $id;
                }
            }
        }
        if (isset($parts['word/settings.xml'])) {
            $settings = $this->xpath($parts['word/settings.xml']);
            $language = $settings->query('//w:themeFontLang')->item(0)?->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'eastAsia');
            if (filled($language)) {
                $defaults['eastAsiaLang'] = $language;
            }
        }
        $themes = [];
        if (isset($parts['word/theme/theme1.xml'])) {
            $theme = $this->xpath($parts['word/theme/theme1.xml']);
            foreach (['major', 'minor'] as $kind) {
                foreach (['latin', 'ea', 'cs'] as $script) {
                    $themes[$kind][$script] = $theme->query('//a:fontScheme/a:' . $kind . 'Font/a:' . $script)->item(0)?->getAttribute('typeface') ?? '';
                }
                foreach ($theme->query('//a:fontScheme/a:' . $kind . 'Font/a:font') as $font) {
                    $themes[$kind][$font->getAttribute('script')] = $font->getAttribute('typeface');
                }
            }
        }
        $requirements = [];
        foreach ($parts as $name => $document) {
            if (!preg_match('~^word/(?:document|header[1-9][0-9]*|footer[1-9][0-9]*|footnotes|endnotes|comments)\.xml$~D', $name)) {
                continue;
            }
            $xpath = $this->xpath($document);
            if ($xpath->query('//a:t')->length > 0) {
                $limitations[] = 'Teks di gambar/bentuk DrawingML belum dapat dipetakan sepenuhnya. Periksa hasil dokumen.';
            }
            if ($xpath->query('//w:numPr')->length > 0) {
                $limitations[] = 'Font simbol penomoran/bullet otomatis belum dapat dipastikan. Periksa hasil dokumen.';
            }
            foreach ($xpath->query('//w:r[w:t or w:sym]') as $run) {
                $text = '';
                foreach ($xpath->query('./w:t', $run) as $node) {
                    $text .= $node->textContent;
                }
                if ($text === '' && $xpath->query('./w:sym', $run)->length === 0) {
                    continue;
                }
                $properties = $defaults;
                $table = $this->ancestor($run, 'tbl');
                if ($table !== null) {
                    $id = $xpath->query('./w:tblPr/w:tblStyle', $table)->item(0)?->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'val');
                    $properties = $this->style($id, $styles, $properties, $limitations);
                    if (isset($styles[$id]) && $styles[$id]->getElementsByTagNameNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'tblStylePr')->length > 0) {
                        $limitations[] = 'Format tabel bersyarat belum dapat dipastikan seluruhnya. Periksa font pada baris/kolom khusus.';
                    }
                }
                $paragraph = $this->ancestor($run, 'p');
                $paragraphId = $paragraph === null ? null : $xpath->query('./w:pPr/w:pStyle', $paragraph)->item(0)?->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'val');
                $properties = $this->style($paragraphId ?? $defaultParagraph, $styles, $properties, $limitations);
                $characterId = $xpath->query('./w:rPr/w:rStyle', $run)->item(0)?->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'val');
                $properties = $this->style($characterId ?? $defaultCharacter, $styles, $properties, $limitations);
                $properties = $this->merge($properties, $this->properties($xpath->query('./w:rPr', $run)->item(0)), false);
                foreach ($xpath->query('./w:sym', $run) as $symbol) {
                    $this->add($requirements, $symbol->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'font'), $properties);
                }
                $scripts = [];
                if (preg_match('/[\x00-\x7f]/u', $text)) {
                    $scripts[] = 'ascii';
                }
                if (preg_match('/[^\x00-\x7f]/u', preg_replace('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Hebrew}\p{Devanagari}\p{Thai}]/u', '', $text))) {
                    $scripts[] = 'hAnsi';
                }
                if (preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]/u', $text)) {
                    $scripts[] = 'eastAsia';
                }
                if (preg_match('/[\p{Arabic}\p{Hebrew}\p{Devanagari}\p{Thai}]/u', $text) || ($properties['cs'] ?? false)) {
                    $scripts[] = 'cs';
                }
                foreach ($scripts as $script) {
                    if (($properties['cs'] ?? false)) {
                        $script = 'cs';
                    }
                    $font = $properties['fonts'][$script] ?? null;
                    if ($font === null) {
                        $limitations[] = 'Sebagian teks memakai font bawaan yang tidak disebutkan dalam DOCX. Periksa hasil dengan font fallback.';

                        continue;
                    }
                    if ($font['theme']) {
                        $kind = str_starts_with($font['value'], 'major') ? 'major' : 'minor';
                        $slot = match ($script) {
                            'eastAsia' => 'ea', 'cs' => 'cs', default => 'latin'
                        };
                        $family = $themes[$kind][$slot] ?? '';
                        if ($family === '' && $script === 'eastAsia') {
                            $supplemental = preg_match('/\p{Hangul}/u', $text) ? 'Hang' : ((preg_match('/[\p{Hiragana}\p{Katakana}]/u', $text) || str_starts_with($properties['eastAsiaLang'] ?? '', 'ja')) ? 'Jpan' : (($properties['eastAsiaLang'] ?? '') === 'zh-TW' ? 'Hant' : 'Hans'));
                            $family = $themes[$kind][$supplemental] ?? '';
                        }
                        if ($family === '' && $script === 'cs') {
                            $supplemental = preg_match('/\p{Arabic}/u', $text) ? 'Arab' : (preg_match('/\p{Hebrew}/u', $text) ? 'Hebr' : (preg_match('/\p{Thai}/u', $text) ? 'Thai' : 'Deva'));
                            $family = $themes[$kind][$supplemental] ?? '';
                        }
                        if ($family === '') {
                            $limitations[] = 'Sebagian referensi font tema tidak dapat diidentifikasi. Periksa font tema di Word.';

                            continue;
                        }
                    } else {
                        $family = $font['value'];
                    }
                    $this->add($requirements, $family, $properties, $script === 'cs');
                }
            }
        }
        ksort($requirements);

        return ['requirements' => array_values($requirements), 'limitations' => array_values(array_unique($limitations))];
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', InspectLetterTemplateDocx::WORD_NAMESPACE);
        $xpath->registerNamespace('a', self::DRAWING_NAMESPACE);

        return $xpath;
    }

    private function ancestor(DOMNode $node, string $name): ?DOMElement
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->localName === $name && $parent->namespaceURI === InspectLetterTemplateDocx::WORD_NAMESPACE) {
                return $parent;
            }
        }

        return null;
    }

    private function properties(?DOMNode $node): array
    {
        $result = [];
        if ($node === null) {
            return $result;
        }
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement || $child->namespaceURI !== InspectLetterTemplateDocx::WORD_NAMESPACE) {
                continue;
            }
            if ($child->localName === 'rFonts') {
                foreach (['ascii', 'hAnsi', 'eastAsia', 'cs'] as $script) {
                    $themeAttribute = $script === 'cs' ? 'cstheme' : $script . 'Theme';
                    if ($child->hasAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, $themeAttribute)) {
                        $result['fonts'][$script] = ['value' => $child->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, $themeAttribute), 'theme' => true];
                    } elseif ($child->hasAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, $script)) {
                        $result['fonts'][$script] = ['value' => $child->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, $script), 'theme' => false];
                    }
                }
            } elseif (in_array($child->localName, ['b', 'i', 'bCs', 'iCs', 'cs'], true)) {
                $result[$child->localName] = !in_array($child->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'val'), ['0', 'false', 'off'], true);
            } elseif ($child->localName === 'lang') {
                $result['eastAsiaLang'] = $child->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'eastAsia');
            }
        }

        return $result;
    }

    private function style(?string $id, array $styles, array $base, array &$limitations, array $seen = []): array
    {
        if ($id === null || $id === '') {
            return $base;
        }
        if (isset($seen[$id]) || !isset($styles[$id]) || count($seen) > 100) {
            $limitations[] = 'Ada style hilang atau pewarisan style yang tidak dapat dipastikan.';

            return $base;
        }
        $seen[$id] = true;
        $style = $styles[$id];
        $xpath = $this->xpath($style->ownerDocument);
        $parent = $xpath->query('./w:basedOn', $style)->item(0)?->getAttributeNS(InspectLetterTemplateDocx::WORD_NAMESPACE, 'val');
        $base = $this->style($parent, $styles, $base, $limitations, $seen);

        return $this->merge($base, $this->properties($xpath->query('./w:rPr', $style)->item(0)), true);
    }

    private function merge(array $base, array $properties, bool $toggle): array
    {
        foreach ($properties as $key => $value) {
            if ($key === 'fonts') {
                $base['fonts'] = [...($base['fonts'] ?? []), ...$value];
            } elseif ($toggle && in_array($key, ['b', 'i', 'bCs', 'iCs'], true)) {
                if ($value) {
                    $base[$key] = !($base[$key] ?? false);
                }
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function add(array &$requirements, string $family, array $properties, bool $complex = false): void
    {
        $family = trim($family);
        if ($family === '') {
            return;
        }
        $bold = $properties[$complex ? 'bCs' : 'b'] ?? false;
        $italic = $properties[$complex ? 'iCs' : 'i'] ?? false;
        $key = mb_strtolower($family) . '|' . (int) $bold . '|' . (int) $italic;
        $requirements[$key] = ['family' => $family, 'bold' => $bold, 'italic' => $italic];
    }
}
