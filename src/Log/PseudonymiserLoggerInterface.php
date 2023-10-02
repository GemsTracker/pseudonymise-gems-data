<?php

namespace Gems\Pseudonymise\Log;

interface PseudonymiserLoggerInterface
{
    public function log(string $message): void;
}