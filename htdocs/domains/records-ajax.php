<?php
/*
	domains/records-ajax.php

	access:
		namedadmins

	Performs a number of functions to support the domains/records.php page - in particular,
	the display of the custom records fields.
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
	var $num_records_custom; // for this pagination instance
	var $num_records_custom_total;
	var $offset;
	var $is_standard;


	function page_output()
	{
		// include custom scripts and/or logic
		$this->requires["javascript"][]	= "include/javascript/domain_records.js";

		// initate object
		$this->obj_domain		= New domain_records;

		// fetch variables
		$this->obj_domain->id		= security_script_input('/^[0-9]*$/', $_GET["id"]);
		$this->page			= 1;
		$this->offset			= 0;

		if (isset($_GET['pagination']))
		{
			$this->page = security_script_input('/^[0-9]*$/', $_GET["pagination"]);

			if ($this->page == 1)
			{
				$this->offset = 0;
			}
			else
			{
				$this->offset = $GLOBALS["config"]['PAGINATION_DOMAIN_RECORDS'] * ($this->page - 1);
			}
		}

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

	function get_pagination_row()
	{
		if ($this->num_records_custom_total > $GLOBALS["config"]["PAGINATION_DOMAIN_RECORDS"])
		{
			$str = "";

			// determine start and end records and total number of pages
			$start_record 	= ($this->offset + 1);
			$end_record	= $GLOBALS["config"]['PAGINATION_DOMAIN_RECORDS'] * $this->page;

			if ($end_record > $this->num_records_custom_total)
			{
				$end_record = $this->num_records_custom_total;
			}

			$this->page_total = ceil($this->num_records_custom_total / $GLOBALS["config"]['PAGINATION_DOMAIN_RECORDS']);

			if ($this->page > $this->page_total)
			{
				$this->page = $this->page_total;
			}


			if ($this->page != 1)
			{
				$str .= '<a class="button_small" id="pagination_1" href="#1">&lt;&lt;</a> ';
			}
			else
			{
				$str .= '<a class="button_small_disabled">&lt;&lt;</a>';
			}

			$pagination_nav_limit = 10;

			// determine starting point
			if (($this->page - $pagination_nav_limit) <= 1)
			{
				$pagination_start = 1;
			}
			else
			{
				$pagination_start = $this->page - $pagination_nav_limit;
			}

			if ($this->page >= ($this->page_total - $pagination_nav_limit))
			{
				$i = $this->page;

				while ($i > $this->page_total - ($pagination_nav_limit * 2) )
				{
					$i--;

					if ($i <= 1)
					{
						$i = 1;
						break;
					}
				}

				$pagination_start = $i;
			}

			if ($this->page + $pagination_nav_limit < $this->page_total)
			{
				$pagination_start = $this->page - $pagination_nav_limit;

				if ($pagination_start <= 1)
				{
					$pagination_start = 1;
				}
			}
			
			for ($i = $pagination_start; $i <= $this->page_total; $i++)
			{
				if ($pagination_start > 1 && $pagination_start == $i)
				{
					$str .= '...';
				}

				if ($i == $this->page)
				{
					$str .= '<a class="button_small_disabled">'. $i .'</a> ';
				}
				else
				{
					$str .= '<a class="button_small" id="pagination_' . $i . '" href="#' . $i . '">' . $i . '</a> ';
				}

				if ($i == $this->page_total)
				{
					break;
				}

				if ($i > $pagination_nav_limit + $this->page)
				{
					$str .= '...';
					break;
				}
			}

			if ($this->page != $this->page_total)
			{
				$str .= ' <a class="button_small" id="pagination_' . $this->page_total . '" href="#' . $this->page_total . '">&gt;&gt;</a>';
			}
			else
			{
				$str .= ' <a class="button_small_disabled"> &gt;&gt; </a>';
			}


			// output the information
			$str .= "<br><br>";
			$str .= $this->num_records_custom_total . ' record' . ($this->num_records_custom_total <> 1 ? 's' : '') . '. Showing Records ';
			$str .= $start_record . ' to ' . $end_record . ' on page ' . $this->page . ' of ' . $this->page_total . '<br />';


			return $str;
		} 
		
	}

	function execute()
	{

		/*
		 * Validate a POST (page navigation move will prompt this)
		 */
		
		if (isset($_POST['record_custom_page'] ))
		{
			// fetch data from POST and validate - we then return values
			$data = stripslashes_deep($this->obj_domain->validate_custom_records());

			// validate the record_custom_page for returning the user to their page, default to page 1 if any errors in validating...
			$data['record_custom_page']		= @security_form_input_predefined("int", "record_custom_page", 1, "");

/*
			echo '<tr><td colspan="100%">post-validation POST data<pre>'; 
			echo '<pre>';
			print_R($data);
			echo '</pre>';
			echo '</td></tr>';
			die("debug");
*/

			if (error_check())
			{
				log_write("debug", "records-ajax", "POST records provided but error encountered, failing");

				$_SESSION["error"]["form"]["domain_records"] = "failed";
				$this->page = $data['record_custom_page'];
			}
			else
			{
				// no errors... set the records to the session
				$_SESSION['form']['domain_records'][$this->obj_domain->id][$data['record_custom_page']] = $data['records'];
			}
		}



		/*
			Load domain data & records
		*/
		
		$this->num_records_custom_total = $this->obj_domain->data_record_custom_count();


		$this->obj_domain->load_data();

		// if the data is present in the session then it has either changed and is awaiting submission
		// or the user has visited that page before during this edit session
		
		if(isset($_SESSION['form']['domain_records'][$this->obj_domain->id][$this->page]) && count($_SESSION['form']['domain_records'][$this->obj_domain->id][$this->page])) {
			log_debug("execute", 'Loading records from session as previous load or edit detected');
			$this->obj_domain->data['records'] = $_SESSION['form']['domain_records'][$this->obj_domain->id][$this->page];
			/*
			echo '<tr><td colspan="100%">from sesssion<pre>';
			print_R($this->obj_domain->data['records']);
			echo '</td></tr>';
			*/
		} else {
			log_debug("execute", 'Loading records from db for page: ' . $this->page);
			$this->obj_domain->load_data_record_custom($this->offset, $GLOBALS["config"]['PAGINATION_DOMAIN_RECORDS']);
			/*
			echo '<tr><td colspan="100%">from db<pre>';
			print_R($this->obj_domain->data['records']);
			echo '</td></tr>';
			*/
		}



		// work out the IP for reverse domains
		if (strpos($this->obj_domain->data["domain_name"], "in-addr.arpa"))
		{
			// IPv4
			$ip = explode(".", $this->obj_domain->data["domain_name"]);

			$this->obj_domain->data["domain_ip_prefix"] = $ip[2] .".". $ip[1] .".". $ip[0];
		}
		elseif (strpos($this->obj_domain->data["domain_name"], "ip6.arpa"))
		{
			// IPv6
			$ip_reverse	= substr($this->obj_domain->data["domain_name"], 0, strlen($this->obj_domain->data["domain_name"])-9);
			$ip_array	= array();
		
			$i=0;
			foreach (array_reverse(explode(".", $ip_reverse)) as $ip)
			{
				$i++;

				$ip_array[] = $ip;

				if ($i == 4)
				{
					$i =0 ;
					$ip_array[] = ":";
				}
			}

			$this->obj_domain->data["domain_ip_prefix"] = implode("", $ip_array);
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
			Define stucture for all other record types

			This includes A, AAAA, PTR and other record types.
		*/


		// fetch all the known record types from the database
		$dns_record_types = sql_get_singlecol("SELECT type as value FROM `dns_record_types` WHERE user_selectable='1'");
			

		// unless there has been error data returned, fetch all the records
		// and work out the number of rows
		if (!isset($_SESSION["error"]["form"][$this->obj_form->formname]))
		{
			$this->num_records_custom = 1;

			foreach ($this->obj_domain->data["records"] as $record)
			{
				if (in_array($record["type"], $dns_record_types))
				{
					$this->num_records_custom++;
				}
			}
		}
		else
		{
			$this->num_records_custom = @security_script_input('/^[0-9]*$/', $_SESSION["error"]["num_records_custom"]);
		}

		// ensure there are at least two rows, if more are needed when entering information,
		// then the javascript functions will provide.

		if ($this->num_records_custom < 2)
		{
			$this->num_records_custom = 2;
		}


		// custom domain records
		for ($i = 0; $i < $this->num_records_custom; $i++)
		{					
			// values
			$structure = NULL;
			$structure["fieldname"] 		= "record_custom_". $i ."_id";
			$structure["type"]			= "hidden";
			$this->obj_form->add_input($structure);


			if (strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				$structure = NULL;
				$structure["fieldname"] 		= "record_custom_". $i ."_type";
				$structure["type"]			= "text";
				$structure["defaultvalue"]		= "PTR";
				$this->obj_form->add_input($structure);
			}
			else
			{
				$structure = form_helper_prepare_dropdownfromdb("record_custom_". $i ."_type", "SELECT type as label, type as id FROM `dns_record_types` WHERE user_selectable='1' AND is_standard='1'");
				$structure["defaultvalue"]		= "A";
				$structure["options"]["width"]		= "100";
				$this->obj_form->add_input($structure);
			}

			$structure = NULL;
			$structure["fieldname"]		 	= "record_custom_". $i ."_name";
			$structure["type"]			= "input";
			
			if (strpos($this->obj_domain->data["domain_name"], "in-addr.arpa"))
			{
				$structure["options"]["width"]		= "50";
				$structure["options"]["max_length"]	= "3";
				$structure["options"]["prelabel"]	= $this->obj_domain->data["domain_ip_prefix"] .". ";
				$structure["options"]["help"]		= "?";
			}
			elseif (strpos($this->obj_domain->data["domain_name"], "ip6.arpa"))
			{
				$structure["options"]["width"]		= "300";
				$structure["options"]["prelabel"]	= " ";
				$structure["options"]["help"]		= $this->obj_domain->data["domain_ip_prefix"] ."....";
				$structure["options"]["autofill"]	= $this->obj_domain->data["domain_ip_prefix"];

			}
			else
			{
				$structure["options"]["width"]		= "300";
				$structure["options"]["help"]		= "Record name, eg www";
			}

			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_custom_". $i ."_content";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "300";
			
			if (strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				// both IPv4 and IPv6
				$structure["options"]["help"]	= "Reverse record name, eg www.example.com";
			}
			else
			{
				$structure["options"]["help"]	= "Target IP, eg 192.168.0.1";
			}

			$this->obj_form->add_input($structure);

			$structure = NULL;
			$structure["fieldname"] 		= "record_custom_". $i ."_ttl";
			$structure["type"]			= "input";
			$structure["options"]["width"]		= "80";
			$structure["defaultvalue"]		= $this->obj_domain->data["soa_default_ttl"];
			$this->obj_form->add_input($structure);
			
			$structure = NULL;
			$structure["fieldname"]			= "record_custom_". $i ."_delete_undo";
			$structure["type"]			= "hidden";
			$structure["defaultvalue"]		= "false";
			$this->obj_form->add_input($structure);
			
			if (!strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				$structure = NULL;
				$structure["fieldname"]			= "record_custom_". $i ."_reverse_ptr";
				$structure["type"]			= "checkbox"; 
				$structure["options"]["label"]		= "";
				$this->obj_form->add_input($structure);

				$structure = NULL;
				$structure["fieldname"]         	= "record_custom_". $i ."_reverse_ptr_orig";
				$structure["type"] 		        = "hidden"; 
				$this->obj_form->add_input($structure);
			}
		}


		// load in what data we have
		//disable invalid fields
		$i = 0;

		foreach ($this->obj_domain->data["records"] as $record)
		{
			if (in_array($record["type"], $dns_record_types))
			{
				// special ID rules
				if ($record["id"])
				{
					$this->obj_form->structure["record_custom_". $i ."_id"]["defaultvalue"]		= $record["id"];
				}
				else
				{
					$this->obj_form->structure["record_custom_". $i ."_id"]["defaultvalue"]		= $record["id_record"];
				}

				// fetch data
				$this->obj_form->structure["record_custom_". $i ."_type"]["defaultvalue"]		= $record["type"];
				$this->obj_form->structure["record_custom_". $i ."_prio"]["defaultvalue"]		= $record["prio"];
				$this->obj_form->structure["record_custom_". $i ."_name"]["defaultvalue"]		= $record["name"];
				$this->obj_form->structure["record_custom_". $i ."_content"]["defaultvalue"]		= $record["content"];
				$this->obj_form->structure["record_custom_". $i ."_ttl"]["defaultvalue"]		= $record["ttl"];
				
				if ($record["type"] == "CNAME")
				{
					// disable inappropate values for CNAME fields
					$this->obj_form->structure["record_custom_". $i ."_reverse_ptr"]["options"]["disabled"] = "yes";
					$this->obj_form->structure["record_custom_". $i ."_reverse_ptr_orig"]["options"]["disabled"] = "yes";
				}
				elseif ($record["type"] == "PTR")
				{
					if (strpos($this->obj_domain->data["domain_name"], "ip6.arpa"))
					{
						// IPv6 PTR records are in ARPA format, we should convert it to something human readable
						$this->obj_form->structure["record_custom_". $i ."_name"]["defaultvalue"] = ipv6_convert_fromarpa($record["name"]);
					}

				}
				elseif ($record["type"] != "PTR")
				{
					if ($record["type"] == "A" || $record["type"] == "AAAA")
					{
						// check if this record has a reverse PTR value
						$obj_ptr = New domain_records;

						$obj_ptr->find_reverse_domain($record["content"]);

						if ($obj_ptr->id_record)
						{
							$obj_ptr->load_data_record();

							if ($record["name"] == "@" || $record["name"] == "*" || preg_match("/^\*\.[A-Za-z0-9:._-]+$/", $record["name"]))
							{
								$record["name"] = $this->obj_domain->data["domain_name"];
							}

							if ($obj_ptr->data_record["content"] == $record["name"] || $obj_ptr->data_record["content"] == ($record["name"] .".". $this->obj_domain->data["domain_name"]))
							{
								$this->obj_form->structure["record_custom_". $i ."_reverse_ptr"]["defaultvalue"] = "on";
								$this->obj_form->structure["record_custom_". $i ."_reverse_ptr_orig"]["defaultvalue"] = "on";
							}
						}

						unset($obj_ptr);
					}
					else
					{
						// reverse PTR not valid for this record type
						$this->obj_form->structure["record_custom_". $i ."_reverse_ptr"]["options"]["disabled"] = "yes";
					}
				}

				$i++;
			}
		}
		


		// hidden
		$structure = NULL;
		$structure["fieldname"] 	= "id_domain";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->obj_domain->id;
		$this->obj_form->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "record_custom_page";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->page";
		$this->obj_form->add_input($structure);

		$structure = NULL;
		$structure["fieldname"] 	= "num_records_custom";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "$this->num_records_custom";
		$this->obj_form->add_input($structure);

		// a record that can be set to determine the form status for final submit
		$structure = NULL;
		$structure["fieldname"] 	= "record_custom_status";
		$structure["type"]		= "hidden";

		// fetch data in event of an error
		if (error_check())
		{
			$this->obj_form->load_data_error();
			$structure["defaultvalue"]	= "0";
		} else {
			$structure["defaultvalue"]	= "1";
		}

		$this->obj_form->add_input($structure);
	}



	function render_html()
	{
		/*
			All other records
		*/

		print "<tr class=\"table_highlight_info\">";
			print "<td width=\"10%\"><b>". lang_trans("record_type") ."</b></td>";
			if (strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				print "<td width=\"15%\"><b>". lang_trans("record_ttl") ."</b></td>";
			}
			else
			{
				print "<td width=\"10%\"><b>". lang_trans("record_ttl") ."</b></td>";
			}
			print "<td width=\"35%\"><b>". lang_trans("record_name") ."</b></td>";
			print "<td width=\"35%\"><b>". lang_trans("record_content") ."</b></td>";
			if (!strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				print "<td width=\"5%\"><b>". lang_trans("reverse_ptr") ."</b></td>";
			}
			print "<td width=\"5%\">&nbsp;</td>";
		print "</tr>";
		
		// display all the rows
		for ($i = 0; $i < $this->num_records_custom; $i++)
		{
			if (isset($_SESSION["error"]["record_custom_". $i ."-error"]))
			{
				print "<tr class=\"form_error\">";
			}
			else
			{
				print "<tr class=\"table_highlight\">";
			}

			print "<td width=\"10%\" valign=\"top\">";
			$this->obj_form->render_field("record_custom_". $i ."_type");
			$this->obj_form->render_field("record_custom_". $i ."_id");
			print "</td>";

			if (strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				print "<td width=\"15%\" valign=\"top\">";
			}
			else
			{
				print "<td width=\"15%\" valign=\"top\">";
			}
			$this->obj_form->render_field("record_custom_". $i ."_ttl");
			print "</td>";

			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("record_custom_". $i ."_name");
			print "</td>";

			print "<td width=\"35%\" valign=\"top\">";
			$this->obj_form->render_field("record_custom_". $i ."_content");
			print "</td>";
			
			if (!strpos($this->obj_domain->data["domain_name"], "arpa"))
			{
				print "<td width=\"5%\" valign=\"top\" align=\"center\">";
				$this->obj_form->render_field("record_custom_". $i ."_reverse_ptr");
				$this->obj_form->render_field("record_custom_". $i ."_reverse_ptr_orig");
				print "</td>";
			}
			
			print "<td width=\"5%\" valign=\"top\" align=\"center\">";
			$this->obj_form->render_field("record_custom_". $i ."_delete_undo");
			print "<strong class=\"delete_undo\"><a href=\"\">delete</a></strong>";
			print "</td>";
				
			print "</tr>";
		}

		// spacer
		print "<tr><td colspan=\"100%\"><p>" . $this->get_pagination_row() . "<span style=\"float:right;\" id=\"domain_records_custom_loading\" style=\"display:hidden;\"><img src=\"images/wait20.gif\" /></span></p></td></tr>";


		// hidden fields
		$this->obj_form->render_field("record_custom_id_domain");
		//$this->obj_form->render_field("num_records_ns");
		//$this->obj_form->render_field("num_records_mx");
		$this->obj_form->render_field("num_records_custom");
		$this->obj_form->render_field("record_custom_page");
		$this->obj_form->render_field("record_custom_status");


		// end table + form
		///print "</table>";
		///print "</form>";

	} // end of render_html


}

/*
	Include configuration + libraries
*/
include("../include/config.php");
include("../include/amberphplib/main.php");
include("../include/application/main.php");


// create new page object
$page_obj = New page_output;

// page is valid
$page_valid = 1;

/*
	Load the page
*/

if ($page_valid == 1)
{
	// check permissions
	if ($page_obj->check_permissions())
	{


		/*
			Check data
		*/
		$page_valid = $page_obj->check_requirements();


		/*
			Run page logic, provided that the data was valid
		*/
		if ($page_valid)
		{
			$page_obj->execute();
		}
	}
	else
	{
		// user has no valid permissions
		$page_valid = 0;
		error_render_noperms();
	}
}

/*
	Draw messages
*/

if ($_SESSION["error"]["message"])
{
	print "<tr><td colspan=\"100%\">";
	log_error_render();
	print "</td></tr>";
}
else
{
	if ($_SESSION["notification"]["message"])
	{
		print "<tr><td>";
		log_notification_render();
		print "</td></tr>";
	}
}



/*
	Draw page data
*/


if ($page_valid)
{
	$page_obj->render_html();
}

?>
<?php

// erase error and notification arrays
$_SESSION["user"]["log_debug"] = array();
$_SESSION["error"] = array();
$_SESSION["notification"] = array();

?>

