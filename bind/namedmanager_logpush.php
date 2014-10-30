#!/usr/bin/env php
<?php
/*
	namedmanger_logpush

	Connects to NamedManager and uploads log messages from the server.

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
	Set sane PHP options required for daemonised run mode
*/

set_time_limit(0);
gc_enable();



/*
	Load options & configuration
	Note: Currently limited to --daemon only
*/

$options_all = array(
			"daemon" => array(
				"short"	=> "D",
				"long"	=> "daemon",
				"about" => "Run application as a backgrounded daemon"
				)
			);

$options_long	= array();
$options_short	= "";

foreach (array_keys($options_all) as $key)
{
	$options_long[] = $key;

	if ($options_all[$key]["short"])
	{
		if (isset($options_all[$key]["getopt"]))
		{
			$options_short .= $options_all[$key]["getopt"];
		}
		else
		{
			$options_short .= $options_all[$key]["short"];
		}
	}
	
}

$options_set = getopt($options_short, $options_long);


/*
	Daemon Mode

	By default the program runs in the foreground, however when called with the -D
	option, we want to turn this process into a proper daemon, in which case we need
	to pcntl_exec() this same program.
*/

if (isset($options_set["daemon"]))
{
	log_write("script", "debug", "Launching background process & terminating current process (Daemon mode)");

	// Re-launch the process with no arguments
	$program	= $argv[0];
	$arguments	= array();
	
	// we fork, then 
	$pid = pcntl_fork();

	if (!$pid)
	{
		// launch new instance as a backgrounded daemon
		pcntl_exec($program, $arguments);
	}

	// terminate origional parent leaving only the backgrounded processes
	exit();
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
	$GLOBALS["config"]["lock_file"] = "/var/lock/namedmanager_lock_logpush";
}
else
{
	$GLOBALS["config"]["lock_file"] = $GLOBALS["config"]["lock_file"] ."_logpush";
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
	// One problem with the locking, is that when another script has been terminated, the lock stays active until the 
	// tail process terminates - this is due to the way PHP and fgets blocks. To nicely handle this, where there is a lock
	// issue, we log a warning about it and then wait for the lock to become available.
	//
	// If this script is terminated whilst waiting, then it will cleanly exit. If the other script is terminated or ends naturally
	// will take over and continue with logging.
	//
	// See https://projects.amberdms.com/p/oss-namedmanager/issues/341/ for more reading.
	//
	log_write("warning", "script", "Unable to execute script due to active lock file ". $GLOBALS["config"]["lock_file"] .", is another instance running?");
	log_write("warning", "script", "Waiting pending availability of log file.....");
	
	// we now wait until the lock is available
	flock($fh_lock, LOCK_EX);
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


	We have a class here for handling the actual logging, it's smart enough to re-authenticate if the session
	gets terminated without dropping log messages.

	(sessions could get terminated if remote API server reboots, connection times out, no logs get generated for long
	time periods, etc)
*/


class app_main extends soap_api
{

	/*
		log_watch

		Use tail to track the file and push any new log messages to NamedManager.

	*/
	function log_watch()
	{
		while (true)
		{
			// we have a while here to handle the unexpected termination of the tail command
			// by restarting a new connection

			$handle = popen("tail -f ". $GLOBALS["config"]["log_file"] ." 2>&1", 'r');

			while(!feof($handle))
			{
				// we now do a blocking read to the EOL. This solution isn't perfect, infact it does raise a number of issues,
				// for example the pcntl signal handler code won't interrupt the block, and when the script terminates, the tail
				// process won't always close until another log message is posted, upon when the process will end.

				$buffer = fgets($handle);

				// process the log input
				//
				// example format: May 30 15:53:35 localhost named[14286]: Message
				//
				if (preg_match("/^\S*\s*\S*\s\S*:\S*:\S*\s(\S*)\snamed\S*:\s([\S\s]*)$/", $buffer, $matches))
				{
					$this->log_push(time(), $matches[2]);
				
					log_write("debug", "script", "Log Recieved: $buffer");
				}
				else
				{
					log_write("debug", "script", "Unprocessable: $buffer");
				}
			}

			pclose($handle);
		}
	}


} // end of app_main


// call class
$obj_main		= New app_main;

$obj_main->authenticate();
$obj_main->log_watch();

log_write("notification", "script", "Terminating logging process for NamedManager");

?>
