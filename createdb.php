<?php
// Enter database connection details here!
$pdo = new PDO('mysql:host=HOSTNAME;port=3306;dbname=DBNAME;charset=utf8', 'USERNAME', 'PASSWORD', array(PDO::ATTR_PERSISTENT => true));

// Enter list of countries to be imported. For every country there must be a directory with that name containing the TMC location code tables in TISA format.
$countries = array("Germany", "Italy", "Sweden", "Norway", "Finland", "France", "Belgium", "Spain");

$pdo->exec("CREATE TABLE countries (cid INTEGER, tabcd INTEGER, name VARCHAR(100), dcomment VARCHAR(100), version VARCHAR(8), versiondescription VARCHAR(100), PRIMARY KEY (cid, tabcd)) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

foreach($countries as $country)
{
	$readme = explode(";", trim(file_get_contents("$country/README.DAT")));
	$arr = explode(' ', $readme[4]);
	$charset = $arr[0];

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
	}

	fclose($csv);
}

function create_table($file, $layout)
{
	global $pdo;
	global $countries;

	$table = strtolower($file);
	$pdo->exec("CREATE TABLE $table ($layout) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;");

	foreach($countries as $country)
	{
		$readme = explode(";", trim(file_get_contents("$country/README.DAT")));
		$arr = explode(' ', $readme[4]);
		$charset = $arr[0];
		
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

create_table('LOCATIONCODES', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, allocated BOOLEAN, PRIMARY KEY (cid, tabcd, lcd)');
create_table('NAMES', 'cid INTEGER, lid INTEGER, nid INTEGER, name VARCHAR(255), ncomment TEXT, KEY cid (cid, nid)');
create_table('ADMINISTRATIVEAREA', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, class CHAR, tcd TINYINT, stcd TINYINT, nid INTEGER, pol_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('OTHERAREAS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, class CHAR, tcd TINYINT, stcd TINYINT, nid INTEGER, pol_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('ROADS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, class CHAR, tcd TINYINT, stcd TINYINT, roadnumber VARCHAR(16), rnid INTEGER, n1id INTEGER, n2id INTEGER, pol_lcd INTEGER, pes_lev INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('SEGMENTS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, class CHAR, tcd TINYINT, stcd TINYINT, roadnumber VARCHAR(16), rnid INTEGER, n1id INTEGER, n2id INTEGER, roa_lcd INTEGER, seg_lcd INTEGER, pol_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('SOFFSETS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, neg_off_lcd INTEGER, pos_off_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('POINTS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, class CHAR, tcd TINYINT, stcd TINYINT, junctionnumber VARCHAR(16), rnid INTEGER, n1id INTEGER, n2id INTEGER, pol_lcd INTEGER, oth_lcd INTEGER, seg_lcd INTEGER, roa_lcd INTEGER, inpos BOOLEAN, inneg BOOLEAN, outpos BOOLEAN, outneg BOOLEAN, presentpos BOOLEAN, presentneg BOOLEAN, diversionpos BOOLEAN, diversionneg BOOLEAN, xcoord REAL, ycoord REAL, interruptsroad INTEGER, urban BOOLEAN, PRIMARY KEY (cid, tabcd, lcd)');
create_table('POFFSETS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, neg_off_lcd INTEGER, pos_off_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
create_table('INTERSECTIONS', 'cid INTEGER, tabcd INTEGER, lcd INTEGER, int_cid INTEGER, int_tabcd INTEGER, int_lcd INTEGER, PRIMARY KEY (cid, tabcd, lcd)');
?>
