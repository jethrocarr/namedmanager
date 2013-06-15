var message;

$(document).ready(function()
{
	$(".helpmessage").live("click", function()
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

		var autofill = $(this).siblings("input[name$='autofill']").val();

		if (autofill.length > 0)
		{
			$(this).val(autofill);
		}

	});
	
	$(".helpmessage").live("focusin", function()
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
		
		var autofill = $(this).siblings("input[name$='autofill']").val();

		if (autofill.length > 0)
		{
			$(this).val(autofill);
		}
	});
});

function obj_hide(obj)
{
	document.getElementById(obj).style.display = 'none';
}
function obj_show(obj)
{
	document.getElementById(obj).style.display = '';
}

function openPopup(url)
{
	popup = window.open(url, 'popup', 'height=700, width=800, left=10, top=10, resizable=yes, scrollbars=yes, toolbar=no, menubar=no, location=no, directories=no');
}
