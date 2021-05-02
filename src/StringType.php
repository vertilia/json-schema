<?php
declare(strict_types=1);

namespace Vertilia\JsonSchema;

class StringType extends BaseType
{
    /**
     * @param mixed $context
     * @return bool
     */
    public function isValid($context): bool
    {
        if (!is_string($context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s must be a string at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }

        $result = parent::isValid($context);

        // verify lengths
        if ((isset($this->schema['minLength']) or isset($this->schema['maxLength']))
            and !$this->isValidLength($context)
        ) {
            $result = false;
        }

        // verify pattern
        if (isset($this->schema['pattern'])
            and !$this->isValidPattern($context)
        ) {
            $result = false;
        }

        // verify format
        if (isset($this->schema['format'])
            and !$this->isValidFormat($context)
        ) {
            $result = false;
        }

        // D7: verify content encoding
        if (isset($this->schema['contentEncoding'])
            and $this->json_schema->getVersion() >= 7
            and !$this->isValidContentEncoding($context)
        ) {
            $result = false;
        }


        // D7: verify content media type
        if (isset($this->schema['contentMediaType'])
            and $this->json_schema->getVersion() >= 7
            and !$this->isValidContentMediaType($context)
        ) {
            $result = false;
        }

        return $result ? parent::isValid($context) : false;
    }

    protected function isValidLength($context): bool
    {
        $result = true;
        $length = strlen($context);

        if (isset($this->schema['minLength']) and $length < $this->schema['minLength']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'string min length must be %u, given: %u at context path: %s',
                    $this->schema['minLength'],
                    $length,
                    $this->label
                );
            }
            $result = false;
        }
        if (isset($this->schema['maxLength']) and $length > $this->schema['maxLength']) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'string max length must be %u, given: %u at context path: %s',
                    $this->schema['maxLength'],
                    $length,
                    $this->label
                );
            }
            $result = false;
        }

        return $result;
    }

    protected function isValidPattern($context): bool
    {
        if (!preg_match("/{$this->schema['pattern']}/u", $context)) {
            if (isset($this->label)) {
                $this->errors[] = sprintf(
                    'value %s does not match pattern at context path: %s',
                    $this->contextStr($context, 64),
                    $this->label
                );
            }
            return false;
        }
        return true;
    }

    protected function isValidFormat($context): bool
    {
        $result = true;

        switch ($this->schema['format']) {
            case 'date-time':
                // https://tools.ietf.org/html/rfc3339
                if (!preg_match(
                    '/^\d\d\d\d-\d\d-\d\d[Tt ]\d\d:\d\d:\d\d(?:\.\d+)?(?:[Zz]|[+-]\d\d:\d\d)?$/',
                    $context
                )) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 32),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'time':
                if (!preg_match('/^\d\d:\d\d:\d\d(?:\.\d+)?(?:[Zz]|[+-]\d\d:\d\d)?$/', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 32),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'date':
                if (!preg_match('/^\d\d\d\d-\d\d-\d\d$/', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 10),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'email':
                if (!filter_var($context, FILTER_VALIDATE_EMAIL)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'idn-email':
                if (!filter_var($context, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'hostname':
                if (!filter_var($context, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'idn-hostname':
                $context_idn = extension_loaded('intl') ? idn_to_ascii($context) : $context;
                if (!filter_var($context_idn, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'ipv4':
                if (!filter_var($context, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 20),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'ipv6':
                if (!filter_var($context, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 45),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'uri':
            case 'uri-reference':
            case 'iri':
            case 'iri-reference':
                // https://tools.ietf.org/html/rfc3986
                // https://tools.ietf.org/html/rfc3987
                $unicode = in_array($this->schema['format'], ['iri', 'iri-reference']) ? 'u' : '';
                if (!preg_match('@^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?$@', $context, $m)
                    or preg_match("`[^:/?#[\]@!$&'()*+,;=[:alpha:][:digit:]._~-]`$unicode", $context)
                    or in_array($this->schema['format'], ['uri', 'iri']) && empty($m[1])
                ) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'uri-template':
                // https://tools.ietf.org/html/rfc6570
                if (!preg_match('`^\{[+#./;?&]?\w+(?:[*]|:\d+)?(?:,\w+(?:[*]|:\d+)?)*\}$`', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'json-pointer':
                // https://tools.ietf.org/html/rfc6901
                if (!preg_match('#^(?:/(?:[^~/]*|~[01])+)*$#', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'relative-json-pointer':
                // https://tools.ietf.org/html/draft-handrews-relative-json-pointer-01
                if (!preg_match('`^\d+(?:#|(?:/(?:[^~/]*|~[01])+)*)$`', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: %s at context path: %s',
                            $this->schema['format'],
                            $this->contextStr($context, 64),
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
            case 'regex':
                if (strlen($context) == 0) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            '"%s" format mismatch: empty string at context path %s',
                            $this->schema['format'],
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
        }

        return $result;
    }

    /**
     * mime type correctness is not verified since is application dependent
     * @param string $context
     */
    protected function isValidContentMediaType(string $context): bool
    {
        return true;
    }

    /**
     * correctness is only guaranteed for base64 encoding
     * @param string $context
     */
    protected function isValidContentEncoding(string $context): bool
    {
        $result = true;

        switch (strtolower($this->schema['contentEncoding'])) {
            case '7bit':
            case '8bit':
            case 'binary':
            case 'quoted-printable':
                break;
            case 'base64':
                // @see http://www.faqs.org/rfcs/rfc2045.html section 6.8
                if (!preg_match('#^[A-Za-z0-9+/\s]*={0,2}$#', $context)) {
                    if (isset($this->label)) {
                        $this->errors[] = sprintf(
                            'content is not base64-encoded string at context path %s',
                            $this->label
                        );
                    }
                    $result = false;
                }
                break;
        }

        return $result;
    }
}
