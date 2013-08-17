<?php
/*
	forms.php

	Provides classes/functions used for generating and processing forms.

	class form_input	Functions for UI component of forms.

	standalone functions:

	function form_helper_prepare_dropdownfromdb

*/

class form_input
{
	var $formname;			// name of the form - needs to be unique per form
	var $language = "en_us";	// language to use for the form labels.
	
	var $action;			// page to process the data
	var $method;			// method to use - POST or GET

	var $sql_query;			// SQL query used to fetch data
	
	var $subforms;			// associative array of sub form titles and contents
	var $subforms_grouped;		// grouped subform items
	var $structure;			// array structure of all variables and options
	var $actions;			// array structure of javascript actions

	var $sessionform;		// If this value is set, the value is the name of the session variable
					// holding form information, which can then be manipulated by multiple
					// different forms.
					//
					// This enables a whole bunch of features for handling session forms in
					// the form functions.
					//
					// For full details about developing session-based forms, please refer
					// to the developers guide.


	/*
		add_input ($option_array)

		This function adds a new field to the form, based on the information in the array. Please refer
		to the render_field array for details on the array structure.
	*/
	function add_input ($option_array)
	{
		log_debug("form", "Executing add_input for field ". $option_array["fieldname"]);
		
		if (!$option_array["fieldname"] || !$option_array["type"])
			print "Warning: missing fieldname and/or type from add_input function<br>";

		// add data to the structure
		$this->structure[ $option_array["fieldname"] ] = $option_array;
		
	}

	/*
		add_action

		Configures a javascript action to trigger when a specific field value is selected. Currently only supports radio fields.
		
		Values
		trigger_field		Field to act as the trigger
		trigger_value		Value to trigger on
		action_type		Action to type - "show", "hide"
		target_field		Field to apply the action to.
	*/
	function add_action ( $trigger_field, $trigger_value, $target_field, $action_type )
	{
		log_debug("form", "Executing add_action ( $trigger_field, $trigger_value, $target_field, $action_type )");

		$this->actions[ $trigger_field ][ $target_field ][ $trigger_value ] = $action_type;
	}



	/*
		load_data()

		Wrapper function which decides upon the suitable way to fill
		the form with data from one of 3 options:
		- database
		- error data
		- session data (if session-based form is active)
	*/
	function load_data()
	{
		log_debug("form", "Executing load_data()");

		// error data has highest perference
		if (isset($_SESSION["error"]["form"][$this->formname]))
		{
			return $this->load_data_error();
		}

		// if enabled, and data exists, fetch information from session form
		if (isset($this->sessionform))
		{
			if (isset($_SESSION["form"][$this->sessionform]))
			{
				return $this->load_data_session();
			}
		}
	
		// if a SQL query has been provided, fetch information from SQL DB
		if (isset($this->sql_query))
		{
			return $this->load_data_sql();
		}


		// failure
		log_debug("form", "Error: No valid data avaliable from any method for load_data() function");
		return 0;
	}


	/*
		load_data_error()

		Imports data from the error arrays into the form structure
	*/
	function load_data_error()
	{
		log_debug("form", "Executing load_data_error()");
	
		if (isset($_SESSION["error"]["form"][$this->formname]))
		{

			foreach (array_keys($this->structure) as $fieldname)
			{
				/*
					We now import the data returned by the field for any editable fields
					and also for any text/message/hidden fields which have recieved data
					from the form.

					If the field is a text/submit/message/hidden field with no data returned, we
					just ignore it.

					We always ignore submit buttons.
				*/

				switch ($this->structure[$fieldname]["type"])
				{
					case "submit":
						// do nothing for submit buttons
					break;

					case "message":
					case "text":
					case "hidden":

						// only set the field if a value has been provided - ie: don't set to blank for any reason
						if (isset($_SESSION["error"]["$fieldname"]) && ($_SESSION["error"]["$fieldname"] != ''))
						{
							$this->structure[$fieldname]["defaultvalue"] = stripslashes($_SESSION["error"][$fieldname]);
						}
					
					break;

					default:
						// set the default value
				
						if (isset($_SESSION["error"]["$fieldname"]))
						{
							$this->structure[$fieldname]["defaultvalue"] = stripslashes($_SESSION["error"][$fieldname]);
						}
					break;
				}
			}
		}
		else
		{
			log_debug("form", "No error data to import.");
		}
	
		return 1;
	}


	/*
		load_data_object
		
		Imports data from the provided associative array, mapping $array[ fieldname ] to the names of
		the form fields.

		Fields
		associative_array

		Returns
		0	Failure
		1	Success
	*/
	function load_data_object($array)
	{
		log_debug("form", "Executing load_data_object()");
	
		
		foreach (array_keys($this->structure) as $fieldname)
		{
			/*
				We now import the data returned by the field for any editable fields
				and also for any text/message/hidden fields which have recieved data
				from the form.

				If the field is a text/submit/message/hidden field with no data returned, we
				just ignore it.

				We always ignore submit buttons.
			*/

			switch ($this->structure[$fieldname]["type"])
			{
				case "submit":
					// do nothing for submit buttons
				break;

				case "message":
				case "text":
				case "hidden":

					// only set the field if a value has been provided - ie: don't set to blank for any reason
					if (isset($array["$fieldname"]) && ($array["$fieldname"] != ''))
					{
						$this->structure[$fieldname]["defaultvalue"] = stripslashes($array[$fieldname]);
					}
				
				break;

				default:
					// set the default value
					if (isset($array["$fieldname"]))
					{
						$this->structure[$fieldname]["defaultvalue"] = stripslashes($array[$fieldname]);
					}
				break;
			}
		}

		return 1;

	} // end of load_data_object
	

	/*
		load_data_session()

		Imports data from the session arrays into the form structure. This is used by session-based forms.
	*/
	function load_data_session()
	{
		log_debug("form", "Executing load_data_session()");
	
		if ($_SESSION["form"][$this->sessionform])
		{
			foreach (array_keys($this->structure) as $fieldname)
			{
				/*
					We now import the data returned by the field for any editable fields
					and also for any text/message/hidden fields which have recieved data
					from the form.

					If the field is a text/submit/message/hidden field with no data returned, we
					just ignore it.

					We always ignore submit buttons.
				*/

				switch ($this->structure[$fieldname]["type"])
				{
					case "submit":
						// do nothing for submit buttons
					break;

					case "message":
					case "text":
					case "hidden":

						// only set the field if a value has been provided - ie: don't set to blank for any reason
						if ($_SESSION["form"][$this->sessionform]["$fieldname"])
						{
							$this->structure[$fieldname]["defaultvalue"] = stripslashes($_SESSION["form"][$this->sessionform][$fieldname]);
						}
					
					break;

					default:
						// set the default value
						$this->structure[$fieldname]["defaultvalue"] = stripslashes($_SESSION["form"][$this->sessionform][$fieldname]);
					break;
				}
			}
		}
		else
		{
			log_debug("form", "No session data to import.");
		}
	
		return 1;
	}


	/*
		load_data_sql()

		Loads data from MySQL to fill in the default values for the defined structure.
		Note: All add_input calls need to be made before this command it run

	*/
	function load_data_sql()
	{
		log_debug("form", "Executing load_data_sql()");

		// execute the SQL query
		$sql_obj		= New sql_query;
		$sql_obj->string	= $this->sql_query;
		$sql_obj->execute();
		
		$this->data_num_rows = $sql_obj->num_rows();
		if (!$this->data_num_rows)
		{
			return 0;
		}
		else
		{
			$sql_obj->fetch_array();
			
			foreach ($sql_obj->data as $data)
			{
				// insert the data into the structure value as the default value
				foreach (array_keys($this->structure) as $fieldname)
				{
					if (isset($data[$fieldname]))
					{
						$this->structure[$fieldname]["defaultvalue"] = $data[$fieldname];
					}
				}
			}

			return $this->data_num_rows;
		}

	}



	/*
		render_sessionform_messagebox()

		Displays a message box informing the user about unsaved information in the session form, and
		create a erase button.
	*/
	function render_sessionform_messagebox()
	{
		log_debug("form", "Executing render_sessionform_messagebox()");
		
		// display message box about the form
		print "<table width=\"100%\" class=\"table_highlight_red\">";
		print "<tr>";
		print "<td colspan=\"2\"><p>You have unsaved changes to this form. Either save them using the buttons at the bottom of the form, or use the clear button to reset the form.</p></td>";
		print "</tr><tr>";
		print "<td><input type=\"submit\" name=\"action\" value=\"Clear Form\"></td>";
		print "</tr>";
		print "</table><br>";
	}




	/*
		render_row ($fieldname)

		Displays a table row, including the field itself.
	*/
	function render_row ($fieldname)
	{
		log_debug("form", "Executing render_row($fieldname)");
		
		//create array of classes
		$class_array = array();
		if(isset($this->structure[$fieldname]["options"]["css_row_class"]))
		{		
			if (is_array($this->structure[$fieldname]["options"]["css_row_class"]))
			{
				array_merge($class_array, $this->structure[$fieldname]["options"]["css_row_class"]);
			}
			else
			{
				$class_array[] = $this->structure[$fieldname]["options"]["css_row_class"];
			}
		}
		
		if (isset($_SESSION["error"]["$fieldname-error"]))
		{
			$class_array[] = "form_error";
		}
		
		if (isset($this->structure[$fieldname]["options"]["css_row_id"]))
		{
			print "<tr id=\"". $this->structure[$fieldname]["options"]["css_row_id"] ."\" ";
		}
		else
		{
			print "<tr id=\"$fieldname\" ";
		}
		
		if (count($class_array) > 0)
		{
			print "class=\"";
			foreach ($class_array as $class)
			{
				print $class ." ";
			}
			print "\"";
		}
		print ">";
	
		// if the fieldname has experienced an error, we want to highlight the field
//		if (isset($_SESSION["error"]["$fieldname-error"]))
//		{
//			print "<tr class=\"form_error\">";
//		}
//		elseif ($this->structure[$fieldname]["options"]["css_row_class"])
//		{
//			print "<tr class=\"". $this->structure[$fieldname]["options"]["css_row_class"] ."\">";
//		}
//		elseif ($this->structure[$fieldname]["options"]["css_row_id"])
//		{
//			print "<tr id=\"". $this->structure[$fieldname]["options"]["css_row_id"] ."\">";
//		}
//		else
//		{
//			print "<tr id=\"$fieldname\">";
//		}

		switch ($this->structure[$fieldname]["type"])
		{
			case "submit":
				// special submit button row
				print "<td width=\"100%\" colspan=\"2\">";

				// only display the message below if actually required
				$req = 0;
				foreach (array_keys($this->structure) as $tmp_fieldname)
				{
					if (isset($this->structure[$tmp_fieldname]["options"]["req"]))
					{
						$req = 1;
					}
				}

				if ($req)
					print "<p><i>Please note that all fields marked with \"*\" must be filled in.</i></p>";

					
				$this->render_field($fieldname);
				print "</td>";
			break;

			case "message":
				// messages
				print "<td width=\"100%\" colspan=\"2\">";
				$this->render_field($fieldname);
				print "</td>";
			break;


			default:
			
				if (isset($this->structure[$fieldname]["options"]["no_fieldname"]))
				{
					if (isset ($this->structure[$fieldname]["options"]["no_shift"]))
					{
						//leave left column blank
						print "<td width=\"30%\" valign=\"top\">&nbsp;</td>";
						print "<td width=\"70%\">";
							$this->render_field($fieldname);
						print "</td>";
					}
					else
					{
						// display the form field, but do no display the column for the fieldname
						print "<td colspan=\"2\" width=\"100%\">";
	
						$this->render_field($fieldname);
	
						print "</td>";
					}
				}
				else
				{
					// field name
					if (!isset($this->structure[$fieldname]["options"]["no_translate_fieldname"]))
					{
						$translation = language_translate_string($this->language, $fieldname);
					}
					else
					{
						// translation disabled
						$translation = $fieldname;
					}


					print "<td width=\"30%\" valign=\"top\">";
					print $translation;
					
					if (isset($this->structure[$fieldname]["options"]["req"]))
					{
						print " *";
					}
					
					print "</td>";

					// display form input field
					print "<td width=\"70%\">";

					$this->render_field($fieldname);
		
					print "</td>";
				}

			break;

		}

		print "</tr>";	
	}


	/*
		render_field ($fieldname)

		Displays the specified field, based on the information in the structure table.

		The information used should first be added by the add_input($option_array) function,
		with the following syntax for the option array:

			$option_array["fieldname"]		Name of the field
			$option_array["type"]			Type of form input

				input		- standard input box
				money		- standard input box with formating for money entry
				password	- password input box
				hidden		- hidden field
				text		- text only display, with hidden field as well

				textarea	- space for blocks of text
				
				date		- special date field - splits a timestamp into 3 DD/MM/YYYY fields
				hourmins	- splits the specified number of seconds into hours, and minutes
				timestamp_date	- provides a date DD/MM/YYYY form field, but converts to timestamp

				checkbox	- checkboxs (tick boxes)
				radio		- radio buttons
				dropdown 	- dropdown/select boxes

				submit		- submit button
				message		- prints the defaultvalue - used for inserting message into forms

				file		- file upload box

			
			$option_array["defaultvalue"]		Default value (if any)
			$option_array["options"]
						["no_translate_fieldname"]	Set to "yes" to disable language translation for field names
						["no_fieldname"]		Do not render a field name and shift the data left by 1 column
						["no_shift"]			For use with no_fieldname option- keeps field in second column
						["req"]				Set to "yes" to mark the field as being required
						["max_length"]			Max length for input types
						["wrap"]			Enable/disable wrapping for textarea boxes (set to either on or off)
						["width"]			Width of field object.
						["height"]			Height of field object.
						["label"]			Label field for checkboxes or i to use instead of a translation
						["prelabel"]			Label field displayed before the field
						["noselectoption"]		Set to yes to prevent the display of a "select:" heading in dropdowns
										and to automatically select the first entry in the list.
										^ - OBSOLETE: this option should be replaced by autoselect option		
						["autoselect"]			Enabling this option will cause a radio or dropdown with just a single
										entry to auto-select the single entry.
						["nohidden"]			Disables the hidden field on text fields
 						["css_row_class"]		Set the CSS class to a custom option for the rendered table row.
 						["css_row_id"]			Set the CSS id to a custom option for the rendered table row.
											(note: by default, table rows have their ID set to the name of the fieldname)
						["css_field_class"]		Set the CSS class of a field
						["search_filter"]		Enable/disable the optional text box to allow search/ filtering of a dropdown
 						["help"]			In-field help message for input and textarea types. Is ignored if defaultvalue is set.
 						["disabled"]			Disables the field if set to yes
						["autofill"]			Set to a value to fill the input field with upon being selected the first time.
						["autocomplete"]		Autocomplete option for input fields, it will display a dropdown that filters
											as the user types, using the Jquery Autocomplete UI functions. Options are
											currenly "sql" to use a SQL query set in ["options"]["autocomplete_sql"] and 
											returning the column "label" to be used for the dropdown.
						["nolabel"]			Set to true to remove labels from checkboxes, etc


			$option_array["values"] = array();			Array of values - used for radio or dropdown type fields
			$option_array["translations"] = array();		Associate array used for labeling the values in radio or dropdown type fields
		
	*/
	function render_field ($fieldname)
	{
		log_debug("form", "Executing render_field($fieldname)");
		
		$helpmessagestatus = "false";
		
		switch ($this->structure[$fieldname]["type"])
		{
			case "input":
				// set default size
				if (!isset($this->structure[$fieldname]["options"]["width"]))
				{
					$this->structure[$fieldname]["options"]["width"] = 250;
				}
		
				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				// display
				print "<input id=\"$fieldname\" name=\"$fieldname\" ";

				$css_field_class = array();

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					print "value=\"". htmlentities($this->structure[$fieldname]["defaultvalue"], ENT_QUOTES, "UTF-8") ."\" ";
				}
 				elseif (isset($this->structure[$fieldname]["options"]["help"]))
 				{
 					print "value=\"". $this->structure[$fieldname]["options"]["help"] ."\" ";
 					$helpmessagestatus = "true";
					$css_field_class[]  = "helpmessage";
 				}

				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					$css_field_class[] = $this->structure[$fieldname]["options"]["css_field_class"];
				}			
 				
				if (!empty($css_field_class))
				{
					print "class=\"";

					foreach ($css_field_class as $css)
					{
						print $css ." ";
					}

					print "\" ";
				}


				if (isset($this->structure[$fieldname]["options"]["max_length"]))
					print "maxlength=\"". $this->structure[$fieldname]["options"]["max_length"] ."\" ";
					
				if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
				
				print "style=\"width: ". $this->structure[$fieldname]["options"]["width"] ."px;\">";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}

				print "<input type=\"hidden\" name=\"".$fieldname."_helpmessagestatus\" value=\"".$helpmessagestatus."\">";
				
				if (isset($this->structure[$fieldname]["options"]["autofill"]))
				{
					print "<input type=\"hidden\" name=\"".$fieldname."_autofill\" value=\"". $this->structure[$fieldname]["options"]["autofill"] ."\">";
				}
			break;


			case "money":
				// set default size
				if (!isset($this->structure[$fieldname]["options"]["width"]))
				{
					$this->structure[$fieldname]["options"]["width"] = 50;
				}


				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}


				// check where to set the currency symbol
				$position = sql_get_singlevalue("SELECT value FROM config WHERE name='CURRENCY_DEFAULT_SYMBOL_POSITION'");

				if ($position == "before")
				{
					print sql_get_singlevalue("SELECT value FROM config WHERE name='CURRENCY_DEFAULT_SYMBOL'") ." ";
				}
	
		
				// display
				print "<input name=\"$fieldname\" ";

				$css_field_class = array();

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					print "value=\"". htmlentities($this->structure[$fieldname]["defaultvalue"], ENT_QUOTES, "UTF-8") ."\" ";
				}
 				elseif (isset($this->structure[$fieldname]["options"]["help"]))
 				{
 					print "value=\"". $this->structure[$fieldname]["options"]["help"] ."\" ";
 					$helpmessagestatus = "true";
					$css_field_class[]  = "helpmessage";
 				}

				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					$css_field_class[] = $this->structure[$fieldname]["options"]["css_field_class"];
				}			
 				
				if (!empty($css_field_class))
				{
					print "class=\"";

					foreach ($css_field_class as $css)
					{
						print $css ." ";
					}

					print "\" ";
				}
 
				if (isset($this->structure[$fieldname]["options"]["max_length"]))
					print "maxlength=\"". $this->structure[$fieldname]["options"]["max_length"] ."\" ";
					
				if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
				
				print "style=\"width: ". $this->structure[$fieldname]["options"]["width"] ."px;\">";


				if ($position == "after")
				{
					print " ". sql_get_singlevalue("SELECT value FROM config WHERE name='CURRENCY_DEFAULT_SYMBOL'");
				}

				print " ". sql_get_singlevalue("SELECT value FROM config WHERE name='CURRENCY_DEFAULT_NAME'");

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}
				
				print "<input type=\"hidden\" name=\"".$fieldname."_helpmessagestatus\" value=\"".$helpmessagestatus."\">";
			break;


			case "password":
				
				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}


				// set default size
				if (empty($this->structure[$fieldname]["options"]["width"]))
					$this->structure[$fieldname]["options"]["width"] = 250;
		
				// display
				print "<input type=\"password\" name=\"$fieldname\"";

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					print " value=\"". $this->structure[$fieldname]["defaultvalue"]. "\" " ;
				}
				
				if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
					
				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
				}			
					
				print "style=\"width: ". $this->structure[$fieldname]["options"]["width"] ."px;\">";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}
			break;
			
			case "hidden":
			
				if (!isset($this->structure[$fieldname]["defaultvalue"]))
				{
						$this->structure[$fieldname]["defaultvalue"] = '';
				}
				print "<input type=\"hidden\" name=\"$fieldname\" value=\"". $this->structure[$fieldname]["defaultvalue"] ."\">";
			break;

			case "text":
		
				if (!isset($this->structure[$fieldname]["defaultvalue"]))
				{
						$this->structure[$fieldname]["defaultvalue"] = '';
				}
				$translation = language_translate_string($this->language, $this->structure[$fieldname]["defaultvalue"]);

				print "$translation";

				if (!isset($this->structure[$fieldname]["options"]["nohidden"]))
				{
					print "<input type=\"hidden\" name=\"$fieldname\" value=\"". $this->structure[$fieldname]["defaultvalue"] ."\">";
				}
			break;

			case "textarea":
				
				// set default size
				if (!isset($this->structure[$fieldname]["options"]["width"]))
					$this->structure[$fieldname]["options"]["width"] = 300;
					
				if (!isset($this->structure[$fieldname]["options"]["height"]))
					$this->structure[$fieldname]["options"]["height"] = 35;

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				// display
				print "<textarea name=\"$fieldname\" ";
				
				if (isset($this->structure[$fieldname]["options"]["wrap"]))
				{
					print "wrap=\"". $this->structure[$fieldname]["options"]["wrap"] ."\" ";
				}
				
				if ( isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
					
				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
				}			
					
				print "style=\"width: ". $this->structure[$fieldname]["options"]["width"] ."px; height: ". $this->structure[$fieldname]["options"]["height"] ."px;\">";

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					print $this->structure[$fieldname]["defaultvalue"];
				}

				print "</textarea>";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}
			break;

			case "date":
				
				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					if ($this->structure[$fieldname]["defaultvalue"] == "0000-00-00" || $this->structure[$fieldname]["defaultvalue"] == 0)
					{
						$date_a = array("","","");
					}
					else
					{
						$date_a = explode("-", $this->structure[$fieldname]["defaultvalue"]);
					}
				}
				else
				{
						$date_a = array("","","");
				}

				// get the format the date field needs to be shown in
				if (isset($_SESSION["user"]["dateformat"]))
				{
					// fetch from user preferences
					$format = $_SESSION["user"]["dateformat"];
				}
				else
				{
					// user hasn't chosen a default time format yet - use the system default
					$format = sql_get_singlevalue("SELECT value FROM config WHERE name='DATEFORMAT' LIMIT 1");
				}

				switch ($format)
				{
					case "mm-dd-yyyy":
						print "<input name=\"". $fieldname ."_mm\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[1] ."\"> ";
						print "<input name=\"". $fieldname ."_dd\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[2] ."\"> ";
						print "<input name=\"". $fieldname ."_yyyy\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $date_a[0] ."\">";
						print " <i>(mm/dd/yyyy)</i>";
					break;

					case "dd-mm-yyyy":
						print "<input name=\"". $fieldname ."_dd\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[2] ."\"> ";
						print "<input name=\"". $fieldname ."_mm\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[1] ."\"> ";
						print "<input name=\"". $fieldname ."_yyyy\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $date_a[0] ."\">";
						print " <i>(dd/mm/yyyy)</i>";
					break;
					
					case "yyyy-mm-dd":
					default:
						print "<input name=\"". $fieldname ."_yyyy\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $date_a[0] ."\"> ";
						print "<input name=\"". $fieldname ."_mm\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[1] ."\"> ";
						print "<input name=\"". $fieldname ."_dd\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[2] ."\">";
						print " <i>(yyyy/mm/dd)</i>";
					break;
				}

				// TODO: it would be good to have a javascript calender pop-up to use here.


				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}

			break;

			case "timestamp_date":

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				if (empty($this->structure[$fieldname]["defaultvalue"]))
				{
					$date_a = array("","","");
				}
				else
				{
					$date_a = explode("-", date("Y-m-d", $this->structure[$fieldname]["defaultvalue"]));
				}
				
				print "<input name=\"". $fieldname ."_dd\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[2] ."\"> ";
				print "<input name=\"". $fieldname ."_mm\" style=\"width: 25px;\" maxlength=\"2\" value=\"". $date_a[1] ."\"> ";
				print "<input name=\"". $fieldname ."_yyyy\" style=\"width: 50px;\" maxlength=\"4\" value=\"". $date_a[0] ."\">";
				print " <i>(dd/mm/yyyy)</i>";

				// TODO: it would be good to have a javascript calender pop-up to use here.


				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}

			break;

			case "hourmins":
				if (empty($this->structure[$fieldname]["defaultvalue"]))
				{
					$time_hours	= "";
					$time_mins	= "";
				}
				else
				{
					$time_processed	= explode(":", time_format_hourmins($this->structure[$fieldname]["defaultvalue"]));
					$time_hours	= $time_processed[0];
					$time_mins	= $time_processed[1];
				}

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				print "<input name=\"". $fieldname ."_hh\" style=\"width: 25px;\" maxlength=\"2\" value=\"$time_hours\"> hours ";
				print "<input name=\"". $fieldname ."_mm\" style=\"width: 25px;\" maxlength=\"2\" value=\"$time_mins\"> mins";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}

			break;
			
			case "radio":
				/*
					there are two ways to draw radio form entries
					
					1. Just pass it the array of values, and the code will translate them using the language DB

					2. Pass it an array of translation values with the array keys matching the value names. This
					   is useful when you want to populate the radio with data from a different table.
				*/

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}
				
				if (isset($this->structure[$fieldname]["translations"]))
				{
					$translations = $this->structure[$fieldname]["translations"];
				}
				else
				{
					// get translation for all options
					$translations = language_translate($this->language, $this->structure[$fieldname]["values"]);
				}


				// if there is only 1 option avaliable, see if we should auto-select it.
				if (isset($this->structure[$fieldname]["options"]["autoselect"]) && isset($this->structure[$fieldname]["values"]))
				{
					if (count($this->structure[$fieldname]["values"]) == 1)
					{
						$autoselect = 1;
					}
				}

				// display all the radio buttons
				foreach ($this->structure[$fieldname]["values"] as $value)
				{
					// is the current row, the one that is in use? If so, add the 'selected' tag to it
					if (isset($this->structure[$fieldname]["defaultvalue"]) && $value == $this->structure[$fieldname]["defaultvalue"])
					{
						print "<input checked ";
					}
					elseif (isset($autoselect))
					{
						print "<input checked ";
					}
					else
					{
						print "<input ";
					}

					// if actions enabled, configure all the actions that have been defined
					if (isset($this->actions[$fieldname]))
					{
						print "onclick=\"";

						foreach (array_keys($this->actions[$fieldname]) as $target_field)
						{
							if (isset($this->actions[$fieldname][ $target_field ][ $value ]))
							{
								$action = $this->actions[$fieldname][ $target_field ][ $value ];
							}
							else
							{
								$action = $this->actions[$fieldname][ $target_field ]["default"];
							}

							switch ($action)
							{
								case "show":
									print "obj_show('". $target_field ."'); ";
								break;

								case "hide":
									print "obj_hide('". $target_field ."'); ";
								break;
							}
						}
						
						print "\" ";
					}
					
					if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
						print "disabled=\"disabled\" ";
						
					if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
					{
						print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
					}			
						
					
					if (isset($this->structure[$fieldname]["options"]["disabled"]))
					{
						if ($this->structure[$fieldname]["options"]["disabled"] == "yes")
							print "disabled=\"disabled\" ";
					}
					
					print "type=\"radio\" style=\"border: 0px\" name=\"$fieldname\" value=\"$value\" id=\"". $fieldname ."_". $value ."\">";
					print "<label for=\"". $fieldname ."_". $value ."\">". $translations[$value] ."</label><br>";
				}

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}

			break;

			case "checkbox":

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}


				// render form field
				print "<input ";

				if (isset($this->structure[$fieldname]["defaultvalue"]))
				{
					if ($this->structure[$fieldname]["defaultvalue"] == "on" || $this->structure[$fieldname]["defaultvalue"] == "1" || $this->structure[$fieldname]["defaultvalue"] == "enabled")
					{
						print "checked ";
					}
				}
				// if actions enabled, configure all the actions that have been defined
				if (isset($this->actions[$fieldname]))
				{
					print "onclick=\"";

					foreach (array_keys($this->actions[$fieldname]) as $target_field)
					{
						if (isset($this->actions[$fieldname][ $target_field ]["1"]))
						{
							$action = $this->actions[$fieldname][ $target_field ]["1"];
						}

						switch ($action)
						{
							case "show":
								print "obj_show('". $target_field ."'); ";
							break;

							case "hide":
								print "obj_hide('". $target_field ."'); ";
							break;
						}
					}
					
					print "\" ";
				}
				
				if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
				{
					print "disabled=\"disabled\" ";
				}
					
				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
				}			
					
				if (isset($this->structure[$fieldname]["options"]["disabled"]))
				{
					if ($this->structure[$fieldname]["options"]["disabled"] == "yes")
						print "disabled=\"disabled\" ";
				}
					
				print "type=\"checkbox\" style=\"border: 0px\" name=\"". $fieldname ."\" id=\"". $fieldname ."\">";



				// post field label
				if (!isset($this->structure[$fieldname]["options"]["nolabel"]))
				{
					if (isset($this->structure[$fieldname]["options"]["label"]))
					{
						$translation = $this->structure[$fieldname]["options"]["label"];
					}
					else
					{
						$translation = language_translate_string($this->language, $fieldname);
					}
	
					print "<label for=\"". $fieldname ."\">". $translation ."</label>";
				}


			break;

			case "dropdown":

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				/*
					there are two ways to draw drop down tables:
					
					1. Just pass it the array of values, and the code will translate them using the language DB

					2. Pass it an array of translation values with the array keys matching the value names. This
					   is useful when you want to populate a dropdown with data from a different table.
				*/

				
				// set default size
				if (!isset($this->structure[$fieldname]["options"]["width"]))
					$this->structure[$fieldname]["options"]["width"] = 250;
					
			
				// create value array if the SQL has not been executed yet
				if (is_string($this->structure[$fieldname]["values"]))
				{
					if(!empty($this->structure[$fieldname]["defaultvalue"]))
					{
						$query = str_replace("CURRENTID", $this->structure[$fieldname]["defaultvalue"], $this->structure[$fieldname]["values"]);
					}
					else
					{
						$query = str_replace("CURRENTID", "0", $this->structure[$fieldname]["values"]);
					}
					$this->structure[$fieldname]["values"] = array();
					
					$sql_obj		= New sql_query;
					$sql_obj->string	= $query;
					$sql_obj->execute();
					
					if ($sql_obj->num_rows())
					{
						$sql_obj->fetch_array();
						foreach ($sql_obj->data as $data)
						{
							// merge multiple labels into a single label
							$label = $data["label"];

							for ($i=0; $i < count(array_keys($data)); $i++)
							{
								if (!empty($data["label$i"]))
								{
									$label .= " -- ". $data["label$i"];
								}
							}				

							// only add an option if there is an id and label for it
							if ($data["id"] && $label)
							{
								$this->structure[$fieldname]["values"][]			= $data["id"];
								$this->structure[$fieldname]["translations"][ $data["id"] ]	= $label;
							}
						}
					}
					else
					{
						print "No ". language_translate_string($_SESSION["user"]["lang"], $fieldname) ." avaliable.";
						print "<input type=\"hidden\" name=\"$fieldname\" value=\"". "No ". language_translate_string($_SESSION["user"]["lang"], $fieldname) ." avaliable." ."\">";
						break;
					}
				}
				
				
				if (isset($this->structure[$fieldname]["translations"]))
				{
					$translations = $this->structure[$fieldname]["translations"];
				}
				else
				{
					// get translation for all options
					$translations = language_translate($this->language, $this->structure[$fieldname]["values"]);
				}

				// input box for filtering
				if (isset($this->structure[$fieldname]["options"]["search_filter"]))
				{
					// subtact filter width from form element width
					$width_filter	= 100;							// px
					$width_element	= $this->structure[$fieldname]["options"]["width"];	// total

					$this->structure[$fieldname]["options"]["width"] = $width_element - $width_filter;

					// write filter field
					print "<input id=\"_" .$fieldname. "\" class=\"dropdown_filter\" style=\"width:". $width_filter ."px\"";
					
					if ( isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
						print "disabled=\"disabled\" ";
					print "/>&nbsp;";
				}

				// start dropdown/select box
				print "<select name=\"$fieldname\" size=\"1\" style=\"width: ". $this->structure[$fieldname]["options"]["width"] ."px;\"";
				if ( isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
					
				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
				}			
					
				print "> ";
				

				// if there is only 1 option avaliable, see if we should auto-select it.
				if (!empty($this->structure[$fieldname]["options"]["noselectoption"]))
				{
					$this->structure[$fieldname]["options"]["autoselect"];
					log_write("warning", "inc_forms", "obsolete usage of noselectoption dropdown option for field $fieldname");
				}

				
				if (!empty($this->structure[$fieldname]["options"]["autoselect"]) && !empty($this->structure[$fieldname]["values"]))
				{
					if (count($this->structure[$fieldname]["values"]) == 1)
					{
						$autoselect = 1;
					}
				}

				// if there is no current entry, add a select entry as default
				if ((!isset($this->structure[$fieldname]["defaultname"]) || ($this->structure[$fieldname]["defaultname"] == null)) && (!isset($autoselect) || ($autoselect == null)))
				{
					print "<option value=\"\">-- select --</option>";
				}
		

				//echo "</select>";			
				// add all the options
				foreach ($this->structure[$fieldname]["values"] as $value)
				{
					
					print "<option ";
					
					// is the current row, the one that is in use? If so, add the 'selected' tag to it
					if (isset($this->structure[$fieldname]["defaultvalue"]) && ($value == $this->structure[$fieldname]["defaultvalue"]))
					{
						print "selected='selected' ";
					}
					
					print "value=\"$value\">" . $translations[$value] ."</option>";
				}

				// end of select/drop down
				print "</select>";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}
			break;

			case "submit":
				$translation = language_translate_string($this->language, $this->structure[$fieldname]["defaultvalue"]);

				print "<input name=\"$fieldname\" type=\"submit\" value=\"$translation\"";
				
				if (isset($this->structure[$fieldname]["options"]["disabled"]))
				{
					if ($this->structure[$fieldname]["options"]["disabled"] == "yes")
						print "disabled=\"disabled\" ";
				}
					
				print ">";
			break;

			case "message":

				// sometimes message data is coming directly out of the SQL database, we should run HTML entities
				// conversion on it.
				$this->structure[$fieldname]["defaultvalue"]	= nl2br($this->structure[$fieldname]["defaultvalue"]);
				//$this->structure[$fieldname]["defaultvalue"]	= htmlentities($this->structure[$fieldname]["defaultvalue"]);

				print $this->structure[$fieldname]["defaultvalue"];
			break;

			case "file":
				// get max upload size
				$upload_maxbytes = format_size_human( sql_get_singlevalue("SELECT value FROM config WHERE name='UPLOAD_MAXBYTES'") );

				// optional prefix label/description
				if (isset($this->structure[$fieldname]["options"]["prelabel"]))
				{
					print $this->structure[$fieldname]["options"]["prelabel"];
				}

				// input field
				print "<input type=\"file\" name=\"$fieldname\"";
				if (isset($this->structure[$fieldname]["options"]["disabled"]) && ($this->structure[$fieldname]["options"]["disabled"] == "yes"))
					print "disabled=\"disabled\" ";
					
				if (isset($this->structure[$fieldname]["options"]["css_field_class"]))	
				{
					print "class=\"". $this->structure[$fieldname]["options"]["css_field_class"] ."\" ";
				}			
					
				print "> <i>Note: File must be no larger than $upload_maxbytes.</i>";

				// optional label/description
				if (isset($this->structure[$fieldname]["options"]["label"]))
				{
					print "<label for=\"". $fieldname ."\">". $this->structure[$fieldname]["options"]["label"] ."</label>";
				}
			break;

			default:
				log_debug("form", "Error: Unknown field type of ". $this->structure["fieldname"]["type"] ."");
			break;
		}

		return 1;
	}


	/*
		render_javascript()

		For a number of form capabilities such as autocomplete of dropdowns and show/hide
		javascript actions, the framework needs to output javascript to go along with the
		form items.

		We do that there, in the render_javascript function.

	*/
	function render_javascript()
	{
		log_debug("form", "Executing render_javascript()");



		print "<script type=\"text/javascript\">";

		foreach (array_keys($this->structure) as $fieldname)
		{
			/*
				If any actions have been defined, process them
			*/

			if (!empty($this->actions[$fieldname]))
			{
				log_debug("form", "Processing action rules for $fieldname field");

				foreach (array_keys($this->actions[$fieldname]) as $target_field)
				{
					if (isset($this->structure[$fieldname]["defaultvalue"]) && !empty($this->actions[$fieldname][ $target_field ][ $this->structure[$fieldname]["defaultvalue"] ]))
					{
						$action = $this->actions[$fieldname][ $target_field ][ $this->structure[$fieldname]["defaultvalue"] ];
					}
					else
					{
						$action = $this->actions[$fieldname][ $target_field ]["default"];
					}

					switch ($action)
					{
						case "show":
							print "obj_show('". $target_field ."'); ";
						break;

						case "hide":
							print "obj_hide('". $target_field ."'); ";
						break;
					}
				}
			}


			/*
				If the form type is input and has autocomplete, set a javascript value
				for the autocomplete feature.
			*/

			if (!empty($this->structure[ $fieldname ]["options"]["autocomplete"]) && $this->structure[ $fieldname ]["type"] == "input")
			{
				log_write("debug", "form", "Setting autocomplete dropdown values for form field $fieldname");

				$values = array();

				// load autocomplete data from SQL query
				if ($this->structure[ $fieldname ]["options"]["autocomplete"] == "sql")
				{
					log_write("debug", "form", "Fetching autocomplete data from SQL database");

					$sql_obj		= New sql_query;
					$sql_obj->string	= $this->structure[ $fieldname ]["options"]["autocomplete_sql"];
					$sql_obj->execute();


					if ($sql_obj->num_rows())
					{
						$sql_obj->fetch_array();
						
						foreach ($sql_obj->data as $data_row)
						{
							$values[]	= $data_row["label"];
						}
					}
				}
				

				// write javascript for autocomplete options
				print "$(function() {\n";
					print "\tvar autocomplete_$fieldname = [". format_arraytocommastring($values, '"') ."];\n";
					print "\t$(\"#$fieldname\").autocomplete({\n";
					print "\tsource: autocomplete_$fieldname\n";
					print "\t});\n";
				print "});\n";											
			}
		}


		print "</script>";
	}


	/*
		render_form()

		Displays a complete form - it either creates a single form, or
		can split the form into different sections with unique titles
	*/
	function render_form()
	{
		log_debug("form", "Executing render_form()");
	
		if (!$this->action || !$this->method)
		{
			log_write("warning", "inc_form", "No form action or method defined for form class");
		}


		// if we have not choosen to use subforms, then add all values to a single form.
		if (!$this->subforms)
		{
			$this->subforms[$this->formname] = array_keys($this->structure);
		}


		// start form
		print "<form enctype=\"multipart/form-data\" method=\"". $this->method ."\" action=\"". $this->action ."\" class=\"form_standard\" name=\"" . $this->formname ."\">";

		// draw session form box
		if ($this->sessionform)
		{
			$this->render_sessionform_messagebox();
		}


		// draw each sub form
		$num_subforms = count(array_keys($this->subforms));
		$count = 0;
		
		foreach (array_keys($this->subforms) as $form_label)
		{
			log_write("debug", "inc_form", "Processing subform: $form_label");

			$count++;
			
			if ($form_label == "hidden")
			{
				/*
					Form contains hidden fields, we don't want to create table rows
				*/
				foreach ($this->subforms[$form_label] as $fieldname)
				{
					$this->render_field($fieldname);
				}
			}
			else
			{
				// start table
				print "<table class=\"form_table\" width=\"100%\">";

				// form header
				print "<tr class=\"header\">";
				print "<td colspan=\"2\"><b>". language_translate_string($this->language, $form_label) ."</b></td>";
				print "</tr>";


				// standard vs grouped subform
				if (isset($this->subforms_grouped[$form_label]))
				{
					log_write("debug", "inc_form", "Subform $form_logic is grouped - running additional logic");

					/*
						Grouped subforms add additional logic to Amberphplib - a subform can be defined as normal
						with a placeholder fieldname.

						This placeholder then matches to a subforms_grouped configuration that defines that fields
						should belong to that group - once added, when the form is drawn, any fields that are grouped
						will be drawn on a single row.

						This feature is ideal for drawing complex charts/table-like forms without having to write
						custom rendering code every time.

						eg:
						$obj_form->subforms["example"] 				= array("group1", "group2", "notgrouped");
						$obj_form->subforms_grouped["example"]["group1"] 	= array("standard_field1", "standard_field2");
						$obj_form->subforms_grouped["example"]["group2"] 	= array("standard_field3", "standard_field4");
					*/


					$grouped_counter = 0;

					foreach ($this->subforms[$form_label] as $fieldname)
					{

						if (isset($this->subforms_grouped[$form_label][$fieldname]))
						{
							/*
								We have determined that $fieldname is a grouped field - from here, we
								now loop through and put grouped fields together.
								
								We track the table status with $grouped_counter, if we run into a regular
								field, we close the table, process that field and then restume the table for
								the next block of grouped fields.

								eg:
										/---------------------\
									group1: |  field1  |  field2  |
									group2: |  field1  |  field2  |
										\---------------------/

										/---------------------\
										| normal_field ...... |
										\---------------------/
									
										/---------------------\
									group3: |  field4  |  field5  |
										\---------------------/


								TODO: There is a limitation to be cautious of, the logic here does not count
									the number of columns to make sure all groups in the subform are the
									same length.

									General rule: don't mess with the number of columns - OR make sure there
									is a regular field between them to cause the table to be re-drawn.
							*/

							log_write("debug", "inc_form", "Processing field $fieldname as a group field");

							// container table
							if ($grouped_counter == 0)
							{
								print "<tr>";
								print "<td colspan=\"2\">";
								print "<table class=\"table_highlight\">";

								$grouped_counter = 1;
							}

							// grouped field
							$num_fields = count ($this->subforms_grouped[$form_label][$fieldname]);

							// check for errors
							$error = 0;

							foreach ($this->subforms_grouped[$form_label][$fieldname] as $fieldname2)
							{
								if (isset($_SESSION["error"]["$fieldname2-error"]))
								{
									$error = 1;
								}
							}


							// run through group members
							if ($error)
							{
								print "<tr class=\"form_error\">";
							}
							else
							{
								print "<tr>";
							}

							foreach ($this->subforms_grouped[$form_label][$fieldname] as $fieldname2)
							{
								// render field
								print "<td>";
								$this->render_field($fieldname2);
								print "</td>";
							}

							print "</tr>";

						}
						else
						{
							if ($grouped_counter == 1)
							{
								// close container table
								print "</table>";
								print "</td></tr>";

								$grouped_counter = 0;
							}

							// display row as normal
							log_write("debug", "inc_form", "Processing field $fieldname as a regular field");

							$this->render_row($fieldname);
						}

					}

					if ($grouped_counter == 1)
					{
						// close container table
						print "</table>";
						print "</td></tr>";

						$grouped_counter = 0;
					}
				}
				else
				{
					log_write("debug", "inc_form", "Form subgroup $form_label is not grouped");

					// display all the rows
					foreach ($this->subforms[$form_label] as $fieldname)
					{
						$this->render_row($fieldname);
					}
				}

				// end table
				print "</table>";

				if ($count != $num_subforms)
				{
					print "<br>";
				}
			}
		}

		// end form
		print "</form>";

		// render javascript components
		$this->render_javascript();
	}



} // end of class form_input


/*
	Standalone Functions
*/

/*
 	form_helper_prepare_dropdownfromobj($fieldname, $sql_obj)

	The function generates the relevant structure needed to add a drop down
	to a form (or the option form for a table based on the values provided from
	the SQL object.
	
	Sets a flag to prevent SQL from being queried until render time, so that
	default values can be accounted for.
	
	Will replace the key CURRENTID with the default value at render time.
	
	Returns the structure array, which can then be passed directly to form::add_input
 */

function form_helper_prepare_dropdownfromobj($fieldname, $sql_obj)
{
	log_debug("form", "Executing form_helper_prepare_dropdownfromdb($fieldname, sql_obj)");
	
	$sql_obj->generate_sql();
	
	
	//if CURRENTID key doesn't exist, proceed as usual
	if (strpos($sql_obj->string, "CURRENTID") === FALSE)
	{
		$structure = form_helper_prepare_valuesfromdb($sql_obj->string);
	}
	
	//otherwise, set as string so query is executed at render time
	else
	{
		$structure["values"]		= $sql_obj->string;
	}
	
	$structure["fieldname"]		= $fieldname;
	$structure["type"]		= "dropdown";

	return $structure;
}



/*
	form_helper_prepare_dropdownfromdb($fieldname, $sqlquery)

	This function generates the relevent structure needed to add a drop down
	to a form (or the option form for a table) based on the values provided from
	the SQL statement.

	Please refer to the form_helper_prepare_valuesfromdb for details about what
	format and options can be provided to the sqlquery.

	Returns the structure array, which can then be passed directly to form::add_input($structure_array);
*/

function form_helper_prepare_dropdownfromdb($fieldname, $sqlquery)
{
	log_debug("form", "Executing form_helper_prepare_dropdownfromdb($fieldname, sqlquery)");
	
	
	// get all the values from the DB.
	$structure = form_helper_prepare_valuesfromdb($sqlquery);

	
	
	// set type and any error messaes
	if (!$structure)
	{
		// no valid data found
		$structure = array();
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "text";
		$structure["defaultvalue"]	= "No ". language_translate_string($_SESSION["user"]["lang"], $fieldname) ." avaliable.";
	}
	else
	{
		// valid dropdown
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "dropdown";
	}

	
	// return the structure
	return $structure;
}



/*
	form_helper_prepare_radiofromdb($fieldname, $sqlquery)

	This function generates the relevent structure needed to add a radio selection
	to the form based on the values provided from the SQL statement.

	Please refer to the form_helper_prepare_valuesfromdb for details about what
	format and options can be provided to the sqlquery.

	Returns the structure array, which can then be passed directly to form::add_input($structure_array);
*/

function form_helper_prepare_radiofromdb($fieldname, $sqlquery)
{
	log_debug("form", "Executing form_helper_prepare_radiofromdb($fieldname, sqlquery)");
	
	// get all the values from the DB.
	$structure = form_helper_prepare_valuesfromdb($sqlquery);
	
	// set type and any error messaes
	if (!$structure)
	{
		// no valid data found
		$structure = array();
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "text";
		$structure["defaultvalue"]	= "No ". language_translate_string($_SESSION["user"]["lang"], $fieldname) ." avaliable.";
	}
	else
	{
		// valid radio button
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "radio";
	}


	// return the structure
	return $structure;
}



/*
	form_helper_prepare_valuesfromdb($sqlquery)

	Used by the other form_helper_prepare functions for generating array structures. Used by:
	* form_helper_prepare_dropdownfromdb
	* from_helper_prepare_radiofromdb

	All the SQL statement needs todo, is to provide 2 different values, labeled "id" and "label"
	and the function will do the rest.

	If you need to merge multiple fields into a single label, pull the additional values
	down as label#. The system will merge all the fields into a single label using "-".

	Returns the structure
*/
function form_helper_prepare_valuesfromdb($sqlquery)
{
	log_debug("form", "Executing form_helper_prepare_valuesfromdb($sqlquery)");
	

	// fetch from cache (if it exists)
	if (isset($GLOBALS["cache"]["form_sql"][$sqlquery]))
	{
		log_write("debug", "form", "Fetching form DB results from cache");

		return $GLOBALS["cache"]["form_sql"][$sqlquery];
	}
	else
	{
		// fetch data from SQL DB
		$sql_obj		= New sql_query;
		$sql_obj->string	= $sqlquery;
		
		$sql_obj->execute();
		
		if ($sql_obj->num_rows())
		{
			$sql_obj->fetch_array();
			foreach ($sql_obj->data as $data)
			{

				// merge multiple labels into a single label
				$label = $data["label"];

				for ($i=0; $i < count(array_keys($data)); $i++)
				{
					if (!empty($data["label$i"]))
					{
						$label .= " -- ". $data["label$i"];
					}
				}
				

				// only add an option if there is an id and label for it
				if ($data["id"] && $label)
				{
					$structure["values"][]				= $data["id"];
					$structure["translations"][ $data["id"] ]	= $label;
				}
			}

			// save structure to cache
			$GLOBALS["cache"]["form_sql"][$sqlquery] = $structure;

			// return the structure
			return $structure;
		}
	}

	return 0;
}





/*
	form_helper_prepare_valuesfromgroup($sqlquery)

	Passes the provided query to the sql_get_grouped_structure query, then takes the provided information and generates
	a form dropdown object, saving the developer from having to write lots of unnessacary information.

	Refer to the documentation in amberphplib/inc_sql.php relating to the sql_get_grouped_structure for information on
	the appropiate way to structure the SQL query.

	Fields
	fieldname		Field to query
	sqlquery		SQL Query to execute
				eg: "SELECT id as value_id, group_name as value_key, id_parent as value_parent FROM object_groups"

	Returns the structure
	array			Form object structure
*/

function form_helper_prepare_dropdownfromgroup($fieldname, $sqlquery)
{
	log_debug("form", "Executing form_helper_prepare_dropdownfromgroup($fieldname, $sqlquery)");
	
	// start object
	$structure = array();


	// fetch from cache (if it exists)
	if (isset($GLOBALS["cache"]["form_sql"][$sqlquery]))
	{
		log_write("debug", "form", "Fetching form DB results from cache");

		$data = $GLOBALS["cache"]["form_sql"][$sqlquery];
	}
	else
	{
		// execute query
		$data = sql_get_grouped_structure($sqlquery);
	}

	// import data into form structure
	if (is_array($data))
	{
		foreach ($data as $data_row)
		{
			$structure["values"][]				= $data_row["id"];
			$structure["translations"][ $data_row["id"] ]	= $data_row["key_formatted"];
		}
	}
	
	// set type and any error messaes
	if (!$structure)
	{
		// no valid data found
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "text";
		$structure["defaultvalue"]	= "No ". language_translate_string($_SESSION["user"]["lang"], $fieldname) ." avaliable.";
	}
	else
	{
		// valid dropdown
		$structure["fieldname"] 	= $fieldname;
		$structure["type"]		= "dropdown";
	}

	
	// return the structure
	return $structure;

} // end of form_helper_prepare_dropdownfromgroup()






/*
	form_helper_prepare_timezonedropdown($fieldname)

	This function produces a dropdown suitable for option fields where users need to select a timezone. This function
	conforms to the rest of the code giving a fixed text field when running on PHP < 5.2.0 (since timezones can't be changed)
	but providing a dropdown of all the timezones in the system when running on PHP > 5.2.0.

	Returns the structure
*/
function form_helper_prepare_timezonedropdown($fieldname)
{
	log_debug("form", "Executing form_helper_prepare_timezonedropdown($fieldname)");

	if (version_compare(PHP_VERSION, '5.2.0') === 1)
	{
		// running on PHP 5.2.0+
		$structure = NULL;
		$structure["fieldname"]			= $fieldname;
		$structure["type"]			= "dropdown";
		$structure["values"][]			= "SYSTEM";
			
		$timezones = DateTimeZone::listIdentifiers();
		sort($timezones);

		foreach ($timezones as $timezone)
		{
			$structure["values"][]		= $timezone;
		}
	}
	else
	{
		// running on PHP version older than 5.2.0
		$structure = NULL;
		$structure["fieldname"]			= $fieldname;
		$structure["type"]			= "text";
	}


	return $structure;
}


?>
