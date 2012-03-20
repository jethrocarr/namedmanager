<?php
/*
	servers/view.php

	access:
		namedadmins

	Displays all the details and configuration options for a specific name server.
*/

class page_output
{
	var $obj_name_server;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{

		// initate object
		$this->obj_name_server		= New name_server;

		// fetch variables
		$this->obj_name_server->id		= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Adjust Server Configuration", "page=servers/view.php&id=". $this->obj_name_server->id ."", TRUE);
		
		if ($GLOBALS["config"]["FEATURE_LOGS_ENABLE_API"])
		{
			$this->obj_menu_nav->add_item("View Server-Specific Logs", "page=servers/logs.php&id=". $this->obj_name_server->id ."");
		}

		$this->obj_menu_nav->add_item("Delete Server", "page=servers/delete.php&id=". $this->obj_name_server->id ."");
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

		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "api_auth_key";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["label"]	= " ". lang_trans("help_api_auth_key");
		$this->obj_form->add_input($structure);

		$this->obj_form->add_action("server_type", "default", "api_auth_key", "hide");
		$this->obj_form->add_action("server_type", "api", "api_auth_key", "show");
	


		// sync status
		$structure = NULL;
		$structure["fieldname"]			= "sync_status_config";
		$structure["type"]			= "text";
		$structure["options"]["nohidden"]	= "hide";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]			= "sync_status_log";
		$structure["type"]			= "text";
		$structure["options"]["nohidden"]	= "hide";
		$this->obj_form->add_input($structure);


		// hidden section
		$structure = NULL;
		$structure["fieldname"] 	= "id_name_server";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_name_server->id;
		$this->obj_form->add_input($structure);
			
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["server_details"]	= array("server_name", "server_description", "server_primary", "server_record");
		$this->obj_form->subforms["server_type"]	= array("server_type", "api_auth_key");
		$this->obj_form->subforms["server_status"]	= array("sync_status_config", "sync_status_log");
		$this->obj_form->subforms["hidden"]		= array("id_name_server");
		$this->obj_form->subforms["submit"]		= array("submit");


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
				$this->obj_form->structure["server_primary"]["defaultvalue"]		= $this->obj_name_server->data["server_primary"];
				$this->obj_form->structure["server_record"]["defaultvalue"]		= $this->obj_name_server->data["server_record"];

				$this->obj_form->structure["server_type"]["defaultvalue"]		= $this->obj_name_server->data["server_type"];
				$this->obj_form->structure["api_auth_key"]["defaultvalue"]		= $this->obj_name_server->data["api_auth_key"];

				if ($this->obj_name_server->data["sync_status_config"])
				{
					$this->obj_form->structure["sync_status_config"]["defaultvalue"]	= "<span class=\"table_highlight_important\">". lang_trans("status_unsynced") ."</span> Last synced on ". time_format_humandate($this->obj_name_server->data["api_sync_config"]) ." ". date("H:i:s", $this->obj_name_server->data["api_sync_log"]) ."";
				}
				else
				{
					$this->obj_form->structure["sync_status_config"]["defaultvalue"]	= "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span>";
				}


				if ($GLOBALS["config"]["FEATURE_LOGS_ENABLE"])
				{
					if ($this->obj_name_server->data["sync_status_log"])
					{
						$this->obj_form->structure["sync_status_log"]["defaultvalue"]		= "<span class=\"table_highlight_important\">". lang_trans("status_unsynced") ."</span> Logging appears stale, last synced on ". time_format_humandate($this->obj_name_server->data["api_sync_log"]) ." ". date("H:i:s", $this->obj_name_server->data["api_sync_log"]) ."";
					}
					else
					{
						$this->obj_form->structure["sync_status_log"]["defaultvalue"]		= "<span class=\"table_highlight_open\">". lang_trans("status_synced") ."</span> Last log message delivered on ". time_format_humandate($this->obj_name_server->data["api_sync_log"]) ." ". date("H:i:s", $this->obj_name_server->data["api_sync_log"]) ."";
					}
				}
				else
				{
					$this->obj_form->structure["sync_status_log"]["defaultvalue"]			= "<span class=\"table_highlight_disabled\">". lang_trans("status_disabled") ."</span>";
				}
			}
		}
	}


	function render_html()
	{
		// title + summary
		print "<h3>SERVER CONFIGURATION</h3><br>";
		print "<p>This page allows you to view and adjust the server configuration.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
