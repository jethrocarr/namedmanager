<?php
/*
	admin/config.php
	
	access: namedadmins only

	Allows administrators to change system-wide settings stored in the config table that affect
	the key operation of the application.
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
		// nothing to do
		return 1;
	}


	function execute()
	{
		/*
			Define form structure
		*/
		
		$this->obj_form = New form_input;
		$this->obj_form->formname = "config";
		$this->obj_form->language = $_SESSION["user"]["lang"];

		$this->obj_form->action = "admin/config-process.php";
		$this->obj_form->method = "post";


/*
		// security options
		$structure = NULL;
		$structure["fieldname"]				= "BLACKLIST_ENABLE";
		$structure["type"]				= "checkbox";
		$structure["options"]["label"]			= "Enable to prevent brute-force login attempts";
		$structure["options"]["no_translate_fieldname"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]				= "BLACKLIST_LIMIT";
		$structure["type"]				= "input";
		$structure["options"]["no_translate_fieldname"]	= "yes";
		$this->obj_form->add_input($structure);
*/


		// date/time configuration
		$structure = form_helper_prepare_timezonedropdown("TIMEZONE_DEFAULT");
		$structure["options"]["no_translate_fieldname"]	= "yes";
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"]				= "DATEFORMAT";
		$structure["type"]				= "radio";
		$structure["values"]				= array("yyyy-mm-dd", "mm-dd-yyyy", "dd-mm-yyyy");
		$structure["options"]["no_translate_fieldname"]	= "yes";
		$this->obj_form->add_input($structure);


		// default options
		$structure = NULL;
		$structure["fieldname"]					= "DEFAULT_HOSTMASTER";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "DEFAULT_TTL_SOA";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "DEFAULT_TTL_NS";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "DEFAULT_TTL_MX";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "DEFAULT_TTL_OTHER";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);




		// zone database configuration
		$structure = NULL;
		$structure["fieldname"]					= "ZONE_DB_TYPE";
		$structure["type"]					= "radio";
		$structure["values"]					= array("zone_internal", "powerdns_mysql");
		$structure["options"]["autoselect"]			= "yes";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "ZONE_DB_HOST";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "ZONE_DB_NAME";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "ZONE_DB_USERNAME";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]					= "ZONE_DB_PASSWORD";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$this->obj_form->add_input($structure);
		
		// miscellaneous configurations
		$structure = NULL;
		$structure["fieldname"]					= "LOG_UPDATE_INTERVAL";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$structure["options"]["label"]				= " seconds";
		$this->obj_form->add_input($structure);


		$max_input_vars = @ini_get('max_input_vars');

		if (empty($max_input_vars))
		{
			// PHP defaults if we can't query
			$max_input_vars = 1000;
		}

		$max_input_vars = sprintf("%d", $max_input_vars / 15);


		$structure = NULL;
		$structure["fieldname"]					= "PAGINATION_DOMAIN_RECORDS";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$structure["options"]["label"]				= " records per page (recommend maximum of $max_input_vars, adjust PHP max_input_vars to support more if required. Some browsers may perform badly with high values here)";
		$this->obj_form->add_input($structure);


		$this->obj_form->add_action("ZONE_DB_TYPE", "default", "ZONE_DB_HOST", "hide");
		$this->obj_form->add_action("ZONE_DB_TYPE", "default", "ZONE_DB_NAME", "hide");
		$this->obj_form->add_action("ZONE_DB_TYPE", "default", "ZONE_DB_USERNAME", "hide");
		$this->obj_form->add_action("ZONE_DB_TYPE", "default", "ZONE_DB_PASSWORD", "hide");

		$this->obj_form->add_action("ZONE_DB_TYPE", "powerdns_mysql", "ZONE_DB_HOST", "show");
		$this->obj_form->add_action("ZONE_DB_TYPE", "powerdns_mysql", "ZONE_DB_NAME", "show");
		$this->obj_form->add_action("ZONE_DB_TYPE", "powerdns_mysql", "ZONE_DB_USERNAME", "show");
		$this->obj_form->add_action("ZONE_DB_TYPE", "powerdns_mysql", "ZONE_DB_PASSWORD", "show");


		// admin API
		$structure = NULL;
		$structure["fieldname"]					= "ADMIN_API_KEY";
		$structure["type"]					= "input";
		$structure["options"]["no_translate_fieldname"]		= "yes";
		$structure["options"]["label"]				= " ". lang_trans("help_admin_api_key");
		$this->obj_form->add_input($structure);



		// submit section
		$structure = NULL;
		$structure["fieldname"]					= "submit";
		$structure["type"]					= "submit";
		$structure["defaultvalue"]				= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
//		$this->obj_form->subforms["config_security"]		= array("BLACKLIST_ENABLE", "BLACKLIST_LIMIT");
		$this->obj_form->subforms["config_zone_defaults"]	= array("DEFAULT_HOSTMASTER", "DEFAULT_TTL_SOA", "DEFAULT_TTL_NS", "DEFAULT_TTL_MX", "DEFAULT_TTL_OTHER");
		$this->obj_form->subforms["config_zone_database"]	= array("ZONE_DB_TYPE", "ZONE_DB_HOST","ZONE_DB_NAME", "ZONE_DB_USERNAME", "ZONE_DB_PASSWORD");
		$this->obj_form->subforms["config_api"]			= array("ADMIN_API_KEY");
		$this->obj_form->subforms["config_dateandtime"]		= array("DATEFORMAT", "TIMEZONE_DEFAULT");
		$this->obj_form->subforms["config_miscellaneous"]	= array("LOG_UPDATE_INTERVAL", "PAGINATION_DOMAIN_RECORDS");
		$this->obj_form->subforms["submit"]			= array("submit");


		if (error_check())
		{
			// load error datas
			$this->obj_form->load_data_error();
		}
		else
		{
			// fetch all the values from the database
			$sql_config_obj		= New sql_query;
			$sql_config_obj->string	= "SELECT name, value FROM config ORDER BY name";
			$sql_config_obj->execute();
			$sql_config_obj->fetch_array();

			foreach ($sql_config_obj->data as $data_config)
			{
				$this->obj_form->structure[ $data_config["name"] ]["defaultvalue"] = $data_config["value"];
			}

			unset($sql_config_obj);
		}
	}



	function render_html()
	{
		// Title + Summary
		print "<h3>CONFIGURATION</h3><br>";
		print "<p>Use this page to adjust NameManager's configuration to suit your requirements.</p>";
	
		// display the form
		$this->obj_form->render_form();
	}

	
}

?>
