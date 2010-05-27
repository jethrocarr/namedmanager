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


	/*
		constructor
	*/
	function api_namedmanager()
	{
		$this->auth_server	= $_SESSION["auth_server"];
		$this->auth_online	= $_SESSION["auth_online"];
	}



	/*
		authenticate

		Authenticates a SOAP client call using the SOAP_API_KEY configuration option to enable/prevent access

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


		// verify input
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM name_servers WHERE server_name='$server_name' AND api_auth_key='$api_auth_key' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			$this->auth_online		= 1;
			$this->auth_server		= $sql_obj->data[0]["id"];

			$_SESSION["auth_online"]	= $this->auth_online;
			$_SESSION["auth_server"]	= $this->auth_server;

			return $this->auth_server;
		}
		else
		{
			throw new SoapFault("Sender", "INVALID_ID");
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
			// sanitise input
			$timestamp	= @security_script_input_predefined("int", $timestamp);
			$log_type	= @security_script_input_predefined("any", $log_type);
			$log_contents	= @security_script_input_predefined("any", $log_contents);

			if (!$timestamp || $timestamp == "error" || !$log_type || $log_type == "error" || !$log_contents || $log_contents == "error")
			{
				throw new SoapFault("Sender", "INVALID_INPUT");
			}

			// write log
			$obj_log 		= New radius_logs;
			$obj_log->id_server	= $this->auth_server;

			$obj_log->log_push($timestamp, $log_type, $log_contents);
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

			if ($obj_server->data["sync_status_config"])
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


			// fetch domain records
			$obj_domain->load_data_record_all();

			if ($obj_domain->data["records"])
			{
				foreach ($obj_domain->data["records"] as $data_record)
				{
					$return_tmp			= array();

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

