<?php

namespace Blueprint\Generators\Statements;

use Blueprint\Contracts\Generator;
use Blueprint\Models\Statements\DispatchStatement;

class JobGenerator implements Generator
{
    /**
     * @var \Illuminate\Contracts\Filesystem\Filesystem
     */
    private $files;

    public function __construct($files)
    {
        $this->files = $files;
    }

    public function output(array $tree): array
    {
        $output = [];

        $stub = $this->files->get(STUBS_PATH . '/job.stub');

        /** @var \Blueprint\Controller $controller */
        foreach ($tree['controllers'] as $controller) {
            foreach ($controller->methods() as $method => $statements) {
                foreach ($statements as $statement) {
                    if (!$statement instanceof DispatchStatement) {
                        continue;
                    }

                    $path = $this->getPath($statement->job());

                    if ($this->files->exists($path)) {
                        continue;
                    }

                    $this->files->put(
                        $path,
                        $this->populateStub($stub, $statement)
                    );

                    $output['created'][] = $path;
                }
            }
        }
        return $output;
    }

    protected function getPath(string $name)
    {
        return 'app/Jobs/' . $name . '.php';
    }

    protected function populateStub(string $stub, DispatchStatement $dispatchStatement)
    {
        $stub = str_replace('DummyNamespace', 'App\\Jobs', $stub);
        $stub = str_replace('DummyClass', $dispatchStatement->job(), $stub);
        $stub = str_replace('// properties...', $this->buildConstructor($dispatchStatement), $stub);

        return $stub;
    }

    private function buildConstructor(DispatchStatement $dispatchStatement)
    {
        static $constructor = null;

        if (is_null($constructor)) {
            $constructor = str_replace('new instance', 'new job instance', $this->files->get(STUBS_PATH . '/partials/constructor.stub'));
        }

        if (empty($dispatchStatement->data())) {
            return trim($constructor);
        }

        $stub = $this->buildProperties($dispatchStatement->data()) . PHP_EOL . PHP_EOL;
        $stub .= str_replace('__construct()', '__construct(' . $this->buildParameters($dispatchStatement->data()) . ')', $constructor);
        $stub = str_replace('//', $this->buildAssignments($dispatchStatement->data()), $stub);

        return $stub;
    }

    private function buildProperties(array $data)
    {
        return trim(array_reduce($data, function ($output, $property) {
            $output .= '    public $' . $property . ';' . PHP_EOL . PHP_EOL;
            return $output;
        }, ''));
    }

    private function buildParameters(array $data)
    {
        $parameters = array_map(function ($parameter) {
            return '$' . $parameter;
        }, $data);

        return implode(', ', $parameters);
    }

    private function buildAssignments(array $data)
    {
        return trim(array_reduce($data, function ($output, $property) {
            $output .= '        $this->' . $property . ' = $' . $property . ';' . PHP_EOL;
            return $output;
        }, ''));
    }

}