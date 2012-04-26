<?php
/*
	servers/groups.php

	access:
		namedadmins

	Interface to manage server groups.
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
		$this->obj_table->tablename	= "name_servers_groups";

		// define all the columns and structure
		$this->obj_table->add_column("standard", "group_name", "");
		$this->obj_table->add_column("standard", "group_description", "");
		$this->obj_table->add_column("standard", "group_members", "NONE");

		// defaults
		$this->obj_table->columns		= array("group_name", "group_description", "group_members");
		$this->obj_table->columns_order		= array("group_name");
		$this->obj_table->columns_order_options	= array("group_name");

		$this->obj_table->sql_obj->prepare_sql_settable("name_servers_groups");
		$this->obj_table->sql_obj->prepare_sql_addfield("id", "");

		// load data
		$this->obj_table->generate_sql();
		$this->obj_table->load_data_sql();


		// fetch member list
		for ($i=0; $i < $this->obj_table->data_num_rows; $i++)
		{
			$members = sql_get_singlecol("SELECT server_name AS value FROM name_servers WHERE id_group='". $this->obj_table->data[$i]["id"] ."'");
			
			$this->obj_table->data[$i]["group_members"] = format_arraytocommastring($members);
		}

	}


	function render_html()
	{
		// title + summary
		print "<h3>NAME SERVERS</h3>";
		print "<p>NamedManager provides group management functions to allow isolation of different groups of Name Servers, so that specific domains can be pushed to specific hosts. This page allows configuration of these domain zones.</p>";

		// table data
		if (!$this->obj_table->data_num_rows)
		{
			format_msgbox("important", "<p>There are no defined groups.</p>");
		}
		else
		{
			// details link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_details", "servers/group-view.php", $structure);

			// delete link
			$structure = NULL;
			$structure["id"]["column"]	= "id";
			$this->obj_table->add_link("tbl_lnk_delete", "servers/group-delete.php", $structure);


			// display the table
			$this->obj_table->render_table_html();

		}

		// add link
		print "<p><a class=\"button\" href=\"index.php?page=servers/group-add.php\">Add New Server Group</a></p>";
	}

}


?>
