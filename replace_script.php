<?php
$dirs = ["app/Http/Controllers", "app/Models", "app/Actions", "app/Services", "app/Http/Requests", "app/Http/Resources", "routes"];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) continue;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() === "php") {
            $content = file_get_contents($file->getPathname());
            $original = $content;

            // Replacements
            $content = str_replace('App\Models\Company', 'App\Models\BusinessProfile', $content);
            $content = preg_replace('/\bCompany::/', 'BusinessProfile::', $content);
            $content = preg_replace('/\bCompanyResource\b/', 'BusinessProfileResource', $content);
            $content = preg_replace('/\bAdminCompanyController\b/', 'AdminBusinessProfileController', $content);
            $content = preg_replace('/\bUserCompanyController\b/', 'UserBusinessProfileController', $content);
            $content = preg_replace('/\bcompanies\.view\b/', 'business_profiles.view', $content);
            $content = preg_replace('/\bcompanies\.update\b/', 'business_profiles.update', $content);
            $content = preg_replace('/\bcompanies\.delete\b/', 'business_profiles.delete', $content);
            
            // Replaces variable names carefully
            $content = preg_replace('/\$company(?!_|[a-zA-Z])/', '$businessProfile', $content);
            $content = preg_replace('/\$companies(?!_|[a-zA-Z])/', '$businessProfiles', $content);
            
            // Replaces property/method accesses
            $content = preg_replace('/->company\b/', '->businessProfile', $content);
            $content = preg_replace('/->companies\(\)/', '->businessProfile()', $content);
            $content = preg_replace('/->companies\b/', '->businessProfile', $content);
            
            if ($content !== $original) {
                file_put_contents($file->getPathname(), $content);
                echo "Updated " . $file->getPathname() . "\n";
            }
        }
    }
}
