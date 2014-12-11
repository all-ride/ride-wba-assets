$(document).ready(function() {

	//List-grid style behaviour
	setAssetStyle($('input[name=style]:checked').val());
	$('input[name=style]', '#asset-overview-bulk').on('change', function() {
		setAssetStyle($('input[name=style]:checked').val());
	});

	//Bulk selection behaviour
	$('input[name=select-all]').change(function() {
		var checked = this.checked ? true : false;
		$('input[name=selected-asset]').prop('checked', checked);
	});
});

function setAssetStyle(style) {
	var previous_class = 'col-md-12',
		new_class = 'col-md-2';
	if (style == 'list') {
		previous_class = 'col-md-2';
		new_class = 'col-md-12';
	}
	$('.asset-handle').each(function(k, v) {
		var $this = $(v);
		$this.removeClass(previous_class);
		$this.addClass(new_class);
	});

	$('.asset_details').each(function(k, v) {
		var $this = $(v);
		console.log($this);
		if (style == 'grid') {
			$this.hide();
			$('.list_header').hide();
		}
		else {
			$('.list_header').show();
			$this.show();
		}
	});


}
