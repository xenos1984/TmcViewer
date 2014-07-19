<?php
$starttime = microtime(true);

include_once("tmcpdo.php");
include_once("tmcosm.php");
include_once("tmcutils.php");

function create_head($cid, $tabcd)
{
	$s = "<!DOCTYPE html>\n";
	$s .= "<html>\n";
	$s .= "<head>\n";
	$s .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">\n";
	$s .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"tmc.css\"/>\n";
	$s .= "<title>TMC point completeness for country cid = $cid and tabcd = $tabcd</title>\n";
	$s .= "</head>\n";
	$s .= "<body>\n";
	$s .= "<h1>TMC point completeness for country cid = $cid and tabcd = $tabcd - " .  gmdate("d. m. Y H:i:s T") . "</h1>\n";
	$s .= "<table class=\"tmccheck\">\n";
	return $s;
}

function create_foot()
{
	$s = "</table>\n";
	$s .= "</body>\n";
	$s .= "</html>\n";
	return $s;
}

if(PHP_SAPI != 'cli')
	die();

$pagestat = "<!DOCTYPE html>\n";
$pagestat .= "<html>\n";
$pagestat .= "<head>\n";
$pagestat .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\">\n";
$pagestat .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"tmc.css\"/>\n";
$pagestat .= "<title>TMC point completeness</title>\n";
$pagestat .= "</head>\n";
$pagestat .= "<body>\n";
$pagestat .= "<h1>TMC point completeness - " .  gmdate("d. m. Y H:i:s T") . "</h1>\n";
$pagestat .= "<h3>Statistics by country</h3>\n";
$pagestat .= "<table class=\"statistics\">\n";
$pagestat .= "<tr><th>Country</th><th>Country ID</th><th>Table Code</th><th>Total points</th><th colspan=\"2\">OK</th><th colspan=\"2\">Missing</th><th colspan=\"2\">Error</th><th>Invalid</th><th>Last change</th></tr>\n";

$result = $pdo->query("SELECT * FROM countries ORDER BY cid");
$countries = $result->fetchAll(PDO::FETCH_ASSOC);

$total2 = 0;
$missing2 = 0;
$wrong2 = 0;
$correct2 = 0;
$nonex2 = 0;
$lasttime2 = 0;

$userstats = array();

foreach($countries as $country)
{
	$cid = $country['cid'];

	$result = $pdo->query("SELECT DISTINCT(tabcd) FROM locationcodes WHERE cid = '$cid' ORDER BY tabcd");
	$tabcds = $result->fetchAll(PDO::FETCH_COLUMN);

	foreach($tabcds as $tabcd)
	{
		$result = $pdo->query("SELECT * FROM points WHERE cid = '$cid' AND tabcd = '$tabcd' ORDER BY lcd");
		if(!$result)
			continue;

		$opurl = "http://overpass-api.de/api/interpreter?data=" . rawurlencode("relation[\"type\"=\"tmc:point\"][\"table\"=\"$cid:$tabcd\"];out meta;");
		$osm = new DOMDocument;
		$osm->load($opurl);
		$osmxp = new DOMXPath($osm);

		$rels = array();
		$lasttime = 0;
		$lastuser = "";
		$osmrels = $osmxp->query("/osm/relation[tag[@k='type'][@v='tmc:point']][tag[@k='table'][@v='$cid:$tabcd']]");
		foreach($osmrels as $osmrel)
		{
			$rel = array();
			$rel['id'] = (int)$osmrel->getAttribute('id');
			$rel['user'] = $osmrel->getAttribute('user');
			$rel['time'] = strtotime($osmrel->getAttribute('timestamp'));
			$rel['tags'] = array();
			$rel['roles'] = array();
			$osmtags = $osmxp->query("tag", $osmrel);
			foreach($osmtags as $osmtag)
				$rel['tags'][$osmtag->getAttribute('k')] = $osmtag->getAttribute('v');
			$osmmembers = $osmxp->query("member", $osmrel);
			foreach($osmmembers as $osmmember)
				$rel['roles'][] = $osmmember->getAttribute('role');
			$rel['roles'] = array_unique($rel['roles']);

			if($rel['time'] > $lasttime)
			{
				$lasttime = $rel['time'];
				$lastuser = $rel['user'];
			}

			if(array_key_exists($rel['user'], $userstats))
				$userstats[$rel['user']]++;
			else
				$userstats[$rel['user']] = 1;

			if(!array_key_exists('lcd', $rel['tags']))
				continue;
			$lcd = (int)$rel['tags']['lcd'];

			if(array_key_exists($lcd, $rels))
				$rels[$lcd] = false;
			else
				$rels[$lcd] = $rel;
		}
		ksort($rels);

		if($lasttime > $lasttime2)
			$lasttime2 = $lasttime;

		$pageok = create_head($cid, $tabcd) . "<tr><th>LCD</th><th>Type</th><th>Name</th><th>ID</th><th>User</th><th>Time</th></tr>\n";
		$pagemiss = create_head($cid, $tabcd) . "<tr><th>LCD</th><th>Type</th><th>Name</th></tr>\n";
		$pageerror = create_head($cid, $tabcd) . "<tr><th>LCD</th><th>Type</th><th>Name</th><th>ID</th><th>User</th><th>Time</th><th>Error</th></tr>\n";
		$pagenonex = create_head($cid, $tabcd) . "<tr><th>LCD</th><th>ID</th><th>User</th><th>Time</th></tr>\n";

		$total = 0;
		$missing = 0;
		$wrong = 0;
		$correct = 0;

		while($data = $result->fetch(PDO::FETCH_ASSOC))
		{
			$data = find_names($data);
			if($data2 = find_location('poffsets', $cid, $tabcd, $data['lcd']))
				$data = array_merge($data, $data2);

			//echo $data['cid'] . ":" . $data['tabcd'] . ":" . $data['lcd'] . " - " . array_desc($data) . "\n";

			$row = "<td><a href=\"tmcview.php?cid=" . $data['cid'] . "&amp;tabcd=" . $data['tabcd'] . "&amp;lcd=" . $data['lcd'] . "\">" . $data['lcd'] . "</a></td>";
			$row .= "<td>" . $data['class'] . $data['tcd'] . "." . $data['stcd'] . "</td>";
			$row .= "<td>" . array_desc($data) . "</td>";

			$total++;
			if(!array_key_exists($data['lcd'], $rels))
			{
				$pagemiss .= "<tr>" . $row . "</tr>\n";
				$missing++;
			}
			else if(!$rels[$data['lcd']])
			{
				$row .= "<td>-</td><td>-</td><td>-</td><td class=\"toomany\">too many relations found</td>";
				$pageerror .= "<tr>" . $row . "</tr>\n";
				$wrong++;
				unset($rels[$data['lcd']]);
			}
			else
			{
				$errors = array();
				$rel = $rels[$data['lcd']];
				$tags = osm_tags($data);
				$keys = array_unique(array_merge(array_keys($tags), array_keys($rel['tags'])));
				sort($keys);

				$row .= "<td><a href=\"http://www.openstreetmap.org/browse/relation/" . $rel['id'] . "\">" . $rel['id'] . "</a></td>";
				$row .= "<td>" . $rel['user'] . "</td>";
				$row .= "<td>" . gmdate("d. m. Y H:i:s T", $rel['time']) . "</td>";

				foreach($keys as $key)
				{
					if(preg_match('/^(note(:[a-z_]+)?|description(:[a-z_]+)?|intersects|([a-z_]+_)?name)(:[a-z_]+)?$/', $key))
						continue;

					$tval = (array_key_exists($key, $tags) ? $tags[$key] : "");
					$oval = (array_key_exists($key, $rel['tags']) ? $rel['tags'][$key] : "");
					if($tval !== $oval)
						$errors[] = "key:$key";
				}

				foreach($rel['roles'] as $role)
				{
					if(!preg_match('/^((positive|negative|both)(:(entry|exit|ramp|parking|restaurant|fuel|motel|attraction))?)?$/', $role))
						$errors[] = "role:$role";
				}

				if(!count($errors))
				{
					$pageok .= "<tr>" . $row . "</tr>\n";
					$correct++;
				}
				else
				{
					$row .= "<td class=\"wrong\">" . implode(", ", $errors) . "</td>";
					$pageerror .= "<tr>" . $row . "</tr>\n";
					$wrong++;
				}
				unset($rels[$data['lcd']]);
			}
		}

		$nonex = count($rels);
		foreach($rels as $lcd => $rel)
		{
			$pagenonex .= "<tr>";
			$pagenonex .= "<td><a href=\"tmcview.php?cid=$cid&amp;tabcd=$tabcd&amp;lcd=$lcd\">$lcd</a></td>";
			$pagenonex .= "<td><a href=\"http://www.openstreetmap.org/browse/relation/" . $rel['id'] . "\">" . $rel['id'] . "</a></td>";
			$pagenonex .= "<td>" . $rel['user'] . "</td>";
			$pagenonex .= "<td>" . gmdate("d. m. Y H:i:s T", $rel['time']) . "</td>";
			$pagenonex .= "</tr>\n";
		}

		$total2 += $total;
		$correct2 += $correct;
		$missing2 += $missing;
		$wrong2 += $wrong;
		$nonex2 += $nonex;

		$pageok .= create_foot();
		$pagemiss .= create_foot();
		$pageerror .= create_foot();
		$pagenonex .= create_foot();

		$fileok = "points_ok_${cid}_${tabcd}.html";
		$filemiss = "points_missing_${cid}_${tabcd}.html";
		$fileerror = "points_error_${cid}_${tabcd}.html";
		$filenonex = "points_nonex_${cid}_${tabcd}.html";

		file_put_contents($fileok, $pageok);
		file_put_contents($filemiss, $pagemiss);
		file_put_contents($fileerror, $pageerror);
		file_put_contents($filenonex, $pagenonex);

		$pagestat .= "<tr>";
		$pagestat .= "<td>" . $country['name'] . "</td>";
		$pagestat .= "<td>$cid</td>";
		$pagestat .= "<td>$tabcd</td>";
		$pagestat .= "<td>$total</td>";
		$pagestat .= "<td><a href=\"$fileok\">$correct</a></td>";
		$pagestat .= sprintf("<td><a href=\"$fileok\">%.3f%%</a></td>", $correct * 100.0 / $total);
		$pagestat .= "<td><a href=\"$filemiss\">$missing</a></td>";
		$pagestat .= sprintf("<td><a href=\"$filemiss\">%.3f%%</a></td>", $missing * 100.0 / $total);
		$pagestat .= "<td><a href=\"$fileerror\">$wrong</a></td>";
		$pagestat .= sprintf("<td><a href=\"$fileerror\">%.3f%%</a></td>", $wrong * 100.0 / $total);
		$pagestat .= "<td><a href=\"$filenonex\">$nonex</a></td>";
		$pagestat .= "<td>" . gmdate("d. m. Y H:i:s T", $lasttime) . "</td>";
		$pagestat .= "</tr>\n";
	}
}

$pagestat .= "<tr>";
$pagestat .= "<th colspan=\"3\">Sum</th>";
$pagestat .= "<td>$total2</td>";
$pagestat .= "<td>$correct2</td>";
$pagestat .= sprintf("<td>%.3f%%</td>", $correct2 * 100.0 / $total2);
$pagestat .= "<td>$missing2</td>";
$pagestat .= sprintf("<td>%.3f%%</td>", $missing2 * 100.0 / $total2);
$pagestat .= "<td>$wrong2</td>";
$pagestat .= sprintf("<td>%.3f%%</td>", $wrong2 * 100.0 / $total2);
$pagestat .= "<td>$nonex2</td>";
$pagestat .= "<td>" . gmdate("d. m. Y H:i:s T", $lasttime2) . "</td>";
$pagestat .= "</tr>\n";
$pagestat .= "</table>\n";

arsort($userstats);
$userstats = array_slice($userstats, 0, 10);

$pagestat .= "<h3>Relations by user</h3>\n";
$pagestat .= "<table class=\"statistics\">\n";
$pagestat .= "<tr><th>User</th><th>Relations</th></tr>\n";
foreach($userstats as $name => $count)
	$pagestat .= "<tr><td>$name</td><td>$count</td></tr>\n";
$pagestat .= "</table>\n";

$stoptime = microtime(true);

$pagestat .= "<h3>Summary</h3>\n";
$pagestat .= sprintf("<p>Automated check done in %.3f seconds.</p>", $stoptime - $starttime);
$pagestat .= "</body>\n";
$pagestat .= "</html>\n";

file_put_contents("points_stats.html", $pagestat);
?>
