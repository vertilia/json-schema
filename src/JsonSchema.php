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
        'http://json-schema.org/draft-07/schema#' => 7,
        'http://json-schema.org/draft-06/schema#' => 6,
        'http://json-schema.org/draft-04/schema#' => 4,
        'http://json-schema.org/schema#' => 4,
    ];

    const KEYWORDS_TO_TYPES = [
        // string
        'minLength' => 'string',
        'maxLength' => 'string',
        'pattern' => 'string',
        'format' => 'string',
        // number
        'multipleOf' => 'number',
        'minimum' => 'number',
        'exclusiveMinimum' => 'number',
        'maximum' => 'number',
        'exclusiveMaximum' => 'number',
        // object
        'properties' => 'object',
        'additionalProperties' => 'object',
        'required' => 'object',
        'propertyNames' => 'object',
        'minProperties' => 'object',
        'maxProperties' => 'object',
        'dependencies' => 'object',
        'patternProperties' => 'object',
        // array
        'items' => 'array',
        'additionalItems' => 'array',
        'contains' => 'array',
        'minItems' => 'array',
        'maxItems' => 'array',
        'uniqueItems' => 'array',
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
        // D6: boolean schema
        if (is_bool($schema) and $this->draft_version >= 6) {
            if (!$schema and isset($label)) {
                $this->errors[] = sprintf(
                    'schema never matches at context path: %s',
                    $label
                );
            }
            return $schema;
        }

        if (!is_array($schema)) {
            if (isset($label)) {
                $this->errors[] = sprintf(
                    'schema is not an object at context path: %s',
                    $label
                );
            }
            return false;
        }

        if (empty($schema['type'])) {
            // define type by a first non-generic keyword
            $verif_array = array_intersect_key(self::KEYWORDS_TO_TYPES, $schema);
            if ($verif_array) {
                $schema['type'] = reset($verif_array);
            }
        }

        if (isset($schema['type'])) {
            if (is_string($schema['type'])) {
                switch ($schema['type']) {
                    case 'integer':
                        $node = new IntegerType($schema, $label, $this);
                        break;
                    case 'number':
                        $node = new NumberType($schema, $label, $this);
                        break;
                    case 'boolean':
                        $node = new BooleanType($schema, $label, $this);
                        break;
                    case 'null':
                        $node = new NullType($schema, $label, $this);
                        break;
                    case 'string':
                        $node = new StringType($schema, $label, $this);
                        break;
                    case 'object':
                        $node = new ObjectType($schema, $label, $this);
                        break;
                    case 'array':
                        $node = new ArrayType($schema, $label, $this);
                        break;
                    default:
                        $node = new BaseType($schema, $label, $this);
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
            $node = new BaseType($schema, $label, $this);
            $valid = $node->isValid($context);
            $errors = $node->getErrors();
            if ($errors) {
                $this->errors = array_merge($this->errors, $errors);
            }
        }

        return $valid;
    }
}
