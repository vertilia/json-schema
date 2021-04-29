<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class ObjectType extends BaseType
{
    /** @var array hashmap of all context properties */
    protected $context_properties = [];

    /** @var string[] array of defined context properties */
    protected $properties = [];

    /** @var string[] array of additional context properties */
    protected $additional_properties = [];

    /** @var array hashmap of pattern_property => schema */
    protected $pattern_schemas = [];

    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_object($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be an object at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }

        $result = parent::isValid($context);

        $this->context_properties = get_object_vars($context);
        $ctx_props = array_keys($this->context_properties);

        // verify properties
        if (isset($this->schema['properties'])
            and is_array($this->schema['properties'])
        ) {
            $defined_props = array_keys($this->schema['properties']);
            $this->properties = array_intersect($ctx_props, $defined_props);
            $this->additional_properties = array_diff($ctx_props, $defined_props);

            if (!$this->isValidProperties($context)) {
                $result = false;
            }
        } else {
            $this->properties = [];
            $this->additional_properties = $ctx_props;
        }

        // verify patternProperties
        if (isset($this->schema['patternProperties'])
            and is_array($this->schema['patternProperties'])
        ) {
            foreach ($this->schema['patternProperties'] as $pattern => $schema) {
                $pattern_escaped = sprintf('/%s/', addcslashes($pattern, '/'));
                $matched = preg_grep($pattern_escaped, $this->additional_properties);
                if ($matched) {
                    $this->pattern_schemas = array_merge(
                        $this->pattern_schemas,
                        array_fill_keys($matched, $schema)
                    );
                }
            }

            // extract pattern_schemas from additional_properties
            if ($this->pattern_schemas) {
                $this->additional_properties = array_diff(
                    $this->additional_properties,
                    array_keys($this->pattern_schemas)
                );
            }

            // validate pattern properties
            if (!$this->isValidPatternProperties($context)) {
                $result = false;
            }
        }

        // verify additionalProperties
        if (isset($this->schema['additionalProperties'])
            and !$this->isValidAdditionalProperties($context)
        ) {
            $result = false;
        }

        // verify required
        if (isset($this->schema['required'])
            and is_array($this->schema['required'])
        ) {
            if ($this->draft_version <= 4 and empty($this->schema['required'])) {
                if (isset($this->label)) {
                    $this->errors[] = sprintf(
                        'D4: "required" must contain at least one string at context path %s',
                        implode(', ', $missing),
                        $this->label
                    );
                }
                $result = false;
            } elseif (!$this->isValidRequired($context)) {
                $result = false;
            }
        }

        // verify propertyNames
        if (isset($this->schema['propertyNames'])
            and $this->draft_version >= 6
            and !$this->isValidPropertyNames($context)
        ) {
            $result = false;
        }

        // verify size
        if ((isset($this->schema['minProperties']) or isset($this->schema['maxProperties']))
            and !$this->isValidSize($context)
        ) {
            $result = false;
        }

        // verify dependencies
        if (isset($this->schema['dependencies'])
            and is_array($this->schema['dependencies'])
            and !$this->isValidDependencies($context)
        ) {
            $result = false;
        }

        return $result ? parent::isValid($context) : false;
    }

    protected function isValidProperties($context): bool
    {
        $result = true;

        foreach ($this->schema['properties'] as $property => $schema) {
            if (property_exists($context, $property)) {
                $prop_valid = $this->json_schema->isValidContext(
                    $schema,
                    $context->$property,
                    $this->label == '#/' ? "#/$property" : "$this->label/$property"
                );
                if (!$prop_valid) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    protected function isValidPatternProperties($context): bool
    {
        $result = true;

        foreach ($this->pattern_schemas as $property => $schema) {
            $prop_valid = $this->json_schema->isValidContext(
                $schema,
                $context->$property,
                $this->label == '#/' ? "#/$property" : "$this->label/$property"
            );
            if (!$prop_valid) {
                $result = false;
            }
        }

        return $result;
    }

    protected function isValidAdditionalProperties($context): bool
    {
        $result = true;

        if ($this->schema['additionalProperties'] === false) {
            return empty($this->additional_properties);
        } elseif (is_array($this->schema['additionalProperties'])) {
            $additional_schema = $this->schema['additionalProperties'];
            foreach ($this->additional_properties as $property) {
                $prop_valid = $this->json_schema->isValidContext(
                    $additional_schema,
                    $this->context_properties[$property],
                    $this->label == '#/' ? "#/$property" : "$this->label/$property"
                );
                if (!$prop_valid) {
                    $result = false;
                }
            }
        }

        return $result;
    }

    protected function isValidRequired($context): bool
    {
        $result = true;
        $missing = array_diff($this->schema['required'], $this->properties);

        if ($missing) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'missing properties: %s at context path %s',
                    implode(', ', $missing),
                    $this->label
                );
            }
            $result = false;
        }

        return $result;
    }

    protected function isValidPropertyNames($context): bool
    {
        $result = true;
        $schema = $this->schema['propertyNames'];
        if (is_array($schema)) {
            $schema['type'] = 'string';
        }

        foreach ($this->context_properties as $property => $value) {
            $prop_valid = $this->json_schema->isValidContext(
                $schema,
                $property,
                "$this->label[$property]"
            );
            if (!$prop_valid) {
                $result = false;
            }
        }

        return $result;
    }

    protected function isValidSize($context): bool
    {
        $result = true;
        $count = count($this->context_properties);

        if (isset($this->schema['minProperties']) and $count < $this->schema['minProperties']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'too few properties (min %u) at context path %s',
                    $this->schema['minProperties'],
                    $this->label
                );
            }
            $result = false;
        }
        if (isset($this->schema['maxProperties']) and $count > $this->schema['maxProperties']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'too many properties (max %u) at context path %s',
                    $this->schema['maxProperties'],
                    $this->label
                );
            }
            $result = false;
        }

        return $result;
    }

    protected function isValidDependencies($context): bool
    {
        $result = true;
        $all_props = array_keys($this->context_properties);

        foreach ($this->schema['dependencies'] as $property => $dependency) {
            if ($dependency and is_array($dependency) and $this->arrayIsVector($dependency)) {
                if (array_key_exists($property, $this->context_properties)) {
                    $missing = array_diff($dependency, $all_props);
                    if ($missing) {
                        if (isset($this->label)) {
                            $this->errors[] = sprintf(
                                'missing dependant properties: "%s" (depending on "%s") at context path %s',
                                implode('", "', $missing),
                                $property,
                                $this->label
                            );
                        }
                        $result = false;
                    }
                }
            } else {
                if (array_key_exists($property, $this->context_properties)) {
                    if (is_array($dependency) and !isset($dependency['type'])) {
                        $dependency['type'] = 'object';
                    }
                    $prop_valid = $this->json_schema->isValidContext(
                        $dependency,
                        $context,
                        $this->label
                    );
                    if (!$prop_valid) {
                        $result = false;
                    }
                }
            }
        }

        return $result;
    }
}
