<?php

namespace App\Http;

class FilterQuery
{
    /** @return array<string, list<string>> */
    public static function parse(mixed $filter): array
    {
        if (!is_string($filter)) {
            return [];
        }

        $filters = [];

        foreach (explode('|', $filter) as $segment) {
            $parts = explode(':', $segment);

            if (count($parts) !== 2) {
                continue;
            }

            $name = trim($parts[0]);

            if (!preg_match('/^[a-z][a-z0-9_]*$/D', $name)) {
                continue;
            }

            foreach (explode(',', $parts[1]) as $rawValue) {
                $value = trim($rawValue);

                if ($value !== '' && !in_array($value, $filters[$name] ?? [], true)) {
                    $filters[$name][] = $value;
                }
            }
        }

        return $filters;
    }
}
