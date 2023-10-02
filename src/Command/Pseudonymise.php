<?php

namespace Gems\Pseudonymise\Command;

use Doctrine\DBAL\Connection;
use Gems\Pseudonymise\Log\ConsoleLogger;
use Gems\Pseudonymise\Pseudonymiser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pseudonymise:respondent')]
class Pseudonymise extends Command
{

    public function __construct(
        protected readonly Connection $connection,
        protected readonly array $config,
    )
    {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger = new ConsoleLogger($input, $output);
        $pseudonymiser = new Pseudonymiser($this->connection, $this->config, $logger);

        if (!isset($this->config['pseudonymise']['fields'])) {
            $output->writeln('No field settings found in config');
            return Command::FAILURE;
        }

        $pseudonymiser->process($this->config['pseudonymise']['fields']);

        return Command::SUCCESS;
    }
}