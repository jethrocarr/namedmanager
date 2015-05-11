<?php
/*
	inc_domain.php

	Provides high-level functions for managing domain entries.
*/




/*
	CLASS DOMAIN

	Functions for managing the domain in the database
*/

class domain
{
	var $id;		// ID of the domain to manipulate
	var $data;		// Domain & record details stored here
	var $format;		// "utf8" (default) or "idn" (punycode/IDN format)

	var $sql_obj;		// zone SQL database connection



	/*
		Constructor
	*/

	function domain()
	{
		log_debug("domain", "Executing domain() [constructor]");


	
		/*
			NamedManager has the capability to be configured to use different sources of database information both
			the internal database as well as external SQL-based databases.

			We need to establish a connection here to the appropiate database and use that on all further queries.
		*/

		$this->sql_obj = New sql_query;

		switch ($GLOBALS["config"]["ZONE_DB_TYPE"])
		{
			case "internal":
			default:

				// nothing todo
				log_write("debug", "domain", "Using internal application database for records");

			break;

	
			case "powerdns_mysql":

				// PowerDNS-compliant MySQL database
				log_write("debug", "domain", "Using external PowerDNS-compliant MySQL database");

				$this->sql_obj->session_init("mysql", $GLOBALS["config"]["ZONE_DB_HOST"], $GLOBALS["config"]["ZONE_DB_NAME"], $GLOBALS["config"]["ZONE_DB_USERNAME"], $GLOBALS["config"]["ZONE_DB_PASSWORD"]);

			break;
		}

	} // end of domain




	/*
		verify_id

		Checks that the provided ID is a valid domain name.

		Results
		0	Failure to find the ID
		1	Success - domain exists
	*/

	function verify_id()
	{
		log_debug("domain", "Executing verify_id()");

		if ($this->id)
		{
			$this->sql_obj->string	= "SELECT id FROM `dns_domains` WHERE id='". $this->id ."' LIMIT 1";
			$this->sql_obj->execute();

			if ($this->sql_obj->num_rows())
			{
				return 1;
			}
		}

		return 0;

	} // end of verify_id



	/*
		verify_domain_name

		Verify that the domain name is not already in use by another domain.

		Results
		0	Failure - name in use
		1	Success - name is available
	*/

	function verify_domain_name()
	{
		log_debug("domain", "Executing verify_domain_name()");
		
		$this->sql_obj->string		= "SELECT id FROM `dns_domains` WHERE domain_name='". $this->data["domain_name"] ."' ";

		if ($this->id)
			$this->sql_obj->string	.= " AND id!='". $this->id ."'";

		$this->sql_obj->string		.= " LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			if (!$this->id)
			{
				$this->sql_obj->fetch_array();

				$this->id = $this->sql_obj->data[0]["id"];
			}


			return 0;
		}
		
		return 1;

	} // end of verify_domain_name



	/*
		load_data

		Load the data for the domain name into $this->data

		Returns
		0	failure
		1	success
	*/
	function load_data()
	{
		log_debug("domain", "Executing load_data()");

		$this->data = array();

		$this->sql_obj->string	= "SELECT * FROM `dns_domains` WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			// load base domain data
			$this->data = $this->sql_obj->data[0];

			// fix international formatting
			if ($this->format == 'idn')
			{
				$this->data["domain_name_idn"]	= idn_to_ascii($this->data["domain_name"]);
				$this->data["domain_name_utf"]	= $this->data["domain_name"];
				$this->data["domain_name"]	= $this->data["domain_name_idn"];
				$this->data["soa_hostmaster"]	= idn_to_ascii($this->data["domain_name"]);
			}

			// load domain group membership
			$this->sql_obj->string	= "SELECT id_group FROM dns_domains_groups WHERE id_domain='". $this->id ."'";
			$this->sql_obj->execute();

			if ($this->sql_obj->num_rows())
			{
				$this->sql_obj->fetch_array();

				foreach ($this->sql_obj->data as $data_group)
				{
					$this->data["groups"][]	= $data_group["id_group"];
				}
			}
			else
			{
				// no group membership - this would typically only occur
				// for a newly imported domain
				$this->data["groups"] = array();
			}

/*
	TODO: powerdns stuff to sort out

			// load SOA record
			//
			// the SOA record is in the form of: primary hostmaster serial refresh retry expire default_ttl
			$this->sql_obj->string = "SELECT content FROM `dns_records` WHERE type='SOA' AND id_domain='". $this->id ."' LIMIT 1";
			$this->sql_obj->execute();
			$this->sql_obj->fetch_array();


			if (!$this->sql_obj->data[0]["content"])
			{
				log_write("warning", "domain", "No SOA record found for domain ". $this->id ." when attempting data load");
			}
			else
			{
				$record_soa_fields = explode(" ", $this->sql_obj->data[0]["content"]);

				$this->data["soa_primary"]	= $record_soa_fields[0];
				$this->data["soa_hostmaster"]	= $record_soa_fields[1];
				$this->data["soa_serial"]	= $record_soa_fields[2];
				$this->data["soa_refresh"]	= $record_soa_fields[3];
				$this->data["soa_retry"]	= $record_soa_fields[4];
				$this->data["soa_expire"]	= $record_soa_fields[5];
				$this->data["soa_default_ttl"]	= $record_soa_fields[6];
			}
*/
			return 1;
		}

		// failure
		return 0;

	} // end of load_data


	/*
		load_data_all

		Loads all the domains in the system into $this->data[]

		Returns
		0	failure
		1	success
	*/
	function load_data_all()
	{
		log_debug("domain", "Executing load_data_all()");

		$this->data 		= array();
		$this->data_groups	= array();


		// Fetch all the domain groups
		$this->sql_obj->string = "SELECT id_domain, id_group FROM `dns_domains_groups`";
		$this->sql_obj->execute();
		$this->sql_obj->fetch_array();

		foreach ($this->sql_obj->data as $data_group)
		{
			$this->data_groups[ $data_group["id_domain"] ][] = $data_group["id_group"];
		}



		// Fetch all the domains
		$this->sql_obj->string	= "SELECT * FROM `dns_domains`";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			foreach ($this->sql_obj->data as $data)
			{
				$data["groups"]	= $this->data_groups[ $data["id"] ];

				$this->data[]	= $data;
			}

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_all




	/*
		load_data_record_all

		Load all the records for the selected domain into the $this->data["records"] array. If you wish to modifiy or query specific
		records, please refer to the domain_records class.

		Options
		exclude_forwardrev	Set to 1 to exclude forwards reverse records

		Returns
		0	failure
		1	success
	*/
	function load_data_record_all($exclude_forwardrev = NULL)
	{
		log_debug("domain", "Executing load_data_record_all($exclude_forwardrev)");


		if ($exclude_forwardrev)
		{
			$this->sql_obj->string	= "SELECT id as id_record, name, type, content, ttl, prio FROM `dns_records` WHERE id_domain='". $this->id ."' AND name NOT REGEXP '^[0-9]*-[0-9]*-[0-9]*-[0-9]*$' ORDER BY type, name";
		}
		else
		{
			$this->sql_obj->string	= "SELECT id as id_record, name, type, content, ttl, prio FROM `dns_records` WHERE id_domain='". $this->id ."' ORDER BY type, name";
		}

		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			foreach ($this->sql_obj->data as $data_records)
			{
				// fix international formatting
				if ($this->format == 'idn')
				{
					$data_records["name"]		= idn_to_ascii($data_records["name"]);
					$data_records["content"]	= idn_to_ascii($data_records["content"]);
				}

				$this->data["records"][] = $data_records;
			}


			if (!function_exists("sort_domain_records"))
			{
				function sort_domain_records($a, $b)
				{
					if ($a["prio"])
					{
						return strnatcmp($a["prio"], $b["prio"]);
					}

					if ($a["name"] == $b["name"])
					{
						// sort by content
						return strnatcmp($a["content"], $b["content"]);
					}
					else
					{
						// sort by name field
						return strnatcmp($a["name"], $b["name"]);
					}
				}
			}

			// re-sort with PHP to fix ip address ordering
			usort( $this->data["records"], 'sort_domain_records');


			return 1;
		}

		
		// failure
		return 0;

	} // end of load_data_record_all


	/*
		load_data_record_custom

		Load all the records for the selected domain into the $this->data["records"] array. If you wish to modifiy or query specific
		records, please refer to the domain_records class.

		Values
		offset
		limit

		Returns
		0	failure
		1	success
	*/
	function load_data_record_custom($offset = 0, $limit = 0)
	{
		log_debug("domains", "Executing load_data_record_custom($offset, $limit)");

		// need actual range
		$limit = $offset + $limit;

		// load array of custom types
		$custom_types = sql_get_singlecol("SELECT type as value FROM dns_record_types WHERE user_selectable = 1");


		// load all records - we can't do a partial limit select, since the sorting in SQL
		// is not suitable for correct domain ordering

		if ($this->load_data_record_all(1))
		{
			log_write("debug", "domains", "Loaded all records, selecting limited set of custom records.");


			$data_new = array();

			$j=0;

			for ($i=0; $i < count(array_keys($this->data["records"])); $i++)
			{
				if (in_array($this->data["records"][$i]["type"], $custom_types))
				{
					// custom record selected
					if ($j >= $offset && $j <= $limit)
					{
						$data_new[] = $this->data["records"][$i];
					}

					$j++;
				}
			}


			$this->data["records"] = $data_new;
			unset($data_new);

			return 1;

		} // end of record load

		// failure
		return 0;

	} // end of load_data_record_all



	/*
		data_record_custom_count

		Returns count of the number of custom records in a domain, used for various UI
		functions that require the knowledge of record count.

		Returns
		#	Integer Count
	*/
	
	function data_record_custom_count() 
	{
		log_debug("domain", "Executing load_data_record_custom_count()");

		return sql_get_singlevalue("SELECT COUNT(*) AS value FROM `dns_records` WHERE type IN (SELECT type from dns_record_types where user_selectable = 1) and id_domain='" . $this->id . "' AND name NOT REGEXP '^[0-9]*-[0-9]*-[0-9]*-[0-9]*$'");
	}



	/*
		action_create

		Create a new domain based on the data in $this->data

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_create()
	{
		log_debug("domain", "Executing action_create()");

		// create a new domain
		$this->sql_obj->string	= "INSERT INTO `dns_domains` (domain_name, domain_description) VALUES ('". $this->data["domain_name"] ."', '')";
		$this->sql_obj->execute();

		$this->id = $this->sql_obj->fetch_insert_id();

		return $this->id;

	} // end of action_create




	/*
		action_update

		Update a domain's details based on the data in $this->data. If no ID is provided,
		it will first call the action_create function.

		Note that this function will not update the serial, call the action_update_serial function
		to do so once all the updates have been made.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_update()
	{
		log_debug("domain", "Executing action_update()");


		/*
			Start Transaction
		*/
		$this->sql_obj->trans_begin();


		/*
			If no ID supplied, create a new domain first
		*/
		if (!$this->id)
		{
			$mode = "create";

			if (!$this->action_create())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}




		/*
			Update domain details
		*/

		$this->sql_obj->string	= "UPDATE `dns_domains` SET "
						."domain_name='". $this->data["domain_name"] ."', "
						."domain_description='". $this->data["domain_description"] ."', "
						."soa_hostmaster='". $this->data["soa_hostmaster"] ."', "
						."soa_serial='". $this->data["soa_serial"] ."', "
						."soa_refresh='". $this->data["soa_refresh"] ."', "
						."soa_retry='". $this->data["soa_retry"] ."', "
						."soa_expire='". $this->data["soa_expire"] ."', "
						."soa_default_ttl='". $this->data["soa_default_ttl"] ."' "

						."WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();



		/*
			Update domain-group relationships

			Domains can belong to one or more groups, we should update the group relationships based on the selected options.
		*/

		// delete any existing entires
		$this->sql_obj->string	= "DELETE FROM dns_domains_groups WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();


		// domain-group selection data
		$sql_group_obj		= New sql_query;
		$sql_group_obj->string	= "SELECT id FROM name_servers_groups";
		$sql_group_obj->execute();

		if ($sql_group_obj->num_rows())
		{
			// fetch all the name server groups and see which are selected for this domain
			$sql_group_obj->fetch_array();

			foreach ($sql_group_obj->data as $data_group)
			{
				if (!empty($this->data["name_server_group_". $data_group["id"] ]))
				{
					$this->sql_obj->string	= "INSERT INTO dns_domains_groups (id_group, id_domain) VALUES ('". $data_group["id"] ."', '". $this->id ."')";
					$this->sql_obj->execute();
				}
			}
		}

		unset($sql_group_obj);




		/*
			Commit
		*/

		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain", "An error occured when updating the domain.");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "domain", "Domain has been successfully updated.");

				$log 			= New changelog;
				$log->id_domain		= $this->id;

				$log->log_post("audit", "Domain ". $this->data["domain_name"] ." details updated.");

			}
			else
			{
				log_write("notification", "domain", "Domain successfully created.");


				$log 			= New changelog;
				$log->id_domain		= $this->id;

				$log->log_post("audit", "New domain ". $this->data["domain_name"] ." created.");
			}
			
			return $this->id;
		}

	} // end of action_update



	/*
		action_update_serial

		Updates the serial on the selected domain, this will cause the name servers to reload the domain configuration, only call
		it once you have finished making all changes to the domain and records, since it's pointless to reload for each record.

		Returns
		0	failure
		#	New serial value
	*/
	function action_update_serial()
	{
		log_debug("domain", "Executing action_update_serial()");


		/*
			Start Transaction
		*/
		$this->sql_obj->trans_begin();




		/*
			Determine new serial
		*/

		$this->sql_obj->string	= "SELECT soa_serial FROM `dns_domains` WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();
		$this->sql_obj->fetch_array();

		$old_serial = $this->sql_obj->data[0]["soa_serial"];


		if (!$this->data["soa_serial"])
		{
			// no serial exists (eg: new domain) we need to generate
			log_write("debug", "domains", "No serial currently exists, a new one will be generated based around the date");

			$this->data["soa_serial"]	= date("Ymd") ."01";
		}
		elseif ($old_serial == $this->data["soa_serial"])
		{
			// the serial hasn't changed, we need to determine new option ourselves
			log_write("debug", "domains", "The serial has not been updated by the user, so we must automatically calculate the new serial");

			if (strlen($this->data["soa_serial"]) <= 10)
			{
				// string is probably in common form of YYYYMMDDXX
				preg_match("/^([0-9]{8})([0-9]{2})$/", $this->data["soa_serial"], $matches);

				if ($matches[1] == date("Ymd"))
				{
					// date is the same, increment counter
					$this->data["soa_serial"] = $matches[0] + 1;
				}
				else
				{
					// old date, we can just generate new one
					$this->data["soa_serial"] = date("Ymd") . "01";
				}
			}
			else
			{
				// it's larger than the standard YYYYMMDDXX string format,
				// so best if we just increment it.

				$this->data["soa_serial"]++;
			}
		}
		else
		{
			log_write("debug", "domains", "The serial has been updated by the user manually, no automated recaluclation of the serial will take place");
		}



		/*
			Update domain SOA serial
		*/

		$this->sql_obj->string	= "UPDATE `dns_domains` SET "
						."soa_serial='". $this->data["soa_serial"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();

	


		/*
			Update configuration status versions
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "UPDATE `config` SET value='". time() ."' WHERE name='SYNC_STATUS_CONFIG' LIMIT 1";
		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain", "An error occured when updating the domain serial.");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			log_write("debug", "domain", "The domain serial has been updated to ". $this->data["soa_serial"] ." and nameservers have been instructed to reload.");


			$log 			= New changelog;
			$log->id_domain		= $this->id;

			$log->log_post("audit", "Domain ". $this->data["domain_name"] ." serial updated to ". $this->data["soa_serial"] ."");

		
			return $this->data["soa_serial"];
		}

	} // end of action_update_serial




	/*
		action_update_ns

		Updates the nameserver configuration on the domain, setting the nameservers for the domain to be the name servers
		belonging to the configured domain groups.

		Returns
		0	failure
		1	success
	*/
	function action_update_ns()
	{
		log_debug("domain", "Executing action_update_ns()");


		/*
			Start Transaction
		*/
		$this->sql_obj->trans_begin();



		/*
			Delete old nameserver records

			We delete any NS record that matches the domain and has the name of the domain. Any other NS records we leave, since they
			might be for things such as subdomains.
		*/

		$this->sql_obj->string		= "DELETE FROM `dns_records` WHERE id_domain='". $this->id ."' AND type='NS' AND name='". $this->data["domain_name"] ."'";
		$this->sql_obj->execute();



		/*
			Fetch all name servers belonging to the groups selected for the domain.
		*/

		$obj_ns_sql		= New sql_query;
		$obj_ns_sql->string	= "SELECT name_servers.id as server_id, name_servers.server_name as server_name, name_servers.server_type as server_type FROM name_servers LEFT JOIN dns_domains_groups ON dns_domains_groups.id_group = name_servers.id_group WHERE dns_domains_groups.id_domain='". $this->id ."' AND name_servers.server_record='1'";
		$obj_ns_sql->execute();
		$obj_ns_sql->fetch_array();

		if ($obj_ns_sql->num_rows())
		{
			/*
				Create new nameserver records
			*/
			foreach ($obj_ns_sql->data as $data_ns)
			{
				switch ($data_ns["server_type"])
				{
					case "route53":
						// Route53 delegated name servers are recorded in the mapping table. We just take
						// the values from that and create an NS record for each one.

						$delegated_zones = sql_get_singlevalue("SELECT delegated_ns as value FROM cloud_zone_map WHERE id_name_server='". $data_ns["server_id"] ."' AND id_domain='". $this->id ."'");

						if ($delegated_zones)
						{
							foreach (unserialize($delegated_zones) as $nameserver)
							{
								$this->sql_obj->string		= "INSERT INTO `dns_records` (id_domain, name, type, content, ttl) VALUES ('". $this->id ."', '". $this->data["domain_name"] ."', 'NS', '$nameserver', '". $GLOBALS["config"]["DEFAULT_TTL_NS"] ."')";
								$this->sql_obj->execute();
							}
						}
						else
						{
							log_write("warning", "inc_domains", "Domain is configured for Route53, but doesn't have delegated NS records yet.");
						}
					break;

					case "api":
					default:
						// Create single NS entry for this Name Server
						$this->sql_obj->string		= "INSERT INTO `dns_records` (id_domain, name, type, content, ttl) VALUES ('". $this->id ."', '". $this->data["domain_name"] ."', 'NS', '". $data_ns["server_name"] ."', '". $GLOBALS["config"]["DEFAULT_TTL_NS"] ."')";
						$this->sql_obj->execute();
					break;
				}
			}
		}

	

		/*
			Commit
		*/

		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain", "An error occured when updating the name server records for domain ". $this->data["domain_name"] ." (". $this->id .")");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			log_write("notification", "domain", "Nameserver configuration settings and domain serials updated for domain ". $this->data["domain_name"] ."");
	
			$log 			= New changelog;
			$log->id_domain		= $this->id;

			$log->log_post("audit", "Updated nameserver (NS) records for ". $this->data["domain_name"] ."");


			return 1;
		}

	} // end of action_update_ns




	/*
		action_delete

		Deletes a domain

		Results
		0	failure
		1	success
	*/
	function action_delete()
	{
		log_debug("domain", "Executing action_delete()");

		/*
			Start Transaction
		*/

		$this->sql_obj->trans_begin();


		/*
			Delete domain from cloud providers (eg Route53)
		*/

		$obj_ns_sql		= New sql_query;
		$obj_ns_sql->string	= "SELECT name_servers.id as server_id, name_servers.server_type as server_type FROM name_servers LEFT JOIN dns_domains_groups ON dns_domains_groups.id_group = name_servers.id_group WHERE dns_domains_groups.id_domain='". $this->id ."' AND name_servers.server_record='1'";
		$obj_ns_sql->execute();
		$obj_ns_sql->fetch_array();

		if ($obj_ns_sql->num_rows())
		{
			foreach ($obj_ns_sql->data as $data_ns)
			{
				switch ($data_ns["server_type"])
				{
					case "route53":

						$obj_route53 = New cloud_route53;

						$obj_route53->select_account($data_ns["server_id"]);
						$obj_route53->select_domain($this->id);

						$obj_route53->action_delete_domain();

					break;

					default:
						// nothing todo.
					break;
				}
			}
		}
	


		/*
			Delete domain
		*/
			
		$this->sql_obj->string	= "DELETE FROM `dns_domains` WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();


		/*
			Delete association with name server group
		*/

		$this->sql_obj->string	= "DELETE FROM `dns_domains_groups` WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();

		$this->sql_obj->string	= "DELETE FROM `cloud_zone_map` WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();


		/*
			Delete domain records
		*/
			
		$this->sql_obj->string	= "DELETE FROM `dns_records` WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();


		/*
			Update configuration status versions - this will trigger the name servers to delete
			the old domain
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "UPDATE `config` SET value='". time() ."' WHERE name='SYNC_STATUS_CONFIG' LIMIT 1";
		$sql_obj->execute();


		/*
			Un-associated any matched log entries
			
			(note: logging is always local)
		*/

		$sql_obj		= New sql_query;
		$sql_obj->string	= "UPDATE logs SET `id_domain`='0' WHERE `id_domain`='". $this->id ."'";
		$sql_obj->execute();



		/*
			Commit
		*/
		
		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain", "An error occured whilst trying to delete the selected domain.");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			log_write("notification", "domain", "The domain has been successfully deleted.");


			$log 			= New changelog;

			$log->log_post("audit", "Domain ". $this->data["domain_name"] ." has been deleted.");


			return 1;
		}
	}





	/*
		action_autofill_reverse

		Automatically fills a reverse IPv4 domain with records. Typically called as an option when creating
		a new reverse IPv4 domain.

		Requires
		this->data["ipv4_network"]
		this->data["ipv4_autofill_domain"]
		this->data["soa_default_ttl"]

		Options
		$this->data["ipv4_autofill_reverse_from_forward"]		0 == disabled, 1 == create matching reverse records for existing forward records for this IP

		Returns
		0	Unexpected Failure
		1	Success
	*/
	
	function action_autofill_reverse()
	{
		log_write("debug", "domains", "Executing action_autofill_reverse()");


		// this is a new domain, we need to seed the domain, by calculating all the addresses
		// in the domain and then creating a record for each one.

		$obj_record		= New domain_records;
		$obj_record->id		= $this->id;
		$obj_record->data	= $this->data;	// shortcut load

		$tmp_network = explode(".", $this->data["ipv4_network"]);


		// if enabled, we should fetch all forward A records that match any of the specified
		$forward_matches = array();

		if ($this->data["ipv4_autofill_reverse_from_forward"])
		{
			log_write("debug", "domains", "ipv4_autofill_reverse_from_forward enabled, pulling data from forward records (if any)");

			$this->sql_obj->string = "SELECT dns_domains.domain_name as domain_name, dns_records.name as record_name, dns_records.content as record_ipv4 FROM dns_records LEFT JOIN dns_domains ON dns_domains.id = dns_records.id_domain WHERE dns_records.content LIKE '". $tmp_network[0] .".". $tmp_network[1] .".". $tmp_network[2] .".%' ORDER BY dns_records.name";
			$this->sql_obj->execute();
			
			if ($this->sql_obj->num_rows())
			{
				// Matching records found - the selection log here isn't too smart, the reverse of the IP will be set to
				// whatever record happens to come last in the query. It's not going to be perfect, but it's impossible
				// to get a perfect solution here, this feature is best-efforts only really.

				$this->sql_obj->fetch_array();

				foreach ($this->sql_obj->data as $data_forwards)
				{
					if ($data_forwards["record_name"] == "@")
					{
						$forward_matches[ $data_forwards["record_ipv4"] ] = $data_forwards["domain_name"];
					}
					else
					{
						$forward_matches[ $data_forwards["record_ipv4"] ] = $data_forwards["record_name"] .".". $data_forwards["domain_name"];
					}
				}
			}
		}

		// assuming /24 only
		for ($i=0; $i < 255; $i++)
		{
			// overwrite any existing records
			$obj_record->id_record			= $obj_record->find_forward_record($i);

			// default PTR style entry
			$obj_record->data_record["type"]	= "PTR";
			$obj_record->data_record["name"]	= $i;
			$obj_record->data_record["prio"]	= 0;
			$obj_record->data_record["content"]	= $tmp_network[0] ."-". $tmp_network[1] ."-". $tmp_network[2] ."-$i.". $this->data["ipv4_autofill_domain"];
			$obj_record->data_record["ttl"]		= $this->data["soa_default_ttl"];

			// if enabled, check for the existance of forward domain records we can use in reverse
			if (!empty($forward_matches))
			{
				if ($forward_matches[ $tmp_network[0] .".". $tmp_network[1] .".". $tmp_network[2] .".$i" ])
				{
					$obj_record->data_record["content"] = $forward_matches[ $tmp_network[0] .".". $tmp_network[1] .".". $tmp_network[2] .".$i" ];
				}
			}


			// create/update record
			$obj_record->action_update_record();
		}

		unset($obj_record);


		return 1;

	} // end of action_autofill_reverse



	/*
		action_autofill_forward

		Automatically fills the matching forwards domain for a reverse IPv4 domain with records. Typically called as an option
		when creating a new reverse IPv4 domain, along with action_autofill_reverse.

		Requires
		this->data["ipv4_network"]			IPv4 /24 range
		this->data["ipv4_autofill_domain_id"]		ID of the forward domain to fill
		this->data["soa_default_ttl"]			Default Record TTL

		Returns
		0	Unexpected Failure
		1	Success
	*/

	function action_autofill_forward()
	{
		log_write("debug", "domains", "Executing action_autofill_forward()");

		// create
		$obj_record		= New domain_records;
		$obj_record->id		= $this->data["ipv4_autofill_domain_id"];
		$obj_record->load_data();

		$tmp_network = explode(".", $this->data["ipv4_network"]);


		// assuming /24 only
		for ($i=0; $i < 255; $i++)
		{
			// check if there is an existing record.
			$obj_record->id_record			= $obj_record->find_forward_record($tmp_network[0] ."-". $tmp_network[1] ."-". $tmp_network[2] ."-$i");

			$obj_record->data_record["type"]	= "A";
			$obj_record->data_record["name"]	= $tmp_network[0] ."-". $tmp_network[1] ."-". $tmp_network[2] ."-$i";	// name
			$obj_record->data_record["content"]	= $tmp_network[0] .".". $tmp_network[1] .".". $tmp_network[2] .".$i";	// ipv4
			$obj_record->data_record["ttl"]		= $this->data["soa_default_ttl"];
		
			$obj_record->action_update_record();
		}
		
		// update serial to regenerate domain files
		$obj_record->action_update_serial();


		unset($obj_record);

		return 1;

	} // end of action_autofill_forward


} // end of class:domain




/*
	CLASS DOMAIN_RECORDS

	Functions for handling domain record entries.
*/
class domain_records extends domain
{
	var $id_record;		// ID of the domain record
	var $data_record;	// domain record data




	/*
		verify_id_record

		Checks that the provided ID is a valid record and belongs to the selected domain

		Results
		0	Failure to find the ID
		1	Success - domain exists
	*/

	function verify_id_record()
	{
		log_debug("domain_records", "Executing verify_id_record()");


		if ($this->id_record)
		{
			$this->sql_obj->string	= "SELECT id as id_record, id_domain FROM `dns_records` WHERE id='". $this->id_record ."' LIMIT 1";
			$this->sql_obj->execute();

			if ($this->sql_obj->num_rows())
			{
				$this->sql_obj->fetch_array();

				if ($this->id)
				{
					if ($this->sql_obj->data[0]["id_domain"] == $this->id)
					{
						return 1;
					}
					else
					{
						log_write("error", "domain_records", "The selected record (". $this->id_record .") does not match the selected domain (". $this->id .")");
						return 0;
					}
				}
				else
				{
					$this->id = $this->sql_obj->data[0]["id_domain"];

					return 1;
				}

			}
		}

		return 0;

	} // end of verify_id_record


	/*
		find_forward_record

		Takes the provided name field and returns the matching ID
		from the domain.

		This function is primarily intended for use for functions wanting
		to check the existance of a record

		Fields
		name/origin

		Returns
		0		No Match
		#		ID of record
	*/

	function find_forward_record($name)
	{
		log_debug("domain_records", "Executing find_forward_record($name)");

		$this->sql_obj->string = "SELECT id FROM `dns_records` WHERE id_domain='". $this->id ."' AND name='". $name ."' LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			$this->id_record = $this->sql_obj->data[0]["id"];

			return $this->id_record;
		}

		return 0;

	} // end of find_forward_record



	/*
		find_reverse_record

		Takes the provided IP address and finds the matching domain and record
		for that address.

		Fields
		ip_address	IPv4 address

		Returns
		0		No match
		1		Valid domain or record exists.
	*/

	function find_reverse_domain($ip_address)
	{
		log_debug("domain_records", "Executing find_reverse_record($ip_address)");


		/*
			With IPv4 we are dealing with /24s... for IPv6, we need to
			convert the record to ARPA and see which domain it belongs to
		*/

		switch (ip_type_detect( $ip_address ))
		{
			case "4":
				$ip_arpa = ipv4_convert_arpa( $ip_address );
			
				$tmp				= explode(".", $ip_address);
				$ip_ptr_name			= $tmp[3];
			break;

			case "6":
				$ip_arpa = ipv6_convert_arpa( $ip_address );
				$ip_ptr  = $ip_arpa;

				// We fetch a list of all the IPv6 reverse domains
				// Note: this is cached in memory after first lookup for duration of this page session
				$reverse_ipv6_domains = array();

				$this->sql_obj->string	= "SELECT domain_name FROM `dns_domains` WHERE domain_name LIKE '%ip6.arpa'";
				$this->sql_obj->execute();

				if ($this->sql_obj->num_rows())
				{
					$this->sql_obj->fetch_array();

					foreach ($this->sql_obj->data as $data_row)
					{
						$reverse_ipv6_domains[] = $data_row["domain_name"];
					}
				}

				if (!$reverse_ipv6_domains)
				{
					return 0;
				}

				// chomp the arpa address till we find the longest match
				while ($ip_arpa)
				{
					foreach ($reverse_ipv6_domains as $domain)
					{
						if ($ip_arpa == $domain)
						{
							break 2;
						}
					}

					$ip_arpa = substr( $ip_arpa, 1 );

					if ($ip_arpa == "")
					{
						// no matching domain
						return 0;
					}
				}

				// get domain name for final domain
				$ip_arpa	= $domain;
				$ip_ptr_name	= $ip_ptr;
			break;

			default:
				return 0;
			break;
		}


		// Fetch domain ID based on the arpa name of the domain
		$this->sql_obj->string	= "SELECT id FROM `dns_domains` WHERE domain_name='". $ip_arpa ."' LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			// fetch domain ID
			$this->sql_obj->fetch_array();

			$this->id	= $this->sql_obj->data[0]["id"];

			log_write("debug", "domain_records", "Found matching domain ". $ip_arpa ." with ID of ". $this->id ."");

			// now fetch the ID for the record that belongs to this domain
			$this->sql_obj->string	= "SELECT id FROM `dns_records` WHERE id_domain='". $this->id ."' AND name='". $ip_ptr_name ."' LIMIT 1";
			$this->sql_obj->execute();

			if ($this->sql_obj->num_rows())
			{
				$this->sql_obj->fetch_array();

				$this->id_record		= $this->sql_obj->data[0]["id"];

				log_write("debug", "domain_records", "Found matching record with ID of ". $this->id_record ."");
			}


			return 1;
		}
		else
		{
			log_write("warning", "domain_records", "Unable to find domain $ip_arpa for address $ip_address");
		}

		return 0;

	} // end of find_reverse_domain



	/*
		load_data_record

		Load the data for the selected record into $this->data_record

		Returns
		0	failure
		1	success
	*/
	function load_data_record()
	{
		log_debug("domain_records", "Executing load_data_record()");

		$this->sql_obj->string	= "SELECT * FROM `dns_records` WHERE id='". $this->id_record ."' LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			$this->data_record = $this->sql_obj->data[0];

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_record





	/*
		action_create_record
		
		Create a new record based on the data in $this->data_record

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_create_record()
	{
		log_debug("domain_records", "Executing action_create()");

		// create a new record
		$this->sql_obj->string	= "INSERT INTO `dns_records` (id_domain, type) VALUES ('". $this->id ."', '". $this->data_record["type"]."')";
		$this->sql_obj->execute();

		$this->id_record = $this->sql_obj->fetch_insert_id();

		return $this->id_record;

	} // end of action_create_record




	/*
		action_update_record

		Update a domain record, based on the data in $this->data_record. If no ID is provided,
		it will first call the action_create function.

		Note that this function will not update the serial, call the action_update_serial function
		to do so once all the updates have been made.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_update_record()
	{
		log_debug("domain_record", "Executing action_update_record()");


		/*
			Start Transaction
		*/
		$this->sql_obj->trans_begin();


		/*
			If no ID supplied, create a new domain record
		*/
		if (!$this->id_record)
		{
			$mode = "create";

			if (!$this->action_create_record())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}


		
		/*
			Seed key values when unspecified
		*/
		if (!isset($this->data_record["name"]))
		{
			$this->data_record["name"]	= $this->data["name"];
		}

		if (empty($this->data_record["prio"]))
		{
			$this->data_record["prio"] = 0;
		}

		if (empty($this->data_record["ttl"]))
		{
			// can't have an empty TTL, but zero is a valid TTL. Note that
			// some systems will enforce mimimum TTLs like 60 seconds anyway.
			$this->data_record["ttl"] = 0;
		}




		/*
			Update record
		*/

		$this->sql_obj->string	= "UPDATE `dns_records` SET "
						."name='". $this->data_record["name"] ."', "
						."type='". $this->data_record["type"] ."', "
						."content='". $this->data_record["content"] ."', "
						."ttl='". $this->data_record["ttl"] ."', "
						."prio='". $this->data_record["prio"] ."' "
						."WHERE id='". $this->id_record ."' LIMIT 1";
		$this->sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain_records", "An error occured when updating the domain record.");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "domain_records", "Domain record has been successfully updated.");

				$log 			= New changelog;
				$log->id_domain		= $this->id;

				$log->log_post("audit", "Updated domain record type ". $this->data_record["type"] ." ". $this->data_record["name"] ."/". $this->data_record["content"] ." for domain ". $this->data["domain_name"] ."");

			}
			else
			{
				log_write("notification", "domain_records", "Domain record successfully created.");

				$log 			= New changelog;
				$log->id_domain		= $this->id;

				$log->log_post("audit", "Updated domain record type ". $this->data_record["type"] ." ". $this->data_record["name"] ."/". $this->data_record["content"] ." for domain ". $this->data["domain_name"] ."");


			}
			
			return $this->id_record;
		}

	} // end of action_update_record



	/*
		action_delete_record

		Deletes a domain record.

		Note that this function will not update the serial, call the action_update_serial function
		to do so once all the updates have been made.

		Results
		0	failure
		1	success
	*/
	function action_delete_record()
	{
		log_debug("domain", "Executing action_delete_record()");

		/*
			Start Transaction
		*/

		$this->sql_obj->trans_begin();


		/*
			Delete domain record
		*/
			
		$this->sql_obj->string	= "DELETE FROM `dns_records` WHERE id='". $this->id_record ."' LIMIT 1";
		$this->sql_obj->execute();


		/*
			Commit
		*/
		
		if (error_check())
		{
			$this->sql_obj->trans_rollback();

			log_write("error", "domain_records", "An error occured whilst trying to delete the selected domain record.");

			return 0;
		}
		else
		{
			$this->sql_obj->trans_commit();

			log_write("notification", "domain_records", "The domain record has been successfully deleted.");


			$log 			= New changelog;
			$log->id_domain		= $this->id;

			$log->log_post("audit", "Domain record type ". $this->data_record["type"] ." ". $this->data_record["name"] ."/". $this->data_record["content"] ." has been deleted from domain ". $this->data["domain_name"] ."");

			return 1;
		}
	}


	/*
		validate_custom_records

		Function used for validating custom records - takes supplied data or pulls from POST
		and then returns validated records.

		If an error is encountered, raises error log type - should by checked with by error_check()
		on the calling page.

		Values
		$data_source	Source data - either array, or empty to default to POST.

		Returns
		$data		Returns validated records.
	*/

	function validate_custom_records($data_orig = array())
	{
		log_debug("domain", "Executing validate_custom_records(array_data)");

		if (!isset($this->data))
		{
			// we need the domain details if we don't already have them
			$this->load_data();
		}

		$data		= array();
		$data_tmp	= array();


		if (!empty($data_orig))
		{
			/*
 				Supplied Array Data - this data has some amount of pre-processing
				done, having already run through the javascript validation.
			*/

			log_debug("domain", "Using supplied array data in \$data_orig");

			/*
				Fetch Data
			*/
		
			$data["custom"]["num_records"] = count(array_keys($data_orig));
			
			for ($i=0; $i < $data["custom"]["num_records"]; $i++)
			{
				$data_tmp[$i]["id"]			= @security_script_input_predefined("int", $data_orig[$i]["id"], 1);
				$data_tmp[$i]["type"]			= @security_script_input_predefined("any", $data_orig[$i]["type"], 1);
				$data_tmp[$i]["ttl"]			= @security_script_input_predefined("int", $data_orig[$i]["ttl"], 1);
				$data_tmp[$i]["name"]			= @security_script_input_predefined("any", $data_orig[$i]["name"], 1);
				$data_tmp[$i]["prio"]			= @security_script_input_predefined("int", $data_orig[$i]["prio"], 0);
				$data_tmp[$i]["content"]		= @security_script_input_predefined("any", $data_orig[$i]["content"], 1);
				$data_tmp[$i]["reverse_ptr"]		= @security_script_input_predefined("checkbox", $data_orig[$i]["reverse_ptr"], 1);
				$data_tmp[$i]["reverse_ptr_orig"]	= @security_script_input_predefined("checkbox", $data_orig[$i]["reverse_ptr_orig"], 1);
				$data_tmp[$i]["delete_undo"]		= @security_script_input_predefined("any", $data_orig[$i]["delete_undo"], 1);
				

				if (empty($data_tmp[$i]["mode"]) || ($data_tmp[$i]["mode"] != "delete" && $data_tmp[$i]["mode"] != "update"))
				{
					// mode undetermined, run check
					if ($data_tmp[$i]["id"] && $data_tmp[$i]["delete_undo"] == "true")
					{
						$data_tmp[$i]["mode"] = "delete";
					}
					else
					{
						if (!empty($data_tmp[$i]["content"]) && $data_tmp[$i]["delete_undo"] == "false")
						{
							$data_tmp[$i]["mode"] = "update";
						}
					}
				}
			}
		}
		elseif ( isset($_POST['record_custom_page']) )
		{
			/*
				Fetch data from POST - easiest way, since we can take advantage of smart
				error handling functions built in.
			*/

			// fetch number of records
			$data["custom"]["num_records"]          = @security_form_input_predefined("int", "num_records_custom", 0, "");

			for ($i = 0; $i < $data["custom"]["num_records"]; $i++)
			{
				/*
					Fetch Data
				*/
				$data_tmp[$i]["id"]			= @security_form_input_predefined("int", "record_custom_". $i ."_id", 0, "");
				$data_tmp[$i]["type"]			= @security_form_input_predefined("any", "record_custom_". $i ."_type", 0, "");
				$data_tmp[$i]["ttl"]			= @security_form_input_predefined("int", "record_custom_". $i ."_ttl", 0, "");
				$data_tmp[$i]["name"]			= @security_form_input_predefined("any", "record_custom_". $i ."_name", 0, "");
				$data_tmp[$i]["content"]		= @security_form_input_predefined("any", "record_custom_". $i ."_content", 0, "");
				$data_tmp[$i]["reverse_ptr"]		= @security_form_input_predefined("checkbox", "record_custom_". $i ."_reverse_ptr", 0, "");
				$data_tmp[$i]["reverse_ptr_orig"]	= @security_form_input_predefined("checkbox", "record_custom_". $i ."_reverse_ptr_orig", 0, "");
				$data_tmp[$i]["delete_undo"]		= @security_form_input_predefined("any", "record_custom_". $i ."_delete_undo", 0, "");


				/*
					Process Raw Data
				*/
				if ($data_tmp[$i]["id"] && $data_tmp[$i]["delete_undo"] == "true")
				{
					$data_tmp[$i]["mode"] = "delete";
				}
				else
				{
					if (!empty($data_tmp[$i]["content"]) && $data_tmp[$i]["delete_undo"] == "false")
					{
						$data_tmp[$i]["mode"] = "update";
					}
				}


			}
		}



		/*
			Process Validated Inputs
		*/

		if (!empty($data_tmp))
		{
			log_write("debug", "domains", "Record values obtained, running detailed check");

			for ($i = 0; $i < $data["custom"]["num_records"]; $i++)
			{
				/*
					Error Handling
				*/


				// verify name syntax
				if ($data_tmp[$i]["name"] == "*" || preg_match("/^\*\.[A-Za-z0-9\p{L}:._-]+$/", $data_tmp[$i]["name"]))
				{
					// wildcard records are annoying - wildcards must be standalone, and can't be part of a sring
					// OK  -> *
					// OK  -> *.example.com
					// BAD -> abc*.example.com
					// BAD -> std*abc.example.com

					// nothing todo
				}
				elseif ($data_tmp[$i]["name"] != "@" && !preg_match("/^[A-Za-z0-9\p{L}:._-]*$/", $data_tmp[$i]["name"]))
				{
					// all other record types
					log_write("error", "process", "Sorry, the value you have entered for record ". $data_tmp[$i]["name"] ." contains invalid charactors");

					error_flag_field("record_custom_". $i ."");
				}


				// validate content and name formatting per domain type
				if ($data_tmp[$i]["name"] != "")
				{
					switch ($data_tmp[$i]["type"])
					{
						case "A":
							// validate IPv4
							if (!preg_match("/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/", $data_tmp[$i]["content"]))
							{
								// invalid IP address
								log_write("error", "process", "A record for ". $data_tmp[$i]["name"] ." did not validate as an IPv4 address");
								error_flag_field("record_custom_". $i ."");
							}
						break;

						case "AAAA":
							// validate IPv6
							if (filter_var($data_tmp[$i]["content"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) == FALSE)
							{
								// invalid IP address
								log_write("error", "process", "AAAA record for ". $data_tmp[$i]["name"] ." did not validate as an IPv6 address");
								error_flag_field("record_custom_". $i ."");
							}
						break;

						case "CNAME":
							// validate CNAME
							if ($data_tmp[$i]["content"] != "@" && !preg_match("/^[A-Za-z0-9\p{L}._-]*$/", $data_tmp[$i]["content"]))
							{
								// invalid CNAME
								log_write("error", "process", "CNAME record for ". $data_tmp[$i]["name"] ." contains invalid characters.");
								error_flag_field("record_custom_". $i ."");
							}

							// make sure it's not an IP
							if (filter_var($data_tmp[$i]["content"], FILTER_VALIDATE_IP) == $data_tmp[$i]["content"])
							{
								// CNAME is pointing at an IP
								log_write("error", "process", "CNAME record for ". $data_tmp[$i]["name"] ." is incorrectly referencing an IP address.");
								error_flag_field("record_custom_". $i ."");
							}
						break;

						case "SRV":
							// validate SRV name (_service._proto.name OR _service._proto))
							if (!preg_match("/^_[A-Za-z0-9\p{L}.-]*\._[A-Za-z\p{L}]*\.[A-Za-z0-9\p{L}.-]*$/", $data_tmp[$i]["name"])
							    && !preg_match("/^_[A-Za-z0-9\p{L}.-]*\._[A-Za-z\p{L}]*$/", $data_tmp[$i]["name"]))
							{
								log_write("error", "process", "SRV record for ". $data_tmp[$i]["name"] ." is not correctly formatted - name must be: _service._proto.name");
								error_flag_field("record_custom_". $i ."");
							}

							// validate SRV content (priority, weight, port, target/host)
							if (!preg_match("/^[0-9]*\s[0-9]*\s[0-9]*\s[A-Za-z0-9\p{L}.-]*$/", $data_tmp[$i]["content"]))
							{
								log_write("error", "process", "SRV record for ". $data_tmp[$i]["name"] ." is not correctly formatted - content must be: priority weight port target/hostname");
								error_flag_field("record_custom_". $i ."");
							}
						break;

						case "SPF":
						case "TXT":
							// TXT string could be almost anything, just make sure it's quoted.
							$data_tmp[$i]["content"] = str_replace("'", "", $data_tmp[$i]["content"]);
							$data_tmp[$i]["content"] = str_replace('"', "", $data_tmp[$i]["content"]);

							$data_tmp[$i]["content"] = '"'. $data_tmp[$i]["content"] .'"';
						break;

						case "PTR":
								
							if (strpos($this->data["domain_name"], "in-addr.arpa"))
							{
								// IPv4 PTR Record
								// We only pass through the 4th octet to the end user.
								
								if (!preg_match("/^[0-9]*$/", $data_tmp[$i]["name"]))
								{
									log_write("error", "process", "PTR reverse record for ". $data_tmp[$i]["content"] ." should be a single octet.");
									error_flag_field("record_custom_". $i ."");
								}

								if (!preg_match("/^[A-Za-z0-9\p{L}.-]*$/", $data_tmp[$i]["content"]))
								{
									log_write("error", "process", "PTR reverse record for ". $data_tmp[$i]["name"] ." is not correctly formatted.");
									error_flag_field("record_custom_". $i ."");
								}
		
							}
							elseif (strpos($this->data["domain_name"], "ip6.arpa"))
							{
								// IPv6 PTR Record

								// If the record is already in reverse ARPA format, we should convert it first
								if (strpos($data_tmp[$i]["name"], "ip6.arpa"))
								{
									$data_tmp[$i]["name"] = ipv6_convert_fromarpa($data_tmp[$i]["name"]);
								}

								// We pass through a full IPv6 address and maybe a CIDR value - if provided,
								// we should strip off the CIDR and then validate the address and process.
								$data_tmp[$i]["name"] = preg_replace("/\/[0-9]*$/", '', $data_tmp[$i]["name"]);

								if (!filter_var($data_tmp[$i]["name"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
								{
									log_write("error", "process", "Provided PTR IPv6 address for ". $data_tmp[$i]["name"] ." is not a valid IPv6 address.");
									error_flag_field("record_custom_". $i ."");
								}

								if (!preg_match("/^[A-Za-z0-9\p{L}.-]*$/", $data_tmp[$i]["content"]))
								{
									log_write("error", "process", "Provided PTR IPv6 reverse record for ". $data_tmp[$i]["name"] ." is not correctly formatted.");
									error_flag_field("record_custom_". $i ."");
								}

								// convert the record into PTR formatted value
								$data_tmp[$i]["name"] = ipv6_convert_arpa($data_tmp[$i]["name"]);
							}

						break;

						case "SSHFP":
						// validate SSHFP content (algorithm, type, key/fingerprint)
						if (!preg_match("/^[1-4] [1-2] [a-fA-F0-9]+$/", $data_tmp[$i]["content"]))
						{
							log_write("error", "process", "SSHFP record for ". $data_tmp[$i]["name"] ." is not correctly formatted - content must be: algorithm(1-4) type(1-2) <key/fingerprint>");
							error_flag_field("record_custom_". $i ."");
						}
						break;

						case "LOC":
						// validate SSHFP content (algorithm, type, key/fingerprint)
						if (!preg_match("/^[0-9]+( [0-9]+( [0-9]+\.[0-9]+)?)? N|S [0-9]+( [0-9]+( [0-9]+\.[0-9]+)?)? E|W \-?[0-9]+\.[0-9]+m?( [0-9]+\.[0-9]+m?)?( [0-9]+\.[0-9]+m?)?/", $data_tmp[$i]["content"]))
						{
							log_write("error", "process", "LOC record for ". $data_tmp[$i]["name"] ." is not correctly formatted - content must follow RFC 1876");
							error_flag_field("record_custom_". $i ."");
						}
						break;

						case "NS":
						case "MX":
						case "HINFO":
							// nothing todo.
						break;


						default:
							log_write("error", "process", "Unknown record type ". $data_tmp[$i]["type"] ."");

						break;
					}


					// remove excess "." which might have been added
					$data_tmp[$i]["name"]		= rtrim($data_tmp[$i]["name"], ".");
					$data_tmp[$i]["content"]	= rtrim($data_tmp[$i]["content"], ".");


					// verify reverse PTR options
					if ($data_tmp[$i]["reverse_ptr"])
					{
						if ($data_tmp[$i]["type"] == "A" || $data_tmp[$i]["type"] == "AAAA")
						{
							// check if the appropiate reverse DNS domain exists
							$obj_record = New domain_records;

							if (!$obj_record->find_reverse_domain($data_tmp[$i]["content"]))
							{
								// no match
								log_write("error", "process", "Sorry, we can't set a reverse PTR for ". $data_tmp[$i]["content"] ." --&gt; ". $data_tmp[$i]["name"] .", since there is no reverse domain record for that IP address");

								error_flag_field("record_custom_". $i ."");
							}
							else
							{
								// match, record the domain ID and record ID to save a lookup
								$data_tmp[$i]["reverse_ptr_id_domain"]	= $obj_record->id;
								$data_tmp[$i]["reverse_ptr_id_record"]	= $obj_record->id_record;
							}


							// add to the reverse domain list - we use this list to avoid reloading for every record
							if (@!in_array($obj_record->id, $data["reverse"]))
							{
								$data["reverse"][] = $obj_record->id;
							}

							unset($obj_record);
						}
						else
						{
							log_write("error", "process", "A reverse PTR record is only valid for an A or AAAA record");
							error_flag_field("record_custom_". $i ."");
						}
					}


					// add to processing array
					$data["records"][] = $data_tmp[$i];

				}
				else
				{
					/*
						No record name exists - this is only valid if no content is also supplied
					*/

					if (!empty($data_tmp[$i]['content']))
					{
						log_write("error", "process", "Name cannot be empty for IP address: " . $data_tmp[$i]['content']);

						error_flag_field("record_custom_". $i ."");
					}

				}

			} // end of loop through records

		} // end of if records set
		else
		{
			log_write("debug", "domains", "No records provided, no validation performed");
		}


		// return structured array
		return $data;

	} // end of validate_custom_records

} // end of class domain_records



?>
