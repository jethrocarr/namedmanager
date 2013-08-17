<?php
/*
	journal.php

	Provides classes & functions to process and render journal entries
*/



/*
	class journal_base

	General functions & classes used by the other journal functions
*/
class journal_base
{
	var $journalname;		// name of the journal (used to store the entries in the DB)
	var $language = "en_us";	// language to use for the labels
	
	var $structure;			// used to hold the structure of the journal entry selected
	var $filter = array();		// structure of the filtering
	var $option = array();		// structure for fixed filter options
	
	var $sql_obj;			// object used for SQL string, queries and data


	/*
		journal_base()

		Class Contructor
	*/
	function journal_base()
	{
		// init the SQL structure
		$this->sql_obj = New sql_query;	
	}
	


	/*
		generate_sql()

		This function automatically builds the SQL query structure and will include
		any custom defined fields, where or orderby statements.

		It then uses the sql_query class to produce an SQL query string, which can be used
		by the load_data_sql() function.
	*/
	function generate_sql()
	{
		log_debug("journal_base", "Executing generate_sql()");

		// table name
		$this->sql_obj->prepare_sql_settable("journal");

		// content
		$this->sql_obj->prepare_sql_addfield("id", "");
		$this->sql_obj->prepare_sql_addfield("locked", "");
		$this->sql_obj->prepare_sql_addfield("customid", "");
		$this->sql_obj->prepare_sql_addfield("type", "");
		$this->sql_obj->prepare_sql_addfield("userid", "");
		$this->sql_obj->prepare_sql_addfield("timestamp", "");
		$this->sql_obj->prepare_sql_addfield("title", "");
		$this->sql_obj->prepare_sql_addfield("content", "");

		// select for this journal only
		$this->sql_obj->prepare_sql_addwhere("journalname='". $this->journalname ."'");

		// generate WHERE filters if any exist
		if ($this->filter)
		{
			foreach (array_keys($this->filter) as $fieldname)
			{
				// note: we only add the filter if a value has been saved to default value, otherwise
				// we assume the SQL could break.
				if (!empty($this->filter[$fieldname]["defaultvalue"]))
				{
					$query = str_replace("value", $this->filter[$fieldname]["defaultvalue"], $this->filter[$fieldname]["sql"]);
					$this->sql_obj->prepare_sql_addwhere($query);
				}
			}
		}


		// order by
		$this->sql_obj->prepare_sql_addorderby_desc("timestamp");

		// produce SQL statement
		$this->sql_obj->generate_sql();
		
		return 1;
	}
	



	/*
		load_data();

		This function executes the SQL statement and fetches all the data from
		the DB into an associative array.

		IMPORTANT NOTE: you *must* run the generate_sql function before running
		this function, in order to generate the SQL statement for execution.
	*/
	function load_data()
	{
		log_debug("journal_base", "Executing load_data_sql()");

		if (!$this->sql_obj->execute())
			return 0;

		if (!$this->sql_obj->num_rows())
		{
			return 0;
		}
		else
		{
			$this->sql_obj->fetch_array();
				
			return $this->sql_obj->data_num_rows;
		}
	}


	/*
		load_options_form()

		Imports data from POST or SESSION which matches this form to be used for the options.
	*/
	function load_options_form()
	{
		log_debug("journal_base", "Executing load_options_form()");
		
		/*
			Form options can be passed in two ways:
			1. POST - this occurs when the options have been passed at the last reload
			2. SESSION - if the user goes away and returns.

		*/

		if (isset($_GET["reset"]))
		{
			// reset the option form
			$_SESSION["form"][$this->journalname] = NULL;
		}
		else
		{
			if (isset($_GET["journal_display_options"]))
			{
				// flag custom options as active - this is used to adjust the display of the options dropdown
				$_SESSION["form"][$this->journalname]["custom_options_active"] = 1;


				log_debug("journal_base", "Loading options form from $_GET");
				
				// load filterby options
				foreach (array_keys($this->filter) as $fieldname)
				{
					// switch to handle the different input types
					// TODO: find a good way to merge this code and the code in the security_form_input_predefined
					// into a single function to reduce reuse and complexity.
					switch ($this->filter[$fieldname]["type"])
					{
						case "date":
							$this->filter[$fieldname]["defaultvalue"] = @security_script_input("/^[0-9]*-[0-9]*-[0-9]*$/", $_GET[$fieldname ."_yyyy"] ."-". $_GET[$fieldname ."_mm"] ."-". $_GET[$fieldname ."_dd"]);

							if ($this->filter[$fieldname]["defaultvalue"] == "--")
								$this->filter[$fieldname]["defaultvalue"] = "";
						break;
						
						// convert date to timestamp
						case "timestamp_date":

							$date = @security_script_input("/^[0-9]*-[0-9]*-[0-9]*$/", $_GET[$fieldname ."_yyyy"] ."-". $_GET[$fieldname ."_mm"] ."-". $_GET[$fieldname ."_dd"]);

							if ($date == "--")
							{
								$this->filter[$fieldname]["defaultvalue"] = "";
							}
							else
							{
								$this->filter[$fieldname]["defaultvalue"] = time_date_to_timestamp($date);
							}
						break;

						default:
							$this->filter[$fieldname]["defaultvalue"] = @@security_script_input("/^\S*$/", $_GET[$fieldname]);
						break;
					}

					// just blank input if it's in error
					if (isset($this->filter[$fieldname]["defaultvalue"]) && $this->filter[$fieldname]["defaultvalue"] == "error")
					{
						$this->filter[$fieldname]["defaultvalue"] = "";
					}
				}

			}
			else if (isset($_SESSION["form"][$this->journalname]["filters"]))
			{
				log_debug("journal_base", "Loading options form from session data");
			
				// load filterby options
				foreach (array_keys($this->filter) as $fieldname)
				{
					if (isset($_SESSION["form"][$this->journalname]["filters"][$fieldname]))
					{
						$this->filter[$fieldname]["defaultvalue"] = $_SESSION["form"][$this->journalname]["filters"][$fieldname];
					}
				}
			}

			// save options to session data
			foreach (array_keys($this->filter) as $fieldname)
			{
				if (isset($this->filter[$fieldname]["defaultvalue"]))
				{
					$_SESSION["form"][$this->journalname]["filters"][$fieldname] = $this->filter[$fieldname]["defaultvalue"];
				}
			}
		}

		return 1;
	}






	/*
		add_filter($option_array)

		Allows the specification of filter options, which display fields such as input boxes
		or dropdowns for search or filtering purposes.

		The input to these options is then used to form SQL WHERE queries.

		The structure for the $option_array is the same as for add_input for the form_input class
		- see the form::render_field function for structure definition - with one addition:
		
			$option_array["sql"] = "QUERY";
			
			Where QUERY can be any SQL statment that goes after WHERE, with the word "value"
			being a variable that gets replaced by the input in this option field.

			eg:
			$option_array["sql"] = "date > 'value'";
	*/
	function add_filter($option_array)
	{
		log_debug("journal_base", "Executing add_filter(option_array)");

		// we append "filter_" to fieldname, to prevent the chance of the filter field
		// having the same name as one of the column fields and breaking stuff.
		$option_array["fieldname"] = "filter_" . $option_array["fieldname"];
		
		$this->filter[ $option_array["fieldname"] ] = $option_array;
	}


	/*
		add_fixed_option($fieldname, $value)

		Adds a fixed hidden form input to the option form - for stuff like specifiy the ID of
		an object, etc.
	*/
	function add_fixed_option($fieldname, $value)
	{
		log_debug("table", "Executing add_fixed_option($fieldname, $value)");

		$this->option[$fieldname] = $value;
	}



	/*
		prepare_set_*

		Set required variables in the structure. This structure is then used by the journal_input
		and journal_process classes for editing/creating journal entries.
	*/
	function prepare_set_journalname($journalname)
	{
		log_debug("journal_base", "Executing prepare_set_journalname($journalname)");

		$this->journalname = $journalname;
	}

	function prepare_set_content($content)
	{
		log_debug("journal_base", "Executing prepare_set_content(content)");

		// make sure we perform quoting, since we will be insert
		// these text strings into the database
		if (get_magic_quotes_gpc() == 0)
		{
			$this->structure["content"] = addslashes($content);
		}
		else
		{
			$this->structure["content"] = $content;
		}
	}

	function prepare_set_title($title)
	{
		log_debug("journal_base", "Executing prepare_set_title($title)");

		if (get_magic_quotes_gpc() == 0)
		{
			$this->structure["title"] = addslashes($title);
		}
		else
		{
			$this->structure["title"] = $title;
		}
		
	}
	
	function prepare_set_journalid($journalid)
	{
		log_debug("journal_base", "Executing prepare_set_journalid($journalid)");
		
		$this->structure["id"] = $journalid;
	}
	
	function prepare_set_userid($userid)
	{
		log_debug("journal_base", "Executing prepare_set_userid($userid)");
		
		$this->structure["userid"] = $userid;
	}
	
	function prepare_set_customid($customid)
	{
		log_debug("journal_base", "Executing prepare_set_customid($customid)");
		
		$this->structure["customid"] = $customid;
	}
	
	function prepare_set_timestamp($timestamp)
	{
		log_debug("journal_base", "Executing prepare_set_timestamp($timestamp)");
		
		$this->structure["timestamp"] = $timestamp;
	}
	
	function prepare_set_type($type)
	{
		log_debug("journal_base", "Executing prepare_set_type($type)");
		
		$this->structure["type"] = $type;
	}

	function prepare_set_form_process_page($string)
	{
		log_debug("journal_base", "Executing prepare_set_form_process_page($string)");

		$this->structure["form_process_page"] = $string;
	}
	
	function prepare_set_download_page($string)
	{
		log_debug("journal_base", "Executing prepare_set_download_page($string)");

		$this->structure["download_page"] = $string;
	}



	/*
		prepare_predefined_optionform()

		Generates a standard option form for journals. It is unlikely that a custom journal
		option form is ever required, since journals are all the same.
	*/
	function prepare_predefined_optionform()
	{
		log_debug("journal_base", "Executing prepare_predefined_optionform()");
	
		$structure = NULL;
		$structure["fieldname"] = "date_start";
		$structure["type"]	= "timestamp_date";
		$structure["sql"]	= "timestamp >= 'value'";
		$this->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"] = "date_end";
		$structure["type"]	= "timestamp_date";
		$structure["sql"]	= "timestamp <= 'value'";
		$this->add_filter($structure);

		$structure = NULL;
		$structure["fieldname"] = "title_search";
		$structure["type"]	= "input";
		$structure["sql"]	= "title LIKE '%value%'";
		$this->add_filter($structure);
			
		$structure = NULL;
		$structure["fieldname"] = "content_search";
		$structure["type"]	= "input";
		$structure["sql"]	= "content LIKE '%value%'";
		$this->add_filter($structure);
	
		$structure = NULL;
		$structure["fieldname"] 	= "hide_events";
		$structure["type"]		= "checkbox";
		$structure["options"]["label"]	= "Hide Event Records";
		$structure["defaultvalue"]	= "enabled";
		$structure["sql"]		= "type!='event'";
		$this->add_filter($structure);
	}


	
	/*
		verify_journalid()

		Verifies that the journal entry ID supplied is valid and is not locked. Any function that makes
		a change to a journal entry, will call this function first to make sure it's ok to proceed.

		Return codes:
		0	invalid ID
		1	valid ID
		2	valid ID but entry is locked
	*/
	function verify_journalid()
	{
		log_debug("journal_base", "Executing verify_journalid()");

		if ($this->structure["id"])
		{
			// verify that the journal requested exists by fetching the lock status
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT locked FROM `journal` WHERE id='". $this->structure["id"] ."' LIMIT 1";
			$sql_obj->execute();

			if ($sql_obj->num_rows())
			{
				// we have verified that the entry exists, now we return different return codes
				// depending whether the entry is locked or not.
				
				$sql_obj->fetch_array();
			
				if ($sql_obj->data[0]["locked"])
				{
					// journal entry is valid, but has now locked
					return 2;
				}
				else
				{
					// journal entry is valid and editable
					return 1;
				}
			}
		}
		
		return 0;
	}



} // end of class journal_base




/*
	class journal_display

	Functions for displaying journal entries.
*/
class journal_display extends journal_base
{
	
	/*
		render_journal();

		Displays the full journal.

		IMPORTANT NOTE: you *must* run the load_data() function before
		running this function in order to load all the required data
		out of the SQL database.
		
	*/
	function render_journal()
	{
		log_debug("journal_display", "Executing render_journal()");


		if (!$this->sql_obj->data_num_rows)
		{
			// TODO: detect if the journal has entries which are being hidden by the filter options
			format_msgbox("important", "<p>This journal is either empty or has no entries matching your filter options</p>");
		}
		else
		{

			// display the journal entries in date order
			$previousvalue = "";
			foreach ($this->sql_obj->data as $data)
			{
				// resets
				$editlink = "";
		
				// if this journal entry was added by this user, allow it to be edited
				if ($data["userid"] == $_SESSION["user"]["id"])
				{
					if (!$data["locked"])
					{
						// user is able to edit/delete this entry
						$editlink = " <a class=\"button_small\" href=\"index.php?page=". $this->structure["form_process_page"] ."&id=". $data["customid"] ."&journalid=" . $data["id"] ."&type=". $data["type"] ."&action=edit\">edit</a> ";
						$editlink .= "<a class=\"button_small\" href=\"index.php?page=". $this->structure["form_process_page"] ."&id=". $data["customid"] ."&journalid=" . $data["id"] ."&type=". $data["type"] ."&action=delete\">delete</a> ";
					}
				}
		


				/*
					Get the name of the user who submitted the forn
				*/
				$sql_user_obj		= New sql_query;
				$sql_user_obj->string	= "SELECT realname FROM `users` WHERE id='". $data["userid"] ."' LIMIT 1";
				$sql_user_obj->execute();

				if (!$sql_user_obj->num_rows())
				{
					log_debug("journal_display", "Error: no user with ID of ". $data["userid"] ." exists!");
				}
				else
				{
					$sql_user_obj->fetch_array();
				}

				
				/*
					Format Fields
				*/
				$content	= format_text_display($data["content"]);
				$post_time 	= date("d F Y H:i:s", $data["timestamp"]);


				/*
					Process the journal entry, depending on it's type.

					The following types of journal entries can exist:
					 text		A block of text content
					 event		Log message/record from the system
				*/
				switch($data["type"])
				{
					
					case "event":
						/*
							Event entries are a very useful way of making log records or notes in journals
							for record keeping/audit trail purposes.
						*/

						if ($previousvalue != "event")
							print "<br>";
					
						print "<table width=\"100%\" cellpadding=\"0\">";

					
						// header
						print "<tr><td width=\"100%\"><table width=\"100%\"><tr>";
							print "<td width=\"50%\"><font style=\"font-size: 10px;\" color=\"#727272\">Event: ". $data["title"] ."</td>";
							print "<td width=\"50%\" align=\"right\"><font style=\"font-size: 10px;\" color=\"#727272\">Posted by ". $sql_user_obj->data[0]["realname"] ." @ $post_time</font></td>";
						print "</tr></table></td></tr>";

						// events don't make any use of the content field					

						print "</table>";

					break;


					case "text":
						/*
							The standard entry is just a block of text.
						*/
				
						print "<br><table width=\"100%\" cellpadding=\"5\" style=\"border: 1px #666666 dashed;\">";

						// header
						print "<tr class=\"journal_header\"><td width=\"100%\"><table width=\"100%\"><tr>";
							print "<td width=\"50%\"><b>". $data["title"] ."</b> $editlink</td>";
							print "<td width=\"50%\" align=\"right\">Posted by ". $sql_user_obj->data[0]["realname"] ." @ $post_time</td>";
						print "</tr></table></td></tr>";
					
						// content
						print "<tr><td width=\"100%\">$content</td></tr>";


						print "</table>";
					break;

					case "file":
						/*
							Files attached to the journal

							The journal uses the standard file uploading system to keep track of files.
						*/

						print "<br><table width=\"100%\" cellpadding=\"5\" style=\"border: 1px #666666 dashed;\">";


						// fetch information about the file
						$file_obj = New file_storage;
						$file_obj->data["type"]		= "journal";
						$file_obj->data["customid"]	= $data["id"];

						if (!$file_obj->load_data_bytype())
						{
							log_debug("journal_display", "Unable to load journal file!");
						}


						// header
						print "<tr class=\"journal_header\"><td width=\"100%\"><table width=\"100%\"><tr>";
							print "<td width=\"50%\"><b>File: ". $data["title"] ."</b> $editlink </td>";
							print "<td width=\"50%\" align=\"right\">Posted by ". $sql_user_obj->data[0]["realname"] ." @ $post_time</td>";
						print "</tr></table></td></tr>";


						// content
						// (this field is optional for attached files)
						if ($content)
						{
							print "<tr><td width=\"100%\">$content</td></tr>";
						}

						// file download link
						print "<tr><td width=\"50%\"><b><a href=\"". $this->structure["download_page"] ."?customid=". $file_obj->data["customid"] ."&fileid=". $file_obj->id ."\">Download File</a></b> (". $file_obj->data["file_size_human"] .")</td></tr>";

						print "</table>";
					break;
					

					default:
						log_debug("journal_display", "Invalid journal type of ". $data["type"] ." provided, unable to process entry ". $data["id"] ."");
					break;
					
				} // end type switch

				// use the previous type value to track if we need <br> added or not
				$previousvalue = $data["type"];
				

			} // end of loop through journal entried

		} // end if journal exists			
		
	} // end of render_journal function




	/*
		render_options_form()
		
		Displays a list of all the avaliable columns for the user to select from, as well as various
		filter options
	*/
	function render_options_form()
	{	
		log_debug("journal_display", "Executing render_options_form()");

	
		// if the user has not configured any default options, display the dropdown
		// link bar instead of the main options table.
		if (isset($_SESSION["form"][$this->journalname]["custom_options_active"]))
		{
			if ($_SESSION["user"]["shrink_tableoptions"] == "on")
			{
				print "<div id=\"". $this->journalname ."_link\">";

				print "<table width=\"100%\" class=\"table_options_dropdown\">";
				print "<tr bgcolor=\"#666666\">";

					print "<td width=\"100%\" onclick=\"obj_show('". $this->journalname ."_form'); obj_hide('". $this->journalname ."_link');\">";
					print "<b style=\"color: #ffffff; text-decoration: none\">ADJUST JOURNAL OPTIONS &gt;&gt;</b>";
					print "</td>";

				print "</tr>";
				print "</table><br>";

				print "</div>";
			}
		}


		// border table / div object
		print "<div id=\"". $this->journalname ."_form\">";
		print "<table width=\"100%\" style=\"border: 1px solid #666666;\" bgcolor=\"#e7e7e7\"><tr><td>";


		
		// start the form
		print "<form method=\"get\" class=\"form_standard\">";
		
		$form = New form_input;
		$form->formname = $this->journalname;
		$form->language = $this->language;

		// include page name
		$structure = NULL;
		$structure["fieldname"] 	= "page";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $_GET["page"];
		$form->add_input($structure);
		$form->render_field("page");

		// include any other fixed options
		foreach (array_keys($this->option) as $fieldname)
		{
			$structure = NULL;
			$structure["fieldname"]		= $fieldname;
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $this->option[$fieldname];
			$form->add_input($structure);
			$form->render_field($fieldname);
		}


		// flag this form as the journal_display_options form
		$structure = NULL;
		$structure["fieldname"] 	= "journal_display_options";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->journalname;
		$form->add_input($structure);
		$form->render_field("journal_display_options");


		print "<table width=\"100%\"><tr>";
		
		/*
			Filter Options
		*/
		
		print "<td width=\"100%\" valign=\"top\" style=\"padding: 4px; background-color: #e7e7e7;\">";
			print "<b>Filter/Search Options:</b><br><br>";

			print "<table width=\"100%\">";

			if ($this->filter)
			{
				foreach (array_keys($this->filter) as $fieldname)
				{
					if ($this->filter[$fieldname]["type"] == "dropdown")
						$this->filter[$fieldname]["options"]["width"] = 150;

					$form->add_input($this->filter[$fieldname]);
					$form->render_row($fieldname);
				}
			}
			
			print "</table>";		
		print "</td>";
		

		// new row
		print "</tr>";
		print "<tr>";


		/* Order By Options 
		print "<td width=\"100%\" colspan=\"4\" valign=\"top\" style=\"padding: 4px; background-color: #e7e7e7;\">";

			print "<br><b>Order By:</b><br>";

			// limit the number of order boxes to 4
			$num_cols = count($columns_available);

			if ($num_cols > 4)
				$num_cols = 4;

			
			for ($i=0; $i < $num_cols; $i++)
			{
				// define dropdown
				$structure = NULL;
				$structure["fieldname"]		= "order_$i";
				$structure["type"]		= "dropdown";
				$structure["options"]["width"]	= 150;
				
				if ($this->columns_order[$i])
					$structure["defaultvalue"] = $this->columns_order[$i];

				$structure["values"] = $columns_available;

				$form->add_input($structure);

				// display drop down
				$form->render_field($structure["fieldname"]);

				if ($i < ($num_cols - 1))
				{
					print " then ";
				}
			}
			
		print "</td>";
*/

		/*
			Submit Row
		*/
		print "<tr>";
		print "<td colspan=\"1\" valign=\"top\" style=\"padding: 4px; background-color: #e7e7e7;\">";
	
			print "<table>";
			print "<tr><td>";
			
			// submit button	
			$structure = NULL;
			$structure["fieldname"]		= "submit";
			$structure["type"]		= "submit";
			$structure["defaultvalue"]	= "Apply Options";
			$form->add_input($structure);

			$form->render_field("submit");

			print "</form>";
			print "</td>";


			print "<td>";


			/*
				Include a reset button - this reset button is an independent form
				which passes any required fixed options and also a reset option back to the page.

				The load_options_form function then detects this reset value and erases the session
				data for the options belonging to this table, resetting the options form to the original
				defaults.
			*/

			// start the form
			print "<form method=\"get\" class=\"form_standard\">";
			
			$form = New form_input;
			$form->formname = "reset";
			$form->language = $this->language;

			// include page name
			$structure = NULL;
			$structure["fieldname"] 	= "page";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= $_GET["page"];
			$form->add_input($structure);
			$form->render_field("page");

			// include any other fixed options
			foreach (array_keys($this->option) as $fieldname)
			{
				$structure = NULL;
				$structure["fieldname"]		= $fieldname;
				$structure["type"]		= "hidden";
				$structure["defaultvalue"]	= $this->option[$fieldname];
				$form->add_input($structure);
				$form->render_field($fieldname);
			}


			// flag as the reset form
			$structure = NULL;
			$structure["fieldname"] 	= "reset";
			$structure["type"]		= "hidden";
			$structure["defaultvalue"]	= "yes";
			$form->add_input($structure);
			$form->render_field("reset");
		
			$structure = NULL;
			$structure["fieldname"]		= "submit";
			$structure["type"]		= "submit";
			$structure["defaultvalue"]	= "Reset Options";
			$form->add_input($structure);

			$form->render_field("submit");

			
			print "</form></td>";
			print "</tr></table>";

				
		print "</td>";
		print "</tr>";



		// end of structure table
		print "</table>";

		// end of border table
		print "</td></tr></table><br>";
		print "</div>";

		// auto-hide options at startup
		if (!isset($_SESSION["form"][$this->journalname]["custom_options_active"]))
		{
			if ($_SESSION["user"]["shrink_tableoptions"] == "on")
			{
				print "<script type=\"text/javascript\">";
				print "obj_hide('". $this->journalname ."_form');";
				print "</script>";
			}
		}


	}

	
} // end class journal_display




/*
	class journal_input

	Provides functions to render input or update forms for journal entries, which
	are then used by the rest of the application for all journals.
*/
class journal_input extends journal_base
{
	var $form_obj;		// form object
	

	/*
		journal_input()

		Class Contructor
	*/
	function journal_input()
	{
		// init the form object
		$this->form_obj = New form_input;
	}



	/*
		render_text_form()

		Displays a form for creating or editing a journal.
		
		If $this->structure["id"] has been defined, this form will be an edit form.

		Return codes:
		0	failure - journal id invalid or locked or some unknown problem occured
		1	success
	*/
	function render_text_form()
	{
		log_debug("journal_input", "Executing render_text_form()");


		if ($this->structure["id"])
		{
			// check if ID is valid and exists
			switch ($this->verify_journalid())
			{
				case 0:
					print "<p><b>The selected journal id - ". $this->structure["id"] ." - is not valid.</b></p>";
					return 0;
				break;

				case 1:
					$mode = "edit";
				break;

				case 2:
					print "<p><b>Sorry, you can no longer edit this journal entry, it has now been locked.</b></p>";
					return 0;
				break;

				default:
					log_debug("journal_input", "Unexpected output from verify_journalid function");
				break;
			}
		}
		else
		{
			$mode = "add";
		}

		

		/*
			Define form structure
		*/
		$this->form_obj->formname = "journal_edit";
		$this->form_obj->language = $this->language;

		$this->form_obj->action = $this->structure["form_process_page"];
		$this->form_obj->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "title";
		$structure["type"]		= "input";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["width"]	= "600";
		$this->form_obj->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "content";
		$structure["type"]		= "textarea";
		$structure["options"]["req"]	= "yes";
		$structure["options"]["width"]	= "600";
		$structure["options"]["height"]	= "100";
		$this->form_obj->add_input($structure);
		

		// hidden values
		$structure = NULL;
		$structure["fieldname"] 	= "id_journal";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["id"];
		$this->form_obj->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "id_custom";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["customid"];
		$this->form_obj->add_input($structure);
	
		$structure = NULL;
		$structure["fieldname"] 	= "type";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "text";
		$this->form_obj->add_input($structure);	
		
		$structure = NULL;
		$structure["fieldname"] 	= "action";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "edit";
		$this->form_obj->add_input($structure);	


		
		

		// submit button
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";

		if ($mode == "edit")
		{
			$structure["defaultvalue"]	= "Edit Journal Entry";
		}
		else
		{
			$structure["defaultvalue"]	= "Create Journal Entry";
		}
		$this->form_obj->add_input($structure);
		

		// define subforms
		$this->form_obj->subforms["journal_edit"]	= array("title", "content");
		$this->form_obj->subforms["hidden"]		= array("id_journal", "id_custom", "type", "action");
		$this->form_obj->subforms["submit"]		= array("submit");
		
		// load data
		if ($mode == "edit")
		{
			$this->form_obj->sql_query = "SELECT title, content FROM `journal` WHERE id='". $this->structure["id"] ."'";
			$this->form_obj->load_data();
		}
		else
		{
			$this->form_obj->load_data_error();
		}

		// display the form
		$this->form_obj->render_form();
	}



	/*
		render_file_form()

		Displays a form for creating or editing a file uploaded to the journal.
		
		If $this->structure["id"] has been defined, this form will be an edit form.

		Return codes:
		0	failure - journal id invalid or locked or some unknown problem occured
		1	success
	*/
	function render_file_form()
	{
		log_debug("journal_input", "Executing render_file_form()");


		if ($this->structure["id"])
		{
			// check if ID is valid and exists
			switch ($this->verify_journalid())
			{
				case 0:
					print "<p><b>The selected journal id - ". $this->structure["id"] ." - is not valid.</b></p>";
					return 0;
				break;

				case 1:
					$mode = "edit";
				break;

				case 2:
					print "<p><b>Sorry, you can no longer edit this journal entry, it has now been locked.</b></p>";
					return 0;
				break;

				default:
					log_debug("journal_input", "Unexpected output from verify_journalid function");
				break;
			}
		}
		else
		{
			$mode = "add";
		}

		

		/*
			Define form structure
		*/
		$this->form_obj->formname = "journal_edit";
		$this->form_obj->language = $this->language;

		$this->form_obj->action = $this->structure["form_process_page"];
		$this->form_obj->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "upload";
		$structure["type"]		= "file";
		$structure["options"]["req"]	= "yes";
		$this->form_obj->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "content";
		$structure["type"]		= "textarea";
		$structure["options"]["width"]	= "600";
		$structure["options"]["height"]	= "100";
		$this->form_obj->add_input($structure);
		

		// hidden values
		$structure = NULL;
		$structure["fieldname"] 	= "id_journal";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["id"];
		$this->form_obj->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "id_custom";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["customid"];
		$this->form_obj->add_input($structure);
	
		$structure = NULL;
		$structure["fieldname"] 	= "type";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "file";
		$this->form_obj->add_input($structure);	
		
		$structure = NULL;
		$structure["fieldname"] 	= "action";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "edit";
		$this->form_obj->add_input($structure);	


		
		// submit button
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";

		if ($mode == "edit")
		{
			$structure["defaultvalue"]	= "Save Changes";
		}
		else
		{
			$structure["defaultvalue"]	= "Upload file to Journal";
		}
		$this->form_obj->add_input($structure);
		

		// define subforms
		$this->form_obj->subforms["hidden"]		= array("id_journal", "id_custom", "type", "action");
		$this->form_obj->subforms["journal_edit"]	= array("upload", "content");
		$this->form_obj->subforms["submit"]		= array("submit");
		
		// load data
		if ($mode == "edit")
		{
			$this->form_obj->sql_query = "SELECT content FROM `journal` WHERE id='". $this->structure["id"] ."'";
			$this->form_obj->load_data();
		}
		else
		{
			$this->form_obj->load_data_error();
		}

		// display the form
		$this->form_obj->render_form();
	}




	/*
		render_delete_form()

		Displays a form for deleting a journal entry.
		
		Return codes:
		0	failure - journal id invalid or locked or some unknown problem occured
		1	success
	*/
	function render_delete_form()
	{
		log_debug("journal_input", "Executing render_text_form()");


		if ($this->structure["id"])
		{
			// check if ID is valid and exists
			switch ($this->verify_journalid())
			{
				case 0:
					print "<p><b>The selected journal id - ". $this->structure["id"] ." - is not valid.</b></p>";
					return 0;
				break;

				case 1:
					$mode = "edit";
				break;

				case 2:
					print "<p><b>Sorry, you can no longer edit this journal entry, it has now been locked.</b></p>";
					return 0;
				break;

				default:
					log_debug("journal_input", "Unexpected output from verify_journalid function");
				break;
			}
		}
		else
		{
			$mode = "add";
		}

		

		/*
			Define form structure
		*/
		$this->form_obj->formname = "journal_delete";
		$this->form_obj->language = $this->language;

		$this->form_obj->action = $this->structure["form_process_page"];
		$this->form_obj->method = "post";
		

		// general
		$structure = NULL;
		$structure["fieldname"] 	= "title";
		$structure["type"]		= "text";
		$this->form_obj->add_input($structure);
		

		// hidden values
		$structure = NULL;
		$structure["fieldname"] 	= "id_journal";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["id"];
		$this->form_obj->add_input($structure);
		
		$structure = NULL;
		$structure["fieldname"] 	= "id_custom";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->structure["customid"];
		$this->form_obj->add_input($structure);
	
		$structure = NULL;
		$structure["fieldname"] 	= "action";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= "delete";
		$this->form_obj->add_input($structure);	

		$structure = NULL;
		$structure["fieldname"] 	= "type";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= @$this->structure["type"];
		$this->form_obj->add_input($structure);	
		

		// submit button
		$structure = NULL;
		$structure["fieldname"] 	= "submit";
		$structure["type"]		= "submit";
		$structure["defaultvalue"]	= "Confim Deletion";
		$this->form_obj->add_input($structure);
		

		// define subforms
		$this->form_obj->subforms["journal_edit"]	= array("title");
		$this->form_obj->subforms["hidden"]		= array("id_journal", "id_custom", "type", "action");
		$this->form_obj->subforms["submit"]		= array("submit");
		
		// load data
		$this->form_obj->sql_query = "SELECT type, title, content FROM `journal` WHERE id='". $this->structure["id"] ."'";
		$this->form_obj->load_data();

		// display the form
		$this->form_obj->render_form();
	}



	

} // end of class journal_input



/*
	class journal_process

	Provides functions for processing data to create or update journal entries.
*/
class journal_process extends journal_base
{

	/*
		journal_process()

		Class Contructor
	*/
	function journal_process()
	{
		// sql query
		$this->sql_obj = New sql_query;	

		// defaults
		$this->structure["userid"]	= $_SESSION["user"]["id"];
		$this->structure["timestamp"]	= time();
	}


	/*
		check_lock

		Check if the requested journal entry is locked or writable.

		0	Journal entry can be written to
		1	Journal entry is locked, no changes permitted
	*/
	function check_lock()
	{
		log_write("debug", "journal_process", "Executing check_lock()");


		if ($this->structure["id"])
		{
			// make sure the user is permitted to adjust the journal entry
			if ($this->structure["userid"] != $_SESSION["user"]["id"])
			{
				log_write("error", "journal_process", "You do not have permissions to update this journal entry");
				return 1;
			}

	
			// make sure the journal entry is valid and is not locked
			switch ($this->verify_journalid())
			{
				case 0:
					log_write("error", "journal_process", "The requested journal entry is invalid");
					return 1;
				break;

				case 1:
					// unlocked
					return 0;
				break;

				case 2:
					log_write("error", "journal_process", "The requested journal entry is now locked, and can not be updated.");
					return 1;
				break;

				default:
					log_write("error", "journal_process", "Unexpected error with verify_journalid function.");
					return 1;
				break;
			}
		}
		else
		{
			// no entry selected, return unlocked
			return 0;
		}
	}




	/*
		process_form_input()

		Reads in all the form input and puts it into the structure array
	*/
	function process_form_input()
	{
		log_debug("journal_process", "Executing process_form_input()");
	
		$this->structure["action"]	= @security_form_input_predefined("any", "action", 1, "");
		$this->structure["type"]	= @security_form_input_predefined("any", "type", 1, "");
		$this->structure["title"]	= @security_form_input_predefined("any", "title", 0, "");
		$this->structure["content"]	= @security_form_input_predefined("any", "content", 0, "");
		$this->structure["customid"]	= @security_form_input_predefined("int", "id_custom", 0, "");
		$this->structure["id"]		= @security_form_input_predefined("int", "id_journal", 0, "");


		if ($this->structure["type"] == "text" && $this->structure["action"] != "delete")
		{
			// need title field for text entries
			if (!$this->structure["title"])
			{
				$_SESSION["error"]["message"][]		= "You must provide a title";
				$_SESSION["error"]["title-error"]	= 1;
			}

			// need content field for text entries
			if (!$this->structure["content"])
			{
				$_SESSION["error"]["message"][]		= "You must provide some content";
				$_SESSION["error"]["content-error"]	= 1;
			}
		}


		// file upload - get the temporary name
		// we still need to security check it, otherwise someone could pull a nasty exploit using a specially name file. :-)
		if ($this->structure["type"] == "file")
		{
			// a file might not have been uploaded - we want to allow users to be able
			// to change the notes on file uploads, without having to upload the file again.
			if ($_FILES["upload"]["size"] < 1)
			{
				// nothing has been uploaded
				if (!$this->structure["id"])
				{
					// this is a new upload - a file MUST be provided for the first upload
					// use the file_storage class to perform error handling.
					$file_obj = New file_storage;
					$file_obj->verify_upload_form("upload");
				}
				else
				{
					// no file has been uploaded. We better get the old title so we don't lose it
					$this->structure["title"] = sql_get_singlevalue("SELECT title as value FROM journal WHERE id='". $this->structure["id"] ."' LIMIT 1");
				}
			}
			else
			{
				// a file has been uploaded - perform verification of the file, if there
				// are any problems, the function will raise errors.
				$file_obj = New file_storage;
				$file_obj->verify_upload_form("upload");

				// set the title of the journal entry to the filename
				$this->structure["title"] = @security_script_input("/^[\S\s]*$/", $_FILES["upload"]["name"]);
			}

		}
	} // end of process_form_input




	/*
		action_create()

		Create a new journal entry. Usually executed by $this->action_update()

		Return codes:
		0	failure
		#	success - returns ID of journal entry
	*/
	function action_create()
	{
		log_debug("journal_process", "Executing action_create()");

		// insert place holder into DB
		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO `journal` (journalname, type, timestamp) VALUES ('". $this->journalname ."', '". $this->structure["type"] ."', '". $this->structure["timestamp"] ."')";

		if ($sql_obj->execute())
		{
			$this->structure["id"] = $sql_obj->fetch_insert_id();
			
			return $this->structure["id"];
		}
	
		return 0;
	}



	/*
		action_update()

		Update a journal entry based on the ID in $this->structure["id"]

		Return codes:
		0	failure
		1	success
	*/
	function action_update()
	{
		log_debug("journal_process", "Executing action_update()");


		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			If no ID supplied, create a new journal entry first
		*/
		if (empty($this->structure["id"]))
		{
			$mode = "create";

			if (!$this->action_create())
			{
				return 0;
			}
		}
		else
		{
			$mode = "update";
		}


		/*
			Handle some optional fields
		*/

		if (!isset($this->structure["content"]))
		{
			$this->structure["content"] = "";
		}

		if (!isset($this->structure["userid"]))
		{
			// automated system user
			$this->structure["userid"] = 0;
		}


		/*
			Update journal record
		*/

		$sql_obj->string	= "UPDATE `journal` SET "
					."userid='". $this->structure["userid"] ."', "
					."customid='". $this->structure["customid"] ."', "
					."timestamp='". $this->structure["timestamp"] ."', "
					."title='". $this->structure["title"] ."', "
					."content='". $this->structure["content"] ."' "
					."WHERE id='". $this->structure["id"] ."' LIMIT 1";

		$sql_obj->execute();



		/*
			If journal entry is a file, upload the data

			TODO: this currently only works for form uploads
		*/
		if ($this->structure["type"] == "file")
		{
			if (isset($_FILES["upload"]["name"]))
			{
				log_write("debug", "journal_process", "Uploading file against journal entry from POST form data");


				// output file data
				$file_obj			= New file_storage;
				$file_obj->data["type"]		= "journal";
				$file_obj->data["customid"]	= $this->structure["id"];

				if ($file_obj->load_data_bytype())
				{
					log_debug("journal_process", "Old file exists, will overwrite.");
				
					// unset the title variable - otherwise the action_update_form function will retain the existing title
					$file_obj->data["file_name"] = NULL;
				}
				else
				{
					log_debug("journal_process", "No previous file exists, performing clean upload.");
				}

						
				// call the upload function
				if (!$file_obj->action_update_form("upload"))
				{
					log_write("error", "journal_process", "Unable to upload file for journal entry id ". $this->structure["id"] . "");
				}
			}
			else
			{
				// not uploading
				log_write("debug", "journal_process", "No file POST data provided, not uploading file to journal - assuming manual DB upload");
			}
		}


		
		/*
			Commit
		*/
		if (error_check())
		{
			$sql_obj->trans_rollback();
			
			log_write("error", "journal_process", "An error occured whilst updating the journal.");
			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			if ($this->structure["type"] != "event")
			{
				if ($mode == "update")
				{
					log_write("notification", "journal_process", "Journal entry updated successfully.");
				}
				else
				{
					log_write("notification", "journal_process", "New record added to the journal successfully.");
				}
			}

			return 1;
		}

		return 0;
	}



	/*
		action_delete()

		Deletes a journal entry based on the ID in $this->structure["id"]

		Return codes:
		0	failure
		1	success
	*/
	function action_delete()
	{
		log_debug("journal_process", "Executing action_delete()");


		/*
			Start Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();

		
		/*
			Delete files (if applicable)
		*/
		if ($this->structure["type"] == "file")
		{
			$file_obj			= New file_storage;
			$file_obj->data["type"]		= "journal";
			$file_obj->data["customid"]	= $this->structure["id"];

			$file_obj->load_data_bytype();
			$file_obj->action_delete();
		}


		/*
			Delete journal record
		*/
		$sql_obj->string	= "DELETE FROM `journal` WHERE id='". $this->structure["id"] ."' LIMIT 1";
		$sql_obj->execute();



		/*
			Commit
		*/
		if (error_check())
		{
			log_write("error", "journal_process", "An error occured preventing the journal record from being deleted");

			$sql_obj->trans_rollback();
			return 0;
		}
		else
		{
			log_write("notification", "journal_process", "Journal record cleanly removed.");
			$sql_obj->trans_commit();
			return 1;
		}

		return 0;
	}


	/*
		action_lock()

		Locks a journal entry based on the ID in $this->structure["id"]

		Return codes:
		0	failure
		1	success
	*/
	function action_lock()
	{
		log_debug("journal_process", "Executing action_lock()");


		/*
			Lock journal record
		*/
		$sql_obj		= New sql_query;
		$sql_obj->string	= "UPDATE `journal` SET locked='1' WHERE id='". $this->structure["id"] ."' LIMIT 1";

		if (!$sql_obj->execute())
		{
			return 0;
		}

		return 1;

	} // end of action_lock

	

} // end of the journal_process class



/*
	FUNCTIONS
*/


/*
	journal_quickadd_event($journalname, $customid, $message)

	Allows the simple addition of event journal messages - this function is used
	by most of the processing files to log changes.

	Return codes:
	0	failure
	1	success
*/
function journal_quickadd_event($journalname, $customid, $message)
{
	// log to journal
	$journal = New journal_process;
		
	$journal->prepare_set_journalname($journalname);
	$journal->prepare_set_customid($customid);
	$journal->prepare_set_type("event");
		
	$journal->prepare_set_title($message);

	return $journal->action_update();
}



/*
	journal_delete_entire($journalname, $customid)

	Deletes all entries for the provided journal with the matching customid - this function
	us used when users delete items which have a journal belonging to them (eg: customers, invoices, etc)
*/
function journal_delete_entire($journalname, $customid)
{
	log_debug("inc_journal", "Executing journal_delete_entire($journalname, $customid)");
	
	
	// start transaction
	$sql_obj = New sql_query;
	$sql_obj->trans_begin();



	/*
		Run though the journal items and delete any attached files
	*/

	$sql_journal_obj		= New sql_query;
	$sql_journal_obj->string	= "SELECT id, type FROM journal WHERE journalname='$journalname' AND customid='$customid' AND type='file'";
	$sql_journal_obj->execute();

	if ($sql_journal_obj->num_rows())
	{
		$sql_journal_obj->fetch_array();

		foreach ($sql_journal_obj->data as $data)
		{
			$file_obj			= New file_storage;
			$file_obj->data["type"]		= "journal";
			$file_obj->data["customid"]	= $data["id"];

			$file_obj->load_data_bytype();
			$file_obj->action_delete();
		}
	}


	/*
		Run delete on all the journal entries
	*/

	$sql_journal_obj		= New sql_query;
	$sql_journal_obj->string	= "DELETE FROM journal WHERE journalname='$journalname' AND customid='$customid'";
	$sql_journal_obj->execute();



	// commit
	if (error_check())
	{
		$sql_obj->trans_rollback();
		return 0;
	}
	else
	{
		$sql_obj->trans_commit();
		return 1;
	}
}


?>
