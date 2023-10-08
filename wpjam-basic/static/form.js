jQuery(function($){
	$.fn.extend({
		wpjam_select_media: function(callback){
			let _this	= $(this);

			wp.media.frame.state().get('selection').map(function(attachment){
				callback.call(_this, attachment.toJSON());
			});
		},

		wpjam_uploader_init: function(){
			this.each(function(){
				let up_args		= $(this).data('plupload');
				let uploader	= new plupload.Uploader($.extend({}, up_args, {
					url : ajaxurl, 
					multipart_params : $.wpjam_append_page_setting(up_args.multipart_params)
				}));

				uploader.bind('init', function(up){
					let up_container = $(up.settings.container);
					let up_drag_drop = $(up.settings.drop_element);

					if(up.features.dragdrop){
						up_drag_drop.on('dragover.wp-uploader', function(){
							up_container.addClass('drag-over');
						}).on('dragleave.wp-uploader, drop.wp-uploader', function(){
							up_container.removeClass('drag-over');
						});
					} else {
						up_drag_drop.off('.wp-uploader');
					}
				});

				uploader.bind('postinit', function(up) {
					up.refresh();
				});

				uploader.bind('FilesAdded', function(up, files){
					$(up.settings.container).find('.button').hide();

					up.refresh();
					up.start();
				});

				uploader.bind('Error', function(up, error){
					alert(error.message);
				});

				uploader.bind('UploadProgress', function(up, file){
					let up_container = $(up.settings.container);

					up_container.find('.progress').show();
					up_container.find('.bar').width((200 * file.loaded) / file.size);
					up_container.find('.percent').html(file.percent + '%');
				});

				uploader.bind('FileUploaded', function(up, file, result){
					let response		= JSON.parse(result.response);
					let up_container	= $(up.settings.container);

					up_container.find('.progress').hide();
					up_container.find('.button').show();

					if(response.errcode){
						alert(response.errmsg);
					}else{
						up_container.find('.field-key-'+up.settings.file_data_name).addClass('hidden').val(response.file);
						up_container.find('.query-title').find('.query-text').text(response.file.split('/').pop());
					}
				});

				uploader.bind('UploadComplete', function(up, files){});

				uploader.init();
			});
		},

		wpjam_cascading_dropdown: function(){
			this.each(function(){
				let value	= $(this).data('value');

				if(value && !$.isEmptyObject(value)){
					let key	= $(this).data('key');

					$.each(value, function(sub_key, level){
						let query_select	= $('.field-key-'+key+'__'+sub_key);

						if(level.parent != 0){
							if(level.items.length > 0){
								query_select.empty();

								let option_all	= query_select.data('option_all');

								if(typeof option_all !== 'undefined'){
									query_select.prepend('<option value="0">'+option_all+'</option>');
								}

								$.each(level.items, function(i, option){
									query_select.append('<option value="'+option.value+'">'+option.label+'</option>');
								});

								query_select.val(level.value).wpjam_show_if();
							}else{
								query_select.closest('.sublevel').addClass('hidden');
							}
						}else{
							query_select.val(level.value).wpjam_show_if();
						}
					});
					
					$(this).data('value', null);
				}
			});
		},

		wpjam_show_if: function(scope=null){
			let _this;

			if(this instanceof jQuery){
				scope	= scope || $('body');
				_this	= this;
			}else{
				scope	= $('body');
				_this	= $([this]);
			}

			_this.each(function(){
				let key	= $(this).data('key');
				let val	= $(this).val();

				if($(this).is(':checkbox')){
					let wrap_id	= $(this).data('wrap_id');

					if(wrap_id){
						if(!$('#'+wrap_id).wpjam_remaining()){
							$(this).prop('checked', false);
						}

						val	= [];

						$('#'+wrap_id+' input:checked').each(function(){
							val.push($(this).val());
						});
					}else{
						if(!$(this).is(':checked')){
							val	= 0;
						}
					}
				}else if($(this).is(':radio')) {
					if(!$(this).is(':checked')){
						return;
					}
				}

				if($(this).prop('disabled')){
					val	= null;
				}

				scope.find('.show_if-'+key).each(function(){
					let data	= $(this).data('show_if');

					if(data.compare){
						if($.wpjam_compare(val, data.compare, data.value)){
							$(this).removeClass('hidden');

							if($(this).is('option')){
								$(this).prop('disabled', false);
							}else{
								$(this).find(':input').not('.disabled').prop('disabled', false);
							}
						}else{
							$(this).addClass('hidden');

							if($(this).is('option')){
								$(this).prop('disabled', true);
								$(this).prop('selected', false);
							}else{
								$(this).find(':input').not('.disabled').prop('disabled', true);	
							}
						}

						if($(this).is('option')){
							$(this).parents('select').wpjam_show_if(scope);
						}else{
							$(this).find('.show_by').wpjam_show_if(scope);
						}
					}

					if(!$(this).hasClass('hidden') && data.query_arg){
						let query_arg		= data.query_arg;
						let query_input		= $(this).find('input.autocomplete');
						let query_select	= $(this).find('select');

						if(query_input.length > 0){
							let query_args	= query_input.data('query_args');

							if(query_args[query_arg] != val){
								query_args[query_arg]	= val;

								query_input.data('query_args', query_args);

								let query_icon	= $(this).find('.query-title span.dashicons');

								if(!query_icon.hasClass('init')){
									query_icon.click();
								}else{
									query_icon.removeClass('init');
								}
							}
						}else if(query_select.length > 0){
							let cascading_dropdown	= query_select.parents('.cascading-dropdown');

							let value		= cascading_dropdown.data('value');
							let query_args	= query_select.data('query_args');
							let data_type	= query_select.data('data_type');

							if(value && !$.isEmptyObject(value) && query_args){
								let sub_key	= query_select.data('sub_key');

								if(value[sub_key]){
									query_args[query_arg]	= value[sub_key]['parent'];

									query_select.data('query_args', query_args);
								}
							}else if(data_type && query_args && query_args[query_arg] != val){
								query_args[query_arg]	= val;

								query_select.data('query_args', query_args);

								query_select.closest('.sublevel').addClass('hidden');

								$.post(ajaxurl, $.wpjam_append_page_setting({
									action:		'wpjam-query',
									data_type:	data_type,
									query_args:	query_args
								}), function(data, status){
									query_select.empty();

									let option_all	= query_select.data('option_all');

									if(typeof option_all !== 'undefined'){
										query_select.prepend('<option value="0">'+option_all+'</option>');
									}

									if(data.items.length > 0){
										$.each(data.items, function(i, item){
											query_select.append('<option value="'+item.value+'">'+item.label+'</option>');
										});

										query_select.closest('.sublevel').removeClass('hidden');
									}

									query_select.wpjam_show_if(scope);
								});
							}
						}
					}
				});
			});
		},

		wpjam_show_if_class:function(){
			this.each(function(){
				let key	= $(this).data('show_if').key;

				$(this).addClass('show_if-'+key);

				if($(this).data('show_if').external){
					$('#'+key).addClass('show_by').data('key', key);
				}else{
					$('.field-key-'+key).addClass('show_by');
				}	
			});
		},

		wpjam_autocomplete: function(){
			this.each(function(){
				$(this).autocomplete({
					minLength:	0,
					source: function(request, response){
						let data_type	= this.element.data('data_type');
						let query_args	= this.element.data('query_args');

						if(request.term){
							if(data_type == 'post_type'){
								query_args.s		= request.term;
							}else{
								query_args.search	= request.term;
							}
						}

						$.post(ajaxurl, $.wpjam_append_page_setting({
							action:		'wpjam-query',
							data_type:	data_type,
							query_args:	query_args
						}), function(data, status){
							response(data.items);
						});
					},
					select: function(event, ui){
						$(this).addClass('hidden').next('.query-title').find('.query-text').html(ui.item.label);
					},
					change: function(event, ui){
						$(this).wpjam_show_if();
					}
				}).focus(function(){
					if(this.value == ''){
						$(this).autocomplete('search');
					}
				});
			});
		},

		wpjam_color: function(){
			this.each(function(){
				$(this).wpColorPicker();
				$(this).next('.description').appendTo($(this).parents('.wp-picker-container'));
			});
		},

		wpjam_img: function(){
			this.each(function(){
				$(this).nextAll('.description').appendTo($(this));
			});
		},

		wpjam_editor: function(){
			this.each(function(){
				if(wp.editor){
					let id	= $(this).attr('id');

					wp.editor.remove(id);
					wp.editor.initialize(id, $(this).data('settings'));
				}else{
					alert('请在页面加载 add_action(\'admin_footer\', \'wp_enqueue_editor\');');
				}
			});
		},

		wpjam_tabs: function(){
			this.each(function(){
				$(this).tabs({
					activate: function(event, ui){
						$('.ui-corner-top a').removeClass('nav-tab-active');
						$('.ui-tabs-active a').addClass('nav-tab-active');

						let tab_href = window.location.origin + window.location.pathname + window.location.search +ui.newTab.find('a').attr('href');
						window.history.replaceState(null, null, tab_href);
						$('input[name="_wp_http_referer"]').val(tab_href);
					},
					create: function(event, ui){
						if(ui.tab.find('a').length){
							ui.tab.find('a').addClass('nav-tab-active');
							if(window.location.hash){
								$('input[name="_wp_http_referer"]').val($('input[name="_wp_http_referer"]').val()+window.location.hash);
							}
						}
					}
				});
			});
		},

		wpjam_remaining: function(){
			let	max_items	= parseInt($(this).data('max_items'));

			if(max_items){
				let sub		= $(this).hasClass('mu-checkbox') ? 'input:checkbox:checked' : ' > div.mu-item';
				let count	= $(this).find(sub).length;

				if($(this).hasClass('mu-img') || $(this).hasClass('mu-fields') || $(this).hasClass('direction-row')){
					count --;
				}

				if(count >= max_items){
					alert('最多支持'+max_items+'个');

					return 0;
				}else{
					return max_items - count;
				}
			}

			return -1;
		}
	});

	$.extend({
		wpjam_attachment_url(attachment){
			return attachment.url+'?'+$.param({orientation:attachment.orientation, width:attachment.width, height:attachment.height});
		},

		wpjam_compare: function(a, compare, b){
			if(a === null){
				return false;
			}

			if(Array.isArray(a)){
				if(compare == '='){
					return a.indexOf(b) != -1;
				}else if(compare == '!='){
					return a.indexOf(b) == -1;
				}else if(compare == 'IN'){
					return a.filter(function(n) { return b.indexOf(n) !== -1; }).length == b.length;
				}else if(compare == 'NOT IN'){
					return a.filter(function(n) { return b.indexOf(n) !== -1; }).length == 0;
				}else{
					return false;
				}
			}else{
				if(compare == '='){
					return a == b;
				}else if(compare == '!='){
					return a != b;
				}else if(compare == '>'){
					return a > b;
				}else if(compare == '>='){
					return a >= b;
				}else if(compare == '<'){
					return a < b;
				}else if(compare == '<='){
					return a <= b;
				}else if(compare == 'IN'){
					return b.indexOf(a) != -1;
				}else if(compare == 'NOT IN'){
					return b.indexOf(a) == -1;
				}else if(compare == 'BETWEEN'){
					return a > b[0] && a < b[1];
				}else if(compare == 'NOT BETWEEN'){
					return a < b[0] && a > b[1];
				}else{
					return false;
				}
			}
		},

		wpjam_form_init: function(event){
			$('.mu-fields.sortable').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-image.sortable').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-file.sortable').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-text.sortable').sortable({
				handle: '.dashicons-menu',
				cursor: 'move'
			});

			$('.mu-img.sortable').sortable({
				cursor: 'move'
			});

			$('.wpjam-tooltip .wpjam-tooltip-text').css('margin-left', function(){
				return 0 - Math.round($(this).width()/2);
			});

			$('.tabs').wpjam_tabs();
			$('.show_if').wpjam_show_if_class();
			$('.show_by').not('.cascading-dropdown .show_by').wpjam_show_if();
			$('.cascading-dropdown').wpjam_cascading_dropdown();
			$('.autocomplete').wpjam_autocomplete();
			$('.plupload').wpjam_uploader_init();

			$('input.color').wpjam_color();
			$('.wpjam-img').wpjam_img()

			$('textarea.editor').wpjam_editor();
		}
	});

	$('body').on('change', '.show_by', $.fn.wpjam_show_if);

	$('body').on('change', 'input[type="radio"]', function(){
		if($(this).is(':checked')){
			let wrap_id	= $(this).data('wrap_id');

			if(wrap_id){
				$('#'+wrap_id+' label').removeClass('checked');
				$(this).parent('label').addClass('checked');
			}
		}
	});

	$('body').on('change', 'input[type="checkbox"]', function(){
		if($(this).is(':checked')){
			let wrap_id	= $(this).data('wrap_id');

			if(wrap_id){
				let remaining	= $('#'+wrap_id).wpjam_remaining();

				if(!remaining){
					$(this).prop('checked', false);
					return false;
				}
			}

			$(this).parent('label').addClass('checked');
		}else{
			$(this).parent('label').removeClass('checked');
		}
	});

	$.wpjam_form_init();

	$('body').on('list_table_action_success', $.wpjam_form_init);
	$('body').on('page_action_success', $.wpjam_form_init);
	$('body').on('option_action_success', $.wpjam_form_init);

	$('body').on('click', '.query-title span.dashicons', function(){
		$(this).parent().fadeOut(300, function(){
			$(this).css('display', '');
			$(this).prev('input').val('').removeClass('hidden').change();
		});
	});

	$('body').on('click', '.wpjam-modal', function(e){
		e.preventDefault();

		wpjam_modal($(this).prop('href'));
	});

	$('body').on('click', '.wpjam-file a', function(e){
		let _this	= $(this);

		let item_type	= $(this).data('item_type');
		let title		= item_type == 'image' ? '选择图片' : '选择文件';

		wp.media({
			id:			'uploader_'+$(this).prev('input').prop('id'),
			title:		title,
			library:	{ type: item_type },
			button:		{ text: title },
			multiple:	false 
		}).on('select', function(){
			_this.wpjam_select_media(function(attachment){
				$(this).prev('input').val($(this).data('item_type') == 'image' ? $.wpjam_attachment_url(attachment) : attachment.url);
			});
		}).open();

		return false;
	});

	//上传单个图片
	$('body').on('click', '.wpjam-img', function(e){
		if($(this).hasClass('readonly')){
			return false;
		}

		let _this	= $(this);
		let args	= {
			id:			'uploader_'+$(this).next('input').prop('id'),
			title:		'选择图片',
			library:	{ type: 'image' },
			button:		{ text: '选择图片' },
			multiple:	false 
		};

		let action	= 'select';

		if(wp.media.view.settings.post.id){
			args.frame	= 'post';
			action		= 'insert';
		}

		wp.media(args).on('open',function(){
			$('.media-frame').addClass('hide-menu');
		}).on(action, function(){
			_this.wpjam_select_media(function(attachment){
				let src	= attachment.url+$(this).data('thumb_args');

				if($(this).find('div.wp-media-buttons').length){
					$(this).next('input').val($(this).data('item_type') == 'url' ? $.wpjam_attachment_url(attachment) : attachment.id);
					$(this).find('img').removeClass('hidden').prop('src', src).fadeIn(300, function(){
						$(this).show();
					});
				}else{
					$(this).find('img').remove();
					$(this).find('a.del-img').remove();
					$(this).append('<img src="'+src+'" /><a href="javascript:;" class="del-img dashicons dashicons-no-alt"></a>');
				}	
			});
		}).open();

		return false;
	});

	//上传多个图片或者文件
	$('body').on('click', 'div.mu-file a.new-item, div.mu-image a.new-item', function(e){
		let parent		= $(this).closest('.mu-file').length > 0 ? '.mu-file' : '.mu-image';
		let remaining	= $(this).parents(parent).wpjam_remaining();

		if(!remaining){
			return false;
		}

		let _this	= $(this);

		wp.media({
			id:			'uploader_'+$(this).parents(parent).prop('id'),
			title:		$(this).data('title'),
			library:	{ type: $(this).data('item_type') },
			button:		{ text: $(this).data('title') },
			multiple:	true
		}).on('select', function() {
			_this.wpjam_select_media(function(attachment){
				let item	= $(this).parent().clone();
				let val 	= $(this).data('item_type')  == 'image' ? $.wpjam_attachment_url(attachment) : attachment.url;

				item.find('.new-item').remove();
				item.find('input').val(val);

				$(this).parent().before(item);
			});
		}).on('selection:toggle', function(e){
			console.log(wp.media.frame.state().get('selection'));
		}).open();

		return false;
	});

	//上传多个图片
	$('body').on('click', 'div.mu-img a.new-item', function(e){
		let remaining	= $(this).parents('.mu-img').wpjam_remaining();
		let selected	= 0;

		if(!remaining){
			return false;
		}

		let _this	= $(this);
		let args	= {
			id:			'uploader_'+$(this).parents('.mu-img').prop('id'),
			title:		'选择图片',
			library:	{ type: 'image' },
			button:		{ text: '选择图片' },
			multiple:	true
		};

		let action	= 'select';

		if(wp.media.view.settings.post.id){
			args.frame	= 'post';
			action		= 'insert';
		}

		wp.media(args).on('selection:toggle', function(){
			let length	= wp.media.frame.state().get('selection').length;

			if(remaining != -1){
				if(length > remaining && length > selected){
					alert('最多还能选择'+remaining+'个');
				}

				$('.media-toolbar .media-button').prop('disabled', length > remaining);
			}

			selected	= length;
		}).on('open', function(){
			$('.media-frame').addClass('hide-menu');
		}).on(action, function(){
			_this.wpjam_select_media(function(attachment){
				let item	= $(this).parent().clone();

				item.find('.new-item').remove();
				item.find('input').val($(this).data('item_type')  == 'url' ? $.wpjam_attachment_url(attachment) : attachment.id);
				item.prepend('<a class="wpjam-modal" href="'+attachment.url+'"><img src="'+attachment.url+$(this).data('thumb_args')+'" /></a>');

				$(this).parent().before(item);
			});
		}).open();

		return false;
	});

	// 添加多个选项
	$('body').on('click', 'div.mu-text a.new-item', function(){
		let remaining	= $(this).parents('.mu-text').wpjam_remaining();

		if(!remaining){
			return false;
		}

		let item	= $(this).parent().clone();

		item.insertAfter($(this).parent());
		item.find(':input').val('');
		item.find('.autocomplete').removeClass('hidden').wpjam_autocomplete();

		$(this).remove();

		return false;
	});

	$('body').on('click', 'div.mu-fields > div.mu-item > a.new-item', function(){
		let remaining	= $(this).parents('.mu-fields').wpjam_remaining();

		if(!remaining){
			return false;
		}

		let i		= $(this).data('i');
		let render	= wp.template($(this).data('tmpl_id'));
		let item	= $(this).parent().clone();

		item.insertBefore($(this).parent());
		item.find('script').remove();
		item.find('.new-item').remove();
		item.prepend($(render({i:i})));
		item.find('.show_if').wpjam_show_if_class();
		item.find('.autocomplete').wpjam_autocomplete();

		$(this).data('i', i+1).parent().parent().trigger('mu_fields_added', i);

		$.wpjam_form_init();

		return false;
	});

	//  删除图片
	$('body').on('click', 'a.del-img', function(){
		$(this).parent().next('input').val('');

		if($(this).prev('div.wp-media-buttons').length){
			$(this).parent().find('img').fadeOut(300, function(){
				$(this).prop('src', '').addClass('hidden');
			});
		}else{
			$(this).parent().find('img').fadeOut(300, function(){
				$(this).remove();
			});
		}

		return false;
	});

	//  删除选项
	$('body').on('click', 'a.del-item', function(){
		let next_input	= $(this).parent().next('input');
		if(next_input.length > 0){
			next_input.val('');
		}

		$(this).parent().fadeOut(300, function(){
			$(this).remove();
		});

		return false;
	});
});

if(self != top){
	document.getElementsByTagName('html')[0].className += ' TB_iframe';
}

function isset(obj){
	if(typeof(obj) != 'undefined' && obj !== null) {
		return true;
	}else{
		return false;
	}
}

function wpjam_modal(src, type, css){
	type	= type || 'img';

	if(jQuery('#wpjam_modal_wrap').length == 0){
		jQuery('body').append('<div id="wpjam_modal_wrap" class="hidden"><div id="wpjam_modal"></div></div>');
		jQuery("<a id='wpjam_modal_close' class='dashicons dashicons-no-alt del-icon'></a>")
		.on('click', function(e){
			e.preventDefault();
			jQuery('#wpjam_modal_wrap').remove();
		})
		.prependTo('#wpjam_modal_wrap');
	}

	if(type == 'iframe'){
		css	= css || {};
		css = jQuery.extend({}, {width:'300px', height:'500px'}, css);

		jQuery('#wpjam_modal').html('<iframe style="width:100%; height: 100%;" src='+src+'>你的浏览器不支持 iframe。</iframe>');
		jQuery('#wpjam_modal_wrap').css(css).removeClass('hidden');
	}else if(type == 'img'){
		let img_preloader	= new Image();
		let img_tag			= '';

		img_preloader.onload	= function(){
			img_preloader.onload	= null;

			let width	= img_preloader.width/2;
			let height	= img_preloader.height/2;

			if(width > 400 || height > 500){
				let radio	= (width / height >= 400 / 500) ? (400 / width) : (500 / height);
				
				width	= width * radio;
				height	= height * radio;
			}

			jQuery('#wpjam_modal').html('<img src="'+src+'" width="'+width+'" height="'+height+'" />');
			jQuery('#wpjam_modal_wrap').css({width:width+'px', height:height+'px'}).removeClass('hidden');
		}

		img_preloader.src	= src;
	}
}

function wpjam_iframe(src, css){
	wpjam_modal(src, 'iframe', css);
}