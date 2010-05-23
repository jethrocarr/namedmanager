<?php
/*
	user/options-process.php

	Access: all users

	Allows the user to change their password and other permited values
*/


// includes
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");


if (user_online())
{
	$obj_user		= New ldap_auth_manage_user;
	$obj_user->id		= $_SESSION["user"]["id"];


	////// INPUT PROCESSING ////////////////////////

	// basic fields
	$data["gn"]			= security_form_input_predefined("any", "gn", 1, "");
	$data["sn"]			= security_form_input_predefined("any", "sn", 1, "");

	// remember these values for error handling
	$data["username"]		= security_form_input_predefined("any", "username", 1, "");
	$data["uidnumber"]		= security_form_input_predefined("int", "uidnumber", 1, "");
	$data["gidnumber"]		= security_form_input_predefined("int", "gidnumber", 1, "");



	///// ERROR CHECKING ///////////////////////

	// verify the user ID exists
	if (!$obj_user->verify_id())
	{
		log_write("error", "process", "Requested user with ID ". $obj_user->id ." does not exist");
	}


	// check password (if the user has requested to change it)
	if ($_POST["password"] || $_POST["password_confirm"])
	{
		$data["password"]		= security_form_input_predefined("any", "password", 1, "");
		$data["password_confirm"]	= security_form_input_predefined("any", "password_confirm", 1, "");

		if ($data["password"] != $data["password_confirm"])
		{
			$_SESSION["error"]["message"][]			= "Your passwords do not match!";
			$_SESSION["error"]["password-error"]		= 1;
			$_SESSION["error"]["password_confirm-error"]	= 1;
		}
	}



	//// PROCESS DATA ////////////////////////////


	if ($_SESSION["error"]["message"])
	{
		$_SESSION["error"]["form"]["user_account"] = "failed";
		header("Location: ../index.php?page=user/account.php");
		exit(0);
	}
	else
	{
		$_SESSION["error"] = array();


		/*
			Prepare Data
		*/

		// load existing data
		$obj_user->load_data();


		// update desired fields
		$obj_user->data["gn"]		= $data["gn"];
		$obj_user->data["sn"]		= $data["sn"];


		// generate new password if required		
		if ($data["password"])
		{
			$obj_user->data["userpassword_plaintext"]	= $data["password"];
		}



		/*
			Update user account details
		*/

		if ($obj_user->update())
		{
			log_write("notification", "process", "Updated account details successfully");
		}
		else
		{
			log_write("error", "process", "An error occured whilst attempting to update user record.");
		}

		
		header("Location: ../index.php?page=user/account.php");
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
