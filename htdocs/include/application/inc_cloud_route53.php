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
	public $aws_records;

	private $changelog;


	function __construct()
	{
		$this->changelog = New changelog;
	}


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

		$this->changelog->id_server		= $id_name_server;

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
		
		$this->changelog->id_domain = $id_domain;

		// load domain
		$this->obj_domain		= New domain;
		$this->obj_domain->id		= $id_domain;
		$this->obj_domain->format	= 'idn';
		
		if (!$this->obj_domain->load_data())
		{
			return 0;
		}

		// fetch aws hosted zone ID (if it exists yet)
		$this->aws_zone_id = sql_get_singlevalue("SELECT id_mapped as value FROM cloud_zone_map WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1");

	} // end of select_domain


	/*
		fetch_records_local

		Load all the DNS records for the selected domain into the domain object.
		---> $this->obj_domain->data["records"]

		Returns
		0	Failure
		1	Success
	*/

	function fetch_records_local()
	{
		log_write("debug", "cloud_route53", "Executing fetch_records_local()");

		return $this->obj_domain->load_data_record_all();

	} // end of fetch_records_local()



	/*
		fetch_records_remote()

		Fetch all the records from the remote Route53 account for the selected domain.
		---> $this->aws_records

		Returns
		0	Failure
		1	Success
	*/

	function fetch_records_remote()
	{
		log_write("debug", "cloud_route53", "Executing fetch_records_remote()");

		$this->aws_records = array();

		$marker = "";

		while (true)
		{
			// Fetch up to 100 records at a time
			try {
				$change = NULL;
				$change["HostedZoneId"]			= $this->aws_zone_id;
				$change["MaxItems"]			= '100';

				if (!empty($marker))
				{
					$change["StartRecordName"]	= $marker;
				}

				$query	= $this->obj_route53->listResourceRecordSets($change);
			
				if (empty($query["ResourceRecordSets"]))
				{
					log_write("error", "cloud_route53", "Invalid resource fetch response returned from Route53");
					return 0;
				}
				else
				{
					log_write("debug", "cloud_route53", "Results returned... (block $marker)");

					if ($query["IsTruncated"])
					{
					 	// Need to fetch next 100 records
						$marker = $query["NextRecordName"];
					}
					else
					{
						$marker = "";
					}
				}
			}
			catch (Route53Exception $e)
			{
				log_write("error", "process", "A failure occured whilst trying to fetch records from AWS/Route53.");
				log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");

				return 0;
			}


			// Walk the array and convert into a format that matches the internal object used by
			// the domain class.
			foreach ($query["ResourceRecordSets"] as $route53_record)
			{
				// amazon returns some records as a single object with multiple record values
				// we need to turn them into individual entries, hence the loop
				
				for ($i=0; $i < count($route53_record["ResourceRecords"]); $i++)
				{
					$tmp		= array();

					// General Record Details
					$tmp["name"]	= rtrim($route53_record["Name"], '.');
					$tmp["type"]	= $route53_record["Type"];
					$tmp["content"]	= rtrim($route53_record["ResourceRecords"][$i]["Value"], '.');
					$tmp["prio"]	= '0';
					$tmp["ttl"]	= $route53_record["TTL"];

					// Type-Specific processing
					switch ($tmp["type"])
					{
						case "MX":
							if (preg_match("/^([0-9]*)\s(\S*)$/", $tmp["content"], $matches))
							{
								$tmp["prio"]	= $matches[1];
								$tmp["content"]	= $matches[2];
							}
						break;
					}

					// AWS returns @ as \100, so we replace any \100 in the domain names with
					// a proper @. Normally this isn't an issue, unless another tool/user did
					// a bad import into AWS and kept an @ record in place.
					$tmp["name"] = str_replace('\100', "@", $tmp["name"]);

					// AWS records are returned with the full domain path - we need to
					// remove the domain name to make it suitable for NamedManager usage.
					//  note: we do this ONCE incase the record is messed up on AWS side, as we need
					//  to be able to reassemble to delete it, errors and all.
					$tmp["name"] = preg_replace("/.". $this->obj_domain->data["domain_name"] ."/", "", $tmp["name"], 1);

					// Apex records
					if ($tmp["name"] == $this->obj_domain->data["domain_name"])
					{
						$tmp["name"] = "@";
					}

					$this->aws_records[] = $tmp;
				}
			}


			unset($query);

			if (empty($marker))
			{
				break;
			}

		} // end of record fetch loop


		return 1;

	} // end of fetch_records_remote()




	/*
		action_sync_records

		Generate a record changeset, submit to Amazon (dealing with pagination) and track
		it until actioned. The changeset is generated from the value of the local records
		and the route53 records that have been loaded.

		This function is a support function and is used by action_create_domain,
		action_delete_domain and action_update_records.

		Most of the heavy lifting API calls and logic are here.

		Requires
		- Expects local state in $this->obj_domain->data["records"] (target state to achieve)
		- Expects remote state in $this->aws_records (old state in Route53).

		Returns
		0	Uncorrectable Failure
		1	Success - Changes applied successfully.
		2	Success - No changes, nothing applied.
	*/

	function action_sync_records()
	{
		log_write("debug", "cloud_route53", "Executing action_sync_records()");


		/*
			Amazon has an annoying fashion of merging records when the name/type are the
			same, but the contents differ. We need need to apply merging logic here,
			otherwise the change will fail due to us trying to create multiple records
			that are the same.
		
			This works by us running through the ID lists, generating a hash of the
			unique record fields (name, type, etc) with all the IDs that match. We can
			then generate a single record with multiple values accurately that can then
			be used to compare for diffs.

			It's also worth noting that NamedManager and Bind technically allow different
		*/

		log_write("debug", "cloud_route53", "Applying merge of records to match Route53 record design");


		// Current Local Records
		$merge_hash_local	= array();
		$data_records_local	= array();

		for ($i=0; $i < count($this->obj_domain->data["records"]); $i++)
		{

			// Of all the subdomain records, we should remove any which belong to nameservers that exist in
			// other domain groups, we should exclude it to avoid contaminating across groups.
			//
			// However if the nameserver does *not* exist in NamedManager, then it must be an
			// NS record for an external domain, so we should include it, so that external
			// delegation works correctly.
			//
			// We need to do this per-merge, incase a single subdomain record has both ones
			// we need to keep and ones we need to remove.

			if ($this->obj_domain->data["records"][$i]["type"] == "NS")
			{
				$obj_ns_sql		= New sql_query;
				$obj_ns_sql->string	= "SELECT id FROM name_servers WHERE server_name='". $this->obj_domain->data["records"][$i]["content"] ."' LIMIT 1";
				$obj_ns_sql->execute();

				if ($obj_ns_sql->num_rows())
				{
					// nameserver exists in other groups, we should exclude this NS record.
					continue;
				}
			}

			$merge_hash_local[ $this->obj_domain->data["records"][$i]["type"] ."_". $this->obj_domain->data["records"][$i]["name"] ][] = $i;
		}

		foreach ($merge_hash_local as $ids)
		{
			// For every hash entry, all the record details are the same EXCEPT
			// for the contents. Take the values from the first matched record, then
			// grab each content value and assume an array record.

			$tmp = array();
			$id  = $ids[0];


			// Exclude any NS records for the domain itself (but still include subdomains)
			// AWS manages NS records for us, so we don't want to mess with them.
			if ($this->obj_domain->data["records"][$id]["type"] == "NS" &&
				$this->obj_domain->data["records"][$id]["name"] == $this->obj_domain->data["domain_name"])
			{
				continue;
			}

			if ($this->obj_domain->data["records"][$id]["type"] == "NS" &&
				$this->obj_domain->data["records"][$id]["name"] == "@")
			{
				continue;
			}


			$tmp["Name"]		= $this->obj_domain->data["records"][$id]["name"] .".";
			$tmp["Type"]		= $this->obj_domain->data["records"][$id]["type"];
			$tmp["TTL"]		= $this->obj_domain->data["records"][$id]["ttl"];
			$tmp["ResourceRecords"]	= array();

			foreach ($ids as $id)
			{
				$tmp2 = array();

				switch ($tmp["Type"])
				{
					case "MX":
						// MX records are special - need to re-merge priority and content together
						$tmp2["Value"]	= $this->obj_domain->data["records"][$id]["prio"] ." ". $this->obj_domain->data["records"][$id]["content"];

						if (preg_match("/\./", $tmp2["Value"]))
						{
							// already a FQDN
							$tmp2["Value"] .= ".";
						}
						else
						{
							// add local domain to make CNAME FQDN
							$tmp2["Value"] .=  ".". $this->obj_domain->data["domain_name"] .".";
						}

					break;
					
					case "NS":
					case "SRV":
					case "CNAME":
						// ensure the value is a FQDN.
						$tmp2["Value"] = $this->obj_domain->data["records"][$id]["content"];
					
						if (preg_match("/\./", $tmp2["Value"]))
						{
							// already a FQDN
							$tmp2["Value"] .= ".";
						}
						else
						{
							// add local domain to make CNAME FQDN
							$tmp2["Value"] .=  ".". $this->obj_domain->data["domain_name"] .".";
						}
					break;

					case "PTR":
						// These record types will always to point to FQDNs, so need the trailing .
						$tmp2["Value"] = $this->obj_domain->data["records"][$id]["content"] .".";
					break;

					case "A":
					case "AAAA":
					case "TXT":
					case "SPF":
					default:
						// Point to IPs or quoted strings. Don't touch.
						if ($this->obj_domain->data["records"][$id]["content"] != "")
						{
							$tmp2["Value"] = $this->obj_domain->data["records"][$id]["content"];
						}
						else
						{
							$tmp2["Value"] = $this->obj_domain->data["records"][$id][3];
						}
					break;
				}
				
				$tmp["ResourceRecords"][] = $tmp2;
			}


			// Adjust the record name to FQDN - AWS won't accept anything else
			if (strpos($tmp["Name"], $this->obj_domain->data["domain_name"]) === FALSE)
			{
				if ($tmp["Name"] == "@.")
				{
					$tmp["Name"] =  $this->obj_domain->data["domain_name"] .".";
				}
				else
				{
					$tmp["Name"] .=  $this->obj_domain->data["domain_name"] .".";
				}
			}

			$data_records_local[] = $tmp;
		}

		unset($merge_hash_local);


		// Existing Route53 Records
		$soa_record		= array(); // we need to fetch and adjust the SOA record.
		$merge_hash_route53	= array();
		$data_records_route53	= array();

		for ($i=0; $i < count($this->aws_records); $i++)
		{
			$merge_hash_route53[ $this->aws_records[$i]["type"] ."_". $this->aws_records[$i]["name"] ][] = $i;	
		}

		foreach ($merge_hash_route53 as $ids)
		{
			// For every hash entry, all the record details are the same EXCEPT
			// for the contents. Take the values from the first matched record, then
			// grab each content value and assume an array record.

			$tmp = array();
			$id  = $ids[0];


			// Exclude any NS records for the domain itself (but still include subdomains)
			// AWS manages NS records for us, so we don't want to mess with them.
			if ($this->aws_records[$id]["type"] == "NS" &&
				$this->aws_records[$id]["name"] == $this->obj_domain->data["domain_name"])
			{
				continue;
			}

			if ($this->aws_records[$id]["type"] == "NS" &&
				$this->aws_records[$id]["name"] == "@")
			{
				continue;
			}


			$tmp["Name"]		= $this->aws_records[$id]["name"] .".";
			$tmp["Type"]		= $this->aws_records[$id]["type"];
			$tmp["TTL"]		= $this->aws_records[$id]["ttl"];
			$tmp["ResourceRecords"]	= array();

			foreach ($ids as $id)
			{
				$tmp2 = array();

				switch ($tmp["Type"])
				{
					case "MX":
						// MX records are special - need to re-merge priority and content together
						$tmp2["Value"] = $this->aws_records[$id]["prio"] ." ". $this->aws_records[$id]["content"] .".";
					break;

					case "NS":
					case "CNAME":
					case "SRV":
					case "PTR":
						// These record types need to point to FQDNs, so need the trailing .
						$tmp2["Value"] = $this->aws_records[$id]["content"] .".";
					break;

					case "A":
					case "AAAA":
					case "TXT":
					case "SPF":
					default:
						// Point to IPs or quoted strings. Don't touch.
						$tmp2["Value"] = $this->aws_records[$id]["content"];
					break;
				}
				
				$tmp["ResourceRecords"][] = $tmp2;
			}


			// Adjust the record name to FQDN - AWS won't accept anything else
			if (strpos($tmp["Name"], $this->obj_domain->data["domain_name"]))
			{
				// A record from AWS already includes the domain name... but we already
				// strip this at import, which means the AWS record is messed up and includes
				// multple domain records (eg something.example.com.example.com) probably
				// from a buggy import.
				//
				// To ensure we can delete and correct this bad record, we append our domain
				// that we originally stripped.

				$tmp["Name"] .=  $this->obj_domain->data["domain_name"] .".";
			}
			else
			{
				if ($tmp["Name"] == "@.")
				{
					$tmp["Name"] =  $this->obj_domain->data["domain_name"] .".";
				}
				else
				{
					$tmp["Name"] .=  $this->obj_domain->data["domain_name"] .".";
				}
			}


			// Capture a copy of the SOA record so we can update the SOA serial.
			if ($this->aws_records[$id]["type"] == "SOA")
			{
				$soa_record = $tmp;

				// skip adding this to the change request, we do that manually.
				continue;
			}


			$data_records_route53[] = $tmp;
		}

		unset($merge_hash_route53);



		/*
			We now have our two array structures in a format that will suite Route53. We need
			to loop through and compare them and generate an array of additions/deletions.
			(note that changes are both a deletion and addition).
		*/
		
		log_write("debug", "cloud_route53", "Generating Change Batch diff data)");


		$ids_local_created  	= array();	// array of Local IDs to create in Route53
		$ids_route53_deleted	= array();	// array of Route53 IDs to delete from Route53 (has changed)
		$ids_route53_nochange	= array();	// records with no change.

		for ($il=0; $il < count($data_records_local); $il++)
		{
			$match = 0;

			// check if the local record exists in Route53
			for ($ir=0; $ir < count($data_records_route53); $ir++)
			{
				if (($data_records_route53[$ir]["Type"] == $data_records_local[$il]["Type"])
					&& ($data_records_route53[$ir]["Name"] == $data_records_local[$il]["Name"])
					&& ($data_records_route53[$ir]["ResourceRecords"] == $data_records_local[$il]["ResourceRecords"]))
				{
					$match = 1;

					$diff = array_diff($data_records_route53[$ir], $data_records_local[$il]);

					if (!empty($diff))
					{
						// the record exists, but has changed some of it's attributes
						$ids_route53_deleted[]	= $ir; // delete existing record.
						$ids_local_created[] 	= $il; // create a new proper record.
					}
					else
					{
						// record exists and is unchanged - nothing needs to be done for this one.
						$ids_route53_nochange[] = $ir;
					}
				}
			}

			if (!$match)
			{
				// Doesn't exist in Route53 - mark it as a new addition.
				$ids_local_created[] = $il;
			}
		}


		// Each unmatched ID is a record that doesn't exist in Route53, we need to
		// add each of these records to the deletion list.
		
		for ($i=0; $i < count($data_records_route53); $i++)
		{
			if (!in_array($i, $ids_route53_deleted)	
				&& !in_array($i, $ids_route53_nochange))
			{
				// Record exists in Route53, but isn't in the unchanged list or the
				// deleted list when compared with local NamedManager. This record must
				// have been deleted, we now need to purge it.

				$ids_route53_deleted[] = $i;
			}
		}

	
		/*
			Verify that there has actually been a change!
		*/
		if (empty($ids_route53_deleted) && empty($ids_local_created))
		{
			log_write("debug", "cloud_route53", "No changes between Route53 and NamedManager");
			return 2;
		}
		else
		{
			log_write("debug", "cloud_route53", "There are differences between Route53 and NamedManager. Applying...");
		}



		/*
			Now we have the lists of IDs that have been added, deleted or unchanged, we can generate our diff.
		*/

		$data_change_batch = array();


		// Firstly, let's delete the existing SOA and add a new SOA. We need to do
		// this first to ensure the delete and creates don't get split across requests.

		$tmp = array();
		$tmp["Action"]				= "DELETE";
		$tmp["ResourceRecordSet"]		= $soa_record;
		$data_change_batch[]			= $tmp;

		$tmp = array();
		$tmp["Action"]				= "CREATE";
		$tmp["ResourceRecordSet"]		= $soa_record;
	
		$soa_record_tmp				= explode(' ', $soa_record["ResourceRecords"][0]["Value"]);
		$soa_record_tmp[2]			= $this->obj_domain->data["soa_serial"];
	
		$tmp["ResourceRecordSet"]["ResourceRecords"][0]["Value"] = implode(' ', $soa_record_tmp);
		
		$data_change_batch[]			= $tmp;


		// Add all the other records to the change batch job. We do DELETE first,
		// since a CREATE will always fail if the old record hasn't been DELETEd
		// first.
		foreach ($ids_route53_deleted as $id)
		{
			$tmp = array();
			$tmp["Action"]			= "DELETE";
			$tmp["ResourceRecordSet"]	= $data_records_route53[ $id ];
			$data_change_batch[]		= $tmp;
		}

		foreach ($ids_local_created as $id)
		{
			$tmp = array();
			$tmp["Action"]			= "CREATE";
			$tmp["ResourceRecordSet"]	= $data_records_local[ $id ];
			$data_change_batch[]		= $tmp;
		}

		unset($data_records_local);
		unset($data_records_route53);


		

		/*
			Submit Change Batch request to Amazon
		*/

		log_write("debug", "cloud_route53", "Submitting batch change request to Amazon AWS.");


		// We need to handle Amazon limits - slice up the change into no more than 100
		// requests per call, with DELETES before CREATES

		$max_records	= count(array_keys($data_change_batch));
		$chunk		= ceil($max_records / 100);
		$count		= 1;

		foreach (array_chunk($data_change_batch, 100) as $data_change_batch2)
		{
			log_write("debug", "cloud_route53", "Uploading batch change request number $count/$chunk");

			try {
				$change = NULL;
				$change["HostedZoneId"]			= $this->aws_zone_id;
				$change["ChangeBatch"]["Changes"]	= $data_change_batch2;

				// If debugging, uncommenting the following will dump out the entire change
				// object before it's sent to AWS.
//				print_r($change);
//				die("debug");

				$query	= $this->obj_route53->changeResourceRecordSets($change);
			
				if (empty($query["ChangeInfo"]))
				{
					log_write("error", "cloud_route53", "Invalid change response returned from Route53");
					$this->changelog->log_post('server', "An error occured updating domain \"". $this->obj_domain->data["domain_name"] ."\" in Route53");
					return 0;
				}
				else
				{
					log_write("debug", "cloud_route53", "Batch change submitted successfully.");

				}
			}
			catch (Route53Exception $e)
			{
				log_write("error", "process", "A failure occured whilst trying to submit a batch change from AWS/Route53.");
				log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");
				$this->changelog->log_post('server', "An error occured updating domain \"". $this->obj_domain->data["domain_name"] ."\" in Route53");

				return 0;
			}

		} // end of foreach loop for uploads



		return 1;

	} // end of action_sync_records



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
				$this->changelog->log_post('server', "A failure occured whilst attempting to create domain \"". $change["Name"] ."\" in Route53");

				return 0;
			}
			else
			{
				log_write("debug", "cloud_route53", "Route53 creation completed, ID is ". $query["HostedZone"]["Id"]);
				$this->changelog->log_post('server', "Created domain \"". $change["Name"] ."\" in Route53 (ID ".$query["HostedZone"]["Id"] .")");
						
				$obj_sql		= New sql_query;
				$obj_sql->string	= "INSERT INTO cloud_zone_map (id_name_server, id_domain, id_mapped, soa_serial, delegated_ns) VALUES ('". $this->obj_name_server->id ."', '". $this->obj_domain->id ."', '". $query["HostedZone"]["Id"] ."', '0', '". serialize($query["DelegationSet"]["NameServers"]) ."')";
				$obj_sql->execute();
			}
		}
		catch (Route53Exception $e)
		{
			log_write("error", "process", "A failure occured whilst trying to create a new hosted zone.");
			log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");
			$this->changelog->log_post('server', "A failure occured whilst attempting to create domain \"". $change["Name"] ."\" in Route53");

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


		// Fetch all the records for this domain from AWS
		if (!$this->fetch_records_remote())
		{
			return 0;
		}

		// Fetch all the records for this domain from NamdManager
		if (!$this->fetch_records_local())
		{
			return 0;
		}

		// Generate a batch job for AWS with the differences to be applied
		if (!$this->action_sync_records())
		{
			return 0;
		}

		// Update SOA for cloud zone map table
		$obj_sql 	 = New sql_query;
		$obj_sql->string = "UPDATE cloud_zone_map SET soa_serial='$current_soa' WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1";
		$obj_sql->execute();

		log_write("debug", "inc_cloud_route53", "Successfully updated to domain version $current_soa");
		$this->changelog->log_post('server', "Successfully updated domain \"". $this->obj_domain->data["domain_name"] ."\" in Route53");

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

			We do this by setting the local object to empty records, then do a sync
			record action against Route53. This generates a batch change to delete
			all the records.
		*/

		$this->obj_domain->data["records"] = array();

		if (!$this->fetch_records_remote())
		{
			return 0;
		}

		if (!$this->action_sync_records())
		{
			return 0;
		}
	

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
				$this->changelog->log_post('server', "Domain \"". $this->obj_domain->data["domain_name"] ."\" deleted from Route53");
						
				$obj_sql		= New sql_query;
				$obj_sql->string	= "DELETE FROM cloud_zone_map WHERE id_name_server='". $this->obj_name_server->id ."' AND id_domain='". $this->obj_domain->id ."' LIMIT 1";
				$obj_sql->execute();
			}
		}
		catch (Route53Exception $e)
		{
			log_write("error", "process", "A failure occured whilst trying to delete hosted zone.");
			log_write("error", "process", "Failure returned: ". $e->getExceptionCode() ."");
			
			$this->changelog->log_post('server', "An error occured attempting to delete domain \"". $this->obj_domain->data["domain_name"] ."\" from Route53");

			return 0;
		}

		// success
		return 1;
	
	} // end of action_delete_domain


} // end of class cloud_route53


?>
