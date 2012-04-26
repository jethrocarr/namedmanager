<?php
/*
	servers/group-delete-process.php

	access:
		namedadmins

	Deletes an unwanted server group.
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


	// for error return if needed
	@security_form_input_predefined("any", "group_name", 1, "");
	@security_form_input_predefined("any", "group_description", 0, "");

	// confirm deletion
	@security_form_input_predefined("any", "delete_confirm", 1, "You must confirm the deletion");




	/*
		Verify Data
	*/


	// verify the selected server exists
	if (!$obj_name_server_group->verify_id())
	{
		log_write("error", "process", "The server group you have attempted to delete - ". $obj_name_server_group->id ." - does not exist in this system.");
	}

	// make sure the group is empty
	if (!$obj_name_server_group->verify_empty())
	{
		log_write("error", "process", "The requested server group (". $obj_name_server_group->id .") is not empty, thus cannot be deleted. Make sure all members are assigned elsewhere first.");
		return 0;
	}


	/*
		Process Data
	*/

	if (error_check())
	{
		$_SESSION["error"]["form"]["name_server_group_delete"]	= "failed";
		header("Location: ../index.php?page=servers/group-delete.php&id=". $obj_name_server_group->id ."");

		exit(0);
	}
	else
	{
		// clear error data
		error_clear();



		/*
			Delete server group
		*/

		$obj_name_server_group->action_delete();



		/*
			Return
		*/

		header("Location: ../index.php?page=servers/groups.php");
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
