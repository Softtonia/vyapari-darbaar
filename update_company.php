<?php
$file = 'app/Models/Company.php';
$content = file_get_contents($file);
$content = preg_replace('/\}\s*$/', "\n    public function businessCategories()\n    {\n        return \$this->belongsToMany(BusinessCategory::class, 'company_business_categories');\n    }\n\n    public function businessDocuments()\n    {\n        return \$this->hasMany(BusinessDocument::class);\n    }\n}", $content);
file_put_contents($file, $content);
echo "Done";
