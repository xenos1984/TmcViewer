<?php
include_once('config.php');
include_once('tmcfile.php');

$tables = array(
	'countries',
	'locationdatasets',
	'languages',
	'locationcodes',
	'names',
	'administrativearea',
	'otherareas',
	'roads',
	'segments',
	'soffsets',
	'points',
	'poffsets',
	'intersections'
);

$pdo = new PDO($config_pdo_connection, $config_pdo_write_user, $config_pdo_write_password, $config_pdo_attributes);

foreach($config_countries as $cc => $country)
{
	$charset = get_charset($country);
	echo "Importing location tables for $country from $charset...\n";

	foreach($tables as $table)
	{
		echo "Filling $table: ";

		$csv = fopen($config_lt_dir . '/' . $country . '/' . strtoupper($table) . '.DAT', 'r');
		$header = explode(";", trim(remove_utf8_bom(fgets($csv))));
		$cols = strtolower("(" . implode(", ", $header) . ")");
		$vals = strtolower("(" . implode(", ", array_map(function ($x) { return ':' . $x; }, $header)) . ")");
		$stmt = $pdo->prepare("INSERT INTO $table $cols VALUES $vals;");

		$total = 0;
		$success = 0;

		for(;;)
		{
			$text = iconv($charset, 'UTF-8', trim(fgets($csv)));
			if(!$text)
				break;

			$data = array_combine($header, explode(";", $text, count($header)));
			$total++;

			foreach($data as $key => $value)
			{
				if(($key == 'XCOORD') || ($key == 'YCOORD'))
					$value = ((float)$value) / 1.0e5;

				$stmt->bindValue(':' . strtolower($key), $value);
			}

			if($stmt->execute() === false)
			{
				print_r($data);
				print_r($stmt->errorInfo());
			}
			else
				$success++;
		}

		echo "$success of $total entries imported.\n";
	}
}
?>

