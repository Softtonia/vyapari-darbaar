<?php
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = DB::select('SHOW COLUMNS FROM companies');
foreach($cols as $col) echo $col->Field . ',';
