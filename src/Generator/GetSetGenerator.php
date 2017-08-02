<?php

/*
 * This file is part of the Indragunawan\Generator package.
 *
 * (c) Indra Gunawan <hello@indra.my.id>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Indragunawan\Generator\Generator;

use Doctrine\Common\Inflector\Inflector;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\Types;
use ReflectionClass;

class GetSetGenerator
{
    /**
     * @var bool
     */
    private $backupExisting = true;

    /**
     * Number of spaces to use for indention in generated code.
     *
     * @var int
     */
    protected $numSpaces = 4;

    /**
     * The actual spaces to use for indention.
     *
     * @var string
     */
    protected $spaces = '    ';

    /**
     * Doctrine style use 'add' and 'remove' for array, and 'ArrayCollection' at constructor.
     *
     * @var bool
     */
    protected $doctrineEntityStyle = false;

    public function setBackupExisting(bool $backupExisting): self
    {
        $this->backupExisting = $backupExisting;

        return $this;
    }

    public function setDoctrineEntityStyle(bool $doctrineEntityStyle): self
    {
        $this->doctrineEntityStyle = $doctrineEntityStyle;

        return $this;
    }

    public function generate(ReflectionClass $refClass)
    {
        $path = $refClass->getFileName();
        // backup
        if ($this->backupExisting && file_exists($path)) {
            $backupPath = $path.'~';
            if (!copy($path, $backupPath)) {
                throw new \RuntimeException('Attempt to backup overwritten file but copy operation failed.');
            }
        }

        file_put_contents($path, $this->generateUpdatedClass($refClass));
        chmod($path, 0664);
    }

    public function generateUpdatedClass(ReflectionClass $refClass)
    {
        $currentCode = file_get_contents($refClass->getFileName());

        $body = $this->generateBody($refClass);
        $body = str_replace('<spaces>', $this->spaces, $body);
        $last = strrpos($currentCode, '}');

        return substr($currentCode, 0, $last).$body.(strlen($body) > 0 ? "\n" : '')."}\n";
    }

    protected function generateBody(ReflectionClass $refClass)
    {
        $code = [];

        $factory = DocBlockFactory::createInstance();
        $contextFactory = new Types\ContextFactory();

        $properties = [];
        foreach ($refClass->getProperties() as $property) {
            try {
                $docBlock = $factory->create($property, $contextFactory->createFromReflector($refClass));

                $types = [];
                foreach ($docBlock->getTagsByName('var') as $var) {
                    if ($var->getType() instanceof Types\Compound) {
                        foreach ($var->getType() as $type) {
                            $types[] = $type;
                        }
                    } else {
                        if ($var->getType() instanceof Types\Array_) {
                            if ($var->getType()->getValueType() instanceof Types\Object_ && !class_exists($var->getType()->getValueType())) {
                                throw new \RuntimeException(sprintf('Class "%s" not found', $var->getType()->getValueType()));
                            }
                        } elseif ($var->getType() instanceof Types\Object_) {
                            if (!class_exists((string) $var->getType())) {
                                throw new \RuntimeException(sprintf('Class "%s" not found', (string) $var->getType()));
                            }
                        } else {
                            if (0 === strpos((string) $var->getType(), '?')) {
                                $type = substr((string) $var->getType(), 1);
                            } else {
                                $type = (string) $var->getType();
                            }

                            if (!in_array($type, ['string', 'int', 'float', 'bool'], true)) {
                                throw new \RuntimeException(sprintf('Type "%s" not found', (string) $var->getType()));
                            }
                        }

                        $types[] = $var->getType();
                    }
                }
                $types = array_unique($types);
                if (count($types) > 1) {
                    throw new \RuntimeException(sprintf('Multi types at "%s" property does not support.', $property->getName()));
                }

                $properties[$property->getName()] = $types[0];
            } catch (\InvalidArgumentException $e) {
                $properties[$property->getName()] = null;
            }
        }

        $code[] = $this->generateConstructor($refClass, $properties);
        $code[] = $this->generateStubMethods($refClass, $properties);

        return implode("\n", $code);
    }

    protected function generateStubMethods(ReflectionClass $refClass, array $properties)
    {
        $methods = [];

        foreach ($properties as $property => $varType) {
            if ($varType instanceof Types\Array_) {
                if ($this->doctrineEntityStyle) {
                    foreach (['add', 'remove', 'get'] as $type) {
                        if ($code = $this->generateEntityStubMethod($refClass, $type, $property, $varType->getValueType(), true)) {
                            $methods[] = $code;
                        }
                    }
                } else {
                    foreach (['set', 'get'] as $type) {
                        if ($code = $this->generateEntityStubMethod($refClass, $type, $property, $varType, true)) {
                            $methods[] = $code;
                        }
                    }
                }
            } else {
                foreach (['set', 'get'] as $type) {
                    if ($code = $this->generateEntityStubMethod($refClass, $type, $property, $varType)) {
                        $methods[] = $code;
                    }
                }
            }
        }

        return implode("\n\n", $methods);
    }

    protected function generateEntityStubMethod(ReflectionClass $refClass, string $type, string $fieldName, ?Type $varType, bool $isTypeArray = false)
    {
        $methodName = $type.Inflector::classify($fieldName);
        $variableName = Inflector::camelize($fieldName);
        if (in_array($type, ['add', 'remove'], true)) {
            $methodName = Inflector::singularize($methodName);
            $variableName = Inflector::singularize($variableName);
        }

        if ($this->hasMethod($methodName, $refClass)) {
            return '';
        }

        $varTypeHint = (string) $varType;

        $variableType = $varTypeHint ?: 'mixed';

        if ('get' === $type) {
            $returnTypeHint = $isTypeArray ? 'array' : $varTypeHint;
        } else {
            $returnTypeHint = 'self';
        }
        $returnTypeHint = $returnTypeHint ? ' : '.$returnTypeHint : '';

        if ($isTypeArray && $this->doctrineEntityStyle && $varType instanceof Types\Array_) {
            $methodTypeHint = (string) $varType->getValueType();
        } elseif ($isTypeArray && $this->doctrineEntityStyle && !$varType instanceof Types\Mixed_) {
            $methodTypeHint = $varTypeHint;
        } elseif ($isTypeArray) {
            if ($varType instanceof Types\Mixed_) {
                $methodTypeHint = '';
            } elseif ($varType instanceof Types\Array_) {
                $methodTypeHint = 'array';
            } else {
                $methodTypeHint = $varTypeHint;
            }
        } else {
            $methodTypeHint = $varTypeHint;
        }
        $methodTypeHint = $methodTypeHint ? $methodTypeHint.' ' : '';

        $replacements = [
          '<description>' => ucfirst($type).' '.$variableName,
          '<methodTypeHint>' => $methodTypeHint,
          '<variableType>' => $variableType,
          '<variableName>' => $variableName,
          '<methodName>' => $methodName,
          '<fieldName>' => $fieldName,
          '<variableDefault>' => '',
          '<entity>' => $refClass->getName(),
          '<returnTypeHint>' => $returnTypeHint,
        ];

        $method = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $this->getStubMethod($type)
        );

        return $this->prefixCodeWithSpaces($method);
    }

    protected function generateConstructor(ReflectionClass $refClass, array $properties)
    {
        if ($this->hasMethod('__construct', $refClass)) {
            return '';
        }

        $collections = [];

        foreach ($properties as $property => $varType) {
            if ($varType instanceof Types\Array_) {
                if ($this->doctrineEntityStyle) {
                    $collections[] = '$this->'.$property.' = new \Doctrine\Common\Collections\ArrayCollection();';
                } else {
                    $collections[] = '$this->'.$property.' = []';
                }
            }
        }

        if ($collections) {
            return $this->prefixCodeWithSpaces(str_replace('<collections>', implode("\n".$this->spaces, $collections), $this->getStubMethod('constructor')));
        }

        return '';
    }

    protected function hasMethod(string $method, ReflectionClass $refClass)
    {
        if ($refClass->hasMethod($method)) {
            return true;
        }

        // check traits for existing method
        foreach ($this->getTraits($refClass) as $trait) {
            if ($trait->hasMethod($method)) {
                return true;
            }
        }

        return false;
    }

    protected function getTraits(ReflectionClass $refClass)
    {
        $traits = [];

        while ($refClass !== false) {
            $traits = array_merge($traits, $refClass->getTraits());

            $refClass = $refClass->getParentClass();
        }

        return $traits;
    }

    private function getStubMethod(string $type): string
    {
        $path = sprintf('%s/stubs/%sMethod.stub', __DIR__, $type);
        if (!file_exists($path)) {
            throw new \RuntimeException(sprintf('File %s not found.', $path));
        }

        return file_get_contents($path);
    }

    protected function prefixCodeWithSpaces(string $code, int $num = 1)
    {
        $lines = explode("\n", $code);

        foreach ($lines as $key => $value) {
            if (!empty($value)) {
                $lines[$key] = str_repeat($this->spaces, $num).$lines[$key];
            }
        }

        return implode("\n", $lines);
    }
}
