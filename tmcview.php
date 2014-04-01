<?php
if(array_key_exists('q', $_REQUEST) && (trim($_REQUEST['q']) != ''))
{
	// Search requested
	include_once("tmcsearch.php");
	tmc_search();
}
else if(!array_key_exists('cid', $_REQUEST))
{
	// No country chosen - display country list.
	include_once("tmccountries.php");
	tmc_countries();
}
else if(!array_key_exists('tabcd', $_REQUEST))
{
	// Country chosen, but no table chosen - display table list.
	include_once("tmctables.php");
	tmc_tables();
}
else if(array_key_exists('lcd', $_REQUEST))
{
	// Country, table and location chosen - display data for this location.
	include_once("tmclocation.php");
	tmc_location();
}
else if(array_key_exists('class', $_REQUEST) && array_key_exists('tcd', $_REQUEST) && array_key_exists('stcd', $_REQUEST))
{
	// Country, table and location type chosen - show list with locations of this type.
	include_once("tmclocations.php");
	tmc_locations();
}
else
{
	// Only country and table chosen - display list of location types.
	include_once("tmctypes.php");
	tmc_types();
}
?>
