<?php
/*
	servers/view.php

	access:
		namedadmins

	Displays all the details and configuration options for a specific name server.
*/

class page_output
{
	var $obj_domain;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{

		// initate object
		$this->obj_domain		= New domain;

		// fetch variables
		$this->obj_domain->id		= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Domain Details", "page=domains/view.php&id=". $this->obj_domain->id ."", TRUE);
		$this->obj_menu_nav->add_item("Domain Records", "page=domains/records.php&id=". $this->obj_domain->id ."");
		$this->obj_menu_nav->add_item("Delete Domain", "page=domains/delete.php&id=". $this->obj_domain->id ."");
	}


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}


	function check_requirements()
	{
		// make sure the server is valid
		if (!$this->obj_domain->verify_id())
		{
			log_write("error", "page_output", "The requested domain server (". $this->obj_domain->id .") does not exist - possibly the domain has been deleted?");
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
		$this->obj_form->formname	= "domain_edit";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "domain/edit-process.php";
		$this->obj_form->method		= "post";

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "domain_name";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "domain_master";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
			
		$structure = NULL;
		$structure["fieldname"]		= "notified_serial";
		$structure["type"]		= "textarea";
		$this->obj_form->add_input($structure);


		// hidden section
		$structure = NULL;
		$structure["fieldname"] 	= "id_domain";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_domain->id;
		$this->obj_form->add_input($structure);
			
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["domain_details"]	= array("domain_name", "domain_master");
		$this->obj_form->subforms["domain_status"]	= array("notified_serial");
		$this->obj_form->subforms["hidden"]		= array("id_domain");
		$this->obj_form->subforms["submit"]		= array("submit");


		// import data
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
		else
		{
			if ($this->obj_domain->load_data())
			{
				$this->obj_form->structure["domain_name"]["defaultvalue"]		= $this->obj_domain->data["name"];
				$this->obj_form->structure["domain_master"]["defaultvalue"]		= $this->obj_domain->data["master"];
				$this->obj_form->structure["notified_serial"]["defaultvalue"]		= $this->obj_domain->data["notified_serial"];
			}
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>DOMAIN DETAILS</h3><br>";
		print "<p>This page allows you to view and adjust the domain details - if you wish to adjust the records, use the domain records page in the navigation menu.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
