<?php
include_once("tmcpdo.php");
include_once("tmchtml.php");

function tmc_countries()
{
	global $pdo;
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC Data Viewer</title>
</head>
<body>
<h1>Country IDs</h1>
<table class="tmclist">
<tr><th>Name</th><th>Country ID</th><th>Table Code</th><th>Version</th></tr>
<?php
	$result = $pdo->query("SELECT * FROM countries ORDER BY name");
	while($data = $result->fetch(PDO::FETCH_ASSOC))
		echo "<tr><td><a href=\"tmcview.php?cid=" . $data['cid'] . "&amp;tabcd=" . $data['tabcd'] . "\">" . $data['name'] . "</a></td><td>" . $data['cid'] . "</td><td>" . $data['tabcd'] . "</td><td>" . $data['version'] . "</td></tr>\n";
?>
</table>
<?php form_search(); ?>
</body>
</html>
<?php
}
?>
