$(function() {
    $('input#domain_name').attr('autocomplete', 'off');
});

$(document).ready(function()
{
	$('input#domain_name').keyup(function()
	{
		if($(this).val().length >= 3)
		{
			$.get("domains/domains-ajax.php", {domain_name: $(this).val()}, function(data)
			{
				$("#domains").html(data);
			});
		}
	});
});