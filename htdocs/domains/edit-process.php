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
			$obj_domain->data["domain_name"]		= security_form_input_predefined("any", "domain_name", 1, "");
			$obj_domain->data["domain_description"]		= security_form_input_predefined("any", "domain_description", 0, "");
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
		elseif ($obj_domain->data["domain_type"] == "domain_reverse_ipv4")
		{
			// fetch domain data
			$obj_domain->data["ipv4_network"]			= security_form_input_predefined("ipv4_cidr", "ipv4_network", 1, "Must supply full IPv4 network address");
			$obj_domain->data["ipv4_autofill"]			= security_form_input_predefined("checkbox", "ipv4_autofill", 0, "");
			$obj_domain->data["ipv4_autofill_forward"]		= security_form_input_predefined("checkbox", "ipv4_autofill_forward", 0, "");
			$obj_domain->data["ipv4_autofill_reverse_from_forward"]	= security_form_input_predefined("checkbox", "ipv4_autofill_reverse_from_forward", 0, "");
			$obj_domain->data["ipv4_autofill_domain"]		= security_form_input_predefined("any", "ipv4_autofill_domain", 0, "");
			$obj_domain->data["domain_description"]			= security_form_input_predefined("any", "domain_description", 0, "");


			// check CIDR
			$matches = explode("/", $obj_domain->data["ipv4_network"]);
			if (!empty($matches[0]) && !empty($matches[1]))
			{
				// set network
				$obj_domain->data["ipv4_network"]	= $matches[0];
				$obj_domain->data["ipv4_cidr"]		= $matches[1];


				// check CIDR
				if ($obj_domain->data["ipv4_cidr"] > 24)
				{
					log_write("error", "process", "CIDRs greater than /24 can not be used for reverse domains.");
					error_flag_field("ipv4_network");
				}
			}
			else
			{
				// no CIDR
				$obj_domain->data["ipv4_cidr"]		= "24";
			}


			// calculate domain name
			if ($obj_domain->data["ipv4_network"])
			{	
				$tmp_network = explode(".", $obj_domain->data["ipv4_network"]);
					
				$obj_domain->data["domain_name"]	= $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";


				// make sure autofill combinations are correct
				if ($obj_domain->data["ipv4_autofill"])
				{
					if (empty($obj_domain->data["ipv4_autofill_domain"]))
					{
						error_flag_field("ipv4_autofill_domain");
						log_write("error", "process", "A domain must be provided in order to use autofill");
					}
				}

				if ($obj_domain->data["ipv4_autofill_forward"])
				{
					// autofill must be enabled -just a safety check if the UI glitches
					if (!$obj_domain->data["ipv4_autofill"])
					{
						error_flag_field("ipv4_autofill");
						log_write("error", "process", "Select autofill if autofill forwards is desired.");
					}


					// a domain must be provided
					if (empty($obj_domain->data["ipv4_autofill_domain"]))
					{
						error_flag_field("ipv4_autofill_domain");
						log_write("error", "process", "A domain must be provided in order to use autofill forwards");
					}


					// check that the selected domain exists in this system as a forward domain
					$obj_domain_check 			= New domain;
					$obj_domain_check->data["domain_name"]	= $obj_domain->data["ipv4_autofill_domain"];

					if (!$obj_domain_check->verify_domain_name())
					{
						$obj_domain->data["ipv4_autofill_domain_id"] = $obj_domain_check->id;
					}
					else
					{
						log_write("error", "process", "The domain provided does not exist and can't be used for forwards autofill.");
						error_flag_field("ipv4_autofill_domain");
					}

					unset($obj_domain_check);
				}
			}

			// if no description, set to original IP
			if (!$obj_domain->data["domain_description"])
			{
				$obj_domain->data["domain_description"] = "Reverse domain for range ". $obj_domain->data["ipv4_network"] ."/". $obj_domain->data["ipv4_cidr"];
			}
		}
		elseif ($obj_domain->data["domain_type"] == "domain_reverse_ipv6")
		{
			// fetch domain data
			$obj_domain->data["ipv6_network"]			= security_form_input_predefined("ipv6_cidr", "ipv6_network", 1, "Must supply full IPv6 network address");
			//$obj_domain->data["ipv6_autofill"]			= security_form_input_predefined("checkbox", "ipv6_autofill", 0, "");
			//$obj_domain->data["ipv6_autofill_forward"]		= security_form_input_predefined("checkbox", "ipv6_autofill_forward", 0, "");
			$obj_domain->data["ipv6_autofill_reverse_from_forward"]	= security_form_input_predefined("checkbox", "ipv6_autofill_reverse_from_forward", 0, "");
			//$obj_domain->data["ipv6_autofill_domain"]		= security_form_input_predefined("any", "ipv6_autofill_domain", 0, "");
			$obj_domain->data["domain_description"]			= security_form_input_predefined("any", "domain_description", 0, "");


			// check CIDR
			$matches = explode("/", $obj_domain->data["ipv6_network"]);
			if (!empty($matches[0]) && !empty($matches[1]))
			{
				// set network
				$obj_domain->data["ipv6_network"]	= $matches[0];
				$obj_domain->data["ipv6_cidr"]		= $matches[1];

				// check CIDR
				if ($obj_domain->data["ipv6_cidr"] > 128 || $obj_domain->data["ipv6_cidr"] < 1)
				{
					log_write("error", "process", "Invalid CIDR, IPv6 CIDRs are between /0 and /128");
					error_flag_field("ipv6_network");
				}

				// generate domain name (IPv6 CIDR)
				$obj_domain->data["domain_name"]	= ipv6_convert_arpa($obj_domain->data["ipv6_network"] ."/". $obj_domain->data["ipv6_cidr"]);

				// if no description, set to original IP
				if (!$obj_domain->data["domain_description"])
				{
					$obj_domain->data["domain_description"] = "Reverse domain for range ". $obj_domain->data["ipv6_network"] ."/". $obj_domain->data["ipv6_cidr"];
				}

			}

		}
		else
		{
			log_write("error", "process", "Unexpected domain type, unable to process.");
		}

	}


	// standard fields
	$obj_domain->data["soa_hostmaster"]			= security_form_input_predefined("email", "soa_hostmaster", 1, "");
	$obj_domain->data["soa_serial"]				= security_form_input_predefined("int", "soa_serial", 1, "");
	$obj_domain->data["soa_refresh"]			= security_form_input_predefined("int", "soa_refresh", 1, "");
	$obj_domain->data["soa_retry"]				= security_form_input_predefined("int", "soa_retry", 1, "");
	$obj_domain->data["soa_expire"]				= security_form_input_predefined("int", "soa_expire", 1, "");
	$obj_domain->data["soa_default_ttl"]			= security_form_input_predefined("int", "soa_default_ttl", 1, "");

	// domain-group selection data
	$sql_group_obj		= New sql_query;
	$sql_group_obj->string	= "SELECT id FROM name_servers_groups";
	$sql_group_obj->execute();

	if ($sql_group_obj->num_rows())
	{
		// fetch all the name server groups and see which are selected for this domain
		$sql_group_obj->fetch_array();

		$count = 0;

		foreach ($sql_group_obj->data as $data_group)
		{
			// set the selection
			$obj_domain->data["name_server_group_". $data_group["id"] ] = @security_form_input_predefined("checkbox", "name_server_group_". $data_group["id"], 0, "");

			// count selected groups
			if (!empty($obj_domain->data["name_server_group_". $data_group["id"] ]))
			{
				$count++;
			}
		}

		if (!$count)
		{
			error_flag_field("domain_message");
			log_write("error", "process", "You must select at least one name server group for the domain to belong to.");
		}
	}




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
			Start DB Transaction
		*/

		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			Handle CIDR lower than /24

			For standard domains or /24 reverse domains, we simply go and create the appropiate domain. For domains with
			lower CIDRs, we need to generate multiple /24 domains.
		*/

		if (!empty($obj_domain->data["ipv4_cidr"]) && ($obj_domain->data["ipv4_cidr"] > 1 && $obj_domain->data["ipv4_cidr"] < 24))
		{
			/*
				Large reverse IPv4 domain, requires splitting into multiple domains.
			*/

			$networks = ipv4_split_to_class_c($obj_domain->data["ipv4_network"] ."/". $obj_domain->data["ipv4_cidr"]);


			foreach ($networks as $classc)
			{
				// copy main domain
				$obj_domain_net		= clone $obj_domain;
				$obj_domain_net->id	= 0;

				// set network range to name
				$obj_domain_net->data["ipv4_network"]	= $classc;

				$tmp_network					= explode(".", $obj_domain_net->data["ipv4_network"]);
				$obj_domain_net->data["domain_name"]		= $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";

				$obj_domain_net->data["domain_description"]	.= " ($classc/24)";


				// check if the domain already exists
				if (!$obj_domain_net->verify_domain_name())
				{
					// domain is in use - we skip and move on, this is designed to better support
					// users who are expanding their use base

					log_write("notification", "process", "No changes made to domain ". $obj_domain_net->data["domain_name"] ." due to existing entry");
				}
				else
				{
					// update/create domain
					$obj_domain_net->action_update();

					// handle IPv4 reverse domains
					if ($obj_domain_net->data["ipv4_autofill_domain"])
					{
						// this is a new domain, we need to seed the domain, by calculating all the addresses
						// in the domain and then creating a record for each one.

						$obj_domain_net->action_autofill_reverse();
					}

					// handle IPv4 forward domains
					if ($obj_domain_net->data["ipv4_autofill_forward"])
					{
						$obj_domain_net->action_autofill_forward();
					}

					// update serial & NS records
					$obj_domain_net->action_update_serial();
					$obj_domain_net->action_update_ns();
				}


				unset($obj_domain_net);

			} // end of loop through networks

			unset($obj_domain);

		}
		else
		{
			/*
				Standard domain or single reverse domain
			*/

			// update domain details
			$obj_domain->action_update();

			// handle IPv4 reverse domains
			if (!empty($obj_domain->data["ipv4_autofill_domain"]))
			{
				// this is a new domain, we need to seed the domain, by calculating all the addresses
				// in the domain and then creating a record for each one.

				$obj_domain->action_autofill_reverse();
			}

			// handle IPv4 forward domains
			if (!empty($obj_domain->data["ipv4_autofill_forward"]))
			{
				$obj_domain->action_autofill_forward();
			}

			// update serial & NS records
			$obj_domain->action_update_serial();
			$obj_domain->action_update_ns();
		}


		/*
			Final
		*/
		
		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "domain", "An error occured whilst trying to adjust the domain.");

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
			$sql_obj->trans_commit();

			$_SESSION["notification"]["message"] = array("Domain updated successfully - name servers scheduled to reload with new domain configuration");


			if (empty($networks))
			{
				// multiple domains
				header("Location: ../index.php?page=domains/view.php&id=". $obj_domain->id ."");
				exit(0);
			}
			else
			{
				// single domain
				header("Location: ../index.php?page=domains/domains.php");
				exit(0);
			}
		}


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
