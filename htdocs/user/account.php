<?php
/*
	user/account.php
	
	access: all users

	Displays details about the user's account and allows it to be adjusted.
*/

class page_output
{
	var $obj_form;
	var $obj_user;


	function page_output()
	{
		$this->obj_user		= New ldap_auth_manage_user;
		$this->obj_user->id	= $_SESSION["user"]["id"];
	}


	function check_permissions()
	{
		return user_online();
	}

	function check_requirements()
	{
		// make sure user exists
		if ($this->obj_user->verify_id())
		{
			return 1;
		}

		return 0;
	}

	function execute()
	{
		/*
			Define form structure
		*/
		
		$this->obj_form = New form_input;
		$this->obj_form->formname = "user_account";
		$this->obj_form->language = $_SESSION["user"]["lang"];

		$this->obj_form->action = "user/account-process.php";
		$this->obj_form->method = "post";



		// general
		$structure = NULL;
		$structure["fieldname"] 	= "username";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"]		= "gn";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);
				
		$structure = NULL;
		$structure["fieldname"]		= "sn";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "uidnumber";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);
			
		$structure = NULL;
		$structure["fieldname"] 	= "gidnumber";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);
			
		$structure = NULL;
		$structure["fieldname"] 	= "loginshell";
		$structure["type"]		= "text";
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
		
			
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
		
		// define subforms
		$this->obj_form->subforms["user_view"]		= array("username", "gn", "sn", "uidnumber", "gidnumber", "loginshell");
		$this->obj_form->subforms["user_password"]	= array("password_message", "password", "password_confirm");
		$this->obj_form->subforms["submit"]		= array("submit");


		// import data from LDAP
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}
		else
		{
			// load from LDAP
			if ($this->obj_user->load_data())
			{
				$this->obj_form->structure["username"]["defaultvalue"]		= $this->obj_user->data["uid"];
				$this->obj_form->structure["gn"]["defaultvalue"]		= $this->obj_user->data["gn"];
				$this->obj_form->structure["sn"]["defaultvalue"]		= $this->obj_user->data["sn"];
				$this->obj_form->structure["uidnumber"]["defaultvalue"]		= $this->obj_user->data["uidnumber"];
				$this->obj_form->structure["gidnumber"]["defaultvalue"]		= $this->obj_user->data["gidnumber"];
				$this->obj_form->structure["loginshell"]["defaultvalue"]	= $this->obj_user->data["loginshell"];

			}
		}

		return 1;
	}



	function render_html()
	{
		// Title + Summary
		print "<h3>MY ACCOUNT</h3><br>";
		print "<p>Here you can view your account and adjust some settings such as your password.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

	
}

?>
