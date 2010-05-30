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
	Setup FIFO Pipe
*/

if (!file_exists($GLOBALS["config"]["log_pipe"]))
{
	// create pipe for communicating log files from freeradius
	if (!posix_mkfifo($GLOBALS["config"]["log_pipe"], 0770))
	{
		log_write("error", "Unable to create named pipe file ". $GLOBALS["config"]["log_pipe"] ."");
		die("Fatal Error");
	}

	// set ownership
	chmod($GLOBALS["config"]["log_pipe"], 0770);
	chown($GLOBALS["config"]["log_pipe"], $GLOBALS["config"]["log_user"]);
	chgrp($GLOBALS["config"]["log_pipe"], $GLOBALS["config"]["log_user"]);

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

		Watch the FIFO pipe for new messages and send to log_push
	*/
	function log_watch()
	{
		while (file_exists($GLOBALS["config"]["log_pipe"]))
		{
			// read the next line
			$fh		= fopen($GLOBALS["config"]["log_pipe"], "r");
			$line_raw	= fread($fh, 8192);

			$lines = explode("\n", $line_raw);

			foreach ($lines as $line)
			{
				if ($line != "\n")
				{
					// process the log input
					//
					// example format: May 30 15:53:35 localhost named[14286]: Message
					//
					if (preg_match("/^\S*\s\S*\s\S*:\S*:\S*\s(\S*)\snamed\S*:\s([\S\s]*)$/", $line, $matches))
					{
						$this->log_push(time(), $matches[2]);
					
						log_write("debug", "script", "Log Recieved: $line");
					}
					else
					{
						log_write("debug", "script", "Unprocessable: $line");
					}
				}
			}
		}
	}


} // end of app_main


// call class
$obj_main		= New app_main;

$obj_main->authenticate();
$obj_main->log_watch();

log_write("notification", "script", "Terminating logging process for NamedManager");

?>
