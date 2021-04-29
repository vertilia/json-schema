<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class NullType extends BaseType
{
    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_null($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be null at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }

        $result = parent::isValid($context);

        return $result ? parent::isValid($context) : false;
    }
}
