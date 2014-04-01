<?php
// Enter database connection details here!
$pdo = new PDO('mysql:host=HOSTNAME;port=3306;dbname=DBMANE;charset=utf8', 'USERNAME', 'PASSWORD', array(PDO::ATTR_PERSISTENT => true));

function find_names($data)
{
	global $pdo;
	static $names = array('nid', 'rnid', 'n1id', 'n2id');

	foreach($names as $name)
	{
		if(array_key_exists($name, $data))
		{
			if($data[$name])
			{
				$result = $pdo->query("SELECT name FROM names WHERE cid = '" . $data['cid'] . "' AND nid = '" . $data[$name] . "'");
				if($result && ($value = $result->fetch(PDO::FETCH_COLUMN)))
					$data[$name] = $value;
				else
					$data[$name] = "";
			}
			else
				$data[$name] = "";
		}
	}

	return $data;
}

function find_type($data)
{
	global $pdo;

	if(($result = $pdo->query("SELECT `desc` FROM types WHERE class = '" . $data['class'] . "' AND tcd = '" . $data['tcd'] . "' AND stcd = '" . $data['stcd'] . "'")) && ($name = $result->fetch(PDO::FETCH_COLUMN)))
		return $name;
	else
		return "";
}

function find_location($table, $cid, $tabcd, $lcd)
{
	global $pdo;

	$result = $pdo->query("SELECT * FROM $table WHERE cid = '$cid' AND tabcd = '$tabcd' AND lcd = '$lcd'");
	if(!$result)
		return false;
	$data = $result->fetch(PDO::FETCH_ASSOC);
	if(!$data)
		return false;
	return find_names($data);
}

function find_road($data)
{
	while(($data['roa_lcd'] == 0) && ($data['seg_lcd'] != 0))
	{
		$data = find_location('segments', $data['cid'], $data['tabcd'], $data['seg_lcd']);
		if(!$data)
			return 0;
	}

	return find_location('roads', $data['cid'], $data['tabcd'], $data['roa_lcd']);
}

function find_links($data)
{
	$links = array();

	if(array_key_exists('pol_lcd', $data) && $data['pol_lcd'] && ($link = find_location('administrativearea', $data['cid'], $data['tabcd'], $data['pol_lcd'])))
		$links['pol_lcd'] = $link;
	if(array_key_exists('oth_lcd', $data) && $data['oth_lcd'] && ($link = find_location('otherareas', $data['cid'], $data['tabcd'], $data['oth_lcd'])))
		$links['oth_lcd'] = $link;
	if(array_key_exists('seg_lcd', $data) && $data['seg_lcd'] && ($link = find_location('segments', $data['cid'], $data['tabcd'], $data['seg_lcd'])))
		$links['seg_lcd'] = $link;
	if(array_key_exists('roa_lcd', $data) && $data['roa_lcd'] && ($link = find_location('roads', $data['cid'], $data['tabcd'], $data['roa_lcd'])))
		$links['roa_lcd'] = $link;
	if(array_key_exists('neg_off_lcd', $data) && $data['neg_off_lcd'] && ($link = find_location(($data['class'] == 'P' ? 'points' : 'segments'), $data['cid'], $data['tabcd'], $data['neg_off_lcd'])))
		$links['neg_off_lcd'] = $link;
	if(array_key_exists('pos_off_lcd', $data) && $data['pos_off_lcd'] && ($link = find_location(($data['class'] == 'P' ? 'points' : 'segments'), $data['cid'], $data['tabcd'], $data['pos_off_lcd'])))
		$links['pos_off_lcd'] = $link;
	if(array_key_exists('interruptsroad', $data) && $data['interruptsroad'] && ($link = find_location('points', $data['cid'], $data['tabcd'], $data['interruptsroad'])))
		$links['interruptsroad'] = $link;

	return $links;
}

function find_inter($data)
{
	global $pdo;

	if(($data['class'] == 'P') && ($result = $pdo->query("SELECT * FROM points WHERE xcoord = '" . $data['xcoord'] . "' AND ycoord = '" . $data['ycoord'] . "' ORDER BY cid, tabcd, lcd")) && ($inters = $result->fetchAll(PDO::FETCH_ASSOC)))
		return array_map("find_names", $inters);
	
	return array();
}

function find_segment_points($data)
{
	global $pdo;

	$points = array();
	if(($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = poffsets.lcd AND points.seg_lcd = '" . $data['lcd'] . "' AND NOT EXISTS (SELECT * FROM points WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND seg_lcd = '" . $data['lcd'] . "' AND lcd = poffsets.neg_off_lcd)")) && ($point = $result->fetch(PDO::FETCH_ASSOC)))
	{
		for(;;)
		{
			$point = find_names($point);
			$points[] = $point;

			if(!($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "' AND points.seg_lcd = '" . $data['lcd'] . "'")))
				break;
			if(!($point = $result->fetch(PDO::FETCH_ASSOC)))
				break;
		}
	}

	return $points;
}

function find_road_points($data)
{
	global $pdo;

	$segs = array();
	if($data['tcd'] == 2)
	{
		if(($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = poffsets.lcd AND (points.roa_lcd = '" . $data['lcd'] . "' OR EXISTS (SELECT * FROM segments WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND roa_lcd = '" . $data['lcd'] . "' AND lcd = points.seg_lcd))")) && ($first = $point = $result->fetch(PDO::FETCH_ASSOC)))
		{
			$points = array();
			for(;;)
			{
				$point = find_names($point);
				$points[] = $point;

				if($point['pos_off_lcd'] == $first['lcd'])
					break;
				if(!($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "'")))
					break;
				if(!($point = $result->fetch(PDO::FETCH_ASSOC)))
					break;
			}
			$segs[] = $points;
		}
	}
	else
	{
		if($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = poffsets.lcd AND poffsets.neg_off_lcd = '0' AND (points.roa_lcd = '" . $data['lcd'] . "' OR EXISTS (SELECT * FROM segments WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND roa_lcd = '" . $data['lcd'] . "' AND lcd = points.seg_lcd))"))
		{
			while($point = $result->fetch(PDO::FETCH_ASSOC))
			{
				$points = array();
				for(;;)
				{
					$point = find_names($point);
					$points[] = $point;

					if(!($result2 = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '" . $data['cid'] . "' AND poffsets.cid = '" . $data['cid'] . "' AND points.tabcd = '" . $data['tabcd'] . "' AND poffsets.tabcd = '" . $data['tabcd'] . "' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "'")))
						break;
					if(!($point = $result2->fetch(PDO::FETCH_ASSOC)))
						break;
				}
				$segs[] = $points;
			}
		}
	}

	return $segs;
}

function find_rs_segments($data)
{
	global $pdo;

	$segments = array();
	if($data['tcd'] == 2)
	{
		if(($result = $pdo->query("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = '" . $data['cid'] . "' AND soffsets.cid = '" . $data['cid'] . "' AND segments.tabcd = '" . $data['tabcd'] . "' AND soffsets.tabcd = '" . $data['tabcd'] . "' AND segments.lcd = soffsets.lcd AND (segments.roa_lcd = '" . $data['lcd'] . "' OR EXISTS (SELECT * FROM segments WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND roa_lcd = '" . $data['lcd'] . "' AND lcd = segments.seg_lcd))")) && ($first = $segment = $result->fetch(PDO::FETCH_ASSOC)))
		{
			$segments = array();
			for(;;)
			{
				$segment = find_names($segment);
				$segments[] = $segment;

				if($segment['pos_off_lcd'] == $first['lcd'])
					break;
				if(!($result = $pdo->query("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = '" . $data['cid'] . "' AND soffsets.cid = '" . $data['cid'] . "' AND segments.tabcd = '" . $data['tabcd'] . "' AND soffsets.tabcd = '" . $data['tabcd'] . "' AND segments.lcd = '" . $segment['pos_off_lcd'] . "' AND soffsets.lcd = '" . $segment['pos_off_lcd'] . "'")))
					break;
				if(!($segment = $result->fetch(PDO::FETCH_ASSOC)))
					break;
			}
		}
	}
	else
	{
		if($result = $pdo->query("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = '" . $data['cid'] . "' AND soffsets.cid = '" . $data['cid'] . "' AND segments.tabcd = '" . $data['tabcd'] . "' AND soffsets.tabcd = '" . $data['tabcd'] . "' AND segments.lcd = soffsets.lcd AND soffsets.neg_off_lcd = '0' AND (segments.roa_lcd = '" . $data['lcd'] . "' OR EXISTS (SELECT * FROM segments WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND roa_lcd = '" . $data['lcd'] . "' AND lcd = segments.seg_lcd))"))
		{
			while($segment = $result->fetch(PDO::FETCH_ASSOC))
			{
				$segments = array();
				for(;;)
				{
					$segment = find_names($segment);
					$segments[] = $segment;

					if(!($result2 = $pdo->query("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = '" . $data['cid'] . "' AND soffsets.cid = '" . $data['cid'] . "' AND segments.tabcd = '" . $data['tabcd'] . "' AND soffsets.tabcd = '" . $data['tabcd'] . "' AND segments.lcd = '" . $segment['pos_off_lcd'] . "' AND soffsets.lcd = '" . $segment['pos_off_lcd'] . "'")))
						break;
					if(!($segment = $result2->fetch(PDO::FETCH_ASSOC)))
						break;
				}
			}
		}
	}
	return $segments;
}

function find_area_points($data)
{
	global $pdo;

	if($result = $pdo->query("SELECT * FROM points WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND (pol_lcd = '" . $data['lcd'] . "' OR oth_lcd = '" . $data['lcd'] . "') ORDER BY junctionnumber, rnid, n1id, n2id ASC"))
		return array_map("find_names", $result->fetchAll(PDO::FETCH_ASSOC));
	else
		return array();
}

function find_area_segments($data)
{
	global $pdo;

	if($result = $pdo->query("SELECT * FROM segments WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND pol_lcd = '" . $data['lcd'] . "' ORDER BY roadnumber, rnid, n1id, n2id ASC"))
		return array_map("find_names", $result->fetchAll(PDO::FETCH_ASSOC));
	else
		return array();
}

function find_area_roads($data)
{
	global $pdo;

	if($result = $pdo->query("SELECT * FROM roads WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND pol_lcd = '" . $data['lcd'] . "' ORDER BY roadnumber, rnid, n1id, n2id ASC"))
		return array_map("find_names", $result->fetchAll(PDO::FETCH_ASSOC));
	else
		return array();
}

function find_area_admins($data)
{
	global $pdo;

	if($result = $pdo->query("SELECT * FROM administrativearea WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND pol_lcd = '" . $data['lcd'] . "' ORDER BY nid ASC"))
		return array_map("find_names", $result->fetchAll(PDO::FETCH_ASSOC));
	else
		return array();
}

function find_area_others($data)
{
	global $pdo;

	if($result = $pdo->query("SELECT * FROM otherareas WHERE cid = '" . $data['cid'] . "' AND tabcd = '" . $data['tabcd'] . "' AND pol_lcd = '" . $data['lcd'] . "' ORDER BY nid ASC"))
		return array_map("find_names", $result->fetchAll(PDO::FETCH_ASSOC));
	else
		return array();
}

function find_country($cid)
{
	global $pdo;

	$result = $pdo->query("SELECT * FROM countries WHERE cid = '$cid'");
	if(!$result)
		return false;
	$data = $result->fetch(PDO::FETCH_ASSOC);
	if(!$data)
		return false;
	return $data;
}
?>
