<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Schema::table('companies', function ($table) {
    if (Illuminate\Support\Facades\Schema::hasColumn('companies', 'country_id')) {
        $table->dropColumn('country_id');
    }
    if (Illuminate\Support\Facades\Schema::hasColumn('companies', 'state_id')) {
        $table->dropColumn('state_id');
    }
    if (Illuminate\Support\Facades\Schema::hasColumn('companies', 'city_id')) {
        $table->dropColumn('city_id');
    }
});
echo "Dropped";
