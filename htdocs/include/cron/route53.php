#!/usr/bin/php
<?php
/*
	include/cron/route53.php

	Route53 integration works by recording the SOA of the Route53 zone, so upon
	cronjob executation we can determine which domains are out of date.

	For each domain, we fetch the record set and compare with NamedManager and
	then prepare a diff change to bring the Route53 entry inline with NamedManager.

	This is annoying in that it requires a more intensive API call to fetch all the
	records, but it has the advantage that if Route53 ever drifts out of sync, we
	bring it inline cleanly, whereas tracking the batch changes and trying to apply
	the diff has issues should one side ever drift....

	The other disadvantage of this approach is that the updates to Route53 are not real
	time - but we have the advantage of the sync being reliable and tolerant of network
	failures, slow performance or API failure.
*/


	
// load framework
require("../config.php");
require("../amberphplib/main.php");
require("../application/main.php");

use Aws\Route53\Route53Client;
use Aws\Route53\Exception\Route53Exception;


log_write("info", "cron_route53", "Started Route53 Cronjob");


/*
	Assemble array of domains to update in Route53
*/

$obj_sql_domains		= New sql_query;
$obj_sql_domains->string	= "SELECT name_servers.id as server_id, dns_domains.id as domain_id, dns_domains.domain_name as domain_name
					FROM name_servers
					LEFT JOIN dns_domains_groups ON dns_domains_groups.id_group = name_servers.id_group
					LEFT JOIN dns_domains ON dns_domains.id = dns_domains_groups.id_domain
					WHERE name_servers.server_type='route53' AND dns_domains.id != '0'";
$obj_sql_domains->execute();
$obj_sql_domains->num_rows();
$obj_sql_domains->fetch_array();


/*
	Garbage collect - any zones that should no longer be in Route53?
*/

if ($obj_sql_domains->data_num_rows)
{
	$valid_ids = array();

	foreach ($obj_sql_domains->data as $data_list)
	{
		$valid_ids[] = $data_list["domain_id"];
	}

	$obj_sql_gc 		= New sql_query;
	$obj_sql_gc->string	= "SELECT id_name_server, id_domain, domain_name FROM cloud_zone_map LEFT JOIN dns_domains ON dns_domains.id = cloud_zone_map.id_domain";
	$obj_sql_gc->execute();

	if ($obj_sql_gc->num_rows())
	{
		$obj_sql_gc->fetch_array();

		foreach ($obj_sql_gc->data as $data_gc)
		{
			if (!in_array($data_gc["id_domain"], $valid_ids))
			{
				log_write("info", "cron_route53", "Removing old domain ". $data_gc["domain_name"] ." from Route53...");

				$obj_route53 = New cloud_route53;

				$obj_route53->select_account($data_gc["id_name_server"]);
				$obj_route53->select_domain($data_gc["id_domain"]);

				$obj_route53->action_delete_domain();

				unset($obj_route53);

				log_write("info", "cron_route53", "Domain removal complete");
			}
		}
	}
	
	unset($obj_sql_gc);

}
else
{
	// There are no current Route53 domains - purge anything in the mapping table.
	$obj_sql_gc 		= New sql_query;
	$obj_sql_gc->string	= "SELECT id_name_server, id_domain, domain_name FROM cloud_zone_map LEFT JOIN dns_domains ON dns_domains.id = cloud_zone_map.id_domain";
	$obj_sql_gc->execute();

	if ($obj_sql_gc->num_rows())
	{
		$obj_sql_gc->fetch_array();

		foreach ($obj_sql_gc->data as $data_gc)
		{
			log_write("info", "cron_route53", "Removing old domain ". $data_gc["domain_name"] ." from Route53...");

			$obj_route53 = New cloud_route53;

			$obj_route53->select_account($data_gc["id_name_server"]);
			$obj_route53->select_domain($data_gc["id_domain"]);

			$obj_route53->action_delete_domain();

			unset($obj_route53);

			log_write("info", "cron_route53", "Domain removal complete");
		}
	}

	unset($obj_sql_gc);
}





/*
	Process domain creations and updates
*/

if ($obj_sql_domains->data_num_rows)
{
	foreach ($obj_sql_domains->data as $data_list)
	{
		$obj_sql		= New sql_query;
		$obj_sql->string 	= "SELECT id, soa_serial FROM cloud_zone_map WHERE id_name_server='". $data_list["server_id"] ."' AND id_domain='". $data_list["domain_id"] ."'";
		$obj_sql->execute();

		if (!$obj_sql->num_rows())
		{
			// Domain doesn't exist in mapping table - this means we are going to need to create it.
			log_write("info", "cron_route53", "Domain ". $data_list["domain_name"] ." does not yet exist in Route53, running creation process.");

			$obj_route53 = New cloud_route53;

			$obj_route53->select_account($data_list["server_id"]);
			$obj_route53->select_domain($data_list["domain_id"]);

			$obj_route53->action_create_domain();
			$obj_route53->action_update_records();

			unset($obj_route53);

			log_write("info", "cron_route53", "Domain creation complete.");
		}
		else
		{
			// Domain exists, check SOA
			$obj_sql->fetch_array();

			$current_soa = sql_get_singlevalue("SELECT soa_serial as value FROM dns_domains WHERE id='". $data_list["domain_id"] ."' LIMIT 1");

			if ($current_soa != $obj_sql->data[0]["soa_serial"])
			{
				// SOA doesn't match, update the domain records
				log_write("info", "cron_route53", "Domain ". $data_list["domain_name"] ." is out of date, running update process");

				$obj_route53 = New cloud_route53;

				$obj_route53->select_account($data_list["server_id"]);
				$obj_route53->select_domain($data_list["domain_id"]);

				$obj_route53->action_update_records();

				unset($obj_route53);

				log_write("info", "cron_route53", "Domain update complete");
			}
			else
			{
				log_write("info", "cron_route53", "Domain ". $data_list["domain_name"] ." is unchanged, no Route53 update required");
			}
		}

		unset($obj_sql);
	}
}
else
{
	log_write("info", "cron_route53", "No domains are in Route53 currently.");
}





// Display Stats if debugging
if (@$_SESSION["user"]["debug"] == "on")
{
	log_debug_render();
}

log_write("info", "cron_route53", "Route53 CronJob clean shutdown");
exit(0);

?>
