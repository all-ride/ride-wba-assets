$(function() {
	$('#asset-edit-form input[type=radio]').on('change', function() {
		handleRedirectForm();
	});

	if ($('#asset-edit-form').length > 0) {
		handleRedirectForm();
	}
});

function handleRedirectForm() {
	var type = $('#asset-edit-form input[type=radio]:checked').val();
	$('.row-url').hide();
	$('.row-file').show();
	if (type == 1) {
		$('.row-url').show();
		$('.row-file').hide();
	}
}
