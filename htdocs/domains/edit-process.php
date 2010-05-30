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

			// fetch domain data
			$obj_domain->data["domain_name"]	= security_form_input_predefined("any", "domain_name", 1, "");
		}
	}
	else
	{
		// new domain, can have some special input like IPV4 reverse
		$obj_domain->data["domain_type"]	= security_form_input_predefined("any", "domain_type", 1, "");

		if ($obj_domain->data["domain_type"] == "domain_standard")
		{
			$obj_domain->data["domain_name"]		= security_form_input_predefined("any", "domain_name", 1, "");
			$obj_domain->data["domain_description"]		= security_form_input_predefined("any", "domain_description", 0, "");
		}
		else
		{
			// fetch domain data
//			$obj_domain->data["ipv4_help"]			= security_form_input_predefined("any", "ipv4_help", 1, "");
			$obj_domain->data["ipv4_network"]		= security_form_input_predefined("ipv4", "ipv4_network", 1, "Must supply full IPv4 network address");
//			$obj_domain->data["ipv4_subnet"]		= security_form_input_predefined("int", "ipv4_subnet", 1, "");
			$obj_domain->data["ipv4_autofill"]		= security_form_input_predefined("any", "ipv4_autofill", 0, "");
			$obj_domain->data["ipv4_autofill_domain"]	= security_form_input_predefined("any", "ipv4_autofill_domain", 0, "");
			$obj_domain->data["domain_description"]		= security_form_input_predefined("any", "domain_description", 0, "");


			// calculate domain name
			if ($obj_domain->data["ipv4_network"])
			{	
				$tmp_network = explode(".", $obj_domain->data["ipv4_network"]);
					
				$obj_domain->data["domain_name"]	= $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";


/*
				TODO: does the RFC standards and Bind allow for larger than /24?

				switch ($obj_domain->data["ipv4_subnet"])
				{
					case "24":
						$obj_domain->data["domain_name"]	= $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";
					break;

					case "16":
						$obj_domain->data["domain_name"]	= $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";
					break;

					case "8":
						$obj_domain->data["domain_name"]	= $tmp_network[0] .".in-addr.arpa";
					break;

					default:
						log_write("error", "process", "Invalid subnet of ". $obj_domain->data["ipv4_subnet"] ." supplied!");
					break;
				}	
*/
			}


			// if no description, set to original IP
			if (!$obj_domain->data["domain_description"])
			{
				$obj_domain->data["domain_description"] = "Reverse domain for range ". $obj_domain->data["ipv4_network"] ." with subnet of /". $obj_domain->data["ipv4_subnet"] ."";
			}
		}

	}


	// standard fields
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
		if (isset($obj_domain->data["ipv4_network"]))
		{
			log_write("error", "process", "The requested IP range already has reverse DNS entries!");

			error_flag_field("ipv4_network");
		}
		else
		{
			log_write("error", "process", "The requested domain you are trying to add already exists!");

			error_flag_field("domain_name");
		}
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

		// update domain details
		$obj_domain->action_update();


		// handle IPv4 reverse domains
		if ($obj_domain->data["ipv4_autofill_domain"])
		{
			// this is a new domain, we need to seed the domain, by calculating all the addresses
			// in the domain and then creating a record for each one.

			$obj_record		= New domain_records;
			$obj_record->id		= $obj_domain->id;
			$obj_record->data	= $obj_domain->data;	// shortcut load

			$tmp_network = explode(".", $obj_domain->data["ipv4_network"]);


			// assuming /24 only
			for ($i=1; $i < 255; $i++)
			{
				$obj_record->id_record			= NULL;		// we are reusing objects, better blank the ID just-in-case.

				$obj_record->data_record["type"]	= "PTR";
				$obj_record->data_record["name"]	= $i;
				$obj_record->data_record["content"]	= $tmp_network[0] ."-". $tmp_network[1] ."-". $tmp_network[2] ."-$i.". $obj_domain->data["ipv4_autofill_domain"];
				$obj_record->data_record["ttl"]		= $obj_domain->data["soa_default_ttl"];
			
				$obj_record->action_update_record();
			}
		}



		// update serial & NS records
		$obj_domain->action_update_serial();
		$obj_domain->action_update_ns();


		/*
			Return
		*/

		$_SESSION["notification"]["message"] = array("Domain updated successfully - name servers scheduled to reload with new domain configuration");

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
