<?php
include_once('tmcpdo.php');
include_once('tmcutils.php');

function not_found($info)
{
	header("HTTP/1.0 404 Not Found");
	header("Content-type: text/plain");
	die($info);
}

function create_wpt($data)
{
	global $xml, $gpx;

	$wpt = $xml->createElement('wpt');
	$wpt->setAttribute('lon', $data['xcoord']);
	$wpt->setAttribute('lat', $data['ycoord']);
	$gpx->appendChild($wpt);
	$wpt->appendChild($xml->createElement('name', $data['cid'] . ":" . $data['tabcd'] . ":" . $data['lcd']));
	$wpt->appendChild($xml->createElement('desc', array_desc($data)));
	$wpt->appendChild($xml->createElement('cmt', array_cmt($data)));
	$wpt->appendChild($xml->createElement('sym', $data['class'] . $data['tcd'] . "." . $data['stcd']));
}

function create_rtept($data)
{
	global $xml;

	$rtept = $xml->createElement('rtept');
	$rtept->setAttribute('lon', $data['xcoord']);
	$rtept->setAttribute('lat', $data['ycoord']);
	return $rtept;
}

$cid = (int)$_REQUEST['cid'];
$tabcd = (int)$_REQUEST['tabcd'];
$lcd = (int)$_REQUEST['lcd'];

if(!($alloc = find_location('locationcodes', $cid, $tabcd, $lcd)))
	not_found("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.\n");
if(!$alloc['allocated'])
	not_found("Location code $lcd is not allocated in country $cid table $tabcd.\n");

header("Content-type: application/xml+gpx; charset=utf-8");
header("Content-Disposition: attachment; filename=\"${cid}_${tabcd}_${lcd}.gpx\"");
//header("Content-type: text/plain; charset=utf-8");

$xml = new DOMDocument("1.0", "utf-8");
$xml->formatOutput = true;
$gpx = $xml->createElement('gpx');
$gpx->setAttribute('creator', "TMC Script");
$gpx->setAttribute('version', "1.0");
$gpx->setAttribute('xmlns:xsi', "http://www.w3.org/2001/XMLSchema-instance");
$gpx->setAttribute('xmlns:xsd', "http://www.w3.org/2001/XMLSchema");
$gpx->setAttribute('xsi:schemaLocation', "http://www.topografix.com/GPX/1/0 http://www.topografix.com/GPX/1/0/gpx.xsd");
$gpx->setAttribute('xmlns', "http://www.topografix.com/GPX/1/0");
$xml->appendChild($gpx);
$meta = $xml->createElement('metadata');
$gpx->appendChild($meta);
$meta->appendChild($xml->createElement('name', "$cid:$tabcd:$lcd"));

if($point = find_location('points', $cid, $tabcd, $lcd))
{
	$meta->appendChild($xml->createElement('desc', array_desc($point)));
	create_wpt($point);
}
else if($segment = find_location('segments', $cid, $tabcd, $lcd))
{
	$meta->appendChild($xml->createElement('desc', array_desc($segment)));

	if(($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = poffsets.lcd AND points.seg_lcd = '$lcd' AND NOT EXISTS (SELECT * FROM points WHERE cid = '$cid' AND tabcd = '$tabcd' AND seg_lcd = '$lcd' AND lcd = poffsets.neg_off_lcd)")) && ($point = $result->fetch(PDO::FETCH_ASSOC)))
	{
		$rte = $xml->createElement('rte');
		$gpx->appendChild($rte);

		for(;;)
		{
			$point = find_names($point);
			$rte->appendChild(create_rtept($point));
			create_wpt($point);

			if(!($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "' AND points.seg_lcd = '$lcd'")))
				break;
			if(!($point = $result->fetch(PDO::FETCH_ASSOC)))
				break;
		}
	}
}
else if($road = find_location('roads', $cid, $tabcd, $lcd))
{
	$meta->appendChild($xml->createElement('desc', array_desc($road)));

	if($road['tcd'] == 2)
	{
		if(($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = poffsets.lcd AND (points.roa_lcd = '$lcd' OR EXISTS (SELECT * FROM segments WHERE cid = '$cid' AND tabcd = '$tabcd' AND roa_lcd = '$lcd' AND lcd = points.seg_lcd))")) && ($first = $point = $result->fetch(PDO::FETCH_ASSOC)))
		{
			$rte = $xml->createElement('rte');
			$gpx->appendChild($rte);

			for(;;)
			{
				$point = find_names($point);
				$rte->appendChild(create_rtept($point));
				create_wpt($point);

				if($point['pos_off_lcd'] == $first['lcd'])
					break;
				if(!($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "'")))
					break;
				if(!($point = $result->fetch(PDO::FETCH_ASSOC)))
					break;
			}
			$rte->appendChild(create_rtept($first));
		}
	}
	else
	{
		if($result = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = poffsets.lcd AND poffsets.neg_off_lcd = '0' AND (points.roa_lcd = '$lcd' OR EXISTS (SELECT * FROM segments WHERE cid = '$cid' AND tabcd = '$tabcd' AND roa_lcd = '$lcd' AND lcd = points.seg_lcd))"))
		{
			while($point = $result->fetch(PDO::FETCH_ASSOC))
			{
				$rte = $xml->createElement('rte');
				$gpx->appendChild($rte);

				for(;;)
				{
					$point = find_names($point);
					$rte->appendChild(create_rtept($point));
					create_wpt($point);

					if(!($result2 = $pdo->query("SELECT points.*, poffsets.neg_off_lcd, poffsets.pos_off_lcd FROM points, poffsets WHERE points.cid = '$cid' AND poffsets.cid = '$cid' AND points.tabcd = '$tabcd' AND poffsets.tabcd = '$tabcd' AND points.lcd = '" . $point['pos_off_lcd'] . "' AND poffsets.lcd = '" . $point['pos_off_lcd'] . "'")))
						break;
					if(!($point = $result2->fetch(PDO::FETCH_ASSOC)))
						break;
				}
			}
		}
	}
}
else if($area = find_location('administrativearea', $cid, $tabcd, $lcd))
{
	$meta->appendChild($xml->createElement('desc', array_desc($area)));

	if($result = $pdo->query("SELECT * FROM points WHERE points.cid = '$cid' AND points.tabcd = '$tabcd' AND points.pol_lcd = '$lcd'"))
	{
		while($point = $result->fetch(PDO::FETCH_ASSOC))
		{
			$point = find_names($point);
			create_wpt($point);
		}
	}
}
else if($area = find_location('otherareas', $cid, $tabcd, $lcd))
{
	$meta->appendChild($xml->createElement('desc', array_desc($area)));

	if($result = $pdo->query("SELECT * FROM points WHERE points.cid = '$cid' AND points.tabcd = '$tabcd' AND points.oth_lcd = '$lcd'"))
	{
		while($point = $result->fetch(PDO::FETCH_ASSOC))
		{
			$point = find_names($point);
			create_wpt($point);
		}
	}
}

$meta->appendChild($xml->createElement('author', "Manuel Hohmann"));
echo $xml->saveXML();
?>
