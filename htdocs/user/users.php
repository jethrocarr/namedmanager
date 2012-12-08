<?php
/*
	user/users.php

	Administrator-only utility to create, edit or delete user accounts.
*/


class page_output
{
	var $obj_table;


	function check_permissions()
	{
		if (!user_permissions_get("admin"))
		{
			return 0;
		}

		if ($GLOBALS["config"]["AUTH_METHOD"] != "sql")
		{
			log_write("error", "page", "User options can only be configured when using local user authentication");
			return 0;
		}

		return 1;
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
		$this->obj_table->tablename	= "user_list";

		// define all the columns and structure
		$this->obj_table->add_column("standard", "username", "");
		$this->obj_table->add_column("standard", "realname", "");
		$this->obj_table->add_column("standard", "contact_email", "");
		$this->obj_table->add_column("timestamp", "lastlogin_time", "time");
		$this->obj_table->add_column("standard", "lastlogin_ipaddress", "ipaddress");

		// defaults
		$this->obj_table->columns		= array("username", "realname", "contact_email", "lastlogin_time");
		$this->obj_table->columns_order		= array("username");
		$this->obj_table->columns_order_options	= array("username", "realname", "contact_email", "lastlogin_time", "lastlogin_ipaddress");

		// define SQL structure
		$this->obj_table->sql_obj->prepare_sql_settable("users");
		$this->obj_table->sql_obj->prepare_sql_addfield("id", "");

		// acceptable filter options
		$structure = NULL;
		$structure["fieldname"] = "searchbox";
		$structure["type"]	= "input";
		$structure["sql"]	= "username LIKE '%value%' OR realname LIKE '%value%' OR contact_email LIKE '%value%'";
		$this->obj_table->add_filter($structure);


		// load options
		$this->obj_table->load_options_form();



		// fetch all the user information
		$this->obj_table->generate_sql();
		$this->obj_table->load_data_sql();

	}


	function render_html()
	{
		// title + summary
		print "<h3>USER MANAGEMENT</h3>";
		print "<p>This page allows you to create, edit or delete user accounts, as well as allowing you to define the the account permissions.</p>";

		// display options form
		$this->obj_table->render_options_form();

		// table data
		if (!count($this->obj_table->columns))
		{
			print "<p><b>Please select some valid options to display.</b></p>";
		}
		elseif (!$this->obj_table->data_num_rows)
		{
			print "<p><b>No users that match your options were found.</b></p>";
		}
		else
		{
			// details link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_details", "user/user-view.php", $structure);

			// permissions
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_permissions", "user/user-permissions.php", $structure);

			// delete link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_delete", "user/user-delete.php", $structure);


			// display the table
			$this->obj_table->render_table_html();

		}


		// add users
		print "<p><a class=\"button\" href=\"index.php?page=user/user-add.php\">Create a new User Account</a></p>";
	}

}


?>
