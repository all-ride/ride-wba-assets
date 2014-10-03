$(function() {
	$('#form-redirect input[type=radio]').on('change', function() {
		handleRedirectForm();
	});

	handleRedirectForm();
});

function handleRedirectForm() {
	var type = $('#form-redirect input[type=radio]:checked').val();

	$('.redirect-type').hide();
	if (type) {
		$('.redirect-type-' + type).show();
	}
}
