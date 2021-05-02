<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class JsonSchema
{
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

    /** @var array decoded JSON schema as array */
    protected $schema;

    /** @var string */
    protected $id;

    /** @var int */
    protected $draft_version;

    /** @var array assoc array of $ref subschemas */
    protected $refs = [];

    /** @var array assoc array of external schemas */
    protected $external_schemas = [];

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

        // set draft_version
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

        // set id
        $id_param = $this->draft_version <= 4 ? 'id' : '$id';
        if (isset($this->schema[$id_param]) and is_string($this->schema[$id_param])) {
            $this->id = $this->schema[$id_param];
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
     * @param mixed &$schema
     * @param string $url
     * @return mixed
     */
    protected function externaliseRefs(&$schema, string $url)
    {
        if (is_array($schema)) {
            foreach ($schema as $key => &$value) {
                if ('$ref' === $key) {
                    list($external, $fragment) = explode('#', $value);
                    if (isset($fragment)) {
                        if (!strlen($external)) {
                            $value = "$url#$fragment";
                        } elseif (null === parse_url($external, PHP_URL_HOST)) {
                            $value = sprintf('%s/%s#%s', dirname($url), $external, $fragment);
                        }
                    } else {
                        if (null === parse_url($external, PHP_URL_HOST)) {
                            $value = sprintf('%s/%s', dirname($url), $external);
                        }
                    }
                } else {
                    $this->externaliseRefs($value, $url);
                }
            }
        }
    }

    /**
     * @param string $url
     * @return mixed
     */
    protected function loadExternalSchema(string $url)
    {
        if (!isset($this->external_schemas[$url])) {
            // load from file
            $json = file_get_contents($url);
            if (false === $json) {
                $this->errors[] = sprintf('error reading file: %s', $url);
                return false;
            }
            // decode file contents as json structure into array
            $schema = json_decode($json, true);
            if (null === $schema) {
                $this->errors[] = sprintf('error decoding json: %s', BaseType::contextStr($json, 64));
                return false;
            }
            // find all references in the schema and replace local references by external ones
            $this->externaliseRefs($schema, $url);

            $this->external_schemas[$url] = $schema;
        }

        return $this->external_schemas[$url];
    }

    /**
     * @param string $ref
     * @return mixed
     */
    protected function getSchemaFromRef(string $ref)
    {
        if (isset($this->refs[$ref])) {
            return $this->refs[$ref];
        }

        $path_struct = parse_url($ref);

        // load schema from local path or external resource
        if (isset($path_struct['path'])) {
            list($url) = explode('#', $ref);
            $target_schema = $this->loadExternalSchema($url);
            if (false === $target_schema) {
                return false;
            }
        } else {
            $target_schema = $this->schema;
        }

        if (isset($path_struct['fragment'])) {
            foreach (explode('/', ltrim($path_struct['fragment'], '/')) as $part) {
                if (is_array($target_schema) and array_key_exists($part, $target_schema)) {
                    $target_schema = $target_schema[$part];
                } else {
                    return false;
                }
            }
        }

        return $target_schema;
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

        if (isset($schema['$ref']) and is_string($schema['$ref'])) {
            return $this->isValidContext($this->getSchemaFromRef($schema['$ref']), $context, $label);
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
