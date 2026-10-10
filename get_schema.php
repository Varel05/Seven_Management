<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = Illuminate\Support\Facades\Schema::getTables();
$schema = [];
foreach ($tables as $table) {
    $tableName = $table['name'];
    if (strpos($tableName, 'sm_') === 0 || in_array($tableName, ['users', 'products'])) {
        $schema[$tableName] = Illuminate\Support\Facades\Schema::getColumns($tableName);
    }
}
file_put_contents('schema_output.json', json_encode($schema, JSON_PRETTY_PRINT));
