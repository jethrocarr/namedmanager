<?php
/*
	domains/edit-process.php

	access:
		namedadmins

	Updates or creates a domain name
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
	$obj_domain->id		= security_form_input_predefined("int", "id_domain", 0, "");


	// are we editing an existing domain or adding a new one?
	if ($obj_domain->id)
	{
		if (!$obj_domain->verify_id())
		{
			log_write("error", "process", "The domain you have attempted to edit - ". $obj_name_server->id ." - does not exist in this system.");
		}
		else
		{
			// load existing data
			$obj_domain->load_data();
		}
	}

	// basic fields
	$obj_domain->data["domain_name"]			= security_form_input_predefined("any", "domain_name", 1, "");
	$obj_domain->data["domain_description"]			= security_form_input_predefined("any", "domain_description", 0, "");
	$obj_domain->data["soa_hostmaster"]			= security_form_input_predefined("email", "soa_hostmaster", 1, "");
	$obj_domain->data["soa_serial"]				= security_form_input_predefined("int", "soa_serial", 1, "");
	$obj_domain->data["soa_refresh"]			= security_form_input_predefined("int", "soa_refresh", 1, "");
	$obj_domain->data["soa_retry"]				= security_form_input_predefined("int", "soa_retry", 1, "");
	$obj_domain->data["soa_expire"]				= security_form_input_predefined("int", "soa_expire", 1, "");
	$obj_domain->data["soa_default_ttl"]			= security_form_input_predefined("int", "soa_default_ttl", 1, "");




	/*
		Verify Data
	*/

	if (!$obj_domain->verify_domain_name())
	{
		log_write("error", "process", "The requested domain you are trying to add already exists!");

		error_flag_field("domain_name");
	}


	/*
		Process Data
	*/

	if (error_check())
	{
		if ($obj_domain->id)
		{
			$_SESSION["error"]["form"]["domain_edit"]	= "failed";
			header("Location: ../index.php?page=domains/view.php&id=". $obj_domain->id ."");
		}
		else
		{
			$_SESSION["error"]["form"]["domain_add"]	= "failed";
			header("Location: ../index.php?page=domains/add.php");
		}

		exit(0);
	}
	else
	{
		// clear error data
		error_clear();


		/*
			Update domain
		*/

		$obj_domain->action_update();
		$obj_domain->action_update_serial();
		$obj_domain->action_update_ns();


		/*
			Return
		*/

		header("Location: ../index.php?page=domains/view.php&id=". $obj_domain->id ."");
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
