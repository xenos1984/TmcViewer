<?php
include_once("tmcpdo.php");
include_once("tmchtml.php");

function tmc_tables()
{
	global $pdo;

	$cid = (int)$_REQUEST['cid'];

	$result = $pdo->query("SELECT COUNT(DISTINCT tabcd) FROM locationcodes WHERE cid = '$cid'");
	$count = $result->fetch(PDO::FETCH_COLUMN);

	$result = $pdo->query("SELECT DISTINCT(tabcd) FROM locationcodes WHERE cid = '$cid' ORDER BY tabcd");
	if($count == 1)
	{
		$tabcd = $result->fetch(PDO::FETCH_COLUMN);
		header("HTTP/1.0 302 Found");
		header("Location: " . $_SERVER['REQUEST_URI'] . "&tabcd=$tabcd");
	}
	else
	{
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC tables for country cid = <?php echo $cid; ?></title>
</head>
<body>
<h1>TMC tables for country cid = <?php echo $cid; ?></h1>
<ul>
<?php
	while($tabcd = $result->fetch(PDO::FETCH_COLUMN))
		echo "<li><a href=\"tmcview.php?cid=$cid&amp;tabcd=$tabcd\">$cid</a></li>\n";
?>
</ul>
<?php form_search(); ?>
</body>
</html>
<?php
	}
}
?>
