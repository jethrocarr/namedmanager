<?php
/*
	namedmanager_bind_configwriter

	Connects to NamedManager and pulls down configuration, then generates Bind
	configuration files from it.


	Copyright (c) 2010 Amberdms Ltd

	Licensed under the GNU AGPL.
*/



/*
	CONFIGURATION
*/

require("include/config.php");
require("include/amberphplib/main.php");
require("include/application/main.php");



/*
	APPLICATION
*/

$obj_bind_api		= New bind_api();



// verify configuration file permissions
if (!$obj_bind_api->check_permissions())
{
	die("Fatal Error");
}

// authenticate with the API
if (!$obj_bind_api->authenticate())
{
	die("Fatal Error");
}

// check if configuration is up to date or not
if (!$config_version = $obj_bind_api->check_update_version())
{
	// all good, close
	exit(0);
}
else
{
	// need to update configuration
	$obj_bind_api->action_update();

	// confirm success
	if (!error_check())
	{
	//	$obj_bind_api->set_update_version($config_version);
		log_write("debug", "script", "VERSION UPDATE DISABLED (TODO)");
	}
	else
	{
		log_write("error", "script", "Some errors occured whilst attempting to deploy domain configuration");
	}
}







?>
