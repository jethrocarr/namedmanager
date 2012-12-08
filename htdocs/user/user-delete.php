<?php
/*
	user/delete.php
	
	access:	admin only

	Allows an unwanted user to be deleted.
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

		$this->obj_menu_nav->add_item("User's Details", "page=user/user-view.php&id=". $this->id ."");
		$this->obj_menu_nav->add_item("User's Permissions", "page=user/user-permissions.php&id=". $this->id ."");
		$this->obj_menu_nav->add_item("Delete User", "page=user/user-delete.php&id=". $this->id ."", TRUE);
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
		$sql_obj->string	= "SELECT id FROM users WHERE id='". $this->id ."'";
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
		/*
			Define form structure
		*/
		$this->obj_form = New form_input;
		$this->obj_form->formname = "user_delete";
		$this->obj_form->language = $_SESSION["user"]["lang"];

		$this->obj_form->action = "user/user-delete-process.php";
		$this->obj_form->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "username";
		$structure["type"]		= "text";
		$this->obj_form->add_input($structure);


		// hidden
		$structure = NULL;
		$structure["fieldname"] 	= "id_user";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->id;
		$this->obj_form->add_input($structure);
		
		
		// confirm delete
		$structure = NULL;
		$structure["fieldname"] 	= "delete_confirm";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= "Yes, I wish to delete this user and realise that once deleted the data can not be recovered.";
		$this->obj_form->add_input($structure);



		// define submit field
		$structure = NULL;
		$structure["fieldname"]		= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "delete";
		$this->obj_form->add_input($structure);


		
		// define subforms
		$this->obj_form->subforms["user_delete"]	= array("username");
		$this->obj_form->subforms["hidden"]		= array("id_user");
		$this->obj_form->subforms["submit"]		= array("delete_confirm", "submit");

		
		// fetch the form data
		$this->obj_form->sql_query = "SELECT username FROM `users` WHERE id='". $this->id ."' LIMIT 1";
		$this->obj_form->load_data();


	}


	function render_html()
	{
		// title + summary
		print "<h3>DELETE USER</h3><br>";
		print "<p>This page allows you to delete the selected user account.</p>";

		// display the form
		$this->obj_form->render_form();
	}

}

?>
