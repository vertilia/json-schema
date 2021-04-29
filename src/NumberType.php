<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class NumberType extends BaseType
{
    use IsValidMultipleOfTrait;
    use IsValidRangeTrait;

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_float($context) and !is_int($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be a number at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }

        $result = parent::isValid($context);

        // verify multipleOf
        if (isset($this->schema['multipleOf'])
            and !$this->isValidMultipleOf($context)
        ) {
            $result = false;
        }

        // verify range
        if ((isset($this->schema['minimum'])
            or isset($this->schema['exclusiveMinimum'])
            or isset($this->schema['maximum'])
            or isset($this->schema['exclusiveMaximum'])
            )
            and !$this->isValidRange($context)
        ) {
            $result = false;
        }

        return $result ? parent::isValid($context) : false;
    }
}
