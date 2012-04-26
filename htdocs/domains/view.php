<?php
/*
	domains/view.php

	access:
		namedadmins

	Displays all the details and configuration options for a specific domain.
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
			log_write("error", "page_output", "The requested domain (". $this->obj_domain->id .") does not exist - possibly the domain has been deleted?");
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

		$this->obj_form->action		= "domains/edit-process.php";
		$this->obj_form->method		= "post";

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "domain_name";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "domain_description";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "domain_description";
		$structure["type"]		= "textarea";
		$this->obj_form->add_input($structure);


		// domain groups
		$sql_name_server_group_obj		= New sql_query;
		$sql_name_server_group_obj->string	= "SELECT id, group_name, group_description FROM name_servers_groups ORDER BY group_name";
		$sql_name_server_group_obj->execute();

		if ($sql_name_server_group_obj->num_rows())
		{
			// user note
			$structure = NULL;
			$structure["fieldname"] 		= "domain_message";
			$structure["type"]			= "message";
			$structure["defaultvalue"]		= "<p>". lang_trans("help_domain_group_selection") ."</p>";
			$this->obj_form->add_input($structure);
			$this->domain_array[] = "domain_message";

			// fetch customer's current domain status
			if (!isset($_SESSION["error"]["message"]))
			{
				$sql_domain_groups_obj		= New sql_query;
				$sql_domain_groups_obj->string	= "SELECT id_group FROM dns_domains_groups WHERE id_domain='". $this->obj_domain->id ."'";

				$sql_domain_groups_obj->execute();

				if ($sql_domain_groups_obj->num_rows())
				{
					$sql_domain_groups_obj->fetch_array();
				}
			}

			// run through all the domains
			$sql_name_server_group_obj->fetch_array();

			foreach ($sql_name_server_group_obj->data as $data_group)
			{
				// define domain checkbox
				$structure = NULL;
				$structure["fieldname"] 		= "name_server_group_". $data_group["id"];
				$structure["type"]			= "checkbox";
				$structure["options"]["label"]		= $data_group["group_name"] ." -- ". $data_group["group_description"];
				$structure["options"]["no_fieldname"]	= "enable";

				// check if this domain is currently checked
				
				if ($sql_domain_groups_obj->data_num_rows)
				{
					foreach ($sql_domain_groups_obj->data as $data)
					{
						if ($data["id_group"] == $data_group["id"])
						{
							$structure["defaultvalue"] = "on";
						}
					}
				}

				// add to form
				$this->obj_form->add_input($structure);
				$this->domain_array[] = "name_server_group_". $data_group["id"];
			}
		}



		// SOA configuration
		$structure = NULL;
		$structure["fieldname"] 	= "soa_hostmaster";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_serial";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_refresh";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_retry";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure["fieldname"] 	= "soa_expire";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure["fieldname"] 	= "soa_default_ttl";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= $GLOBALS["config"]["DEFAULT_TTL_SOA"];
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
		$this->obj_form->subforms["domain_details"]	= array("domain_name", "domain_description");
		$this->obj_form->subforms["domain_groups"]	= $this->domain_array;
		$this->obj_form->subforms["domain_soa"]		= array("soa_hostmaster", "soa_serial", "soa_refresh", "soa_retry", "soa_expire", "soa_default_ttl");
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
				$this->obj_form->structure["domain_name"]["defaultvalue"]		= $this->obj_domain->data["domain_name"];
				$this->obj_form->structure["domain_description"]["defaultvalue"]	= $this->obj_domain->data["domain_description"];

				$this->obj_form->structure["soa_hostmaster"]["defaultvalue"]		= $this->obj_domain->data["soa_hostmaster"];
				$this->obj_form->structure["soa_serial"]["defaultvalue"]		= $this->obj_domain->data["soa_serial"];
				$this->obj_form->structure["soa_refresh"]["defaultvalue"]		= $this->obj_domain->data["soa_refresh"];
				$this->obj_form->structure["soa_retry"]["defaultvalue"]			= $this->obj_domain->data["soa_retry"];
				$this->obj_form->structure["soa_expire"]["defaultvalue"]		= $this->obj_domain->data["soa_expire"];
				$this->obj_form->structure["soa_default_ttl"]["defaultvalue"]		= $this->obj_domain->data["soa_default_ttl"];
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
