jQuery(document).ready(function($){
	if ($('[data-toggle="tooltip"]').length > 0)
	{
		$('[data-toggle="tooltip"]').tooltip();
	}

	if ($('table.datatable').length > 0)
	{
		var table = $('table.datatable').DataTable({
			stateSave: true
		});

		$(table.table().container())
			.find('div.dataTables_paginate')
			.css('display', table.page.info().pages <= 1 ? 'none' : 'block');
		$(table.table().container())
			.find('th.no-sort')
			.removeClass('sorting');
	}
});
