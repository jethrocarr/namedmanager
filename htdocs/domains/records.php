<?php
/*
	domains/records.php

	access:
		namedadmins

	Allows the updating of records for the selected domain.
*/

class page_output
{
	// framework
	var $requires;
	var $obj_menu_nav;

	// data objects
	var $obj_domain;
	var $obj_form;

	// used to tracking how many form rows for records to display
	var $num_records_mx;
	var $num_records_ns;
	var $num_records_custom;
	var $is_standard;


	function page_output()
	{
		// include custom scripts and/or logic
		$this->requires["javascript"][]	= "include/javascript/domain_records.js";

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
			Load domain data & records
		*/

		$this->obj_domain->load_data();
		$this->obj_domain->load_data_record_all();
		

		// work out the IP for reverse domains, assuming IPv4
		if (strpos($this->obj_domain->data["domain_name"], "arpa"))
		{
			$ip = explode(".", $this->obj_domain->data["domain_name"]);

			$this->obj_domain->data["domain_ip_prefix"] = $ip[2] .".". $ip[1] .".". $ip[0];
		}

		/*
			Define form structure
		*/
		$this->obj_form			= New form_input;
		$this->obj_form->formname	= "domain_records";
		$this->obj_form->language	= $_SESSION["user"]["lang"];

		$this->obj_form->action		= "domains/records-process.php";
		$this->obj_form->method		= "post";
		

		/*
			General Domain Info
		*/
		$structure = NULL;
		$structure["fieldname"] 		= "domain_name";
		$structure["type"]			= "message";
 		$structure["options"]["css_row_class"]	= "table_highlight";
		$structure["defaultvalue"]		= "<p><b>Domain ". $this->obj_domain->data["domain_name"] ." selected for adjustment</b></p>";
		$this->obj_form->add_input($structure);

		/*
			Define Nameservers
		*/

		// unless there has been error data returned, fetch all the records
		// and work out the number of rows - always have one extra
		if (!isset($_SESSION["error"]["form"][$this->obj_form->formname]))
		{
			$this->num_records_ns = 1;
			
			foreach ($this->obj_domain->data["records"] as $record)
			{
				if ($record["type"] == "NS")
				{
					$this->num_records_ns++;
				}
			}
		}
		else
		{
			$this->num_records_ns = @security_script_input('/^[0-9]*$/', $_SESSION["error"]["num_records_ns"]);
		}

		
		// ensure there are at least two rows, if more are needed when entering information,
		// then the javascript functions will provide.
		
		if ($this->num_records_ns < 2)
		{
			$this->num_records_ns = 2;
		}


		// NS domain records
		for ($i = 0; $i < $this->num_records_ns; $i++)
		{					
			// values
			$structure = NULL;
			$structure["fieldname"] 		= "record_ns_". $i ."_id";
			$structure["type"]			= "hidden";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_ns_". $i ."_type";
			$structure["type"]			= "text";
			$structure["defaultvalue"]		= "NS";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"]		 	= "record_ns_". $i ."_name";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "300";
			$structure["options"]["help"]		= "Domain Name (usually ". $this->obj_domain->data["domain_name"] .")";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_ns_". $i ."_content";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "300";
			$structure["options"]["help"]		= "FQDN of the name server";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_ns_". $i ."_ttl";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "80";
			$structure["defaultvalue"]		= $GLOBALS["config"]["DEFAULT_TTL_NS"];
			$this->obj_form->add_input($structure);
			
			$structure = NULL;
			$structure["fieldname"]			= "record_ns_". $i ."_delete_undo";
			$structure["type"]			= "hidden";
			$structure["defaultvalue"]		= "false";
			$this->obj_form->add_input($structure);
		}

		// load in what data we have
		$i = 0;

		foreach ($this->obj_domain->data["records"] as $record)
		{
			if ($record["type"] == "NS")
			{
				// fetch data
				$this->obj_form->structure["record_ns_". $i ."_id"]["defaultvalue"]		= $record["id_record"];
				$this->obj_form->structure["record_ns_". $i ."_name"]["defaultvalue"]		= $record["name"];
				$this->obj_form->structure["record_ns_". $i ."_content"]["defaultvalue"]	= $record["content"];
				$this->obj_form->structure["record_ns_". $i ."_ttl"]["defaultvalue"]		= $record["ttl"];


				// if the NS records belong to the domain, then disable
				if ($record["name"] == $this->obj_domain->data["domain_name"])
				{
					$this->obj_form->structure["record_ns_". $i ."_name"]["type"]		= "text";
					$this->obj_form->structure["record_ns_". $i ."_content"]["type"]	= "text";
					$this->obj_form->structure["record_ns_". $i ."_ttl"]["type"]		= "text";
					$this->obj_form->structure["record_ns_". $i ."_delete_undo"]["defaultvalue"]	= "disabled";
				}


				$i++;
			}
		}





		/*
			Define MX Record Structure
		*/

		// unless there has been error data returned, fetch all the records
		// and work out the number of rows
		if (!isset($_SESSION["error"]["form"][$this->obj_form->formname]))
		{
			$this->num_records_mx = 1;
			
			foreach ($this->obj_domain->data["records"] as $record)
			{
				if ($record["type"] == "MX")
				{
					$this->num_records_mx++;
				}
			}
		}
		else
		{
			$this->num_records_mx = @security_script_input('/^[0-9]*$/', $_SESSION["error"]["num_records_mx"]);
		}

		
		// ensure there are at least two rows, if more are needed when entering information,
		// then the javascript functions will provide.
		
		if ($this->num_records_mx < 2)
		{
			$this->num_records_mx = 2;
		}


		// MX domain records
		for ($i = 0; $i < $this->num_records_mx; $i++)
		{					
			// values
			$structure = NULL;
			$structure["fieldname"] 		= "record_mx_". $i ."_id";
			$structure["type"]			= "hidden";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_mx_". $i ."_type";
			$structure["type"]			= "text";
			$structure["defaultvalue"]		= "MX";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_mx_". $i ."_prio";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "50";
			$structure["options"]["max_length"]	= "2";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"]		 	= "record_mx_". $i ."_name";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "300";
			$structure["options"]["help"]		= "Origin (usually ". $this->obj_domain->data["domain_name"] .")";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_mx_". $i ."_content";
			$structure["type"]			= "input";
			$structure["options"]["help"]		= "FQDN or hostname of mail server";
			$structure["options"]["width"]		= "300";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_mx_". $i ."_ttl";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "80";
			$structure["defaultvalue"]		= $GLOBALS["config"]["DEFAULT_TTL_MX"];
			$this->obj_form->add_input($structure);
			
			$structure = NULL;
			$structure["fieldname"]			= "record_mx_". $i ."_delete_undo";
			$structure["type"]			= "hidden";
			$structure["defaultvalue"]		= "false";
			$this->obj_form->add_input($structure);
		}

		// load in what data we have
		$i = 0;

		foreach ($this->obj_domain->data["records"] as $record)
		{
			if ($record["type"] == "MX")
			{
				$this->obj_form->structure["record_mx_". $i ."_id"]["defaultvalue"]		= $record["id_record"];
				$this->obj_form->structure["record_mx_". $i ."_prio"]["defaultvalue"]		= $record["prio"];
				$this->obj_form->structure["record_mx_". $i ."_name"]["defaultvalue"]		= $record["name"];
				$this->obj_form->structure["record_mx_". $i ."_content"]["defaultvalue"]	= $record["content"];
				$this->obj_form->structure["record_mx_". $i ."_ttl"]["defaultvalue"]		= $record["ttl"];

				$i++;
			}
		}



		/*
			Define Custom Records

			The custom records form is more complex than MX/NS and is defined in the domains/records-ajax.php, being called via an AJAX
			call when required to display records.
		*/

		// hidden
		$structure = NULL;
		$structure["fieldname"] 	= "form_session";
		$structure["type"]		= "hidden";

		// clear the custom records from the session on initial page load for this edit session
		// if there is no record from the SESSION then we can assume that this is a new edit session as apposed to one that has been redirected from the process page

		if (!empty($_SESSION["form"]["domain_records"]["form_session"]))
		{
			$structure["defaultvalue"]		= $_SESSION["form"]["domain_records"]["form_session"];
		}
		else
		{
			$structure["defaultvalue"]		= time();
			$_SESSION['form']['domain_records']	= array();
		}

		$this->obj_form->add_input($structure);



		// hidden
		$structure = NULL;
		$structure["fieldname"] 	= "id_domain";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_domain->id;
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "num_records_ns";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->num_records_ns";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "num_records_mx";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->num_records_mx";
		$this->obj_form->add_input($structure);
/*
		$structure = NULL;
		$structure["fieldname"] 	= "num_records_custom";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->num_records_custom";
		$this->obj_form->add_input($structure);
*/
	
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
		print "<form method=\"". $this->obj_form->method ."\" action=\"". $this->obj_form->action ."\" class=\"form_standard\" name=\"domain_records\">";
		print "<table class=\"form_table\" width=\"100%\">";


		// general form fields
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_details") ."</b></td>";
		print "</tr>";

		$this->obj_form->render_row("domain_name");	

		print "</tr>";


		// spacer
		print "<tr><td colspan=\"2\"><br></td></tr>";



		/*
			NS Records
		*/
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_records_ns") ."</b></td>";
		print "</tr>";

		print "<tr>";
		print "<td colspan=\"2\" width=\"100%\">";

		print "<p>". lang_trans("domain_records_ns_help") ."</p>";

		print "<table width=\"100%\">";

		print "<tr class=\"table_highlight_info\">";
			print "<td width=\"10%\"><b>". lang_trans("record_type") ."</b></td>";
			print "<td width=\"15%\"><b>". lang_trans("record_ttl") ."</b></td>";
			print "<td width=\"35%\"><b>". lang_trans("record_origin") ."</b></td>";
			print "<td width=\"35%\"><b>". lang_trans("record_content") ."</b></td>";
			print "<td width=\"5%\">&nbsp;</td>";
		print "</tr>";

		print "</tr>";
		

		// display all the rows
		for ($i = 0; $i < $this->num_records_ns; $i++)
		{
			if (isset($_SESSION["error"]["record_ns_". $i ."-error"]))
			{
				print "<tr class=\"form_error\">";
			}
			else
			{
				print "<tr class=\"table_highlight\">";
			}

			print "<td width=\"10%\" valign=\"top\">";
			$this->obj_form->render_field("record_ns_". $i ."_type");
			$this->obj_form->render_field("record_ns_". $i ."_id");
			print "</td>";

			print "<td width=\"15%\" valign=\"top\">";
			$this->obj_form->render_field("record_ns_". $i ."_ttl");
			print "</td>";

			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("record_ns_". $i ."_name");
			print "</td>";

			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("record_ns_". $i ."_content");
			print "</td>";
			
			print "<td width=\"5%\" valign=\"top\">";
			if ($this->obj_form->structure["record_ns_". $i ."_delete_undo"]["defaultvalue"] != "disabled")
			{
				$this->obj_form->render_field("record_ns_". $i ."_delete_undo");
				print "<strong class=\"delete_undo\"><a href=\"\">delete</a></strong>";
			}


			print "</td>";

			print "</tr>";
		}

		print "</table>";
		print "</td></tr>";

		// spacer
		print "<tr><td colspan=\"2\"><br></td></tr>";


		/*
			MX Records
		*/
		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_records_mx") ."</b></td>";
		print "</tr>";

		print "<tr>";
		print "<td colspan=\"2\" width=\"100%\">";
		print "<table width=\"100%\">";

		print "<p>". lang_trans("domain_records_mx_help") ."</p>";

		print "<tr class=\"table_highlight_info\">";
			print "<td width=\"10%\"><b>". lang_trans("record_type") ."</b></td>";
			print "<td width=\"15%\"><b>". lang_trans("record_ttl") ."</b></td>";
			print "<td width=\"10%\"><b>". lang_trans("record_prio") ."</b></td>";
			print "<td width=\"25%\"><b>". lang_trans("record_origin") ."</b></td>";
			print "<td width=\"35%\"><b>". lang_trans("record_content") ."</b></td>";
			print "<td width=\"5%\">&nbsp;</td>";
		print "</tr>";
		

		// display all the rows
		for ($i = 0; $i < $this->num_records_mx; $i++)
		{
			if (isset($_SESSION["error"]["record_mx_". $i ."-error"]))
			{
				print "<tr class=\"form_error\">";
			}
			else
			{
				print "<tr class=\"table_highlight\">";
			}

			print "<td width=\"10%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_type");
			$this->obj_form->render_field("record_mx_". $i ."_id");
			print "</td>";

			print "<td width=\"15%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_ttl");
			print "</td>";

			print "<td width=\"10%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_prio");
			print "</td>";

			print "<td width=\"25%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_name");
			print "</td>";

			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_content");
			print "</td>";
			
			print "<td width=\"5%\" valign=\"top\">";
			$this->obj_form->render_field("record_mx_". $i ."_delete_undo");
			print "<strong class=\"delete_undo\"><a href=\"\">delete</a></strong>";
			print "</td>";
	
			print "</tr>";
		}

		print "</table>";
		print "</td></tr>";
	
		// spacer
		print "<tr><td colspan=\"2\"><br></td></tr>";


		/*	
			Custom Records

			The definition and display of custom records is controlled in domain/records-ajax.php.
		*/

		print "<tr class=\"header\">";
		print "<td colspan=\"2\"><b>". lang_trans("domain_records_custom") ."</b></td>";
		print "</tr>";

		print "<tr>";
		print "<td colspan=\"2\" width=\"100%\">";
		print "<table width=\"100%\" id=\"domain_records_custom\">";

		print "</table>";
		print "</td></tr>";



		// spacer
		print "<tr><td colspan=\"2\"><br></td></tr>";


		// hidden fields
		$this->obj_form->render_field("id_domain");
		$this->obj_form->render_field("num_records_ns");
		$this->obj_form->render_field("num_records_mx");
		$this->obj_form->render_field("form_session");
		// $this->obj_form->render_field("num_records_custom");


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
