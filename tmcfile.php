<?php
function remove_utf8_bom($text)
{
	$bom = pack('H*','EFBBBF');
	$text = preg_replace("/^$bom/", '', $text);
	return $text;
}

function get_charset($country)
{
	global $config_lt_dir;

	$readme = explode(";", trim(remove_utf8_bom(file_get_contents("$config_lt_dir/$country/README.DAT"))));
	$arr = explode(' ', $readme[4]);
	if($arr[0] == 'ISO')
		// Variant for German ISO 8859 without minus
		$arr = explode(' (', $readme[4]);
	return $arr[0];
}
?>

