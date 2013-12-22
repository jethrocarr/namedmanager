<?php
/*
	MASTER CONFIGURATION FILE

	This file contains key application configuration options and values for
	developers rather than users/admins.

	DO NOT MAKE ANY CHANGES TO THIS FILE, INSTEAD PLEASE MAKE ANY ADJUSTMENTS
	TO "config-settings.php" TO ENSURE CORRECT APPLICATION OPERATION.

	If config-settings.php does not exist, then you need to copy sample_config.php
	into it's place.
*/



$GLOBALS["config"] = array();



/*
	Define Application Name & Versions
*/

// define the application details
$GLOBALS["config"]["app_name"]			= "NamedManager";
$GLOBALS["config"]["app_version"]		= "1.8.0";

// define the schema version required
$GLOBALS["config"]["schema_version"]		= "20131222";



/*
	Apply required PHP settings

	These can be overridden in config-settings.php if desired
*/
ini_set('memory_limit', '256M');		// NamedManager can be a bit RAM hungry, especially if debugging is enabled 
						// (debugging == about 2-4x normal memory usage)




/*
	Session Management
*/

// Initate session variables
if (isset($_SERVER['SERVER_NAME']))
{
	// proper session variables
	session_name("namedmanager");
	session_start();
}
else
{
	// trick to make logging and error system work correctly for scripts.
	$GLOBALS["_SESSION"]	= array();
	$_SESSION["mode"]	= "cli";
}


/*
	Inherit User Configuration
*/
include("config-settings.php");


/*
	Silence warnings to avoid unexpected errors appearing on newer PHP versions
	than what the developers tested with, unless running in dev mode (in which
	case we want to see all the configured errors).
*/
if (empty($_SESSION["user"]["debug"]))
{
	ini_set("display_errors", 0);
}



/*
	Connect to Databases
*/
include("database.php");

?>
