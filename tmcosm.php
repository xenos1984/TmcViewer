<?php
include_once('tmcpdo.php');

function osm_tags($data)
{
	$tags = array();
	$country = find_country($data['cid']);

	$tags['table'] = $data['cid'] . ":" . $data['tabcd'];
	$tags['lcd'] = $data['lcd'];
	$tags['class'] = $data['class'] . $data['tcd'] . "." . $data['stcd'];

	if($country)
		$tags['version'] = $country['version'];

	if($data['class'] == 'P')
	{
		$tags['type'] = "tmc:point";

		if($data['n1id'])
		{
			$tags['name'] = $data['n1id'];
			if($data['cid'] == 58)
				$tags['name'] = preg_replace('/([[:alpha:]]+)([[:digit:]])/', '$1 $2', $tags['name']);
		}
		if($data['n2id'])
		{
			$tags['intersects'] = $data['n2id'];
			if($data['cid'] == 58)
				$tags['intersects'] = preg_replace('/([[:alpha:]]+)([[:digit:]])/', '$1 $2', $tags['intersects']);
		}
		if($data['rnid'] != "")
			$tags['road_name'] = $data['rnid'];
		if($data['junctionnumber'])
			$tags['junction_ref'] = $data['junctionnumber'];
		if($data['pol_lcd'])
			$tags['area_lcd'] = $data['pol_lcd'];
		if($data['oth_lcd'])
			$tags['area_lcd'] = $data['oth_lcd'];
		if($data['seg_lcd'])
			$tags['seg_lcd'] = $data['seg_lcd'];

		if(array_key_exists('pos_off_lcd', $data) && $data['pos_off_lcd'])
			$tags['pos_offset'] = $data['pos_off_lcd'];
		if(array_key_exists('neg_off_lcd', $data) && $data['neg_off_lcd'])
			$tags['neg_offset'] = $data['neg_off_lcd'];

		if($data['presentpos'] && !$data['presentneg'])
			$tags['present'] = 'positive';
		else if($data['presentneg'] && !$data['presentpos'])
			$tags['present'] = 'negative';

		if(($data['seg_lcd'] && ($rs = find_location('segments', $data['cid'], $data['tabcd'], $data['seg_lcd']))) || ($data['roa_lcd'] && ($rs = find_location('roads', $data['cid'], $data['tabcd'], $data['roa_lcd']))))
		{
			if($rs['roadnumber'])
			{
				$tags['road_ref'] = $rs['roadnumber'];
				if($data['cid'] == 58)
					$tags['road_ref'] = preg_replace('/([[:alpha:]]+)([[:digit:]])/', '$1 $2', $tags['road_ref']);
			}
		}

		if($road = find_road($data))
			$tags['road_lcd'] = $road['lcd'];
	}
	else if($data['class'] == 'A')
	{
		$tags['type'] = "tmc:area";
		if($data['nid'] != "")
			$tags['name'] = $data['nid'];
		if($data['pol_lcd'])
			$tags['area_lcd'] = $data['pol_lcd'];
	}

	return $tags;
}

function link_tags($data, $dir)
{
	$tags = array();
	$country = find_country($data['cid']);

	if($data['class'] == 'P')
	{
		$tags['type'] = "tmc:link";
		$tags['table'] = $data['cid'] . ":" . $data['tabcd'];
		$tags['pos_lcd'] = $data[($dir ? 'pos_off_lcd' : 'lcd')];
		$tags['neg_lcd'] = $data[($dir ? 'lcd' : 'neg_off_lcd')];

		if($country)
			$tags['version'] = $country['version'];

		if($road = find_road($data))
			$tags['road_lcd'] = $road['lcd'];
	}

	return $tags;
}

function create_relation($tags)
{
	$xml = new DOMDocument("1.0", "utf-8");
	$xml->formatOutput = true;
	$osm = $xml->createElement('osm');
	$osm->setAttribute('version', "0.6");
	$osm->setAttribute('upload', "true");
	$osm->setAttribute('generator', "TMC import helper");
	$xml->appendChild($osm);

	$rel = $xml->createElement('relation');
	$rel->setAttribute('id', -1);
	$rel->setAttribute('action', "create");
	$rel->setAttribute('visible', "true");
	$osm->appendChild($rel);

	foreach($tags as $key => $value)
	{
		if($value)
		{
			$tag = $xml->createElement('tag');
			$tag->setAttribute('k', $key);
			$tag->setAttribute('v', $value);
			$rel->appendChild($tag);
		}
	}

	return $xml->saveXML();
}
?>
