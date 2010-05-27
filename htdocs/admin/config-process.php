<?php
/*
	admin/config-process.php
	
	Access: namedadmins only

	Updates the system configuration.
*/


// includes
include_once("../include/config.php");
include_once("../include/amberphplib/main.php");


if (user_permissions_get("namedadmins"))
{
	/*
		Fetch Configuration Data
	*/

	$data["ZONE_DB_TYPE"]			= @security_form_input_predefined("any", "ZONE_DB_TYPE", 1, "");
	$data["ZONE_DB_HOST"]			= @security_form_input_predefined("any", "ZONE_DB_HOST", 0, "");
	$data["ZONE_DB_NAME"]			= @security_form_input_predefined("any", "ZONE_DB_NAME", 1, "");
	$data["ZONE_DB_USERNAME"]		= @security_form_input_predefined("any", "ZONE_DB_USERNAME", 1, "");
	$data["ZONE_DB_PASSWORD"]		= @security_form_input_predefined("any", "ZONE_DB_PASSWORD", 0, "");

	$data["DEFAULT_HOSTMASTER"]		= security_form_input_predefined("email", "DEFAULT_HOSTMASTER", 1, "");
	$data["DEFAULT_TTL_SOA"]		= security_form_input_predefined("int", "DEFAULT_TTL_SOA", 1, "");
	$data["DEFAULT_TTL_NS"]			= security_form_input_predefined("int", "DEFAULT_TTL_NS", 1, "");
	$data["DEFAULT_TTL_MX"]			= security_form_input_predefined("int", "DEFAULT_TTL_MX", 1, "");
	$data["DEFAULT_TTL_OTHER"]		= security_form_input_predefined("int", "DEFAULT_TTL_OTHER", 1, "");

	$data["DATEFORMAT"]			= security_form_input_predefined("any", "DATEFORMAT", 1, "");
	$data["TIMEZONE_DEFAULT"]		= security_form_input_predefined("any", "TIMEZONE_DEFAULT", 1, "");



	/*
		Test Zone Database
	*/

	if ($data["ZONE_DB_TYPE"] == "powerdns_mysql")
	{
		$obj_sql = New sql_query;

		if (!$obj_sql->session_init("mysql", $data["ZONE_DB_HOST"], $data["ZONE_DB_NAME"], $data["ZONE_DB_USERNAME"], $data["ZONE_DB_PASSWORD"]))
		{
			log_write("error", "process", "Unable to connect to powerdns-compliant zone database!");

			error_flag_field("ZONE_DB_HOST");
			error_flag_field("ZONE_DB_NAME");
			error_flag_field("ZONE_DB_USERNAME");
			error_flag_field("ZONE_DB_PASSWORD");
		}
		else
		{
			log_write("notification", "process", "Tested successful connection to powerdns-compliant zone database");

			$obj_sql->session_terminate();
		}

	}
	else
	{
		log_write("notification", "process", "Using internal application database for record storage");
	}


	/*
		Process Data
	*/
	if ($_SESSION["error"]["message"])
	{
		$_SESSION["error"]["form"]["config"] = "failed";
		header("Location: ../index.php?page=admin/config.php");
		exit(0);
	}
	else
	{
		$_SESSION["error"] = array();

		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();

	
		/*
			Update all the config fields

			We have already loaded the data for all the fields, so simply need to go and set all the values
			based on the naming of the $data array.
		*/

		foreach (array_keys($data) as $data_key)
		{
			$sql_obj->string = "UPDATE config SET value='". $data[$data_key] ."' WHERE name='$data_key' LIMIT 1";
			$sql_obj->execute();
		}


		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "process", "An error occured whilst updating configuration, no changes have been applied.");
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "process", "Configuration Updated Successfully");
		}

		header("Location: ../index.php?page=admin/config.php");
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
