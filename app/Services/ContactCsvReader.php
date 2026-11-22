<?php

namespace App\Services;

class ContactCsvReader
{
    // Retain at most 64 KB per record, even for a huge quoted field or millions of columns.
    // Oversized records are consumed to the next unquoted newline without storing their data.
    public function read($handle): ?array
    {
        $raw = '';
        $size = 0;
        $state = 0; // 0: unquoted, 1: quoted, 2: closing/escaped quote
        $fieldStart = true;
        while (($part = fgets($handle, 8192)) !== false) {
            $size += strlen($part);
            $raw = $size <= 65536 ? $raw.$part : '';
            for ($i = 0, $length = strlen($part); $i < $length; $i++) {
                $char = $part[$i];
                if ($state === 1) {
                    if ($char === '"') {
                        $state = 2;
                    }
                } elseif ($state === 2 && $char === '"') {
                    $state = 1;
                } elseif ($char === ',') {
                    $state = 0;
                    $fieldStart = true;
                } elseif ($char === '"' && $fieldStart && $state === 0) {
                    $state = 1;
                    $fieldStart = false;
                } elseif ($char !== ' ' && $char !== "\t") {
                    $fieldStart = false;
                }
            }
            if (str_ends_with($part, "\n") && $state !== 1) {
                break;
            }
        }
        if ($size === 0) {
            return null;
        }

        return ['oversized' => $size > 65536, 'malformed' => $state === 1, 'values' => $size <= 65536 ? str_getcsv($raw, ',', '"', '') : []];
    }
}
