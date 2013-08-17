<?php
/*
	inc_file_uploads.php

	Provides the file_storage class for uploading files or large amounts of
	BLOB data to a configurable location (such as filesystem or database).


	FILE STORAGE

	AMBERPHPLIB allows for two different storage methods of uploaded files:

	1. Upload all files to a directory on the webserver

		Advantages:
			* Keeps the SQL database small, which increases performance and the
			  small size makes backups faster.
			* Work well in a hosting cluster if using a shared storage device.
			* More efficent to backup, due to ability to rsync or transfer increments.
			* No limits to the filesize, except for limits imposed by network connection speed and HTTP timeout.

	2. Upload all files into the MySQL database as binary blobs

		Advantages:
			* All the data is in a single location
			* Single location to backup
			* Easier security controls.
			* If you have a replicating MySQL setup, the files will be replicated
			  as well.

	Either way the file_uploads database is used to store information about the file, such as it's size
	and filename, but the actual data will be pulled from the chosen location.

	The following configuration options need to be defined in the config table:
	name				value
	--
	DATA_STORAGE_METHOD		database


	or

	name				value
	--
	DATA_STORAGE_METHOD		filesystem
	DATA_STORAGE_LOCATION		data/default/
	
*/




/*
	class file_storage

	Provides functions for uploading files or large amounts of
	BLOB data to a configurable location (such as filesystem or database).

*/
class file_storage
{
	var $config;	// array holding some desired configuration information

	var $id;	// file ID
	var $data;	// array holding information about the file.


	/*
		file_storage

		Constructor function
	*/
	function file_storage()
	{
		$this->config["data_storage_method"]	= sql_get_singlevalue("SELECT value FROM config WHERE name='DATA_STORAGE_METHOD' LIMIT 1");
		$this->config["data_storage_location"]	= sql_get_singlevalue("SELECT value FROM config WHERE name='DATA_STORAGE_LOCATION' LIMIT 1");
		$this->config["upload_maxbytes"]	= sql_get_singlevalue("SELECT value FROM config WHERE name='UPLOAD_MAXBYTES' LIMIT 1");
	}


	/*
		load_data

		Fetches the information for the selected file.

		Return codes:
		0	failure to find record
		1	success
	*/
	function load_data()
	{
		log_debug("file_storage", "Executing load_data()");

		// fetch file metadata
		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT customid, file_name, file_size, file_location FROM file_uploads WHERE id='". $this->id ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			// fetch data
			$sql_obj->fetch_array();

			$this->data["customid"]		= $sql_obj->data[0]["customid"];
			$this->data["file_name"]	= $sql_obj->data[0]["file_name"];
			$this->data["file_size"]	= $sql_obj->data[0]["file_size"];
			$this->data["file_size_human"]	= format_size_human($sql_obj->data[0]["file_size"]);
			$this->data["file_location"]	= $sql_obj->data[0]["file_location"];

			return 1;
		}

		return 0;
	}

	
	/*
		load_data_bytype

		Loads the information for the file with the specified type and customid. This is used
		by functions which don't know the ID of the file, but do know the ID of the record
		that the file belongs to. (eg: the journal functions)

		Return codes:
		0	failure to find record
		1 	success
	*/
	function load_data_bytype()
	{
		log_debug("file_storage", "Executing load_data_bytype()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "SELECT id, file_name, file_size, file_location FROM file_uploads WHERE type='". $this->data["type"] ."' AND customid='". $this->data["customid"] ."' LIMIT 1";
		$sql_obj->execute();

		if ($sql_obj->num_rows())
		{
			// fetch data
			$sql_obj->fetch_array();
			
			$this->id			= $sql_obj->data[0]["id"];

			$this->data["file_name"]	= $sql_obj->data[0]["file_name"];
			$this->data["file_size"]	= $sql_obj->data[0]["file_size"];
			$this->data["file_size_human"]	= format_size_human($sql_obj->data[0]["file_size"]);
			$this->data["file_location"]	= $sql_obj->data[0]["file_location"];

			return 1;
		}

		return 0;
	}



	/*
		verify_upload_form

		Verifies that supplied form field file upload confirms to all requirements
		and is within configured limits for uploading.

		Values
		fieldname		Name of the POST field
		acceptable_formats	(optional) array of formats which are acceptable for upload
	*/
	function verify_upload_form($fieldname, $acceptable_formats = NULL)
	{
		log_write("debug", "file_storage", "Executing verify_upload_form($fieldname, Array)");

		// make sure a file has been provided.
		if (!$_FILES[ $fieldname ]['size'])
		{
			// no file provided - maybe it hit the PHP max?
			switch ($_FILES[ $fieldname ]["error"])
			{
				case UPLOAD_ERR_INI_SIZE:
					log_write("error", "file_storage", "File upload was in excess of maximum PHP limit of ". ini_get('upload_max_filesize') ."");
				break;

				case UPLOAD_ERR_NO_FILE:
					log_write("error", "file_storage", "No file supplied for upload.");
				break;

				default:
					log_write("error", "file_storage", "Unexpected upload error: ". $_FILES[ $fieldname ]["error"] ."");
				break;
			}


			// return failure
			$_SESSION["error"]["$fieldname-error"] = 1;
			return 0;
		}

		// check the filesize is less than or equal to the max upload size
		if ($_FILES[ $fieldname ]['size'] >= $this->config["upload_maxbytes"])
		{
			$filesize_max_human	= format_size_human($this->config["upload_maxbytes"]);
			$filesize_upload_human	= format_size_human($_FILES[ $fieldname ]['size']);	
	
			log_write("error", "file_storage", "Files must be no larger than $filesize_max_human. You attempted to upload a $filesize_upload_human file.");
			$_SESSION["error"]["$fieldname-error"] = 1;

			return 0;
		}

		// check if the upload format is acceptable
		if ($acceptable_formats)
		{
			if (!in_array(format_file_extension($_FILES[ $fieldname ]["name"]), $acceptable_formats))
			{
				log_write("error", "file_storage", "Unsupported file format, only the following file formats are acceptable: ". format_arraytocommastring($acceptable_formats) );
				$_SESSION["error"]["$fieldname-error"] = 1;

				return 0;
			}
		}


		// no problems
		return 1;
	}


	
	/*
		action_create

		Creates a place holder in the database for the uploaded file which is then used by the action_update
		fuction to upload the file data.

		Results
		0	failure
		#	ID of the new metadata entry
	*/
	function action_create()
	{
		log_write("debug", "file_storage", "Executing action_create()");

		$sql_obj		= New sql_query;
		$sql_obj->string	= "INSERT INTO file_uploads (customid, type) VALUES ('". $this->data["customid"] ."', '". $this->data["type"] ."')";
			
		if (!$sql_obj->execute())
		{
			return 0;
		}

		$this->id = $sql_obj->fetch_insert_id();

		return $this->id;

	} // end of action_create



	/*
		action_update_form

		Uploads a new file from a POST form. (will over-write an existing file if $this->id is supplied)

		Values
		fieldname	Name of the POST form field

		Results
		0		failure
		#		ID of the successfully uploaded file.
	*/
	function action_update_form($fieldname)
	{
		log_write("debug", "file_storage", "Executing action_update_form($fieldname)");


		/*
			Start SQL Transaction
		*/

		$sql_obj = New sql_query;
		$sql_obj->trans_begin();


		/*
			If no ID exists, create a new file entry first
		*/
		if (!$this->id)
		{
			if (!$this->action_create())
			{
				$sql_obj->trans_rollback();

				log_write("error", "file_storage", "Unexpected DB error whilst attempting to create a new file metadata entry");
				return 0;
			}
		}


		/*
			Fetch file metadata - most of the information we want about the file
			is provided from the POST upload.
		*/

		$this->data["file_size"]		= $_FILES[ $fieldname ]["size"];

		if (!$this->data["file_name"])
		{
			// no filename supplied, take the filename of the uploaded fie
 			$this->data["file_name"]	= $_FILES[ $fieldname ]["name"];
		}




		if ($this->config["data_storage_method"] == "filesystem")
		{
			/*
				Upload file to configured location on filesystem

				For this, we simply need to copy the temporary file that has been uploaded to the new location.
			*/
			$uploadname = $this->config["data_storage_location"] ."/". $this->id;
			
			if (!copy($_FILES[$fieldname]["tmp_name"],  $uploadname))
			{
				log_write("error", "file_storage", "Unable to upload file to filesystem storage location ('$uploadname')- possible permissions issue.");
			}
			
			$this->data["file_location"] = "fs";

		}
		elseif ($this->config["data_storage_method"])
		{
			/*
				Upload file to database

				We need to split the file into 64kb chunks, and add a new row to the file_upload_data table for	each chunk - by splitting
				the file we reduce memory usage when retrieving the file data as well as supporting standard MySQL database configurations.
			*/

			if (!file_exists($_FILES[$fieldname]["tmp_name"]))
			{
				log_write("error", "file_storage", "Uploaded file ". $_FILES[$fieldname]["tmp_name"] ." was not found and could not be loaded into the database");
			}
			else
			{
				// delete any existing files from the database
				$sql_obj->string = "DELETE FROM file_upload_data WHERE fileid='". $this->id ."'";
				$sql_obj->execute();
			
				
				// open the file - read only & binary
        			$file_handle = fopen($_FILES[$fieldname]["tmp_name"], "rb");

			    	while (!feof($file_handle))
			   	{
					// make the data safe for MySQL, we don't want any
					// SQL injections from file uploads!
		        
					$binarydata = addslashes(fread($file_handle, 65535));


					// upload the row
					// note that the ID of the rows will increase, so if we sort the rows
					// in ascenting order, we will recieve the correct data.
					$sql_obj->string = "INSERT INTO file_upload_data (fileid, data) values ('". $this->id ."', '". $binarydata ."')";
					$sql_obj->execute();
				}

				// close the file
				fclose($file_handle);
			}
			

			$this->data["file_location"] = "db";
		}
		else
		{
			log_write("error", "file_storage", "Invalid data_storage_method (". $this->config["data_storage_method"] .") configured, unable to upload file.");
		}


		// update database record
		$sql_obj->string	= "UPDATE file_uploads SET "
						."timestamp='". time() ."', "
						."file_name='". $this->data["file_name"] ."', "
						."file_size='". $this->data["file_size"] ."', "
						."file_location='". $this->data["file_location"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";

		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "file_storage", "An error occured whilst attempting to upload the file, no changes have been made.");
			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("debug", "file_storage", "Successfully uploaded file ID '". $this->id ."'");
			return $this->id;
		}

	} // end of action_update_form





	/*
		action_update_file

		Uploads a new file from another file on the local server filesystem.

		Values
		filepath	File name & path of the file to upload

		Results
		0		failure
		#		ID of the successfully uploaded file.
	*/
	function action_update_file($filepath)
	{
		log_write("debug", "file_storage", "Executing action_update_file($filepath)");


		/*
			Make sure the supplied file exists
		*/
		if (!file_exists($filepath))
		{
			log_write("error", "file_storage", "The requested file $filepath does not exist, unable to upload new file.");
			return 0;
		}


		/*
			Start SQL Transaction
		*/

		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			If no ID exists, create a new file entry first
		*/
		if (!$this->id)
		{
			if (!$this->action_create())
			{
				$sql_obj->trans_rollback();

				log_write("error", "file_storage", "Unexpected DB error whilst attempting to create a new file metadata entry");
				return 0;
			}
		}



		/*
			Fetch metadata from file
		*/

		$this->data["file_size"]	= filesize($filepath);

		if (empty($this->data["file_name"]))
		{
			// no filename supplied, take the filename of the provided file path
 			$this->data["file_name"] = format_file_name($filepath);
		}


		if ($this->config["data_storage_method"] == "filesystem")
		{
			/*
				Upload file to configured location on filesystem

				For this, we simply need to copy the temporary file that has been uploaded to the new location.
			*/
			$uploadname = $this->config["data_storage_location"] ."/". $this->id;
			
			if (!copy($filepath,  $uploadname))
			{
				log_write("error", "file_storage", "Unable to upload file to filesystem storage location ('$uploadname')- possible permissions issue.");
			}
			
			$this->data["file_location"] = "fs";

		}
		elseif ($this->config["data_storage_method"])
		{
			/*
				Upload file to database

				We need to split the file into 64kb chunks, and add a new row to the file_upload_data table for	each chunk - by splitting
				the file we reduce memory usage when retrieving the file data as well as supporting standard MySQL database configurations.
			*/

			
			// delete any existing files from the database
			$sql_obj->string = "DELETE FROM file_upload_data WHERE fileid='". $this->id ."'";
			$sql_obj->execute();
			
				
			// open the file - read only & binary
        		$file_handle = fopen($filepath, "rb");

		    	while (!feof($file_handle))
		   	{
				// make the data safe for MySQL, we don't want any
				// SQL injections from file uploads!
		        
				$binarydata = addslashes(fread($file_handle, 65535));


				// upload the row
				// note that the ID of the rows will increase, so if we sort the rows
				// in ascenting order, we will recieve the correct data.
				$sql_obj->string = "INSERT INTO file_upload_data (fileid, data) values ('". $this->id ."', '". $binarydata ."')";
				$sql_obj->execute();
			}

			// close the file
			fclose($file_handle);

			$this->data["file_location"] = "db";
		}
		else
		{
			log_write("error", "file_storage", "Invalid data_storage_method (". $this->config["data_storage_method"] .") configured, unable to upload file.");
		}


		// update database record
		$sql_obj->string	= "UPDATE file_uploads SET "
						."timestamp='". time() ."', "
						."file_name='". $this->data["file_name"] ."', "
						."file_size='". $this->data["file_size"] ."', "
						."file_location='". $this->data["file_location"] ."' "
						."WHERE id='". $this->id ."' LIMIT 1";

		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "file_storage", "An error occured whilst attempting to upload the file, no changes have been made.");
			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("debug", "file_storage", "Successfully uploaded file ID '". $this->id ."'");
			return $this->id;
		}

	} // end of action_update_file





	/*
		action_update_var

		Uploads the provided file in the form of BLOB data provided as a string.

		This function works by writing the blob to a tempory file before uploading, as this solves various
		issues with splitting the data into chunks & correctly reading the file size.

		Values
		blob		file data

		Results
		0		failure
		#		ID of the successfully uploaded file.
	*/
	function action_update_var($blob)
	{
		log_write("debug", "file_storage", "Executing action_update_var(BLOB)");


		/*
			Create temp file
		*/

		$temp_file = file_generate_tmpfile();


		/*
			Write blob to disk
		*/
		
		if (!$fhandle = fopen($temp_file, "w"))
		{
			log_write("error", "file_storage", "Unable to open output file '$temp_file' for writing.");
			return 0;
		}
	
		// write all blob data
		fwrite($fhandle, $blob);
		
		// close the output file
		fclose($fhandle);



		/*
			Process with action_update_file
		*/
		if ($this->action_update_file($temp_file))
		{
			// clean up temp file
			unlink($temp_file);

			return $this->id;
		}
		else
		{
			unlink($temp_file);

			return 0;
		}

	} // end of action_update_var





	/*
		action_delete

		Deletes the selected file. (based on $this->id)

		Return code:
		0	failure
		1	success
	*/
	function action_delete()
	{
		log_write("debug", "file_storage", "Executing action_delete()");


		/*
			Start SQL Transaction
		*/
		$sql_obj = New sql_query;
		$sql_obj->trans_begin();



		/*
			Remove file data
		*/		
		if ($this->data["file_location"] == "db")
		{
			// delete file from the database
			$sql_obj->string = "DELETE FROM file_upload_data WHERE fileid='". $this->id ."'";
			$sql_obj->execute();
		}
		else
		{
			// delete file from the filesystem
			$file_path = $this->config["data_storage_location"] . "/". $this->id;
			@unlink($file_path);
		}


		/*
			Remove metadata information from database
		*/
		$sql_obj->string	= "DELETE FROM file_uploads WHERE id='". $this->id . "'";
		$sql_obj->execute();



		/*
			Commit
		*/

		if (error_check())
		{
			$sql_obj->trans_rollback();

			log_write("error", "file_storage", "An error occured whilst attempting to upload the file, no changes have been made.");
			return 0;
		}
		else
		{
			$sql_obj->trans_commit();

			log_write("debug", "file_storage", "Successfully deleted file ID '". $this->id ."'");
			return $this->id;
		}

	}
	


	/*
		filedata_render()

		This function outputs all the data for the file, including content headers. This function
		should be called by download scripts that output nothing other than this function.

		NOTE: One of the load_data functions needs to be called before executing this function, as
		we need to have metadata in order to correctly output the data.

		Return codes:
		0	failure
		1	success
	*/
	
	function filedata_render()
	{
		log_write("debug", "file_storage", "Executing filedata_render()");

		// the metadata is vital, so die if we don't have it
		if (!$this->id || !$this->data["file_size"] || !$this->data["file_name"] || !$this->data["file_location"])
		{
			die("load_data or load_data_bytype must be called before executing filedata_render!");
		}



		/*
			Setup HTTP headers we need
		*/
		
		// required for IE, otherwise Content-disposition is ignored
		if (ini_get('zlib.output_compression'))
			ini_set('zlib.output_compression', 'Off');

		
		// set the relevant content type
		$file_extension = format_file_extension($this->data["file_name"]);
		$ctype		= format_file_contenttype($file_extension);
		
		header("Pragma: public"); // required
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false); // required for certain browsers 
		header("Content-Type: $ctype");
		
		header("Content-Disposition: attachment; filename=\"".basename($this->data["file_name"])."\";" );
		header("Content-Transfer-Encoding: binary");
		
		// tell the browser how big the file is (in bytes)
		// most browers seem to ignore this, but it's vital in order to make IE 7 work.
		header("Content-Length: ". $this->data["file_size"] ."");

		
		
		/*
			Output File Data

			Each file in the DB has a field to show where the file is located, so if a user
			has some files on disk and some in the DB, we can handle it accordingly.
		*/

		log_write("debug", "file_storage", "Fetching file ". $this->id ." from location ". $this->data["file_location"] ."");
		
		if ($this->data["file_location"] == "db")
		{
			/*
				Fetch file data from database
			*/
		
			// fetch a list of all the rows with file data from the file_upload_data directory
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT id FROM file_upload_data WHERE fileid='". $this->id ."' ORDER BY id";
			$sql_obj->execute();

			if (!$sql_obj->num_rows())
			{
				die("No data found for file". $this->id ."");
			}

			$sql_obj->fetch_array();


			// create an array of all the IDs
			$file_data_ids = array();
			foreach ($sql_obj->data as $data_sql)
			{
				$file_data_ids[] = $data_sql["id"];
			}

			
			// fetch the data for each ID
			foreach ($file_data_ids as $id)
			{
				$sql_obj		= New sql_query;
				$sql_obj->string	= "SELECT data FROM file_upload_data WHERE id='$id' LIMIT 1";
				$sql_obj->execute();
				$sql_obj->fetch_array();

				print $sql_obj->data[0]["data"];
			}
		}
		else
		{
			/*
				Output data from filesystem
			*/

			$file_path = $this->config["data_storage_location"] . "/". $this->id;
			
			if (file_exists($file_path))
			{
				readfile($file_path);
			}
			else
			{
				die("FATAL ERROR: File ". $this->id . " $file_path is missing or inaccessible.");
			}
		}


		return 1;

	} // end of filedata_render




	/*
		filedata_var

		This function returns only the file data as a single string value - useful for rendering small
		files, but not recommended for using with large files due to memory consumption.

		NOTE: One of the load_data functions needs to be called before executing this function, as
		we need to have metadata in order to correctly output the data.

		Return codes:
		0	failure
		BLOB	Requested file data
	*/

	function filedata_var()
	{
		log_write("debug", "file_storage", "Executing filedata_var()");

		// the metadata is vital, so die if we don't have it
		if (!$this->id || !$this->data["file_size"] || !$this->data["file_name"] || !$this->data["file_location"])
		{
			die("load_data or load_data_bytype must be called before executing filedata_var!");
		}



		/*
			Output File Data

			Each file in the DB has a field to show where the file is located, so if a user
			has some files on disk and some in the DB, we can handle it accordingly.
		*/

		$return_string = NULL;

		log_write("debug", "file_storage", "Fetching file ". $this->id ." from location ". $this->data["file_location"] ."");
		
		if ($this->data["file_location"] == "db")
		{
			/*
				Fetch file data from database

				Because we are loading all the data into memory, we can assume that this is a small file
				and execute a single SQL query to fetch all the information.

				This consumes more memory, but reduces the number of SQL queries made dramaticly.
			*/
		

			// fetch a list of all the rows with file data from the file_upload_data directory
			$sql_obj		= New sql_query;
			$sql_obj->string	= "SELECT data FROM file_upload_data WHERE fileid='". $this->id ."' ORDER BY id";
			$sql_obj->execute();

			if (!$sql_obj->num_rows())
			{
				die("No data found for file". $this->id ."");
			}
			

			$sql_obj->fetch_array();

			foreach ($sql_obj->data as $data_sql)
			{
				$return_string .= $data_sql["data"];
			}
		}
		else
		{
			/*
				Output data from filesystem
			*/

			$file_path = $this->config["data_storage_location"] . "/". $this->id;
			
			if (file_exists($file_path))
			{
				$return_string = file_get_contents($file_path);
			}
			else
			{
				die("FATAL ERROR: File ". $this->id . " $file_path is missing or inaccessible.");
			}
		}

		// return the data
		return $return_string;

	} // end of filedata_var



	/*
		filedata_write

		Writes the contents of the file data to a file on the server's filesystem. This is useful when generating documents and need
		to include images out of the file upload system.

		NOTE: One of the load_data functions needs to be called before executing this function, as
		we need to have metadata in order to correctly output the data.

		Fields
		filename	File to write to.

		Return codes:
		0		failure
		1		success
	*/
	function filedata_write($filename)
	{
		log_write("debug", "file_storage", "Executing filedata_write($filename)");

		// the metadata is vital, so die if we don't have it
		if (!$this->id || !$this->data["file_size"] || !$this->data["file_name"] || !$this->data["file_location"])
		{
			die("load_data or load_data_bytype must be called before executing filedata_var!");
		}

		if (!$filename)
		{
			die("A filename must be supplied to the write_filedata function!");
		}

	

		
		/*
			Output File Data

			Each file in the DB has a field to show where the file is located, so if a user
			has some files on disk and some in the DB, we can handle it accordingly.
		*/

		log_write("debug", "file_storage", "Fetching file ". $this->id ." from location ". $this->data["file_location"] ."");
		
		if ($this->data["file_location"] == "db")
		{
			// open output file
			if (!$fhandle = fopen($filename, "w"))
			{
				log_write("error", "file_storage", "Unable to open output file \"$filename\" for writing.");
				return 0;
			}
	
		
			/*
				Fetch file data from database
			*/
		
			// fetch a list of all the rows with file data from the file_upload_data directory
			$sql_obj = New sql_query;
			$sql_obj->string = "SELECT id FROM file_upload_data WHERE fileid='". $this->id ."' ORDER BY id";
			$sql_obj->execute();

			if (!$sql_obj->num_rows())
			{
				die("No data found for file ". $this->id ."");
			}

			$sql_obj->fetch_array();

			// create an array of all the IDs
			$file_data_ids = array();
			foreach ($sql_obj->data as $data_sql)
			{
				$file_data_ids[] = $data_sql["id"];
			}

			
			// fetch the data for each ID
			foreach ($file_data_ids as $id)
			{
				$sql_obj = New sql_query;
				$sql_obj->string = "SELECT data FROM file_upload_data WHERE id='$id' LIMIT 1";
				$sql_obj->execute();
				$sql_obj->fetch_array();

				fwrite($fhandle, $sql_obj->data[0]["data"]);
			}
		
			// close the output file
			fclose($fhandle);
		}
		else
		{
			/*
				Output data from filesystem
			*/
			// get file from filesystem
			$file_path = $this->config["data_storage_location"] . "/". $this->id;
			
			if (file_exists($file_path))
			{
				// copy the file
				if (!copy($file_path, $filename))
				{
					log_write("error", "file_storage", "A fatal error occured trying to copy file \"$file_path\" to \"$filename\"");
					return 0;
				}
			}
			else
			{
				die("FATAL ERROR: File ". $this->id . " $file_path is missing or inaccessible.");
			}
		}


		return 1;

	} // end of filedata_write


} // end of file_storage class




?>
