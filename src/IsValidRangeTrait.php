<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

trait IsValidRangeTrait
{
    protected function isValidRange($context): bool
    {
        return $this->json_schema->getVersion() >= 6
            ? $this->isValidRangeV6($context)
            : $this->isValidRangeV4($context);
    }

    protected function isValidRangeV6($context): bool
    {
        $result = true;
        if (isset($this->schema['minimum']) and $context < $this->schema['minimum']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s is less than minimum of %s at context path %s',
                    $context,
                    $this->schema['minimum'],
                    $this->label
                );
            }
            $result = false;
        }
        if (isset($this->schema['exclusiveMinimum']) and $context <= $this->schema['exclusiveMinimum']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s is less than or equal to exclusive minimum of %s at context path %s',
                    $context,
                    $this->schema['exclusiveMinimum'],
                    $this->label
                );
            }
            $result = false;
        }
        if (isset($this->schema['maximum']) and $context > $this->schema['maximum']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s is greater than maximum of %s at context path %s',
                    $context,
                    $this->schema['maximum'],
                    $this->label
                );
            }
            $result = false;
        }
        if (isset($this->schema['exclusiveMaximum']) and $context >= $this->schema['exclusiveMaximum']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s is greater than or equal to exclusive maximum of %s at context path %s',
                    $context,
                    $this->schema['exclusiveMaximum'],
                    $this->label
                );
            }
            $result = false;
        }

        return $result;
    }

    protected function isValidRangeV4($context): bool
    {
        $result = true;
        if (isset($this->schema['minimum'])) {
            if ($context < $this->schema['minimum']) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'value %s is less than minimum of %s at context path %s',
                        $context,
                        $this->schema['minimum'],
                        $this->label
                    );
                }
                $result = false;
            } elseif (!empty($this->schema['exclusiveMinimum']) and $context <= $this->schema['minimum']) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'value %s is less than or equal to exclusive minimum of %s at context path %s',
                        $context,
                        $this->schema['minimum'],
                        $this->label
                    );
                }
                $result = false;
            }
        }
        if (isset($this->schema['maximum'])) {
            if ($context > $this->schema['maximum']) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'value %s is greater than maximum of %s at context path %s',
                        $context,
                        $this->schema['maximum'],
                        $this->label
                    );
                }
                $result = false;
            } elseif (!empty($this->schema['exclusiveMaximum']) and $context >= $this->schema['maximum']) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'value %s is greater than or equal to exclusive maximum of %s at context path %s',
                        $context,
                        $this->schema['exclusiveMaximum'],
                        $this->label
                    );
                }
                $result = false;
            }
        }

        return $result;
    }
}
