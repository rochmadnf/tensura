<?php

namespace App\Services\WorkCalendar;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Parser adapted from api-hari-libur, Copyright (c) 2023 Pringgo Radianto.
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class FetchIndonesianHolidays
{
    public bool $fromCache = false;

    public ?string $fetchedAt = null;

    public function url(int $year): string
    {
        return 'https://tanggalans.com/kalender-' . $year . '/';
    }

    /** @return list<array{date: string, name: string, is_collective_leave: bool, source_url: string}> */
    public function fetch(int $year): array
    {
        if ($year < 1900 || $year > 2199) {
            throw new CalendarFailure('validation_failed', 'Tahun di luar batas kalender.');
        }
        $key = 'work-calendar.holidays.v1.' . $year;
        $cached = Cache::get($key);
        $this->fromCache = $cached !== null;
        if ($cached === null) {
            try {
                $response = Http::connectTimeout(3)->timeout(10)->retry(2, 100, throw: false)->withOptions(['allow_redirects' => false, 'stream' => true])->get($this->url($year));
                if (!$response->successful()) {
                    throw new CalendarFailure('holiday_source_failed', 'Sumber libur belum dapat diakses.', 502);
                }
                $body = $response->toPsrResponse()->getBody()->read(2 * 1024 * 1024 + 1);
                if (strlen($body) > 2 * 1024 * 1024) {
                    throw new CalendarFailure('holiday_source_changed', 'Ukuran sumber hari libur tidak sesuai.', 502);
                }
                $cached = ['items' => $this->parse($body, $year), 'fetched_at' => now()->toIso8601String()];
                Cache::put($key, $cached, now()->addDay());
            } catch (CalendarFailure $e) {
                throw $e;
            } catch (Throwable $e) {
                report($e);
                throw new CalendarFailure('holiday_source_failed', 'Sumber libur belum dapat diakses. Data kalender tetap tersimpan.', 502);
            }
        }
        $this->fetchedAt = $cached['fetched_at'];

        return $cached['items'];
    }

    /** @return list<array{date: string, name: string, is_collective_leave: bool, source_url: string}> */
    public function parse(string $html, int $year): array
    {
        if (stripos($html, '<!ENTITY') !== false || strlen($html) > 2 * 1024 * 1024) {
            throw new CalendarFailure('holiday_source_changed', 'Struktur sumber libur tidak sesuai.', 502);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new DOMXPath($dom);
            $class = fn(string $name): string => "contains(concat(' ',normalize-space(@class),' '),' " . $name . " ')";
            $months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
            $seen = [];
            $items = [];
            $blocks = $xpath->query('//*[' . $class('entry-content') . ']//*[' . $class('kalender-indo') . ']');
            foreach ($blocks as $block) {
                $title = trim($xpath->query('.//*[' . $class('kal-title-link') . ']', $block)->item(0)?->textContent ?? '');
                if (!preg_match('/^([A-Za-z]+)\s+(\d{4})$/u', $title, $match) || (int) $match[2] !== $year || ($month = array_search(strtolower($match[1]), $months, true)) === false || isset($seen[$month])) {
                    throw new CalendarFailure('holiday_source_changed', 'Judul bulan pada sumber libur berubah.', 502);
                }
                $seen[$month] = true;
                $month++;
                foreach ($xpath->query('.//*[' . $class('kal-libur-list') . ']//li', $block) as $li) {
                    $day = $xpath->query('.//*[' . $class('kal-libur-day') . ']', $li)->item(0);
                    if ($day === null || !preg_match('/^(\d{1,2})(?:\s*[-–]\s*(\d{1,2}))?$/u', trim($day->textContent), $range)) {
                        throw new CalendarFailure('holiday_source_changed', 'Tanggal libur pada sumber berubah.', 502);
                    }
                    $name = '';
                    for ($node = $day->nextSibling; $node !== null; $node = $node->nextSibling) {
                        $name .= $node->textContent;
                    }
                    $name = trim(preg_replace('/\s+/u', ' ', $name));
                    $first = (int) $range[1];
                    $last = (int) ($range[2] ?? $range[1]);
                    if ($name === '' || mb_strlen($name) > 150 || $first > $last || !checkdate($month, $first, $year) || !checkdate($month, $last, $year)) {
                        throw new CalendarFailure('holiday_source_changed', 'Isi libur pada sumber tidak valid.', 502);
                    }
                    for ($d = $first; $d <= $last; $d++) {
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $items[$date . '|' . $name] = ['date' => $date, 'name' => $name, 'is_collective_leave' => str_contains(mb_strtolower($name), 'cuti bersama'), 'source_url' => $this->url($year)];
                    }
                }
            }
            if (count($seen) !== 12 || $items === []) {
                throw new CalendarFailure('holiday_source_changed', 'Daftar dua belas bulan atau hari libur tidak ditemukan. Periksa sumber.', 502);
            }
            ksort($items);

            return array_values($items);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
