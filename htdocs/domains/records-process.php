<?php
/*
	domains/records-process.php

	access:
		namedadmins

	Updates DNS records for the selected domain name.
*/

// includes
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");



if (user_permissions_get("namedadmins"))
{
	$obj_domain	= New domain;


	/*
		Check for max_input_vars error

		The default limit for PHP is to allow a maxium of 1000 POST values to be passed through. This can sometimes be
		reached quite easily with domain records where the administrator of NamedManager has configured high limits
		for PAGINATION_DOMAIN_RECORDS.

		Whilst the admin page will warn and advise the recommended maximum, we should also handle gracefully here
		to reduce support headaches.
	*/

	$max_input_vars = @ini_get('max_input_vars');

	if (empty($max_input_vars))
	{
		// PHP defaults if we can't query
		$max_input_vars = 1000;
	}

	if (count($_POST) >= $max_input_vars)
	{
		log_write("error", "process", "A fatal error occurred with form submission due to too many records being posted for the configured server environment. To resolve, please lower PAGINATION_DOMAIN_RECORDS in NamedManager, or increase max_input_vars in the php.ini configuration to allow more records to be submitted concurrently");
	}



	/*
		Import form data
	*/

	$obj_domain->id				= @security_form_input_predefined("int", "id_domain", 1, "");

	// determine number of rows
	$data["ns"]["num_records"]		= @security_form_input_predefined("int", "num_records_ns", 0, "");
	$data["mx"]["num_records"]		= @security_form_input_predefined("int", "num_records_mx", 0, "");
	$data["custom"]["num_records"]		= @security_form_input_predefined("int", "num_records_custom", 0, "");

	$data["records"]			= array();
	$data["reverse"]			= array();


	/*
		Fetch & Verify NS record data
	*/

	for ($i = 0; $i < $data["ns"]["num_records"]; $i++)
	{
		/*
			Fetch data
		*/
		$data_tmp			= array();
		$data_tmp["id"]			= @security_form_input_predefined("int", "record_ns_". $i ."_id", 0, "");
		$data_tmp["type"]		= "NS";
		$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_ns_". $i ."_ttl", 0, "");
		$data_tmp["name"]		= @security_form_input_predefined("any", "record_ns_". $i ."_name", 0, "");
		$data_tmp["content"]		= @security_form_input_predefined("any", "record_ns_". $i ."_content", 0, "");
		$data_tmp["delete_undo"]	= @security_form_input_predefined("any", "record_ns_". $i ."_delete_undo", 0, "");
		

		/*
			Process Raw Data
		*/
		if (empty($data_tmp["name"]))
		{
			$data_tmp["name"] = "@";
		}

		if ($data_tmp["id"] && $data_tmp["delete_undo"] == "true")
		{
			$data_tmp["mode"] = "delete";
		}
		else
		{
			if (!empty($data_tmp["content"]) && $data_tmp["delete_undo"] == "false")
			{
				$data_tmp["mode"] = "update";
			}
		}

		$data["records"][] = $data_tmp;


		/*
			Error Checking
		*/
	}



	/*
		Fetch & Verify MX record data
	*/

	for ($i = 0; $i < $data["mx"]["num_records"]; $i++)
	{
		/*
			Fetch Data
		*/
		$data_tmp			= array();
		$data_tmp["id"]			= @security_form_input_predefined("int", "record_mx_". $i ."_id", 0, "");
		$data_tmp["type"]		= "MX";
		$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_mx_". $i ."_ttl", 0, "");
		$data_tmp["prio"]		= @security_form_input_predefined("any", "record_mx_". $i ."_prio", 0, "");
		$data_tmp["name"]		= @security_form_input_predefined("any", "record_mx_". $i ."_name", 0, "");
		$data_tmp["content"]		= @security_form_input_predefined("any", "record_mx_". $i ."_content", 0, "");
		$data_tmp["delete_undo"]	= @security_form_input_predefined("any", "record_mx_". $i ."_delete_undo", 0, "");
		

		/*
			Process Raw Data
		*/
		if (empty($data_tmp["name"]))
		{
			$data_tmp["name"] = "@";
		}

		if ($data_tmp["id"] && $data_tmp["delete_undo"] == "true")
		{
			$data_tmp["mode"] = "delete";
		}
		else
		{
			if (!empty($data_tmp["content"]) && $data_tmp["delete_undo"] == "false")
			{
				$data_tmp["mode"] = "update";
			}
		}

		$data["records"][] = $data_tmp;
	}


	/*
		Fetch and verify custom records - note that the brower must be running javascript
		in order for this function to execute correctly.
	*/

	if (is_array($_SESSION['form']['domain_records'][$obj_domain->id]))
	{
		foreach($_SESSION['form']['domain_records'][$obj_domain->id] as $page => $records)
		{
			foreach($records as $record)
			{
				$data['records'][] = $record;
			}
		}
	}


	//print "<pre>";
	//print_r($_SESSION["form"][$obj_domain->id]);
	//print "</pre>";
	//die("foo");


	/*
		Validate Records
	*/


	// initate object
	$obj_domain_records               = New domain_records;
	$obj_domain_records->id           = $obj_domain->id;

	// validate records
	$data_validated = $obj_domain_records->validate_custom_records($data['records']);

	$data['records']	= $data_validated['records'];
	$data['reverse']	= $data_validated['reverse'];

/*
	print "<pre>";
	print_r($data);
	print "</pre>";
	die("debug halt");
*/

	/*
		Error Handling
	*/

	// return to input page in event of an error
	if (error_check())
	{
		// if there is an error check for the existance of a form_session, this will retain the session data for custom records if the form fails
		// otherwise on the next load of the domain records page the custom section will be reset
		if ($form_session = @security_form_input_predefined("int", "form_session", 0, "") !== 0)
		{
			$_SESSION['form']['domain_records'][$obj_domain->id]['form_session'] = $form_session;
		}

		$_SESSION["error"]["form"]["domain_records"] = "failed";
		header("Location: ../index.php?page=domains/records.php&id=". $obj_domain->id ."");
		exit(0);
	}



	/*
		Transaction Start
	*/

	$sql_obj = New sql_query;
	$sql_obj->trans_begin();



	/*
		Update Domain
	*/

	log_write("debug", "process", "Updating main domain");


	// fetch all DNS records
	$obj_domain->load_data();
	$obj_domain->load_data_record_all();

	// update records
	foreach ($data["records"] as $record)
	{
		if (!empty($record["mode"]))
		{
			$obj_record		= New domain_records;
			
			$obj_record->id		= $obj_domain->id;
			$obj_record->data	= $obj_domain->data;		// copy domain data from existing object to save time & SQL queries

			$obj_record->id_record	= $record["id"];
			$obj_record->load_data_record();			// load record data
			

			if ($record["mode"] == "update")
			{
				// data sent through, we should update an existing record. But first, let's check if we actually need to
				// make a change or not.

				if ($obj_record->data_record["name"]		!= $record["name"]
					|| $obj_record->data_record["type"]	!= $record["type"]
					|| $obj_record->data_record["content"]	!= $record["content"]
					|| $obj_record->data_record["ttl"]	!= (int)$record["ttl"]
					|| $obj_record->data_record["prio"]	!= (int)$record["prio"]
					|| $record["reverse_ptr"]		!= $record["reverse_ptr_orig"]
					)
				{
					/*
						Update record
					*/
					log_write("debug", "process", "Updating record ". $record["id"] ." due to changed details");
			
					$obj_record->data_record["name"]	= $record["name"];
					$obj_record->data_record["type"]	= $record["type"];
					$obj_record->data_record["content"]	= $record["content"];
					$obj_record->data_record["ttl"]		= $record["ttl"];
					$obj_record->data_record["prio"]	= $record["prio"];

					$obj_record->action_update_record();



					/*
						Update reverse PTR (if required)

						At this stage we have already validated the reverse IP domains and we know
						that the reverse request is valid and what the id of the domain is. Really
						all that we need to do is set the details for the record create/update
					*/
					if ($record["reverse_ptr"])
					{
						log_write("debug", "process", "Updating reverse PTR record for ". $record["name"] ."--&gt; ". $record["content"] ."");


						$obj_ptr		= New domain_records;

						$obj_ptr->id		= $record["reverse_ptr_id_domain"];	// will always be set
						$obj_ptr->id_record	= $record["reverse_ptr_id_record"];	// might be set, if not, a new record will be added

						$obj_ptr->load_data();


						if ($obj_ptr->id_record)
						{
							$obj_ptr->load_data_record();
						}


						// fetch host portion of IP address
						switch (ip_type_detect($record["content"]))
						{
							case "6":
								$ip_ptr = ipv6_convert_arpa( $record["content"] );
							break;

							case "4":
							default:
								$tmp	= explode(".", $record["content"]);
								$ip_ptr = $tmp[3];
							break;
						}


						// standard reverse record details
						$obj_ptr->data_record["type"]		= "PTR";
						$obj_ptr->data_record["ttl"]		= $record["ttl"];
						$obj_ptr->data_record["name"]		= $ip_ptr;


						// make sure we are using the FQDN
						if ($record["name"] == "@" || $record["name"] == "*" || preg_match("/^\*\.[A-Za-z0-9:._-]+$/", $record["name"]))
						{
							// @ is a special value, means set to the domain name
							// * is a wild card, reverse DNS can only be the domain name itself

							$obj_ptr->data_record["content"]	= $obj_domain->data["domain_name"];
						}
						elseif (strpos($record["name"], "."))
						{
							// already a FQDN, pass unaltered.
							$obj_ptr->data_record["content"]	= $record["name"];
						}
						else
						{
							// standard A record
							$obj_ptr->data_record["content"]	= $record["name"] .".". $obj_domain->data["domain_name"];
						}


						$obj_ptr->action_update_record();

						unset($obj_ptr);
					}



				}
				else
				{
					log_write("debug", "process", "Not updating record ". $record["id"] ." due to no change in details");
				}
			}
			elseif ($record["mode"] == "delete")
			{
				$obj_record->action_delete_record();

				// note: we intentionally don't delete the reverse PTR
			}
		}
		else
		{
			// new row but empty/deleted
		}

	}

	// update domain
	$obj_domain->action_update_serial();
	$obj_domain->action_update_ns();


	// clear messages & replace with custom one
	$_SESSION["notification"]["message"] = array("Domain updated successfully - name servers scheduled to reload with new domain configuration");

	// clear the form_session as the update has been successfully updated
	$_SESSION['form']['domain_records']['form_session'] = 0;



	/*
		Update reverse domains (if appropiate)

		If there are any reverse domain PTR records set, we will need to update the 
		serial on the domain to reflect the changes.
	*/

	log_write("debug", "process", "Updating serials for reverse domains");

	if ($data["reverse"])
	{
		foreach ($data["reverse"] as $id_domain)
		{
			$obj_reverse			= New domain;
			$obj_reverse->id		= $id_domain;

			$obj_reverse->load_data();
			$obj_reverse->action_update_serial();

			log_write("notification", "process", "Updating serials for reverse domain ". $obj_reverse->data["domain_name"] ."");
		}
	}

	
	$data["reverse"][] = $obj_record->id;





	if (!error_check())
	{

		$sql_obj->trans_commit();
	}
	else
	{
		// error encountered
		log_write("error", "process", "An unexpected error occured, the domain remains unchanged");

		$sql_obj->trans_rollback();
	}

	// display updated details
	header("Location: ../index.php?page=domains/records.php&id=". $obj_domain->id);
	exit(0);
	
}
else
{
	// user does not have perms to view this page/isn't logged on
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>
