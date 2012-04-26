<?php
/*
	servers/group-delete.php

	access:
		namedadmins

	Allows the selected server group to be deleted
*/

class page_output
{
	var $obj_name_server;
	var $obj_menu_nav;
	var $obj_form;

	var $lock_empty;
	var $lock_delete;


	function page_output()
	{

		// initate object
		$this->obj_name_server_group		= New name_server_group;

		// fetch variables
		$this->obj_name_server_group->id	= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Adjust Server Group", "page=servers/group-view.php&id=". $this->obj_name_server_group->id ."");
		$this->obj_menu_nav->add_item("Delete Server Group", "page=servers/group-delete.php&id=". $this->obj_name_server_group->id ."", TRUE);
	}


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}


	function check_requirements()
	{
		// make sure the group is valid
		if (!$this->obj_name_server_group->verify_id())
		{
			log_write("error", "page_output", "The requested server group (". $this->obj_name_server_group->id .") does not exist - possibly the server group has been deleted?");
			return 0;
		}

		// make sure the group has no member servers
		if (!$this->obj_name_server_group->verify_empty())
		{
			$this->lock_empty = 1;
		}

		// make sure the group is deleteable
		if (!$this->obj_name_server_group->verify_delete())
		{
			$this->lock_delete = 1;
		}

		return 1;
	}



	function execute()
	{
		/*
			Define form structure
		*/
		$this->obj_form			= New form_input;
		$this->obj_form->formname	= "name_server_group_delete";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "servers/group-delete-process.php";
		$this->obj_form->method		= "post";



		// general
		$structure = NULL;
		$structure["fieldname"] 	= "group_name";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);
							
		$structure = NULL;
		$structure["fieldname"]		= "group_description";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);


		// hidden section
		$structure = NULL;
		$structure["fieldname"] 	= "id_name_server_group";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_name_server_group->id;
		$this->obj_form->add_input($structure);
			

		// confirm delete
		$structure = NULL;
		$structure["fieldname"] 	= "delete_confirm";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= "Yes, I wish to delete this server group and realise that once deleted the data can not be recovered.";
		$this->obj_form->add_input($structure);

		// submit
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "delete";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["group_delete"]	= array("group_name","group_description");
		$this->obj_form->subforms["hidden"]		= array("id_name_server_group");

		if (!$this->lock_empty && !$this->lock_delete)
		{
			$this->obj_form->subforms["submit"]	= array("delete_confirm", "submit");
		}
		else
		{
			$this->obj_form->subforms["submit"]	= array();
		}


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
		print "<h3>DELETE SERVER GROUP</h3><br>";
		print "<p>This page allows you to delete an unwanted server group - take care to make sure you are deleting the server group that you intend to, this action is not reversable.</p>";

	
		// display the form
		$this->obj_form->render_form();
		
		if ($this->lock_empty)
		{
			format_msgbox("locked", "<p>Sorry, you can not delete this group whilst there are name servers and/or domains assigned to it - see the \"<a href=\"index.php?page=servers/group-view.php&id=". $this->obj_name_server_group->id ."\">Adjust Server Group</a>\" page for details.</p>");
		}

		if ($this->lock_delete)
		{
			format_msgbox("locked", "<p>This group can not be deleted as it is the only group in the system. Rename this group, or create another group before deleting this one.</p>");
		}
	}

}

?>
