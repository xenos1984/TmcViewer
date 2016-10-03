<?php
// Enter database connection details here!
$pdo = new PDO('mysql:host=HOSTNAME;port=3306;dbname=DBNAME;charset=utf8', 'USERNAME', 'PASSWORD', array(PDO::ATTR_PERSISTENT => true));

// Enter list of countries to be imported. For every country there must be a directory with that name containing the TMC location code tables in TISA format.
$countries = array('de'=>"Germany", 'it'=>"Italy", 'se'=>"Sweden", 'no'=>"Norway", 'fi'=>"Finland", 'fr'=>"France", 'be'=>"Belgium", 'es'=>"Spain", 'sk'=>'Slovakia');

$tables = array('locationcodes', 'names', 'administrativearea', 'otherareas', 'roads', 'segments', 'soffsets', 'points', 'poffsets', 'intersections');

foreach($countries as $country)
{
	$query = "DELETE FROM countries WHERE name = '$country'";
	echo $pdo->exec($query) . ": $query\n";

	$charset = get_charset($country);

	$csv = fopen("$country/LOCATIONDATASETS.DAT", "r");
	$header = explode(";", utf8_encode(trim(fgets($csv))));
	$cols = strtolower("(" . implode(", ", array_merge($header, array('NAME'))) . ")");

	for(;;)
	{
		$text = fgets($csv);
		if(!$text)
			break;

		$data = array_combine($header, explode(";", iconv($charset, 'UTF-8', trim($text)), count($header)));
		$data['NAME'] = $country;
		$vals = "('" . implode("', '", $data) . "')";
		$query = "INSERT INTO countries $cols VALUES $vals;";
		echo $pdo->exec($query) . ": $query\n";

		foreach($tables as $table)
		{
			$query = "DELETE FROM $table WHERE cid = {$data['CID']}";
			echo $pdo->exec($query) . ": $query\n";
		}
	}

	fclose($csv);
}

function update_table($table)
{
	global $pdo;
	global $countries;

	$file = strtoupper($table);

	foreach($countries as $country)
	{
		$charset = get_charset($country);

		$csv = fopen("$country/$file.DAT", "r");
		$header = explode(";", utf8_encode(trim(fgets($csv))));
		$cols = strtolower("(" . implode(", ", $header) . ")");

		for(;;)
		{
			$text = fgets($csv);
			if(!$text)
				break;

			$data = array_combine($header, explode(";", iconv($charset, 'UTF-8', trim($text)), count($header)));
			if(strpos($cols, 'xcoord') !== false)
			{
				$data['XCOORD'] = ((float)$data['XCOORD'])/1e5;
				$data['YCOORD'] = ((float)$data['YCOORD'])/1e5;
			}
			$vals = "('" . implode("', '", $data) . "')";
			$query = "INSERT INTO $table $cols VALUES $vals;";
			echo $pdo->exec($query) . ": $query\n";
		}

		fclose($csv);
	}
}

function update_types($table, $file)
{
	global $pdo;
	global $countries;

	$table = strtolower($table);

	foreach($countries as $cid=>$country)
	{
		$charset = get_charset($country);

		$csv = fopen("$country/$file.DAT", "r");
		$header = explode(";", utf8_encode(trim(fgets($csv))));
		$cols = strtolower("(" . implode(", ", $header) . ")");

		// International Cols
		$headerInt = array('CLASS', 'TCD', 'STCD', '`DESC`');
		$colsInt = strtolower("(" . implode(", ", $headerInt) . ")");

		for(;;)
		{
			$text = fgets($csv);
			if(!$text)
				break;

			$data = array_combine($header, explode(";", iconv($charset, 'UTF-8', trim($text)), count($header)));

			// Only Cols: CLASS;TCD;STCD;SDESC
			$desc_lang = $data['SNATDESC'];
			unset($data['SNATDESC']);
			unset($data['SNATCODE']);

			$vals = "('" . implode("', '", $data) . "')";
			$query = "INSERT INTO $table $colsInt VALUES $vals;";
			echo $pdo->exec($query) . ": $query\n";

			// SNATDESC
			if($desc_lang){
				$query = "UPDATE $table SET desc_$cid='$desc_lang' WHERE class='".$data['CLASS']."' AND  tcd='".$data['TCD']."' AND stcd='".$data['STCD']."'";
				echo $pdo->exec($query) . ": $query\n";
			}

		}
	}
}

function get_charset($country)
{
	$readme = explode(";", trim(file_get_contents("$country/README.DAT")));
	$arr = explode(' ', $readme[4]);
	if($arr[0] == 'ISO')
		// Variant for German ISO 8859 without minus
		$arr = explode(' (', $readme[4]);
	return $arr[0];
}

update_types('TYPES', 'SUBTYPES');

foreach($tables as $table)
	update_table($table);
?>
