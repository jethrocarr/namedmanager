<?php
/*
	domains/domains-ajax.php

	access:
		namedadmins

	Pull in filtered list of domains.
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

		// filter pattern
		$filter = NULL;
		$filter["fieldname"] 		= "domain_name";
		$filter["type"]				= "input";
		$filter["sql"]				= "domain_name LIKE '%value%'";
		$filter["defaultvalue"]		= security_script_input("/^[A-Za-z0-9\.\-]*$/", $_GET["domain_name"]);

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
	
		// table data
		if ((!$this->obj_table->data_num_rows) and (!$_GET["domain_name"]))
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
			if (!$this->obj_table->data_num_rows)
			{
				format_msgbox("important", "<p>No match on domain names with current filter expression.</p>");
			}

		}
	}
}

/*
	Include configuration + libraries
*/
include("../include/config.php");
include("../include/amberphplib/main.php");
include("../include/application/main.php");


// create new page object
$page_obj = New page_output;

// page is valid
$page_valid = 1;

/*
	Load the page
*/

if ($page_valid == 1)
{
	// check permissions
	if ($page_obj->check_permissions())
	{


		/*
			Check data
		*/
		$page_valid = $page_obj->check_requirements();


		/*
			Run page logic, provided that the data was valid
		*/
		if ($page_valid)
		{
			$page_obj->execute();
		}
	}
	else
	{
		// user has no valid permissions
		$page_valid = 0;
		error_render_noperms();
	}
}

/*
	Draw messages
*/

if ($_SESSION["error"]["message"])
{
	print "<tr><td colspan=\"100%\">";
	log_error_render();
	print "</td></tr>";
}
else
{
	if ($_SESSION["notification"]["message"])
	{
		print "<tr><td>";
		log_notification_render();
		print "</td></tr>";
	}
}



/*
	Draw page data
*/


if ($page_valid)
{
	$page_obj->render_html();
}

?>
<?php

// erase error and notification arrays
$_SESSION["user"]["log_debug"] = array();
$_SESSION["error"] = array();
$_SESSION["notification"] = array();

?>
