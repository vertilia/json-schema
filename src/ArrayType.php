<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class ArrayType extends BaseType
{
    /** @var mixed */
    protected $additional_items;

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_array($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be an array at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }

        $result = parent::isValid($context);

        // verify items
        if (isset($this->schema['items']) and is_array($this->schema['items'])) {
            if ($this->arrayIsVector($this->schema['items'])) {
                if (isset($this->schema['additionalItems'])) {
                    $this->additional_items = $this->schema['additionalItems'];
                }
                if (!$this->isValidItemsVector($context)) {
                    $result = false;
                }
            } elseif (!$this->isValidItemsSchema($context)) {
                $result = false;
            }
        }

        // D6: verify contains
        if (isset($this->schema['contains'])
            and $this->draft_version >= 6
            and !$this->isValidContains($context)
        ) {
            $result = false;
        }

        // verify lengths
        if ((isset($this->schema['minItems']) or isset($this->schema['maxItems']))
            and !$this->isValidLength($context)
        ) {
            $result = false;
        }

        // verify uniqueness
        if (isset($this->schema['uniqueItems'])
            and !$this->isValidUniqueness($context)
        ) {
            $result = false;
        }

        return $result;
    }

    protected function isValidItemsSchema($context): bool
    {
        $result = true;
        $schema = $this->schema['items'];

        foreach ($context as $index => $item) {
            $item_valid = $this->json_schema->isValidContext(
                $schema,
                $item,
                $this->label == '#/' ? "#/[$index]" : "$this->label[$index]"
            );
            if (!$item_valid) {
                $result = false;
            }
        }

        return $result;
    }

    protected function isValidContains($context): bool
    {
        $schema = $this->schema['contains'];

        foreach ($context as $index => $item) {
            $item_valid = $this->json_schema->isValidContext(
                $schema,
                $item,
                null
            );
            if ($item_valid) {
                return true;
            }
        }

        if (isset($this->label)) {
            $this->errors[] = sprintf(
                'array does not contain required item at context path: %s',
                $this->label
            );
        }

        return false;
    }

    protected function isValidItemsVector($context): bool
    {
        if (!$this->arrayIsVector($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'context must be array at context path: %s',
                    $this->label
                );
            }
            return false;
        }

        $result = true;

        foreach ($context as $index => $value) {
            if (array_key_exists($index, $this->schema['items'])) {
                $value_valid = $this->json_schema->isValidContext(
                    $this->schema['items'][$index],
                    $value,
                    $this->label == '#/' ? "#/[$index]" : "$this->label[$index]"
                );
                if (!$value_valid) {
                    $result = false;
                }
            } elseif (false === $this->additional_items) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'additional items forbidden at context path: %s',
                        $this->label == '#/' ? "#/[$index]" : "$this->label[$index]"
                    );
                }
                $result = false;
                break;
            } elseif (isset($this->additional_items)) {
                $value_valid = $this->json_schema->isValidContext(
                    $this->additional_items,
                    $value,
                    $this->label == '#/' ? "#/[$index]" : "$this->label[$index]"
                );
                if (!$value_valid) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    protected function isValidLength($context): bool
    {
        $result = true;
        $count = count($context);

        if (isset($this->schema['minItems'])
            and is_numeric($this->schema['minItems'])
            and $this->schema['minItems'] >= 0
            and $count < $this->schema['minItems']
        ) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'array has %u items, must contain at least %u items at context path: %s',
                    $count,
                    $this->schema['minItems'],
                    $this->label
                );
            }
            $result = false;
        }

        if (isset($this->schema['maxItems'])
            and is_numeric($this->schema['maxItems'])
            and $this->schema['maxItems'] >= 0
            and $count > $this->schema['maxItems']
        ) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'array has %u items, must contain maximum %u items at context path: %s',
                    $count,
                    $this->schema['maxItems'],
                    $this->label
                );
            }
            $result = false;
        }

        return $result;
    }

    protected function isValidUniqueness($context): bool
    {
        $result = count($context) == count(array_unique($context, SORT_REGULAR));

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'array contains non-unique items at context path: %s',
                $this->label
            );
        }

        return $result;
    }
}
