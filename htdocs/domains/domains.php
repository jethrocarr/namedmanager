<?php
/*
	domains/domains.php

	access:
		namedadmins

	Interface to view and manage domains.
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
		$this->obj_table->add_column("standard", "domain_name", "domain_name");
		$this->obj_table->add_column("standard", "domain_serial", "soa_serial");
		$this->obj_table->add_column("standard", "domain_description", "domain_description");

		// defaults
		$this->obj_table->columns		= array("domain_name", "domain_serial", "domain_description");

		// TODO: we should stop querying directly and use the domains class logic, this would also provide support for multiple backends.
		// use seporate zone database
		//$this->obj_table->sql_obj->session_init("mysql", $GLOBALS["config"]["ZONE_DB_HOST"], $GLOBALS["config"]["ZONE_DB_NAME"], $GLOBALS["config"]["ZONE_DB_USERNAME"], $GLOBALS["config"]["ZONE_DB_PASSWORD"]);

		// fetch all the domains
		$this->obj_table->sql_obj->prepare_sql_settable("dns_domains");
		$this->obj_table->sql_obj->prepare_sql_addfield("id", "");
		$this->obj_table->sql_obj->prepare_sql_addorderby("REVERSE(domain_name) LIKE 'apra%'");
		$this->obj_table->sql_obj->prepare_sql_addorderby("domain_description");
		$this->obj_table->sql_obj->prepare_sql_addorderby("domain_name");

		// load data
		$this->obj_table->generate_sql();
		$this->obj_table->load_data_sql();
	}


	function render_html()
	{
		// title + summary
		print "<h3>DOMAINS</h3>";
		print "<p>List of domains managed by this server:</p>";

		// table data
		if (!$this->obj_table->data_num_rows)
		{
			format_msgbox("important", "<p>There are currently no domain names configured.</p>");
		}
		else
		{
			// details link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_details", "domains/view.php", $structure);

			// domain records
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_records", "domains/records.php", $structure);

			// delete link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_delete", "domains/delete.php", $structure);


			// display the table
			$this->obj_table->render_table_html();

		}

		// add link
		print "<p><a class=\"button\" href=\"index.php?page=domains/add.php\">Add New Domain</a></p>";

	}

}


?>
