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
	var $obj_form;
	var $invalid_filter;

		function page_output()
	{
		// include custom scripts and/or logic
		$this->requires["javascript"][]	= "include/javascript/filter_domains.js";

		// initialize filter
		$this->invalid_filter=false;
	}

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
		// Define form structure for the filter
		$this->obj_form			= New form_input;
		$this->obj_form->formname	= "filter_domains";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "/";
		$this->obj_form->method		= "get";


		// filter pattern
		$filter = NULL;
		$filter["fieldname"] 		= "domain_name";
		$filter["type"]				= "input";
		$filter["sql"]				= "domain_name LIKE '%value%'";
		$filter["defaultvalue"]		= security_script_input("/^[A-Za-z0-9\.\-]*$/", $_GET["domain_name"]);
		if ($filter["defaultvalue"] == "error")
		{
			$this->invalid_filter = true;
			$filter["defaultvalue"] = "";
		}
		$this->obj_form->add_input($filter);

		// submit button
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]			= "submit";
		$structure["defaultvalue"]	= "Apply Filter";
		$this->obj_form->add_input($structure);


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
		$this->obj_table->add_filter($filter);
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
		if ((!$this->obj_table->data_num_rows) and (!$_GET["domain_name"]))
		{
			format_msgbox("important", "<p>There are currently no domain names configured.</p>");
		}
		else {
			if ($this->invalid_filter)
			{
				format_msgbox("important", "<p>Invalid filter expression.</p>");
			}

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

			// include page name
			$structure = NULL;
			$structure["fieldname"] 	= "page";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $_GET["page"];
			$this->obj_form->add_input($structure);

			// hide hidden field "page"
			$this->obj_form->subforms["hidden"]	= array("page");

			// show input field with button
			$this->obj_form->subforms["Filter"]	= array("domain_name","submit");

			// display filter form
			$this->obj_form->render_form();

			// display the table
			print "<div id=\"domains\">";
			$this->obj_table->render_table_html();
			if (!$this->obj_table->data_num_rows)
			{
				format_msgbox("important", "<p>No match on domain names with current filter expression.</p>");
			}
			print "</div>";

		}

		// add link
		print "<p><a class=\"button\" href=\"index.php?page=domains/add.php\">Add New Domain</a></p>";

	}

}


?>
