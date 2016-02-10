<?php
/*
	NAMEDMANAGER SOAP API

	Application management, logging and query APIs for NamedManager.

	* APIs for querying configuration from the NamedManager application in order to generate name server configuration.
	* APIs for management of records from other applications (such as phpfreeradius)
	* APIs to return logging information.

	Refer to the Developer API documentation for information on using this service
	as well as sample code.
*/


// include libraries
require("../include/config.php");
require("../include/amberphplib/main.php");
require("../include/application/main.php");


class api_namedmanager
{
	var $auth_server;		// ID of the DNS server that has authenticated.
	var $auth_online;		// set to 1 if authenticated
	var $auth_admin;		// set to 1 if authenticated as admin
	var $auth_group;		// ID of the group the DNS server belongs to


	/*
		constructor
	*/
	function api_namedmanager()
	{
        $this->auth_server  = isset($_SESSION["auth_server"]) ? $_SESSION["auth_server"] : NULL;                                                                
        $this->auth_online  = isset($_SESSION["auth_online"]) ? $_SESSION["auth_online"] : NULL;
        $this->auth_admin   = isset($_SESSION["auth_admin"]) ? $_SESSION["auth_admin"] : NULL;
        $this->auth_group   = isset($_SESSION["auth_group"]) ? $_SESSION["auth_group"] : NULL;
	}



	/*
		authenticate

		Authenticates a SOAP API client using one of two methods:
		* Against the server table with a specific auth key, used by the nameservers
		* Against the admin API key, used by external applications such as phpfreeradius

		Returns
		0	Failure
		#	ID of the radius server authenticated as
	*/
	function authenticate($server_name, $api_auth_key)
	{
		log_write("debug", "api_namedmanager", "Executing authenticate($server_name, $api_auth_key)");

		// sanitise input
		$server_name	= @security_script_input_predefined("any", $server_name);
		$api_auth_key	= @security_script_input_predefined("any", $api_auth_key);

		if (!$server_name || $server_name == "error" || !$api_auth_key || $api_auth_key == "error")
		{
			throw new SoapFault("Sender", "INVALID_INPUT");
		}


		if ($server_name == "ADMIN_API")
		{
			// validate against admin key, if one has been set
			if ($GLOBALS["config"]["ADMIN_API_KEY"])
			{
				if ($api_auth_key == $GLOBALS["config"]["ADMIN_API_KEY"])
				{
					log_write("debug", "api", "Authentication against API key successful");

					$this->auth_online	= 1;
					$this->auth_admin	= 1;
					$this->auth_group	= NULL;

					$_SESSION["auth_online"]	= $this->auth_online;
					$_SESSION["auth_admin"]		= $this->auth_admin;
					$_SESSION["auth_group"]		= $this->auth_group;

					return 1;
				}
				else
				{
					throw new SoapFault("Sender", "ACCESS_DENIED");
				}
			}
			else
			{
				throw new SoapFault("Sender", "AUTHENTICATION_DISABLED");
			}
		}
		else
		{
			// authenticate against servers table


			// verify input
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id, id_group FROM name_servers WHERE server_name='$server_name' AND api_auth_key='$api_auth_key' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				$sql_obj->fetch_array();

				$this->auth_online		= 1;
				$this->auth_server		= $sql_obj->data[0]["id"];
				$this->auth_group		= $sql_obj->data[0]["id_group"];

				$_SESSION["auth_online"]	= $this->auth_online;
				$_SESSION["auth_server"]	= $this->auth_server;
				$_SESSION["auth_group"]		= $this->auth_group;

				return $this->auth_server;
			}
			else
			{
				throw new SoapFault("Sender", "ACCESS_DENIED");
			}
		}

	} // end of authenticate




	/*
		log_write

		Writes a new log value to the database

		Fields
		timestamp		UNIX timestamp
		log_type		Category (max 10 char)
		log_contents		Contents of log message
	*/

	function log_write($timestamp, $log_type, $log_contents)
	{
		log_write("debug", "api_namedmanager", "Executing get_customer_from_by_code($code_customer)");

		if ($this->auth_online)
		{
			// refuse authentication if logging disabled
			if (!$GLOBALS["config"]["FEATURE_LOGS_API"])
			{
				throw new SoapFault("Sender", "FEATURE_DISABLED");
			}

			// sanitise input
			$timestamp	= @security_script_input_predefined("int", $timestamp);
			$log_type	= @security_script_input_predefined("any", $log_type);
			$log_contents	= @security_script_input_predefined("any", $log_contents);

			if (!$timestamp || $timestamp == "error" || !$log_type || $log_type == "error" || !$log_contents || $log_contents == "error")
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}

			// write log
			$log 			= New changelog;
			$log->id_server		= $this->auth_server;

			$log->log_post($log_type, $log_contents, $timestamp);

		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of log_write




	/*
		check_update_version

		Return whether or not the connected server is out of date with configuration or not.

		Returns
		0	Name server has the latest configuration
		#	Out of date, timestamp version ID returned
	*/
	function check_update_version()
	{
		log_write("debug", "api_namedmanager", "Executing check_update_version()");


		if ($this->auth_online)
		{
			$obj_server		= New name_server;
			$obj_server->id		= $this->auth_server;

			$obj_server->load_data();

			if (isset($obj_server->data["sync_status_config"]))
			{
				log_write("debug", "api_namedmanager", "Configuration is OUT OF SYNC!");

				return sql_get_singlevalue("SELECT value FROM config WHERE name='SYNC_STATUS_CONFIG' LIMIT 1");
			}
			else
			{
				log_write("debug", "api_namedmanager", "Configuration is all up-to-date");

				return 0;
			}
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of check_update_version



	/*
		set_update_version

		Update the version field for the specific name server

		Fields
		version		Timestamp version of the configuration applied - should be what as originally supplied
				with the check_update_version function.

		Returns
		0		Failure
		1		Success
	*/
	function set_update_version($version)
	{
		log_write("debug", "api_namedmanager", "Executing set_update_version($version)");


		if ($this->auth_online)
		{
			$obj_server		= New name_server;
			$obj_server->id		= $this->auth_server;

			return $obj_server->action_update_config_version($version);
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of set_update_version




	/*
		update_serial

		Update the serial of the requested domain

		Fields
		id_domain	ID of the domain

		Returns
		0		Failure
		#		New serial number
	*/

	function update_serial( $id_domain )
	{
		log_write("debug", "api_namedmanager", "Executing update_serial ( $id_domain )");

		if ($this->auth_admin)
		{
			$obj_domain = New domain;

			
			// validate domain ID input
			$id_domain	= @security_script_input_predefined("int", $id_domain);

			if (!$id_domain || $id_domain == "error")
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}
			

			// verify domain ID
			$obj_domain->id	= $id_domain;

			if (!$obj_domain->verify_id())
			{
				throw new SoapFault("Sender", "INVALID_ID");
			}


			// apply changes

			$obj_domain->load_data();

			if ($serial = $obj_domain->action_update_serial())
			{
				return $serial;
			}
			else
			{
				throw new SoapFault("Sender", "UNKNOWN_ERROR");
			}
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of update_serial



	/*
		update_record

		Updates or creates a new domain record.

		Note that once you have completed calling this function, you should execute update_serial so that the changes
		will actually get rolled out to the nameservers.

		TODO: this function doesn't perfom the level of validation that domains/records-process.php does, we should
		take a look at improving it, however there aren't any security risks, just data corruption risks.

		Fields
		id_domain	ID of the domain
		id_record	ID of the record
		record_name	\
		record_type	|
		record_content	|-- refer to domain_records->action_update and domains/record-process.php for details
		record_ttl	|
		record_prio	/
		
		Returns
		0		Failure
		#		ID of the domain record
	*/

	function update_record( $id_domain, $id_record, $record_name, $record_type, $record_content, $record_ttl, $record_prio )
	{
		log_write("debug", "api_namedmanager", "Executing update_record( $id_domain, $id_record, $record_name, $record_type, $record_content, $record_ttl, $record_prio )");

		if ($this->auth_admin)
		{
			$obj_record = New domain_records;

			
			// validate record inpit
			$data			= array();
			$data["id_domain"]	= @security_script_input_predefined("int", $id_domain);
			$data["id_record"]	= @security_script_input_predefined("int", $id_record);
			$data["record_name"]	= @security_script_input_predefined("any", $record_name);
			$data["record_type"]	= @security_script_input_predefined("any", $record_type);
			$data["record_content"]	= @security_script_input_predefined("any", $record_content);
			$data["record_ttl"]	= @security_script_input_predefined("int", $record_ttl);
			$data["record_prio"]	= @security_script_input_predefined("int", $record_prio);

			foreach ($data as $value)
			{
				if ($value == "error" && $value != 0)
				{
					throw new SoapFault("Sender", "INVALID_INPUT");
				}
			}

			if (!$data["id_domain"] || !$data["record_name"] || !$data["record_type"] || !$data["record_content"])
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}



			// verify domain ID
			$obj_record->id	= $data["id_domain"];

			if (!$obj_record->verify_id())
			{
				throw new SoapFault("Sender", "INVALID_ID");
			}


			// load domain and record data
			$obj_record->load_data();

			if ($data["id_record"])
			{
				$obj_record->id_record = $data["id_record"];

				if (!$obj_record->verify_id_record())
				{
					// ID is invalid
					//
					// blank the ID and create a new record - we do this for apps like
					// phpfreeradius, but it might not be the best approach long-term
					$data["id_record"]	= 0;
				}
				else
				{
					$obj_record->load_data_record();
				}
			}
			else
			{
				// check if there is a record with the same values already - if so, we should
				// take it's ID.
				//
				// TODO: turn this into a proper function
				//
				$sql_obj		= New sql_query;
				$sql_obj->string	= "SELECT id FROM `dns_records` WHERE id_domain='". $data["id_domain"] ."' AND name='". $data["record_name"] ."' LIMIT 1";
				$sql_obj->execute();

				if ($sql_obj->num_rows())
				{
					$sql_obj->fetch_array();

					$obj_record->id_record = $sql_obj->data[0]["id"];
					$obj_record->load_data_record();
				}
			}



			// apply changes
			$obj_record->data_record["name"]		= $data["record_name"];
			$obj_record->data_record["type"]		= $data["record_type"];
			$obj_record->data_record["content"]		= $data["record_content"];
			$obj_record->data_record["ttl"]			= $data["record_ttl"];
			$obj_record->data_record["prio"]		= $data["record_prio"];

			if (!$data["record_ttl"])
			{
				$obj_record->data_record["ttl"]		= $obj_record->data["soa_default_ttl"];
			}


			if ($obj_record->action_update_record())
			{
				return $obj_record->id_record;
			}
			else
			{
				throw new SoapFault("Sender", "UNKNOWN_ERROR");
			}
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of update_record




	/*
		fetch_domains

		Returns an array of all configured domains along with their serial numbers, useful
		for allowing the client application to determine what domains it then wants to fetch records for.

		Returns
		0		Failure
		array		Domain Data
	*/
	function fetch_domains()
	{
		log_write("debug", "api_namedmanager", "Executing fetch_domains()");


		if ($this->auth_online)
		{
			$obj_domain = New domain;
			$obj_domain->load_data_all();

			if ($obj_domain->data)
			{
				foreach ($obj_domain->data as $data)
				{
					// If the domain does not belong to the same group as the name server, skip it.
					if ($this->auth_group)
					{
						if (!in_array($this->auth_group, $data["groups"]))
						{
							continue;
						}
					}

					// add domain to values to be returned
					$return_tmp				= array();

					$return_tmp["id"]			= $data["id"];
					$return_tmp["domain_name"]		= $data["domain_name"];
					$return_tmp["domain_description"]	= $data["domain_description"];
					$return_tmp["soa_hostmaster"]		= $data["soa_hostmaster"];
					$return_tmp["soa_serial"]		= $data["soa_serial"];
					$return_tmp["soa_refresh"]		= $data["soa_refresh"];
					$return_tmp["soa_retry"]		= $data["soa_retry"];
					$return_tmp["soa_expire"]		= $data["soa_expire"];
					$return_tmp["soa_default_ttl"]		= $data["soa_default_ttl"];

					$return_tmp["soa_ns_primary"]		= sql_get_singlevalue("SELECT server_name as value FROM name_servers WHERE server_primary='1' LIMIT 1");

					$return[]	= $return_tmp;
				}

				return $return;
			}

			return 0;
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of fetch_domains



	/*
		fetch_records

		Returns an array of all the domain records for the selected domain

		Fields
		id		ID of the domain to fetch records for

		Returns
		0		Failure
		array		Domain Data
	*/
	function fetch_records( $id_domain )
	{
		log_write("debug", "api_namedmanager", "Executing fetch_records()");

		if ($this->auth_online)
		{
			// verify input
			$id_domain	= @security_script_input_predefined("int", $id_domain);

			if (!$id_domain || $id_domain == "error")
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}


			// verify domain
			$obj_domain		= New domain;
			$obj_domain->id		= $id_domain;

			if (!$obj_domain->verify_id())
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}


			// if querying for a name server, we filter the NS records
			// to only members of that name server group.
			if ($this->auth_group)
			{
				$group_nameservers	= array();

				$obj_ns_sql		= New sql_query;
				$obj_ns_sql->string	= "SELECT server_name FROM name_servers WHERE id_group='". $this->auth_group ."' AND server_record='1'";
				$obj_ns_sql->execute();
				$obj_ns_sql->fetch_array();

				foreach ($obj_ns_sql->data as $data_ns)
				{
					$group_nameservers[] = $data_ns["server_name"];
				}

				unset($obj_ns_sql);
			}


			// fetch domain records
			$obj_domain->load_data_record_all();

			if ($obj_domain->data["records"])
			{
				foreach ($obj_domain->data["records"] as $data_record)
				{
					// filter to NS records that apply for the selected domain group only
					if ($this->auth_group)
					{
						if ($data_record["type"] == "NS")
						{
							if (!in_array($data_record["content"], $group_nameservers))
							{
								// Current NS record isn't in the domain group list. If the nameserver exists in
								// other domain groups, we should exclude it to avoid contaminating across groups.
								//
								// However if the nameserver does *not* exist in NamedManager, then it must be an
								// NS record for an external domain, so we should include it, so that external
								// delegation works correcty.

								$obj_ns_sql		= New sql_query;
								$obj_ns_sql->string	= "SELECT id FROM name_servers WHERE server_name='". $data_record["content"] ."' LIMIT 1";
								$obj_ns_sql->execute();


								if ($obj_ns_sql->num_rows())
								{
									// nameserver exists in other groups, we should exclude this NS record.
									continue;
								}
							}

						}
					}


					// add record to return array
					$return_tmp	= array();

					$return_tmp["id_record"]			= $data_record["id_record"];
					$return_tmp["record_name"]			= $data_record["name"];
					$return_tmp["record_type"]			= $data_record["type"];
					$return_tmp["record_content"]			= $data_record["content"];
					$return_tmp["record_ttl"]			= $data_record["ttl"];
					$return_tmp["record_prio"]			= $data_record["prio"];
							
					$return[]	= $return_tmp;
				}

				return $return;
			}

			return 0;
		}
		else
		{
			throw new SoapFault("Sender", "ACCESS_DENIED");
		}

	} // end of fetch_records

				
} // end of api_namedmanager class



// define server
$server = new SoapServer("namedmanager.wsdl");
$server->setClass("api_namedmanager");
$server->handle();



?>
