<?php
/*
	include/application/inc_soap_api.php

	Provides functions for talking with the NamedManger SOAP API
*/


class soap_api
{
	var $client;



	/*
		authenticate

		Connects to the NamedManager API and uses the API key & server name to authenticate.

		Returns
		0		Failure
		1		Success
	*/
	function authenticate()
	{
		log_write("debug", "soap_api", "Executing authenticate()");


		/*
			Initiate connection & authenticate with NamedManager

		*/
		$this->client = new SoapClient($GLOBALS["config"]["api_url"] ."/api/namedmanager.wsdl");
		$this->client->__setLocation($GLOBALS["config"]["api_url"] ."/api/namedmanager.php");


		// login & get PHP session ID
		try
		{
			log_write("debug", "soap_api", "Authenticating with API as DNS server ". $GLOBALS["config"]["api_server_name"] ."...");

			if ($this->client->authenticate($GLOBALS["config"]["api_server_name"], $GLOBALS["config"]["api_auth_key"]))
			{
				log_write("debug", "soap_api", "Authentication successful");

				return 1;
			}

		}
		catch (SoapFault $exception)
		{
			if ($exception->getMessage() == "ACCESS_DENIED")
			{
				log_write("error", "soap_api", "Unable to authenticate with NamedManager API - check that auth API key and server name are valid");

				return 0;
			}
			else
			{	
				log_write("error", "soap_api", "Unknown failure whilst attempting to authenticate with the API - ". $exception->getMessage() ."");

				return 0;
			}
		}
	}


	/*
		log_push

		Send a log message to the server

		Fields
		timestamp		UNIX timestamp
		log_contents		Log contents

		Results
		0			Failure
		1			Success
	*/
	function log_push($timestamp, $log_contents)
	{
		log_write("debug", "script", "Executing log_push(timestamp, log_contents)");

		// safety check - make sure the log message isn't for a DNS lookup for the API server, otherwise
		// this will form a painful loop that DOSes the server.

		if (!isset($GLOBALS["config"]["api_host"]))
		{
			// need to set what the API hostname is for DNS lookups
			preg_match("/^http[s]?:\/\/(\S*)[:0-9]*\//", $GLOBALS["config"]["api_url"], $matches);
			$GLOBALS["config"]["api_host"] = $matches[1];
		}

		if (strpos($log_contents, $GLOBALS["config"]["api_host"]))
		{
			// skip this log message as it's about the host we're querying
			log_write("debug", "script", "Skipping log message as it self-references the API host, anti-loop prevention.");
			return 1;
		}


		// post log message - we then check the exceptions returned and if there is an error, attempt to re-establish a connection
		// as sometimes a network or server disruption can cause issues.

		try
		{
			$this->client->log_write($timestamp, "server", $log_contents);
		}
                catch (SoapFault $exception)
                {
                        if ($exception->getMessage() == "ACCESS_DENIED")
                        {
                                // no longer able to access API - perhaps the session has timed out?
                                if ($this->authenticate())
                                {
                                        $this->client->log_write($timestamp, "server", $log_contents);
                                }
                                else
                                {
					// this situation would only occur if the session was logged out and authentication then refused, a network
					// disruption will not lead to this occuring, since an ACCESS_DENIED response is required.

                                        log_write("error", "script", "Unable to re-establish connection with NamedManager");
                                        die("Fatal Error");
                                }
                        }
                        elseif ($exception->getMessage() == "Could not connect to host")
                        {
                                // this can happen when restarting the server or apache.... sleep for 10 seconds and retry.
                                log_write("error", "script", "Unable to connect to the host, probably apache/NamedManager outage or server unreachable");

                                sleep(10);

                        }
			elseif ($exception->getMessage() == "Error Fetching http headers")
			{
				// unexpected HTTP header issue
				log_write("error", "script", "An unexpected issue occured when fetching HTTP headers. Possible network or service disruption.");

				sleep (10);
			}
                        else
                        {
				// for any other errors, report fault and retry later - better to keep retrying and only quit if there's an actual
				// known un-fixable problem.

                                log_write("error", "script", "Unknown failure whilst attempting to push log messages - ". $exception->getMessage() ."");

				sleep(10);
                        }

		}

	}



	/*
		check_update_version

		Check if the configuration for the authenticated server is uptodate or not, allows
		the application to quickly tell if there is a need to regenerate configuration or not.

		Returns
		0		Configuration is up to date
		#		Configuration is out of date - returns version number of config
	*/
	function check_update_version()
	{
		log_write("debug", "soap_api", "Executing check_update_version()");

		if (!$config_version = $this->client->check_update_version())
		{
			// all good

			log_write("debug", "soap_api", "System configuration is uptodate, no changes nessacary");

			return 0;
		}
		else
		{
			// out-of-date

			log_write("debug", "soap_api", "Configuration is out of date need to update to $config_version");

			return $config_version;
		}

	} // end of check_update_version


	
	/*
		set_update_version

		Set the update version with the one that has just been applied.
	
		Fields
		config_version	(int) configuration version

		Returns
		0		Unexpected error occured
		1		Successful update
	*/
	
	function set_update_version( $config_version )
	{
		log_write("debug", "soap_api", "Executing set_update_version( $config_version )");


		try
		{
			if (!$this->client->set_update_version($config_version))
			{
				log_write("debug", "soap_api", "Unable to confirm successful application of configuration version $config_version");
				die("Fatal Error");
			}
		}
		catch (SoapFault $exception)
		{
			log_write("error", "soap_api", "An unexpected error occured ". $exception->getMessage() ."");
			die("Fatal Error");
		}

	} // end of set_update_version




	/*
		fetch_domains

		Fetches the domain details including name and SOA serial. Provides enough information to
		enable the application to decide if we should query all the records for generating the
		configuration file

		Returns
		array		Array of all the domains
	*/

	function fetch_domains()
	{
		log_write("debug", "soap_api", "Executing fetch_domains()");

		try
		{
			$domains = $this->client->fetch_domains();
		}
		catch (SoapFault $exception)
		{
			if ($exception->getMessage() == "ACCESS_DENIED")
			{
				log_write("error", "Access failure attempting to fetch domains");
				return 0;
			}
			else
			{	
				log_write("error", "Unexpected error \"". $exception->getMessage() ."\" whilst attempting to fetch domains");
				return 0;
			}
		}

		return $domains;

	} // end of fetch_domains




	/*
		fetch_records

		Fetches all the domain records including SOA, NS, MX, A, PTR and more.


		Fields
		id_domain	ID of domain

		Returns
		array		Array of all the records
	*/

	function fetch_records( $id_domain )
	{
		log_write("debug", "soap_api", "Executing fetch_records( $id_domain )");

		try
		{
			$records = $this->client->fetch_records($id_domain);
		}
		catch (SoapFault $exception)
		{
			if ($exception->getMessage() == "ACCESS_DENIED")
			{
				log_write("error", "soap_api", "Access failure attempting to fetch domain records");
				return 0;
			}
			elseif ($exception->getMessage() == "NO_RECORDS")
			{
				log_write("warning", "soap_api", "There are no records for the requested domain.");
				return 0;
			}
			else
			{	
				log_write("error", "soap_api", "Unexpected error \"". $exception->getMessage() ."\" whilst attempting to fetch domain records");
				return 0;
			}
		}

		return $records;

	} // end of fetch_records



} // end of soap_api


?>
