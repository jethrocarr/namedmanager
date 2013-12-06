<?php
/*
	inc_cloud_route53.php

	Include abstracted functions for managing Route53 hosts DNS zones
	easily with NamedManager functions.
*/



/*
	We require the (sadly rather large and heavy weight)
	AWS SDK to provide functions for Route53 interaction.
*/

require (dirname(__FILE__) ."/../vendor/aws-sdk/aws-autoloader.php");

use Aws\Route53\Route53Client;
use Aws\Route53\Exception\Route53Exception;


class cloud_route53
{
	public $obj_route53;
	public $obj_domain;
	public $obj_name_server;

	public $aws_zone_id;


	/*
		select_account

		Initalize the account by providing the ID for the Route53
		nameserver/account to use.

		Requires
		id_name_server		ID of name server

		Returns
		1			No error validation in this function.
	*/
	function select_account($id_name_server)
	{
		log_write("debug", "cloud_route53", "Executing select_account(". $id_name_server .")");

		$this->obj_name_server			= New name_server;
		$this->obj_name_server->id		= $id_name_server;
		$this->obj_name_server->load_data();

		$key = unserialize($this->obj_name_server->data["api_auth_key"]);

		$this->obj_route53 = Route53Client::factory(array(
			'key'    => $key["route53_access_key"],
			'secret' => $key["route53_secret_key"]
		));

		return 1;

	} // end of select_account



	/*
		select_domain

		Select the domain to operate on, and load it's data for futher operations.

		Requires
		id_domain	ID of the domain to process

		Returns
		0		Failure - invalid domain ID
		1		Success
	*/

	function select_domain($id_domain)
	{
		log_write("debug", "cloud_route53", "Executing select_domain($id_domain)");

		// load domain
		$this->obj_domain	= New domain;
		$this->obj_domain->id	= $id_domain;
		
		if (!$this->obj_domain->load_data())
		{
			return 0;
		}

		// fetch aws hosted zone ID (if it exists yet)
		$this->aws_zone_id = sql_get_singlevalue("SELECT id_mapped as value FROM cloud_zone_map WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1");

	} // end of select_domain



	/*
		action_create_domain

		Creates a new domain entry in Route53 and adds the domain into the cloud
		mapping table.

		Returns
		0		Failure
		1		Success
	*/
	function action_create_domain()
	{
		log_write("debug", "cloud_route53", "Executing action_create_domain()");

		// create the Route53 domain
		try {
			$change = NULL;
			$change["CallerReference"]		= mktime() ."_create_". $this->obj_domain->data["domain_name"];
			$change["Name"]				= $this->obj_domain->data["domain_name"];
			$change["HostedZoneConfig"]["Comment"]	= $this->obj_domain->data["domain_description"];

			$query	= $this->obj_route53->createHostedZone($change);

			if (!$query["HostedZone"]["Id"] || !$query["DelegationSet"]["NameServers"])
			{
				log_write("error", "cloud_route53", "Invalid creation response returned from Route53");
				return 0;
			}
			else
			{
				log_write("debug", "cloud_route53", "Route53 creation completed, ID is ". $query["HostedZone"]["Id"]);
						
				$obj_sql		= New sql_query;
				$obj_sql->string	= "INSERT INTO cloud_zone_map (id_name_server, id_domain, id_mapped, soa_serial, delegated_ns) VALUES ('". $this->obj_name_server->id ."', '". $this->obj_domain->id ."', '". $query["HostedZone"]["Id"] ."', '0', '". serialize($query["DelegationSet"]["NameServers"]) ."')";
				$obj_sql->execute();
			}
		}
		catch (Route53Exception $e)
		{
			log_write("error", "process", "A failure occured whilst trying to create a new hosted zone.");
			log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");

			return 0;
		}

		// save zone ID in this active object for any futher calls this session
		$this->aws_zone_id = $query["HostedZone"]["Id"];

		// add the Route53 name servers to the domain and update the serial number. This will force
		// any other non-Route53 name servers that use this domain to be aware of the new NS details

		$this->obj_domain->action_update_ns();
		$this->obj_domain->action_update_serial();

		// success
		return 1;

	} // end of action_create_domain



	/*
		action_update_records

		Update the records of the select domain by downloading the current record
		set from AWS and comparing with NamedManager.
	
		Returns
		0		Failure
		1		Success
	*/

	function action_update_records()
	{
		log_write("debug", "inc_cloud_route53", "Executing action_update_records()");
	
		// fetch the SOA before we get the records - that way if the records get updated between now and us querying them,
		// we won't have a race condition and miss them, as our SOA will be behind, and a subsequent run will
		// cause the domain to get updated. This is easier than dealing with table locks.

		$current_soa = sql_get_singlevalue("SELECT soa_serial as value FROM dns_domains WHERE id='". $this->obj_domain->id ."' LIMIT 1");


		// TODO: need this code

		// Fetch all the records for this domain from AWS

		// Fetch all the records for this domain from NamdManager

		// Generate a batch job for AWS with the differences to be applied

			// remember to include the SOA record itself.


		// update SOA for cloud zone map table
		$obj_sql 	 = New sql_query;
		$obj_sql->string = "UPDATE cloud_zone_map SET soa_serial='$current_soa' WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1";
		$obj_sql->execute();

		log_write("debug", "inc_cloud_route53", "Successfully updated to domain version $current_soa");

	} // end of action_update_records



	/*
		action_delete_domain

		Delete the domain from Route53

		Returns
		0		Failure
		1 		Success
	*/
	function action_delete_domain()
	{
		log_write("debug", "inc_cloud_route53", "Executing action_delete_domain()");


		/*
			Delete Record Sets

			Amazon won't delete a domain if there are any records other than the base
			SOA and NS records assigned to it. We need to submit a delete request for
			all the records first.
		*/

		// TODO: need this code


		/*
			Delete Domain

			Now we can delete the domain itself.
		*/

		try {
			$change		= NULL;
			$change["Id"]	= $this->aws_zone_id;

			$query	= $this->obj_route53->deleteHostedZone($change);

			if (!$query["ChangeInfo"]["Id"])
			{
				log_write("error", "cloud_route53", "Invalid delete response returned from Route53");
				return 0;
			}
			else
			{
				log_write("debug", "cloud_route53", "Route53 delete request submitted");
						
				$obj_sql		= New sql_query;
				$obj_sql->string	= "DELETE FROM cloud_zone_map WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1";
				$obj_sql->execute();
			}
		}
		catch (Route53Exception $e)
		{
			log_write("error", "process", "A failure occured whilst trying to delete hosted zone.");
			log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");

			return 0;
		}

		// success
		return 1;
	
	} // end of action_delete_domain


} // end of class cloud_route53


?>
