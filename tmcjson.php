<?php
include_once("tmcutils.php");

function json_combine($k, $v)
{
	return "\"$k\": $v";
}

function json_coord($data)
{
	return array($data['xcoord'], $data['ycoord']);
}

function json_point($data, $angle = false)
{
	$props = $data;
	if($angle !== false)
		$props['angle'] = $angle;
	$props['desc'] = array_desc($data);
	$geom = array('type' => 'Point', 'coordinates' => json_coord($data));
	return array('type' => 'Feature', 'properties' => $props, 'geometry' => $geom);
}

function json_points($array)
{
	return array('type' => 'FeatureCollection', 'features' => array_map("json_point", $array));
}

function json_segment($seg, $data)
{
	$props = $data;
	$props['desc'] = array_desc($data);
	$line = array('type' => 'Feature', 'properties' => $props, 'geometry' => array('type' => 'LineString', 'coordinates' => array_map("json_coord", $seg)));
	$elements = array($line);
	$elements = array_merge($elements, array_map("json_point", $seg, line_angles($seg)));
	return array('type' => 'FeatureCollection', 'features' => $elements);
}

function json_road($array, $data)
{
	$props = $data;
	$props['desc'] = array_desc($data);
	$lines = array();
	$points = array();
	foreach($array as $seg)
	{
		$lines[] = array('type' => 'Feature', 'properties' => $props, 'geometry' => array('type' => 'LineString', 'coordinates' => array_map("json_coord", $seg)));
		$points = array_merge($points, array_map("json_point", $seg, line_angles($seg)));
	}
	return array('type' => 'FeatureCollection', 'features' => array_merge($lines, $points));
}

function json_ring($array, $data)
{
	$props = $data;
	$props['desc'] = array_desc($data);
	$lines = array();
	$points = array();
	foreach($array as $seg)
	{
		$lines[] = array('type' => 'Feature', 'properties' => $props, 'geometry' => array('type' => 'LineString', 'coordinates' => array_map("json_coord", array_merge($seg, array($seg[0])))));
		$points = array_merge($points, array_map("json_point", $seg, ring_angles($seg)));
	}
	return array('type' => 'FeatureCollection', 'features' => array_merge($lines, $points));
}

function json_osm($xml)
{
	$xpath = new DOMXPath($xml);

	$nodes = array();
	$osmnodes = $xpath->query("/osm/node");
	foreach($osmnodes as $osmnode)
	{
		$node = array('lon' => $osmnode->getAttribute('lon'), 'lat' => $osmnode->getAttribute('lat'));
		$nodes[$osmnode->getAttribute('id')] = $node;
	}

	$ways = array();
	$osmways = $xpath->query("/osm/way");
	foreach($osmways as $osmway)
	{
		$wns = array();
		$osmwns = $xpath->query("nd", $osmway);
		foreach($osmwns as $osmwn)
			$wns[] = $osmwn->getAttribute('ref');
		$ways[$osmway->getAttribute('id')] = $wns;
	}

	$osmrels = $xpath->query("/osm/relation");
	$features = array();

	foreach($osmrels as $osmrel)
	{
		$relprops = array('relation' => $osmrel->getAttribute('id'));
		$reltags = $xpath->query("tag", $osmrel);
		foreach($reltags as $reltag)
			$relprops[$reltag->getAttribute('k')] = $reltag->getAttribute('v');

		echo "<!--\n"; print_r($relprops); echo "-->\n";

		$members = $xpath->query("member", $osmrel);
		foreach($members as $member)
		{
			$id = $member->getAttribute('ref');
			$type = $member->getAttribute('type');
			$role = $member->getAttribute('role');
			$props = array('id' => $id, 'member' => $type, 'role' => $role);

			if($type == 'node')
			{
				$geom = array('type' => 'Point', 'coordinates' => array($nodes[$id]['lon'], $nodes[$id]['lat']));
			}
			else if($type == 'way')
			{
				$wns = $ways[$id];
				$coords = array();
				foreach($wns as $wn)
					$coords[] = array($nodes[$wn]['lon'], $nodes[$wn]['lat']);
				if($wns[0] == $wns[count($wns) - 1])
					$geom = array('type' => 'Polygon', 'coordinates' => array($coords));
				else
					$geom = array('type' => 'LineString', 'coordinates' => $coords);
			}

			$features[] = array('type' => 'Feature', 'properties' => array_merge($props, $relprops), 'geometry' => $geom);
			//echo "<!--\n"; print_r(array_merge($props, $relprops)); echo "-->\n";
		}
	}

	return array('type' => 'FeatureCollection', 'features' => $features);
}

function json_string($array)
{
	if(is_numeric($array))
		return $array;

	if(is_string($array))
		return "\"$array\"";

	if(count(array_filter(array_keys($array), 'is_string')))
		return '{' . implode(", ", array_map("json_combine", array_keys($array), array_map("json_string", $array))) . '}';
	else
		return '[' . implode(", ", array_map("json_string", $array)) . ']';
}
?>
