# JSON Schema validator

A lightweight Draft 7 JSON Schema validator, according to [Understanding JSON Schema](https://json-schema.org/understanding-json-schema).

# Usage

```php
<?php

$validator = new Vertilia\JsonSchema('{
  "type": "array",
  "items": {"type": "number"}
}');

print_r($validator->isValid('[1, 2, 3]')); // true, array of numbers

print_r($validator->isValid('[1, 2, "3"]')); // false, array of numbers and a string
```

More examples available in `/tests/`.

# Installation

```
cd /your/project/root/
composer require vertilia/json-schema
```
