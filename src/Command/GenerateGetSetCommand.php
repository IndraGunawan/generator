<?php

/*
 * This file is part of the Indragunawan\Generator package.
 *
 * (c) Indra Gunawan <hello@indra.my.id>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Indragunawan\Generator\Command;

use Indragunawan\Generator\Generator\GetSetGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateGetSetCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('getset')
            ->setDescription('Generate Setter / Getter of PHP files.')
            ->addArgument('name', InputArgument::REQUIRED, 'Namespace or Path of PHP files.')
            ->addOption('php-version', null, InputOption::VALUE_REQUIRED, 'PHP Version, Implies the return type.', '7.1')
            ->addOption('no-backup', null, InputOption::VALUE_NONE, 'Do not backup existing files.')
            ->addOption('doctrine-entity-style', null, InputOption::VALUE_NONE, 'Doctrine entity style.')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = trim(strtr($input->getArgument('name'), '/', '\\'), '\\');
        $backupExisting = !$input->getOption('no-backup');
        $doctrineEntityStyle = $input->getOption('doctrine-entity-style');

        $generator = new GetSetGenerator();
        $generator
            ->setBackupExisting($backupExisting)
            ->setDoctrineEntityStyle($doctrineEntityStyle);

        $names = [$name];
        foreach ($names as $name) {
            $ref = new \ReflectionClass($name);

            $output->writeln(sprintf('Generating getter/setter for namespace "<info>%s</info>"', $name));

            if ($backupExisting) {
                $basename = $ref->getFileName();
                $output->writeln(sprintf('  > backing up <comment>%s</comment> to <comment>%s~</comment>', $basename, $basename));
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>', $name));

            $generator->generate($ref);
        }
    }

    private function getClassLoader()
    {
        foreach (spl_autoload_functions() as $autoloader) {
            $classLoader = $autoloader[0] ?? null;

            if ($classLoader instanceof \Composer\Autoload\ClassLoader) {
                return $classLoader;
            }
        }
    }
}
