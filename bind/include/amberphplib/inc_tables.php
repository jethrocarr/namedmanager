<?php
/*
	tables.php

	Provides classes/functions used for generating all the tables and forms
	used.

	Some of the handy features it provides:
	* Ability to select/unselect columns to display on tables
	* Lookups of column names against word database allows for different language translations.
	* CSV export function

	Class provided is "table"

*/

class table
{
	var $tablename;				// name of the table - used for internal purposes, not displayed
	var $language = "en_us";		// language to use for the form labels.
	
	var $columns;				// array containing the list of all the columns to display
	var $columns_order;			// array containing columns to order by
	var $columns_order_options;		// array containing columns that can be sorted - if set to none, then the sortable
						// option box will not appear

	var $total_columns;			// array of columns to create totals for
	var $total_rows;			// array of columns to create per-row totals for
	var $total_rows_mode = "subtotal";	// row total modes
						//
						//	* subtotal		Total for just each row only (default)
						//	* subtotal_nofinal	Do not display a final total for the subtotal rows
						//	* incrementing		Add each row total to the previous one
						//	* ledger_add_debit	Like incrementing, but will add any columns
						//				titled "debit" and subtract any titled "credit"
						//	* ledger_add_credit	Like incrementing, but will add any columns
						//				titled "credit" and subtract any titled "debit"


	var $links;				// array of links to place in a final column
	var $links_columns;			// array used to define links that belong inside columns

	var $structure;				// contains the structure of all the defined columns.
	var $filter = array();			// structure of the filtering
	var $option = array();			// fixed options to add to the option form

	var $data;				// table content
	var $data_render;			// processed table content
	var $data_num_rows;			// number of rows

	var $sql_obj;				// object used for SQL string, queries and data
	var $obj_ldap;				// object used for LDAP queries
	
	var $render_columns;			// human readable column names
	
	var $limit_rows;			// maximum number of table rows to disable at any stage - if the max is reached,
						// more/next buttons are rendered - note: this limit functionality only takes place
						// *AFTER* any DB queries have been made, due to the need to filter only for HTML
						// output as well as needing to handle any structure of data.
	var $limit_start;			// internal only: start of displayed range
	var $limit_stop;			// internal only: end of displayed range

	


	/*
		table()

		Constructor Function
	*/
	function table()
	{
		// init the SQL structure
		$this->sql_obj = New sql_query;

		// defaults
		if (!empty($GLOBALS["config"]["TABLE_LIMIT"]))
		{
			$this->limit_rows = $GLOBALS["config"]["TABLE_LIMIT"];
		}
		
		if (!empty($_SESSION["user"]["table_limit"]))
		{
			$this->limit_rows = $_SESSION["user"]["table_limit"];
		}
	}


	/*
		add_column($type, $name, $dbname)
	
		Defines the column structure.
		type	- A known type of column
				standard	- non-processed field
				text		- same as standard, but any \n are replaced with <br>
				date		- YYYY-MM-DD format date field
				timestamp	- UNIX style timestamp field (converts to date + time)
				timestamp_date	- UNIX style timestamp field (converts to date only)
				money		- displays a financial value correctly
				money_float	- displays a financial value without rounding
				price		- legacy use - just calls money
				precentage	- does formatting for display percentages
				hourmins	- input is a number of seconds, display as H:MM
				bool_tick	- interperate a bool as an image (1 == tick, 0 == cross)
				
		name	- name/label of the column for display purposes

		dbname	- name of the field in the DB or session data to use for the input data (optional)
	*/
	function add_column($type, $name, $dbname)
	{
		log_debug("table", "Executing add_column($type, $name, $dbname)");

		$this->structure[$name]["type"]		= $type;
		$this->structure[$name]["dbname"]	= $dbname;
	}


	/*
		add_link($name, $page, $options_array)

		Adds a new link to the links array, with "name" becomming the link after undergoing
		translation. Note that $page is equal to the page to display, you don't need to define
		"index.php?page=" or anything.
		
		$options_array is used to specifiy get values, and has the following structure:
		$options_array["get_field_name"]["value"]	= "value";
		$options_array["get_field_name"]["column"]	= "columnname";


		To force the code to not add the index.php?page to the link, set the following option:
		$options_array["full_link"] = yes

		If the value option is specified, a GET field will be added with the specified value,
		otherwise if the column option is


		If you wish to have a column value turned into a link, set the following options:
		$options_array["column"] = "columnname";

		
	*/
	function add_link($name, $page, $options_array)
	{
		log_debug("table", "Executing add_link($name, $page, options_array)");

		$this->links[$name]["page"]	= $page;
		$this->links[$name]["options"]	= $options_array;


		if (isset($options_array["column"]))
		{
			log_debug("table", "Configuring $name as a column link");

			$this->links_columns[ $options_array["column"] ] = $name;
		}
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
		log_debug("table", "Executing add_filter(option_array)");

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
		custom_column_label($column, $label)

		Instead of doing a translate, the render functions will load the label from the data
		inputted by this function
	*/
	function custom_column_label($column, $label)
	{
		log_debug("table", "Executing custom_column_label($column, $label)");
		
		$this->structure[$column]["custom"]["label"] = $label;
	}


	/*
		custom_column_link($column, $link)

		Create the column label into a hyper link to the specified link.
	*/
	function custom_column_link($column, $link)
	{
		log_debug("table", "Executing custom_column_link($column, $link)");
		
		$this->structure[$column]["custom"]["link"] = $link;
	}
	


	/*
		generate_sql()

		This function automatically builds the SQL query structure using the options
		and columns that the user has chosen.

		It then used the sql_query class to produce an SQL query string, which can be used
		by the load_data_sql() function.
	*/
	function generate_sql()
	{
		log_debug("table", "Executing generate_sql");


		// run through all the columns, and add their fields to the SQL structure, unless
		// the dbname is equal to NONE, in which case ignore
		foreach ($this->columns as $column)
		{
			if ($this->structure[$column]["dbname"] != "NONE")
			{
				$this->sql_obj->prepare_sql_addfield($column, $this->structure[$column]["dbname"]);
			}
		}

		// generate WHERE filters if any exist
		if ($this->filter)
		{
			foreach (array_keys($this->filter) as $fieldname)
			{
				// ignore input from text fields, text fields
				if ($this->filter[$fieldname]["type"] != "text")
				{
					// note: we only add the filter if a value has been saved to default value, otherwise
					// we assume the SQL could break.
					if (!empty($this->filter[$fieldname]["defaultvalue"]))
					{
						// It is possible to have filters with no SQL query
						// supplied - these are used when creating complex filters which require code and can not
						// be expressed in a SQL query.
						//
						// Therefore, we ignore any filter without an SQL query and assume the code calling us
						// will handle it.
						//

						if (!empty($this->filter[$fieldname]["sql"]))
						{
							$query = str_replace("value", $this->filter[$fieldname]["defaultvalue"], $this->filter[$fieldname]["sql"]);
							$this->sql_obj->prepare_sql_addwhere($query);
						}
					}
				}
			}
		}

		// generate order by rules
		if ($this->columns_order)
		{
			foreach ($this->columns_order as $column_order)
			{
				$this->sql_obj->prepare_sql_addorderby($column_order);
			}
		}

		// produce SQL statement
		$this->sql_obj->generate_sql();
		
		return 1;
	}



	/*
		load_data_sql()
		
		This function executes the SQL statement and fetches all the data from
		the DB into an associative array.

		IMPORTANT NOTE: you *must* either:
		
		 a) run the generate_sql function before running this function, in order
		    to generate the SQL statement for execution.

		 b) Set the $this->sql_obj->string variable to a SQL string you want to
		    execute. Only do this if you understand what you're doing, since you'll
		    break all the filtering stuff.

		This data can then be used directly to generate the table, or can be
		modified by other code to produce the desired result before creating
		the final output.

		Returns the number of rows found.
	*/
	function load_data_sql()
	{
		log_debug("table", "Executing load_data_sql()");

		if (!$this->sql_obj->execute())
			return 0;

		$this->data_num_rows = $this->sql_obj->num_rows();

		if (!$this->data_num_rows)
		{
			return 0;
		}
		else
		{
			$this->sql_obj->fetch_array();
			
			foreach ($this->sql_obj->data as $data)
			{
				$tmparray = array();
			
				// run through all the fields defined in the SQL structure - we can't use the
				// defined columns, since there are often other fields queried (such as ID) which
				// are not included as columns but required for things such as hyperlinks.
				foreach ($this->sql_obj->sql_structure["fields"] as $sqlfield)
				{
					$tmparray[$sqlfield] = $data[$sqlfield];
				}

				// save data to final results
				$this->data[] = $tmparray;
			}

			return $this->data_num_rows;
		}
	}


	/*
		init_data_ldap

		Initalise the LDAP connection

		Returns
		0		Failure
		1		Success
	*/

	function init_data_ldap()
	{
		log_write("debug", "inc_tables", "Executing init_data_ldap()");

		$this->obj_ldap = New ldap_query;

		return 1;
	}

	/*
		load_data_ldap

		Query from a LDAP database.

		Values
		filter
		base_dn		(optional) DN to use or leave blank for default

		Returns
		0		Failure
		1		Success
	*/
	function load_data_ldap($filter, $base_dn = NULL)
	{
		log_write("debug", "inc_tables", "Executing load_data_ldap($filter, $base_dn)");

		$attributes = array();

		// run through all the columns, and add their fields to the LDAP attribute query unless
		// the dbname is equal to NONE, in which case ignore.
		foreach ($this->columns as $column)
		{
			if ($this->structure[$column]["dbname"] != "NONE")
			{
				$attributes[] = $this->structure[$column]["dbname"];
			}
		}


		// apply any LDAP filters that have been specified
		if ($this->filter)
		{
			foreach (array_keys($this->filter) as $fieldname)
			{
				// ignore input from text fields, text fields
				if ($this->filter[$fieldname]["type"] != "text")
				{
					// note: we only add the filter if a value has been saved to default value, otherwise
					// we assume the filter could break.
					if (!empty($this->filter[$fieldname]["defaultvalue"]))
					{
						// only apply ldap filters, ignore other types like SQL
						if (!empty($this->filter[$fieldname]["ldap"]))
						{
							$filter = str_replace("value", $this->filter[$fieldname]["defaultvalue"], $this->filter[$fieldname]["ldap"]);
						}
					}
				}
			}
		}



		// connect to LDAP server
		$this->obj_ldap->connect();


		// set DN
		if ($base_dn)
		{
			$this->obj_ldap->srvcfg["base_dn"] = $base_dn;
		}


		// run query and process results
		if ($this->obj_ldap->search($filter, $attributes))
		{
			// run through returned records
			$this->data_num_rows	= $this->obj_ldap->data_num_rows;

			for ($i=0; $i < $this->data_num_rows; $i++)
			{
				$tmparray = array();

				// fetch values for each column
				foreach ($this->columns as $column)
				{
					if ($this->structure[$column]["dbname"] != "NONE")
					{
						if ($this->obj_ldap->data[$i][  $this->structure[$column]["dbname"]  ]["count"] > 1)
						{
							// convert array of values to comma-deliminated string
							$values = array();

							for ($j = 0; $j < $this->obj_ldap->data[$i][  $this->structure[$column]["dbname"]  ]["count"]; $j++)
							{
								$values[] = $this->obj_ldap->data[$i][  $this->structure[$column]["dbname"]  ][$j];
							}

							$tmparray[$column] = format_arraytocommastring( $values );
						}
						else
						{
							// single standard value
							$tmparray[$column] = $this->obj_ldap->data[$i][  $this->structure[$column]["dbname"]  ][0];
						}
					}
				}

				// save data to final results
				$this->data[]	= $tmparray;
			}
		}
		else
		{
			// query failure or no rows returned
			$this->data_num_rows	= 0;

			return 0;
		}
	}
	


	/*
		load_options_form()

		Imports data from POST or SESSION which matches this form to be used for the options.
	*/
	function load_options_form()
	{
		/*
			Form options can be passed in two ways:
			1. POST - this occurs when the options have been passed at the last reload
			2. SESSION - if the user goes away and returns.

		*/

		if (isset($_GET["reset"]))
		{
			// reset the option form
			$_SESSION["form"][$this->tablename] = NULL;
		}
		else
		{
			
			if (isset($_GET["table_display_options"]))
			{
				// flag custom options as active - this is used to adjust the display of
				// the table options dropdown
				$_SESSION["form"][$this->tablename]["custom_options_active"] = 1;

				log_debug("table", "Loading options form from $_GET");
				
				$this->columns		= array();
				$this->columns_order	= array();

				// load checkboxes
				foreach (array_keys($this->structure) as $column)
				{
					if (isset($_GET[$column]))
					{
						$column_setting = @security_script_input("/^[a-z]*$/", $_GET[$column]);
					
						if ($column_setting == "on")
						{
							$this->columns[] = $column;
						}
					}
				}

				// load orderby options
				$num_cols = count(array_keys($this->structure));
				for ($i=0; $i < $num_cols; $i++)
				{
					if (!empty($_GET["order_$i"]))
					{
						$this->columns_order[] = @security_script_input("/^\S*$/", $_GET["order_$i"]);
					}
				}

				// load filterby option
				foreach (array_keys($this->filter) as $fieldname)
				{
					// switch to handle the different input types
					// TODO: find a good way to merge this code and the code in the security_form_input_predefined
					// into a single function to reduce reuse and complexity.
					switch ($this->filter[$fieldname]["type"])
					{
						case "date":
							$this->filter[$fieldname]["defaultvalue"] = @@security_script_input("/^[0-9]*-[0-9]*-[0-9]*$/", $_GET[$fieldname ."_yyyy"] ."-". $_GET[$fieldname ."_mm"] ."-". $_GET[$fieldname ."_dd"]);

							if ($this->filter[$fieldname]["defaultvalue"] == "--")
								$this->filter[$fieldname]["defaultvalue"] = "";
						break;

						case "timestamp":
							$this->filter[$fieldname]["defaultvalue"] = security_script_input("/^[0-9]*$/", $_GET[$fieldname]);

							if ($this->filter[$fieldname]["defaultvalue"] == "--")
								$this->filter[$fieldname]["defaultvalue"] = "";
						break;



						default:
							$this->filter[$fieldname]["defaultvalue"] = @@security_script_input("/^\S*$/", $_GET[$fieldname]);
						break;
					}

					// just blank input if it's in error
					if ($this->filter[$fieldname]["defaultvalue"] == "error")
						$this->filter[$fieldname]["defaultvalue"] = "";
				}

			}
			elseif (isset($_SESSION["form"][$this->tablename]["columns"]))
			{
				log_debug("table", "Loading options form from session data");
				
				// load checkboxes
				$this->columns		= $_SESSION["form"][$this->tablename]["columns"];

				// load orderby options
				$this->columns_order	= $_SESSION["form"][$this->tablename]["columns_order"];

				// load filterby options
				foreach (array_keys($this->filter) as $fieldname)
				{
					if (isset($_SESSION["form"][$this->tablename]["filters"][$fieldname]))
					{
						$this->filter[$fieldname]["defaultvalue"] = $_SESSION["form"][$this->tablename]["filters"][$fieldname];
					}
				}
			}

			// save options to session data
			$_SESSION["form"][$this->tablename]["columns"]		= $this->columns;
			$_SESSION["form"][$this->tablename]["columns_order"]	= $this->columns_order;
			
			foreach (array_keys($this->filter) as $fieldname)
			{
				if (isset($this->filter[$fieldname]["defaultvalue"]))
				{
					$_SESSION["form"][$this->tablename]["filters"][$fieldname] = $this->filter[$fieldname]["defaultvalue"];
				}
			}
		}

		return 1;
	}


	/*
		render_column_names()

		This function creates the labels for the columns. There are two different ways for this to occur:
		1. Using the translate functions, look up the label in the language DB
		2. Use the custom provided label.
	*/
	function render_column_names()
	{
		foreach ($this->columns as $column)
		{
			if (isset($this->structure[$column]["custom"]["label"]))
			{
				$this->render_columns[$column] = $this->structure[$column]["custom"]["label"];
			}
			else
			{
				// do translation
				$this->render_columns[$column] = lang_trans($column);
			}
		}

		return 1;
	}


	/*
		render_field($column, $row)

		This function correctly formats/processes values based on their type, and then returns them.
	*/
	function render_field($column, $row)
	{
		log_debug("table", "Executing render_field($column, $row)");

		/*
			See the add_column function for comments about
			the different possible types.
		*/
		if (!isset($this->structure[$column]["type"]))
		{
			$this->structure[$column]["type"] = "";
		}


		switch ($this->structure[$column]["type"])
		{
			case "date":
				if ($this->data[$row][$column] == "0000-00-00" || $this->data[$row][$column] == 0)
				{
					// no date in this field, add filler
					$result = "---";
				}
				else
				{
					// format the date and display
					$result = time_format_humandate($this->data[$row][$column]);
				}
			break;

			case "timestamp":
				if ($this->data[$row][$column])
				{
					$result_1 = time_format_humandate(date("Y-m-d", $this->data[$row][$column]));
					$result_2 = date("H:i:s", $this->data[$row][$column]);

					$result = "$result_1 $result_2";
				}
				else
				{
					$result = "---";
				}
			break;

			case "timestamp_date":
				if ($this->data[$row][$column])
				{
					$result = time_format_humandate(date("Y-m-d", $this->data[$row][$column]));
				}
				else
				{
					$result = "---";
				}
			break;

			case "price":
			case "money":
			case "money_float":

				// TODO: This exists here to work around a PHP bug - it seems that if
				// we don't have it, even though $row will equal 0, it will still match
				// the if statements below comparing it to "total".
				//
				// Bug was observed on PHP v4 on CentOS 4
				//
				$row = strval($row);


				// check if this field is a total or not, since we only
				// want to blank non-total spaces.
				$total = NULL;
				
				if ($row == "total")
					$total = "yes";

				if ($column == "total")
					$total = "yes";
				
				
				if (empty($this->data[$row][$column]) && !$total)
				{
					// instead of 0.00, make blank, as long as this field is not a total
					$result = "";
				}
				else
				{
					if ($this->structure[$column]["type"] == "money_float")
					{
						$result = format_money($this->data[$row][$column], NULL, 4);
					}
					else
					{
						$result = format_money($this->data[$row][$column]);
					}
				}
			break;

			case "hourmins":
				// value is a number of seconds, we need to convert into an H:MM format.
				$result = @time_format_hourmins($this->data[$row][$column]);
			break;


			case "bool_tick":
				// label as Y or N. The render functions may perform further work such
				// as displaying icons instead

				if (!empty($this->data[$row][$column]))
				{
					$result = "Y";
				}
				else
				{
					$result = "N";
				}
			break;

			case "text":
				$result = format_text_display($this->data[$row][$column]);
			break;

			case "percentage":
				if (!empty($this->data[$row][$column]))
				{
					$result = $this->data[$row][$column] ."%";
				}
				else
				{
					$result = "";
				}
			break;

			case "standard":
			default:
				if (isset($this->data[$row][$column]))
				{
					$result = $this->data[$row][$column];
				}
				else
				{
					$result = "";
				}
			break;
			
		} // end of switch


		return $result;
	}



	/*
		render_options_form()
		
		Displays a list of all the avaliable columns for the user to select from, as well as various
		filter options
	*/
	function render_options_form()
	{	
		log_debug("table", "Executing render_options_form()");


		// if the user has not configured any default options, display the dropdown
		// link bar instead of the main options table.
		if (!isset($_SESSION["form"][$this->tablename]["custom_options_active"]))
		{
			if (isset($_SESSION["user"]["shrink_tableoptions"]) && $_SESSION["user"]["shrink_tableoptions"] == "on")
			{
				print "<div id=\"". $this->tablename ."_link\">";

				print "<table class=\"table_options_dropdown\">";
				print "<tr>";

					print "<td onclick=\"obj_show('". $this->tablename ."_form'); obj_hide('". $this->tablename ."_link');\">";
					print "ADJUST TABLE OPTIONS &gt;&gt;";
					print "</td>";

				print "</tr>";
				print "</table><br>";

				print "</div>";
			}
		}


		// border table / div object
		print "<div id=\"". $this->tablename ."_form\">";
		print "<table class=\"table_options\"><tr><td>";


		
		// create tmp array to prevent excessive use of array_keys
		$columns_available = array_keys($this->structure);
		
		// get labels for all the columns
		$labels = language_translate($this->language, $columns_available);


		// start the form
		print "<form method=\"get\" class=\"form_standard\">";
		
		$form = New form_input;
		$form->formname = $this->tablename;
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


		// flag this form as the table_display_options form
		$structure = NULL;
		$structure["fieldname"] 	= "table_display_options";
		$structure["type"]		= "hidden";
		$structure["defaultvalue"]	= $this->tablename;
		$form->add_input($structure);
		$form->render_field("table_display_options");


		/*
			Check box options
		*/

		// configure all the checkboxes
		$num_cols	= count($columns_available);
		$num_cols_half	= sprintf("%d", $num_cols / 2);
		
		for ($i=0; $i < $num_cols; $i++)
		{
			$column = $columns_available[$i];
			
			// define the checkbox
			$structure = NULL;
			$structure["fieldname"]		= $column;
			$structure["type"]		= "checkbox";

			if (isset($this->structure[$column]["custom"]["label"]))
			{
				$structure["options"]["label"]	= lang_trans($this->structure[$column]["custom"]["label"]);
			}
			
			if (in_array($column, $this->columns))
				$structure["defaultvalue"] = "on";
				
			$form->add_input($structure);

			// split the column options boxes into two different columns
			if ($i < $num_cols_half)
			{
				$column_a1[] = $column;
			}
			else
			{
				$column_a2[] = $column;
			}
			
		}
		

		// structure table
		print "<table width=\"100%\"><tr>";
	
	
		print "<td width=\"50%\" valign=\"top\"  style=\"padding: 4px;\">";
			print "<b>Fields to display:</b><br><br>";

			print "<table width=\"100%\">";
				print "<td width=\"50%\" valign=\"top\">";
		
				// display the checkbox(s)
				foreach ($column_a1 as $column)
				{
					$form->render_field($column);
					print "<br>";
				}

				print "</td>";

				print "<td width=\"50%\" valign=\"top\">";
			
				// display the checkbox(s)
				foreach ($column_a2 as $column)
				{
					$form->render_field($column);
					print "<br>";
				}

				print "</td>";
			print "</table>";
		print "</td>";

		
		/*
			Filter Options
		*/
		
		
		print "<td width=\"50%\" valign=\"top\" style=\"padding: 4px;\">";
			print "<b>Filter/Search Options:</b><br><br>";

			print "<table width=\"100%\">";

			if ($this->filter)
			{
				foreach (array_keys($this->filter) as $fieldname)
				{
					if ($this->filter[$fieldname]["type"] == "dropdown")
						$this->filter[$fieldname]["options"]["width"] = 300;

					$form->add_input($this->filter[$fieldname]);
					$form->render_row($fieldname);
				}
			}
			
			print "</table>";		
		print "</td>";
		

		// new row
		print "</tr>";
		print "<tr>";


		/* Order By Options */
		if ($this->columns_order_options)
		{
			print "<td width=\"100%\" colspan=\"4\" valign=\"top\" style=\"padding: 4px;\">";

				print "<br><b>Order By:</b><br>";

				// limit the number of order boxes to 4
				$num_cols = count($this->columns_order_options);

				if ($num_cols > 4)
					$num_cols = 4;

				
				for ($i=0; $i < $num_cols; $i++)
				{
					// define dropdown
					$structure = NULL;
					$structure["fieldname"]		= "order_$i";
					$structure["type"]		= "dropdown";
					$structure["options"]["width"]	= 150;
					
					if (isset($this->columns_order[$i]))
					{
						$structure["defaultvalue"] = $this->columns_order[$i];
					}

					$structure["values"] = $this->columns_order_options;

					$form->add_input($structure);

					// display drop down
					$form->render_field($structure["fieldname"]);

					if ($i < ($num_cols - 1))
					{
						print " then ";
					}
				}
				
			print "</td>";
		}


		/*
			Submit Row
		*/
		print "<tr>";
		print "<td colspan=\"4\" valign=\"top\" style=\"padding: 4px;\">";
	
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
		if (!isset($_SESSION["form"][$this->tablename]["custom_options_active"]))
		{
			if (isset($_SESSION["user"]["shrink_tableoptions"]) && $_SESSION["user"]["shrink_tableoptions"] == "on")
			{
				print "<script type=\"text/javascript\">";
				print "obj_hide('". $this->tablename ."_form');";
				print "</script>";
			}
		}

	}


	/*
		render_table_prepare()

		This function calculates all the totals and generates the rendered values. This function is called
		by the render_table_html + render_table_csv functions to do all the hard work for them, and is
		also useful when generating custom table ouput for generating all the totals and formating options.
	*/
	function render_table_prepare()
	{
		log_debug("table", "Executing render_table_prepare()");



		/*
			Configure Row Limits

			We do the limits post-query, since we often get data sources from means other than
			the SQL database - in such situations, we can't filter until after the query has executed.

			Even if we did limit the SQL query, we would still need to run one query to get the full DB
			row count, so we can adjust the UI based on the number of rows possible.
		*/

		// set start/end
		if ($this->limit_rows)
		{
			if ($_SESSION["form"][$this->tablename]["limit_start"])
			{
				$this->limit_start = $_SESSION["form"][$this->tablename]["limit_start"];
			}
			else
			{
				$this->limit_start = 0;
			}

			if ($_SESSION["form"][$this->tablename]["limit_end"])
			{
				$this->limit_end = $_SESSION["form"][$this->tablename]["limit_end"];
			}
			else
			{
				$this->limit_end = $this->limit_rows + $range_start;
			}
	
		}
		else
		{
			$this->limit_start	= 0;
			$this->limit_end	= $this->data_num_rows;
		}

		// handle user requests for more
		if (isset($_GET["table_limit"]))
		{
			if ($_GET["table_limit"] == "less")
			{
				// reduce start/end
				$this->limit_start	= $this->limit_start - $this->limit_rows;
				$this->limit_end	= $this->limit_end - $this->limit_rows;
			}
			elseif ($_GET["table_limit"] == "more")
			{
				// increase start/end
				$this->limit_start	= $this->limit_start + $this->limit_rows;
				$this->limit_end	= $this->limit_end + $this->limit_rows;

			}

		} // end of limit options


		// apply sane constraints
		if ($this->limit_start < 0)
		{
			$this->limit_start = 0;
		}

		if ($this->limit_end < $this->limit_rows)
		{
			$this->limit_end = $this->limit_rows;
		}

		if ($this->limit_start > $this->data_num_rows )
		{
			$this->limit_start = $this->data_num_rows - $this->limit_rows;
		}

		if ($this->limit_end > $this->data_num_rows)
		{
			$this->limit_end = $this->data_num_rows;
		}


		// save session limit options
		$_SESSION["form"][$this->tablename]["limit_start"]	= $this->limit_start;
		$_SESSION["form"][$this->tablename]["limit_end"]	= $this->limit_end;

		
		// translate the column labels
		$this->render_column_names();

		$total_rows_incrementing = 0;
		
		// format data rows
		for ($i=$this->limit_start; $i < $this->limit_end; $i++)
		{
			// content for columns
			foreach ($this->columns as $columns)
			{
				// format content
				$this->data_render[$i][$columns] = $this->render_field($columns, $i);
			}


			// optional: row totals column
			if ($this->total_rows)
			{
				switch ($this->total_rows_mode)
				{
					/*
						SUBTOTAL

						Add all the columns for the row together, but don't increment
						them at all.
					*/
					case "subtotal":
					case "subtotal_nofinal":
					
						$this->data[$i]["total"] = 0;
	
						foreach ($this->total_rows as $total_col)
						{
							// add to the total
							if (isset($this->data[$i][$total_col]))
							{
								$this->data[$i]["total"] += $this->data[$i][$total_col];
							}
						}
					break;


					/*
						INCREMENTING

						We keep track of the previous row's value and add it to the total
						for the current row.
					*/
					case "incrementing":
					
						$this->data[$i]["total"] = $total_rows_incrementing;

						foreach ($this->total_rows as $total_col)
						{
							// add to the total
							$this->data[$i]["total"] += $this->data[$i][$total_col];
						}

						// add to row incrementing total
						$total_rows_incrementing = $this->data[$i]["total"];
					break;


					/*
						LEDGER
						
						For ledger row totals, we need to total up to show the account balance. We
						can either add credit or add debit as different modes are needed, depending
						on the account type.

						Because it's a ledger, we then set the final total row value
						to be equal to the final total from the ledger.
					*/
					case "ledger_add_credit":
					case "ledger_add_debit":
					
						$this->data[$i]["total"] = $total_rows_incrementing;
						
							
						if ($this->total_rows_mode == "ledger_add_credit")
						{
							// add the credit column
							$this->data[$i]["total"] += $this->data[$i]["credit"];
	
							// subtract the debit column
							$this->data[$i]["total"] -= $this->data[$i]["debit"];
						}
						else
						{
							// add the debit column
							$this->data[$i]["total"] += $this->data[$i]["debit"];
	
							// subtract the credit column
							$this->data[$i]["total"] -= $this->data[$i]["credit"];
						}

						// add to row incrementing total
						$total_rows_incrementing = $this->data[$i]["total"];

						// set the total summary row, since it can't be incremented further on
						// like normal totals.
						$this->data["total"]["total"] = $total_rows_incrementing;
						
					break;


					default:
						log_debug("inc_tables", "Error: Unrecognised row total mode ". $this->total_rows_mode ."");
					break;
				}


				// make the type of the column the same as one of the columns to be totaled
				// this is assumed to be correct, since only the same type of column should ever be totaled
				$this->structure["total"]["type"] = $this->structure[ $this->total_rows[0] ]["type"];


				// format row total
				$this->data_render[$i]["total"] = $this->render_field("total", $i);
			}
		}


		// calculate totals for columns
		if ($this->total_columns)
		{
			foreach ($this->columns as $column)
			{
				if (in_array($column, $this->total_columns))
				{
					$this->data["total"][$column] = 0;
					
					for ($i=0; $i < $this->data_num_rows; $i++)
					{
						if (isset($this->data[$i][$column]))
						{
							$this->data["total"][$column] += $this->data[$i][$column];
						}
					}

					$this->data_render["total"][$column] = $this->render_field($column, "total");
				}
			}

			// optional: totals for rows
			if ($this->total_rows && $this->total_rows_mode != "subtotal_nofinal")
			{
				// we have already calculated the final total for ledger
				// totals, so only calculate for non-ledger items.
				if ($this->total_rows_mode != "ledger_add_credit" && $this->total_rows_mode != "ledger_add_debit")
				{
					// run through and total all the row totals
					// then we create a final total for the row totals
					$this->data["total"]["total"] = 0;

					for ($i=0; $i < $this->data_num_rows; $i++)
					{
						$this->data["total"]["total"] += $this->data[$i]["total"];
					}
				}

				$this->data_render["total"]["total"] = $this->render_field("total", "total");
			}
		}


	} // end of render_table_prepare



	/*
		render_table_html()

		This function renders the entire table in HTML format.
	*/
	function render_table_html()
	{
		log_debug("table", "Executing render_table_html()");


		// calculate all the totals and prepare processed values
		if (empty($this->data_render))
		{
			$this->render_table_prepare();
		}
		
		// display header row
		print "\n<table class=\"table_content\" cellspacing=\"0\" width=\"100%\">\n";
		print "<tr>\n";

		foreach ($this->columns as $column)
		{
			// add a custom link if one has been specified, otherwise
			// just display the standard name
			if (isset($this->structure[$column]["custom"]["link"]))
			{
				print "\t<td class=\"header\"><b><a class=\"header_link\" href=\"". $this->structure[$column]["custom"]["link"] ."\">". $this->render_columns[$column] ."</a></b></td>\n";
			}
			else
			{
				print "\t<td class=\"header\"><b>". $this->render_columns[$column] ."</b></td>\n";
			}
		}
		
		// title for optional total column (displayed when row totals are active)
		if ($this->total_rows)
			print "\t<td class=\"header\"><b>Total:</b></td>\n";
	
		// filler for optional link column
		if ($this->links)
			print "\t<td class=\"header\">&nbsp;</td>\n";


		print "</tr>\n";

		// display data
		for ($i=$this->limit_start; $i < $this->limit_end; $i++)
		{
			if (isset($this->data[$i]["options"]["css_class"]))
			{
				print "<tr class=\"". $this->data[$i]["options"]["css_class"] ."\">\n";
			}
			else
			{
				print "<tr>\n";
			}

			// content for columns
			foreach ($this->columns as $columns)
			{
				$content = $this->data_render[$i][$columns];

				// start cell
				print "\t<td valign=\"top\" class=\"$columns\">";

				// hyperlink?
				if (isset($this->links_columns[ $columns ]))
				{
					$link		= $this->links_columns[ $columns ];
					$linkname	= lang_trans($link);
					$link_valid	= 1;

					
					/*
						check if there are any logic options we need to process

						This is used to provide the capabilities such as optional hyperlinks that
						only appear for some table rows.
					*/

					// if statements
					if (!empty($this->links[$link]["options"]["logic"]["if"]))
					{
						foreach (array_keys($this->links[$link]["options"]["logic"]["if"]) as $logic)
						{
							if ($this->links[$link]["options"]["logic"]["if"]["column"])
							{
								if ($this->data[$i][  $this->links[$link]["options"]["logic"]["if"]["column"] ])
								{
									$link_valid = 1;
								}
								else
								{
									// ensures that multiple if queries act as AND rather than OR
									$link_valid = 0;
								}
							}
						}
					}

					// if not statements
					if (!empty($this->links[$link]["options"]["logic"]["if_not"]))
					{
						foreach (array_keys($this->links[$link]["options"]["logic"]["if_not"]) as $logic)
						{
							if ($this->links[$link]["options"]["logic"]["if_not"]["column"])
							{
								if (!$this->data[$i][  $this->links[$link]["options"]["logic"]["if_not"]["column"] ])
								{
									$link_valid = 1;
								}
								else
								{
									// ensures that multiple if queries act as AND rather than OR
									$link_valid = 0;
								}
							}
						}
					}




					/*
						If the link passed logic processing, display
					*/

					if ($link_valid)
					{
						// link to page
						// There are two ways:
						// 1. (default) Link to index.php
						// 2. Set the ["options]["full_link"] value to yes to force a full link

						if (isset($this->links[$link]["options"]["full_link"]) && $this->links[$link]["options"]["full_link"] == "yes")
						{
							print "<a href=\"". $this->links[$link]["page"] ."?libfiller=n";
						}
						else
						{
							print "<a href=\"index.php?page=". $this->links[$link]["page"] ."";
						}

						// add each option
						foreach (array_keys($this->links[$link]["options"]) as $getfield)
						{
							/*
								There are two methods for setting the value of the variable:
								1. The value has been passed.
								2. The name of a column to take the value from has been passed
							*/
							if ($this->links[$link]["options"][$getfield]["value"])
							{
								print "&$getfield=". $this->links[$link]["options"][$getfield]["value"];
							}
							else
							{
								print "&$getfield=". $this->data[$i][ $this->links[$link]["options"][$getfield]["column"] ];
							}
						}

						// finish link
						print "\">";

					} // end if link valid

				} // end if hyperlink

				// handle bool images
				if ($this->structure[$columns]["type"] == "bool_tick")
				{
					if ($content == "Y")
					{
						$content = "<img src=\"images/icons/tick_16.gif\" alt=\"Y\"></img>";
					}
					else
					{
						$content = "<img src=\"images/icons/cross_16.gif\" alt=\"N\"></img>";
					}
				}

				// handle bytes
				if ($this->structure[$columns]["type"] == "bytes")
				{
					if ($content)
					{
						$file_size_types = array(" Bytes", " KB", " MB", " GB", " TB");

						if (!empty($GLOBALS["config"]["BYTECOUNT"]))
						{
							$content = round($content/pow($GLOBALS["config"]["BYTECOUNT"], ($z = floor(log($content, $GLOBALS["config"]["BYTECOUNT"])))), 2) . $file_size_types[$z];
						}
						else
						{
							// use 1024 bytes as a default
							$content = round($content/pow(1024, ($z = floor(log($content, 1024)))), 2) . $file_size_types[$z];
						}
					}
				}

				// display content
				if ($content)
				{
					print "$content";
				}
				else
				{
					// this is required for table formatting to work poperly with IE 7.
					// not required for firefox or safari
					print "&nbsp;";
				}



				// end hyperlink
				if (isset($this->links["columns"][ $columns ]) && $link_valid)
				{
					print "</a>";
				}

				print "</td>\n";
			}


			// optional: row totals column
			if (isset($this->total_rows))
			{
				$content = $this->data_render[$i]["total"];

				// handle bytes
				if ($this->structure[$column]["type"] == "bytes")
				{
					if ($content)
					{
						if (!empty($GLOBALS["config"]["BYTECOUNT"]))
						{
							$content = round($content/pow($GLOBALS["config"]["BYTECOUNT"], ($z = floor(log($content, $GLOBALS["config"]["BYTECOUNT"])))), 2) . $file_size_types[$z];
						}
						else
						{
							// use 1024 bytes as a default
							$content = round($content/pow(1024, ($z = floor(log($content, 1024)))), 2) . $file_size_types[$z];
						}
					}
				}

				print "\t<td><b>". $content ."</b></td>\n";
			}

			
			// optional: links column
			if (isset($this->links))
			{
				// filter out column links from the links page
				$links			= array_keys($this->links);
				$links_available	= array();
				$links_count		= 0;
				$count			= 0;

				foreach ($links as $link)
				{
					if (!isset($this->links[$link]["options"]["column"]))
					{
						$links_count++;
						$links_available[] = $link;
					}
				}

				if ($links_count)
				{
					print "\t<td align=\"right\" class=\"table_links\" nowrap>";
	
					foreach ($links_available as $link)
					{
						$count++;
						
						$linkname	= lang_trans($link);
						$link_valid	= 1;

						
						/*
							check if there are any logic options we need to process

							This is used to provide the capabilities such as optional hyperlinks that
							only appear for same table rows.
						*/

						// if statements
						if (isset($this->links[$link]["options"]["logic"]["if"]) && ($this->links[$link]["options"]["logic"]["if"] != NULL))
						{
							foreach (array_keys($this->links[$link]["options"]["logic"]["if"]) as $logic)
							{
								if ($this->links[$link]["options"]["logic"]["if"]["column"])
								{
									if ($this->data[$i][  $this->links[$link]["options"]["logic"]["if"]["column"] ])
									{
										$link_valid = 1;
									}
									else
									{
										// ensures that multiple if queries act as AND rather than OR
										$link_valid = 0;
									}
								}
							}
						}

						// if not statements
						if (isset($this->links[$link]["options"]["logic"]["if_not"]) && ($this->links[$link]["options"]["logic"]["if_not"] != NULL))
						{
							foreach (array_keys($this->links[$link]["options"]["logic"]["if_not"]) as $logic)
							{
								if ($this->links[$link]["options"]["logic"]["if_not"]["column"])
								{
									if (!$this->data[$i][  $this->links[$link]["options"]["logic"]["if_not"]["column"] ])
									{
										$link_valid = 1;
									}
									else
									{
										// ensures that multiple if queries act as AND rather than OR
										$link_valid = 0;
									}
								}
							}
						}




						/*
							If the link passed logic processing, display
						*/

						if ($link_valid)
						{
							// link to page
							// There are two ways:
							// 1. (default) Link to index.php
							// 2. Set the ["options]["full_link"] value to yes to force a full link
							if (empty($this->links[$link]["options"]["class"]))
							{
								$this->links[$link]["options"]["class"] = "";
							}

							if (isset($this->links[$link]["options"]["full_link"]) && $this->links[$link]["options"]["full_link"] == "yes")
							{
								print "<a class=\"button_small ". $this->links[$link]["options"]["class"] ."\" href=\"". $this->links[$link]["page"] ."?libfiller=n";
							}
							else
							{
								print "<a class=\"button_small ". $this->links[$link]["options"]["class"] ."\" href=\"index.php?page=". $this->links[$link]["page"] ."";
							}

							// add each option
							foreach (array_keys($this->links[$link]["options"]) as $getfield)
							{
								/*
									There are two methods for setting the value of the variable:
									1. The value has been passed.
									2. The name of a column to take the value from has been passed
								*/
								if (isset($this->links[$link]["options"][$getfield]["value"]))
								{
									print "&$getfield=". $this->links[$link]["options"][$getfield]["value"];
								}
								elseif (isset($this->links[$link]["options"][$getfield]["column"]))
								{
									print "&$getfield=". $this->data[$i][ $this->links[$link]["options"][$getfield]["column"] ];
								}
							}

							// finish link
							print "\">$linkname</a>";

						} // end if link valid

						// if required, add seporator
						if ($count < $links_count)
						{
							print "  ";
						}
					}

					print "</td>\n";

				} // end if valid links exist
			}
	
			print "</tr>\n";
		}


		// display totals for columns
		if (isset($this->total_columns))
		{
			print "<tr>\n";

			foreach ($this->columns as $column)
			{
				print "\t<td class=\"footer\">";
		
				if (in_array($column, $this->total_columns))
				{
					$content = $this->data_render["total"][$column];

					// handle bytes
					if ($this->structure[$column]["type"] == "bytes")
					{
						if ($content)
						{
							$file_size_types = array(" Bytes", " KB", " MB", " GB", " TB");
							$content = round($content/pow(1024, ($z = floor(log($content, 1024)))), 2) . $file_size_types[$z];
						}
					}

					// render column total
					print "<b>". $content ."</b>";
				}
				else
				{
					print "&nbsp;";
				}
		
				print "</td>\n";
			}

			// optional: totals for rows
			if (isset($this->total_rows))
			{
				$content = @$this->data_render["total"]["total"];

				// handle bytes
				if ($this->structure[$column]["type"] == "bytes")
				{
					if ($content)
					{
						$file_size_types = array(" Bytes", " KB", " MB", " GB", " TB");
						$content = round($content/pow(1024, ($z = floor(log($content, 1024)))), 2) . $file_size_types[$z];
					}
				}

				print "\t<td class=\"footer\"><b>". $content ."</b></td>\n";
			}


			// optional: filler for link column
			if (isset($this->links))
				print "\t<td class=\"footer\">&nbsp;</td>\n";
			
			print "</tr>\n";
		}
	
		print "</table>\n";
		
		
		// limit filter buttons
		if ($this->limit_start > 0)
		{
			$query = $_SERVER["QUERY_STRING"];
			$query = preg_replace('/&table_limit=\S*/', '', $query);

			print "<a class=\"button_small\" href=\"index.php?". $query ."&table_limit=less\">< less</a>";
		}

		if ($this->limit_end < $this->data_num_rows)
		{
			$query = $_SERVER["QUERY_STRING"];
			$query = preg_replace('/&table_limit=\S*/', '', $query);

			print "<a class=\"button_small\" href=\"index.php?". $query ."&table_limit=more\">more ></a>";
		}
		
		if ($this->limit_end != $this->data_num_rows || $this->limit_start != 0)
		{
			print "<font style=\"font-size: 10px;\">Total ". $this->data_num_rows ." entries</font>";
		}

	} // end of render_table_html



	/*
		render_table_csv()

		This function renders the entire table in CSV format
	*/
	function render_table_csv()
	{
		log_debug("table", "Executing render_table_csv()");

		// calculate all the totals and prepare processed values
		if (!isset($this->data_render))
		{
			$this->render_table_prepare();
		}

		// display header row
		foreach ($this->columns as $column)
		{
			print "\"". $this->render_columns[$column] ."\",";
		}
		
		// title for optional total column (displayed when row totals are active)
		if ($this->total_rows)
			print "\"Total\",";
	

		print "\n";


		// display data
		for ($i=0; $i < $this->data_num_rows; $i++)
		{
			print "\n";

			// content for columns
			foreach ($this->columns as $columns)
			{
				print "\"". str_replace('"', '""',$this->data_render[$i][$columns]) ."\",";
			}


			// optional: row totals column
			if ($this->total_rows)
			{
				print "\"". str_replace('"', '""',$this->data_render[$i]["total"]) ."\",";
			}
	
		}

		// display totals for columns
		if ($this->total_columns)
		{
			print "\n";

			foreach ($this->columns as $column)
			{
				print "\"";
				
				if (in_array($column, $this->total_columns))
				{
					print str_replace('"', '""',$this->data_render["total"][$column]);
				}

				print "\",";
			}

			// optional: totals for rows
			if ($this->total_rows)
			{
				print "\"". str_replace('"', '""',$this->data_render["total"]["total"]) ."\",";
			}

			print "\n";
		}
	
	} // end of render_table_csv





	/*
		render_table_pdf($template)

		This function outputs the table information as a PDF.

		Since the table could have any number of columns, we can't use a standard template - instead,
		we use a generic table template, copy it to the PATH_TMPDIR location, adjust the template with all
		the column information and then execute as normal and pass the data to the template_engine class.

		Optional Values:
		template	Name of the latex template to use for generating the PDF from - leave blank for default template to
				be used.

	*/
	function render_table_pdf($template = NULL)
	{
		log_debug("table", "Executing render_table_pdf()");

		if (!$template)
		{
			$template = "amberphplib_table_default.tex";
		}


		// calculate all the totals and prepare processed values
		if (!isset($this->data_render))
		{
			$this->render_table_prepare();
		}



		// start the PDF object
		$template_pdf = New template_engine_latex;

		// load template
		$template_pdf->prepare_load_template("templates/latex/$template");


		
		/*
			Generate PDF Template
		*/

		log_write("debug", "table", "Generating custom PDF template latex...");


		// init values
		$output_tabledata = array();


		// fetch the number of columns so we can draw the latex table
		$col_num = count($this->columns);


		// work out the number columns with the totals
		$col_num_with_total	= $col_num;

		if ($this->total_rows)
			$col_num_with_total++;

		// fetch column widths (float of percent of one)
		$col_width = (0.9 / $col_num_with_total);

		$output_tabledata[]		= '\noindent \begin{longtable}{';

		for ($i=0; $i < $col_num_with_total; $i++)
		{
			$output_tabledata[] 	= '>{\centering}p{'. $col_width .'\columnwidth}';
		}

		$output_tabledata[]		= '}\cline{1-'. $col_num_with_total .'}';


		// define column headers
		for ($i=0; $i < $col_num; $i++)
		{
			$output_tabledata[]	= '\textbf{'. $this->render_columns[ $this->columns[$i] ] .'}';

			if ($i != ($col_num - 1))
			{
				$output_tabledata[] = " & ";
			}

		}

		// add header for total
		if ($this->total_rows)
		{
			$output_tabledata[]	= ' & \textbf{Total} ';
		}

		$output_tabledata[]		= '\endfirsthead \cline{1-'. $col_num_with_total .'}';


		
		// table foreach loop
		$output_tabledata[]		= '%% foreach table\_data';

		$line	= "";
		$line	.= '%% ';

		for ($i=0; $i < $col_num; $i++)
		{
			$line .= '(column\_'. $i .')';

			if ($i != ($col_num - 1))
			{
				$line .= " & ";
			}
		}

		if ($this->total_rows)
		{
			$line .= ' & (column\_total) ';
		}

		$line .= '\tabularnewline';

		$output_tabledata[]		= $line;
		$output_tabledata[]		= '%% end';


		// display totals for columns (if required)
		if ($this->total_columns)
		{
			$output_tabledata[]		= '\cline{1-'. $col_num_with_total .'}';


			$line = "";

			for ($i=0; $i < $col_num; $i++)
			{
				$line .= '(column\_total\_'. $i .')';

				if ($i != ($col_num - 1))
				{
					$line .= " & ";
				}
			}

			if ($this->total_rows)
			{
				$line .= ' & (column\_total\_total) ';
			}


			$line .= '\tabularnewline';
			$output_tabledata[]	= $line;
		}


		// end data table
		$output_tabledata[]		= '\cline{1-'. $col_num_with_total .'}';
		$output_tabledata[]		= '\end{longtable}';



		/*
			Write changes to PDF template in memory
		*/
		log_write("debug", "table", "Writing custom PDF template in memory");

		$template_new = array();

		foreach ($template_pdf->template as $line_orig)
		{
			if ($line_orig == "%% TABLE_DATA\n")
			{
				foreach ($output_tabledata as $line_new)
				{
					$template_new[] = $line_new ."\n";
				}
			}
			else
			{
				$template_new[] = $line_orig;
			}
		}


		// overwrite memory version with processed version
		$template_pdf->template = $template_new;
		unset($template_new);




		/*
			Fill Template
		*/

		// company logo
		$template_pdf->prepare_add_file("company_logo", "png", "COMPANY_LOGO", 0);

		// table name
		$template_pdf->prepare_add_field("table_name", lang_trans($this->tablename));


		// table options
		$structure_main = NULL;

		// add date created option
		$structure			= array();
		$structure["option_name"]	= lang_trans("date_created");
		$structure["option_value"]	= time_format_humandate();
		$structure_main[]		= $structure;

		foreach (array_keys($this->filter) as $filtername)
		{
			$structure = array();

			$structure["option_name"] = lang_trans($filtername);

			switch ($this->filter[$filtername]["type"])
			{
				case "date":
					$structure["option_value"] = time_format_humandate($this->filter[$filtername]["defaultvalue"]);
				break;

				case "timestamp":
					$structure["option_value"] = time_format_humandate($this->filter[$filtername]["defaultvalue"]);
				break;

				default:
					// for all other types of filters, just display raw
					if ($this->filter[$filtername]["defaultvalue"])
					{
						$structure["option_value"] = $this->filter[$filtername]["defaultvalue"];
					}
					else
					{
						$structure["option_value"] = "---";
					}
				break;
			}


			$structure_main[] = $structure;
		}

		$template_pdf->prepare_add_array("table_options", $structure_main);



		// main table data rows
		$structure_main = NULL;

		for ($i=0; $i < $this->data_num_rows; $i++)
		{
			$structure = array();

			// add data for all selected columns
			for ($j=0; $j < count($this->columns); $j++)
			{
				$structure["column_$j"]	= $this->data_render[$i]["". $this->columns[$j] .""];
			}

			// optional: row totals column
			if ($this->total_rows)
			{
				$structure["column_total"] = $this->data_render[$i]["total"];
			}
	
			$structure_main[] = $structure;
		}

		$template_pdf->prepare_add_array("table_data", $structure_main);



		// totals
		if ($this->total_columns)
		{
			for ($j=0; $j < count($this->columns); $j++)
			{
				$column = $this->columns[$j];

				if (in_array($column, $this->total_columns))
				{
					$template_pdf->prepare_add_field('column_total_'. $j, $this->data_render["total"][ $column ]);
				}
				else
				{
					$template_pdf->prepare_add_field('column_total_'. $j, "");
				}
			}

			// optional: totals for rows
			if ($this->total_rows)
			{
				$template_pdf->prepare_add_field('column_total_total', $this->data_render["total"]["total"]);
			}

			print "\n";
		}



		/*
			Output PDF
		*/

		// perform string escaping for latex
		$template_pdf->prepare_escape_fields();
		
		// fill template
		$template_pdf->prepare_filltemplate();

		// generate PDF output
		$template_pdf->generate_pdf();

		// display PDF
		print $template_pdf->output;
//		print_r($template_pdf->template);
//		print_r($template_pdf->processed);
//		print_r($template_pdf->data_array);

	} // end of render_table_pdf
		

} // end of table class



?>
