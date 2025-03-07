<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Commands;

use Elabftw\Elabftw\Sql;
use Elabftw\Exceptions\ImproperActionException;
use League\Flysystem\Filesystem as Fs;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Use this to revert a specific schema
 */
class RevertSchema extends Command
{
    protected static $defaultName = 'db:revert';

    protected function configure(): void
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Allow reverting a specific schema upgrade.')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp("Use this command to revert a specific schema. Example to revert schema 116: 'db:revert 116'.")
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Ignore errors during execution')
            ->addArgument('number', InputArgument::REQUIRED, 'Schema number to revert');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetSchema = $input->getArgument('number');
        if (!is_string($targetSchema)) {
            throw new ImproperActionException('Incorrect schema number provided.');
        }
        $Sql = new Sql(new Fs(new LocalFilesystemAdapter(dirname(__DIR__) . '/sql')), $output);
        $Sql->execFile(sprintf('schema%d-down.sql', (int) $targetSchema), $input->getOption('force'));
        return 0;
    }
}
