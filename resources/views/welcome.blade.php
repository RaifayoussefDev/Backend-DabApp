<?php
// display time now
echo "Current time: " . date("Y-m-d H:i:s");
$countryName = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Morocco';
echo " - Country: " . $countryName;
?>
