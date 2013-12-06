<?php
/*
	include/application/inc_changelog.php

	Provides functions for logging all changes made for audit purposes.
*/




/*
	CLASS CHANGELOG

	Functions for quering and updating logs.
*/
class changelog
{
	var $id_server = 0;		// name server ID (if appropiate)
	var $id_domain = 0;		// domain ID (if appropiate)

	var $username;



	/*
		Constructor
	*/
	function changelog()
	{
		// default to current user or "SYSTEM"
		if (isset($_SESSION["user"]["name"]))
		{
			$this->username = $_SESSION["user"]["name"];
		}
		else
		{
			$this->username = "SYSTEM";
		}

	} // end of changelog()



	/*
		log_post

		Creates a new log entry based on the supplied information.

		Fields
		log_type		Type of log message - "audit" or "server"
		log_contents		Contents.
		timestamp		(optional) timestamp override

		Results
		0	Failure
		1	Success
	*/
	function log_post($log_type, $log_contents, $timestamp = NULL)
	{
		log_debug("changelog", "Executing log_post($log_type, $log_contents, $timestamp)");

		// check audit logging
		if (!$GLOBALS["config"]["FEATURE_LOGS_AUDIT"] && $log_type == "audit")
		{
			// audit logging is disabled
			return 0;
		}

		// do retention clean check
		if ($GLOBALS["config"]["LOG_RETENTION_PERIOD"])
		{
			// check when we last ran a retention clean
			if ($GLOBALS["config"]["LOG_RETENTION_CHECKTIME"] < (time() - 86400))
			{
				$this->log_retention_clean();
			}
		}

		if (empty($timestamp))
		{
			$timestamp = time();
		}

		// write log
		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO logs (id_server, id_domain, username, timestamp, log_type, log_contents) VALUES ('". $this->id_server ."', '". $this->id_domain ."', '". $this->username ."', '$timestamp', '$log_type', '$log_contents')";
		$sql_obj->execute();

		// update last sync on name server
		if ($this->id_server)
		{
			$obj_server		= New name_server;
			$obj_server->id		= $this->id_server;
			$obj_server->action_update_log_version($timestamp);
		}


		return 1;

	} // end of log_post



	/*
		log_retention_clean

		Cleans the log table of outdated records.

		This process needs to take place at least every day to ensure speedy performance and is triggered from either
		a log API call or an audit log entry (since there is no guarantee that either logging method is going to be enabled,
		we have to trigger on any.)

		Returns
		0	No log clean requires
		1	Performed log clean.
	*/

	function log_retention_clean()
	{
		log_write("debug", "changelog", "Executing log_retention_clean()");
		log_write("debug", "changelog", "A retention clean is required - last one was more than 24 hours ago.");

		// calc date to clean up to
		$clean_time	= time() - ($GLOBALS["config"]["LOG_RETENTION_PERIOD"] * 86400);
		$clean_date	= time_format_humandate($clean_time);


		// clean
		$obj_sql_clean		= New sql_query;
		$obj_sql_clean->string	= "DELETE FROM logs WHERE timestamp <= '$clean_time'";
		$obj_sql_clean->execute();

		$clean_removed = $obj_sql_clean->fetch_affected_rows();

		unset($obj_sql_clean);


		// update rentention time check
		$obj_sql_clean		= New sql_query;
		$obj_sql_clean->string	= "UPDATE `config` SET value='". time() ."' WHERE name='LOG_RETENTION_CHECKTIME' LIMIT 1";
		$obj_sql_clean->execute();

		unset($obj_sql_clean);


		// add audit entry - we have to set the LOG_RETENTION_CHECKTIME variable here to avoid
		// looping the program, as the SQL change above won't be applied until the current transaction
		// is commited.

		$GLOBALS["config"]["LOG_RETENTION_CHECKTIME"] = time();
		$this->log_post("audit", "Automated log retention clean completed, removed $clean_removed records order than $clean_date");


		// complete
		log_write("debug", "changelog", "Completed retention log clean, removed $clean_removed log records older than $clean_date");

		return 1;
	}



} // end of class: changelost

?>
