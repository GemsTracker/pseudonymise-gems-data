<?php

namespace Gems\Pseudonymise\Log;

class NullLogger implements PseudonymiserLoggerInterface
{

    public function log(string $message): void
    {
    }
}