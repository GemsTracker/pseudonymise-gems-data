<?php

namespace Gems\Pseudonymize;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'pseudonymize:respondent')]
class Pseudonymize extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Get fields that need to be changed

        // Get database rows (Stream?)
        // Generate new seed
        // Generate new data
        // Update row

        // Change other data


        return Command::SUCCESS;
    }
}