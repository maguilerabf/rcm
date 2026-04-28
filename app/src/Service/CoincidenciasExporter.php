<?php

namespace App\Service;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera xlsx en streaming con todas las coincidencias del cruce telesalud × inscritos.
 * Memoria constante incluso para decenas de miles de filas.
 *
 * Coloreo:
 *  - Celda Sector con el color del sector (o ámbar si la persona no está en Inscritos).
 *  - Toda la fila en rojo cuando `repeticiones > 1` (mismo RUN+DV con mismo prestador).
 */
class CoincidenciasExporter
{
    private const COLUMNS = [
        'repeticiones' => 'Repeticiones',
        'external_id' => 'ID Solicitud',
        'cesfam' => 'Cesfam',
        'sector' => 'Sector',
        'prioridad' => 'Prioridad',
        'codigo_seguimiento' => 'Código seguimiento',
        'fecha_solicitud' => 'Fecha solicitud',
        'genero' => 'Género',
        'nombre' => 'Nombre',
        'apellido_paterno' => 'Apellido paterno',
        'apellido_materno' => 'Apellido materno',
        'nombre_social' => 'Nombre social',
        'tipo_identificador' => 'Tipo identificador',
        'num_identificador' => 'Nº identificador',
        'edad' => 'Edad',
        'email' => 'Email',
        'telefono' => 'Teléfono',
        'direccion' => 'Dirección',
        'tipo_prestador' => 'Tipo prestador',
        'motivo_consulta' => 'Motivo consulta',
        'especificidad' => 'Especificidad',
        'estado' => 'Estado',
        'contactado' => 'Contactado',
        'informacion_adicional' => 'Información adicional',
    ];

    public function __construct(private readonly CoincidenciasService $coincidencias)
    {
    }

    public function writeToPath(string $path, ?string $sector = null, ?string $prestador = null): int
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())->setFontBold();
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS), $headerStyle));

        $count = 0;
        foreach ($this->coincidencias->streamAll($sector, $prestador) as $row) {
            $repeticiones = (int) ($row['repeticiones'] ?? 1);
            $enInscritos = !empty($row['en_inscritos']);
            $rowStyle = $repeticiones > 1
                ? (new Style())->setBackgroundColor(SectorColors::DUPLICADO)
                : null;

            $cells = [];
            foreach (array_keys(self::COLUMNS) as $key) {
                $value = $row[$key] ?? null;

                if ($key === 'sector') {
                    if (!$enInscritos) {
                        $value = 'No inscritos';
                        $bg = $repeticiones > 1 ? SectorColors::DUPLICADO : SectorColors::NO_INSCRITOS;
                        $cells[] = Cell::fromValue($value, (new Style())->setBackgroundColor($bg)->setFontBold());
                        continue;
                    }
                    $bg = $value ? SectorColors::backgroundFor((string) $value) : null;
                    if ($repeticiones > 1) {
                        $bg = SectorColors::DUPLICADO;
                    }
                    $style = $bg ? (new Style())->setBackgroundColor($bg) : null;
                    $cells[] = Cell::fromValue($value, $style);
                    continue;
                }

                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }
                $cells[] = Cell::fromValue($value);
            }

            $writer->addRow(new Row($cells, $rowStyle));
            $count++;
        }

        $writer->close();
        return $count;
    }

    public function suggestFilename(?string $sector = null, ?string $prestador = null): string
    {
        $stamp = (new \DateTimeImmutable())->format('Ymd_His');
        $suffix = '';
        if ($sector === '__no_inscritos__') {
            $suffix .= '_no_inscritos';
        } elseif ($sector === '__sin_sector__') {
            $suffix .= '_sin_sector';
        } elseif ($sector) {
            $suffix .= '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $sector);
        }
        if ($prestador === '__sin_prestador__') {
            $suffix .= '_sin_prestador';
        } elseif ($prestador) {
            $suffix .= '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $prestador);
        }
        return "coincidencias_sectores{$suffix}_{$stamp}.xlsx";
    }
}
