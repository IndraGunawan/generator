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
use Symfony\Component\Finder\Finder;

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
        $name = trim(strtr($input->getArgument('name'), DIRECTORY_SEPARATOR, '\\'), '\\');
        $backupExisting = !$input->getOption('no-backup');
        $doctrineEntityStyle = $input->getOption('doctrine-entity-style');

        $generator = new GetSetGenerator();
        $generator
            ->setBackupExisting($backupExisting)
            ->setDoctrineEntityStyle($doctrineEntityStyle);

        foreach ($this->getClasses($name) as $class) {
            $ref = new \ReflectionClass($class);

            $output->writeln(sprintf('Generating getter/setter for namespace "<info>%s</info>"', $class));

            if ($backupExisting) {
                $basename = substr($ref->getFileName(), strlen(getcwd()));
                $output->writeln(sprintf('  > backing up <comment>%s</comment> to <comment>%s~</comment>', $basename, $basename));
            }

            $output->writeln(sprintf('  > generating <comment>%s</comment>', $class));

            $generator->generate($ref);
        }
    }

    private function getClassLoader()
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (!is_array($autoloader)) {
                continue;
            }

            $classLoader = $autoloader[0] ?? null;
            if ($classLoader instanceof \Symfony\Component\Debug\DebugClassLoader) {
                $classLoader = $classLoader->getClassLoader()[0];
            }

            if ($classLoader instanceof \Composer\Autoload\ClassLoader) {
                return $classLoader;
            }
        }

        return null;
    }

    private function getClasses(string $name)
    {
        if (class_exists($name)) {
            return [$name];
        }

        $classLoader = $this->getClassLoader();
        if (null === $classLoader) {
            return [];
        }

        return $this->findFile($classLoader->getPrefixesPsr4(), $name, $name);
    }

    private function findFile(array $prefixDirsPsr4, string $namespace, string $parentNamespace)
    {
        if (isset($prefixDirsPsr4[$parentNamespace.'\\'])) {
            $subPath = strtr(substr($namespace, strlen($parentNamespace)), '\\', DIRECTORY_SEPARATOR);
            $finder = new Finder();
            $files = [];
            foreach ($prefixDirsPsr4[$parentNamespace.'\\'] as $dir) {
                if (is_dir($dir.$subPath)) {
                    $finder->files()->in($dir.$subPath)->name('*.php');
                    foreach ($finder as $file) {
                        $files[] = strtr($namespace.DIRECTORY_SEPARATOR.substr($file->getRelativePathname(), 0, -4), DIRECTORY_SEPARATOR, '\\');
                    }
                }
            }

            return $files;
        }
        while (false !== $lastPos = strrpos($parentNamespace, '\\')) {
            return $this->findFile($prefixDirsPsr4, $namespace, substr($parentNamespace, 0, $lastPos));
        }

        return [];
    }
}
