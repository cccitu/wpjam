jQuery(function($){
	if(window.location.protocol == 'https:'){
		ajaxurl	= ajaxurl.replace('http://', 'https://');
	}

	$.fn.extend({
		wpjam_submit: function(callback){
			let _this	= $(this);

			$.post(ajaxurl, {
				action:			$(this).data('action'),
				_ajax_nonce:	$(this).data('nonce'),
				data:			$(this).serialize()
			},function(data, status){
				callback.call(_this, data);
			});
		},
		wpjam_action: function(callback){
			let _this	= $(this);

			$.post(ajaxurl, {
				action:			$(this).data('action'),
				_ajax_nonce:	$(this).data('nonce'),
				data:			$(this).data('data')
			},function(data, status){
				callback.call(_this, data);
			});
		}
	});
});