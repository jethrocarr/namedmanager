<?php
/*
	domains/import.php

	access:
		namedadmins

	Imports existing zonefiles from an external source into 
	NamedManager. This form is a little clever and runs in
	a two stage process, storing the data in the session and then
	passing back as content to process before uploading.

	1. File Upload
	2. Basic Domain Processing

*/

class page_output
{
	var $mode;
	var $obj_form;


	function page_output()
	{
		$this->mode	= @security_script_input('/^[0-9]*$/', $_GET["mode"]);

		if (!$this->mode)
		{
			$this->mode = 1;
		}
	}


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
		if ($this->mode == 1)
		{
			/*
				MODE 1: INITAL FILE UPLOAD
			*/

			$this->obj_form 		= New form_input;

			$this->obj_form->formname 	= "domain_import";
			$this->obj_form->language 	= $_SESSION["user"]["lang"];

			$this->obj_form->action 	= "domains/import-process.php";
			$this->obj_form->method 	= "post";
	
			// import type
			$structure			= NULL;
			$structure["fieldname"]		= "import_upload_type";
			$structure["type"]		= "radio";
			$structure["values"]		= array("file_bind_8");
			$structure["defaultvalue"]	= "file_bind_8";
			$this->obj_form->add_input($structure);

			// file upload
			$structure 			= NULL;
			$structure["fieldname"]		= "import_upload_file";
			$structure["type"]		= "file";
			$this->obj_form->add_input($structure);

			

			// submit section
			$structure = NULL;
			$structure["fieldname"] 	= "submit";
			$structure["type"]		= "submit";
			$structure["defaultvalue"]	= "Save Changes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"]		= "mode";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $this->mode;
			$this->obj_form->add_input($structure);


			// define subforms
			$this->obj_form->subforms["upload"]		= array("import_upload_type", "import_upload_file");
			$this->obj_form->subforms["hidden"]		= array("mode");
			$this->obj_form->subforms["submit"]		= array("submit");


			// import data
			if (error_check())
			{
				$this->obj_form->load_data_error();
			}
	
		}
		elseif ($this->mode == 2)
		{
			/*
				MODE 2: DOMAIN RECORD ASSIGNMENT

				Information from the imported zone file under mode 1 has been converted and loaded into
				the session variables, from here we can now enter all that information into a form and
				the user can correct/complete before we push through to the database.

				We also need to address issues like over-writing of existing domains here.
			*/
		


			/*
				Define form structure
			*/
			$this->obj_form			= New form_input;
			$this->obj_form->formname	= "domain_import";
			$this->obj_form->language	= $_SESSION["user"]["lang"];

			$this->obj_form->action		= "domains/import-process.php";
			$this->obj_form->method		= "post";



			/*
				General domain & SOA information
			*/

			$structure = NULL;
			$structure["fieldname"] 	= "domain_type";
			$structure["type"]		= "radio";
			$structure["values"]		= array("domain_standard", "domain_reverse_ipv4", "domain_reverse_ipv6");
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
			$structure["fieldname"] 		= "ipv6_help";
			$structure["type"]			= "text";
			$structure["options"]["req"]		= "yes";
			$structure["defaultvalue"]		= "help_ipv6_help";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv4_network";
			$structure["type"]		= "input";
			$structure["options"]["help"]	= "eg: 192.168.0.0";
			$structure["options"]["label"]	= " /24";
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);
	/*
			$structure = NULL;
			$structure["fieldname"] 	= "ipv4_subnet";
			$structure["type"]		= "radio";
			$structure["values"]		= array("24", "16", "8");
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv4_autofill";
			$structure["type"]		= "checkbox";
			$structure["options"]["label"]	= lang_trans("help_ipv4_autofill");
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv4_autofill_domain";
			$structure["type"]		= "input";
			$structure["options"]["help"]	= "eg: static.example.com";
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);
	*/


			$structure = NULL;
			$structure["fieldname"] 	= "ipv6_network";
			$structure["type"]		= "input";
			$structure["options"]["help"]	= "eg: 2001:db8::/48";
			$structure["options"]["label"]	= " always include a /cidr value (/1 though to /64)";
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

/*
			$structure = NULL;
			$structure["fieldname"] 	= "ipv6_autofill";
			$structure["type"]		= "checkbox";
			$structure["options"]["label"]	= lang_trans("help_ipv6_autofill");
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv6_autofill_forward";
			$structure["type"]		= "checkbox";
			$structure["options"]["label"]	= lang_trans("help_ipv6_autofill_forward");
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv6_autofill_reverse_from_forward";
			$structure["type"]		= "checkbox";
			$structure["options"]["label"]	= lang_trans("help_ipv6_autofill_reverse_from_forward");
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 	= "ipv6_autofill_domain";
			$structure["type"]		= "input";
			$structure["options"]["help"]	= "eg: static.example.com";
			$structure["options"]["req"]	= "yes";
			$this->obj_form->add_input($structure);
*/


			$this->obj_form->add_action("domain_type", "default", "domain_name", "show");
			$this->obj_form->add_action("domain_type", "default", "ipv4_help", "hide");
			$this->obj_form->add_action("domain_type", "default", "ipv4_network", "hide");
	//		$this->obj_form->add_action("domain_type", "default", "ipv4_subnet", "hide");
	//		$this->obj_form->add_action("domain_type", "default", "ipv4_autofill", "hide");
			$this->obj_form->add_action("domain_type", "default", "ipv6_help", "hide");
			$this->obj_form->add_action("domain_type", "default", "ipv6_network", "hide");
	//		$this->obj_form->add_action("domain_type", "default", "ipv4_subnet", "hide");
	//		$this->obj_form->add_action("domain_type", "default", "ipv4_autofill", "hide");


			$this->obj_form->add_action("domain_type", "domain_standard", "domain_name", "show");

			$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "domain_name", "hide");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_help", "show");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_network", "show");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_subnet", "show");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv4_autofill", "show");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv6_help", "hide");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv6_network", "hide");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv6_subnet", "hide");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv4", "ipv6_autofill", "hide");

			$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "domain_name", "hide");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv4_help", "hide");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv4_network", "hide");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv4_subnet", "hide");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv4_autofill", "hide");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv6_help", "show");
			$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv6_network", "show");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv6_subnet", "hide");
	//		$this->obj_form->add_action("domain_type", "domain_reverse_ipv6", "ipv6_autofill", "hide");


	//		$this->obj_form->add_action("ipv4_autofill", "default", "ipv4_autofill_domain", "hide");
	//		$this->obj_form->add_action("ipv4_autofill", "1", "ipv4_autofill_domain", "show");


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
			$structure["defaultvalue"]	= $GLOBALS["config"]["DEFAULT_TTL_SOA"];
			$this->obj_form->add_input($structure);



			// define main domain subforms
			$this->obj_form->subforms["domain_details"]	= array("domain_type", "domain_name", "ipv4_help", "ipv4_network", "ipv6_help", "ipv6_network", "domain_description");
			$this->obj_form->subforms["domain_soa"]		= array("soa_hostmaster", "soa_serial", "soa_refresh", "soa_retry", "soa_expire", "soa_default_ttl");



			/*
				Imported Records

				The record import logic is not as advanced as the regular record handling
				page, it's primarily intended to display the import and allow correction
				before submission.

				For more advanced configuration and addition of rows, the user should
				import the domain and then adjust like normal.
			*/


			// subform header
			$this->obj_form->subforms["domain_records"]	= array("record_import_guide");

			$structure = NULL;
			$structure["fieldname"]		= "record_import_guide";
			$structure["type"]		= "message";
			$structure["defaultvalue"]	= "<p>". lang_trans("record_import_guide") ."</p>";
			$this->obj_form->add_input($structure);


			if (empty($_SESSION["error"]["num_records"]))
			{
				// no records returned
				$structure = NULL;
				$structure["fieldname"]				= "record_import_notice";
				$structure["type"]				= "message";
				$structure["defaultvalue"]			= "<p>". lang_trans("records_not_imported")  ."</p>";
				$structure["options"]["css_row_class"]		= "table_highlight_important";
				$this->obj_form->add_input($structure);
			
				$this->obj_form->subforms["domain_records"][]	= "record_import_notice";
			}
			else
			{
				// headers
				$this->obj_form->subforms["domain_records"][]				= "record_header";

				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_type";
				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_ttl";
				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_prio";
				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_name";
				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_content";
				$this->obj_form->subforms_grouped["domain_records"]["record_header"][]	= "record_header_import";

				$structure = NULL;
				$structure["fieldname"]				= "record_header_type";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_type");
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]				= "record_header_ttl";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_ttl");
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]				= "record_header_prio";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_prio");
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]				= "record_header_name";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_name");
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]				= "record_header_content";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_content");
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]				= "record_header_import";
				$structure["type"]				= "text";
				$structure["defaultvalue"]			= lang_trans("record_header_import");
				$this->obj_form->add_input($structure);



				// draw pre-defined nameserver records
				$obj_sql		= New sql_query;
				$obj_sql->string	= "SELECT server_name FROM name_servers";
				$obj_sql->execute();

				if ($obj_sql->num_rows())
				{
					$obj_sql->fetch_array();

					$i = 0;

					foreach ($obj_sql->data as $data_ns)
					{
						$i++;

						// record form items
						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_type";
						$structure["type"]				= "text";
						$structure["defaultvalue"]			= "NS";
						$this->obj_form->add_input($structure);

						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_ttl";
						$structure["type"]				= "text";
						$structure["defaultvalue"]			= $GLOBALS["config"]["DEFAULT_TTL_NS"];
						$this->obj_form->add_input($structure);

						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_prio";
						$structure["type"]				= "text";
						$structure["defaultvalue"]			= "";
						$this->obj_form->add_input($structure);

						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_name";
						$structure["type"]				= "text";
						$structure["defaultvalue"]			= "@";
						$this->obj_form->add_input($structure);

						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_content";
						$structure["type"]				= "text";
						$structure["defaultvalue"]			= $data_ns["server_name"];
						$this->obj_form->add_input($structure);

						$structure = NULL;
						$structure["fieldname"]				= "ns_". $i ."_import";
						$structure["type"]				= "checkbox";
						$structure["defaultvalue"]			= "on";
						$structure["options"]["disabled"]		= "yes";
						$structure["options"]["label"]			= "Import";
						$this->obj_form->add_input($structure);

			
						// domain records
						$this->obj_form->subforms["domain_records"][]				= "ns_".$i;

						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_type";
						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_ttl";
						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_prio";
						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_name";
						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_content";
						$this->obj_form->subforms_grouped["domain_records"]["ns_".$i ][]	= "ns_". $i ."_import";

					}

				} // end of pre-defined nameserver loop



				// loop through imported records and create form structure
				for ($i=0; $i < $_SESSION["error"]["num_records"]; $i++)
				{
					$record = $_SESSION["error"]["records"][$i];

					// record form items
					$structure 					= form_helper_prepare_dropdownfromdb("record_". $i ."_type", "SELECT type as label, type as id FROM `dns_record_types` WHERE type!='SOA'");
					$structure["options"]["width"]			= "100";
					$structure["defaultvalue"]			= $record["type"];
					$this->obj_form->add_input($structure);


					if (!$record["ttl"])
					{
						$record["ttl"]				= $GLOBALS["config"]["DEFAULT_TTL_OTHER"];
					}

					$structure = NULL;
					$structure["fieldname"]				= "record_". $i ."_ttl";
					$structure["type"]				= "input";
					$structure["options"]["width"]			= "100";
					$structure["defaultvalue"]			= $record["ttl"];
					$this->obj_form->add_input($structure);

					$structure = NULL;
					$structure["fieldname"]				= "record_". $i ."_prio";
					$structure["type"]				= "input";
					$structure["options"]["width"]			= "100";
					$structure["defaultvalue"]			= $record["prio"];
					$this->obj_form->add_input($structure);

					$structure = NULL;
					$structure["fieldname"]				= "record_". $i ."_name";
					$structure["type"]				= "input";
					$structure["defaultvalue"]			= $record["name"];
					$this->obj_form->add_input($structure);

					$structure = NULL;
					$structure["fieldname"]				= "record_". $i ."_content";
					$structure["type"]				= "input";
					$structure["defaultvalue"]			= $record["content"];
					$this->obj_form->add_input($structure);

					$structure = NULL;
					$structure["fieldname"]				= "record_". $i ."_import";
					$structure["type"]				= "checkbox";
					$structure["defaultvalue"]			= "on";
					$structure["options"]["label"]			= "Import";
					$this->obj_form->add_input($structure);

		
					// domain records
					$this->obj_form->subforms["domain_records"][]				= "record_".$i;

					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_type";
					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_ttl";
					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_prio";
					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_name";
					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_content";
					$this->obj_form->subforms_grouped["domain_records"]["record_".$i ][]	= "record_". $i ."_import";

				}
			}



			/*
				Unmatched Lines Report

				Sadly it's not always possible to import *every* line of ever zone file out there - the styles can vary
				by far too much to match at times.

				We have a section of the form to display the records which do not match so that users are notified and thus
				able to make corrections if needed.
			*/

			// subform header
			$this->obj_form->subforms["unmatched_import"]	= array("unmatched_import_help", "unmatched_import_notice");

			$structure = NULL;
			$structure["fieldname"]		= "unmatched_import_help";
			$structure["type"]		= "message";
			$structure["defaultvalue"]	= "<p>". lang_trans("unmatched_import_help") ."</p>";
			$this->obj_form->add_input($structure);


			if (empty($_SESSION["error"]["unmatched"]))
			{
				// no unmatched rows
				$structure = NULL;
				$structure["fieldname"]				= "unmatched_import_notice";
				$structure["type"]				= "message";
				$structure["defaultvalue"]			= "<p>". lang_trans("import_notice_no_unmatched_rows")  ."</p>";
				$structure["options"]["css_row_class"]		= "table_highlight_open";
				$this->obj_form->add_input($structure);
			}
			else
			{
				// import notice
				$structure = NULL;
				$structure["fieldname"]				= "unmatched_import_notice";
				$structure["type"]				= "message";
				$structure["defaultvalue"]			= "<p>". lang_trans("import_notice_unmatched_rows")  ."</p>";
				$structure["options"]["css_row_class"]		= "table_highlight_important";
				$this->obj_form->add_input($structure);


				// add all the unmatched rows
				for ($i=0; $i < count($_SESSION["error"]["unmatched"]); $i++)
				{
					$this->obj_form->subforms["unmatched_import"][]	= "unmatched_row_$i";

					$structure = NULL;
					$structure["fieldname"]				= "unmatched_row_$i";
					$structure["type"]				= "message";
					$structure["defaultvalue"]			= "\"". $_SESSION["error"]["unmatched"][$i] ."\"";
					$this->obj_form->add_input($structure);
				}

			} // end of unmatched lines loop



			/*
				Submission
			*/

			// submit section
			$structure = NULL;
			$structure["fieldname"] 	= "submit";
			$structure["type"]		= "submit";
			$structure["defaultvalue"]	= "Save Changes";
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"]		= "mode";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $this->mode;
			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"]		= "num_records";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $_SESSION["error"]["num_records"];
			$this->obj_form->add_input($structure);



			// define submit subforms
			$this->obj_form->subforms["hidden"]		= array("mode", "num_records");
			$this->obj_form->subforms["submit"]		= array("submit");


			// import data
//			if (error_check())
//			{
//				$_SESSION["error"]["form"]["domain_import"] = "error";
//				$this->obj_form->load_data_error();
//			}

			foreach (array_keys($this->obj_form->structure) as $fieldname)
			{
				if (isset($_SESSION["error"][$fieldname]))
				{
					$this->obj_form->structure[$fieldname]["defaultvalue"] = stripslashes($_SESSION["error"][$fieldname]);
				}
			}




		} // end of mode

	} // end of execute()


	function render_html()
	{
		// title + summary
		print "<h3>IMPORT DOMAIN</h3><br>";
		print "<p>Use this page to import a domain from a legacy DNS platform. Upload the zonefile and NamedManager will match records as best as it can to allow them to be imported.</p>";

	
		// display the form
		$this->obj_form->render_form();
	}

}

?>
