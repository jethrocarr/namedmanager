<?php
/*
	servers/group-view.php

	access:
		namedadmins

	Displays all the details and configuration options for a specific name server group.
*/

class page_output
{
	var $obj_name_server;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{

		// initate object
		$this->obj_name_server_group			= New name_server_group;

		// fetch variables
		$this->obj_name_server_group->id		= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Adjust Server Group", "page=servers/group-view.php&id=". $this->obj_name_server_group->id ."", TRUE);
		$this->obj_menu_nav->add_item("Delete Server Group", "page=servers/group-delete.php&id=". $this->obj_name_server_group->id ."");
	}


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}


	function check_requirements()
	{
		// make sure the server is valid
		if (!$this->obj_name_server_group->verify_id())
		{
			log_write("error", "page_output", "The requested server group (". $this->obj_name_server_group->id .") does not exist - possibly the server group has been deleted?");
			return 0;
		}

		return 1;
	}



	function execute()
	{
		/*
			Define form structure
		*/
		$this->obj_form			= New form_input;
		$this->obj_form->formname	= "name_server_group_edit";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "servers/group-edit-process.php";
		$this->obj_form->method		= "post";

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "group_name";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
							
		$structure = NULL;
		$structure["fieldname"]		= "group_description";
		$structure["type"]		= "textarea";
		$this->obj_form->add_input($structure);

		
		
		// group members

		$member_servers = "";

		$obj_sql		= New sql_query;
		$obj_sql->string	= "SELECT id, server_name FROM name_servers WHERE id_group='". $this->obj_name_server_group->id ."' ORDER BY server_name";
		$obj_sql->execute();

		if ($obj_sql->num_rows())
		{
			$obj_sql->fetch_array();

			$count = 0;
			foreach ($obj_sql->data as $data)
			{
				$count++;

				$member_servers .= "<a href=\"index.php?page=servers/view.php&id=". $data["id"] ."\">". $data["server_name"] ."</a>";

				if ($count != $obj_sql->data_num_rows)
				{
					$member_servers .= "<br> ";
				}
			}
		}

		$structure = NULL;
		$structure["fieldname"]		= "group_member_servers";
		$structure["type"]		= "text";
		$structure["options"]["nohidden"] = 1;
		$structure["defaultvalue"]	= $member_servers;
		$this->obj_form->add_input($structure);




		$member_domains = "";

		$obj_sql		= New sql_query;
		$obj_sql->string	= "SELECT id, domain_name FROM dns_domains WHERE id IN (SELECT id_domain FROM dns_domains_groups WHERE id_group='". $this->obj_name_server_group->id ."') ORDER BY domain_name";
		$obj_sql->execute();

		if ($obj_sql->num_rows())
		{
			$obj_sql->fetch_array();

			$count = 0;
			foreach ($obj_sql->data as $data)
			{
				$count++;

				$member_domains .= "<a href=\"index.php?page=domains/view.php&id=". $data["id"] ."\">". $data["domain_name"] ."</a>";

				if ($count != $obj_sql->data_num_rows)
				{
					$member_domains .= "<br> ";
				}
			}
		}

		$structure = NULL;
		$structure["fieldname"]		= "group_member_domains";
		$structure["type"]		= "text";
		$structure["options"]["nohidden"] = 1;
		$structure["defaultvalue"]	= $member_domains;
		$this->obj_form->add_input($structure);




		// hidden section
		$structure = NULL;
		$structure["fieldname"] 	= "id_name_server_group";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_name_server_group->id;
		$this->obj_form->add_input($structure);
			
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["group_details"]	= array("group_name", "group_description");
		$this->obj_form->subforms["group_members"]	= array("group_member_servers", "group_member_domains");
		$this->obj_form->subforms["hidden"]		= array("id_name_server_group");
		$this->obj_form->subforms["submit"]		= array("submit");


		// import data
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
		else
		{
			if ($this->obj_name_server_group->load_data())
			{
				$this->obj_form->structure["group_name"]["defaultvalue"]		= $this->obj_name_server_group->data["group_name"];
				$this->obj_form->structure["group_description"]["defaultvalue"]		= $this->obj_name_server_group->data["group_description"];
			}
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>SERVER GROUP CONFIGURATION</h3><br>";
		print "<p>This page allows you to view and adjust the server group details.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
