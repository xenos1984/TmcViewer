<?php
include_once('tmcpdo.php');
include_once('tmchtml.php');

$roadlist_status = array('error' => 0, 'missing' => 0, 'ok' => 0, 'link_error' => 0, 'link_missing' => 0, 'link_ok' => 0);
$current_status = array('error' => 0, 'missing' => 0);

// read/write the status of displaying roles
$show_all_roles = (isset($_COOKIE['tmc_show_all_roles']) && $_COOKIE['tmc_show_all_roles']);
if(isset($_GET['showall'])) {
	$show_all_roles = (boolean)$_GET['showall'];
	setcookie('tmc_show_all_roles', $_GET['showall'], time()+60*60*24*30);
}

function tmc_roadlist()
{
	global $current_status;
	global $roadlist_status;

	$error = false;

	$cid = (int)$_REQUEST['cid'];
	$tabcd = (int)$_REQUEST['tabcd'];
	$lcd = (int)$_REQUEST['lcd'];

	if($data = find_location('roads', $cid, $tabcd, $lcd))
	{
		$is_road = true;
		$road = $data;
		$segments = find_rs_segments($data);
		$psegs = find_road_points($data);
		$points = array_reduce($psegs, "array_merge", array());
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$lcd\"];);>;);out meta;");
	}
	else if($data = find_location('segments', $cid, $tabcd, $lcd))
	{
		if($data && ($data2 = find_location('soffsets', $cid, $tabcd, $lcd)))
			$data = array_merge($data, $data2);

		$is_road = false;
		$road = find_road($data);
		$segments = find_rs_segments($road);
		$points = find_segment_points($data);
		$road_lcd = $road['lcd'];
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"seg_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$road_lcd\"];);>;);out meta;");

	}
	else
	{
		header("HTTP/1.0 404 Not Found");
		header("Content-type: text/plain");
		die("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.");
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $opurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 45);
	$opdata = curl_exec($ch);

	if($opdata === false)
	{
		$opdata = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<osm version=\"0.6\" generator=\"Overpass API\">\n</osm>";
		$error = true;
	}

	curl_close($ch);

	$osm = new DOMDocument;
	$osm->formatOutput = false;
	$osm->loadXML($opdata);
	$osmxp = new DOMXPath($osm);

?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC road/segment status <?php echo "$cid:$tabcd:$lcd - " . array_desc($data); ?></title>

</head>
<body>
<h1>Status for <?php echo "$cid:$tabcd:$lcd - " . array_desc($data); ?></h1>

<?php
if($error)
{
?>
<div class="error">
	Error: No response of overpass-api! Retry later.
</div>
<?php
}
?>

<h3>Road:</h3>
<table>
<?php write_line($road); ?>
</table>
<h3>Segments:</h3>
<table>
<?php write_table($segments); ?>
</table>

<table class="roads" style="margin-bottom: 10em;">
<thead>
<tr>
<th rowspan="2">LCD</th>
<th rowspan="2">OSM</th>
<th rowspan="2">Type</th>
<th rowspan="2">Name</th>
<th rowspan="2">present</th>

<th colspan="10">Roles</th>
</tr>

<tr>
<th>pos</th>
<th>neg</th>
<th>both</th>
<th>entry</th>
<th>exit</th>
<th>ramp</th>
<th>parking</th>
<th>fuel</th>
<th>restaurant</th>
</tr>
</thead>
<tbody>
<?php

foreach($points as $i => $point)
{
	$rels_point = get_osm_rels($osmxp, $cid, $tabcd, $point['lcd']);

	if($point['presentpos'] && !$point['presentneg'])
		$point['present'] = 'positive';
	else if($point['presentneg'] && !$point['presentpos'])
		$point['present'] = 'negative';
	else
		$point['present'] = '';

	// Data for this Point
	$current_status = array('error' => 0, 'missing' => 0);
	echo "<tr>";
	write_main_data($point, $rels_point);
	write_relation_data($point, $rels_point);
	echo "</tr>";

	if($current_status['error'])
		$roadlist_status['error']++;
	else if($current_status['missing'])
		$roadlist_status['missing']++;
	else
		$roadlist_status['ok']++;

	// Data for links to next Point
	// Ignore last point of segments
	if($point['pos_off_lcd'] && ($is_road || $i+1 < count($points)))
	{
		$rels_link = get_link_rels($osmxp, $cid, $tabcd, $point['lcd'], $point['pos_off_lcd']);
		$current_status = array('error' => 0, 'missing' => 0);
		echo "<tr>";
		write_link_data($point, $rels_link);
		echo "</tr>";
		if($current_status['error'])
			$roadlist_status['link_error']++;
		else if($current_status['missing'])
			$roadlist_status['link_missing']++;
		else
			$roadlist_status['link_ok']++;
	}
}
?>
</thead>
<tfoot>
<tr>
<td colspan="14"><?php write_relation_status($data); ?></td>
</tr>
</tfoot>
</table>

<div style="position:fixed;bottom:0px;width:100%;clear:both;background-color:white;">
<table class="roads" style="width:100%">
<tr>
<td colspan="12">
<h3>Status:</h3>
</td>
</tr>
<tr>
<td colspan="6">
Points:<br/>
OK: <?php echo (int)$roadlist_status['ok']; ?> <br/>
Missing elements: <?php echo (int)$roadlist_status['missing']; ?> <br/>
No Relation: <?php echo (int)$roadlist_status['error']; ?>
</td>
<td colspan="6">
Links:<br/>
OK: <?php echo (int)$roadlist_status['link_ok']; ?> <br/>
Missing elements: <?php echo (int)$roadlist_status['link_missing']; ?> <br/>
No Relation: <?php echo (int)$roadlist_status['link_error']; ?>
</td>
</table>
</div>

</body>
</html>
<?php
}

function write_link_data($point, $rels_link)
{
	global $current_status;
	global $show_all_roles;

	echo "<td/>";

	$links = get_osm_html_links(get_osm_ids($rels_link));
	if($links)
		echo "<td>".$links."</td>";
	else {
		$current_status['error'] = true;
		echo "<td class=\"missing\"> </td>";
	}

	echo "<td/>";
	echo "<td>Link</td>";
	echo "<td/>";
	$first = array_shift($rels_link);

	if($links || $show_all_roles)
	{
		echo get_role_field($first, "positive");
		echo get_role_field($first, "negative");
		echo get_role_field($first, "both");
		echo "<td colspan=\"6\"/>";
	} else
		echo "<td colspan=\"9\"/>";
}

function write_main_data($point, $rels_point)
{
	global $current_status;

	echo "<td><a href=\"tmcview.php?cid=" . $point['cid'] . "&amp;tabcd=" . $point['tabcd'] . "&amp;lcd=" . $point['lcd'] . "\">".$point['lcd']."</a></td>";

	$links = get_osm_html_links(get_osm_ids($rels_point));
	if($links)
		echo "<td>".$links."</td>";
	else {
		echo "<td class=\"missing\"> </td>";
		$current_status['error'] = true;
	}

	echo "<td>".get_html_type($point)."</td>";
	echo "<td>".array_desc($point)."</td>";
	echo "<td>".$point['present']."</td>";
}

function write_relation_data($point, $rels_point)
{
	global $show_all_roles;

	if(!count($rels_point) && !$show_all_roles)
		return;

	$first = array_shift($rels_point);
	echo get_role_field($first, "positive");
	echo get_role_field($first, "negative");
	echo get_role_field($first, "both");

	echo get_role_desc($first, "entry", get_role_requirement("entry", $point));
	echo get_role_desc($first, "exit", get_role_requirement("exit", $point));
	echo get_role_desc($first, "ramp", get_role_requirement("ramp", $point));
	echo get_role_desc($first, "parking",  get_role_requirement("parking", $point));
	echo get_role_desc($first, "fuel",  get_role_requirement("fuel", $point));
	echo get_role_desc($first, "restaurant",  get_role_requirement("restaurant", $point));
}

/**
 * This function writes the link to change the display-option for the roles.
 * You can activate or deactivate the status for the roles of links or points 
 * without an existing relation. If it is activate it can help to find out 
 * which roles you have to map in the new realtion. 
 */
function write_relation_status($point)
{
	global $show_all_roles;

	if($show_all_roles)
		// activated
		$text = "deactivate all roles.";
	else
		// deactivated
		$text = "activate all roles.";

	echo "<a href=\"tmcroads.php?cid=" . $point['cid'] . "&amp;tabcd=" . $point['tabcd'] . "&amp;lcd=" . $point['lcd'] . "&amp;showall=".(int)!$show_all_roles."\">$text</a>";
}

function get_osm_html_links($ids)
{
	$links = array();
  	foreach($ids as $id)
		$links[] = "<a href=\"http://www.openstreetmap.org/browse/relation/$id\">$id</a>";

	return implode (", " , $links);
}

function get_osm_ids($rels)
{
	$ids = array_keys($rels);
	return $ids;
}

function get_html_type($point)
{
	
	return "<span title=\"".find_type($point)."\">".$point['class'].".".$point['tcd'].".".$point['stcd']."</span>";
}

function get_osm_rels($osmxp,$cid,$tabcd,$lcd)
{
	$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='$lcd']]");
	$keys = array();
	$rels = array();

	foreach($osmrels as $osmrel)
	{
		$rel = array();
		$osmtags = $osmxp->query("tag", $osmrel);
		foreach($osmtags as $osmtag)
			$rel[$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');

		$rel['member'] = array();
		$osmmembers = $osmxp->query("member", $osmrel);
		foreach($osmmembers as $member)
			$rel['member'][] = array('role'=>$member->getAttribute('role'),'type'=>$member->getAttribute('type'),'id'=>$member->getAttribute('ref'));

		$rels[$osmrel->getAttribute('id')] = $rel;
		$keys = array_merge($keys, array_keys($rel));
	}

	return $rels;
}

function get_link_rels($osmxp,$cid,$tabcd,$neg_lcd,$pos_lcd)
{
	$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:link']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='neg_lcd'][@v='$neg_lcd']][tag[@k='pos_lcd'][@v='$pos_lcd']]");
	$keys = array();
	$rels = array();

	foreach($osmrels as $osmrel)
	{
		$rel = array();
		$osmtags = $osmxp->query("tag", $osmrel);
		foreach($osmtags as $osmtag)
			$rel[$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');

		$rel['member'] = array();
		$osmmembers = $osmxp->query("member", $osmrel);
		foreach($osmmembers as $member)
			$rel['member'][] = array('role'=>$member->getAttribute('role'),'type'=>$member->getAttribute('type'),'id'=>$member->getAttribute('ref'));

		$rels[$osmrel->getAttribute('id')] = $rel;
		$keys = array_merge($keys, array_keys($rel));
	}

	return $rels;
}

function get_role_field($rel, $role)
{
	global $current_status;

	$roles = get_roles($rel, $role);

	if($roles>0)
		return "<td class=\"correct\">found</td>";
	else {
		$sum = get_roles($rel, "positive") + get_roles($rel, "negative") + get_roles($rel, "both");
		if($sum == 0){
			if($role === "positive") {
				$current_status['missing'] = true;
				return "<td class=\"missing\" colspan=\"3\">missing</td>";
			}
		} else if($role != "both" && !get_roles($rel, "both")){
			$current_status['missing'] = true;
			return "<td class=\"ugly\">missing?</td>";
		} else
			return "<td/>";
	}
}

// require: false, both, positive, negative
function get_role_desc($rel, $role, $require=false)
{
	global $current_status;

	$rolesP = get_roles($rel, "positive:".$role);
	$rolesN = get_roles($rel, "negative:".$role);
	$rolesB = get_roles($rel, "both:".$role);

	$both = $rolesB + ($rolesP * $rolesN); 

	$class = $text = "";
	if($both)
	{
		// present in both directions
		$class = "correct";
		$text = "found";
		if($require === "negative")
		{
			$class = "ugly";
			$text .= ", neg not needed";
		}
		if($require === "positive")
		{
			$class = "ugly";
			$text .= ", pos not needed";
		}
	}
	else if($rolesP && !$rolesN)
	{
		// Only positive
		$class = "correct";
		$text = "pos only";
		if($require === "negative")
		{
			$class = "ugly";
			$text .= ", not needed";
		}
		if($require === "negative" || $require === "both" || $require === true)
		{
			$class = "missing";
			$text .= ", neg missing";
		}
	}
	else if($rolesN && !$rolesP)
	{
		// Only negative
		$class = "correct";
		$text = "neg only";

		if($require === "positive")
		{
			$class = "ugly";
			$text .= ", not needed";
		}
		if($require === "positive" || $require === "both" || $require === true)
		{
			$class = "missing";
			$text .= ", pos missing";
		}
	}
	else if($require)
			$class = $text = "missing";
	
	if($class != "correct" && $class != "")
		$current_status['missing'] = true;
	
	return "<td class=\"$class\">$text</td>";
}

function get_roles($rel, $role)
{
	$count = 0;

	if(substr($role,0,4) === "both")
		$count += get_roles($rel, substr($role,4));
	if(isset($rel['member']))
		foreach($rel['member'] as $member)
			if(trim($member['role']) == trim($role))
				$count++;

	return $count;
}

$role_req = array(
		'P.1.1' => array('exit','entry'),                // AK
		'P.1.2' => array('exit','entry'),                // AD
		'P.1.3' => array('exit','entry'),                // AS
		'P.1.4' => array('exit'),                        // Ausfahrt
		'P.1.5' => array('entry'),                       // Einfahrt

		'P.3.1' => array(),                              // Tunnel
		'P.3.2' => array(),                              // Brücke
		'P.3.3' => array('parking','fuel','restaurant'), // Raststätte
		'P.3.4' => array('parking'),                     // Rastplatz
		'P.3.7' => array('parking'),                     // park and ride
		'P.3.8' => array('parking'),                     // Parkplatz
		'P.3.9' => array('parking'),                     // Parkplatz + Kiosk
		'P.3.10' => array('parking'),                    // Parkplatz + Kiosk + WC
		'P.3.11' => array('parking','fuel'),             // Tankstelle
		'P.3.12' => array('parking','fuel'),             // Tankstelle + Kiosk
		'P.3.21' => array('parking'),                    // Parkhaus
		'P.3.22' => array('parking'),                    // Tiefgarage
	);

function get_role_requirement($role, $point)
{
	global $role_req;

	$type = $point['class'].".".$point['tcd'].".".$point['stcd'];

	$req = isset($role_req[$type]) && in_array($role, $role_req[$type]);	

	if($req && $point['present'])
		// Only for one direction
		$req = $point['present'];
	
	if($req)
		// Check exit + entry direction if required
		if($role === 'exit') 
		{
			if($point['outpos'] && $point['outneg'])
				$req = true;
			else if($point['outpos'])
				$req = 'positive';
			else if($point['outneg'])
				$req = 'negative';
			else
				$req = false;
		}
		else if($role === 'entry')
		{
			if($point['inpos'] && $point['inneg'])
				$req = true;
			else if($point['inpos'])
				$req = 'positive';
			else if($point['inneg'])
				$req = 'negative';
			else
				$req = false;
		}

	return $req;
}

tmc_roadlist();

?>
