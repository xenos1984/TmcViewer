<?php
// Rename this file to config.php and edit settings.

// Connection details.
$config_pdo_connection = 'mysql:host=HOSTNAME;port=3306;dbname=DBNAME;charset=utf8';
$config_pdo_readonly_user = 'READONLY_USERNAME';
$config_pdo_readonly_password = 'READONLY_PASSWORD';
$config_pdo_write_user = 'WRITE_USERNAME';
$config_pdo_write_password = 'WRITE_PASSWORD';
$config_pdo_attributes = array(PDO::ATTR_PERSISTENT => true);

// Countries for which new tables are imported or updated. For every country there must be a directory with that name containing the TMC location code tables in TISA format.
$config_countries = array(
	'be' => "Belgium",
	'de' => "Germany",
	'es' => "Spain",
	'fi' => "Finland",
	'fr' => "France",
	'it' => "Italy",
	'no' => "Norway",
	'se' => "Sweden",
	'sk' => 'Slovakia'
);

// Directory in which tables can be found.
$config_lt_dir = './';
?>

