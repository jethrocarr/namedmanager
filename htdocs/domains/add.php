<?php
/*
	domains/add.php

	access:
		namedadmins

	Allows a new domain to be added to the system.
*/

class page_output
{
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
		$this->obj_form->formname	= "domain_add";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "domains/edit-process.php";
		$this->obj_form->method		= "post";

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "domain_type";
		$structure["type"]		= "radio";
		$structure["values"]		= array("domain_standard", "domain_reverse_ipv4");
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= "domain_standard";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "domain_name";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 		= "ipv4_help";
		$structure["type"]			= "text";
		$structure["options"]["req"]		= "yes";
		$structure["defaultvalue"]		= "help_ipv4_help";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "ipv4_network";
		$structure["type"]		= "input";
		$structure["options"]["help"]	= "eg: 192.168.0.0/24";
		$structure["options"]["label"]	= " include /cidr for ranges greater than /24";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "ipv4_autofill";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= lang_trans("help_ipv4_autofill");
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "ipv4_autofill_forward";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= lang_trans("help_ipv4_autofill_forward");
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "ipv4_autofill_reverse_from_forward";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= lang_trans("help_ipv4_autofill_reverse_from_forward");
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);


		$structure = NULL;
		$structure["fieldname"] 	= "ipv4_autofill_domain";
		$structure["type"]		= "input";
		$structure["options"]["help"]	= "eg: static.example.com";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);


		$this->obj_form->add_action("domain_type", "default", "domain_name", "show");
		$this->obj_form->add_action("domain_type", "default", "ipv4_help", "hide");
		$this->obj_form->add_action("domain_type", "default", "ipv4_network", "hide");
//		$this->obj_form->add_action("domain_type", "default", "ipv4_subnet", "hide");
		$this->obj_form->add_action("domain_type", "default", "ipv4_autofill", "hide");

		$this->obj_form->add_action("domain_type", "domain_standard", "domain_name", "show");

		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "domain_name", "hide");
		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_help", "show");
		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_network", "show");
//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_subnet", "show");
		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_autofill", "show");
	

		$this->obj_form->add_action("ipv4_autofill", "default", "ipv4_autofill_domain", "hide");
		$this->obj_form->add_action("ipv4_autofill", "default", "ipv4_autofill_forward", "hide");
		$this->obj_form->add_action("ipv4_autofill", "default", "ipv4_autofill_reverse_from_forward", "hide");
		$this->obj_form->add_action("ipv4_autofill", "1", "ipv4_autofill_domain", "show");
		$this->obj_form->add_action("ipv4_autofill", "1", "ipv4_autofill_forward", "show");
		$this->obj_form->add_action("ipv4_autofill", "1", "ipv4_autofill_reverse_from_forward", "show");



		$structure = NULL;
		$structure["fieldname"] 	= "domain_description";
		$structure["type"]		= "textarea";
		$this->obj_form->add_input($structure);



		// SOA configuration
		$structure = NULL;
		$structure["fieldname"] 	= "soa_hostmaster";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= $GLOBALS["config"]["DEFAULT_HOSTMASTER"];
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_serial";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= date("Ymd") ."01";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_refresh";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= "21600";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "soa_retry";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= "3600";
		$this->obj_form->add_input($structure);

		$structure["fieldname"] 	= "soa_expire";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= "604800";
		$this->obj_form->add_input($structure);

		$structure["fieldname"] 	= "soa_default_ttl";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["defaultvalue"]	= $GLOBALS["config"]["DEFAULT_TTL_OTHER"];
		$this->obj_form->add_input($structure);


		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["domain_details"]	= array("domain_type", "domain_name", "ipv4_help", "ipv4_network", "ipv4_autofill", "ipv4_autofill_forward", "ipv4_autofill_reverse_from_forward", "ipv4_autofill_domain", "domain_description");
		$this->obj_form->subforms["domain_soa"]		= array("soa_hostmaster", "soa_serial", "soa_refresh", "soa_retry", "soa_expire", "soa_default_ttl");
		$this->obj_form->subforms["submit"]		= array("submit");


		// import data
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>ADD NEW DOMAIN</h3><br>";
		print "<p>Use this page to add a new domain to the system.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
