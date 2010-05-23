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
		log_debug("domain", "Executing domain() - contructor");


		// we need to initiate a session to the zone database here, rather
		// than needing to do it for every single function in the class

		// TODO: in future, this will handle more database types, eg pgsql

		$this->sql_obj = New sql_query;

		$this->sql_obj->session_init("mysql", $GLOBALS["config"]["ZONE_DB_HOST"], $GLOBALS["config"]["ZONE_DB_NAME"], $GLOBALS["config"]["ZONE_DB_USERNAME"], $GLOBALS["config"]["ZONE_DB_PASSWORD"]);

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
			$this->sql_obj->string	= "SELECT id FROM `domains` WHERE id='". $this->id ."' LIMIT 1";
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
		log_debug("domain", "Executing verify_nas_address()");

		$this->sql_obj->string		= "SELECT id FROM `domains` WHERE name='". $this->data["name"] ."' ";

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

		$this->sql_obj->string	= "SELECT * FROM `domains` WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();

		if ($this->sql_obj->num_rows())
		{
			$this->sql_obj->fetch_array();

			$this->data = $this->sql_obj->data[0];

			return 1;
		}

		// failure
		return 0;

	} // end of load_data




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
		$this->sql_obj->string	= "INSERT INTO `domains` (name, type) VALUES ('NATIVE', '". $this->data["name"] ."')";
		$this->sql_obj->execute();

		$this->id = $this->sql_obj->fetch_insert_id();

		return $this->id;

	} // end of action_create




	/*
		action_update

		Update a domain's details based on the data in $this->data. If no ID is provided,
		it will first call the action_create function.

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
			If no ID supplied, create a new NAS first
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
			TODO: Update domain serial
		/*


		/*
			Update domain details
		*/

		$this->sql_obj->string	= "UPDATE `domains` SET "
						."name='". $this->data["name"] ."', "
						."master='". $this->data["master"] ."', "
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
			
		$this->sql_obj->string	= "DELETE FROM domain WHERE id='". $this->id ."' LIMIT 1";
		$this->sql_obj->execute();


		/*
			Delete domain records
		*/
			
		$this->sql_obj->string	= "DELETE FROM records WHERE domain_id='". $this->id ."'";
		$this->sql_obj->execute();


		/*
			Un-associated any matched log entries
		*/

		$this->sql_obj->string	= "UPDATE logs SET id_nas='0' WHERE id_nas='". $this->id ."'";
		$this->sql_obj->execute();



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
	// TODO: solve the world here
}


?>
