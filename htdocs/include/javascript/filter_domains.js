/*
	Ajax funktion to apply filter while typing.
*/

$(document).ready(function () {
	"use strict";

	/* disable browser autocomplete on filter input */
	$('input#domain_name').attr('autocomplete', 'off');

	/* allow only a-z, A-Z, 0-9 . - CR */
	$('input#domain_name').keypress(function (key) {
		if ((key.charCode < 97 || key.charCode > 122) && (key.charCode < 65 || key.charCode > 90) && (key.charCode < 48 || key.charCode > 57) && (key.charCode !== 45) && (key.charCode !== 46) && (key.charCode !== 13)) {
			return false;
		}
	});

	/* call domains/domains-ajax.php for every key stroke */
	$('input#domain_name').keyup(function () {
		/* ignore filter strings with less the 3 chars */
		if ($(this).val().length >= 3) {
			/* call domains/domains-ajax.php with filter-string as argument */
			$.get("domains/domains-ajax.php", {domain_name: $(this).val()}, function (data) {
				/* fill div container with requested content */
				$("#domains").html(data);
			});
		}
	});
});
