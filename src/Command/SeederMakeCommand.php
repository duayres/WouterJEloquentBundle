<?php

namespace WouterJ\EloquentBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Seeder as IlluminateSeeder;
use WouterJ\EloquentBundle\Seeder;

class SeederMakeCommand extends ContainerAwareCommand
{
    private $stubPath;

    protected function configure()
    {
        $this->setName('eloquent:make:seeder')
            ->setDescription('Create a new seeder class')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'The target directory')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the seeder class')
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->stubPath = dirname((new \ReflectionClass(IlluminateSeeder::class))->getFileName()).'/Console/Seeds/stubs';
    }

    protected function execute(InputInterface $i, OutputInterface $o)
    {
        $name = $i->getArgument('name');
        $path = $i->getOption('target') ?: $this->getPath($name);

        if (file_exists($path)) {
            $o->writeln('<error>Seeder already exists!</>');

            return 1;
        }

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $this->buildClass($name));
    }

    private function buildClass($name)
    {
        $lastPos = strrpos($name, '\\');
        $namespace = $lastPos ? substr($name, 0, $lastPos) : false;
        $class = substr($name, $lastPos + 1);

        $stub = file_get_contents($this->stubPath.'/seeder.stub');
        $stub = str_replace('DummyClass', $class, $stub);
        $stub = str_replace(IlluminateSeeder::class, Seeder::class, $stub);

        if ($namespace) {
            $stub = str_replace('<?php', "<?php\n\nnamespace ".$namespace.';', $stub);
        }

        return $stub;
    }

    private function getPath($name)
    {
        $lastPos = strrpos($name, '\\');
        $namespace = str_replace('\\Seed', '', substr($name, 0, $lastPos));
        $fileName = 'Seed/'.substr($name, $lastPos + 1).'.php';

        // is it part of a bundle?
        foreach ($this->getContainer()->getParameter('kernel.bundles') as $bundle) {
            if (false !== strpos($bundle, $namespace)) {
                return dirname((new \ReflectionClass($bundle))->getFileName()).'/'.$fileName;
            }
        }

        // is it in the App namespace?
        if ('App\\' === substr($name, 0, 4)) {
            return $this->getContainer()->getParameter('kernel.root_dir').'/src/App/'.$fileName;
        }

        throw new \InvalidArgumentException('Cannot guess the seeder file name, please specify the --target option.');
    }
}
