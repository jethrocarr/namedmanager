<?php
/*
	logs/logs.php

	access:
		namedadmins

	Fetches the system changelog
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
		$this->obj_table->tablename	= "changelog";

		// define all the columns and structure
		$this->obj_table->add_column("timestamp", "timestamp", "");
		$this->obj_table->add_column("standard", "server_name", "name_servers.server_name");
		$this->obj_table->add_column("standard", "domain_name", "dns_domains.domain_name");
		$this->obj_table->add_column("standard", "username", "");
		$this->obj_table->add_column("standard", "log_type", "");
		$this->obj_table->add_column("standard", "log_contents", "");

		// defaults
		$this->obj_table->columns		= array("timestamp", "server_name", "domain_name", "username", "log_type", "log_contents");

		$this->obj_table->sql_obj->prepare_sql_settable("logs");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN name_servers ON name_servers.id = logs.id_server");
		$this->obj_table->sql_obj->prepare_sql_addjoin("LEFT JOIN dns_domains ON dns_domains.id = logs.id_domain");
		$this->obj_table->sql_obj->prepare_sql_addorderby_desc("timestamp");

		// acceptable filter options
		$structure = NULL;
		$structure["fieldname"] 	= "searchbox";
		$structure["type"]		= "input";
		$structure["sql"]		= "(server_name LIKE '%value%' OR domain_name LIKE '%value%' OR log_type LIKE '%value%' OR log_contents LIKE '%value%')";
		$this->obj_table->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "num_logs_rows";
		$structure["type"]		= "input";
		$structure["sql"]		= "";
		$structure["defaultvalue"]	= "1000";
		$this->obj_table->add_filter($structure);

		$structure = form_helper_prepare_dropdownfromdb("id_server_name", "SELECT id, server_name as label FROM name_servers ORDER BY server_name");
		$structure["type"]	= "dropdown";
		$structure["sql"]	= "id_server='value'";
		$this->obj_table->add_filter($structure);

		$structure = form_helper_prepare_dropdownfromdb("id_domain", "SELECT id, domain_name as label FROM dns_domains ORDER BY domain_name");
		$structure["type"]	= "dropdown";
		$structure["sql"]	= "id_domain='value'";
		$this->obj_table->add_filter($structure);




		// load options
		$this->obj_table->add_fixed_option("id", $this->obj_server_name->id);
		$this->obj_table->load_options_form();


		// generate SQL
		$this->obj_table->generate_sql();

		// load limit filter
		$this->obj_table->sql_obj->string .= "LIMIT ". $this->obj_table->filter["filter_num_logs_rows"]["defaultvalue"];

		// load data from DB
		$this->obj_table->load_data_sql();

	}


	function render_html()
	{
		// title + summary
		print "<h3>CHANGELOG</h3>";
		print "<p>This page is a record of all changes and logs relating to the domains controlled by this interface.</p>";

		// display options form
		$this->obj_table->render_options_form();

		// table data
		if (!count($this->obj_table->columns))
		{
			format_msgbox("important", "<p>Please select some valid options to display.</p>");
		}
		elseif (!$this->obj_table->data_num_rows)
		{
			format_msgbox("info", "<p>No log records that match your options were found.</p>");
		}
		else
		{

			// display the table
			$this->obj_table->render_table_html();

		}

	}

}


?>
