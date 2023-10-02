<?php

namespace Gems\Pseudonymise\Log;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ConsoleLogger implements PseudonymiserLoggerInterface
{
    public function __construct(
        protected readonly InputInterface $input,
        protected readonly OutputInterface $output,
    )
    {}

    public function log(string $message): void
    {
        $this->output->writeln($message);
    }
}