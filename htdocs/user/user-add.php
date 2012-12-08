<?php
/*
	user/add.php
	
	access: admin only

	Allows the creation of new user accounts
*/


class page_output
{
	var $obj_form;	// page form


	function check_permissions()
	{
		return user_permissions_get("admin");
	}

	function check_requirements()
	{
		if (!user_online())
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


	function execute()
	{
		/*
			Define form structure
		*/
		$this->obj_form = New form_input;
		$this->obj_form->formname = "user_add";
		$this->obj_form->language = $_SESSION["user"]["lang"];

		$this->obj_form->action = "user/user-edit-process.php";
		$this->obj_form->method = "post";
		

		// general
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
		$structure["fieldname"]		= "password";
		$structure["type"]		= "password";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
	
		$structure = NULL;
		$structure["fieldname"]		= "password_confirm";
		$structure["type"]		= "password";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
	
		
	
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["user_view"]		= array("username", "realname", "contact_email");
		$this->obj_form->subforms["user_password"]	= array("password", "password_confirm");
		
		$this->obj_form->subforms["submit"]		= array("submit");

		
		// load any data returned due to errors
		$this->obj_form->load_data_error();
	}


	function render_html()
	{
		// Title + Summary
		print "<h3>ADD USER ACCOUNT</h3><br>";
		print "<p>This page allows you to create new user accounts.</b></p>";

		// display the form
		$this->obj_form->render_form();
	}

}

?>
