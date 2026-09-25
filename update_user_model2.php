<?php
$file = 'app/Models/User.php';
$content = file_get_contents($file);
$content = preg_replace('/\}\s*$/', "\n    public function locations()\n    {\n        return \$this->hasMany(UserLocation::class);\n    }\n}", $content);
file_put_contents($file, $content);
echo "Done";
