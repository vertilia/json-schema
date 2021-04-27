<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

trait IsValidMultipleOfTrait
{
    protected function isValidMultipleOf($context): bool
    {
        $result = (is_int($context) && is_int($this->schema['multipleOf']))
            ? ($context % $this->schema['multipleOf'])
            : fmod($context, $this->schema['multipleOf']);
        if ($result != 0) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value must be a multiple of %s, given: %s at context path %s',
                    $this->schema['multipleOf'],
                    $context,
                    $this->label
                );
            }
            return false;
        }

        return true;
    }
}
