<?php
include_once('config.php');

$dblayout = array(
	'types' => array(
		'class CHAR(1)',
		'tcd TINYINT(4)',
		'stcd TINYINT(4)',
		'tdesc VARCHAR(255)',
		'PRIMARY KEY (class, tcd, stcd)'
	),
	'countries' => array(
		'cid INTEGER PRIMARY KEY',
		'ecc CHAR(2)',
		'ccd CHAR(1)',
		'name VARCHAR(100)'
	),
	'locationdatasets' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'dcomment VARCHAR(100)',
		'version VARCHAR(8)',
		'versiondescription VARCHAR(100)',
		'PRIMARY KEY (cid, tabcd)'
	),
	'languages' => array(
		'cid INTEGER',
		'lid INTEGER',
		'language VARCHAR(100)',
		'PRIMARY KEY (cid, lid)'
	),
	'locationcodes' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'allocated BOOLEAN',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'names' => array(
		'cid INTEGER',
		'lid INTEGER',
		'nid INTEGER',
		'name VARCHAR(255)',
		'ncomment VARCHAR(255)',
		'KEY cnid (cid, nid)',
		'PRIMARY KEY (cid, lid, nid)'
	),
	'administrativearea' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'class CHAR',
		'tcd TINYINT',
		'stcd TINYINT',
		'nid INTEGER',
		'pol_lcd INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'otherareas' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'class CHAR',
		'tcd TINYINT',
		'stcd TINYINT',
		'nid INTEGER',
		'pol_lcd INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'roads' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'class CHAR',
		'tcd TINYINT',
		'stcd TINYINT',
		'roadnumber VARCHAR(16)',
		'rnid INTEGER',
		'n1id INTEGER',
		'n2id INTEGER',
		'pol_lcd INTEGER',
		'pes_lev INTEGER',
		'rdid INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'segments' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'class CHAR',
		'tcd TINYINT',
		'stcd TINYINT',
		'roadnumber VARCHAR(16)',
		'rnid INTEGER',
		'n1id INTEGER',
		'n2id INTEGER',
		'roa_lcd INTEGER',
		'seg_lcd INTEGER',
		'pol_lcd INTEGER',
		'rdid INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'soffsets' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'neg_off_lcd INTEGER',
		'pos_off_lcd INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'points' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'class CHAR',
		'tcd TINYINT',
		'stcd TINYINT',
		'junctionnumber VARCHAR(16)',
		'rnid INTEGER',
		'n1id INTEGER',
		'n2id INTEGER',
		'pol_lcd INTEGER',
		'oth_lcd INTEGER',
		'seg_lcd INTEGER',
		'roa_lcd INTEGER',
		'inpos BOOLEAN',
		'inneg BOOLEAN',
		'outpos BOOLEAN',
		'outneg BOOLEAN',
		'presentpos BOOLEAN',
		'presentneg BOOLEAN',
		'diversionpos BOOLEAN',
		'diversionneg BOOLEAN',
		'xcoord REAL',
		'ycoord REAL',
		'interruptsroad INTEGER',
		'urban BOOLEAN',
		'jnid INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'poffsets' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'neg_off_lcd INTEGER',
		'pos_off_lcd INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	),
	'intersections' => array(
		'cid INTEGER',
		'tabcd INTEGER',
		'lcd INTEGER',
		'int_cid INTEGER',
		'int_tabcd INTEGER',
		'int_lcd INTEGER',
		'PRIMARY KEY (cid, tabcd, lcd)'
	)
);

$pdo = new PDO($config_pdo_connection, $config_pdo_write_user, $config_pdo_write_password, $config_pdo_attributes);

foreach($dblayout as $table => $layout)
{
	$query = "CREATE TABLE $table ($layout) DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	$result = $pdo->exec($query);
	echo "$result <= $query\n";
	if($result === false)
		print_r($pdo->errorInfo());
}
?>
