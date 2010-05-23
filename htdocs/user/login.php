<?php
/*
	user/login.php

	provides the user login interface
*/



class page_output
{
	var $obj_form;


	function check_permissions()
	{
		if (user_online())
		{
			log_write("error", "You are already logged in, there is no need to revisit the login page.");
			return 0;
		}
		else
		{
			return 1;
		}
	}

	function check_requirements()
	{
		// nothing todo
		return 1;
	}



	function execute()
	{
		/*
			Make sure that the user's old session has been totally cleaned up
			otherwise some very strange errors and logon behaviour can occur
		*/
		//$_SESSION["user"] = array();


		/*
			Define Login Form
		*/
		$this->obj_form = New form_input;

		$this->obj_form->formname = "login";

		$this->obj_form->action = "user/login-process.php";
		$this->obj_form->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "username_namedmanager";
		$structure["type"]		= "input";
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "password_namedmanager";
		$structure["type"]		= "password";
		$this->obj_form->add_input($structure);
		

		// submit button
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Login";
		$this->obj_form->add_input($structure);
		

		// load any data returned due to errors
		$this->obj_form->load_data_error();
	}



	function render_html()
	{
		// heading
		print "<h3>SYSTEM LOGIN:</h3>";
		print "<p>Please enter your LDAP username and password to login:</p>";

		// display the form
		$this->obj_form->render_form();
	}
	
} // end of page_output class


?>
