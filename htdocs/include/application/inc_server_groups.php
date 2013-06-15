<?php
/*
	include/application/inc_server_groups.php

	Functions/classes for managing and querying name server groups.
*/




/*
	CLASS NAME_SERVER_GROUP

	Functions for managing and querying name server groups.
*/
class name_server_group
{
	var $id;		// ID of the server to manipulate (if any)
	var $data;



	/*
		verify_id

		Checks that the provided ID is a valid name server group.

		Results
		0	Failure to find the ID
		1	Success - group
	*/

	function verify_id()
	{
		log_debug("name_server_group", "Executing verify_id()");

		if ($this->id)
		{
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `name_servers_groups` WHERE id='". $this->id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				return 1;
			}
		}

		return 0;

	} // end of verify_id



	/*
		verify_group_name

		Checks that the group name supplied has not already been taken.

		Results
		0	Failure - name in use
		1	Success - name is available
	*/

	function verify_group_name()
	{
		log_debug("name_server_groups", "Executing verify_server_name()");

		$sql_obj			= New sql_query;
		$sql_obj->string		= "SELECT id FROM `name_servers` WHERE server_name='". $this->data["server_name"] ."' ";

		if ($this->id)
			$sql_obj->string	.= " AND id!='". $this->id ."'";

		$sql_obj->string		.= " LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			return 0;
		}
		
		return 1;

	} // end of verify_server_name


	/*
		verify_empty

		Checks whether or not the selected group is empty or whether name servers and/or domains are still assigned to it.

		Results
		0	Not empty
		1	Empty
	*/

	function verify_empty()
	{
		log_debug("name_server_group", "Executing verify_empty()");

		if ($this->id)
		{
			// name servers
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `name_servers` WHERE id_group='". $this->id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				return 0;
			}


			// domains
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM `dns_domains_groups` WHERE id_group='". $this->id ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				return 0;
			}

			return 1;
		}

		return 0;

	} // end of verify_empty


	
	/*
		verify_delete

		Checks whether or not the selected group can be safely deleted. Does not include verify_empty check,
		but will check for things such as it not being the last domain group.

		Results
		0	Not deletable
		1	Deleteable
	*/

	function verify_delete()
	{
		log_debug("name_server_group", "Executing verify_delete()");

		if ($this->id)
		{
			$obj_sql		= New sql_query;
			$obj_sql->string	= "SELECT id FROM name_servers_groups";
			$obj_sql->execute();

			if ($obj_sql->num_rows() == 1)
			{
				// this is the last group, not deletable
				return 0;
			}
			else
			{
				return 1;
			}

		}

		return 0;
	}



	/*
		load_data

		Load the name server group's information into the $this->data array.

		Returns
		0	failure
		1	success
	*/
	function load_data()
	{
		log_debug("name_server_group", "Executing load_data()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT * FROM name_servers_groups WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();

			// set attributes
			$this->data = $sql_obj->data[0];

			return 1;
		}

		// failure
		return 0;

	} // end of load_data




	/*
		action_create

		Create a new name server group based on the data in $this->data

		Results
		0	Failure
		#	Success - return ID
	*/
	function action_create()
	{
		log_debug("name_server_group", "Executing action_create()");

		// create a new server group
		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO `name_servers_groups` (group_name, group_description) VALUES ('". $this->data["group_name"] ."', '". $this->data["group_description"] ."')";
		$sql_obj->execute();

		$this->id = $sql_obj->fetch_insert_id();

		return $this->id;

	} // end of action_create




	/*
		action_update

		Update a name server group's details based on the data in $this->data. If no ID is provided,
		it will first call the action_create function.

		Returns
		0	failure
		#	success - returns the ID
	*/
	function action_update()
	{
		log_debug("name_server_group", "Executing action_update()");


		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			If no ID supplied, create a new name server group first
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
			Update name server group details
		*/

		$sql_obj->string	= "UPDATE `name_servers_groups` SET "
						."group_name='". $this->data["group_name"] ."', "
						."group_description='". $this->data["group_description"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "name_server_group", "An error occured when updating the name server group.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($mode == "update")
			{
				log_write("notification", "name_server_group", "Name server group has been successfully updated.");
			}
			else
			{
				log_write("notification", "name_server_group", "Name server group successfully created.");
			}
			
			return $this->id;
		}

	} // end of action_update


	/*
		action_delete

		Deletes a name server group.

		Call verify_delete first to confirm OK to delete.

		Results
		0	failure
		1	success
	*/
	function action_delete()
	{
		log_debug("name_server_group", "Executing action_delete()");

		/*
			Start Transaction
		*/

		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			Delete Name Server Group

			Note: no need to delete anything from dns_domains_groups, as the name server group
			must be empty before it can be deleted.
		*/
			
		$sql_obj->string	= "DELETE FROM name_servers_groups WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();



		/*
			Commit
		*/
		
		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "name_server_group", "An error occured whilst trying to delete the name server group.");

			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("notification", "name_server_group", "Name server group has been successfully deleted.");

			return 1;
		}
	}


} // end of class:name_server_group



?>
