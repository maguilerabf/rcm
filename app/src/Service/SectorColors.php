<?php

namespace App\Service;

/**
 * Mapeo de nombre de sector a color hex (para celdas xlsx).
 * Hex sin '#'. Tonos pasteles para que el texto negro se lea bien.
 */
final class SectorColors
{
    public const NO_INSCRITOS = 'FEF3C7'; // amber-100 — solicitudes sin cruce con padrón
    public const DUPLICADO = 'FECACA';    // red-200 — repetidos del mismo prestador

    public static function backgroundFor(?string $sector): ?string
    {
        if ($sector === null || $sector === '') return null;
        $s = mb_strtolower($sector);
        if (str_contains($s, 'azul'))     return 'DBEAFE'; // blue-100
        if (str_contains($s, 'rojo'))     return 'FEE2E2'; // red-100
        if (str_contains($s, 'verde'))    return 'D1FAE5'; // emerald-100
        if (str_contains($s, 'amarill'))  return 'FEF3C7'; // amber-100
        if (str_contains($s, 'no informado') || str_contains($s, 'noinf')) return 'F1F5F9'; // slate-100
        return 'EDE9FE'; // violet-100 (fallback genérico)
    }
}
