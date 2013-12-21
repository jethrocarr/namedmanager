<?php
/*
	servers/servers.php

	access:
		namedadmins

	Interface to view and manage what name servers are managed by this interface. The main reason
	for this interface is to put a view onto what is being recorded to allow the API to function and
	make it easier to get reports on a per-server basis.
*/


class page_output
{
	var $obj_table;


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}

	function check_requirements()
	{
		// nothing todo
		return 1;
	}


	function execute()
	{
		// establish a new table object
		$this->obj_table = New table;

		$this->obj_table->language	= $_SESSION["user"]["lang"];
		$this->obj_table->tablename	= "name_servers";

		// define all the columns and structure
		$this->obj_table->add_column("bool_tick", "server_primary", "");
		$this->obj_table->add_column("bool_tick", "server_record", "");
		$this->obj_table->add_column("standard", "server_name", "");
		$this->obj_table->add_column("standard", "server_group", "name_servers_groups.group_name");
		$this->obj_table->add_column("standard", "server_description", "");
		$this->obj_table->add_column("standard", "server_type", "");
		$this->obj_table->add_column("standard", "sync_status_zones", "NONE");
		$this->obj_table->add_column("standard", "sync_status_log", "NONE");

		// defaults
		$this->obj_table->columns		= array("server_primary", "server_record", "server_name", "server_description", "server_group", "server_type", "sync_status_zones", "sync_status_log");
		$this->obj_table->columns_order		= array("server_name");
		$this->obj_table->columns_order_options	= array("server_name");

		$this->obj_table->sql_obj->prepare_sql_settable("name_servers");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN name_servers_groups ON name_servers_groups.id = name_servers.id_group");
		$this->obj_table->sql_obj->prepare_sql_addfield("id", "name_servers.id");
		$this->obj_table->sql_obj->prepare_sql_addfield("api_sync_config", "");
		$this->obj_table->sql_obj->prepare_sql_addfield("api_sync_log", "");

		// load data
		$this->obj_table->generate_sql();
		$this->obj_table->load_data_sql();


		// check sync status
		$sync_status_config = sql_get_singlevalue("SELECT value FROM config WHERE name='SYNC_STATUS_CONFIG'");

		for ($i=0; $i < $this->obj_table->data_num_rows; $i++)
		{
			switch ($this->obj_table->data[$i]["server_type"])
			{
				case "route53":
					// check the SOA of the mapped zones against the actual zones. The Mapped SOA is only
					// updated once a successful change has been sent to Route53.
					$num_unsynced = sql_get_singlevalue("SELECT COUNT(id_domain) as value FROM cloud_zone_map LEFT JOIN dns_domains ON cloud_zone_map.id_domain=dns_domains.id WHERE cloud_zone_map.id_name_server='". $this->obj_table->data[$i]["id"] ."' AND cloud_zone_map.soa_serial!=dns_domains.soa_serial");

					if ($num_unsynced)
					{
						$this->obj_table->data[$i]["sync_status_zones"] = "<span class=\"table_highlight_important\">". lang_trans("status_unsynced") ."</span>";
					}
					else
					{
						$this->obj_table->data[$i]["sync_status_zones"] = "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span>";
					}

					// only route53 logs are self generated, so always show as synced.
					$this->obj_table->data[$i]["sync_status_log"]	= "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span>";

					// set primary and NS record for route53 hosts
					$this->obj_table->data[$i]["server_primary"]	= 1;
					$this->obj_table->data[$i]["server_record"]	= 1;
				break;

				case "api":
				default:

					if ($sync_status_config != $this->obj_table->data[$i]["api_sync_config"])
					{
						$this->obj_table->data[$i]["sync_status_zones"]	= "<span class=\"table_highlight_important\">". lang_trans("status_unsynced") ."</span>";
					}
					else
					{
						$this->obj_table->data[$i]["sync_status_zones"]	= "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span>";
					}


					if ($GLOBALS["config"]["FEATURE_LOGS_API"])
					{
						if ((time() - $this->obj_table->data[$i]["api_sync_log"]) > 86400)
						{
							$this->obj_table->data[$i]["sync_status_log"]	= "<span class=\"table_highlight_important\">". lang_trans("status_unsynced") ."</span>";
						}
						else
						{
							$this->obj_table->data[$i]["sync_status_log"]	= "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span>";
						}
					}
					else
					{
						$this->obj_table->data[$i]["sync_status_log"]	= "<span class=\"table_highlight_disabled\">". lang_trans("status_disabled") ."</span>";
					}
				break;
			}

		}

	}


	function render_html()
	{
		// title + summary
		print "<h3>NAME SERVERS</h3>";
		print "<p>Define all the name servers that are being used for management in this interface, all NS servers should be set here since the values are used to set the NS records on the domains.</p>";

		// table data
		if (!$this->obj_table->data_num_rows)
		{
			format_msgbox("important", "<p>There are currently no name servers being managed.</p>");
		}
		else
		{
			// details link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_details", "servers/view.php", $structure);

			// logging link
			if ($GLOBALS["config"]["FEATURE_LOGS_API"])
			{
				$structure = NULL;
				$structure["id"]["column"]	= "id";
				$this->obj_table->add_link("tbl_lnk_logs", "servers/logs.php", $structure);
			}

			// delete link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_delete", "servers/delete.php", $structure);


			// display the table
			$this->obj_table->render_table_html();

		}

		// add link
		print "<p><a class=\"button\" href=\"index.php?page=servers/add.php\">Add New Server</a></p>";

	}

}


?>
