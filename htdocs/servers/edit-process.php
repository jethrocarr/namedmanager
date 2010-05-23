<?php
/*
	servers/edit-process.php

	access:
		namedadmins

	Updates or creates a new name server entry.
*/


// includes
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");


if (user_permissions_get('namedadmins'))
{
	/*
		Form Input
	*/

	$obj_name_server		= New name_server;
	$obj_name_server->id		= security_form_input_predefined("int", "id_name_server", 0, "");


	// are we editing an existing server or adding a new one?
	if ($obj_name_server->id)
	{
		if (!$obj_name_server->verify_id())
		{
			log_write("error", "process", "The name server you have attempted to edit - ". $obj_name_server->id ." - does not exist in this system.");
		}
		else
		{
			// load existing data
			$obj_name_server->load_data();
		}
	}

	// basic fields
	$obj_name_server->data["server_name"]			= security_form_input_predefined("any", "server_name", 1, "");
	$obj_name_server->data["server_description"]		= security_form_input_predefined("any", "server_description", 0, "");
	$obj_name_server->data["server_type"]			= security_form_input_predefined("any", "server_type", 1, "");

	if ($obj_name_server->data["server_type"] == "api")
	{
		$obj_name_server->data["api_auth_key"]		= security_form_input_predefined("any", "api_auth_key", 1, "");
	}




	/*
		Verify Data
	*/

	// ensure the server name is unique
	if (!$obj_name_server->verify_server_name())
	{
		log_write("error", "process", "The requested server name already exists, have you checked that the server you're trying to add doesn't already exist?");

		error_flag_field("server_name");
	}


	/*
		Process Data
	*/

	if (error_check())
	{
		if ($obj_name_server->id)
		{
			$_SESSION["error"]["form"]["name_server_edit"]	= "failed";
			header("Location: ../index.php?page=servers/view.php&id=". $obj_name_server->id ."");
		}
		else
		{
			$_SESSION["error"]["form"]["name_server_edit"]	= "failed";
			header("Location: ../index.php?page=servers/add.php");
		}

		exit(0);
	}
	else
	{
		// clear error data
		error_clear();


		/*
			Update name server
		*/

		$obj_name_server->action_update();


		/*
			Return
		*/

		header("Location: ../index.php?page=servers/view.php&id=". $obj_name_server->id ."");
		exit(0);


	} // if valid data input
	
	
} // end of "is user logged in?"
else
{
	// user does not have permissions to access this page.
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>
