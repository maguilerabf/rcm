<?php

namespace App\Message;

final readonly class ImportInscritosMessage
{
    public function __construct(public string $jobId)
    {
    }
}
