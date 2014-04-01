<?php
include_once("tmcpdo.php");
include_once("tmchtml.php");

function tmc_locations()
{
	global $pdo;

	$cid = (int)$_REQUEST['cid'];
	$tabcd = (int)$_REQUEST['tabcd'];
	$tcd = (int)$_REQUEST['tcd'];
	$stcd = (int)$_REQUEST['stcd'];
	$class = strtoupper($_REQUEST['class']);
	if(!in_array($class, array('A', 'L', 'P')))
		$class = 'P';
	$type = "$class$tcd.$stcd";

	if($class == 'P')
	{
		$table = 'points';
		$order = 'junctionnumber, rnid, n1id, n2id';
	}
	else if($class == 'L')
	{
		if(($tcd == 3) || ($tcd == 4))
			$table = 'segments';
		else
			$table = 'roads';
		$order = 'roadnumber, rnid, n1id, n2id';
	}
	else
	{
		if(($tcd == 5) || ($tcd == 6) || ($tcd == 12))
			$table = 'otherareas';
		else
			$table = 'administrativearea';
		$order = 'nid';
	}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC locations of type <?php echo $type; ?> for country cid = <?php echo $cid; ?> and tabcd = <?php echo $tabcd; ?></title>
</head>
<body>
<h1>TMC locations of type <?php echo $type; ?> for country cid = <?php echo $cid; ?> and tabcd = <?php echo $tabcd; ?></h1>
<table>
<?php
	if($result = $pdo->query("SELECT * FROM $table WHERE cid = '$cid' AND tabcd = '$tabcd' AND class = '$class' AND tcd = '$tcd' AND stcd = '$stcd' ORDER BY $order ASC"))
		write_list($result);
?>
</table>
<?php form_search(); ?>
</body>
</html>
<?php
}
?>
