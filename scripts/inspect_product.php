<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$id = $argv[1] ?? 173;
$p = \App\Models\Product::find($id);
if (!$p) {
    echo "Product $id not found\n";
    exit(0);
}

echo "ID: " . $p->id . "\n";
echo "image: " . ($p->image ?? 'NULL') . "\n";
$imgs = $p->images()->pluck('path')->toArray();
foreach ($imgs as $path) echo "images path: $path\n";
