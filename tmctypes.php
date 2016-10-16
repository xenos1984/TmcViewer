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
<?php
	$tables = array(
		'administrativearea' => "Administrative areas",
		'otherareas' => "Other areas",
		'roads' => "Roads",
		'segments' => "Segments",
		'points' => "Points"
	);

	foreach($tables as $table => $tabname)
	{
		echo "<h2>$tabname</h2>\n";
		echo "<table>\n";
		echo "<tr><th>Type</th><th>Description</th><th>Entries</th></tr>\n";

		$stmt = $pdo->prepare("SELECT types.*, COUNT(*) FROM types, $table WHERE types.class = $table.class AND types.tcd = $table.tcd AND types.stcd = $table.stcd AND $table.cid = :cid AND $table.tabcd = :tabcd GROUP BY types.class, types.tcd, types.stcd;");
		$stmt->bindValue('cid', $cid, PDO::PARAM_INT);
		$stmt->bindValue('tabcd', $tabcd, PDO::PARAM_INT);
		$stmt->execute();

		while($data = $stmt->fetch(PDO::FETCH_ASSOC))
			echo "<tr><td><a href=\"tmcview.php?cid=$cid&amp;tabcd=$tabcd&amp;class={$data['class']}&amp;tcd={$data['tcd']}&amp;stcd={$data['stcd']}\">{$data['class']}{$data['tcd']}.{$data['stcd']}</a></td><td>{$data['tdesc']}</td><td>{$data['COUNT(*)']}</td></tr>\n";

		echo "</table>\n";
	}
?>
<?php form_search(); ?>
</body>
</html>
<?php
}
?>
