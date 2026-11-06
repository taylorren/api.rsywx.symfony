<?php

require_once 'vendor/autoload.php';

use OpenApi\Generator;

// Writes to docs/ (NOT public/) so the output is never served statically and
// can never shadow the on-the-fly /api-docs.json route.
$openapi = Generator::scan(['src/Controllers']);

file_put_contents('docs/api-docs.json', $openapi->toJson());
file_put_contents('docs/api-docs.yaml', $openapi->toYaml());

echo "Wrote docs/api-docs.json and docs/api-docs.yaml\n";