<?php

namespace Vertilia\JsonSchema;

use PHPUnit\Framework\TestCase;

/**
 * Based on official JSON Schema specifications,
 * Understanding JSON Schema, Release 7.0
 * @link https://json-schema.org/understanding-json-schema/UnderstandingJSONSchema.pdf
 * @coversDefaultClass \Vertilia\JsonSchema\JsonSchema
 */
class JsonSchemaTest extends TestCase
{
    /**
     * @dataProvider getVersionProvider
     * @covers ::__construct
     * @covers ::getVersion
     * @covers ::setSchema
     * @param string $json_schema
     * @param int $expected
     */
    public function testGetVersion($json_schema, $expected)
    {
        $schema = new JsonSchema();
        $this->assertEquals($expected, $schema->setSchema($json_schema)->getVersion());
    }

    /** data provider */
    public function getVersionProvider()
    {
        return [
            ['{"$schema": "http://json-schema.org/draft-04/schema#"}', 4],
            ['{"$schema": "http://json-schema.org/draft-06/schema#"}', 6],
            ['{"$schema": "http://json-schema.org/draft-07/schema#"}', 7],
            ['{"$schema": "http://json-schema.org/schema#"}', 7],
            ['{"$schema": "http://json-schema.org/draft/2019-09/schema#"}', 7],
        ];
    }

    /**
     * @dataProvider isValidBasicsProvider
     * @dataProvider isValidStringProvider
     * @dataProvider isValidNumberProvider
     * @dataProvider isValidObjectProvider
     * @dataProvider isValidBooleanProvider
     * @dataProvider isValidNullProvider
     * @dataProvider isValidGenericProvider
     * @dataProvider isValidArrayProvider
     * @covers ::__construct
     * @covers ::isValid
     * @param string $json_schema
     * @param string $json_value
     * @param bool $expected
     */
    public function testIsValid($json_schema, $json_value, $expected)
    {
        $schema = new JsonSchema($json_schema);
        $this->assertEquals($expected, $schema->isValid($json_value));
        $errors = $schema->getErrors();
        if ($errors) {
            print_r($errors);
        }
    }

    /** data provider */
    public function isValidBasicsProvider()
    {
        $ex_2 =
        '{
            "type": "object",
            "properties": {
                "first_name": {"type": "string"},
                "last_name": {"type": "string"},
                "birthday": {"type": "string", "format": "date"},
                "address": {
                    "type": "object",
                    "properties": {
                        "street_address": {"type": "string"},
                        "city": {"type": "string"},
                        "state": {"type": "string"},
                        "country": {"type" : "string"}
                    }
                }
            }
        }';

        return [
            // {} is always valid
            ['{}', 'null', true],
            ['{}', '"abc"', true],
            ['{}', '42', true],
            ['{}', 'true', true],
            ['{}', 'false', true],
            ['{}', '{}', true],

            // D6: true is always valid
            ['true', 'null', true],
            ['true', '"abc"', true],
            ['true', '42', true],
            ['true', 'true', true],
            ['true', 'false', true],
            ['true', '{}', true],

            // D6: false is never valid
            ['false', 'null', false],
            ['false', '"abc"', false],
            ['false', '42', false],
            ['false', 'true', false],
            ['false', 'false', false],
            ['false', '{}', false],

            // type is array
            ['{"type": ["number", "string"]}', '42', true],
            ['{"type": ["number", "string"]}', '"Life, the universe, and everything"', true],
            ['{"type": ["number", "string"]}', '["Life", "the universe", "and everything"]', false],

            // object basics
            [
                $ex_2,
                '{
                    "name": "George Washington",
                    "birthday": "February 22, 1732",
                    "address": "Mount Vernon, Virginia, United States"
                }',
                false,
            ],
            [
                $ex_2,
                '{
                    "first_name": "George",
                    "last_name": "Washington",
                    "birthday": "1732-02-22",
                    "address": {
                        "street_address": "3200 Mount Vernon Memorial Highway",
                        "city": "Mount Vernon",
                        "state": "Virginia",
                        "country": "United States"
                    }
                }',
                true,
            ],
        ];
    }

    /** data provider */
    public function isValidNumberProvider()
    {
        $d4_number = '{
            "$schema": "http://json-schema.org/draft-04/schema#",
            "type": "number",
            "minimum": 0,
            "maximum": 100,
            "exclusiveMaximum": true
        }';

        return [
            // integer
            ['{"type": "integer"}', '42', true],
            ['{"type": "integer"}', '-1', true],
            ['{"type": "integer"}', '3.1415926', false],
            ['{"type": "integer"}', '"42"', false],

            // number
            ['{"type": "number"}', '42', true],
            ['{"type": "number"}', '-1', true],
            ['{"type": "number"}', '5.0', true],
            ['{"type": "number"}', '2.99792458e8', true],
            ['{"type": "number"}', '"42"', false],

            // number / multipleOf
            ['{"type": "number", "multipleOf": 1.0}', '42', true],
            ['{"type": "number", "multipleOf": 1.0}', '42.0', true],
            ['{"type": "number", "multipleOf": 1.0}', '3.14156926', false],
            ['{"type": "number", "multipleOf": 10}', '0', true],
            ['{"type": "number", "multipleOf": 10}', '10', true],
            ['{"type": "number", "multipleOf": 10}', '20', true],
            ['{"type": "number", "multipleOf": 10}', '23', false],

            // number / range
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '-1', false],
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '0', true],
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '10', true],
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '99', true],
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '100', false],
            ['{"type": "number", "minimum": 0, "exclusiveMaximum": 100}', '101', false],

            // D4: number / range
            [$d4_number, '-1', false],
            [$d4_number, '0', true],
            [$d4_number, '10', true],
            [$d4_number, '99', true],
            [$d4_number, '100', false],
            [$d4_number, '101', false],
        ];
    }

    /** data provider */
    public function isValidStringProvider()
    {
        return [
            // string
            ['{"type": "string"}', '"I\'m a string"', true],
            ['{"type": "string"}', '"Déjà vu"', true],
            ['{"type": "string"}', '""', true],
            ['{"type": "string"}', '"42"', true],
            ['{"type": "string"}', '42', false],

            // string / length
            ['{"type": "string", "minLength": 2, "maxLength": 3}', '"A"', false],
            ['{"type": "string", "minLength": 2, "maxLength": 3}', '"AB"', true],
            ['{"type": "string", "minLength": 2, "maxLength": 3}', '"ABC"', true],
            ['{"type": "string", "minLength": 2, "maxLength": 3}', '"ABCD"', false],

            // string / pattern
            ['{"type": "string", "pattern": "^(\\\\([0-9]{3}\\\\))?[0-9]{3}-[0-9]{4}$"}', '"555-1212"', true],
            ['{"type": "string", "pattern": "^(\\\\([0-9]{3}\\\\))?[0-9]{3}-[0-9]{4}$"}', '"(888)555-1212"', true],
            ['{"type": "string", "pattern": "^(\\\\([0-9]{3}\\\\))?[0-9]{3}-[0-9]{4}$"}', '"(888)555-1212 / 5"', false],
            ['{"type": "string", "pattern": "^(\\\\([0-9]{3}\\\\))?[0-9]{3}-[0-9]{4}$"}', '"(800)FLOWERS"', false],

            // string / format
            ['{"type": "string", "format": "date-time"}', '"2018-11-13T20:20:39+00:00"', true],
            ['{"type": "string", "format": "date-time"}', '"2018-11-13T20:20:39.123+00:00"', true],
            ['{"type": "string", "format": "date-time"}', '"2018-11-13T20:20:39Z"', true],
            ['{"type": "string", "format": "date-time"}', '"what time is it?"', false],
            ['{"type": "string", "format": "time"}', '"20:20:39+00:00"', true],
            ['{"type": "string", "format": "time"}', '"20:20:39.123+00:00"', true],
            ['{"type": "string", "format": "time"}', '"20:20:39Z"', true],
            ['{"type": "string", "format": "time"}', '"what time is it?"', false],
            ['{"type": "string", "format": "date"}', '"2018-11-13"', true],
            ['{"type": "string", "format": "date"}', '"what time is it?"', false],
            ['{"type": "string", "format": "email"}', '"spring@example.com"', true],
            ['{"type": "string", "format": "email"}', '"весна@example.com"', false],
            ['{"type": "string", "format": "email"}', '"unknown email"', false],
            ['{"type": "string", "format": "idn-email"}', '"spring@example.com"', true],
            ['{"type": "string", "format": "idn-email"}', '"весна@example.com"', true],
            ['{"type": "string", "format": "idn-email"}', '"unknown idn email"', false],
            ['{"type": "string", "format": "hostname"}', '"www.example.com"', true],
            ['{"type": "string", "format": "hostname"}', '"весна.example.com"', false],
            ['{"type": "string", "format": "hostname"}', '"unknown domain"', false],
            ['{"type": "string", "format": "idn-hostname"}', '"www.example.com"', true],
            ['{"type": "string", "format": "idn-hostname"}', '"весна.example.com"', extension_loaded('intl')],
            ['{"type": "string", "format": "idn-hostname"}', '"unknown domain"', false],
            ['{"type": "string", "format": "ipv4"}', '"1.2.3.4"', true],
            ['{"type": "string", "format": "ipv4"}', '"255.255.255.255"', true],
            ['{"type": "string", "format": "ipv4"}', '"123.234.345.456"', false],
            ['{"type": "string", "format": "ipv4"}', '"not an ipv4"', false],
            ['{"type": "string", "format": "ipv6"}', '"1080:0:0:0:8:800:200C:417A"', true],
            ['{"type": "string", "format": "ipv6"}', '"1080::8:800:200C:417A"', true],
            ['{"type": "string", "format": "ipv6"}', '"FF01:0:0:0:0:0:0:101"', true],
            ['{"type": "string", "format": "ipv6"}', '"FF01::101"', true],
            ['{"type": "string", "format": "ipv6"}', '"0:0:0:0:0:0:0:1"', true],
            ['{"type": "string", "format": "ipv6"}', '"::1"', true],
            ['{"type": "string", "format": "ipv6"}', '"0:0:0:0:0:0:0:0"', true],
            ['{"type": "string", "format": "ipv6"}', '"::"', true],
            ['{"type": "string", "format": "ipv6"}', '"0:0:0:0:0:0:13.1.68.3"', true],
            ['{"type": "string", "format": "ipv6"}', '"::13.1.68.3"', true],
            ['{"type": "string", "format": "ipv6"}', '"0:0:0:0:0:FFFF:129.144.52.38"', true],
            ['{"type": "string", "format": "ipv6"}', '"::FFFF:129.144.52.38"', true],
            ['{"type": "string", "format": "ipv6"}', '"::X"', false],
            ['{"type": "string", "format": "ipv6"}', '"not an ipv6"', false],
            ['{"type": "string", "format": "uri"}', '"ftp://ftp.is.co.za/rfc/rfc1808.txt"', true],
            ['{"type": "string", "format": "uri"}', '"http://www.ietf.org/rfc/rfc2396.txt"', true],
            ['{"type": "string", "format": "uri"}', '"ldap://[2001:db8::7]/c=GB?objectClass?one"', true],
            ['{"type": "string", "format": "uri"}', '"mailto:John.Doe@example.com"', true],
            ['{"type": "string", "format": "uri"}', '"news:comp.infosystems.www.servers.unix"', true],
            ['{"type": "string", "format": "uri"}', '"tel:+1-816-555-1212"', true],
            ['{"type": "string", "format": "uri"}', '"telnet://192.0.2.16:80/"', true],
            ['{"type": "string", "format": "uri"}', '"urn:oasis:names:specification:docbook:dtd:xml:4.1.2"', true],
            ['{"type": "string", "format": "uri"}', '"not a uri"', false],
            ['{"type": "string", "format": "uri-reference"}', '"//ftp.is.co.za/rfc/rfc1808.txt"', true],
            ['{"type": "string", "format": "uri-reference"}', '"/rfc/rfc2396.txt"', true],
            ['{"type": "string", "format": "uri-reference"}', '"not a uri-reference"', false],
            ['{"type": "string", "format": "iri"}', '"ftp://осень.is.co.za/rfc/осень.txt"', true],
            ['{"type": "string", "format": "iri"}', '"http://осень.ietf.org/rfc/осень.txt"', true],
            ['{"type": "string", "format": "iri"}', '"совсем не iri"', false],
            ['{"type": "string", "format": "iri-reference"}', '"//осень.is.co.za/rfc/осень.txt"', true],
            ['{"type": "string", "format": "iri-reference"}', '"/rfc/осень.txt"', true],
            ['{"type": "string", "format": "iri-reference"}', '"совсем не iri-reference"', false],
            ['{"type": "string", "format": "uri-template"}', '"{var}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{+var}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{#var}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{v1,v2,v3}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{+v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{#v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{.v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{/v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{;v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{?v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{&v1,v2}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{var:30}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{var*}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{/var*}"', true],
            ['{"type": "string", "format": "uri-template"}', '"{/v1*,v2:4}"', true],
            ['{"type": "string", "format": "uri-template"}', '"not a uri-template"', false],
            ['{"type": "string", "format": "json-pointer"}', '"/meta"', true],
            ['{"type": "string", "format": "json-pointer"}', '"/meta~0definition"', true],
            ['{"type": "string", "format": "json-pointer"}', '"not a json-pointer"', false],
            ['{"type": "string", "format": "json-pointer"}', '"/meta~definition"', false],
            ['{"type": "string", "format": "relative-json-pointer"}', '"0"', true],
            ['{"type": "string", "format": "relative-json-pointer"}', '"1/0"', true],
            ['{"type": "string", "format": "relative-json-pointer"}', '"2/highly/nested/objects"', true],
            ['{"type": "string", "format": "relative-json-pointer"}', '"0#"', true],
            ['{"type": "string", "format": "relative-json-pointer"}', '"1#"', true],
            ['{"type": "string", "format": "relative-json-pointer"}', '"2/foo/0"', true],
            ['{"type": "string", "format": "json-pointer"}', '"not a relative-json-pointer"', false],
            ['{"type": "string", "format": "regex"}', '"^\\\\d+"', true],
            ['{"type": "string", "format": "regex"}', '""', false],
        ];
    }

    /** data provider */
    public function isValidObjectProvider()
    {
        $ex_4_5_1_1 =
        '{
            "type": "object",
            "properties": {
                "number": {"type": "number"},
                "street_name": {"type": "string"},
                "street_type": {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                }
            }
        }';
        $ex_4_5_1_2 =
        '{
            "type": "object",
            "properties": {
                "number": {"type": "number"},
                "street_name": {"type": "string"},
                "street_type": {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                }
            },
            "additionalProperties": false
        }';
        $ex_4_5_1_3 =
        '{
            "type": "object",
            "properties": {
                "number": {"type": "number"},
                "street_name": {"type": "string"},
                "street_type": {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                }
            },
            "additionalProperties": {"type": "string"}
        }';
        $ex_4_5_2 =
        '{
            "type": "object",
            "properties": {
                "name": {"type": "string"},
                "email": {"type": "string"},
                "address": {"type": "string"},
                "telephone": {"type": "string"}
            },
            "required": ["name", "email"]
        }';
        $ex_4_5_3 =
        '{
            "type": "object",
            "propertyNames": {
                "pattern": "^[A-Za-z_][A-Za-z0-9_]*$"
            }
        }';
        $ex_4_5_4 =
        '{
            "type": "object",
            "minProperties": 2,
            "maxProperties": 3
        }';
        $ex_4_5_5_1 =
        '{
            "type": "object",
            "properties": {
                "name": {"type": "string"},
                "credit_card": {"type": "number"},
                "billing_address": {"type": "string"}
            },
            "required": ["name"],
            "dependencies": {
                "credit_card": ["billing_address"]
            }
        }';
        $ex_4_5_5_2 =
        '{
            "type": "object",
            "properties": {
                "name": {"type": "string"},
                "credit_card": {"type": "number"},
                "billing_address": {"type": "string"}
            },
            "required": ["name"],
            "dependencies": {
                "credit_card": ["billing_address"],
                "billing_address": ["credit_card"]
            }
        }';
        $ex_4_5_5_3 =
        '{
            "type": "object",
            "properties": {
                "name": {"type": "string"},
                "credit_card": {"type": "number"}
            },
            "required": ["name"],
            "dependencies": {
                "credit_card": {
                    "properties": {
                        "billing_address": {"type": "string"}
                    },
                    "required": ["billing_address"]
                }
            }
        }';
        $ex_4_5_6_1 =
        '{
            "type": "object",
            "patternProperties": {
                "^S_": {"type": "string"},
                "^I_": {"type": "integer"}
            },
            "additionalProperties": false
        }';
        $ex_4_5_6_2 =
        '{
            "type": "object",
            "properties": {
                "builtin": {"type": "number"}
            },
            "patternProperties": {
                "^S_": {"type": "string"},
                "^I_": {"type": "integer"}
            },
            "additionalProperties": {"type": "string"}
        }';

        return [
            // object
            [
                '{"type": "object"}',
                '{"key": "value", "another_key": "another_value"}',
                true,
            ],
            [
                '{"type": "object"}',
                '{
                    "Sun": 1.9891e30,
                    "Jupiter": 1.8986e27,
                    "Saturn": 5.6846e26,
                    "Neptune": 10.243e25,
                    "Uranus": 8.6810e25,
                    "Earth": 5.9736e24,
                    "Venus": 4.8685e24,
                    "Mars": 6.4185e23,
                    "Mercury": 3.3022e23,
                    "Moon": 7.349e22,
                    "Pluto": 1.25e22
                }',
                true,
            ],
            ['{"type": "object"}', '"Not an object"', false],
            ['{"type": "object"}', '["An", "array", "not", "an", "object"]', false],

            // object / properties
            [$ex_4_5_1_1, '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue"}', true],
            [$ex_4_5_1_1, '{"number": "1600", "street_name": "Pennsylvania", "street_type": "Avenue"}', false],
            [$ex_4_5_1_1, '{"number": 1600, "street_name": "Pennsylvania"}', true],
            [$ex_4_5_1_1, '{}', true],
            [
                $ex_4_5_1_1,
                '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue", "direction": "NW"}',
                true,
            ],

            // object / additionalProperties
            [
                $ex_4_5_1_2,
                '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue"}',
                true,
            ],
            [
                $ex_4_5_1_2,
                '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue", "direction": "NW"}',
                false,
            ],
            [
                $ex_4_5_1_3,
                '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue"}',
                true,
            ],
            [
                $ex_4_5_1_3,
                '{"number": 1600, "street_name": "Pennsylvania", "street_type": "Avenue", "direction": "NW"}',
                true,
            ],

            // object / required
            [
                $ex_4_5_2,
                '{"name": "William Shakespeare", "email": "bill@stratford-upon-avon.co.uk"}',
                true,
            ],
            [
                $ex_4_5_2,
                '{
                    "name": "William Shakespeare",
                    "email": "bill@stratford-upon-avon.co.uk",
                    "address": "Henley Street, Stratford-upon-Avon, Warwickshire, England",
                    "authorship": "in question"
                }',
                true,
            ],
            [
                $ex_4_5_2,
                '{
                    "name": "William Shakespeare",
                    "address": "Henley Street, Stratford-upon-Avon, Warwickshire, England"
                }',
                false,
            ],

            // object / propertyNames
            [$ex_4_5_3, '{"_a_proper_token_001": "value"}', true],
            [$ex_4_5_3, '{"001 invalid": "value"}', false],

            // object / size
            [$ex_4_5_4, '{}', false],
            [$ex_4_5_4, '{"a": 0}', false],
            [$ex_4_5_4, '{"a": 0, "b": 1}', true],
            [$ex_4_5_4, '{"a": 0, "b": 1, "c": 2}', true],
            [$ex_4_5_4, '{"a": 0, "b": 1, "c": 2, "d": 3}', false],

            // object / dependencies
            [
                $ex_4_5_5_1,
                '{
                    "name": "John Doe",
                    "credit_card": 5555555555555555,
                    "billing_address": "555 Debtor\'s Lane"
                }',
                true,
            ],
            [
                $ex_4_5_5_1,
                '{
                    "name": "John Doe",
                    "credit_card": 5555555555555555
                }',
                false,
            ],
            [
                $ex_4_5_5_1,
                '{
                    "name": "John Doe"
                }',
                true,
            ],
            [
                $ex_4_5_5_1,
                '{
                    "name": "John Doe",
                    "billing_address": "555 Debtor\'s Lane"
                }',
                true,
            ],
            [
                $ex_4_5_5_2,
                '{
                    "name": "John Doe",
                    "credit_card": 5555555555555555
                }',
                false,
            ],
            [
                $ex_4_5_5_2,
                '{
                    "name": "John Doe",
                    "billing_address": "555 Debtor\'s Lane"
                }',
                false,
            ],
            [
                $ex_4_5_5_3,
                '{
                    "name": "John Doe",
                    "credit_card": 5555555555555555,
                    "billing_address": "555 Debtor\'s Lane"
                }',
                true,
            ],
            [
                $ex_4_5_5_3,
                '{
                    "name": "John Doe",
                    "credit_card": 5555555555555555
                }',
                false,
            ],
            [$ex_4_5_6_1, '{"S_25": "This is a string"}', true],
            [$ex_4_5_6_1, '{"I_0": 42}', true],
            [$ex_4_5_6_1, '{"S_0": 42}', false],
            [$ex_4_5_6_1, '{"I_42": "This is a string"}', false],
            [$ex_4_5_6_1, '{"keyword": "value"}', false],
            [$ex_4_5_6_2, '{"builtin": 42}', true],
            [$ex_4_5_6_2, '{"keyword": "value"}', true],
            [$ex_4_5_6_2, '{"keyword": 42}', false],
        ];
    }

    /** data provider */
    public function isValidArrayProvider()
    {
        $ex_4_6_1_1 =
        '{
            "type": "array",
            "items": [
                {
                    "type": "number"
                },
                {
                    "type": "string"
                },
                {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                },
                {
                    "type": "string",
                    "enum": ["NW", "NE", "SW", "SE"]
                }
            ]
        }';
        $ex_4_6_1_2 =
        '{
            "type": "array",
            "items": [
                {
                    "type": "number"
                },
                {
                    "type": "string"
                },
                {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                },
                {
                    "type": "string",
                    "enum": ["NW", "NE", "SW", "SE"]
                }
            ],
            "additionalItems": false
        }';
        $ex_4_6_1_3 =
        '{
            "type": "array",
            "items": [
                {
                    "type": "number"
                },
                {
                    "type": "string"
                },
                {
                    "type": "string",
                    "enum": ["Street", "Avenue", "Boulevard"]
                },
                {
                    "type": "string",
                    "enum": ["NW", "NE", "SW", "SE"]
                }
            ],
            "additionalItems": {"type": "string"}
        }';

        return [
            // lists

            // array / items
            ['{"type": "array", "items": {"type": "number"}}', '[1, 2, 3, 4, 5]', true],
            ['{"type": "array", "items": {"type": "number"}}', '[1, 2, "3", 4, 5]', false],
            ['{"type": "array", "items": {"type": "number"}}', '[]', true],

            // D6: array / contains
            ['{"type": "array", "contains": {"type": "number"}}', '["life", "universe", "everything", 42]', true],
            ['{"type": "array", "contains": {"type": "number"}}', '["life", "universe", "everything", "42"]', false],
            ['{"type": "array", "contains": {"type": "number"}}', '[1, 2, 3, 4, 5]', true],

            // tuples
            // array
            [$ex_4_6_1_1, '[1600, "Pennsylvania", "Avenue", "NW"]', true],
            [$ex_4_6_1_1, '[24, "Sussex", "Drive"]', false],
            [$ex_4_6_1_1, '["Palais de l\'Élysée"]', false],
            [$ex_4_6_1_1, '[10, "Downing", "Street"]', true],
            [$ex_4_6_1_1, '[1600, "Pennsylvania", "Avenue", "NW", "Washington"]', true],

            // array / additionalItems as false
            [$ex_4_6_1_2, '[1600, "Pennsylvania", "Avenue", "NW"]', true],
            [$ex_4_6_1_2, '[1600, "Pennsylvania", "Avenue"]', true],
            [$ex_4_6_1_2, '[1600, "Pennsylvania", "Avenue", "NW", "Washington"]', false],

            // array / additionalItems as schema
            [$ex_4_6_1_3, '[1600, "Pennsylvania", "Avenue", "NW", "Washington"]', true],
            [$ex_4_6_1_3, '[1600, "Pennsylvania", "Avenue", "NW", 20500]', false],
        ];
    }

    /** data provider */
    public function isValidBooleanProvider()
    {
        return [
            // boolean
            ['{"type": "boolean"}', 'true', true],
            ['{"type": "boolean"}', 'false', true],
            ['{"type": "boolean"}', '"true"', false],
            ['{"type": "boolean"}', '0', false],
        ];
    }

    /** data provider */
    public function isValidNullProvider()
    {
        return [
            // null
            ['{"type": "null"}', 'null', true],
            ['{"type": "null"}', 'false', false],
            ['{"type": "null"}', '0', false],
            ['{"type": "null"}', '""', false],
        ];
    }

    /** data provider */
    public function isValidGenericProvider()
    {
        return [
            // enum
            ['{"enum": ["red", "amber", "green", null, 42]}', '"red"', true],
            ['{"enum": ["red", "amber", "green", null, 42]}', 'null', true],
            ['{"enum": ["red", "amber", "green", null, 42]}', '42', true],
            ['{"enum": ["red", "amber", "green", null, 42]}', '0', false],

            // string / enum
            ['{"type": "string", "enum": ["red", "amber", "green", null]}', '"red"', true],
            ['{"type": "string", "enum": ["red", "amber", "green", null]}', 'null', false],

            // D6: const
            ['{"const": "Antarctida"}', '"Antarctida"', true],
            ['{"const": "Antarctida"}', '"Arctic"', false],
        ];
    }
}
