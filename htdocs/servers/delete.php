<?php
/*
	servers/delete.php

	access:
		namedadmins

	Allows the selected server to be deleted
*/

class page_output
{
	var $obj_name_server;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{

		// initate object
		$this->obj_name_server	= New name_server;

		// fetch variables
		$this->obj_name_server->id	= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Adjust Server Configuration", "page=servers/view.php&id=". $this->obj_name_server->id ."");
		
		if ($GLOBALS["config"]["FEATURE_LOGS_API"])
		{
			$this->obj_menu_nav->add_item("View Server-Specific Logs", "page=servers/logs.php&id=". $this->obj_name_server->id ."");
		}

		$this->obj_menu_nav->add_item("Delete Server", "page=servers/delete.php&id=". $this->obj_name_server->id ."", TRUE);
	}


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}


	function check_requirements()
	{
		// make sure the server is valid
		if (!$this->obj_name_server->verify_id())
		{
			log_write("error", "page_output", "The requested server (". $this->obj_name_server->id .") does not exist - possibly the server has been deleted?");
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
		$this->obj_form->formname	= "name_server_delete";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "servers/delete-process.php";
		$this->obj_form->method		= "post";



		// general
		$structure = NULL;
		$structure["fieldname"] 	= "server_name";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);
							
		$structure = NULL;
		$structure["fieldname"]		= "server_description";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);


		// hidden section
		$structure = NULL;
		$structure["fieldname"] 	= "id_name_server";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_name_server->id;
		$this->obj_form->add_input($structure);
			

		// confirm delete
		$structure = NULL;
		$structure["fieldname"] 	= "delete_confirm";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= "Yes, I wish to delete this server and realise that once deleted the data can not be recovered.";
		$this->obj_form->add_input($structure);

		// submit
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "delete";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["server_delete"]	= array("server_name","server_description");
		$this->obj_form->subforms["hidden"]		= array("id_name_server");
		$this->obj_form->subforms["submit"]		= array("delete_confirm", "submit");


		// import data
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
		else
		{
			if ($this->obj_name_server->load_data())
			{
				$this->obj_form->structure["server_name"]["defaultvalue"]		= $this->obj_name_server->data["server_name"];
				$this->obj_form->structure["server_description"]["defaultvalue"]	= $this->obj_name_server->data["server_description"];
			}
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>DELETE SERVER</h3><br>";
		print "<p>This page allows you to delete an unwanted server - take care to make sure you are deleting the server that you intend to, this action is not reversable.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
