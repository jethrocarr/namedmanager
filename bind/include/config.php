<?php
/*
	This configuration file is the main configuration file for the bind
	module of the NamedManager application.

	No adjustments should be made here, all adustments should be made in the
	associated config-settings.php file (or sometimes called config-bind.php)
*/

$GLOBALS["config"] = array();



/*
	Define Application Name & Versions
*/

// define the application details
$GLOBALS["config"]["app_name"]			= "NamedManager";
$GLOBALS["config"]["app_version"]		= "1.0.0";


/*
	Initate session variables
*/

// trick to make logging and error system work correctly for scripts.
$GLOBALS["_SESSION"]	= array();
$_SESSION["mode"]	= "cli";


/*
	Inherit User Configuration
*/
require("config-settings.php");



?>
