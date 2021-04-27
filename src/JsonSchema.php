<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

/**
 * @property-read int $var
 */
class JsonSchema
{
    public $var;

    const DRAFT_LATEST = 'http://json-schema.org/draft/2019-09/schema#';

    const DRAFT_VERSIONS = [
        self::DRAFT_LATEST => 7,
        'http://json-schema.org/schema#' => 7,
        'http://json-schema.org/draft-07/schema#' => 7,
        'http://json-schema.org/draft-06/schema#' => 6,
        'http://json-schema.org/draft-04/schema#' => 4,
    ];

    const TYPES = [
        'null' => true,
        'boolean' => true,
        'numeric' => true,
        'integer' => true,
        'string' => true,
        'array' => true,
        'object' => true,
    ];

    /** @var string[] */
    protected $errors = [];

    /** @var array decoded JSON schema */
    protected $schema;

    /** @var int */
    protected $draft_version;

    /**
     * @param string|null $json
     */
    public function __construct(string $json = null)
    {
        if (isset($json)) {
            $this->setSchema($json);
        }
    }

    /**
     * @param string $json
     * @return self
     */
    public function setSchema(string $json)
    {
        $this->errors = [];
        $this->draft_version = null;
        $this->schema = json_decode($json, true);

        if (isset($this->schema['$schema']) and is_string($this->schema['$schema'])) {
            if (isset(self::DRAFT_VERSIONS[$this->schema['$schema']])) {
                $this->draft_version = self::DRAFT_VERSIONS[$this->schema['$schema']];
            } else {
                $this->errors[] = sprintf(
                    '$schema is unknown: %s',
                    BaseType::contextStr($this->schema['$schema'], 64)
                );
            }
        } else {
            $this->draft_version = self::DRAFT_VERSIONS[self::DRAFT_LATEST];
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getVersion(): int
    {
        return $this->draft_version;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string $json_value
     * @return bool
     */
    public function isValid(string $json_value): bool
    {
        return $this->isValidContext($this->schema, json_decode($json_value), '#/');
    }

    /**
     * @param mixed $schema decoded as array
     * @param mixed $context decoded as object
     * @param string|null $label (don't generate error message if null)
     * @return bool
     */
    public function isValidContext($schema, $context, ?string $label): bool
    {
        if ($schema === false) {
            return false;
        }

        if (isset($schema['type'])) {
            if (is_string($schema['type'])) {
                switch ($schema['type']) {
                    case 'integer':
                        $node = new IntegerType($schema, $label, $this->draft_version);
                        break;
                    case 'number':
                        $node = new NumberType($schema, $label, $this->draft_version);
                        break;
                    case 'boolean':
                        $node = new BooleanType($schema, $label, $this->draft_version);
                        break;
                    case 'null':
                        $node = new NullType($schema, $label, $this->draft_version);
                        break;
                    case 'string':
                        $node = new StringType($schema, $label, $this->draft_version);
                        break;
                    case 'object':
                        $node = (new ObjectType($schema, $label, $this->draft_version))->setSchema($this);
                        break;
                    case 'array':
                        $node = (new ArrayType($schema, $label, $this->draft_version))->setSchema($this);
                        break;
                    default:
                        $node = new BaseType($schema, $label, $this->draft_version);
                }
                $valid = $node->isValid($context);
                $errors = $node->getErrors();
                if ($errors) {
                    $this->errors = array_merge($this->errors, $errors);
                }
            } elseif (is_array($schema['type'])) {
                $schema2 = $schema;
                $valid = false;
                foreach ($schema['type'] as $type) {
                    if (is_string($type)) {
                        $schema2['type'] = $type;
                        $valid = $this->isValidContext($schema2, $context, $label);
                        if ($valid) {
                            break;
                        }
                    } elseif (isset($label)) {
                        $this->errors[] = sprintf(
                            'if type is array, all elements must be strings at context path: %s',
                            $label
                        );
                    }
                }
            } else {
                $valid = true;
            }
        } else {
            $node = new BaseType($schema, $label, $this->draft_version);
            $valid = $node->isValid($context);
            $errors = $node->getErrors();
            if ($errors) {
                $this->errors = array_merge($this->errors, $errors);
            }
        }

        return $valid;
    }
}
