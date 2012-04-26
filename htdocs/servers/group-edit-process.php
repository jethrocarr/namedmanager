<?php
/*
	servers/group-edit-process.php

	access:
		namedadmins

	Updates or creates a new name server group entry.
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

	$obj_name_server_group		= New name_server_group;
	$obj_name_server_group->id	= security_form_input_predefined("int", "id_name_server_group", 0, "");


	// are we editing an existing server group or adding a new one?
	if ($obj_name_server_group->id)
	{
		if (!$obj_name_server_group->verify_id())
		{
			log_write("error", "process", "The name server group you have attempted to edit - ". $obj_name_server_group->id ." - does not exist in this system.");
		}
		else
		{
			// load existing data
			$obj_name_server_group->load_data();
		}
	}

	// basic fields
	$obj_name_server_group->data["group_name"]			= security_form_input("/^\w*$/", "group_name", 1, "Group name must be a alpha numeric word with optional underscores - no spaces or other symbols.");
	$obj_name_server_group->data["group_description"]		= security_form_input_predefined("any", "group_description", 0, "");



	/*
		Verify Data
	*/

	// ensure the group name is unique
	if (!$obj_name_server_group->verify_group_name())
	{
		log_write("error", "process", "The requested group name already exists, have you checked that the group you're trying to add doesn't already exist?");

		error_flag_field("group_name");
	}


	/*
		Process Data
	*/

	if (error_check())
	{
		if ($obj_name_server_group->id)
		{
			$_SESSION["error"]["form"]["name_server_group_edit"]	= "failed";
			header("Location: ../index.php?page=servers/group-view.php&id=". $obj_name_server_group->id ."");
		}
		else
		{
			$_SESSION["error"]["form"]["name_server_group_edit"]	= "failed";
			header("Location: ../index.php?page=servers/group-add.php");
		}

		exit(0);
	}
	else
	{
		// clear error data
		error_clear();


		/*
			Update name server group (will create if nessacary)
		*/

		$obj_name_server_group->action_update();


		/*
			Return
		*/

		header("Location: ../index.php?page=servers/group-view.php&id=". $obj_name_server_group->id ."");
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
