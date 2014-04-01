<?php
include_once("tmcutils.php");
include_once("tmcpdo.php");

function write_line($data)
{
	echo "<tr><td><a href=\"tmcview.php?cid=" . $data['cid'] . "&amp;tabcd=" . $data['tabcd'] . "&amp;lcd=" . $data['lcd'] . "\">" . $data['cid'] . ":" . $data['tabcd'] . ":" . $data['lcd'] . "</a></td><td>" . $data['class'] . $data['tcd'] . "." . $data['stcd'] . "</td><td>" . array_desc($data) . "</td></tr>\n";
}

function write_table($array)
{
	echo "<table class=\"tmclist\">\n";
	foreach($array as $data)
		write_line($data);
	echo "</table>\n";
}

function write_list($result)
{
	echo "<table class=\"tmclist\">\n";
	while($data = $result->fetch(PDO::FETCH_ASSOC))
	{
		$data = find_names($data);
		write_line($data);
	}
	echo "</table>\n";
}

function write_link($id, $name, $links)
{
	if(array_key_exists($id, $links) && ($data = $links[$id]))
		echo "<tr><td>$name</td><td><a href=\"tmcview.php?cid=" . $data['cid'] . "&amp;tabcd=" . $data['tabcd'] . "&amp;lcd=" . $data['lcd'] . "\">" . $data['cid'] . ":" . $data['tabcd'] . ":" . $data['lcd'] . "</a></td><td>" . $data['class'] . $data['tcd'] . "." . $data['stcd'] . "</td><td>" . array_desc($data) . "</td></tr>\n";
}

function form_search()
{
	echo "<form method=\"GET\" action=\"tmcview.php\">\n";
	echo "<p>";
	if(array_key_exists('cid', $_REQUEST))
		echo "<input type=\"hidden\" name=\"cid\" value=\"" . (int)$_REQUEST['cid'] . "\"/>";
	echo "<input type=\"text\" name=\"q\"/><input type=\"submit\" value=\"Search\"/></p>\n";
	echo "</form>\n";
}
?>
