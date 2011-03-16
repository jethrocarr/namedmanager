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

				// fetch the file type if we can't get it from elsewhere
// TODO					$filetype = format_file_extension($_FILES["BANK_STATEMENT"]["name"]);


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
						$line = ltrim($line);

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
									$data["soa_serial"]		= $matches[4];
									$data["soa_refresh"]		= $matches[5];
									$data["soa_retry"]		= $matches[6];
									$data["soa_expire"]		= $matches[7];
									$data["soa_default_ttl"]	= $matches[8];

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
										$data_tmp["name"]		= $matches[1];

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
												// matches - nothing TODO here
												log_write("debug", "process", "NS record matches domain orgin, no need to add it as a custom record");

												next;
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
									*/

									$data_tmp = array();
									$data_tmp["type"]	= "MX";

									if (preg_match("/^(\S*)\s(\S*)\s*IN/", $line, $matches))
									{
										//
										// we don't care about the origin, as this is always
										// going to be the same as the domain itself.
										//
										// (from a NamedManager POV at least)
										//
										// $data_tmp["origin"]		= $matches[1];

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
									if (preg_match("/\sCNAME\s([0-9]*)\s(\S*)$/", $line, $matches))
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

						// TODO
//						print "$line<br>";

					} // end of zonefile line loop

					// TODO
//					print "<pre>";
//					print_r($data);
//					print "</pre>";

//					die("die a horrible, horrible death");
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
			Domain Type Handling

			We need to determine the type of the domain - whether it's standard/reverse.
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
