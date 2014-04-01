<?php
function nonempty($s)
{
	return($s !== "");
}

function combine($k, $v)
{
	return "$k = $v";
}

function array_cmt($a)
{
	$v = array_filter($a, "nonempty");
	$k = array_keys($v);
	$c = array_map("combine", $k, $v);
	return implode("\n", $c);
}

function array_desc($a)
{
	if($a['class'] == 'P')
		$data = array($a['junctionnumber'], $a['rnid'], $a['n1id'], $a['n2id']);
	else if($a['class'] == 'L')
		$data = array($a['roadnumber'], $a['rnid'], $a['n1id'], $a['n2id']);
	else
		$data = array($a['nid']);
	return implode(" ", array_filter($data, "nonempty"));
}

function calc_angle($neg, $data, $pos)
{
	return rad2deg(atan2((float)($neg['xcoord'] - $pos['xcoord']) * cos(deg2rad($data['ycoord'])), (float)($neg['ycoord'] - $pos['ycoord'])));
}

function line_angles($array)
{
	$angles = array();
	$pp = $p = false;
	foreach($array as $data)
	{
		if(!$p)
		{
			$p = $data;
		}
		else if(!$pp)
		{
			$angles[] = calc_angle($p, $data, $data);
			$pp = $p;
			$p = $data;
		}
		else
		{
			$angles[] = calc_angle($pp, $p, $data);
			$pp = $p;
			$p = $data;
		}
	}
	$angles[] = calc_angle($pp, $p, $p);
	return $angles;
}

function ring_angles($array)
{
	$angles = array();
	$pp = $p = false;
	foreach($array as $data)
	{
		if(!$p)
		{
			$p = $data;
		}
		else if(!$pp)
		{
			$angles[] = calc_angle($array[count($array) - 1], $p, $data);
			$pp = $p;
			$p = $data;
		}
		else
		{
			$angles[] = calc_angle($pp, $p, $data);
			$pp = $p;
			$p = $data;
		}
	}
	$angles[] = calc_angle($pp, $p, $array[0]);
	return $angles;
}
?>
