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
	var $data;

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

		$this->data = array();

		$this->sql_obj->string	= "SELECT * FROM `dns_domains`";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			foreach ($this->sql_obj->data as $data)
			{
				$this->data[] = $data;
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

		Returns
		0	failure
		1	success
	*/
	function load_data_record_all()
	{
		log_debug("domain", "Executing load_data_record_all()");


		$this->sql_obj->string	= "SELECT id as id_record, name, type, content, ttl, prio FROM `dns_records` WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			foreach ($this->sql_obj->data as $data_records)
			{
				$this->data["records"][] = $data_records;
			}

			return 1;
		}

		// failure
		return 0;

	} // end of load_data_record_all




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
		$this->sql_obj->string	= "INSERT INTO `dns_domains` (domain_name) VALUES ('". $this->data["domain_name"] ."')";
		$this->sql_obj->execute();

		$this->id = $this->sql_obj->fetch_insert_id();


		// create a new domain version row for all the servers
		$obj_app_sql		= New sql_query;

		$obj_app_sql->string	= "SELECT id as id_name_server FROM name_servers";
		$obj_app_sql->execute();
		$obj_app_sql->fetch_array();

		foreach ($obj_app_sql->data as $data_server)
		{
			$obj_app_sql->string	= "INSERT INTO `api_domains_versions` (id_domain, id_name_server, status) VALUES ('". $this->id ."', '". $data_server["id_name_server"] ."', 'disabled')";
			$obj_app_sql->execute();
		}


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
			}
			else
			{
				log_write("notification", "domain", "Domain successfully created.");
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
		1	success
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
		
			return 1;
		}

	} // end of action_update_serial




	/*
		action_update_ns

		Updates the nameserver configuration on the domain, setting the nameservers for the domain to be the ones
		setup on NamedManager.

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
			Create new nameserver records
		*/

		$obj_ns_sql		= New sql_query;
		$obj_ns_sql->string	= "SELECT server_name FROM `name_servers`";
		$obj_ns_sql->execute();
		$obj_ns_sql->fetch_array();

		foreach ($obj_ns_sql->data as $data_ns)
		{
			$this->sql_obj->string		= "INSERT INTO `dns_records` (id_domain, name, type, content, ttl) VALUES ('". $this->id ."', '". $this->data["domain_name"] ."', 'NS', '". $data_ns["server_name"] ."', '". $GLOBALS["config"]["DEFAULT_TTL_NS"] ."')";
			$this->sql_obj->execute();
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
			Delete domain
		*/
			
		$this->sql_obj->string	= "DELETE FROM `dns_domains` WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();


		/*
			Delete domain records
		*/
			
		$this->sql_obj->string	= "DELETE FROM `dns_records` WHERE id_domain='". $this->id ."'";
		$this->sql_obj->execute();


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

			return 1;
		}
	}


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
		load_data_record

		Load the data for the selected record into $this->data_record

		Returns
		0	failure
		1	success
	*/
	function load_data_record()
	{
		log_debug("domain_records", "Executing load_data_record()");

		$this->sql_obj->string	= "SELECT * FROM `dns_records` WHERE id='". $this->id ."' LIMIT 1";
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
		if (!$this->data_record["name"])
		{
			$this->data_record["name"]	= $this->data["name"];
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
			}
			else
			{
				log_write("notification", "domain_records", "Domain record successfully created.");
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

			return 1;
		}
	}

} // end of class domain_records



?>
