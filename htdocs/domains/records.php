<?php
/*
	domains/records.php

	access:
		namedadmins

	Allows the updating of records for the selected domain.
*/

class page_output
{
	var $obj_domain;
	var $obj_menu_nav;
	var $obj_form;


	function page_output()
	{

		// initate object
		$this->obj_domain		= New domain_records;

		// fetch variables
		$this->obj_domain->id		= security_script_input('/^[0-9]*$/', $_GET["id"]);


		// define the navigiation menu
		$this->obj_menu_nav = New menu_nav;

		$this->obj_menu_nav->add_item("Domain Details", "page=domains/view.php&id=". $this->obj_domain->id ."");
		$this->obj_menu_nav->add_item("Domain Records", "page=domains/records.php&id=". $this->obj_domain->id ."", TRUE);
		$this->obj_menu_nav->add_item("Delete Domain", "page=domains/delete.php&id=". $this->obj_domain->id ."");
	}


	function check_permissions()
	{
		return user_permissions_get("namedadmins");
	}


	function check_requirements()
	{
		// make sure the domain is valid
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
		$this->obj_form->formname	= "domain_records";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "domains/records-process.php";
		$this->obj_form->method		= "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "domain_name";
		$structure["type"]		= "input";
		$this->obj_form->add_input($structure);


		/*
			Define Record Structure
		*/

		// unless there has been error data returned, fetch all the records
		// from the DB, and work out the number of rows
		if (!isset($_SESSION["error"]["form"][$this->obj_form->formname]))
		{
			$sql_trans_obj		= New sql_query;
			$sql_trans_obj->string	= "SELECT date_trans, amount_debit, amount_credit, chartid, source, memo FROM `account_trans` WHERE type='gl' AND customid='". $this->id ."'";
			$sql_trans_obj->execute();
	
			if ($sql_trans_obj->num_rows())
			{
				$sql_trans_obj->fetch_array();
		
				$this->num_records = $sql_trans_obj->data_num_rows+1;
			}
		}
		else
		{
			$this->num_records = @security_script_input('/^[0-9]*$/', $_SESSION["error"]["num_records"])+1;
		}

		

		// ensure there are always 2 rows at least, additional rows are added if required (ie viewing
		// an existing transaction) or on the fly when needed by javascript UI.
		
		if ($this->num_records < 2)
		{
			$this->num_records = 2;
		}

		// transaction rows
		for ($i = 0; $i < $this->num_records; $i++)
		{					
			// account
			$structure = form_helper_prepare_dropdownfromdb("trans_". $i ."_account", "SELECT id, code_chart as label, description as label1 FROM account_charts WHERE chart_type!='1' ORDER BY code_chart");
			$structure["options"]["width"]	= "200";
			$this->obj_form->add_input($structure);
			
			// debit field
			$structure = NULL;
			$structure["fieldname"] 	= "trans_". $i ."_debit";
			$structure["type"]		= "input";
			$structure["options"]["width"]	= "80";
			$this->obj_form->add_input($structure);

			// credit field
			$structure = NULL;
			$structure["fieldname"] 	= "trans_". $i ."_credit";
			$structure["type"]		= "input";
			$structure["options"]["width"]	= "80";
			$this->obj_form->add_input($structure);
		
			
			// source
			$structure = NULL;
			$structure["fieldname"] 	= "trans_". $i ."_source";
			$structure["type"]		= "input";
			$structure["options"]["width"]	= "100";
			$this->obj_form->add_input($structure);
			
			// description
			$structure = NULL;
			$structure["fieldname"] 	= "trans_". $i ."_description";
			$structure["type"]		= "textarea";
			$this->obj_form->add_input($structure);
			

			// if we have data from a sql query, load it in
			if ($sql_trans_obj->data_num_rows)
			{
				if (isset($sql_trans_obj->data[$i]["chartid"]))
				{
					$this->obj_form->structure["trans_". $i ."_debit"]["defaultvalue"]		= $sql_trans_obj->data[$i]["amount_debit"];
					$this->obj_form->structure["trans_". $i ."_credit"]["defaultvalue"]		= $sql_trans_obj->data[$i]["amount_credit"];
					$this->obj_form->structure["trans_". $i ."_account"]["defaultvalue"]		= $sql_trans_obj->data[$i]["chartid"];
					$this->obj_form->structure["trans_". $i ."_source"]["defaultvalue"]		= $sql_trans_obj->data[$i]["source"];
					$this->obj_form->structure["trans_". $i ."_description"]["defaultvalue"]	= $sql_trans_obj->data[$i]["memo"];
				}
			}
		}


		// total fields
		$structure = NULL;
		$structure["fieldname"] 	= "total_debit";
		$structure["type"]		= "hidden";
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "total_credit";
		$structure["type"]		= "hidden";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"]		= "money_format";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= format_money(0);
		$this->obj_form->add_input($structure);


		// hidden
		$structure = NULL;
		$structure["fieldname"] 	= "id_transaction";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->id;
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "num_records";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->num_records";
		$this->obj_form->add_input($structure);

	
	
		// submit section
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Save Changes";
		$this->obj_form->add_input($structure);
		
	
		// fetch data in event of an error
		if (error_check())
		{
			$this->obj_form->load_data_error();
		}

	}



	function render_html()
	{
		// Title + Summary
		print "<h3>DOMAIN NAME RECORDS</h3><br>";
		print "<p>Below is a list of all the records for your domain name, if you change any of them and click save, the changes will be applied and the name servers will reload shortly.</p>";


		/*
			Basic domain details

			We have to do this manually in order to be able to handle all the transaction rows
		*/

		// start form/table structure
		print "<form method=\"". $this->obj_form->method ."\" action=\"". $this->obj_form->action ."\" class=\"form_standard\">";
		print "<table class=\"form_table\" width=\"100%\">";


		// general form fields
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_details") ."</b></td>";
		print "</tr>";

		$this->obj_form->render_row("domain_name");	

		print "</tr>";



		/*
			Domain Records

			This section is the most complex part of the form, where we add new rows to the form
			for each transactions.
		*/
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_records") ."</b></td>";
		print "</tr>";

		print "<tr>";
		print "<td colspan=\"2\">";


		// display all the rows
		for ($i = 0; $i < $this->num_records; $i++)
		{
			if (isset($_SESSION["error"]["trans_". $i ."-error"]))
			{
				print "<tr class=\"form_error\">";
			}
			else
			{
				print "<tr class=\"table_highlight\">";
			}

			// account
			print "<td width=\"20%\" valign=\"top\">";
			$this->obj_form->render_field("trans_". $i ."_account");
			print "</td>";

			// debit
			print "<td width=\"15%\" valign=\"top\">";
			$this->obj_form->render_field("trans_". $i ."_debit");
			print "</td>";

			// credit
			print "<td width=\"15%\" valign=\"top\">";
			$this->obj_form->render_field("trans_". $i ."_credit");
			print "</td>";

			// source
			print "<td width=\"15%\" valign=\"top\">";
			$this->obj_form->render_field("trans_". $i ."_source");
			print "</td>";
		
			// description
			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("trans_". $i ."_description");
			print "</td>";

	
			print "</tr>";
		}


		/*
			Totals Display
		*/
	
		print "<tr class=\"table_highlight\">";

		// joining/filler columns
		print "<td width=\"20%\"></td>";
	

		// total debit
		print "<td width=\"15%\">";
		$this->obj_form->render_field("total_debit");
		print "</td>";

		// total credit
		print "<td width=\"15%\">";
		$this->obj_form->render_field("total_credit");
		print "</td>";
	
		// joining/filler columns
		print "<td width=\"15%\"></td>";
		print "<td width=\"35%\"></td>";
		
		print "</tr>";

		

		print "</table>";
		print "</td>";
		print "</tr>";

		// hidden fields
		$this->obj_form->render_field("id_domain");
		$this->obj_form->render_field("num_records");


		// form submit
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("submit") ."</b></td>";
		print "</tr>";
		
		$this->obj_form->render_row("submit");
		
		// end table + form
		print "</table>";
		print "</form>";

	} // end of render_html


}


?>
