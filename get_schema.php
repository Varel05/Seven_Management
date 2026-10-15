<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$tables = Schema::getTables();
$schema = [];
foreach ($tables as $table) {
    $tableName = $table['name'];
    if (strpos($tableName, 'sm_') === 0 || in_array($tableName, ['users', 'products'])) {
        $schema[$tableName] = Schema::getColumns($tableName);
    }
}
file_put_contents('schema_output.json', json_encode($schema, JSON_PRETTY_PRINT));
