<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

interface IsValidInterface
{
    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool;
}
