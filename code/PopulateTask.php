<?php

namespace DNADesign\Populate;

use Exception;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class PopulateTask extends BuildTask
{
    protected static string $commandName = 'PopulateTask';

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        Populate::requireRecords();

        return Command::SUCCESS;
    }
}
