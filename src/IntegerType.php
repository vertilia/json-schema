<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class IntegerType extends BaseType
{
    use IsValidMultipleOfTrait;
    use IsValidRangeTrait;

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        $result = is_int($context);

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s must be an integer at context path: %s',
                $this->contextStr($context, 64),
                $this->label
            );
        }

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