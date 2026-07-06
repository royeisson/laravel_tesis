<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='users' ORDER BY ordinal_position");
echo "Tabla users:\n";
foreach ($cols as $c) echo '- ' . $c->column_name . ' (' . $c->data_type . ')' . "\n";

echo "\nTabla aulas:\n";
$cols = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name='aulas' ORDER BY ordinal_position");
foreach ($cols as $c) echo '- ' . $c->column_name . ' (' . $c->data_type . ')' . "\n";
