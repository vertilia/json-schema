<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class BaseType implements IsValidInterface
{
    /** @var JsonSchema */
    protected $json_schema;

    /** @var string[] */
    protected $errors = [];

    /** @var mixed */
    protected $schema;

    /** @var string */
    protected $label;

    /**
     * @param mixed $schema
     * @param string|null $label (don't generate error message if null)
     * @param JsonSchema $json_schema
     */
    public function __construct($schema, ?string $label, JsonSchema $json_schema)
    {
        $this->schema = $schema;
        $this->label = $label;
        $this->json_schema = $json_schema;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string $context
     * @param int $length
     * @return string
     */
    public static function contextStr($context, $length): string
    {
        // compact consequent whitespaces
        $compact = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // split in 2 chunks in UTF-8 mode
        if (preg_match(sprintf('/^\s?(.{,%u})(.+)$/u', $length), $compact, $matches)) {
            return sprintf('%s...', rtrim($matches[1]));
        } else {
            return $compact;
        }
    }

    /**
     * whether array is a vector with natural ordered 0-based keys
     * @param array $a
     * @return bool false for empty array
     */
    protected static function arrayIsVector(array $a): bool
    {
        end($a);
        $k1 = key($a);
        reset($a);
        $k0 = key($a);
        return (0 === $k0 and count($a) - 1 === $k1);
    }

    /**
     * @param string|null $label
     * @param string $property
     * @return string|null
     */
    protected static function labelProperty(?string $label, string $property): ?string
    {
        return isset($label)
            ? ($label === '#/' ? "#/$property" : "$label/$property")
            : null;
    }

    /**
     * @param string|null $label
     * @param int $index
     * @return string|null
     */
    protected static function labelIndex(?string $label, int $index): ?string
    {
        return isset($label) ? "{$label}[$index]" : null;
    }

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        $result = true;

        // verify enum
        if (isset($this->schema['enum'])
            and is_array($this->schema['enum'])
            and !$this->isValidEnum($context)
        ) {
            $result = false;
        }

        // D6: verify const
        if (isset($this->schema['const'])
            and $this->json_schema->getVersion() >= 6
            and !$this->isValidConst($context)
        ) {
            $result = false;
        }

        // verify anyOf
        if (isset($this->schema['anyOf'])
            and is_array($this->schema['anyOf'])
            and !$this->isValidAnyOf($context)
        ) {
            $result = false;
        }

        // verify allOf
        if (isset($this->schema['allOf'])
            and is_array($this->schema['allOf'])
            and !$this->isValidAllOf($context)
        ) {
            $result = false;
        }

        // verify oneOf
        if (isset($this->schema['oneOf'])
            and is_array($this->schema['oneOf'])
            and !$this->isValidOneOf($context)
        ) {
            $result = false;
        }

        // verify not
        if (isset($this->schema['not'])
            and !$this->isValidNot($context)
        ) {
            $result = false;
        }

        // verify if
        if (isset($this->schema['if'])
            and $this->json_schema->getVersion() >= 7
        ) {
            if (!isset($this->schema['then'])) {
                $this->schema['then'] = true;
            }
            if (!isset($this->schema['else'])) {
                $this->schema['else'] = true;
            }
            if (!$this->isValidIf($context)) {
                $result = false;
            }
        }

        return $result;
    }

    protected function isValidEnum($context): bool
    {
        $result = false;

        foreach ($this->schema['enum'] as $value) {
            if ($value === $context) {
                $result = true;
            }
        }

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s is not one of a list at context path: %s',
                $this->contextStr($context, 20),
                $this->label
            );
        }

        return $result;
    }

    protected function isValidConst($context): bool
    {
        if ($context !== $this->schema['const']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s is not a defined constant at context path: %s',
                    $this->contextStr($context, 20),
                    $this->label
                );
            }
            return false;
        }

        return true;
    }

    protected function isValidAnyOf($context): bool
    {
        foreach ($this->schema['anyOf'] as $schema) {
            if ($this->json_schema->isValidContext($schema, $context, null)) {
                return true;
            }
        }

        if (isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s does not match any subschema at context path: %s',
                $this->contextStr($context, 20),
                $this->label
            );
        }

        return false;
    }

    protected function isValidAllOf($context): bool
    {
        foreach ($this->schema['allOf'] as $schema) {
            if (!$this->json_schema->isValidContext($schema, $context, null)) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'value %s does not match all subschemas at context path: %s',
                        $this->contextStr($context, 20),
                        $this->label
                    );
                }
                return false;
            }
        }

        return true;
    }

    protected function isValidOneOf($context): bool
    {
        $matched = false;

        foreach ($this->schema['oneOf'] as $schema) {
            if ($this->json_schema->isValidContext($schema, $context, null)) {
                if ($matched) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            'several matches found at context path: %s',
                            $this->label
                        );
                    }
                    return false;
                }
                $matched = true;
            }
        }

        if (!$matched) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'no match found at context path: %s',
                    $this->label
                );
            }
            return false;
        }

        return true;
    }

    protected function isValidNot($context): bool
    {
        if ($this->json_schema->isValidContext($this->schema['not'], $context, null)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s matched at context path: %s',
                    $this->contextStr($context, 20),
                    $this->label
                );
            }
            return false;
        }

        return true;
    }

    protected function isValidIf($context): bool
    {
        if ($this->json_schema->isValidContext($this->schema['if'], $context, null)) {
            return $this->json_schema->isValidContext($this->schema['then'], $context, $this->label);
        } else {
            return $this->json_schema->isValidContext($this->schema['else'], $context, $this->label);
        }
    }
}
