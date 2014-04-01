<?php
include_once('tmcpdo.php');
include_once('tmchtml.php');

function tmc_search()
{
	global $pdo;
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC Search</title>
</head>
<body>
<?php
	$limit = 50;

	echo "<h1>Search for " . trim($_REQUEST['q']) . "</h1>\n";

	$q = $pdo->quote('%' . trim($_REQUEST['q']) . '%');
	$stmt = "SELECT * FROM names WHERE ";
	$cnt = "SELECT COUNT(*) FROM names WHERE ";

	if(array_key_exists('cid', $_REQUEST))
	{
		$cid = (int)$_REQUEST['cid'];
		$stmt .= "cid = '$cid' AND ";
		$cnt .= "cid = '$cid' AND ";
	}

	if(array_key_exists('start', $_REQUEST))
		$start = (int)$_REQUEST['start'];
	else
		$start = 0;

	$stmt .= "LOWER(name) LIKE BINARY LOWER($q) ORDER BY name LIMIT $start, $limit";
	$cnt .= "LOWER(name) LIKE BINARY LOWER($q)";

	if($result = $pdo->query($cnt))
	{
		$count = $result->fetch(PDO::FETCH_COLUMN);
		echo "<p>$count hits found.";
		if($count > $limit)
			echo " Displaying " . ($start + 1) . " to " . ($start + $limit) . ".";
		else
			$start = 0;
		echo "</p>\n";
	}
	else
		$count = 0;

	if($count && ($result = $pdo->query($stmt)))
	{
		echo "<ul>\n";
		while($name = $result->fetch(PDO::FETCH_ASSOC))
		{
			echo "<li><b>" . $name['name'] . "</b>\n";
			if($result2 = $pdo->query("SELECT * FROM administrativearea WHERE cid = '" . $name['cid'] . "' AND nid = '" . $name['nid'] . "'"))
				write_list($result2);
			if($result2 = $pdo->query("SELECT * FROM otherareas WHERE cid = '" . $name['cid'] . "' AND nid = '" . $name['nid'] . "'"))
				write_list($result2);
			if($result2 = $pdo->query("SELECT * FROM roads WHERE cid = '" . $name['cid'] . "' AND (rnid = '" . $name['nid'] . "' OR n1id = '" . $name['nid'] . "' OR n2id = '" . $name['nid'] . "')"))
				write_list($result2);
			if($result2 = $pdo->query("SELECT * FROM segments WHERE cid = '" . $name['cid'] . "' AND (rnid = '" . $name['nid'] . "' OR n1id = '" . $name['nid'] . "' OR n2id = '" . $name['nid'] . "')"))
				write_list($result2);
			if($result2 = $pdo->query("SELECT * FROM points WHERE cid = '" . $name['cid'] . "' AND (rnid = '" . $name['nid'] . "' OR n1id = '" . $name['nid'] . "' OR n2id = '" . $name['nid'] . "')"))
				write_list($result2);
			echo "</li>\n";
		}
		echo "</ul>\n";
	}

	form_search();
?>
</body>
</html>
<?php
}
?>
