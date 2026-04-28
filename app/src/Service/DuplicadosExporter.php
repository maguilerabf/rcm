<?php

namespace App\Service;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class DuplicadosExporter
{
    private const COLUMNS = [
        'group_id' => '# Grupo',
        'match_type' => 'Tipo coincidencia',
        'criterio' => 'Criterio',
        'run_dv' => 'RUN',
        'nombres' => 'Nombres',
        'apellido_paterno' => 'Apellido paterno',
        'apellido_materno' => 'Apellido materno',
        'fecha_nacimiento' => 'Fecha nacimiento',
        'sector' => 'Sector',
        'estado' => 'Estado',
        'situacion' => 'Situación',
        'establecimiento' => 'Establecimiento',
    ];

    public function __construct(private readonly DuplicadosInscritosService $service)
    {
    }

    public function writeToPath(string $path, ?array $matchTypes = null, ?string $search = null): int
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(array_values(self::COLUMNS), (new Style())->setFontBold()));

        $count = 0;
        foreach ($this->service->streamFlat($matchTypes, $search) as $row) {
            $cells = [];
            foreach (array_keys(self::COLUMNS) as $key) {
                $value = $row[$key] ?? null;

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

    public function suggestFilename(): string
    {
        $stamp = (new \DateTimeImmutable())->format('Ymd_His');
        return "duplicados_inscritos_{$stamp}.xlsx";
    }
}
