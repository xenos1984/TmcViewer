<?php
include_once("tmcpdo.php");
include_once("tmchtml.php");
include_once("tmcjson.php");
include_once("tmcosm.php");

function tmc_location()
{
	global $pdo;

	$cid = (int)$_REQUEST['cid'];
	$tabcd = (int)$_REQUEST['tabcd'];
	$lcd = (int)$_REQUEST['lcd'];

	if(!($alloc = find_location('locationcodes', $cid, $tabcd, $lcd)))
	{
		header("HTTP/1.0 404 Not Found");
		header("Content-type: text/plain");
		die("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.");
	}
	if(!$alloc['allocated'])
	{
		header("HTTP/1.0 404 Not Found");
		header("Content-type: text/plain");
		die("Location code $lcd is not allocated in country $cid table $tabcd.");
	}

	if(($data = find_location('otherareas', $cid, $tabcd, $lcd)) || ($data = find_location('administrativearea', $cid, $tabcd, $lcd)))
	{
		$admins = find_area_admins($data);
		$others = find_area_others($data);
		$roads = find_area_roads($data);
		$segments = find_area_segments($data);
		$points = find_area_points($data);
		$jsondata = json_points($points);
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"area_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:area\"][\"table\"=\"$cid:$tabcd\"][\"lcd\"=\"$lcd\"];rel(r););>;);out meta;");
	}
	else if($data = find_location('roads', $cid, $tabcd, $lcd))
	{
		$admins = array();
		$others = array();
		$roads = array();
		$segments = find_rs_segments($data);
		$psegs = find_road_points($data);
		$points = array_reduce($psegs, "array_merge", array());
		if($data['tcd'] == 2)
			$jsondata = json_ring($psegs, $data);
		else
			$jsondata = json_road($psegs, $data);
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
		$jsondata = json_segment($points, $data);
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("(relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"seg_lcd\"=\"$lcd\"];>;);out meta;");
	}
	else if($data = find_location('points', $cid, $tabcd, $lcd))
	{
		if($data && ($data2 = find_location('poffsets', $cid, $tabcd, $lcd)))
			$data = array_merge($data, $data2);
/*
		$admins = array();
		$others = array();
		$roads = array();
		$segments = array();
		$points = array();*/
		$inters = find_inter($data);

		$pos = (array_key_exists('pos_off_lcd', $data) ? find_location('points', $cid, $tabcd, $data['pos_off_lcd']) : 0);
		$neg = (array_key_exists('neg_off_lcd', $data) ? find_location('points', $cid, $tabcd, $data['neg_off_lcd']) : 0);

		if($pos && $neg)
			$angle = calc_angle($neg, $data, $pos);
		else if($pos && !$neg)
			$angle = calc_angle($data, $data, $pos);
		else if($neg && !$pos)
			$angle = calc_angle($neg, $data, $data);
		else
			$angle = false;

		$jsondata = json_point($data, $angle);
		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("((relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"][\"lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"pos_lcd\"=\"$lcd\"];relation[\"type\"=\"tmc:link\"][\"table\"=\"$cid:$tabcd\"][\"neg_lcd\"=\"$lcd\"];);>;);out meta;");
	}
	else
	{
		header("HTTP/1.0 404 Not Found");
		header("Content-type: text/plain");
		die("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.");
	}

	$links = find_links($data);

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
	$osmdata = json_osm($osm);

	if($jsondata['type'] == 'Feature')
	{
		$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='" . $jsondata['properties']['lcd'] . "']]");
		$jsondata['properties']['osm'] = $osmrels->length;
	}
	else if($jsondata['type'] == 'FeatureCollection')
	{
		foreach($jsondata['features'] as $index => $feature)
		{
			$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='" . $feature['properties']['lcd'] . "']]");
			$jsondata['features'][$index]['properties']['osm'] = $osmrels->length;
		}
	}
?>
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<link rel="stylesheet" type="text/css" href="tmc.css"/>
<title>TMC location <?php echo "$cid:$tabcd:$lcd - " . array_desc($data); ?></title>
<script src="http://www.openlayers.org/api/OpenLayers.js"></script>
<script src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>
<script src="tmcview.js"></script>
<script type="text/javascript">
osmdata = <?php echo json_string($osmdata); ?>;
tmcdata = <?php echo json_string($jsondata); ?>;
</script>
</head>
<body onload="init();">
<div id="map" style="position: fixed; top: 12px; bottom: 12px; left: 480px; right: 12px"></div>
<div id="list" style="position: absolute; left: 12px; top: 12px; bottom: 12px; width: 456px; overflow: auto">
<?php
	echo "<h1>Location Code $cid:$tabcd:$lcd</h1>\n";
	echo "<iframe id=\"josm\" name=\"josm\" src=\"about:blank\"></iframe>\n";

	echo "<h3>Summary and tools</h3>\n";
	echo "<ul>\n";
	if($data['class'] == 'P')
	{
		if($data['junctionnumber'])
			echo "<li>Junction number: " . $data['junctionnumber'] . "</li>\n";
		if($data['rnid'])
			echo "<li>Road name: " . $data['rnid'] . "</li>\n";
		if($data['n1id'])
			echo "<li>Name: " . $data['n1id'] . "</li>\n";
		if($data['n2id'])
			echo "<li>Intersects: " . $data['n2id'] . "</li>\n";
	}
	else if($data['class'] == 'L')
	{
		if($data['roadnumber'])
			echo "<li>Number: " . $data['roadnumber'] . "</li>\n";
		if($data['rnid'])
			echo "<li>Name: " . $data['rnid'] . "</li>\n";
		if($data['n1id'])
			echo "<li>From: " . $data['n1id'] . "</li>\n";
		if($data['n2id'])
			echo "<li>To: " . $data['n2id'] . "</li>\n";
	}
	else if($data['class'] == 'A')
	{
		if($data['nid'])
			echo "<li>Name: " . $data['nid'] . "</li>\n";
	}
	echo "<li>Class: " . $data['class'] . $data['tcd'] . "." . $data['stcd'] . " " . find_type($data) . "</li>\n";
	echo "<li><a href=\"tmc2gpx.php?cid=$cid&amp;tabcd=$tabcd&amp;lcd=$lcd\">Download as GPX</a></li>\n";
	echo "<li><a href=\"javascript:load_and_zoom();\">Load current map into editor</a></li>\n";

	$oldtag = "[\"TMC:cid_$cid:tabcd_$tabcd:LocationCode\"=\"$lcd\"]";
	$oldurl = rawurlencode("((node$oldtag;way$oldtag;relation$oldtag;rel(r));>;);out meta;");
	echo "<li><a href=\"http://overpass-turbo.eu/map.html?Q=$oldurl\">Search in old OSM-TMC data</a></li>\n";
	echo "<li><a href=\"http://localhost:8111/import?url=http://overpass-api.de/api/interpreter?data=$oldurl\" target=\"josm\">Import old OSM-TMC data into editor</a></li>\n";

	echo "</ul>\n";

	if($data['class'] == 'A')
	{
		echo "<h3>OSM relations</h3>\n";
		echo "<h5>tmc:area</h5>\n";

		$rels = array();
		$tags = osm_tags($data);
		$keys = array_keys($tags);

		$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:area']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='$lcd']]");
		foreach($osmrels as $osmrel)
		{
			$rel = array();
			$osmtags = $osmxp->query("tag", $osmrel);
			foreach($osmtags as $osmtag)
				$rel[$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');
			$rels[$osmrel->getAttribute('id')] = $rel;
			$keys = array_merge($keys, array_keys($rel));
		}
		$ids = array_keys($rels);
		$keys = array_unique($keys);
		sort($keys);

		if($osmrels->length > 1)
			echo "<p>More than one relation found!</p>\n";
		if(!$osmrels->length)
		{
			echo "<p>No relation found!</p>\n";
			echo "<ul>\n";
			echo "<li><a href=\"tmc2osm.php?cid=$cid&amp;tabcd=$tabcd&amp;lcd=$lcd\">Download as OSM XML</a></li>\n";
			echo "<li><a href=\"" . htmlentities("http://localhost:8111/import?url=http://" . $_SERVER['HTTP_HOST'] . str_replace('tmcview', 'tmc2osm', $_SERVER['REQUEST_URI'])) . "\" target=\"josm\">Import into editor</a></li>\n";
			echo "</ul>\n";
		}
		else
		{
			echo "<ul>\n";
			echo "<li><a href=\"$opurl\">Download as OSM XML</a></li>\n";
			echo "<li><a href=\"http://localhost:8111/load_object?objects=r" . implode(',r', $ids) . "\" target=\"josm\">Load into editor</a></li>\n";
			echo "</ul>\n";
		}

		echo "<table class=\"tmcosm\">\n";
		echo "<tr><th>id</th>";
		foreach($ids as $id)
			echo "<td><a href=\"http://www.openstreetmap.org/browse/relation/$id\">$id</a></td>";
		echo "<td>TMC data</td></tr>\n";
		foreach($keys as $key)
		{
			echo "<tr><th>$key</th>";
			$tval = (array_key_exists($key, $tags) ? $tags[$key] : "");
			foreach($ids as $id)
			{
				$oval = (array_key_exists($key, $rels[$id]) ? $rels[$id][$key] : "");
				echo "<td" . (preg_match('/^(note|description|name)(:[a-z_]+)?$/', $key) ? "" : " class=\"" . ($tval === $oval ? "correct" : "wrong") . "\"") . ">$oval</td>";
			}
			echo "<td>" . (array_key_exists($key, $tags) ? $tags[$key] : "") . "</td></tr>\n";
		}
		echo "</table>\n";
	}
	else if($data['class'] == 'P')
	{
		echo "<h3>OSM relations</h3>\n";
		echo "<h5>tmc:point</h5>\n";

		$rels = array();
		$tags = osm_tags($data);
		$keys = array_keys($tags);

		$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='lcd'][@v='$lcd']]");
		foreach($osmrels as $osmrel)
		{
			$rel = array();
			$osmtags = $osmxp->query("tag", $osmrel);
			foreach($osmtags as $osmtag)
				$rel[$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');
			$rels[$osmrel->getAttribute('id')] = $rel;
			$keys = array_merge($keys, array_keys($rel));
		}
		$ids = array_keys($rels);
		$keys = array_unique($keys);
		sort($keys);

		if($osmrels->length > 1)
			echo "<p>More than one relation found!</p>\n";
		if(!$osmrels->length)
		{
			echo "<p>No relation found!</p>\n";
			echo "<ul>\n";
			echo "<li><a href=\"tmc2osm.php?cid=$cid&amp;tabcd=$tabcd&amp;lcd=$lcd\">Download as OSM XML</a></li>\n";
			echo "<li><a href=\"" . htmlentities("http://localhost:8111/import?url=http://" . $_SERVER['HTTP_HOST'] . str_replace('tmcview', 'tmc2osm', $_SERVER['REQUEST_URI'])) . "\" target=\"josm\">Import into editor</a></li>\n";
			echo "</ul>\n";
		}
		else
		{
			echo "<ul>\n";
			echo "<li><a href=\"$opurl\">Download as OSM XML</a></li>\n";
			echo "<li><a href=\"http://localhost:8111/load_object?objects=r" . implode(',r', $ids) . "\" target=\"josm\">Load into editor</a></li>\n";
			echo "</ul>\n";
			// List all types + directions + roles here
			echo '<div id="types"></div>';
			echo '<div id="directions"></div>';
			echo '<div id="roles"></div>';
		}

		echo "<table class=\"tmcosm\">\n";
		echo "<tr><th>id</th>";
		foreach($ids as $id)
			echo "<td><a href=\"http://www.openstreetmap.org/browse/relation/$id\">$id</a></td>";
		echo "<td>TMC data</td></tr>\n";
		foreach($keys as $key)
		{
			echo "<tr><th>$key</th>";
			$tval = (array_key_exists($key, $tags) ? $tags[$key] : "");
			foreach($ids as $id)
			{
				$oval = (array_key_exists($key, $rels[$id]) ? $rels[$id][$key] : "");
				echo "<td" . (preg_match('/^(note|description|intersects|([a-z_]+_)?name)(:[a-z_]+)?$/', $key) ? "" : " class=\"" . ($tval === $oval ? "correct" : "wrong") . "\"") . ">$oval</td>";
			}
			echo "<td>" . (array_key_exists($key, $tags) ? $tags[$key] : "") . "</td></tr>\n";
		}
		echo "</table>\n";

		$dir = false;
		do
		{
			if($data[($dir ? 'pos_off_lcd' : 'neg_off_lcd')])
			{
				echo "<h5>tmc:link (" . ($dir ? "positive" : "negative") . ")</h5>\n";

				$rels = array();
				$tags = link_tags($data, $dir);
				$keys = array_keys($tags);

				$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:link']][tag[@k='table'][@v='$cid:$tabcd']][tag[@k='" . ($dir ? "neg" : "pos"). "_lcd'][@v='$lcd']]");
				foreach($osmrels as $osmrel)
				{
					$rel = array();
					$osmtags = $osmxp->query("tag", $osmrel);
					foreach($osmtags as $osmtag)
						$rel[$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');
					$rels[$osmrel->getAttribute('id')] = $rel;
					$keys = array_merge($keys, array_keys($rel));
				}
				$ids = array_keys($rels);
				$keys = array_unique($keys);
				sort($keys);

				if($osmrels->length > 1)
					echo "<p>More than one relation found!</p>\n";
				if(!$osmrels->length)
				{
					echo "<p>No relation found!</p>\n";
					echo "<ul>\n";
					echo "<li><a href=\"tmc2link.php?cid=$cid&amp;tabcd=$tabcd&amp;lcd=$lcd&amp;dir=" . ($dir ? "pos" : "neg") . "\">Download as OSM XML</a></li>\n";
					echo "<li><a href=\"" . htmlentities("http://localhost:8111/import?url=http://" . $_SERVER['HTTP_HOST'] . str_replace('tmcview', 'tmc2link', $_SERVER['REQUEST_URI'])) . "&amp;dir=" . ($dir ? "pos" : "neg") . "\" target=\"josm\">Import into editor</a></li>\n";
					echo "</ul>\n";
				}
				else
				{
					echo "<ul>\n";
					echo "<li><a href=\"$opurl\">Download as OSM XML</a></li>\n";
					echo "<li><a href=\"http://localhost:8111/load_object?objects=r" . implode(',r', $ids) . "\" target=\"josm\">Load into editor</a></li>\n";
					echo "</ul>\n";
				}

				echo "<table class=\"tmcosm\">\n";
				echo "<tr><th>id</th>";
				foreach($ids as $id)
					echo "<td><a href=\"http://www.openstreetmap.org/browse/relation/$id\">$id</a></td>";
				echo "<td>TMC data</td></tr>\n";
				foreach($keys as $key)
				{
					echo "<tr><th>$key</th>";
					$tval = (array_key_exists($key, $tags) ? $tags[$key] : "");
					foreach($ids as $id)
					{
						$oval = (array_key_exists($key, $rels[$id]) ? $rels[$id][$key] : "");
						echo "<td class=\"" . ($tval === $oval ? "correct" : "wrong") . "\">$oval</td>";
					}
					echo "<td>" . (array_key_exists($key, $tags) ? $tags[$key] : "") . "</td></tr>\n";
				}
				echo "</table>\n";
			}

			$dir = !$dir;
		}
		while($dir);
	} else {
					// List all types + directions + roles here
			echo '<div id="types"></div>';
			echo '<div id="directions"></div>';
			echo '<div id="roles"></div>';
	}

	echo "<h3>Raw TMC data</h3>\n";
	echo "<table class=\"tmcraw\">\n";
	foreach($data as $key => $value)
	{
		if($value != "")
			echo "<tr><th>$key</th><td>$value</td></tr>\n";
	}
	echo "</table>\n";

	echo "<h3>Linked locations</h3>\n";
	echo "<table class=\"tmclist\">\n";
	write_link('pol_lcd', "Administrative area", $links);
	write_link('oth_lcd', "Other area", $links);
	write_link('seg_lcd', "Containing segment", $links);
	write_link('roa_lcd', "Containing road", $links);
	write_link('neg_off_lcd', "Previous element", $links);
	write_link('pos_off_lcd', "Next element", $links);
	write_link('interruptsroad', "Interrupted road", $links);
	echo "</table>\n";

	if($data['class'] == 'P')
	{
		echo "<h3>Points at the same coordinates</h3>\n";
		write_table($inters);
	}
	else
	{
		echo "<h3>Contained locations</h3>\n";

		if(count($admins))
		{
			echo "<h5>Contained administrative areas</h5>\n";
			write_table($admins);
		}

		if(count($others))
		{
			echo "<h5>Contained other areas</h5>\n";
			write_table($others);
		}

		if(count($roads))
		{
			echo "<h5>Contained roads</h5>\n";
			write_table($roads);
		}

		if(count($segments))
		{
			echo "<h5>Contained segments</h5>\n";
			write_table($segments);
		}

		if(count($points))
		{
			echo "<h5>Contained points</h5>\n";
			write_table($points);
		}
	}

	form_search();
?>
</div>
</body>
</html>
<?php
}
?>
