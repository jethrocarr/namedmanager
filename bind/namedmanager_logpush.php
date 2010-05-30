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
	APP_MAIN

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
				$buffer = fgets($handle);

				// process the log input
				//
				// example format: May 30 15:53:35 localhost named[14286]: Message
				//
				if (preg_match("/^\S*\s\S*\s\S*:\S*:\S*\s(\S*)\snamed\S*:\s([\S\s]*)$/", $buffer, $matches))
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
