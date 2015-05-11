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

	/*
		There's a pretty common issue where when importing large domains, PHP cuts off the POST
		half-way due to max-input limits. To handle this better, we check for presence of a final
		end-of-form value and return a useful error if we don't have it.
	*/

	if (!isset($_POST["mode"]))
	{
		log_write("error", "process", "Unable to import domain - you have too many records to be posted with your current PHP configuration. You need to increase the max_input_vars parameter (10,000 is a good level for most users) and then re-try this import. Take care to increase limits in other security layers like Suhosin if you're using it. <a href=\"https://projects.jethrocarr.com/p/oss-namedmanager/page/Troubleshooting/\" target=\"new\">See this troubleshooting document for more details</a>.");
		header("Location: ../index.php?page=domains/import.php&mode=1");
		exit(0);
	}





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
		$data				= array();	// valid record rows
		$data["unmatched"]		= array();	// unmatched record rows


		// pre-load dns record types
		$domain_record_types		= sql_get_singlecol("SELECT type as value FROM dns_record_types");


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

						// retain orig
						$line_orig = $line;

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
							continue;
						}



						/*
							Header Values
						*/

						// domain origin
						if (preg_match("/^\\\$ORIGIN\s(\S*)/", $line, $matches))
						{
							log_write("debug", "process", "Header: ORIGIN: ". $matches[1] ."");

							if (empty($data["domain_name"]))
							{
								// set origin
								$data["domain_name"] = rtrim($matches[1], ".");
							}

							// there is already a domain name/origin, anything else is the currently
							// selected origin field.

							$data["domain_origin_current"] = rtrim($matches[1], ".");
							$data["domain_origin_current"] = str_replace($data["domain_name"], "", $data["domain_origin_current"]);

							if (!empty($data["domain_origin_current"]))
							{
								$data["domain_origin_current"] = ".". rtrim($data["domain_origin_current"], ".");
							}
						}


						// domain TTL
						if (preg_match("/^\\\$TTL\s([0-9]*)/", $line, $matches))
						{
							log_write("debug", "process", "Header: TTL: ". $matches[1] ."");


							if (empty($data["domain_ttl"]))
							{
								// set domain TTL
								$data["domain_ttl"] = $matches[1];
							}
							
							// there is already a domain TTL, any other TTL is the current TTL
							// which applies to any records being added
							$data["domain_ttl_current"] = $matches[1];
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

						if (!empty($file_soa))
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

									// do a common guess that the first period should be converted to @
									$data["soa_hostmaster"]		= preg_replace("/\./", "@", $data["soa_hostmaster"], 1);

									// SOA record information
									$data["soa_serial"]		= time_bind_to_seconds($matches[4]);
									$data["soa_refresh"]		= time_bind_to_seconds($matches[5]);
									$data["soa_retry"]		= time_bind_to_seconds($matches[6]);
									$data["soa_expire"]		= time_bind_to_seconds($matches[7]);
									$data["soa_default_ttl"]	= time_bind_to_seconds($matches[8]);
								}
								else
								{
									log_write("debug", "process", "Unrecognised format of SOA line");
								}
							}
						}


						/*
							Fix structure of zonefiles

							We often see zone files lacking IN keyword which makes parsing more
							difficult.

							eg:
							> something A 192.168.1.1
							rather than
							> something IN A 192.168.1.1

							This bit of code tries to standardise the lines a bit more to make them
							easier to parse.
						*/

						foreach ($domain_record_types as $domain_type)
						{
							if (preg_match("/\s$domain_type\s/", $line) && !preg_match("/\sIN\s/", $line))
							{
								$line = preg_replace("/\s$domain_type\s/", " IN $domain_type ", $line);
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

						if (preg_match("/\sIN\s(\S*)/", $line, $matches))
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
										IN NS ns1.example.com

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

									// default name if unspecified
									if (!$data_tmp["name"])
									{
										$data_tmp["name"] = "@";
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
									if (!$data_tmp["content"])
									{
										log_write("warning", "process", "Unable to process line \"$line\"");

										$data["unmatched"][] = "$line_orig";
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

									if (!$data_tmp["name"])
									{
										$data_tmp["name"] = "@";
									}


									// content information
									if (preg_match("/IN\s\MX\s([0-9]*)\s(\S*)$/", $line, $matches))
									{
										$data_tmp["prio"]		= $matches[1];
										$data_tmp["content"]		= rtrim($matches[2], ".");
									}


									// verify required fields provided
									if (!isset($data_tmp["prio"]) || empty($data_tmp["content"]))
									{
										log_write("warning", "process", "Unable to process line \"$line\"");
										
										$data["unmatched"][] = "$line_orig";
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
										mail.example.org. 60 CNAME host1.example.org
									*/

									$data_tmp = array();
									$data_tmp["type"]	= "CNAME";
									$data_tmp["prio"]	= 0;

									// name information
									if (preg_match("/^(\S*)\s([0-9]*)\s*IN/", $line, $matches)
									   || preg_match("/^(\S*)\s([0-9]*)\s*CNAME/", $line, $matches))
									{
										// name
										$data_tmp["name"]		= $matches[1];

										// TTL
										if ($matches[2])
										{
											$data_tmp["ttl"]	= $matches[2];
										}
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
										
										$data["unmatched"][] = "$line_orig";
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
								case "SSHFP":
								case "LOC":
								case "HINFO":
									/*
										General Records

										eg:
											@ 1800 IN A 192.168.0.5
											files 1800 IN A 192.168.0.1
											www IN A 192.168.0.6
									*/

									$data_tmp = array();
									$data_tmp["type"]	= $matches[1];
									$data_tmp["prio"]	= 0;

									// name information
									if (preg_match("/^(\S*)\s(\S*)\s*IN/", $line, $matches))
									{
										// name
										$data_tmp["name"]		= rtrim($matches[1], ".") . $data["domain_origin_current"];

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


									// TTL
									if (empty($data_tmp["ttl"]))
									{
										$data_tmp["ttl"] = $data["domain_ttl_current"];
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
										
										$data["unmatched"][] = "$line_orig";
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
									
									$data["unmatched"][] = "$line_orig";
								break;

							} // end of record type processing

						} // end if domain record

					} // end of zonefile line loop


					/*
						ASCII->UTF-8 translation

						Bind zonefiles are stored in an ASCII format and any special characters need to be
						converted back into their native UTF-8 version for storage in the DB.
					*/

					if (function_exists("idn_to_utf8"))
					{
						$data["domain_name"]		= idn_to_utf8($data["domain_name"]);
						$data["domain_primary_ns"]	= idn_to_utf8($data["domain_primary_ns"]);
						$data["soa_hostmaster"]		= idn_to_utf8($data["soa_hostmaster"]);

						for ($i=0; $i < count($data["records"]); $i++)
						{
							$data["records"][$i]["name"]	= idn_to_utf8($data["records"][$i]["name"]);
							$data["records"][$i]["content"]	= idn_to_utf8($data["records"][$i]["content"]);
						}
					}
					else
					{
						log_write("warning", "process", 'Unable to do any IDN->UTF8 conversions');
					}


					/*
						Domain Type Handling

						We need to determine the type of the domain - whether it's standard/reverse.
					*/

					$data["domain_type"] == "domain_standard";

					if (strpos($data["domain_name"], "in-addr.arpa"))
					{
						$data["domain_type"] = "domain_reverse_ipv4";
					}

					if (strpos($data["domain_name"], "ip6.arpa"))
					{
						$data["domain_type"] = "domain_reverse_ipv6";
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

						if ($extension == "zone" || $extension == "rev")
						{
							$data["domain_name"]	= $_FILES["import_upload_file"]["name"];
							$data["domain_name"]	= str_replace(".$extension", "", $data["domain_name"]);
						}
						else
						{
							$data["domain_name"]	= $_FILES["import_upload_file"]["name"];
						}
					}


					/*
						Handle reverse domains network range determination

						We can take the domain name and take the IPv4 information from it if possible.
					*/
					if ($data["domain_type"] == "domain_reverse_ipv4")
					{
						if (preg_match("/in-addr.arpa/", $data["domain_name"]))
						{
							// in-addr.apra domains have the IP address in reverse, we need to flip.

							$tmp	= str_replace('.in-addr.arpa', '', $data["domain_name"]);
							$tmp	= explode(".", $tmp);

							$data["ipv4_network"] = $tmp[2] .".". $tmp[1] .".". $tmp[0] .".0";
						}
						else
						{
							// default to assigning the network address to the domain name
							$data["ipv4_network"] = $data["domain_name"];
						}
					}

					if ($data["domain_type"] == "domain_reverse_ipv6")
					{
						// default to assigning the network address to the domain name
						$data["ipv6_network"] = ipv6_convert_fromarpa($data["domain_name"]);
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
		elseif ($obj_domain->data["domain_type"] == "domain_reverse_ipv4")
		{
			// fetch domain data
			$obj_domain->data["ipv4_network"]			= security_form_input_predefined("ipv4_cidr", "ipv4_network", 1, "Must supply full IPv4 network address");
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


		/*
			Verify domain name/IP
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
			Fetch all the provides records and only pass through the oneds we have flagged
			for import to the validator and processor.
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
			$data_tmp["delete_undo"]	= 0;
			$data_tmp["reverse_ptr"]	= 0;
			$data_tmp["reverse_ptr_orig"]	= 0;
			

			// only process records that are to be imported
			if (!$data_tmp["import"])
			{
				// record to be ignored
				continue;
			}

			// remove excess "." which might have been added from imported file
			$data_tmp["name"]	= rtrim($data_tmp["name"], ".");
			$data_tmp["content"]	= rtrim($data_tmp["content"], ".");

			// append the array
			$data["records"][] = $data_tmp;
		}



		/*
			Validate all imported records against standard validation function.
		*/
		$obj_domain_records               = New domain_records;
//		$obj_domain_records->id           = $obj_domain->id;

		$data = stripslashes_deep($data); // strip quoting, we'll do it as part of the following process

		$data_validated = $obj_domain_records->validate_custom_records($data['records']);

		$data['records'] = $data_validated['records'];
//		$data['reverse'] = $data_validated['reverse'];


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
