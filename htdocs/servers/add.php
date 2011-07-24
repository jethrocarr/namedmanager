<?php
/*
	servers/add.php

	access: namedadmins only

	Configure a new FreeRadius server to allow it to recieve configuration and push log to phpfreename.
*/

class page_output
{
	var $obj_menu_nav;
	var $obj_form;


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
		/*
			Define form structure
		*/
		$this->obj_form			= New form_input;
		$this->obj_form->formname	= "name_server_edit";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "servers/edit-process.php";
		$this->obj_form->method		= "post";


		// general
		$structure = NULL;
		$structure["fieldname"] 	= "server_name";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
							
		$structure = NULL;
		$structure["fieldname"]		= "server_description";
		$structure["type"]		= "textarea";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "server_primary";
		$structure["type"]		= "checkbox";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["label"]	= lang_trans("server_primary_option_help");
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "server_record";
		$structure["type"]		= "checkbox";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["label"]	= lang_trans("server_record_option_help");
		$structure["defaultvalue"]	= "on";
		$this->obj_form->add_input($structure);
			



		// api	
		$structure = NULL;
		$structure["fieldname"]		= "server_type";
		$structure["type"]		= "radio";
		$structure["values"]		= array("api");
		
		if ($GLOBALS["config"]["ZONE_DB_TYPE"] == "powerdns-mysql")
		{
			$structure["values"][] = "powerdns-compat";
		}

		$structure["defaultvalue"]	= "api";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "api_auth_key";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["label"]	= " ". lang_trans("help_api_auth_key");
		$this->obj_form->add_input($structure);

		$this->obj_form->add_action("server_type", "default", "api_auth_key", "hide");
		$this->obj_form->add_action("server_type", "api", "api_auth_key", "show");
	

		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		

		// subforms
		$this->obj_form->subforms["server_details"]	= array("server_name", "server_description", "server_primary", "server_record");
		$this->obj_form->subforms["server_type"]	= array("server_type", "api_auth_key");
		$this->obj_form->subforms["submit"]		= array("submit");



		// load data
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>ADD NEW NAME SERVER</h3><br>";
		print "<p>This page allows you to add a new name server to the system and define the API access key for the server.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
