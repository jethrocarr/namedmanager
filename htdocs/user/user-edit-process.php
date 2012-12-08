<?php
/*
	user/user-edit-process.php

	Access: admin users only

	Updates or creates a user account based on the information provided to it.
*/


// includes
include_once("../include/config.php");
include_once("../include/amberphplib/main.php");
include_once("../include/application/main.php");


if (user_permissions_get(namedadmins))
{
	////// INPUT PROCESSING ////////////////////////

	$id				= security_form_input_predefined("int", "id_user", 0, "");
	
	$data["username"]		= security_form_input_predefined("any", "username", 1, "");
	$data["realname"]		= security_form_input_predefined("any", "realname", 1, "");
	$data["contact_email"]		= security_form_input_predefined("any", "contact_email", 1, "");

	// are we editing an existing user or adding a new one?
	if ($id)
	{
		$mode = "edit";

		// make sure the user actually exists
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM `users` WHERE id='$id' LIMIT 1";
		$sql_obj->execute();

		if (!$sql_obj->num_rows())
		{
			log_write("error", "process", "The user you have attempted to edit - $id - does not exist in this system.");
		}
	}
	else
	{
		$mode = "add";
	}


	// account options are for edits only
	if ($mode == "edit")
	{
		// general options
		$data["option_lang"]			= security_form_input_predefined("any", "option_lang", 1, "");
		$data["option_dateformat"]		= security_form_input_predefined("any", "option_dateformat", 1, "");
		// $data["option_timezone"]		= security_form_input_predefined("any", "option_timezone", 1, "");
		$data["option_shrink_tableoptions"]	= security_form_input_predefined("any", "option_shrink_tableoptions", 0, "");
		$data["option_debug"]			= security_form_input_predefined("any", "option_debug", 0, "");
		$data["option_concurrent_logins"]	= security_form_input_predefined("any", "option_concurrent_logins", 0, "");
	}


	///// ERROR CHECKING ///////////////////////

	// only enabled when doing SQL authentication
	if ($GLOBALS["config"]["AUTH_METHOD"] != "sql")
	{
		log_write("error", "page", "User options can only be configured when using local user authentication");
	}


	// make sure we don't choose a user name that has already been taken
	$sql_obj		= New sql_query;
	$sql_obj->string	= "SELECT id FROM `users` WHERE username='". $data["username"] ."'";

	if ($id)
		$sql_obj->string .= " AND id!='$id'";

	$sql_obj->execute();

	if ($sql_obj->num_rows())
	{
		log_write("error", "process", "This user name is already used for another user - please choose a unique name.");
		$_SESSION["error"]["username-error"] = 1;
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
	else
	{
		// if adding a new user, a password *must* be provided
		if ($mode == "add")
		{
			$_SESSION["error"]["message"][]			= "You must supply a password!";
			$_SESSION["error"]["password-error"]		= 1;
			$_SESSION["error"]["password_confirm-error"]	= 1;
		}
	}




	//// PROCESS DATA ////////////////////////////


	if ($_SESSION["error"]["message"])
	{
		if ($mode == "edit")
		{
			$_SESSION["error"]["form"]["user_view"] = "failed";
			header("Location: ../index.php?page=user/user-view.php&id=$id");
			exit(0);
		}
		else
		{
			$_SESSION["error"]["form"]["user_add"] = "failed";
			header("Location: ../index.php?page=user/user-add.php");
			exit(0);
		}
	}
	else
	{
		$_SESSION["error"] = array();

		if ($mode == "add")
		{
			// start the transaction
			$sql_obj = New sql_query;
			$sql_obj->trans_begin();


			// create the user account
			$id = user_newuser($data["username"], $data["password"], $data["realname"], $data["contact_email"]);

			if ($id)
			{
				// Load the user with default configuration - set the configuration values to the
				// system defaults when possible. The admin can always change them once the account creation is complete.
			
				// language
				$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'lang', 'en_us')";
				$sql_obj->execute();

				// date format
				$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'dateformat', '". sql_get_singlevalue("SELECT value FROM config WHERE name='DATEFORMAT'") ."')";
				$sql_obj->execute();

				// timezone
				//$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'timezone', '". sql_get_singlevalue("SELECT value FROM config WHERE name='TIMEZONE_DEFAULT'") ."')";
				//$sql_obj->execute();

				// table options
				$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'shrink_tableoptions', 'on')";
				$sql_obj->execute();

				// debugging
				$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'debug', 'disabled')";
				$sql_obj->execute();

				// concurrent logins
				$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'concurrent_logins', 'disabled')";
				$sql_obj->execute();
			
				// assign the user "disabled" permissions
				$sql_obj->string	= "INSERT INTO `users_permissions` (userid, permid) VALUES ('$id', '1')";
				$sql_obj->execute();
			}


			// commit/rollback
			if ($_SESSION["error"]["message"])
			{
				$sql_obj->trans_rollback();

				log_write("error", "process", "An error occured whilst attempting to create the user - No changes have been made.");
			}
			else
			{
				$sql_obj->trans_commit();

				log_write("notification", "process", "Successfully created user account. Note that the user is disabled by default, you will need to use the User Permissions page to assign them access rights.");
			}
		}
		else
		{
			// begin transaction
			$sql_obj = New sql_query;
			$sql_obj->trans_begin();


			// generate a new password and salt
			if ($data["password"])
			{
				user_changepwd($id, $data["password"]);
			}

			// update the account details
			$sql_obj->string	= "UPDATE `users` SET "
							."username='". $data["username"] ."', "
							."realname='". $data["realname"] ."', "
							."contact_email='". $data["contact_email"] ."' "
							."WHERE id='$id' LIMIT 1";

			$sql_obj->execute();



			/*
				Update user options
			*/

			// remove old user options
			$sql_obj->string	= "DELETE FROM users_options WHERE userid='$id'";
			$sql_obj->execute();


			// language
			$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'lang', '". $data["option_lang"] ."')";
			$sql_obj->execute();

			// date format
			$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'dateformat', '". $data["option_dateformat"] ."')";
			$sql_obj->execute();

			// timezone
			//$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'timezone', '". $data["option_timezone"] ."')";
			//$sql_obj->execute();

			// table options
			$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'shrink_tableoptions', '". $data["option_shrink_tableoptions"] ."')";
			$sql_obj->execute();

			// debugging
			$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'debug', '". $data["option_debug"] ."')";
			$sql_obj->execute();

			// concurrent logins
			$sql_obj->string	= "INSERT INTO users_options (userid, name, value) VALUES ($id, 'concurrent_logins', '". $data["option_concurrent_logins"] ."')";
			$sql_obj->execute();



			// commit/rollback
			$sql_obj->string = "UPDATE config SET value='update_required' WHERE name='PROCMAIL_UPDATE_STATUS' LIMIT 1";
			$sql_obj->execute();

			if ($_SESSION["error"]["message"])
			{
				$sql_obj->trans_rollback();

				log_write("error", "process", "An error occured whilst attempting to update the user - No changes have been made.");
			}
			else
			{
				$sql_obj->trans_commit();

				log_write("notification", "process", "Successfully updated user account");
			}
		}



		// Because we have changed the user's details such as their username, we need to kill all the user's
		// sessions to prevent any undesired issues from occuring.

		$sql_obj		= New sql_query;
		$sql_obj->string	= "DELETE FROM `users_sessions` WHERE userid='$id'";
		$sql_obj->execute();


		// goto view page
		header("Location: ../index.php?page=user/user-view.php&id=$id");
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
