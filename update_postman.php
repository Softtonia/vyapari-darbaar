<?php
$file = "docs/postman/Vyapari-Darbaar.postman_collection.json";
$content = file_get_contents($file);
$content = str_replace("api/admin/companies", "api/admin/business-profiles", $content);
$content = str_replace("api/user/companies", "api/user/business-profiles", $content);
$content = str_replace("api/user/company", "api/user/business-profile", $content);
$content = str_replace("api/admin/company", "api/admin/business-profile", $content);
$content = str_replace('"name": "Company"', '"name": "Business Profile"', $content);
$content = str_replace('"name": "Companies"', '"name": "Business Profiles"', $content);
$content = str_replace('"name": "User Company"', '"name": "User Business Profile"', $content);
$content = str_replace('"name": "Admin Company"', '"name": "Admin Business Profile"', $content);
$content = str_replace('"key": "company_id"', '"key": "user_id"', $content); 
file_put_contents($file, $content);
echo "Postman JSON updated.\n";
