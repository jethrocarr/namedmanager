/*
	include/javascript/domain_records.js

	Provides logic for adding additional fields for the records page when required.
*/

var num_records_mx;
var num_records_ns;
var num_records_custom;


/*
	Page Load
*/

$(document).ready(function()
{

	/*
		Load in the records via ajax at page load
		Loads record for the current domain, and starts at page 1
	*/

	id_domain = $("input:[name='id_domain']").val();

	load_domain_records_custom(id_domain, 1);


	/* 
		When a pagination item is clicked, load the records page in via ajax
	*/

	$("a[id^=pagination]").live("click", function()
	{ 
		load_domain_records_custom(id_domain, $(this).attr('id').replace('pagination_',''), get_custom_form_data()); 
	});


	/*
		As soon as a form element is changed, declare the form status as 0 (requiring validation)
	*/

	$(":input[name^='record_custom_']").live("change", function()
	{
		// $("form[name='domain_records']").attr('validated', false);
		$(":input[name='record_custom_status']").val("0");
	});


	/*
		Intercept the form submit button. 

		* Intercept submit action
		* Validate current custom records - we do this to enforce UI consistency, so any issues with custom
		  records get adddressed in the same place.
		* Submit (POST) form to the processing page for *secure* validation and processing.
	*/

	$("form[name='domain_records']").submit(function(e)
	{
		console.log('Processing form upon submit click');


		// we need to validate the form - this is not a secure check, since a user could have
		// changed this value - but that's OK, since we validate properly on the paste.
		//
		// this step is also important, since it loads the currently displayed records into $_SESSION
		// so we can process it and any other records at submit time.
		// 

		$("#domain_records_custom_loading").show();

		$.ajax({ 
			data: get_custom_form_data(),
			type: 'POST', 
			async: false,
			dataType: 'html',
			url: "domains/records-ajax.php?id=" + id_domain + "&pagination=" + record_custom_page,
			success: function(res){

				console.log('ajax response has returned');

				$('#domain_records_custom').html(res);
				after_load_domain_records_custom();


				if($(":input[name='record_custom_status']").val() == 1)
				{
					// validation successful
					console.log('Validation successful: the form record custom status is 1');

					// unbind the submit hooks - if we don't, we'll keep looping through this same
					// function forever
					$("form[name='domain_records']").unbind('submit');
				}
				else
				{
					// validation failed
					console.log('Validation failed: the form record custom status is 0');

					// prevent the form from submitting
					e.preventDefault();
				}
			}

		});


		// return true, regardless of validation state
		return true;

	}); // end if submit



	/*
		When any element in the last row is changed (therefore, having data put into it), call a function to create a new row
	*/
	num_records_ns = $("input[name='num_records_ns']").val();
	num_records_mx = $("input[name='num_records_mx']").val();

	$("select[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);

	$("select[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);

	
	/*
	 * 	Attach delete function to mouse click on delete link
	 */
	$(".delete_undo").live("click", function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
	
	$(".delete_undo").live("select", function(){
		var cell = $(this).parent();
		delete_undo_row(cell);
		return false;
	});
	
	/*
	 * 	Change columns for custom records on change
	 */
	$("select[name^='record_custom_']").live("change", function()
	{	
		if ($(this).val() == "CNAME")
		{
			$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
			change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name for CNAME, eg www");
			change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Hostname or FQDN of target record");
		}
		else
		{
			$(this).parent().siblings().children("input[name$='_ttl']").removeAttr("disabled");
			$(this).parent().siblings().children("input[name$='_reverse_ptr']").removeAttr("disabled");
			change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name, eg www");
			change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Target IP, eg 192.168.0.1");
		}

		if ($(this).val() != "A" && $(this).val() != "AAAA")
		{
			$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
		}
	});

});


/*
	load_domain_records_custom
*/
function load_domain_records_custom(id_domain, page, data) 
{
	
	/*
		Load in the records via ajax at page load
	*/

	if($("#domain_records_custom").html() == "") {
		$("#domain_records_custom").html('<tr><td><img src="images/wait20.gif" /></td></tr>');
	} else {
		$("#domain_records_custom_loading").show();
	}

	if(!data) {
		var data = new Array();
	}

	$.post("domains/records-ajax.php?id=" + id_domain + "&pagination=" + page, data, function(res) {
			$('#domain_records_custom').html(res);
			after_load_domain_records_custom();
	});

}

/*
function submit_callback() {

	if($(":input[name='record_custom_status']").val() == 1) {
		alert('forms are good, off we go');
		// $("form[name='domain_records']").submit();
		return true;
	} else {
		alert('forms are bad');
		return false;
	}

}
*/



/*
	after_load_domain_records_custom

	Execute function after loading custom domain records to cleanly add a new row
	for adding additional custom domain records.
*/
function after_load_domain_records_custom(submit)
{
	$("#domain_records_custom_loading").hide();

	num_records_custom = $("input[name='num_records_custom']").val();
	record_custom_page = $("input:[name='record_custom_page']").val();
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);

}


/*
	get_custom_form_data

 	Get the custom form data and serialize it for AJAX POSTing - we use this approach to enable
	UI-side validation of form logic, by reading all the values of a form and then posting
	to the AJAX page.
*/
function get_custom_form_data()
{
	return $(":input[name^='record_custom_'],:input[name='num_records_custom']").serialize();
}




/*
	add_recordrow_mx

	Add new form rows for MX records
*/
function add_recordrow_mx()
{
	previous_row	= $("input[name='record_mx_" + (num_records_mx-1) + "_prio']").parent().parent();
	new_row			= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_prio']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_prio_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_prio";
	previous_string = "record_mx_" + (num_records_mx-1) + "_prio";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_ttl']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_ttl";
	previous_string = "record_mx_" + (num_records_mx-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");
	
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_content']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content");
	$(new_row).children().children("input[name='record_mx_" + (num_records_mx-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content_helpmessagestatus");
	new_string = "record_mx_" + (num_records_mx) + "_content";
	previous_string = "record_mx_" + (num_records_mx-1) + "_content";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_delete_undo']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_delete_undo").val("false");
	
	//remove function calls from previous row
	$("select[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change");
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change", add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_mx++;
	$("input[name='num_records_mx']").val(num_records_mx);
	
	//add function calls to new row
	$("select[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
}

/*
	add_recordrow_ns

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_ns()
{
	previous_row	= $("input[name='record_ns_" + (num_records_ns-1) + "_name']").parent().parent();
	new_row			= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_ttl']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_ttl");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_ttl_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_ttl";
	previous_string = "record_ns_" + (num_records_ns-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");	
	
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_name']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_name");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_name_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_name_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_name";
	previous_string = "record_ns_" + (num_records_ns-1) + "_name";
	dynamic_help_message(previous_string, new_string);	
	
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_content']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_content");
	$(new_row).children().children("input[name='record_ns_" + (num_records_ns-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_content_helpmessagestatus");
	new_string = "record_ns_" + (num_records_ns) + "_content";
	previous_string = "record_ns_" + (num_records_ns-1) + "_content";
	dynamic_help_message(previous_string, new_string);	
	
	$(new_row).children().children("input[name^='record_ns_" + (num_records_ns-1) + "_delete_undo']").removeAttr("name").attr("name", "record_ns_" + num_records_ns + "_delete_undo").val("false");
	
	//remove function calls from previous row
	$("select[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change");
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change", add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_ns++;
	$("input[name='num_records_ns']").val(num_records_ns);
	
	//add function calls to new row
	$("select[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("input[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);
	$("textarea[name^='record_ns_" + (num_records_ns-1) + "']").change(add_recordrow_ns);	
}


/*
	add_recordrow_custom

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_custom()
{

	previous_row	= $("input[name='record_custom_" + (num_records_custom-1) + "_name']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("select[name='record_custom_" + (num_records_custom-1) + "_type']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_type");
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_ttl']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_ttl");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_ttl_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_ttl_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_ttl";
	previous_string = "record_custom_" + (num_records_custom-1) + "_ttl";
	dynamic_help_message(previous_string, new_string, "true");
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_name']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_name_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_name";
	previous_string = "record_custom_" + (num_records_custom-1) + "_name";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_content']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_content_helpmessagestatus']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content_helpmessagestatus");
	new_string = "record_custom_" + (num_records_custom) + "_content";
	previous_string = "record_custom_" + (num_records_custom-1) + "_content";
	dynamic_help_message(previous_string, new_string);
	
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_delete_undo']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_delete_undo").val("false");
	$(new_row).children().children("input[name='record_custom_" + (num_records_custom-1) + "_reverse_ptr']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_reverse_ptr").attr('checked', false);
	
	//remove function calls from previous row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_custom++;
	$("input[name='num_records_custom']").val(num_records_custom);
	
	//add function calls to new row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(function()
			{
				if ($(this).val() == "CNAME")
				{
					$(this).parent().siblings().children("input[name$='_ttl']").attr("disabled", "disabled");
					$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
					change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name for CNAME, eg www");
					change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Hostname or FQDN of target record eg webserver1");
				}
				else
				{
					$(this).parent().siblings().children("input[name$='_ttl']").removeAttr("disabled");
					$(this).parent().siblings().children("input[name$='_reverse_ptr']").removeAttr("disabled");
					change_help_message($(this).parent().siblings().children("input[name$='_name']"), "Record name, eg www");
					change_help_message($(this).parent().siblings().children("input[name$='_content']"), "Target IP, eg 192.168.0.1");
				}

				if ($(this).val() != "A" && $(this).val() != "AAAA")
				{
					$(this).parent().siblings().children("input[name$='_reverse_ptr']").attr("disabled", "disabled");
				}

			});
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);	
}


/*
 * 	delete_row
 * 
 * 	grey out row and set hidden delete variable to true
 */
function delete_undo_row(cell)
{
	var status = $(cell).children("input").val();
	if (status == "false")
	{
		$(cell).siblings().fadeTo("slow", 0.1);
		$(cell).children(".delete_undo").children().html("undo");
		$(cell).children("input").val("true");
	}
	else if (status == "true")
	{
		$(cell).siblings().fadeTo("slow", 1);
		$(cell).children(".delete_undo").children().html("delete");
		$(cell).children("input").val("false");
	}
	
	$(":input[name='record_custom_status']").val("0");

}

function dynamic_help_message(previous_string, new_string, is_ttl)
{
	//check if ttl
	is_ttl = is_ttl || "false";
	//check if helpmessagestatus field is false (false means there is no help message)
	if ($("input[name='" + previous_string + "_helpmessagestatus']").val() != "false")
	{
	//if exists
		//set class to helpmessage
		$("input[name='" + new_string + "']").addClass("helpmessage");
		//check if helpmessagestatus is true (if true, value remains the same, therefore no modifications are needed
			if ($("input[name='" + previous_string + "_helpmessagestatus']").val() != "true")
			{
			//if not true
				//val becomes value of helpmessagestatus field
				$("input[name='" + new_string + "']").val($("input[name='" + previous_string + "_helpmessagestatus']").val());
				$("input[name='" + new_string + "_helpmessagestatus']").val("true");
			}


		//apply on click / blur functions
		/*
		$("input[name='" + new_string + "']").click(function()
		{
			var message = $(this).val();
			$(this).siblings("input[name$='helpmessagestatus']").val(message);
			$(this).val("").removeClass("helpmessage").blur(function()
			{
				if ($(this).val().length == 0)
				{
					$(this).addClass("helpmessage").val(message);
					$(this).siblings("input[name$='helpmessagestatus']").val("true");
				}
			});
		});
	*/	
		$("input[name='" + new_string + "']").select(function()
		{
			var message = $(this).val();
			$(this).siblings("input[name$='helpmessagestatus']").val(message);
			$(this).val("").removeClass("helpmessage").blur(function()
			{
				if ($(this).val().length == 0)
				{
					$(this).addClass("helpmessage").val(message);
					$(this).siblings("input[name$='helpmessagestatus']").val("true");
				}
			});
		});
	}
	//if does not exist
	else
	{
		if (is_ttl == "false")
		{
			//val set to ""
			$("input[name='" + new_string + "']").val("");
		}
	}
}

function change_help_message(field, message)
{
	if ($(field).hasClass("helpmessage"))
	{
		$(field).val(message);
		$(field).siblings("input[name$='helpmessagestatus']").val("true");
	}
	else
	{
		if ($(field).val().length < 1)
		{
			$(field).addClass("helpmessage").val(message);
			$(field).siblings("input[name$='helpmessagestatus']").val("true");
		}
		else
		{
			$(field).siblings("input[name$='helpmessagestatus']").val(message);
		}
	}
}
