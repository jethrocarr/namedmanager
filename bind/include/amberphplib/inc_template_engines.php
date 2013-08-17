<?php
/*
	inc_template_engines.php

	Functions and classes for generating rendered output from templates to different
	formats such as HTML and PDF using a number of rendering engines include LaTeX
	or WKHTMLTOPDF.

	This code is mostly used for PDF generation.


	TODO:	This file has fixed references to /tmp, this needs to be updated to use
		the configurable temp location set in the config table.
*/


/*
	CLASS TEMPLATE_ENGINE 

	Base clase used by all other render classes. Provides basic template loading
	options as well as field replacement.
*/
class template_engine
{
	var $data;		// data for standard fields
	var $data_array;	// data for array field	
	var $data_files;	// data for files

	var $template;		// template contents
	var $processed;		// processed lines of the templates (template with field data merged)

	var $output;		// field to hold processed information (eg: binary file data)


	/*
		Constructor/Destructors
	*/
	function template_engine()
	{
		log_debug("template_engine", "Executing template_engine()");
		
		// make sure we call the destructor on shutdown to cleanup all the tmp files.
		register_shutdown_function(array(&$this, 'destructor'));
    }

	function destructor()
	{
		log_debug("template_engine", "Executing destructor()");
		
		// remove any temporary files
		if ($this->data_files)
		{
			foreach (array_keys($this->data_files) as $var)
			{
				log_debug("template_engine", "Removing tmp file ". $this->data_files[$var]["filename"] ."");
				unlink($this->data_files[$var]["filename"]);
			}
		}
	}



	/*
		prepare_load_template

		Reads a template file into memory

		Values
		templatefile		Filename/path of the template file

		Returns
		0			failure to load
		1			success
	*/
	function prepare_load_template($templatefile)
	{
		log_debug("template_engine", "Executing prepare_load_template($templatefile))");
		
		// load template data into memory.
		$this->template = file($templatefile);

		if (!$this->template)
		{
			log_write("error", "template_engine", "Unable to load template file $templatefile into memory");
			return 0;
		}

		return 1;
	}



	/*
		prepare_add_field

		Add a new field to the template

		Values
		fieldname		name of the field in the template to match
		value			value to insert into the template

		Return
		0			failure
		1			success
	*/
	function prepare_add_field($fieldname, $value)
	{
		log_debug("template_engine", "Executing prepare_add_field($fieldname, value)");
		
		$this->data[$fieldname] = $value;
		return 1;
	}


	/*
		prepare_add_file

		Add a new file to the template.

		Values
		fieldname		name of the field to replace with the tmp filename
		file_extension		extension of the file to create
		
		file_type		type of the file in the file_uploads table
		file_id			ID of the file in the file_uploads table

		Return
		0			failure
		1			success
	*/
	function prepare_add_file($fieldname, $file_extension, $file_type, $file_id)
	{
		log_debug("template_engine", "Executing prepare_add_file($fieldname, $file_extension, $file_type, $file_id)");
		
		
		$tmp_filename = file_generate_name("/tmp/$fieldname", $file_extension);

		
		// output file data
		$file_obj			= New file_storage;
		$file_obj->data["type"]		= $file_type;
		$file_obj->data["customid"]	= $file_id;

		if (!$file_obj->load_data_bytype())
		{
			log_write("error", "template_engine", "Unable to find company logo image - use the administration config page to upload a company logo");
			return 0;
		}

		$file_obj->filedata_write($tmp_filename);


		// work out the filename without path or extension
		//
		// we have to do this, since latex will not tolerate file extensions in the filename when
		// the file is referenced in the tex data.
		//
		preg_match("/\S*\/(\S*)$/", $tmp_filename, $matches);
				
		$tmp_filename_short = $matches[1];
		

		// map fieldname to filename
		$this->data_files[$fieldname]["filename"]	= $tmp_filename;
		$this->data_files[$fieldname]["filename_short"]	= $tmp_filename_short;

		log_debug("template_engine", "Wrote tempory file $tmp_filename with shortname of $tmp_filename_short");
		
		return 1;
	}


	/*
		prepare_add_array

		Add a new array to the template - this will tell the code to create
		multiple rows in the template based on the provided data

		Values
		fieldname		name of the field in the template to match
		structure		array of values to insert for each row
					
						structure:
						$structure[0]["field1"] = "banana";
						$structure[0]["field2"] = "$10";;
						$structure[1]["field1"] = "apple";
						$structure[1]["field2"] = "$5";
		
		Return
		0			failure
		1			success
	*/
	function prepare_add_array($fieldname, $structure)
	{
		log_debug("template_engine", "Executing prepare_add_array($fieldname, array)");
		
		$this->data_array[$fieldname] = $structure;
		return 1;
	}



	/*
		fillter_template_data

		Can be overridden in child classes to alter incoming data
		fields.
	*/

	function fillter_template_data()
	{
		// this is purely a placeholder here to avoid missing function errors elsewhere in the application
	}
	
	
	/*
	 * process_template_loops, recursively processes template loops.
	 */
	
	function process_template_loops($line, $data, $fieldname, $fieldnames, $level) {
		// first loop through the parent fields
		$line_array = array();
		
		for ($j=0; $j < count($data); $j++)
		{	
			$current_level = $data[$j];
			if($current_level[$fieldname] != null)
			{	
				foreach($current_level[$fieldname] as $key => $current_level_part)
				{
				 	if (preg_match("/^\S*\sif\s(\S*)\sne\sprevious\s-->/", $line, $matches))
				 	{
				 		$line_tmp = '';
				 		$match = $matches[1];
				 		if ((!isset($current_level[$fieldname][($key-1)][$match]) && $current_level_part[$match])
							|| ($current_level_part[$match] != $current_level[$fieldname][($key-1)][$match]))
				 		{
						 	$line_tmp = str_replace($matches[0], '', $line);
				 		}
				 		else
				 		{	// only print what is after the endif
					 		$line_tmp = stristr($line, "<!-- endif -->");
				 		}
				 		$line_tmp = str_replace("<!-- endif -->", '', $line_tmp);
				 	}
				 	else if (preg_match("/^\S*\sif\s(\S*)/", $line, $matches))
				 	{
				 		$line_tmp = '';
				 		$match = $matches[1];
				 		if($current_level_part[$match] != '')
				 		{
						 	$line_tmp = str_replace($matches[0], '', $line);
						 	
				 		}
				 		else
				 		{	// only print what is after the endif
					 		$line_tmp = stristr($line, "<!-- endif -->");
				 		}
				 		$line_tmp = str_replace("<!-- endif -->", '', $line_tmp);
				 	}
				 	else
				 	{
					 	$line_tmp = $line;
						$line_tmp = preg_replace("/^\S*\s/", "", $line);
				 	} 	
					
					
					foreach (array_keys($current_level_part) as $var)
					{
						$line_tmp = str_replace("($var)", $current_level_part[$var], $line_tmp);
					}
					
					// if there are any items left that didn't get matched by any of the loop items, we should
					// then run through the global variables, as there may be a match there
					foreach (array_keys($this->data) as $var)
					{
						$line_tmp = str_replace("($var)", $this->data[$var], $line_tmp);
					}
					
				
					// And we also need to match any files, as well.
					foreach (array_keys($this->data_files) as $var)
					{
						$line_tmp = str_replace("($var)", $this->data_files[$var]["filename_short"], $line_tmp);
					}
					
					$line_array[$j][] = $line_tmp;
				}
			}
			else
			{	
				if(isset($current_level[$fieldnames[$level]]))
				{
					$inner_line_array = $this->process_template_loops($line, $current_level[$fieldnames[$level]], $fieldname, $fieldnames, $level+1);
					$line_array[$j][] = implode("\n",$inner_line_array);
				}
			}

		}
		return $line_array;
	}

	/*
		prepare_filltemplate

		Runs through the template and performs replacements for all the defined
		fields.
		
		Returns
		0			failure
		1			success
	*/
	function prepare_filltemplate()
	{
		log_debug("template_engine", "Executing prepare_filltemplate()");


		$fieldname	= "";
		$fieldnames = array(0 => '');
		$repeated_processed_lines = array();
		
		
		$in_foreach	= 0; 
		$in_if		= 0;
		for ($i=0; $i < count($this->template); $i++)
		{
			$line = $this->template[$i];
		
			// if $in_foreach is set, then this line should be repeated for each row in the array
			if ($in_foreach)
			{
				$current_fieldname = $fieldnames[$in_foreach];
				
				// check for loop end
				if (preg_match("/^\S*\send\s($current_fieldname)/", $line))
				{
					unset($fieldnames[$in_foreach]);
					$in_foreach--;
					$value_references = array(0 => '');
					$fieldname = $fieldnames[$in_foreach];
					if($in_foreach == 0)
					{
						foreach((array)$repeated_processed_lines as $repeated_processed_line_set) {
							$this->processed = array_merge($this->processed, (array)$repeated_processed_line_set);
						}
						$repeated_processed_lines = array();
					}
				}
				else if (preg_match("/^\S*\send[\s->]*$/", $line))
				{
					$in_foreach = 0;
					foreach((array)$repeated_processed_lines as $repeated_processed_line_set) {
						$this->processed = array_merge($this->processed, (array)$repeated_processed_line_set);
					}
					
					$repeated_processed_lines = array();
					$repeated_processed_lines = null;
				}
				else if (preg_match("/^\S*\sforeach\s(\S*)/", $line, $matches))
				{
					$fieldname = $matches[1];
					$in_foreach++;
					$fieldnames[$in_foreach] = $fieldname;
				}
				else
				{
					// remove commenting from the front of the line
					//$line = preg_replace("/^\S*\s/", "", $line);
					/*
						For this line, run through all the rows and add a new, processed
						row for every row required.
					*/
					// if we are in greater than one depth of loop (although , this only works for two levels, a third will break)
					// TODO: in future, expand this to handle indefinite numbers of levels?
					if ($in_foreach > 1)
					{
						$parent_fieldname = $fieldnames[1];
						// first loop through the parent fields
						//echo "<pre>".htmlentities(print_r($line, true), ENT_QUOTES)."</pre>";
						$repeated_processed_line_sections = $this->process_template_loops($line, $this->data_array[$parent_fieldname], $fieldname, $fieldnames, 2);
						for ($j=0; $j < count($this->data_array[$parent_fieldname]); $j++)
						{
							if(empty($repeated_processed_lines[$j]))
							{
								$repeated_processed_lines[$j] = array();
							}
						
							if(empty($repeated_processed_line_sections[$j]))
							{
								$repeated_processed_line_sections[$j] = array();
							}
							$repeated_processed_lines[$j] = array_merge((array)$repeated_processed_lines[$j], (array)$repeated_processed_line_sections[$j]);
						}
					
					}
					else
					{
						for ($j=0; $j < count($this->data_array[$fieldname]); $j++)
						{
							$line_tmp = $line;
						
						
						 	if (preg_match("/^\S*\sif\s(\S*)\s-->/", $line, $matches))
						 	{
						 		$line_tmp = '';
						 		$match = $matches[1];
						 		if (!empty($this->data[$match]) || !empty($this->data_array[$match][$j]))
						 		{
								 	$line_tmp = str_replace($matches[0], '', $line);
								 	
						 		}
						 		else
						 		{	// only print what is after the endif
							 		$line_tmp = stristr($line, "<!-- endif -->");
						 		}
						 		$line_tmp = str_replace("<!-- endif -->", '', $line_tmp);
						 	}

							$line_tmp = preg_replace("/^\S*\s/", "", $line_tmp);
							// run through the loop items
							foreach (array_keys($this->data_array[$fieldname][$j]) as $var)
							{
								/*
									TODO: Need to figure out exactly what is going on here... essentially
									the non-array version of $this->data_array[$fieldname][$j][$var] are fine
									but there are array versions mixed in too - appearing in ABS invoice line
									items mostly.
									
									Unfortunatly the invoicing logic is quite complex and will need a code review
									in the near future, there will be stuff here that could be optimised further.

									For now, the check on array status is the workaround solution.
								*/

								if (!is_array($this->data_array[$fieldname][$j][$var]))
								{
									$line_tmp = str_replace("($var)", $this->data_array[$fieldname][$j][$var], $line_tmp);
								}

							}
							
							// if there are any items left that didn't get matched by any of the loop items, we should
							// then run through the global variables, as there may be a match there
							if (isset($this->data))
							{
								foreach (array_keys($this->data) as $var)
								{
									$line_tmp = str_replace("($var)", $this->data[$var], $line_tmp);
								}
							}
						
						
							// And we also need to match any files, as well.
							if (isset($this->data_files))
							{
								foreach (array_keys($this->data_files) as $var)
								{
									$line_tmp = str_replace("($var)", $this->data_files[$var]["filename_short"], $line_tmp);
								}
							}
							
							// append the rows to the processed item array
							$repeated_processed_lines[$j][] = $line_tmp; 
						}

					}		
				}
				
			}

			// if $in_if is set, then the lines should only be set and uncommented if the if statement is true
			if ($in_if)
			{
				// check for if end
				if (preg_match("/^\S*\send/", $line))
				{
					$in_if = 0;
					$execute_if = 0;
				}
				else if($execute_if == 1)
				{
					// remove commenting from the front of the line
					$line_tmp = preg_replace("/^\S*\s/", "", $line);
				
					// process any single variables in this line
					if ($this->data)
					{
						foreach (array_keys($this->data) as $var)
						{
							$line_tmp = str_replace("($var)", $this->data[$var], $line_tmp);
						}
					}

					// process any files in this line
					if ($this->data_files)
					{
						foreach (array_keys($this->data_files) as $var)
						{
							$line_tmp = str_replace("($var)", $this->data_files[$var]["filename_short"], $line_tmp);
						}
					}
					
					$this->processed[] = $line_tmp;

				}
			}

		
			if (!$in_if && !$in_foreach)
			{
				// NOT IN LOOP SECTIONS
				if (preg_match("/^\S*\sforeach\s(\S*)/", $line, $matches))
				{
					// check for foreach loop
					$fieldname = $matches[1];
				
					log_debug("template_engine","Processing array field $fieldname");

					$in_foreach = 1;
					$fieldnames[$in_foreach] = $fieldname; 
				}
				elseif (preg_match("/^\S*\sif\s(\S*)/", $line, $matches))
				{
					// check for if loop
					$fieldname = $matches[1];
					log_debug("template_engine","Processing if field $fieldname");

					if (!empty($this->data[$fieldname]) && (trim($this->data[$fieldname]) != '') || (!empty($this->data_files[$fieldname]) && (trim($this->data_files[$fieldname]) != '')))
					{
						log_debug("template_engine", "Value or file $fieldname has been set, processing optional lines.");
						$in_if = 1;
						$execute_if = 1;
					}
					else
					{
						log_debug("template_engine", "File $fieldname has not been set, so will not display if section of template");	
						$in_if = 1;
						$execute_if = 0;					
					}
				}
				else
				{
					$line_tmp = $line;
				
					// process any single variables in this line
					if ($this->data)
					{
						foreach (array_keys($this->data) as $var)
						{
							$line_tmp = str_replace("($var)", trim($this->data[$var]), $line_tmp);
						}
					}

					// process any files in this line
					if ($this->data_files)
					{
						foreach (array_keys($this->data_files) as $var)
						{
							$line_tmp = str_replace("($var)", $this->data_files[$var]["filename_short"], $line_tmp);
						}
					}


					$this->processed[] = $line_tmp;
				}
			}

		}
		
		
		
		//exit("<pre>".htmlentities(print_r($this->processed,true), ENT_QUOTES)."</pre>");	
	}

	
} // end of template_engine class




/*
	CLASS TEMPLETE_ENGINE_LATEX

	Functions for generating documents using LaTeX.

*/
class template_engine_latex extends template_engine
{
	/*
		prepare_escape_fields()

		Escapes bad characters for latex - eg: \, % and others.
		
		Returns
		0		failure
		1		success
	*/
	function prepare_escape_fields()
	{
		log_debug("template_engine_latex", "Executing prepare_escape_fields()");  

		$target		= array('/\\\/','/\{/','/\}/','/\^/', '/%/', '/_/', '/\$/', '/&/', '/#/','/~/', '/€/');
		$replace	= array('\textbackslash','\{','\}','\textasciicircum','\%', '\_', '\\\$', '\&', '\#','\textasciitilde', '\euro{}');
 
		// escape single fields
		if ($this->data)
		{
			foreach ($this->data as $fieldname => $data)
			{
				$filtered_fieldname = preg_replace($target, $replace, $fieldname);
				$filtered_data = preg_replace($target, $replace, $data);

				// Unset the unfiltered value, it is no longer needed
				unset($this->data[$fieldname]);
				$this->data[$filtered_fieldname] = $filtered_data;
			}
		}


		// escape arrays
		if ($this->data_array)
		{
			foreach ($this->data_array as $fieldname => $data_array)
			{
				$filtered_fieldname = preg_replace($target, $replace, $fieldname);
				unset($this->data_array[$fieldname]);
				$this->data_array[$filtered_fieldname] = $data_array;
				
				for ($j=0; $j < count($data_array); $j++)
				{						
					foreach ($this->data_array[$filtered_fieldname][$j] as $sub_fieldname => $data)
					{
						$filtered_sub_fieldname = preg_replace($target, $replace, $sub_fieldname);
						$filtered_data = preg_replace($target, $replace, $data);
						
						unset($this->data_array[$fieldname][$j][$sub_fieldname]);
						$this->data_array[$filtered_fieldname][$j][$filtered_sub_fieldname] = $filtered_data;
					}
				}
			}
		}
		//exit("<pre>".print_r($this->data_array, true)."</pre>");
	} // end of prepare_escape_fields

	/*
		prepare_add_file

		Add a new file to the template. Latex version, returns filename with no extension

		Values
		fieldname		name of the field to replace with the tmp filename
		file_extension		extension of the file to create
		
		file_type		type of the file in the file_uploads table
		file_id			ID of the file in the file_uploads table

		Return
		0			failure
		1			success
	*/
	function prepare_add_file($fieldname, $file_extension, $file_type, $file_id)
	{
		log_debug("template_engine", "Executing prepare_add_file($fieldname, $file_extension, $file_type, $file_id)");
		
		
		$tmp_filename = file_generate_name("/tmp/$fieldname", $file_extension);

		
		// output file data
		$file_obj			= New file_storage;
		$file_obj->data["type"]		= $file_type;
		$file_obj->data["customid"]	= $file_id;

		if (!$file_obj->load_data_bytype())
		{
			log_write("error", "template_engine", "Unable to find company logo image - use the administration config page to upload a company logo");
			return 0;
		}

		$file_obj->filedata_write($tmp_filename);


		// work out the filename without path or extension
		//
		// we have to do this, since latex will not tolerate file extensions in the filename when
		// the file is referenced in the tex data.
		//
		preg_match("/\S*\/(\S*).$file_extension$/", $tmp_filename, $matches);
				
		$tmp_filename_short = $matches[1];
		

		// map fieldname to filename
		$this->data_files[$fieldname]["filename"]	= $tmp_filename;
		$this->data_files[$fieldname]["filename_short"]	= $tmp_filename_short;

		log_debug("template_engine", "Wrote tempory file $tmp_filename with shortname of $tmp_filename_short");
		
		return 1;
	}
	

	/*
		generate_pdf()

		Generate a PDF file and stores it in $this->output.

		Returns
		0		failure
		1		success
	*/
	
	function generate_pdf()
	{
		log_debug("template_engine_latex", "Executing generate_pdf()");


		// generate unique tmp filename
		$tmp_filename = file_generate_name("/tmp/amberdms_billing_system");

		// write out template data
		if (!$handle = fopen("$tmp_filename.tex", "w"))
		{	
			log_write("error", "template_engine_latex", "Failed to create temporary file ($tmp_filename.tex) with template data");
			return 0;
		}

		foreach ($this->processed as $line)
		{
			if (fwrite($handle, $line) === FALSE)
			{
				log_write("error", "template_engine_latex", "Error occured whilst writing file ($tmp_filename.tex)");
				return 0;
			}
		}

		fclose($handle);


                // create a "home directory" for texlive - some components of texlive
		// require this dir to write files to, and if it doesn't exist the PDF
		// will fail to build.
		//
		// Also note that we need to specify HOME=$tmp_filename_texlive so that
		// texlive will use it, otherwise it will default to the home directory
		// of whoever the last user to restart apache was
		//
		// (this is often root, but can sometimes be another admin who restarted
		//  apache whilst sudoed)
		//

		mkdir($tmp_filename ."_texlive", 0700);
		
		// get the current directory so that we can change back after switching to the tmp directory
		$run_directory = getcwd();

		// process with pdflatex
		$app_pdflatex = sql_get_singlevalue("SELECT value FROM config WHERE name='APP_PDFLATEX' LIMIT 1");

		if (!is_executable($app_pdflatex))
		{
			log_write("error", "process", "You have selected a template that requires the pdflatex application, however $app_pdflatex does not exist or is not executable by your webserver process");
		}

		chdir("/tmp"); // TODO: this should be a configurable value, as it will break on non-NIX platforms
		exec("HOME=/tmp/ $app_pdflatex $tmp_filename.tex", $output);
		
		foreach ($output as $line)
		{
			log_debug("template_engine_latex", "pdflatex: $line");
		}


		// check that a PDF was generated
		if (file_exists("$tmp_filename.pdf"))
		{
			log_debug("template_engine_latex", "Temporary PDF $tmp_filename.pdf generated");
			
			// import file data into memory
			$this->output = file_get_contents("$tmp_filename.pdf");

			// remove temporary files from disk
			unlink("$tmp_filename");
			unlink("$tmp_filename.aux");
			unlink("$tmp_filename.log");
			unlink("$tmp_filename.tex");
			unlink("$tmp_filename.pdf");

			// cleanup texlive home directory
			system("rm -rf ". $tmp_filename ."_texlive");

			// return back to original directory
			chdir($run_directory);

			return 1;
		}
		else
		{
			log_write("error", "template_engine_latex", "Unable to use pdflatex ($app_pdflatex) to generate PDF file");
			
			// return back to original directory
			chdir($run_directory);
			
			return 0;
		}
		
	} // end of generate_pdf


	

	
} // end of template_engine_latex class






/*
	CLASS TEMPLETE_ENGINE_HTMLTOPDF

	Functions for generating documents using HTML to PDF conversion renders.

	Currently this only consists of wkhtmltopdf, however it could potentially support other CSS 2.1 compliant
	rendering engines in future with configurable options.
*/
class template_engine_htmltopdf extends template_engine
{
	var $template_directory;
	
	/*
		set_template_directory

		Sets the template directory
		
		Values
		template_dir		Filename/path of the template file

		Returns
		nothing
	*/
	function set_template_directory($template_dir)
	{
		$this->template_directory = $template_dir;
	}
	
	
	/*
		prepare_load_template

		Reads a template file into memory, overrides the parent method, html templates are directories, main file is index.html

		Values
		templatefile		Filename/path of the template file

		Returns
		0			failure to load
		1			success
	*/
	function prepare_load_template($templatefile)
	{
		log_debug("template_engine", "Executing prepare_load_template($templatefile))");
		
		// load template data into memory.
		$this->template = file($templatefile);

		if (!$this->template)
		{
			log_write("error", "template_engine", "Unable to load template file $templatefile into memory");
			return 0;
		}

		return 1;
	}
	
	
	/*
		prepare_escape_fields()

		Escapes bad characters for html using htmlentities
		
		Returns
		0		failure
		1		success
	*/
	function prepare_escape_fields()
	{
		log_debug("template_engine_htmltopdf", "Executing prepare_escape_fields()");

		// escape single fields
		if ($this->data)
		{
			foreach (array_keys($this->data) as $var)
			{
				$this->data[$var] = htmlentities($this->data[$var], ENT_QUOTES, "UTF-8");
				$this->data[$var] = str_replace("\n", "<br />", $this->data[$var]);
			}
		}


		// escape arrays
		if ($this->data_array)
		{
			foreach (array_keys($this->data_array) as $fieldname)
			{
				for ($j=0; $j < count($this->data_array[$fieldname]); $j++)
				{
					foreach (array_keys($this->data_array[$fieldname][$j]) as $var)
					{
						$this->data_array[$fieldname][$j][$var] = htmlentities($this->data_array[$fieldname][$j][$var], ENT_QUOTES, "UTF-8");
						$this->data_array[$fieldname][$j][$var] = str_replace("\n", "<br />", $this->data_array[$fieldname][$j][$var]); 
					}
				}
			}
		}
		
	} // end of prepare_escape_fields



	/*
		fillter_template_data()

		Can be overridden in child classes to alter incoming data
		
		Used here to split the invoice items up
		
		Returns
		0		failure
		1		success

		// TODO: this function name is a typo, should be filter_template_data, needs a sed to correct source

		// TODO: the function seems to contain ABS-specific references to invoice_items? This needs to be changed, since
			 this function needs to be product-neutral as it is part of the Amberphplib framework.
	*/

	function fillter_template_data()
	{
		$page_row_count = 30;
		
		$i = 0;
		$this->data_array['invoice_pages'] = array();
		if($this->data_array['invoice_items'])
		{
			$temp_data = array();
			foreach($this->data_array['invoice_items'] as $set_key => $invoice_row)
			{
				$temp_data['invoice_items'][$set_key] = $invoice_row;
				$i++;

				// if there are new lines in invoice description fields, increment
				// the counter further to prevent wrap failure.
				if (!empty($invoice_row["description"]))
				{
					$i = $i + substr_count($invoice_row["description"], '<br />');
				}

//				foreach($invoice_set['invoice_items'] as $row_key => $invoice_row)
//				{
//					$i++;
//					$temp_data['invoice_sets'][$set_key]['invoice_items'][] = $invoice_row;
//					if($i >= $page_row_count)
//					{
//						$this->data_array['invoice_pages'][] = $temp_data;
//						$temp_data = array();
//						$i = 0;
//					}
//				}
				if($i >= $page_row_count)
				{
					$this->data_array['invoice_pages'][] = $temp_data;
					$temp_data = array();
					$i = 0;
				}
			}
			if(count($temp_data) >= 1)
			{
				$this->data_array['invoice_pages'][] = $temp_data;
			}
			
			// static page offset
			$page_offset = 1;
			$page_count = $page_offset + count($this->data_array['invoice_pages']);
			$i = $page_offset;
			foreach($this->data_array['invoice_pages'] as &$invoice_page)
			{
				$i++;
				$invoice_page['page_number'] = $i;
				$invoice_page['page_count'] = $page_count;
			}

			// set page count for any special pages
			$this->data["page_count"] = $page_count;
			
			unset($this->data_array['invoice_items']);
			
		}
		
		//print("<pre>".print_r($temp_data,true)."</pre>");
		//exit("<pre>".print_r($this->data,true)."</pre>");
	}
	
	
	/*
		generate_pdf()

		Generate a PDF file and stores it in $this->output.

		Returns
		0		failure
		1		success
	*/
	
	function generate_pdf()
	{
		log_debug("template_engine_htmltopdf", "Executing generate_pdf()");
		//unset($_SESSION);

		/*
			Generate temporary file for template
		*/

		// generate unique tmp filename
		$tmp_filename = file_generate_name("/tmp/amberdms_billing_system");
		
		
		// write out template data
		if (!$handle = fopen("$tmp_filename.html", "w"))
		{	
			log_write("error", "template_engine_htmltopdf", "Failed to create temporary file ($tmp_filename.html) with template data");
			return 0;
		}
		
		$directory_prefix = $tmp_filename."_"; 
		//$directory_prefix = "https://devel-web-tom.local.amberdms.com/development/amberdms/oss-amberdms-bs/trunk/templates/ar_invoice/ar_invoice_htmltopdf_telcostyle/";
		
		foreach((array)$this->processed as $key => $processed_row)
		{	
			$this->processed[$key] = str_replace("(tmp_filename)", $directory_prefix, $processed_row);
		}
		//print(implode("",$this->processed));
		//log_debug_render();
		//exit();
		foreach ($this->processed as $line)
		{
			if (fwrite($handle, $line) === FALSE)
			{
				log_write("error", "template_engine_htmltopdf", "Error occured whilst writing file ($tmp_filename.tex)");
				return 0;
			}
		}
 
		fclose($handle);



		/*
			Create Directory for HTML data files & copy them over
		*/

		$tmp_data_directory = $tmp_filename ."_html_data";
		
		mkdir($tmp_filename ."_html_data", 0700);
						
		$data_directory_items = glob($this->template_directory."/html_data/*");
		
		foreach((array) $data_directory_items as $data_dir_file) {
			$filename = basename($data_dir_file);
			$new_file_path =  $tmp_data_directory."/".$filename;
			copy($data_dir_file, $new_file_path);
		}
																				

		/*
			Process with wkhtmltopdf

			Note: In future, this may be extended to support alternative HTML to PDF rendering engines
		*/

		// get the current directory so that we can change back after switching to the tmp directory
		$run_directory = getcwd();


		$app_wkhtmltopdf = sql_get_singlevalue("SELECT value FROM config WHERE name='APP_WKHTMLTOPDF' LIMIT 1");

		if (!is_executable($app_wkhtmltopdf))
		{
			log_write("error", "process", "You have selected a template that requires the wkhtmltopdf engine, however $app_wkhtmltopdf does not exist or is not executable by your webserver process");
			return 0;
		}
	
		chdir("/tmp");		// TODO: fix this to be a configurable value
		exec("$app_wkhtmltopdf -B 5mm -L 5mm -R 5mm -T 5mm $tmp_filename.html $tmp_filename.pdf 2>&1", $output);

		foreach ($output as $line)
		{
			log_debug("template_engine_htmltopdf", "wkhtmltopdf: $line");
		}

		if (in_array("wkhtmltopdf: cannot connect to X server", $output))
		{
			log_debug("template_engine_htmltopdf", "Known fault on older systems, unable to run wkhtmltopdf without X server instance");

			/*
				On older distribution versions of wkhtmltopdf, the shipped version of wkhtmltopdf/QT doesn't
				work without a running X server.

				This was fixed by patching both QT and wkhtmltopdf in later releases, however most distributions
				don't appear to go to the extend of shipping this patched QT version. On RHEL platforms (as of RHEL 4/5/6), there is
				no wkhtmltopdf, so we just ship a good package, on Ubuntu (as of 10.04 lts) where is the more limited package
				we execute a wrapper script which runs a short lived Xorg session in a virtual framebuffer.

				It's not perfect, in testing the PDFs are rendering nicely, apart from the page size being incorrect, regardless what paramaters
				are passed to it - hence, we give the user a notification warning, so they know why the invoices are weird and how to fix it.

				If we have numerious problems with a popular platform, then it will be worth Amberdms building packages for that platform, but
				it's not a small effort.

				TODO: Maybe we should have an external/natives/ static binary directory for some key essentials for common architectures?
			*/

			$install_directory	= dirname( __FILE__ );
			$legacy_wrapper_cmd	= "$install_directory/../../external/legacy/wkhtmltopdf_x11wrap.sh $tmp_filename.html $tmp_filename.pdf 2>&1";

			log_debug("template_engine_htmltopdf", "Executing $legacy_wrapper_cmd");

			$output = array();
			exec($legacy_wrapper_cmd, $output);
	
			foreach ($output as $line)
			{
				log_debug("template_engine_htmltopdf", "wkhtmltopdf (legacy wrapper): $line");
			}

			log_write("notification", "template_engine_htmltopdf", "Warning: Your server has a older/limited version of wkhtmltopdf installed, this can cause some performance and page-size rendering issues. If these cause you major issues, consider obtaining the static binary version and adjusting the configured executable path. A static version can be found on the wkhtmltopdf developer's website at http://code.google.com/p/wkhtmltopdf/downloads/list ");
		}



		/*
			check that a PDF was generated
		*/
		if (file_exists("$tmp_filename.pdf"))
		{
			log_debug("template_engine_htmltopdf", "Temporary PDF $tmp_filename.pdf generated");
			
			// import file data into memory
			
			$this->output = file_get_contents("$tmp_filename.pdf");

			// remove temporary files from disk
			@ unlink("$tmp_filename");
			@ unlink("$tmp_filename.aux");
			@ unlink("$tmp_filename.log");
			@ unlink("$tmp_filename.html");
			@ unlink("$tmp_filename.pdf");

			// cleanup texlive home directory
			system("rm -rf ". $tmp_filename ."_html_data");

			// return back to original directory
			chdir($run_directory);

			return 1;
		}
		else
		{
			log_write("error", "template_engine_htmltopdf", "Unable to use wkhtmltopdf ($app_wkhtmltopdf) to generate PDF file");

			// return back to original directory
			chdir($run_directory);

			return 0;
		}
		
	} // end of generate_pdf


	
} // end of template_engine_htmltopdf class


?>
