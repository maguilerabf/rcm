<?php

namespace App\Service;

/**
 * Helpers para detectar la fila del header en un xlsx con metadata previa.
 */
final class HeaderDetector
{
    /**
     * Devuelve true si todos los `$required` están presentes en `$cells`
     * (comparación case-insensitive con trim).
     *
     * @param array<int, mixed> $cells
     * @param array<int, string> $required
     */
    public static function matches(array $cells, array $required): bool
    {
        $upperSet = [];
        foreach ($cells as $v) {
            if (!is_string($v) && !is_numeric($v)) continue;
            $s = strtoupper(trim((string) $v));
            if ($s !== '') $upperSet[$s] = true;
        }
        foreach ($required as $req) {
            if (!isset($upperSet[strtoupper(trim($req))])) {
                return false;
            }
        }
        return true;
    }
}
