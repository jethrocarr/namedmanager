<?php
/*
	domains/delete-process.php

	access:
		namedadmins

	Deletes an unwanted domain.
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

	$obj_domain		= New domain;
	$obj_domain->id		= security_form_input_predefined("int", "id_domain", 1, "");


	// for error return if needed
	@security_form_input_predefined("any", "domain_name", 0, "");
	@security_form_input_predefined("any", "domain_description", 0, "");

	// confirm deletion
	@security_form_input_predefined("any", "delete_confirm", 1, "You must confirm the deletion");




	/*
		Verify Data
	*/


	// verify the selected domain exists
	if (!$obj_domain->verify_id())
	{
		log_write("error", "process", "The domain you have attempted to delete - ". $obj_domain->id ." - does not exist in this system.");
	}




	/*
		Process Data
	*/

	if (error_check())
	{
		$_SESSION["error"]["form"]["domain_delete"]	= "failed";
		header("Location: ../index.php?page=domains/delete.php&id=". $obj_domain->id ."");

		exit(0);
	}
	else
	{
		// clear error data
		error_clear();



		/*
			Delete domain
		*/

		$obj_domain->load_data();

		$obj_domain->action_delete();



		/*
			Return
		*/

		header("Location: ../index.php?page=domains/domains.php");
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
