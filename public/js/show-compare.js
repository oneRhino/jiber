jQuery(document).ready(function($){
	$('#send-all').click(function(e){
		e.preventDefault();
		$('input.switch').each(function(){
			$(this).attr('checked', true).bootstrapSwitch('state', true);
		});
	});

	$('#ignore-all').click(function(e){
		e.preventDefault();
		$('input.switch').each(function(){
			$(this).attr('checked', false).bootstrapSwitch('state', false);
		});
	});

	$('#invert-all').click(function(e){
		e.preventDefault();
		$('input.switch').each(function(){
			$(this).attr('checked', !$(this).attr('checked')).bootstrapSwitch('toggleState');
		});
	});

	$('.panel-title a').click(function(){
		$('span', this).toggleClass('fa-angle-down').toggleClass('fa-angle-up');
	});

	$('input.switch[type=checkbox]').bootstrapSwitch({
		size   : 'mini',
		onText : 'send',
		offText: 'ignore',
		onSwitchChange: function(event, state){
			if (state)
				$(this).parents('tr').removeClass('disabled');
			else
				$(this).parents('tr').addClass('disabled');
		}
	});

	$('#close-all').click(function(e){
		e.preventDefault();
		$('.panel-collapse').collapse('hide');
	});

	$('#open-all').click(function(e){
		e.preventDefault();
		$('.panel-collapse').collapse('show');
	});

	$('#close-settled').click(function(e){
		e.preventDefault();
		$('.panel-collapse[rel=0]').collapse('hide');
	});
});
