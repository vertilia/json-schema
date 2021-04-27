<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class ArrayType extends BaseType
{
    /** @var JsonSchema */
    protected $json_schema;

    /** @var mixed */
    protected $additional_items;

    public function setSchema(JsonSchema $json_schema): self
    {
        $this->json_schema = $json_schema;
        return $this;
    }

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        $result = is_array($context);

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s must be an array at context path: %s',
                $this->contextStr($context, 64),
                $this->label
            );
        }

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
        $result = false;
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
}
