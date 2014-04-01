<?php
include_once("tmcpdo.php");
include_once("tmchtml.php");

function tmc_types()
{
	global $pdo;

	$cid = (int)$_REQUEST['cid'];
	$tabcd = (int)$_REQUEST['tabcd'];
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC types for country cid = <?php echo $cid; ?> and tabcd = <?php echo $tabcd; ?></title>
</head>
<body>
<h1>TMC types for country cid = <?php echo $cid; ?> and tabcd = <?php echo $tabcd; ?></h1>
<table>
<?php
	$result = $pdo->query("SELECT * FROM types ORDER BY class, tcd, stcd ASC");
	while($data = $result->fetch(PDO::FETCH_ASSOC))
	{
		echo "<tr><td><a href=\"tmcview.php?cid=$cid&amp;tabcd=$tabcd&amp;class=" . $data['class'] . "&amp;tcd=" . $data['tcd'] . "&amp;stcd=" . $data['stcd'] . "\">" . $data['class'] . $data['tcd'] . "." . $data['stcd'] . "</a></td><td>" . $data['desc'] . "</td></tr>\n";
	}
?>
</table>
<?php form_search(); ?>
</body>
</html>
<?php
}
?>
