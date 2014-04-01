<?php
include_once('tmcpdo.php');
include_once('tmcosm.php');

function not_found($info)
{
	header("HTTP/1.0 404 Not Found");
	header("Content-type: text/plain");
	die($info);
}

$cid = (int)$_REQUEST['cid'];
$tabcd = (int)$_REQUEST['tabcd'];
$lcd = (int)$_REQUEST['lcd'];
$dir = strtolower($_REQUEST['dir']);

if(($dir != 'pos') && ($dir != 'neg'))
	not_found("Direction $dir not found.\n");
if(!($alloc = find_location('locationcodes', $cid, $tabcd, $lcd)))
	not_found("No data found for cid = $cid, tabcd = $tabcd and lcd = $lcd.\n");
if(!$alloc['allocated'])
	not_found("Location code $lcd is not allocated in country $cid table $tabcd.\n");
if(!($point = find_location('points', $cid, $tabcd, $lcd)))
	not_found("No point found for cid = $cid, tabcd = $tabcd and lcd = $lcd.\n");
if(!($poffset = find_location('poffsets', $cid, $tabcd, $lcd)))
	not_found("No offsets found for cid = $cid, tabcd = $tabcd and lcd = $lcd.\n");

header("Content-type: application/xml; charset=utf-8");
header("Content-Disposition: inline; filename=\"${cid}_${tabcd}_${lcd}.osm\"");

$point = array_merge($point, $poffset);
$tags = link_tags($point, $dir == 'pos');

echo create_relation($tags); //$xml->saveXML();
?>
