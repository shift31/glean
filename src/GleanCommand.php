<?php namespace Shift31\Glean;

use Illuminate\Console\Command;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class GleanCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'glean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a fixture from the output of any object method';


    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $className = $this->argument('className');
        $methodName = $this->argument('methodName');
        $methodArguments = $this->argument('methodArguments');

        $name = $this->option('name');

        if ($name === '' or $name === null) {
            throw new \InvalidArgumentException("The 'name' option must have a value");
        }

        $path = $this->option('path');

        if (!is_dir($path)) {
            mkdir($path);
        }

        $format = $this->option('format');

        $data = $this->getMethodOutput($className, $methodName, $methodArguments);
        $content = $this->serializeData($data, $format);

        $this->saveFixture($path, $name, $format, $content);
    }


    /**
     * @param string $className
     * @param string $methodName
     * @param array $methodArguments
     *
     * @return mixed
     */
    protected function getMethodOutput($className, $methodName, array $methodArguments)
    {
        $object = $this->laravel->make($className);
        $reflectionMethod = new \ReflectionMethod($className, $methodName);

        if ($reflectionMethod->isStatic()) {
            $object = null;
        }

        $this->correctScalarTypes($methodArguments);

        $data = $reflectionMethod->invokeArgs($object, $methodArguments);

        return $data;
    }


    /**
     * @param array $methodArguments
     */
    protected function correctScalarTypes(array &$methodArguments)
    {
        array_walk(
            $methodArguments,
            function (&$argument) {
                switch ($argument) {
                    case 'null':
                        $argument = null;
                        break;
                    case 'true':
                        $argument = true;
                        break;
                    case 'false':
                        $argument = false;
                        break;
                }
            }
        );
    }


    /**
     * @param mixed $data
     * @param string $format
     *
     * @return mixed
     */
    protected function serializeData($data, $format)
    {
        if ($format === 'php') {
            $content = '<?php' . PHP_EOL . PHP_EOL. 'return ' . var_export($data, true) . ';';
        } else {
            $serializer = SerializerBuilder::create()->build();
            $content = $serializer->serialize($data, $format);
        }

        return $content;
    }


    /**
     * @param string $path
     * @param string $name
     * @param string $format
     * @param mixed $content
     */
    protected function saveFixture($path, $name, $format, $content)
    {
        $fileName = $path . DIRECTORY_SEPARATOR . "{$name}.{$format}";
        file_put_contents($fileName, $content);
        $this->info("Saved $fileName");
    }


    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['className', InputArgument::REQUIRED, 'The fully-qualified class/interface name.'],
            ['methodName', InputArgument::REQUIRED, 'The name of the method.'],
            ['methodArguments', InputArgument::IS_ARRAY, 'Method argument values.']
        ];
    }


    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['name', 'a', InputOption::VALUE_REQUIRED, 'The filename (without extension) of the fixture.'],
            ['path', 'p', InputOption::VALUE_OPTIONAL, 'The path to save the fixture.', storage_path('fixtures')],
            ['format', 'f', InputOption::VALUE_OPTIONAL, 'The fixture format (php, yml, json, xml).', 'php'],
        ];
    }
}
