<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class BooleanType extends BaseType
{
    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_bool($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be boolean at context path: %s',
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
