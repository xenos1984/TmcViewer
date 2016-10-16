<?php
include_once('config.php');

$pdo = new PDO($config_pdo_connection, $config_pdo_readonly_user, $config_pdo_readonly_password, $config_pdo_attributes);

function find_names($data)
{
	global $pdo;
	static $names = array('nid', 'rnid', 'n1id', 'n2id');
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT name FROM names WHERE cid = :cid AND nid = :nid");

	foreach($names as $name)
	{
		if(array_key_exists($name, $data))
		{
			if($data[$name])
			{
				$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
				$stmt->bindValue(':nid', $data[$name], PDO::PARAM_INT);
				$stmt->execute();

				if($value = $stmt->fetch(PDO::FETCH_COLUMN))
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
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT tdesc FROM types WHERE class = :class AND tcd = :tcd AND stcd = :stcd");

	$stmt->bindValue(':class', $data['class'], PDO::PARAM_STR);
	$stmt->bindValue(':tcd', $data['tcd'], PDO::PARAM_INT);
	$stmt->bindValue(':stcd', $data['stcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($name = $stmt->fetch(PDO::FETCH_COLUMN))
		return $name;
	else
		return "";
}

function find_location($table, $cid, $tabcd, $lcd)
{
	global $pdo;
	static $stmts = array('locationcodes' => null, 'points' => null, 'poffsets' => null, 'segments' => null, 'soffsets' => null, 'roads' => null, 'administrativearea' => null, 'otherareas' => null);

	if(!array_key_exists($table, $stmts))
		return false;

	if($stmts[$table] === null)
		$stmts[$table] = $pdo->prepare("SELECT * FROM $table WHERE cid = :cid AND tabcd = :tabcd AND lcd = :lcd");

	$stmts[$table]->bindValue(':cid', $cid, PDO::PARAM_INT);
	$stmts[$table]->bindValue(':tabcd', $tabcd, PDO::PARAM_INT);
	$stmts[$table]->bindValue(':lcd', $lcd, PDO::PARAM_INT);
	$stmts[$table]->execute();

	if(!($data = $stmts[$table]->fetch(PDO::FETCH_ASSOC)))
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
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM points WHERE xcoord = :xcoord AND ycoord = :ycoord ORDER BY cid, tabcd, lcd");

	if($data['class'] == 'P')
	{
		$stmt->bindValue(':xcoord', $data['xcoord']);
		$stmt->bindValue(':ycoord', $data['ycoord']);
		$stmt->execute();

		if($inters = $stmt->fetchAll(PDO::FETCH_ASSOC))
			return array_map("find_names", $inters);
	}

	return array();
}

function find_segment_points($data)
{
	global $pdo;
	static $first = null;
	static $next = null;

	if($first === null)
		$first = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = poffsets.lcd AND points.seg_lcd = :lcd_a AND NOT EXISTS (SELECT * FROM points WHERE cid = :cid_c AND tabcd = :tabcd_c AND seg_lcd = :lcd_b AND lcd = poffsets.neg_off_lcd)");

	if($next === null)
		$next = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = :lcd_a AND poffsets.lcd = :lcd_b AND points.seg_lcd = :lcd_c");

	$first->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
	$first->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
	$first->bindValue(':cid_c', $data['cid'], PDO::PARAM_INT);
	$first->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
	$first->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
	$first->bindValue(':tabcd_c', $data['tabcd'], PDO::PARAM_INT);
	$first->bindValue(':lcd_a', $data['lcd'], PDO::PARAM_INT);
	$first->bindValue(':lcd_b', $data['lcd'], PDO::PARAM_INT);

	$next->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
	$next->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
	$next->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
	$next->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
	$next->bindValue(':lcd_c', $data['lcd'], PDO::PARAM_INT);

	$points = array();
	$first->execute();

	if($point = $first->fetch(PDO::FETCH_ASSOC))
	{
		for(;;)
		{
			$point = find_names($point);
			$points[] = $point;

			$next->bindValue(':lcd_a', $point['pos_off_lcd'], PDO::PARAM_INT);
			$next->bindValue(':lcd_b', $point['pos_off_lcd'], PDO::PARAM_INT);
			$next->execute();

			if(!($point = $next->fetch(PDO::FETCH_ASSOC)))
				break;
		}
	}

	return $points;
}

function find_road_points($data)
{
	global $pdo;
	static $rfirst = null;
	static $rnext = null;
	static $lfirst = null;
	static $lnext = null;

	$segs = array();

	if($data['tcd'] == 2)
	{
		if($rfirst === null)
			$rfirst = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = poffsets.lcd AND (points.roa_lcd = :lcd_a OR EXISTS (SELECT * FROM segments WHERE cid = :cid_c AND tabcd = :tabcd_c AND roa_lcd = :lcd_b AND lcd = points.seg_lcd)) LIMIT 1");

		if($rnext === null)
			$rnext = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = :lcd_a AND poffsets.lcd = :lcd_b");

		$rfirst->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':cid_c', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_c', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':lcd_a', $data['lcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':lcd_b', $data['lcd'], PDO::PARAM_INT);

		$rnext->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$rnext->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$rnext->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$rnext->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);

		$rfirst->execute();

		if($first = $point = $rfirst->fetch(PDO::FETCH_ASSOC))
		{
			$points = array();
			for(;;)
			{
				$point = find_names($point);
				$points[] = $point;

				if($point['pos_off_lcd'] == $first['lcd'])
					break;

				$rnext->bindValue(':lcd_a', $point['pos_off_lcd'], PDO::PARAM_INT);
				$rnext->bindValue(':lcd_b', $point['pos_off_lcd'], PDO::PARAM_INT);
				$rnext->execute();

				if(!($point = $rnext->fetch(PDO::FETCH_ASSOC)))
					break;
			}
			$segs[] = $points;
		}
	}
	else
	{
		if($lfirst === null)
			$lfirst = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = poffsets.lcd AND poffsets.neg_off_lcd = '0' AND (points.roa_lcd = :lcd_a OR EXISTS (SELECT * FROM segments WHERE cid = :cid_c AND tabcd = :tabcd_c AND roa_lcd = :lcd_b AND lcd = points.seg_lcd))");

		if($lnext === null)
			$lnext = $pdo->prepare("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = :cid_a AND poffsets.cid = :cid_b AND points.tabcd = :tabcd_a AND poffsets.tabcd = :tabcd_b AND points.lcd = :lcd_a AND poffsets.lcd = :lcd_b");

		$lfirst->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':cid_c', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_c', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':lcd_a', $data['lcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':lcd_b', $data['lcd'], PDO::PARAM_INT);

		$lnext->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$lnext->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$lnext->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$lnext->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);

		$lfirst->execute();

		while($point = $lfirst->fetch(PDO::FETCH_ASSOC))
		{
			$points = array();
			for(;;)
			{
				$point = find_names($point);
				$points[] = $point;

				$lnext->bindValue(':lcd_a', $point['pos_off_lcd'], PDO::PARAM_INT);
				$lnext->bindValue(':lcd_b', $point['pos_off_lcd'], PDO::PARAM_INT);
				$lnext->execute();

				if(!($point = $lnext->fetch(PDO::FETCH_ASSOC)))
					break;
			}
			$segs[] = $points;
		}
	}

	return $segs;
}

function find_rs_segments($data)
{
	global $pdo;
	static $rfirst = null;
	static $rnext = null;
	static $lfirst = null;
	static $lnext = null;

	$segments = array();

	if($data['tcd'] == 2)
	{
		if($rfirst === null)
			$rfirst = $pdo->prepare("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = :cid_a AND soffsets.cid = :cid_b AND segments.tabcd = :tabcd_a AND soffsets.tabcd = :tabcd_b AND segments.lcd = soffsets.lcd AND (segments.roa_lcd = :lcd_a OR EXISTS (SELECT * FROM segments WHERE cid = :cid_c AND tabcd = :tabcd_c AND roa_lcd = :lcd_b AND lcd = segments.seg_lcd))");

		if($rnext === null)
			$rnext = $pdo->prepare("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = :cid_a AND soffsets.cid = :cid_b AND segments.tabcd = :tabcd_a AND soffsets.tabcd = :tabcd_b AND segments.lcd = :lcd_a AND soffsets.lcd = :lcd_b");

		$rfirst->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':cid_c', $data['cid'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':tabcd_c', $data['tabcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':lcd_a', $data['lcd'], PDO::PARAM_INT);
		$rfirst->bindValue(':lcd_b', $data['lcd'], PDO::PARAM_INT);

		$rnext->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$rnext->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$rnext->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$rnext->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);

		$rfirst->execute();

		if($first = $segment = $rfirst->fetch(PDO::FETCH_ASSOC))
		{
			$segments = array();
			for(;;)
			{
				$segment = find_names($segment);
				$segments[] = $segment;

				if($segment['pos_off_lcd'] == $first['lcd'])
					break;

				$rnext->bindValue(':lcd_a', $segment['pos_off_lcd'], PDO::PARAM_INT);
				$rnext->bindValue(':lcd_b', $segment['pos_off_lcd'], PDO::PARAM_INT);
				$rnext->execute();

				if(!($segment = $rnext->fetch(PDO::FETCH_ASSOC)))
					break;
			}
		}
	}
	else
	{
		if($lfirst === null)
			$lfirst = $pdo->prepare("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = :cid_a AND soffsets.cid = :cid_b AND segments.tabcd = :tabcd_a AND soffsets.tabcd = :tabcd_b AND segments.lcd = soffsets.lcd AND soffsets.neg_off_lcd = '0' AND (segments.roa_lcd = :lcd_a OR EXISTS (SELECT * FROM segments WHERE cid = :cid_c AND tabcd = :tabcd_c AND roa_lcd = :lcd_b AND lcd = segments.seg_lcd))");

		if($lnext === null)
			$lnext = $pdo->prepare("SELECT segments.*, soffsets.neg_off_lcd, soffsets.pos_off_lcd FROM segments, soffsets WHERE segments.cid = :cid_a AND soffsets.cid = :cid_b AND segments.tabcd = :tabcd_a AND soffsets.tabcd = :tabcd_b AND segments.lcd = :lcd_a AND soffsets.lcd = :lcd_b");

		$lfirst->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':cid_c', $data['cid'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':tabcd_c', $data['tabcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':lcd_a', $data['lcd'], PDO::PARAM_INT);
		$lfirst->bindValue(':lcd_b', $data['lcd'], PDO::PARAM_INT);

		$lnext->bindValue(':cid_a', $data['cid'], PDO::PARAM_INT);
		$lnext->bindValue(':cid_b', $data['cid'], PDO::PARAM_INT);
		$lnext->bindValue(':tabcd_a', $data['tabcd'], PDO::PARAM_INT);
		$lnext->bindValue(':tabcd_b', $data['tabcd'], PDO::PARAM_INT);

		$lfirst->execute();

		while($segment = $lfirst->fetch(PDO::FETCH_ASSOC))
		{
			$segments = array();
			for(;;)
			{
				$segment = find_names($segment);
				$segments[] = $segment;

				$lnext->bindValue(':lcd_a', $segment['pos_off_lcd'], PDO::PARAM_INT);
				$lnext->bindValue(':lcd_b', $segment['pos_off_lcd'], PDO::PARAM_INT);
				$lnext->execute();

				if(!($segment = $lnext->fetch(PDO::FETCH_ASSOC)))
					break;
			}
		}
	}
	return $segments;
}

function find_area_points($data)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM points WHERE cid = :cid AND tabcd = :tabcd AND (pol_lcd = :pol_lcd OR oth_lcd = :oth_lcd) ORDER BY junctionnumber, rnid, n1id, n2id ASC");

	$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
	$stmt->bindValue(':tabcd', $data['tabcd'], PDO::PARAM_INT);
	$stmt->bindValue(':pol_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->bindValue(':oth_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($result = $stmt->fetchAll(PDO::FETCH_ASSOC))
		return array_map("find_names", $result);
	else
		return array();
}

function find_area_segments($data)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM segments WHERE cid = :cid AND tabcd = :tabcd AND pol_lcd = :pol_lcd ORDER BY roadnumber, rnid, n1id, n2id ASC");

	$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
	$stmt->bindValue(':tabcd', $data['tabcd'], PDO::PARAM_INT);
	$stmt->bindValue(':pol_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($result = $stmt->fetchAll(PDO::FETCH_ASSOC))
		return array_map("find_names", $result);
	else
		return array();
}

function find_area_roads($data)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM roads WHERE cid = :cid AND tabcd = :tabcd AND pol_lcd = :pol_lcd ORDER BY roadnumber, rnid, n1id, n2id ASC");

	$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
	$stmt->bindValue(':tabcd', $data['tabcd'], PDO::PARAM_INT);
	$stmt->bindValue(':pol_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($result = $stmt->fetchAll(PDO::FETCH_ASSOC))
		return array_map("find_names", $result);
	else
		return array();
}

function find_area_admins($data)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM administrativearea WHERE cid = :cid AND tabcd = :tabcd AND pol_lcd = :pol_lcd ORDER BY nid ASC");

	$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
	$stmt->bindValue(':tabcd', $data['tabcd'], PDO::PARAM_INT);
	$stmt->bindValue(':pol_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($result = $stmt->fetchAll(PDO::FETCH_ASSOC))
		return array_map("find_names", $result);
	else
		return array();
}

function find_area_others($data)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM otherareas WHERE cid = :cid AND tabcd = :tabcd AND pol_lcd = :pol_lcd ORDER BY nid ASC");

	$stmt->bindValue(':cid', $data['cid'], PDO::PARAM_INT);
	$stmt->bindValue(':tabcd', $data['tabcd'], PDO::PARAM_INT);
	$stmt->bindValue(':pol_lcd', $data['lcd'], PDO::PARAM_INT);
	$stmt->execute();

	if($result = $stmt->fetchAll(PDO::FETCH_ASSOC))
		return array_map("find_names", $result);
	else
		return array();
}

function find_country($cid)
{
	global $pdo;
	static $stmt = null;

	if($stmt === null)
		$stmt = $pdo->prepare("SELECT * FROM countries WHERE cid = :cid");

	$stmt->bindValue(':cid', $cid, PDO::PARAM_INT);
	$stmt->execute();

	if(!($data = $stmt->fetch(PDO::FETCH_ASSOC)))
		return false;

	return $data;
}
?>
