<?php
/*
	include/application/inc_bind.php

	Functions for manipulating configuration for the bind nameserver
*/



class bind_api extends soap_api
{
	var $domains;		// array of domain data
	var $records;		// associate array of domain record data


	/*
		check_permissions

		Checks the permissions of all the configuration files and returns an error if incorrect.

		Returns
		0	Incorrect permissions
		1	All good
	*/
	function check_permissions()
	{
		log_write("debug", "bind_config", "Executing check_permissions()");

		if (!file_exists($GLOBALS["config"]["bind"]["config"]))
		{
			if (!touch($GLOBALS["config"]["bind"]["config"]))
			{
				log_write("error", "script", "Unable to create Bind configuration file ". $GLOBALS["config"]["bind"]["config"]);
			}
		}

		if (!is_writable($GLOBALS["config"]["bind"]["config"]))
		{
			log_write("error", "script", "Unable to write to Bind configuration file ". $GLOBALS["config"]["bind"]["config"]);

			return 0;
		}
		if (!is_writable($GLOBALS["config"]["bind"]["zonefiledir"]))
		{
			log_write("error", "script", "Unable to write to zone file directory ". $GLOBALS["config"]["bind"]["zonefiledir"]);

			return 0;
		}

		return 1;

	} // end of check_permissions



	/*
		check_domain_serial

		Read the zonefile for the serial and return the result, used to be able to
		compare zonefiles on disk with versions in the database
	
		Fields
		domainname	Name of the domain

		Returns
		0		Unknown zone or unreadable file
		#		Serial Number of the zone file (int)
	*/

	function check_domain_serial( $domain_name )
	{
		// UTF-8 compatibility
		if (function_exists("idn_to_ascii"))
		{
			$domain_name = idn_to_ascii($domain_name);
		}
	

		// set zonefile location
		$zonefile = $GLOBALS["config"]["bind"]["zonefiledir"] ."/". $domain_name .".zone";

		if (!file_exists($zonefile))
		{
			log_write("debug", "main", "Zonefile $zonefile does not exist yet, unable to check serial number for change detection. Assuming changed.");
			return 0;
		}

		// open zonefile for reading
		if (!$fh = fopen($zonefile, "r"))
		{
			log_write("debug", "main", "Unable to open file ". $zonefile ." for reading");
			return 0;
		}

    		while (($buffer = fgets($fh, 4096)) !== false)
		{
			if (preg_match("/([0-9][0-9]*)\s;\sserial$/", $buffer, $matches))
			{
				$serial = $matches[1];

				log_write("debug", "main", "Existing domain $domain_name serial is $serial");

				// done, no more reading needed
				fclose($fh);
				return $serial;
			}
	        }

		// close & done
		fclose($fh);
		
		return 0;

	} // end of check_domain_serial




	/*
		action_reload

		Reloads the Bind nameserver after new configuration has been written.

		Returns
		0		Failure
		1		Success
	*/
	function action_reload()
	{
		log_write("debug", "script", "Reloading Bind with new configuration using ". $GLOBALS["config"]["bind"]["reload"] ."");

		exec($GLOBALS["config"]["bind"]["reload"] ." 2>&1", $exec_output, $exec_return_value);

		if ($exec_return_value)
		{
			// an error occured
			if (preg_match('/rndc:\sconnect\sfailed/', $exec_output[0]))
			{
				// typical sign that Bind hasn't been started
				log_write("error", "script", "Rndc unable to connect to running Bind process - is there an active name server on this host?");
			}

			// generic failure
			log_write("error", "script", "Unable to confirm successful reload of Bind!");

			return 0;
		}
		else
		{
			// Success!
			log_write("debug", "script", "Bind successfully reloaded with new configuration");

			return 1;
		}

	} // end of action_reload



	/*
		action_update

		Updates the configuration for the Bind nameserver and all zones.

		Returns
		0		Failure
		1		Success
	*/
	
	function action_update()
	{
		log_write("debug", "script", "Executing action_update()");

		// fetch domains and their status from the API
		$this->domains = $this->fetch_domains();


		if ($this->domains)
		{
			foreach ($this->domains as $domain)
			{
				if ($this->check_domain_serial($domain["domain_name"]) == $domain["soa_serial"])
				{
					// domain is up to date
					log_write("debug", "script", "Domain ". $domain["domain_name"] ." is all up to date (serial: ". $domain["soa_serial"] .")");
				}
				else
				{
					// domain is out of date
					log_write("debug", "script", "Domain ". $domain["domain_name"] ." is out of date - configuration needs to be regenerated");

					// update the zone file
					if (!$this->action_generate_zonefile( $domain["domain_name"], $domain["id"] ))
					{
						// unhandlable fault
						return 0;
					}
				}
			}
		}
		else
		{
			log_write("warning", "script", "There are no domains currently in NamedManager that apply to this name server.");
		}


		// remove any deleted domain zonefiles
		$this->action_remove_deleted();

		// update application configuration
		if (!$this->action_generate_appconfig())
		{
			// unhandlable fault	
			return 0;
		}


		// reload Bind
		if (!$this->action_reload())
		{
			return 0;
		}

		// successful update
		return 1;
	}


	
	/*
		action_generate_appconfig

		Generates a new application configuration file for all domains in $this->domains.

		Returns
		0		Failure
		1		Success
	*/
	function action_generate_appconfig()
	{
		log_write("debug", "script", "Generating Bind application configuration file (". $GLOBALS["config"]["bind"]["config"] .")");

		if (!$fh = fopen($GLOBALS["config"]["bind"]["config"], "w"))
		{
			log_write("error", "main", "Unable to open file ". $GLOBALS["config"]["bind"]["config"] ." for writing");
			return 0;
		}

		fwrite($fh, "//\n");
		fwrite($fh, "// NamedManager Configuration\n");
		fwrite($fh, "//\n");
		fwrite($fh, "// This file is automatically generated any manual changes will be lost.\n");
		fwrite($fh, "//\n");

		if ($this->domains)
		{
			foreach ($this->domains as $domain)
			{
				// we can assume a utf8 filesystem, but bind requires we convert the domain names
				// to an IDN format (eg παράδειγμα.δοκιμή -> xn--hxajbheg2az3al.xn--jxalpdlp) before
				// it can resolve DNS requests.
				//
				// Requires PHP > 5.4 and/or PHP with intl package installed.

				if (function_exists("idn_to_ascii"))
				{
					$domain["domain_name_idn"]	= $domain["domain_name"];
					$domain["domain_name"]		= idn_to_ascii($domain["domain_name"]);
				
					if ($domain["domain_name"] != $domain["domain_name_idn"])
					{
						fwrite($fh, "// UTF-8 Compatibility: ". $domain["domain_name_idn"] ." renamed to ". $domain["domain_name"] ."\n");
					}
				}
				else
				{
					// this will break international domains, but will work for all other domains
					log_write("warning", "script", "idn/international functions not available on this server, UTF-8 domains may be handled incorrectly");
				}


				fwrite($fh, "zone \"". $domain["domain_name"] ."\" IN {\n");
				fwrite($fh, "\ttype master;\n");
				
				if (!empty($GLOBALS["config"]["bind"]["zonefullpath"]))
				{
					// unusual, needed if Bind lacks a directory configuration option
					fwrite($fh, "\tfile \"". $GLOBALS["config"]["bind"]["zonefiledir"] ."". $domain["domain_name"] .".zone\";\n");
				}
				else
				{
					fwrite($fh, "\tfile \"". $domain["domain_name"] .".zone\";\n");
				}

				fwrite($fh, "\tallow-update { none; };\n");
				fwrite($fh, "};\n");
			}
		}

		fclose($fh);

		// validate configuration
		if ($GLOBALS["config"]["bind"]["verify_config"] && file_exists($GLOBALS["config"]["bind"]["verify_config"]))
		{
			exec($GLOBALS["config"]["bind"]["verify_config"] ." ". $GLOBALS["config"]["bind"]["config"], $exec_output, $exec_return_value);

			if ($exec_return_value)
			{
				log_write("error", "script", "An unexpected problem occured when validating the generated configuration.");
				log_write("error", "script", "It is possible this is an application bug, raising error to avoid reload");

				return 0;
			}
		}
		else
		{
			log_write("warning", "script", "No named configuration validater found, you should install named-checkconf to improve safety checks of your name server");
		}
		


		log_write("debug", "script", "Finished writing application configuration");

		return 1;

	} // end of action_generate_app_config



	/*
		action_remove_deleted

		Checks the list of domains returned from namedmanager and deletes any from the host
		that no longer valid.

		Returns
		0		Failure
		1		Success
	*/
	function action_remove_deleted()
	{
		log_write("debug", "script", "Executing action_remove_deleted()");


		// fetch list of domains from old config
		if (!$file = file($GLOBALS["config"]["bind"]["config"]))
		{
			log_write("error", "main", "Unable to open file ". $GLOBALS["config"]["bind"]["config"] ." for reading");
			return 0;
		}

		foreach ($file as $line)
		{
			// fetch the old domains
			if (preg_match("/zone\s\"(\S*)\"\sIN/", $line, $matches))
			{
				$domains_old[]	= $matches[1];
			}
		}


		// fetch equalivent list of new domains
		if ($this->domains)
		{
			foreach ($this->domains as $domain)
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$domains_new[] = idn_to_ascii($domain["domain_name"]);
				}
				else
				{
					$domains_new[] = $domain["domain_name"];
				}
			}
		}


		// compare with current domains
		if ($domains_old)
		{
			foreach ($domains_old as $domain)
			{
				if (!in_array($domain, $domains_new))
				{
					// domain has been deleted
					log_write("debug", "script", "Domain $domain has been deleted, removing old configuration file");
					unlink( $GLOBALS["config"]["bind"]["zonefiledir"] ."/". $domain .".zone" );
				}
				
			}
		}

		return 1;

	} // end of action_remove_deleted



	/*
		action_generate_zonefile

		Generates a new zonefile for the selected domain and replaces any existing ones. Most of this function
		is logic for handling and formatting the different types of domain records correctly and
		resolving challanges such as FQDN.

		Fields
		domain_name	Named of the domain
		domain_id	ID of the domain

		Returns
		0		Failure to generate zonefile
		1		Successful generation
	*/

	function action_generate_zonefile( $domain_name, $domain_id )
	{
		log_write("debug", "script", "Executing action_generate_zonefile ($domain_name, $domain_id)");
		log_write("debug", "script", "Generating new zonefile for $domain_name");


		// UTF-8 compatibility
		if (function_exists("idn_to_ascii"))
		{
			$domain_name = idn_to_ascii($domain_name);
		}
	
		$zonefile = $GLOBALS["config"]["bind"]["zonefiledir"] ."/". $domain_name .".zone";

		// open zonefile for writing
		if (!$fh = fopen("$zonefile.tmp", "w"))
		{
			log_write("error", "main", "Unable to open file $zonefile.tmp for writing");
			return 0;
		}


		// fetch the domain records
		$this->records = $this->fetch_records( $domain_id );


		// check that there are records that can be written
		if (!$this->records)
		{
			log_write("warning", "script", "No records have been configured for domain, zonefile will be empty");

			$this->records = array();
		}



		// SOA record
//		fwrite($fh, "$domain_name\n");
//		fwrite($fh, "// This file is automatically generated any manual changes will be lost.\n");
//		fwrite($fh, "//\n");

		foreach ($this->domains as $domain)
		{
			if ($domain["id"] == $domain_id)
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$domain["domain_name"]		= idn_to_ascii($domain["domain_name"]);
					$domain["soa_hostmaster"]	= idn_to_ascii($domain["soa_hostmaster"]);
					$domain["soa_ns_primary"]	= idn_to_ascii($domain["soa_ns_primary"]);
				}

				// header
				fwrite($fh, "\$ORIGIN ". $domain["domain_name"] .".\n");
				fwrite($fh, "\$TTL ". $domain["soa_default_ttl"] ."\n");

				// change @ to . for hostmaster email address. Any . preceeding @, need to become \.
				$tmp_soa_hostmaster = explode("@", $domain["soa_hostmaster"]);

				$domain["soa_hostmaster"] = str_replace(".", "\.", $tmp_soa_hostmaster[0]) .".". $tmp_soa_hostmaster[1];

				// create SOA record from domain information
				fwrite($fh, "@\t\tIN SOA ". $domain["soa_ns_primary"] .". ". $domain["soa_hostmaster"] .". (\n");
				fwrite($fh, "\t\t\t". $domain["soa_serial"] ." ; serial\n");
				fwrite($fh, "\t\t\t". $domain["soa_refresh"] ." ; refresh\n");
				fwrite($fh, "\t\t\t". $domain["soa_retry"] ." ; retry\n");
				fwrite($fh, "\t\t\t". $domain["soa_expire"] ." ; expiry\n");
				fwrite($fh, "\t\t\t". $domain["soa_default_ttl"] ." ; minimum ttl\n");
				fwrite($fh, "\t\t)\n");

			}
		}


		// NS records
		fwrite($fh, "\n");
		fwrite($fh, "; Nameservers\n");
		fwrite($fh, "\n");

		foreach ($this->records as $record)
		{
			if ($record["record_type"] == "NS")
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$record["record_name"]		= idn_to_ascii($record["record_name"]);
					$record["record_content"]	= idn_to_ascii($record["record_content"]);
				}

				// handle origin and content format
				if (preg_match("/\./", $record["record_content"]))
				{
					$record["record_content"]	= $record["record_content"] .".";
				}

				if (preg_match("/\./", $record["record_name"]) && preg_match("/". $domain_name ."$/", $record["record_name"]))
				{
					$record["record_name"]		= $record["record_name"] .".";
				}



				// write line
				fwrite($fh, "". $record["record_name"] ."\t". $record["record_ttl"] ." IN NS ". $record["record_content"] ."\n");
			}
		}


		// MX records
		fwrite($fh, "\n");
		fwrite($fh, "; Mailservers\n");
		fwrite($fh, "\n");

		foreach ($this->records as $record)
		{
			if ($record["record_type"] == "MX")
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$record["record_name"]		= idn_to_ascii($record["record_name"]);
					$record["record_content"]	= idn_to_ascii($record["record_content"]);
				}

				// handle origin and content format
				if (preg_match("/\./", $record["record_content"]))
				{
					$record["record_content"]	= $record["record_content"] .".";
				}

				if (preg_match("/\./", $record["record_name"]) && preg_match("/". $domain_name ."$/", $record["record_name"]))
				{
					$record["record_name"]		= $record["record_name"] .".";
				}

				// write line
				fwrite($fh, "".$record["record_name"] ."\t". $record["record_ttl"] ." IN MX ". $record["record_prio"] ." ". $record["record_content"] ."\n");
			}
		}

		
		// PTR records
		fwrite($fh, "\n");
		fwrite($fh, "; Reverse DNS Records (PTR)\n");
		fwrite($fh, "\n");

		foreach ($this->records as $record)
		{
			if ($record["record_type"] == "PTR")
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$record["record_name"]		= idn_to_ascii($record["record_name"]);
					$record["record_content"]	= idn_to_ascii($record["record_content"]);
				}

				if (strpos($record["record_name"], "ip6.arpa"))
				{
					// IPv6 records are full domains, hence trailing .
					$record["record_name"] = $record["record_name"] .".";
				}

				fwrite($fh, $record["record_name"] . "\t". $record["record_ttl"] ." IN PTR ". $record["record_content"] .".\n");
			}
		}


		// CNAME records
		fwrite($fh, "\n");
		fwrite($fh, "; CNAME\n");
		fwrite($fh, "\n");

		foreach ($this->records as $record)
		{
			if ($record["record_type"] == "CNAME")
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$record["record_name"]		= idn_to_ascii($record["record_name"]);
					$record["record_content"]	= idn_to_ascii($record["record_content"]);
				}

				if (preg_match("/\./", $record["record_name"]) && preg_match("/". $domain_name ."$/", $record["record_name"]))
				{
					$record["record_name"] .= ".";	// append . as FQDN
				}

				fwrite($fh, $record["record_name"] . "\t". $record["record_ttl"] ." IN CNAME ". $record["record_content"] ."");

				if (preg_match("/\./", $record["record_content"]))
				{
					// FQDN
					fwrite($fh, ".\n");
				}
				else
				{
					// non-FQND
					fwrite($fh, "\n");
				}
			}
		}


		// A, AAAA and other host records
		fwrite($fh, "\n");
		fwrite($fh, "; HOST RECORDS\n");
		fwrite($fh, "\n");

		foreach ($this->records as $record)
		{

			if (!in_array($record["record_type"], array("NS", "MX", "PTR", "CNAME", "SOA")))
			{
				// UTF-8 compatibility
				if (function_exists("idn_to_ascii"))
				{
					$record["record_name"] = idn_to_ascii($record["record_name"]);

					// idn_to_ascii has a lovely habit of blowing up with some record values, such as
					// DKIM records. If idn_to_ascii fails, we leave the value unchanged
					if ($tmp = idn_to_ascii($record["record_content"]))
					{
						$record["record_content"] = $tmp;
					}
					else
					{
						log_write("warning", "script", "Unable to punnycode parse record \"". $record["record_name"] ."\". This sometimes happens with certain records like DKIM and may not be an issue.");
					}
				}

				switch($record["record_type"])
				{
					case "A":
					case "AAAA":
					case "SPF":
					case "TXT":

						// Adjust to handle FQDN in name/origin
						if (preg_match("/\./", $record["record_name"]) && preg_match("/". $domain_name ."$/", $record["record_name"]))
						{
							$record["record_name"] .= ".";	// append . as FQDN
						}

					break;
					
					
					case "SRV":

						// Adjust to handle FQDN in name/origin
						if (preg_match("/\./", $record["record_name"]) && preg_match("/". $domain_name ."$/", $record["record_name"]))
						{
							$record["record_name"] .= ".";		// append . as FQDN
						}

						// Adjust to handle FQDN in server target
						if (preg_match("/\./", $record["record_content"]))
						{
							$record["record_content"] .= ".";	// append . as FQDN
						}
					break;


					default:
						// nothing to do
					break;
				}
			
				// write record
				fwrite($fh, $record["record_name"] . "\t". $record["record_ttl"] ." IN ". $record["record_type"] ." ". $record["record_content"] ."\n");
			}
		}


		// footer
		fclose($fh);


		// validate configuration
		if ($GLOBALS["config"]["bind"]["verify_zone"] && file_exists($GLOBALS["config"]["bind"]["verify_zone"]))
		{
			exec($GLOBALS["config"]["bind"]["verify_zone"] ." $domain_name $zonefile.tmp", $exec_output, $exec_return_value);

			if ($exec_return_value)
			{
				log_write("error", "script", "An unexpected problem occured when validating the generated zone file for $domain_name");

				foreach ($exec_output as $line)
				{
					log_write("error", "script", "Validator: $line");
				}

				log_write("error", "script", "It is possible this is an application bug, raising error to avoid reload.");
				log_write("error", "script", "Zonefile remains on previous version until validation error resolved.");

				return 0;
			}
		}
		else
		{
			log_write("warning", "script", "No named configuration validater found, you should install named-checkconf to improve safety checks of your name server");
		}

		// successful pass of the configuration, we can now rename the files
		if (!rename("$zonefile.tmp", $zonefile))
		{
			log_write("error", "script", "Fatal error moving $zonefile.tmp to $zonefile");
			return 0;
		}

		return 1;


	} // end of action_generate_zonefile


} // end of class bind_config


?>
