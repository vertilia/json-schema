<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class BaseType implements IsValidInterface
{
    /** @var string[] */
    protected $errors = [];

    /** @var array */
    protected $schema;

    /** @var string */
    protected $label;

    /** @var int */
    protected $draft_version;

    /**
     * @param array $schema
     * @param string|null $label (don't generate error message if null)
     * @param int $draft_version
     */
    public function __construct($schema, ?string $label, int $draft_version = null)
    {
        $this->schema = $schema;
        $this->label = $label;
        $this->draft_version = $draft_version;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string $context
     * @param int $length
     * @return string
     */
    public static function contextStr($context, $length): string
    {
        // compact consequent whitespaces
        $compact = preg_replace('/\s+/', ' ', var_export($context, true));

        // split in 2 chunks in UTF-8 mode
        if (preg_match(sprintf('/^\s?(.{,%u})(.+)$/u', $length), $compact, $matches)) {
            return sprintf('%s...', rtrim($matches[1]));
        } else {
            return $compact;
        }
    }

    /**
     * whether array is a vector with natural ordered 0-based keys
     * @param array $a
     * @return bool false for empty array
     */
    protected static function arrayIsVector(array $a): bool
    {
        end($a);
        $k1 = key($a);
        reset($a);
        $k0 = key($a);
        return (0 === $k0 and count($a) - 1 === $k1);
    }

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        $result = true;

        // verify enum
        if (isset($this->schema['enum'])
            and is_array($this->schema['enum'])
            and !$this->isValidEnum($context)
        ) {
            $result = false;
        }

        // D6: verify const
        if (isset($this->schema['const'])
            and $this->draft_version > 4
            and !$this->isValidConst($context)
        ) {
            $result = false;
        }

        return $result;
    }

    protected function isValidEnum($context): bool
    {
        $result = false;

        foreach ($this->schema['enum'] as $value) {
            if ($value === $context) {
                $result = true;
            }
        }

        if (!$result and isset($this->label)) {
            $this->errors[] = sprintf(
                'value %s is not one of a list at context path: %s',
                $this->contextStr($context, 20),
                $this->label
            );
        }

        return $result;
    }

    protected function isValidConst($context): bool
    {
        if ($context !== $this->schema['const']) {
            $this->errors[] = sprintf(
                'value %s is not a defined constant at context path: %s',
                $this->contextStr($context, 20),
                $this->label
            );
            return false;
        }

        return true;
    }
}
