<?php

require_once 'vendor/autoload.php';

use OpenApi\Generator;

// Generate OpenAPI documentation
$openapi = Generator::scan(['src/Controllers']);

// Save as JSON
file_put_contents('public/api-docs.json', $openapi->toJson());

// Save as YAML (optional)
file_put_contents('public/api-docs.yaml', $openapi->toYaml());

echo "API documentation generated successfully!\n";
echo "JSON: public/api-docs.json\n";
echo "YAML: public/api-docs.yaml\n";