<?php

namespace App\Service;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Genera xlsx en streaming con todas las coincidencias del cruce telesalud × inscritos.
 * Memoria constante incluso para decenas de miles de filas.
 * La celda de Sector se rellena con el color del sector.
 */
class CoincidenciasExporter
{
    private const COLUMNS = [
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

    public function writeToPath(string $path, ?string $sector = null): int
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $headerStyle = (new Style())->setFontBold();
        $writer->addRow(Row::fromValues(array_values(self::COLUMNS), $headerStyle));

        $count = 0;
        foreach ($this->coincidencias->streamAll($sector) as $row) {
            $cells = [];
            foreach (array_keys(self::COLUMNS) as $key) {
                $value = $row[$key] ?? null;
                if ($value instanceof \DateTimeInterface) {
                    $value = $value->format('Y-m-d H:i:s');
                }

                if ($key === 'sector' && $value) {
                    $bg = SectorColors::backgroundFor((string) $value);
                    $style = $bg ? (new Style())->setBackgroundColor($bg) : null;
                    $cells[] = Cell::fromValue($value, $style);
                } else {
                    $cells[] = Cell::fromValue($value);
                }
            }
            $writer->addRow(new Row($cells, null));
            $count++;
        }

        $writer->close();
        return $count;
    }

    public function suggestFilename(?string $sector = null): string
    {
        $stamp = (new \DateTimeImmutable())->format('Ymd_His');
        $suffix = $sector ? '_' . preg_replace('/[^A-Za-z0-9]+/', '_', $sector) : '';
        return "coincidencias_sectores{$suffix}_{$stamp}.xlsx";
    }
}
