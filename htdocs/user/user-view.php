<?php
/*
	user/user-view.php
	
	access: admin only

	Displays all the details of the user accounts and allows them to be adjusted.
*/

class page_output
{
	var $id;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{
		// fetch variables
		$this->id = security_script_input('/^[0-9]*$/', $_GET["id"]);

		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("User's Details", "page=user/user-view.php&id=". $this->id ."", TRUE);
		$this->obj_menu_nav->add_item("User's Permissions", "page=user/user-permissions.php&id=". $this->id ."");
		$this->obj_menu_nav->add_item("Delete User", "page=user/user-delete.php&id=". $this->id ."");
	}


	function check_permissions()
	{
		if (!user_permissions_get("admin"))
		{
			return 0;
		}

		if ($GLOBALS["config"]["AUTH_METHOD"] != "sql")
		{
			log_write("error", "page", "User options can only be configured when using local user authentication");
			return 0;
		}

		return 1;
	}


	function check_requirements()
	{
		// verify that user exists
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id FROM users WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if (!$sql_obj->num_rows())
		{
			log_write("error", "page_output", "The requested user (". $this->id .") does not exist - possibly the user has been deleted.");
			return 0;
		}

		unset($sql_obj);


		return 1;
	}



	function execute()
	{
		// fetch user options from the database
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT name, value FROM users_options WHERE userid='". $this->id ."'";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();
			
			// structure the results into a form we can then use to fill the fields in the form
			foreach ($sql_obj->data as $data)
			{
				$options[ $data["name"] ] = $data["value"];
			}
		}



	
		/*
			Define form structure
		*/
		$this->obj_form = New form_input;
		$this->obj_form->formname = "user_view";
		$this->obj_form->language = $_SESSION["user"]["lang"];

		$this->obj_form->action = "user/user-edit-process.php";
		$this->obj_form->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "id_user";
		$structure["type"]		= "text";
		$structure["defaultvalue"]	= $this->id;
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "username";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"]		= "realname";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "contact_email";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);


		// passwords
		$structure = NULL;
		$structure["fieldname"]		= "password_message";
		$structure["type"]		= "message";
		$structure["defaultvalue"]	= "<i>Only input a password if you wish to change the existing one.</i>";
		$this->obj_form->add_input($structure);
		
		
		$structure = NULL;
		$structure["fieldname"]		= "password";
		$structure["type"]		= "password";
		$this->obj_form->add_input($structure);
	
		$structure = NULL;
		$structure["fieldname"]		= "password_confirm";
		$structure["type"]		= "password";
		$this->obj_form->add_input($structure);
	
		
		
		// last login information
		$structure = NULL;
		$structure["fieldname"]		= "time";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "ipaddress";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);


		// options
		$structure = form_helper_prepare_radiofromdb("option_lang", "SELECT name as id, name as label FROM language_avaliable ORDER BY name");
		$structure["defaultvalue"] = $options["lang"];
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"]		= "option_dateformat";
		$structure["type"]		= "radio";
		$structure["values"]		= array("yyyy-mm-dd", "mm-dd-yyyy", "dd-mm-yyyy");
		$structure["defaultvalue"]	= $options["dateformat"];
		$this->obj_form->add_input($structure);

/*
		Timezone settings not important for this application

		$structure 			= form_helper_prepare_timezonedropdown("option_timezone");
		$structure["defaultvalue"]	= $options["timezone"];
		$this->obj_form->add_input($structure);
*/

		$structure = NULL;
		$structure["fieldname"]		= "option_shrink_tableoptions";
		$structure["type"]		= "checkbox";
		$structure["defaultvalue"]	= $options["shrink_tableoptions"];
		$structure["options"]["label"]	= "Automatically hide the options table when using defaults";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "option_debug";
		$structure["type"]		= "checkbox";
		$structure["defaultvalue"]	= $options["debug"];
		$structure["options"]["label"]	= "Enable debug logging - this will impact performance a bit but will show a full trail of all functions and SQL queries made</i>";
		$this->obj_form->add_input($structure);

		// concurrent logins
		$structure = NULL;
		$structure["fieldname"]		= "option_concurrent_logins";
		$structure["type"]		= "checkbox";
		$structure["defaultvalue"]	= $options["concurrent_logins"];
		$structure["options"]["label"]	= "Permit this user to make multiple simultaneous logins</i>";
		$this->obj_form->add_input($structure);
			
	
	
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["user_view"]		= array("id_user", "username", "realname", "contact_email");
		$this->obj_form->subforms["user_password"]	= array("password_message", "password", "password_confirm");
		$this->obj_form->subforms["user_info"]		= array("time", "ipaddress");
		$this->obj_form->subforms["user_options"]	= array("option_lang", "option_dateformat", "option_shrink_tableoptions", "option_debug", "option_concurrent_logins");
		
		$this->obj_form->subforms["submit"]		= array("submit");


		// fetch the form data
		$this->obj_form->sql_query = "SELECT id, username, realname, contact_email, time, ipaddress FROM `users` WHERE id='". $this->id ."' LIMIT 1";
		$this->obj_form->load_data();

		// convert the last login time to a human readable value
		$this->obj_form->structure["time"]["defaultvalue"] = date("Y-m-d H:i:s", $this->obj_form->structure["time"]["defaultvalue"]);


	}


	function render_html()
	{
		// title + summary
		print "<h3>USER DETAILS</h3><br>";
		print "<p>This page allows you to view and adjust the user account details. <b>Note: if you adjust any of the details on this page, the user will be logged out if they are currently using the system.</b></p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
