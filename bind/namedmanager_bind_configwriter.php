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
	VERIFY LOG FILE ACCESS
*/

if (!is_readable($GLOBALS["config"]["log_file"]))
{
	log_write("error", "script", "Unable to read log file ". $GLOBALS["config"]["log_file"] ."");
	die("Fatal Error");
}



/*
	CHECK LOCK FILE

	We use exclusive file locks in non-blocking mode in order to check whether or not the script is already
	running to prevent any duplicate instances of it.

	The lock uses a file, but the file isn't actually the decider of the lock - so if the script is killed and
	doesn't properly clean up the lock file, it won't prevent the script starting again, as the actual lock
	determination is done using flock()
*/

if (empty($GLOBALS["config"]["lock_file"]))
{
	$GLOBALS["config"]["lock_file"] = "/var/lock/namedmanager_lock_configwriter";
}
else
{
	$GLOBALS["config"]["lock_file"] = $GLOBALS["config"]["lock_file"] ."_configwriter";
}

if (!file_exists($GLOBALS["config"]["lock_file"]))
{
	touch($GLOBALS["config"]["lock_file"]);
}

$fh_lock = fopen($GLOBALS["config"]["lock_file"], "r+");

if (flock($fh_lock, LOCK_EX | LOCK_NB))
{
	log_write("debug", "script", "Obtained filelock");
}
else
{
	log_write("warning", "script", "Unable to execute script due to active lock file ". $GLOBALS["config"]["lock_file"] .", is another instance running?");
	die("Lock Conflict ". $GLOBALS["config"]["lock_file"] ."\n");
}


// Establish lockfile deconstructor - this is purely for a tidy up process, the file's existance doesn't actually
// determine the lock.
function lockfile_remove()
{
	// delete lock file
	if (!unlink($GLOBALS["config"]["lock_file"]))
	{
		log_write("error", "script", "Unable to remove lock file ". $GLOBALS["config"]["lock_file"] ."");
	}
}

register_shutdown_function('lockfile_remove');





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
		$obj_bind_api->set_update_version($config_version);
	}
	else
	{
		log_write("error", "script", "Some errors occured whilst attempting to deploy domain configuration");
	}
}







?>
