<?php
/*
	inc_ldap.php

	Provides high-level functions for handling and working with LDAP databases.
	
	These functions are typically used by the user_auth class for handling user authentication, however
	the functions can be used for many different LDAP manipulation needs.

	Because of the nature of LDAP, we don't need to abstract basic calls like we do with SQL in order
	to handle different databases, so only use the standard php LDAP functions if needed otherwise.
*/



class ldap_query
{
	var $ldapcon;			// reference to LDAP database session - if unset
					// will default to last LDAP database opened.

	var $srvcfg;			// settings for server to connect to.

	var $record_dn;			// DN for a specific entry

	var $data;			// used to store queried entries
	var $data_num_rows;		// used to store num of entries


	/*
		connect()

		Initates a connection to the LDAP server and binds with the
		configured user.

		TODO: check out SSL support for when accessing hosts via the network

		Returns
		0	Failure
		1	Success
	*/
	function connect()
	{
		log_debug("ldap_query", "Executing connect()");


		// select default configuration if none has been provided
		if (!isset($this->srvcfg))
		{
			// use config files for LDAP server settings.
			if ($GLOBALS["config"]["ldap_host"])
			{
				$this->srvcfg["host"]		= $GLOBALS["config"]["ldap_host"];
				$this->srvcfg["port"]		= $GLOBALS["config"]["ldap_port"];
				$this->srvcfg["base_dn"]	= $GLOBALS["config"]["ldap_dn"];
				$this->srvcfg["user"]		= $GLOBALS["config"]["ldap_manager_user"];
				$this->srvcfg["password"]	= $GLOBALS["config"]["ldap_manager_pwd"];
			}
		}


		// connect to server
		$this->ldapcon = ldap_connect($this->srvcfg["host"], $this->srvcfg["port"]);

		if (!$this->ldapcon)
		{
			log_debug("ldap_query", "Unable to connect to LDAP server ". $this->srvcfg["host"] ." on port ". $this->srvcfg["port"] ."");
			return 0;
		}


		// initate LDAP version 3 connection if possible
		if (ldap_set_option($this->ldapcon, LDAP_OPT_PROTOCOL_VERSION, 3))
		{
			log_debug("ldap_query", "Connecting using LDAP version 3");
		}
		else
		{
			log_debug("ldap_query", "Unable to establish version 3 connection, falling back to version 2. Note that TLS/SSL is not available with version 2.");
		}


		// if SSL/TLS is enabled, we need to use it
		if ($GLOBALS["config"]["ldap_ssl"] == "enable")
		{
			if (ldap_start_tls($this->ldapcon))
			{
				log_debug("ldap_query", "Initated TLS/SSL connection to LDAP server.");
			}
			else
			{
				log_debug("ldap_query", "Unable to initate TLS/SSL connection - check that /etc/openldap/ldap.conf has the CA located for certificate validation.");
				return 0;
			}
		}


		// bind user
		if (ldap_bind($this->ldapcon, $this->srvcfg["user"], $this->srvcfg["password"]))
		{
			log_debug("ldap_query", "Successfully connect to LDAP database on ". $this->srvcfg["host"] ." as ". $this->srvcfg["user"] ."");
			return 1;
		}
		else
		{
			log_debug("ldap_query", "Unable to connect to LDAP database on ". $this->srvcfg["host"] ." as ". $this->srvcfg["user"] ."");
			return -1;
		}


	} // end of connect()



	/*
		disconnect()

		Disconnects from the currently active LDAP server.

		Returns
		0	Failure
		1	Success
	*/

	function disconnect()
	{
		log_debug("ldap_query", "Executing disconnect()");

		// disconnect from server.
		ldap_unbind($this->ldapcon);

		return 1;

	} // end of disconnect()



	/*
		search

		Search the configured base_dn with the provided filter and returns number of matching entries as well
		as storing the data in $this->data array for easy access.

		Fields
		filter		The search filter can be simple or advanced, using boolean operators in the format described in the LDAP documentation
		attributes	(optional) array of what attributes to return

		Returns
		0		Failure
		#		Number of entries returned
	*/
	function search($filter, $attributes = array())
	{
		log_debug("ldap_query", "Executing search($filter, \$attribute_array)");

		$sr_link	= ldap_search($this->ldapcon, $this->srvcfg["base_dn"], $filter, $attributes);
		$this->data	= ldap_get_entries($this->ldapcon, $sr_link);

		if (!count($this->data))
		{
			log_debug("ldap_query", "Unable to match any entries in LDAP database");
			return 0;
		}
		else
		{
			// set the number of rows
			$this->data_num_rows =	$this->data["count"];

			return $this->data_num_rows;
		}

		return 0;

	}



	/*
		record_create

		Creates a new LDAP entry/record from the provided data.

		Fields
		$this->record_dn	DN of the record to create
		$this->data		Array of attributes to write (see php ldap_add for syntax information)

		Returns
		0		Failure
		1		Success
	*/

	function record_create()
	{
		log_write("debug", "ldap_query", "Executing record_create()");

		if (ldap_add($this->ldapcon, $this->record_dn .",". $this->srvcfg["base_dn"], $this->data))
		{
			return 1;
		}
		else
		{
			log_write("warning", "ldap_query", "LDAP error: \"". ldap_error($this->ldapcon) ."\"");
		}


		return 0;

	} // end of record_create()



	/*
		record_update

		Updates an existing LDAP entry with new data.

		NOTE: this function will not add a new entry


		Fields
		$this->record_dn	DN of the record to modify
		$this->data		Array of attributes to write (see php ldap_add for syntax information)

		Returns
		0		Failure
		1		Success
	*/

	function record_update()
	{
		log_write("debug", "ldap_query", "Executing record_update()");

		if (ldap_modify($this->ldapcon, $this->record_dn .",". $this->srvcfg["base_dn"], $this->data))
		{
			return 1;
		}
		else
		{
			log_write("warning", "ldap_query", "LDAP error: \"". ldap_error($this->ldapcon) ."\"");
		}

		return 0;

	} // end of record_update()




	/*
		record_delete

		Deletes an LDAP record

		Fields
		$this->record_dn	DN of the record to delete

		Returns
		0		Failure
		1		Success
	*/

	function record_delete()
	{
		log_write("debug", "ldap_query", "Executing record_delete()");

		if (ldap_delete($this->ldapcon, $this->record_dn .",". $this->srvcfg["base_dn"]))
		{
			return 1;
		}
		else
		{
			log_write("warning", "ldap_query", "LDAP error: \"". ldap_error($this->ldapcon) ."\"");
		}


		return 0;

	} // end of record_delete()


		

} // end of ldap_query





?>
