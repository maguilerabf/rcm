<?php

namespace App\Message;

final readonly class ImportTelesaludMessage
{
    public function __construct(public string $jobId)
    {
    }
}
