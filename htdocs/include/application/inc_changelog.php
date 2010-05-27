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
	var $id_server;		// name server ID (if appropiate)
	var $id_domain;		// domain ID (if appropiate)

	var $username;



	/*
		Constructor
	*/
	function changelog()
	{
		// default to current user or "SYSTEM"
		if ($_SESSION["user"]["name"])
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



} // end of class: changelost

?>
