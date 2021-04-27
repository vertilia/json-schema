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
        $result = is_bool($context);

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s must be boolean at context path: %s',
                $this->contextStr($context, 64),
                $this->label
            );
        }

        return $result ? parent::isValid($context) : false;
    }
}
