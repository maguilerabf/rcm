<?php

namespace App\Service;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Devuelve el reader correcto de openspout según la extensión del archivo.
 */
class SpoutReaderFactory
{
    public function fromPath(string $path): ReaderInterface
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'xlsx' => new XlsxReader(),
            'csv' => new CsvReader($this->csvOptions()),
            default => throw new \InvalidArgumentException("Extensión no soportada: {$ext}"),
        };
    }

    private function csvOptions(): CsvOptions
    {
        $options = new CsvOptions();
        $options->FIELD_DELIMITER = ',';
        $options->FIELD_ENCLOSURE = '"';
        $options->ENCODING = 'UTF-8';
        return $options;
    }
}
