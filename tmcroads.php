<?php
// TODO: Variante für Ringe
include_once('tmcpdo.php');
include_once('tmchtml.php');

// Angabe von cid, tabcd, lcd für ein L1.*

function tmc_roadlist(){

	$cid = (int)$_REQUEST['cid'];
	$tabcd = (int)$_REQUEST['tabcd'];
	$lcd = (int)$_REQUEST['lcd'];

	if($data = find_location('roads', $cid, $tabcd, $lcd))
	{
		$is_road = true;
		$admins = array();
		$others = array();
		$roads = array();
		$segments = find_rs_segments($data);
		$psegs = find_road_points($data);
		$points = array_reduce($psegs, "array_merge", array());
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$lcd\"];);>;);out meta;");
	}
	else if($data = find_location('segments', $cid, $tabcd, $lcd))
	{
		if($data && ($data2 = find_location('soffsets', $cid, $tabcd, $lcd)))
			$data = array_merge($data, $data2);

		$admins = array();
		$others = array();
		$roads = array();
		$segments = find_rs_segments($data);
		$points = find_segment_points($data);
		$road_lcd = $points[0]['road_lcd'];
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"seg_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"road_lcd\"=\"$road_lcd\"];);>;);out meta;");

	}else{
		header("HTTP/1.0 404 Not Found");
		header("Content-type: text/plain");
		die("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.");
	}

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $opurl);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	$opdata = curl_exec($ch);

	if($opdata === FALSE)
		$opdata = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<osm version=\"0.6\" generator=\"Overpass API\">\n</osm>";

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

<table id="listroad">
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
<?php

foreach($points as $i => $point) {

	$rels_point = get_osm_rels($osmxp,$cid,$tabcd,$point['lcd']);

	if($point['presentpos'] && !$point['presentneg'])
		$point['present'] = 'positive';
	else if($point['presentneg'] && !$point['presentpos'])
		$point['present'] = 'negative';


	// Data for this Point
	echo "<tr>";
	write_main_data($point, $rels_point);
	write_relation_data($point, $rels_point);
	echo "</tr>";

	// Data for links to next Point
	// Ignore last point of segments
	if($point['pos_off_lcd'] && ($is_road || $i+1 < count($points))){
		$rels_link = get_link_rels($osmxp,$cid,$tabcd,$point['lcd'],$point['pos_off_lcd']);

		echo "<tr>";
		write_link_data($point, $rels_link);
		echo "</tr>";
	}
}
?>
</table>

</body>
</html>
<?php
}

function write_link_data($point, $rels_link){


	echo "<td/>";
	echo "<td>".get_osm_html_links(get_osm_ids($rels_link))."</td>";
	echo "<td/>";
	echo "<td>Link</td>";
	echo "<td/>";
	$first = array_shift($rels_link);

	echo get_role_field($first, "positive");
	echo get_role_field($first, "negative");
	echo get_role_field($first, "both");
}

function write_main_data($point, $rels_point){
	echo "<td><a href=\"tmcview.php?cid=" . $point['cid'] . "&amp;tabcd=" . $point['tabcd'] . "&amp;lcd=" . $point['lcd'] . "\">".$point['lcd']."</a></td>";
	echo "<td>".get_osm_html_links(get_osm_ids($rels_point))."</td>";
	echo "<td>".get_html_type($point)."</td>";
	echo "<td>".array_desc($point)."</td>";
	echo "<td>".$point['present']."</td>";
}

function write_relation_data($point, $rels_point){
	$first = array_shift($rels_point);
	echo get_role_field($first, "positive");
	echo get_role_field($first, "negative");
	echo get_role_field($first, "both");

	echo get_role_desc($first, "entry");
	echo get_role_desc($first, "exit");
	echo get_role_desc($first, "ramp");
	echo get_role_desc($first, "parking");
	echo get_role_desc($first, "fuel");
	echo get_role_desc($first, "restaurant");
}

function get_osm_html_links($ids){
	$links = array();
  	foreach($ids as $id)
		$links[] = "<a href=\"http://www.openstreetmap.org/browse/relation/$id\">$id</a>";

	return implode (", " , $links);
}

function get_osm_ids($rels){
	$ids = array_keys($rels);
	return $ids;
}

function get_html_type($point){
	
	return "<span title=\"".find_type($point)."\">".$point['class'].".".$point['tcd'].".".$point['stcd']."</span>";
}

function get_osm_rels($osmxp,$cid,$tabcd,$lcd){
	$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='$lcd']]");
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

function get_link_rels($osmxp,$cid,$tabcd,$neg_lcd,$pos_lcd){
	$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:link']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='neg_lcd'][@v='$neg_lcd']][tag[@k='pos_lcd'][@v='$pos_lcd']]");

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

function get_role_field($rel, $role) {
	$roles = get_roles($rel, $role);



	if($roles>0)
		return "<td class=\"correct\">found</td>";
	else {
		$sum = get_roles($rel, "positive") + get_roles($rel, "negative") + get_roles($rel, "both");
		if($sum == 0)
			return "<td class=\"missing\">missing</td>";			
		else if($role != "both" && !get_roles($rel, "both"))
			return "<td class=\"ugly\">missing?</td>";
		else
			return "<td/>";
	}
}

// require: false, true, both, positive, negative
function get_role_desc($rel, $role, $require=false){
	$rolesP = get_roles($rel, "positive:".$role);
	$rolesN = get_roles($rel, "negative:".$role);
	$rolesB = get_roles($rel, "both:".$role);

	$both = $rolesB + ($rolesP * $rolesN); 

	$class = "";
	if($both) {
		// present in both directions
		$class = "correct";
		$text = "found";
		if($require == "negative"){
			$class = "ugly";
			$text .= ", neg not needed";
		}
		if($require == "positive"){
			$class = "ugly";
			$text .= ", neg not needed";
		}
	} else if($rolesP && !$rolesN){
		// Only positive
		$class = "correct";
		$text = "pos only";
		if($require == "negative"){
			$class = "ugly";
			$text .= ", not needed";
		}
		if($require == "negative" || $require == "both"){
			$class = "missing";
			$text .= ", neg missing";
		}

	} else if($rolesN && !$rolesP){
		// Only negative
		$class = "correct";
		$text = "neg only";

		if($require == "positive"){
			$class = "ugly";
			$text .= ", not needed";
		}
		if($require == "positive" || $require == "both"){
			$class = "missing";
			$text .= ", pos missing";
		}

	} else if($require)
			$class = $text = "missing";
		
	return "<td class=\"$class\">$text</td>";
}

function get_roles($rel, $role){
	$count = 0;

	// TODO: startWith both
	if($role == "both")
		$roles += get_roles($rel, "");

	foreach($rel['member'] as $member)
		if($member['role'] == $role)
			$count++;

	return $count;
}

tmc_roadlist();

?>

