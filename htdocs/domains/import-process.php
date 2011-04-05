<?php
/*
	domains/import-process.php

	access:
		namedadmins


	Processes the imported zonefile in one of two ways:
	1. Reads the uploaded file and pulls the data out into a session array.
	2. Reads the returned form data and records and creates the new domain.
*/

//inclues
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");


if (user_permissions_get("namedadmins"))
{
	// fetch upload mode
	$mode	= @security_form_input_predefined("int", "mode", 1, "");

	if ($mode == 1)
	{
		/*
			MODE 1 :: FILE UPLOAD AND PROCESS

			In this mode we need to upload the provided zonefile and import into an session array structure for further
			processing and validation.

			TODO:
			* complete remaining notes
			* better handling of unmatches/unprocessible rows
			* further testing
			* slash ALL input data to prevent exploit risk or having charactors that are not
			  form friendly break the processing form.
		*/

		// fetch type information
		$import_upload_type	= @security_form_input_predefined("any", "import_upload_type", 1, "");


		// check type requirements
		switch ($import_upload_type)
		{
			case "file_bind_8":
				// Bind 8/9 compatible zonefile

				$file_obj = New file_storage;
				$file_obj->verify_upload_form("import_upload_file");
			break;


			default:
				log_write("error", "process", "An invalid import type (\"". $import_upload_type ."\") was uploaded");
			break;
		}


		/*
			Handle Validation Errors
		*/

		if (error_check())
		{
			header("Location: ../index.php?page=domains/import.php&mode=1");
			exit(0);
		}


		/*
			Import the data
		*/

		// store all the domain information in the data array to be
		// passed back to the user form.
		$data = array();


		switch ($import_upload_type)
		{
			case "file_bind_8":
				/*
					Bind 8/9 Zonefile

					The following code reads in bind 8/9 zonefiles and pulls all the data that we can interpate
					into an array structure that is then returned to a form page.

					For more information about the file format, refer to RFC 1035
					http://tools.ietf.org/html/rfc1035
				*/



				// open the file and read data
				if ($zonefile = file($_FILES["import_upload_file"]["tmp_name"]))
				{
					log_write("debug", "process", "Processing file ". $_FILES["import_upload_file"]["name"] ."");

					foreach ($zonefile as $line)
					{
						// strip newline
						$line = rtrim($line);

						// strip whitespace to 1 char
						$line = preg_replace("/\s\s*/", " ", $line);

						// strip prefix whitespace
						//$line = ltrim($line);

						// ignore comment only lines
						if (preg_match("/^;/", $line))
						{
							next;
						}

						// strip comments
						$line = preg_replace("/;[\S\s]*$/", "", $line);

						// ignore any blank lines
						if ($line == "")
						{
							next;
						}



						/*
							Header Values
						*/

						// domain origin
						if (preg_match("/^\\\$ORIGIN\s(\S*)/", $line, $matches))
						{
							log_write("debug", "process", "Header: ORIGIN: ". $matches[1] ."");

							$data["domain_name"] = rtrim($matches[1], ".");
						}


						// domain TTL
						if (preg_match("/^\\\$TTL\s([0-9]*)/", $line, $matches))
						{
							log_write("debug", "process", "Header: TTL: ". $matches[1] ."");

							$data["domain_ttl"] = $matches[1];
						}



						/*
							SOA RECORD

							The SOA record is a bit more tricky, since it tends to be spread across
							multiple lines that might include commenting.

							We need to flag once we detect the SOA header and read each line's values
							from there and assemble a single SOA record.

							@ IN SOA ns.example.com. support.example.com. (
								2010060829 ; serial
								21600 ; refresh
								3600 ; retry
								604800 ; expiry
								86400 ; minimum ttl
							)
						*/

						if (preg_match("/^\S*\sIN\sSOA/", $line))
						{
							// flag SOA mode
							$file_soa = 1;


						}

						if ($file_soa == 1)
						{
							// process SOA header lines
							$file_soa_line .= rtrim($line) ." ";


							// check for end line
							if (preg_match("/\\)/", $line))
							{
								// completed SOA read
								// 
								// the SOA data is now in a single line in the form of:
								// @ IN SOA ns.example.com. support.example.com. (2010060829 21600 3600 604800 86400 ))
								//
								//

								$file_soa = 0;

								if (preg_match("/^(\S*)\sIN\sSOA\s(\S*)\s(\S*)\s*\\(\s*([0-9A-Za-z]*)\s*([0-9A-Za-z]*)\s*([0-9A-Za-z]*)\s*([0-9A-Za-z]*)\s*([0-9A-Za-z]*)\s*/m", $file_soa_line, $matches))
								{
									// depending on the file, we might be able to obtain the domain name
									if (!$data["domain_name"])
									{
										if ($matches[1] != "@")
										{
											$data["domain_name"] = rtrim($matches[1], ".");
										}
									}

									// fetch the primary NS - we can't use it, but we have it
									// for being able to report back to the user if it's going to change
									$data["domain_primary_ns"]	= rtrim($matches[2], ".");

									// fetch the administrator's email address
									$data["soa_hostmaster"]		= rtrim($matches[3], ".");

									// SOA record information
									$data["soa_serial"]		= time_bind_to_seconds($matches[4]);
									$data["soa_refresh"]		= time_bind_to_seconds($matches[5]);
									$data["soa_retry"]		= time_bind_to_seconds($matches[6]);
									$data["soa_expire"]		= time_bind_to_seconds($matches[7]);
									$data["soa_default_ttl"]	= time_bind_to_seconds($matches[8]);

									// TODO: handle alpha-date formations (eg: 15H, 1W, etc)
								}
								else
								{
									log_write("debug", "process", "Unrecognised format of SOA line");
								}
							}
						}




						/*
							Domain Records

							Domain records are all single lines, we need to read through them and create an associative array
							structure.

							NS records:
								@ 86400 IN NS ns1.example.com
								@ 86400 IN NS ns2.example.com.

								local.example.com. 86400 IN NS ns1.example.com
								local.example.com. 86400 IN NS ns2.example.com.

							MX records:
								example.org. IN MX 10 mail1.example.com
								@ IN MX 10 mail1.example.com

							A records:
								@ 1800 IN A 192.168.0.5
								files 1800 IN A 192.168.0.1
								www IN A 192.168.0.6
						*/

						if (preg_match("/\sIN\s(\S*)/", $line, $matches)
							|| preg_match("/\s(CNAME)\s/", $line, $matches)
							|| preg_match("/\s(PTR)\s/", $line, $matches)
							|| preg_match("/^(NS)\s/", $line, $matches))
						{
							switch ($matches[1])
							{
								case "SOA":
									// SOA records are handled up above in a specific section,
									// no need to process it again
								break;


								case "NS":
									/*
										NS: name server records
										
										These should be matched against the automatically configured name servers,
										if they are different, the user will have the ability to add these records
										as custom records.

										eg:								
										@ 86400 IN NS ns1.example.com
										@ 86400 IN NS ns2.example.com.

										There may also be NS entries for subdomains, for example:

										eg:
										local.example.com. 86400 IN NS ns1.example.com
										local.example.com. 86400 IN NS ns2.example.com.
									*/

									$data_tmp = array();
									$data_tmp["type"]	= "NS";

									if (preg_match("/^(\S*)\s(\S*)\s*IN/", $line, $matches))
									{
										// orgin/name
										$data_tmp["name"]		= rtrim($matches[1], ".");

										// TTL
										if ($matches[2])
										{
											$data_tmp["ttl"]	= $matches[2];
										}
									}


									// content information
									if (preg_match("/IN\sNS\s(\S*)$/", $line, $matches))
									{
										$data_tmp["content"]		= rtrim($matches[1], ".");

										// verify if the origin is a match
										if ($data_tmp["name"] == $data["domain_name"] || $data_tmp["name"] == "@") 
										{
											// origin matches current domain
											//
											// if this name server is the same as one of the configured servers
											// in namedmanager, we don't need to add it.
											//

											$obj_sql 		= New sql_query;
											$obj_sql->string	= "SELECT id FROM name_servers WHERE server_name='". $data_tmp["content"] ."' LIMIT 1";
											$obj_sql->execute();

											if ($obj_sql->num_rows())
											{
												// nameserver matches one of the standard nameservers, we
												// should not import this to prevent duplication.
												log_write("debug", "process", "NS record matches domain orgin, no need to add it as a custom record");

												continue;
											}
										}
									}


									// verify required fields provided
									if (!$data_tmp["name"] || !$data_tmp["content"])
									{
										log_write("warning", "process", "Unable to process line \"$line\"");
									}
									else
									{
										// success
										log_write("debug", "process", "Added new NS record");

										// add to data structure
										$data["records"][] = $data_tmp;
										$data["num_records"]++;
									}

								break;


								case "MX":
									/*
										MX: Mail Server Records

										eg:
										example.org. IN MX 10 mail1.example.com
										@ IN MX 10 mail1.example.com
										IN MX 10 mail1.example.com
									*/

									$data_tmp = array();
									$data_tmp["type"]	= "MX";

									if (preg_match("/^(\S*)\s(\S*)\s*IN/", $line, $matches))
									{
										// usually the origin will be for the same domain, but
										// this is not always the case.
										$data_tmp["name"]		= rtrim($matches[1], ".");

										// TTL
										if ($matches[2])
										{
											$data_tmp["ttl"]	= $matches[2];
										}
									}


									// content information
									if (preg_match("/IN\s\MX\s([0-9]*)\s(\S*)$/", $line, $matches))
									{
										$data_tmp["prio"]		= $matches[1];
										$data_tmp["content"]		= rtrim($matches[2], ".");
									}


									// verify required fields provided
									if (!$data_tmp["prio"] || !$data_tmp["content"])
									{
										log_write("warning", "process", "Unable to process line \"$line\"");
									}
									else
									{
										// success
										log_write("debug", "process", "Added new MX record");

										// add to data structure
										$data["records"][] = $data_tmp;
										$data["num_records"]++;
									}

								break;



								case "CNAME":
									/*
										CNAME: redirection/alias record

										eg:
										mail.example.org. IN CNAME host1.example.org
										mail.example.org. CNAME host1.example.org
									*/

									$data_tmp = array();
									$data_tmp["type"]	= "CNAME";

									if (preg_match("/^(\S*)\sCNAME/", $line, $matches))
									{
										// name
										$data_tmp["name"]		= $matches[1];
									}

									// content information
									if (preg_match("/\sCNAME\s(\S*)$/", $line, $matches))
									{
										$data_tmp["content"]		= rtrim($matches[1], ".");
									}


									// verify required fields provided
									if (!$data_tmp["name"] || !$data_tmp["content"])
									{
										log_write("warning", "process", "Unable to process line \"$line\"");
									}
									else
									{
										// success
										log_write("debug", "process", "Added new CNAME record");

										// add to data structure
										$data["records"][] = $data_tmp;
										$data["num_records"]++;
									}

								break;



								case "A":
								case "AAAA":
								case "PTR":
								case "TXT":
								case "SPF":
								case "SRV":
									/*
										General Records

										eg:
											@ 1800 IN A 192.168.0.5
											files 1800 IN A 192.168.0.1
											www IN A 192.168.0.6
									*/

									$data_tmp = array();
									$data_tmp["type"]	= $matches[1];

									// name information
									if (preg_match("/^(\S*)\s(\S*)\s*IN/", $line, $matches))
									{
										// name
										$data_tmp["name"]		= rtrim($matches[1], ".");

// TODO: should we default to "@" ?
//										if ($data_tmp["name"] == $data["domain_name"])
//										{
//											$data_tmp["name"] = "@";
//										}


										// TTL
										if ($matches[2])
										{
											$data_tmp["ttl"]	= $matches[2];
										}
									}


									// content information
									if (preg_match("/IN\s\S*\s([\S\s]*)$/", $line, $matches))
									{
										$data_tmp["content"]		= rtrim($matches[1], ".");

									}


									// verify required fields provided
									if (!$data_tmp["name"] || !$data_tmp["content"])
									{
										log_write("warning", "process", "Unable to process line \"$line\"");
									}
									else
									{
										// success
										log_write("debug", "process", "Added new ". $data_tmp["type"] ." record");

										// add to data structure
										$data["records"][] = $data_tmp;
										$data["num_records"]++;
									}

								break;


								default:
									// unknown type
									log_write("warning", "process", "Sorry, unable to process DNS record, type \"". $matches[1] ."\" is unknown.");
								break;

							} // end of record type processing

						} // end if domain record

					} // end of zonefile line loop



					/*
						Domain Type Handling

						We need to determine the type of the domain - whether it's standard/reverse.

						// TODO: is this working?
					*/

					$data["domain_type"] == "domain_standard";

					foreach ($data["records"] as $record)
					{
						if ($record["type"] == "PTR")
						{
							$data["domain_type"] = "domain_reverse_ipv4";

							break;
						}
					}



					
					/*
						If no domain name could be determined (this can happen with some
						zonefiles depending on the way origin has been setup) we should
						query the filename to determine the needs.
					*/

					if (empty($data["domain_name"]))
					{
						// strip the filename, minus extensions
						$extension = format_file_extension($_FILES["import_upload_file"]["name"]);

						if ($extension == "zone" || $extension == "arpa" || $extension == "rev")
						{
							$data["domain_name"]	= $_FILES["import_upload_file"]["name"];
							$data["domain_name"]	= str_replace(".$extension", "", $data["domain_name"]);
						}
						else
						{
							$data["domain_name"]	= $_FILES["import_upload_file"]["name"];
						}


						// handle reverse domains
						if ($data["domain_type"] == "domain_reverse_ipv4")
						{
							if (preg_match("/([0-9][0-9]*.[0-9][0-9]*.[0-9][0-9]*.[0-9][0-9]*)/", $data["domain_name"], $matches))
							{
								$data["ipv4_network"] = $matches[1];
							}
						}
					}


					/*
						Enable for detailed import debugging
					*/

//					print "<pre>";
//					print_r($data);
//					print "</pre>";

//					die("debugging break");
				}
				else
				{
					// failure to open
					log_write("error", "process", "There was an unexpected fault trying to open ". $_FILES["import_upload_file"]["tmp_name"] ." for reading");
					exit(0);
				}

			break;


			default:
				// catch all, should never be executed
				log_write("error", "process", "Internal application failure");
			break;
		}



		/*
			Handle Processing Errors
		*/

		if (error_check())
		{
			header("Location: ../index.php?page=domains/import.php&mode=1");
			exit(0);
		}



		/*
			Return Valid Data
		*/

		log_write("notification", "process", "Returning imported zonefile information to domain import form.");


		// overload the error form data with the data from the form	
		$_SESSION["error"]["form"]["domain_import"]	= "failed";
		$_SESSION["error"]				= $data;


		// return
		header("Location: ../index.php?page=domains/import.php&mode=2");
		exit(0);

	}
	elseif ($mode == 2)
	{
		/*
			MODE 2 :: ENTER ZONE RECORD

			After records have been validated, they are posted back to this page for final processing and importing into the database.
		*/


			

		/*
			Domain Object Init
		*/

		$obj_domain		= New domain;
		$obj_domain->id		= security_form_input_predefined("int", "id_domain", 0, "");



		/*
			Domain Details Validation
		*/


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
			$obj_domain->data["domain_description"]		= security_form_input_predefined("any", "domain_description", 0, "");


			// calculate domain name
			if ($obj_domain->data["ipv4_network"])
			{	
				$tmp_network = explode(".", $obj_domain->data["ipv4_network"]);
					
				$obj_domain->data["domain_name"]	= $tmp_network[2] .".". $tmp_network[1] .".". $tmp_network[0] .".in-addr.arpa";
			}


			// if no description, set to original IP
			if (!$obj_domain->data["domain_description"])
			{
				$obj_domain->data["domain_description"] = "Reverse domain for range ". $obj_domain->data["ipv4_network"] ." with subnet of /". $obj_domain->data["ipv4_subnet"] ."";
			}
		}



		/*
			Domain Record Validation
		*/
		
		$data["num_records"]	= @security_form_input_predefined("int", "num_records", 0, "");

		for ($i = 0; $i < $data["num_records"]; $i++)
		{
			/*
				Fetch Data
			*/
			$data_tmp			= array();
			$data_tmp["type"]		= @security_form_input_predefined("any", "record_". $i ."_type", 0, "");
			$data_tmp["prio"]		= @security_form_input_predefined("int", "record_". $i ."_prio", 0, "");
			$data_tmp["ttl"]		= @security_form_input_predefined("int", "record_". $i ."_ttl", 0, "");
			$data_tmp["name"]		= @security_form_input_predefined("any", "record_". $i ."_name", 0, "");
			$data_tmp["content"]		= @security_form_input_predefined("any", "record_". $i ."_content", 0, "");
			$data_tmp["import"]		= @security_form_input_predefined("checkbox", "record_". $i ."_import", 0, "");
			

			// only process records that are to be imported
			if (!$data_tmp["import"])
			{
				// record to be ignored
				continue;
			}



			/*
				Error Handling
			*/

			// verify name syntax
			if ($data_tmp["name"] != "@" && !preg_match("/^[A-Za-z0-9._-]*$/", $data_tmp["name"]))
			{
				log_write("error", "process", "Sorry, the value you have entered for record ". $data_tmp["name"] ." contains invalid charactors");

				error_flag_field("record_custom_". $i ."");
			}


			// add to processing array
			$data["records"][] = $data_tmp;
		}


		/*
			SOA Fields
		*/
	
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
			$_SESSION["error"]["form"]["domain_import"]	= "failed";

			header("Location: ../index.php?page=domains/import.php&mode=2");
			exit(0);
		}
		else
		{
			// clear error data
			error_clear();

		
			/*
				Transaction Start
			*/

			$sql_obj = New sql_query;
			$sql_obj->trans_begin();



			/*
				Update/Create Domain
			*/

			// update domain details
			$obj_domain->action_update();

			// update nameserver
			$obj_domain->action_update_ns();



			/*
				Update/Create Domain Records
			*/


			// fetch all DNS records
			$obj_domain->load_data();
			$obj_domain->load_data_record_all();

			// update records
			foreach ($data["records"] as $record)
			{
				$obj_record		= New domain_records;
					
				$obj_record->id		= $obj_domain->id;
				$obj_record->data	= $obj_domain->data;		// copy domain data from existing object to save time & SQL queries


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

			} // end for records


			/*
				Update domain serial
			*/
			$obj_domain->action_update_serial();


			// clear messages & replace with custom one
			$_SESSION["notification"]["message"] = array("Domain imported successfully, configured nameservers will recieve the new configuration shortly.");



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



			/*
				Handle Processing Errors
			*/

			if (error_check())
			{
				$_SESSION["error"]["form"]["domain_import"]	= "failed";

				header("Location: ../index.php?page=domains/import.php&mode=2");
				exit(0);
			}



			/*
				Take the user to the domain page
			*/

			// return
			header("Location: ../index.php?page=domains/view.php&id=". $obj_domain->id ."");
			exit(0);

			
		} // if verification passed


	} // end of mode 1 or 2



	// else - assume failure
	header("Location: ../index.php?page=domains/import.php&mode=1");
	exit(0);
}
else
{
	// user does not have permissions to access this page.
	error_render_noperms();
	header("Location: ../index.php?page=message.php");
	exit(0);
}


?>
