/*
	include/javascript/domain_records.js

	Provides logic for adding additional fields for the records page when required.
*/

var num_records_mx;
var num_records_custom;

$(document).ready(function()
{
	/*
		When any element in the last row is changed (therefore, having data put into it), call a function to create a new row
	*/
	num_records_mx = $("input[name='num_records_mx']").val();
	num_records_custom = $("input[name='num_records_custom']").val();
	
	$("select[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("input[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);
	$("textarea[name^='record_mx_" + (num_records_mx-1) + "']").change(add_recordrow_mx);

	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);

});




/*
	Add new form rows for MX records
*/
function add_recordrow_mx()
{
	previous_row	= $("input[name='record_mx_" + (num_records_mx-1) + "_prio']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_prio']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_prio").val("");
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_ttl']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_ttl").val("");
	$(new_row).children().children("input[name^='record_mx_" + (num_records_mx-1) + "_content']").removeAttr("name").attr("name", "record_mx_" + num_records_mx + "_content").val("");
	
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
	add_recordrow_custom

	Any other type of DNS record, such as A, AAAA, PTR, etc
*/
function add_recordrow_custom()
{
	previous_row	= $("input[name='record_custom_" + (num_records_custom-1) + "_name']").parent().parent();
	new_row		= $(previous_row).clone().insertAfter(previous_row);

	$(new_row).children().children("select[name^='record_custom_" + (num_records_custom-1) + "_type']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_type");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_name']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_name").val("");
	$(new_row).children().children("input[name^='record_custom_" + (num_records_custom-1) + "_content']").removeAttr("name").attr("name", "record_custom_" + num_records_custom + "_content").val("");
	
	//remove function calls from previous row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change", add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").unbind("change");
	
	//add one to num_tran
	num_records_custom++;
	$("input[name='num_records_custom']").val(num_records_custom);
	
	//add function calls to new row
	$("select[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("input[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);
	$("textarea[name^='record_custom_" + (num_records_custom-1) + "']").change(add_recordrow_custom);	
}



