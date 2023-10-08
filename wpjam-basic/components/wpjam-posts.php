<?php
/*
Name: 文章设置
URI: https://mp.weixin.qq.com/s/XS3xk-wODdjX3ZKndzzfEg
Description: 文章设置把文章编辑的一些常用操作，提到文章列表页面，方便设置和操作
Version: 2.0
*/
class WPJAM_Basic_Posts extends WPJAM_Option_Model{
	public static function get_sections(){
		$excerpt_show_if	= ['key'=>'excerpt_optimization', 'value'=>1];
		$excerpt_options	= [0=>'WordPress 默认方式截取', 1=>'按照中文最优方式截取', 2=>'直接不显示摘要'];
		$excerpt_fields		= [
			'optimization'	=> ['before'=>'未设文章摘要：',	'options'=>$excerpt_options],
			'length'		=> ['before'=>'文章摘要长度：',	'type'=>'number',	'show_if'=>$excerpt_show_if,	'value'=>200],
			'cn_view2'		=> ['before'=>'中文截取算法：',	'type'=>'view',		'show_if'=>$excerpt_show_if,	'short'=>'QB6zUXA_QI1lseAfNV29Lg',	'value'=>'<strong>中文算2个字节，英文算1个字节</strong>']
		];

		$list_fields	= [
			'post_list_support'	=> ['type'=>'fields',	'before'=>'支持：',	'sep'=>'&emsp;',	'fields'=>[
				'post_list_ajax'			=> ['value'=>1,	'description'=>'全面 AJAX 操作'],
				'upload_external_images'	=> ['value'=>0,	'description'=>'上传外部图片操作'],
			]],
			'post_list_display'	=> ['type'=>'fields',	'before'=>'显示：',	'sep'=>'&emsp;',	'fields'=>[
				'post_list_set_thumbnail'	=> ['value'=>1,	'description'=>'文章缩略图'],
				'post_list_author_filter'	=> ['value'=>1,	'description'=>'作者下拉选择框'],
				'post_list_sort_selector'	=> ['value'=>1,	'description'=>'排序下拉选择框'],
			]]
		];

		$other_fields	= [
			'remove_post_tag'	=> ['value'=>0,	'description'=>'移除标签功能'],
			'404_optimization'	=> ['value'=>0,	'description'=>'增强404页面跳转']
		];

		return ['posts'	=>['title'=>'文章设置',	'fields'=>WPJAM_Basic::parse_fields([
			'excerpt'	=> ['title'=>'文章摘要',	'type'=>'fieldset',	'fields'=>$excerpt_fields,	'prefix'=>'excerpt'],
			'list'		=> ['title'=>'文章列表',	'type'=>'fieldset',	'fields'=>$list_fields],
			'other'		=> ['title'=>'功能优化',	'type'=>'fields',	'fields'=>$other_fields,	'sep'=>'&emsp;'],
		])]];
	}

	public static function get_menu_page(){
		return [
			'parent'		=> 'wpjam-basic',
			'menu_slug'		=> 'wpjam-posts',
			'menu_title'	=> '文章设置',
			'position'		=> 4,
			'function'		=> 'tab',
			'tabs'			=> ['posts'=>[
				'title'			=> '文章设置',
				'function'		=> 'option',
				'option_name'	=> 'wpjam-basic',
				'site_default'	=> true,
				'order'			=> 20,
				'summary'		=> __FILE__,
			]]
		];
	}

	public static function get_admin_load(){
		return [
			[
				'base'	=> ['edit', 'upload'],
				'model'	=> 'WPJAM_Basic_Posts_Builtin_Page'
			],
			[
				'base'	=> 'post',
				'model'	=> 'WPJAM_Basic_Post_Builtin_Page'
			],
			[
				'base'	=> ['edit-tags', 'term'],
				'model'	=> 'WPJAM_Basic_Term_Builtin_Page'
			]
		];
	}

	public static function is_wc_shop($post_type){
		return defined('WC_PLUGIN_FILE') && str_starts_with($post_type, 'shop_');
	}

	public static function set_list_table_option($screen){
		$scripts	= '';
		$ajax		= false;

		if($screen->base != 'edit' || !self::is_wc_shop($screen->post_type)){
			if(wpjam_basic_get_setting('post_list_ajax', 1)){
				$ajax		= true;
				$scripts	.= preg_replace("/^\t{3}/m", '', "
				$(window).load(function(){
					if($('#the-list').length){
						$.wpjam_delegate_events('#the-list', '.editinline');
					}

					if($('#doaction').length){
						$.wpjam_delegate_events('#doaction');
					}
				});\n");
			}

			$scripts	.= preg_replace("/^\t{2}/m", '', "
			let observer = new MutationObserver(function(mutations){
				if($('#the-list .inline-editor').length > 0){
					let tr_id	= $('#the-list .inline-editor').attr('id');

					if(tr_id == 'bulk-edit'){
						$('#the-list').trigger('bulk_edit');
					}else{
						let id	= tr_id.split('-')[1];

						if(id > 0){
							$('#the-list').trigger('quick_edit', id);
						}
					}
				}
			});

			observer.observe(document.querySelector('body'), {childList: true, subtree: true});\n");
		}

		if($scripts){
			$scripts	= "jQuery(function($){".$scripts."\n});";
		}

		if(!$ajax){
			$scripts	.= "\nwpjam_page_setting.list_table.ajax = false;";
		}

		wp_add_inline_script('jquery', $scripts);
	}

	public static function find_by_name($post_name, $post_type='', $post_status='publish'){
		$args	= $args_with_type = $args_for_meta = [];

		if($post_status && $post_status != 'any'){
			$args['post_status']	= $post_status;
		}

		if($post_type && $post_type != 'any'){
			$args_with_type	= array_merge($args, ['post_type'=>$post_type]);
		}

		$post_types		= get_post_types(['public'=>true, 'exclude_from_search'=>false]);
		$post_types		= array_diff($post_types, ['attachment']);
		$args_for_meta	= array_merge($args, ['post_type'=>array_values($post_types)]);

		$meta	= wpjam_get_by_meta('post', '_wp_old_slug', $post_name);
		$posts	= $meta ? WPJAM_Post::get_by_ids(array_column($meta, 'post_id')) : [];

		if($args_with_type){
			foreach($posts as $post){
				if(wpjam_match($post, $args_with_type)){
					return $post;
				}
			}
		}

		foreach($posts as $post){
			if(wpjam_match($post, $args_for_meta)){
				return $post;
			}
		}

		$wpdb		= $GLOBALS['wpdb'];
		$post_types	= get_post_types(['public'=>true, 'hierarchical'=>false, 'exclude_from_search'=>false]);
		$post_types	= array_diff($post_types, ['attachment']);

		$where		= "post_type in ('" . implode( "', '", array_map('esc_sql', $post_types)) . "')";
		$where		.= ' AND '.$wpdb->prepare("post_name LIKE %s", $wpdb->esc_like($post_name).'%');

		$post_ids	= $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE $where");
		$posts		= $post_ids ? WPJAM_Post::get_by_ids($post_ids) : [];

		if($args_with_type){
			foreach($posts as $post){
				if(wpjam_match($post, $args_with_type)){
					return $post;
				}
			}
		}

		foreach($posts as $post){
			if($args){
				if(wpjam_match($post, $args)){
					return $post;
				}
			}else{
				return $post;
			}
		}
	}

	public static function filter_get_the_excerpt($text='', $post=null){
		$optimization	= self::get_setting('excerpt_optimization');

		if(empty($text) && $optimization){
			remove_filter('get_the_excerpt', 'wp_trim_excerpt');

			if($optimization != 2){
				remove_filter('the_excerpt', 'wp_filter_content_tags');
				remove_filter('the_excerpt', 'shortcode_unautop');

				$length	= self::get_setting('excerpt_length') ?: 200;
				$text	= wpjam_get_post_excerpt($post, $length);
			}
		}

		return $text;
	}

	public static function filter_old_slug_redirect_post_id($post_id){
		// 解决文章类型改变之后跳转错误的问题
		// WP 原始解决函数 'wp_old_slug_redirect' 和 'redirect_canonical'
		if(empty($post_id) && self::get_setting('404_optimization')){
			$post	= self::find_by_name(get_query_var('name'), get_query_var('post_type'));

			return $post ? $post->ID : $post_id;
		}

		return $post_id;
	}

	public static function init(){
		if(self::get_setting('remove_post_tag')){
			unregister_taxonomy_for_object_type('post_tag', 'post');
		}
	}

	public static function add_hooks(){
		add_filter('get_the_excerpt',			[self::class, 'filter_get_the_excerpt'], 9, 2);
		add_filter('old_slug_redirect_post_id',	[self::class, 'filter_old_slug_redirect_post_id']);
	}
}

if(is_admin()){
	class WPJAM_Basic_Post_Builtin_Page extends WPJAM_Builtin_Page{
		public function __construct($screen){
			if(wpjam_basic_get_setting('disable_trackbacks')){
				wp_add_inline_style('list-tables', "\n".'label[for="ping_status"]{display:none !important;}');
			}

			if(wpjam_basic_get_setting('disable_autoembed')){
				if($screen->is_block_editor){
					$scripts	= "
					jQuery(function($){
						wp.domReady(function (){
							wp.blocks.unregisterBlockType('core/embed');
						});
					});
					";

					wp_add_inline_script('jquery', $scripts);
				}
			}
		}

		public static function filter_content_save_pre($content){
			if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
				return $content;
			}

			if(!preg_match_all('/<img.*?src=\\\\[\'"](.*?)\\\\[\'"].*?>/i', $content, $matches)){
				return $content;
			}

			$img_urls	= array_unique($matches[1]);

			if($replace	= wpjam_fetch_external_images($img_urls)){
				if(is_multisite()){
					setcookie('wp-saving-post', $_POST['post_ID'].'-saved', time()+DAY_IN_SECONDS, ADMIN_COOKIE_PATH, false, is_ssl());
				}

				$content	= str_replace($img_urls, $replace, $content);
			}

			return $content;
		}
	}

	class WPJAM_Basic_Posts_Builtin_Page extends WPJAM_Builtin_Page{
		public function __construct($screen){
			parent::__construct($screen);
			
			WPJAM_Basic_Posts::set_list_table_option($screen);

			add_action('restrict_manage_posts',	[$this, 'taxonomy_dropdown'], 1);
			add_action('restrict_manage_posts',	[$this, 'author_dropdown'], 1);
			add_action('restrict_manage_posts',	[$this, 'orderby_dropdown'], 99);

			$style	= ['.fixed .column-date{width:8%;}'];
			$ptype	= $screen->post_type;

			if($ptype != 'attachment'){
				add_filter('wpjam_single_row',	[$this, 'filter_single_row'], 10, 2);

				if($this->in_taxonomy('category')){
					add_filter('disable_categories_dropdown', '__return_true');
				}

				if(wpjam_basic_get_setting('upload_external_images')){
					wpjam_register_list_table_action('upload_external_images', [
						'title'			=> '上传外部图片',
						'page_title'	=> '上传外部图片',
						'direct'		=> true,
						'confirm'		=> true,
						'bulk'			=> 2,
						'order'			=> 9,
						'callback'		=> [$this, 'upload_external_images']
					]);
				}

				$style[]	= '#bulk-titles, ul.cat-checklist{height:auto; max-height: 14em;}';

				if($ptype == 'page'){
					$style[]	= '.fixed .column-template{width:15%;}';

					wpjam_register_posts_column('template', '模板', 'get_page_template_slug');
				}elseif($ptype == 'product'){
					if(wpjam_basic_get_setting('post_list_set_thumbnail', 1) && defined('WC_PLUGIN_FILE')){
						wpjam_unregister_posts_column('thumb');
					}
				}
			}

			$width_columns	= [];

			if($this->supports('author')){
				$width_columns[]	= '.fixed .column-author';
			}

			foreach($this->get_taxonomies() as $tax_object){
				if($tax_object->show_admin_column){
					$width_columns[]	= '.fixed .column-'.$tax_object->column_name;
				}
			}

			$count = count($width_columns);

			if($count){
				$widths		= ['14%',	'12%',	'10%',	'8%',	'7%'];
				$style[]	= implode(',', $width_columns).'{width:'.($widths[$count-1] ?? '6%').'}';
			}

			wp_add_inline_style('list-tables', "\n".implode("\n", $style)."\n");
		}

		public function taxonomy_dropdown($ptype){
			foreach($this->get_taxonomies() as $taxonomy => $tax_object){
				$filterable	= $tax_object->filterable ?? ($taxonomy == 'category' ? true : false);

				if($filterable && $tax_object->show_admin_column){
					$tax_object->dropdown();
				}
			}
		}

		public function author_dropdown($ptype){
			if(wpjam_basic_get_setting('post_list_author_filter', 1) && $this->supports('author')){
				wp_dropdown_users([
					'name'						=> 'author',
					'capability'				=> 'edit_posts',
					'orderby'					=> 'post_count',
					'order'						=> 'DESC',
					'hide_if_only_one_author'	=> true,
					'show_option_all'			=> $ptype == 'attachment' ? '所有上传者' : '所有作者',
					'selected'					=> (int)wpjam_get_data_parameter('author')
				]);
			}
		}

		public function orderby_dropdown($ptype){
			if(wpjam_basic_get_setting('post_list_sort_selector', 1) && !WPJAM_Basic_Posts::is_wc_shop($ptype)){
				$options		= [''=>'排序','ID'=>'ID'];
				$wp_list_table	= _get_list_table('WP_Posts_List_Table', ['screen'=>get_current_screen()->id]);

				list($columns, $hidden, $sortable_columns)	= $wp_list_table->get_column_info();

				foreach($sortable_columns as $sortable_column => $data){
					if(isset($columns[$sortable_column])){
						$options[$data[0]]	= $columns[$sortable_column];
					}
				}

				if($ptype != 'attachment'){
					$options['modified']	= '修改时间';
				}

				$orderby	= wpjam_get_data_parameter('orderby', ['sanitize_callback'=>'sanitize_key']);
				$order		= wpjam_get_data_parameter('order', ['sanitize_callback'=>'sanitize_key', 'default'=>'DESC']);

				echo wpjam_field(['key'=>'orderby',	'type'=>'select',	'value'=>$orderby,	'options'=>$options]);
				echo wpjam_field(['key'=>'order',	'type'=>'select',	'value'	=>$order,	'options'=>['desc'=>'降序','asc'=>'升序']]);
			}
		}

		public function filter_single_row($single_row, $post_id){
			if(wpjam_basic_get_setting('post_list_set_thumbnail', 1) && ($this->supports('thumbnail') || $this->supports('images'))){
				$thumbnail	= get_the_post_thumbnail($post_id, [50,50]) ?: '<span class="no-thumbnail">暂无图片</span>';
				$thumbnail	= '[row_action name="set" class="wpjam-thumbnail-wrap" fallback="1"]'.$thumbnail.'[/row_action]';
				$single_row	= str_replace('<a class="row-title" ', $thumbnail.'<a class="row-title" ', $single_row);
			}

			$set_action	= '[row_action name="set" class="row-action"]<span class="dashicons dashicons-edit"></span>[/row_action]';
			$single_row = preg_replace('/(<strong>.*?<a class=\"row-title\".*?<\/a>.*?)(<\/strong>)/is', '$1 '.$set_action.'$2', $single_row);

			if(wpjam_basic_get_setting('post_list_ajax', 1)){
				$quick_edit	= '<a title="快速编辑" href="javascript:;" class="editinline row-action"><span class="dashicons dashicons-edit"></span></a>';

				if($this->supports('author')){
					$single_row = preg_replace('/(<td class=\'author column-author\' .*?>.*?)(<\/td>)/is', '$1 '.$quick_edit.'$2', $single_row);
				}

				foreach($this->get_taxonomies() as $tax_object){
					if($tax_object->show_in_quick_edit){
						$single_row	= preg_replace('/(<td class=\''.$tax_object->column_name.' column-'.$tax_object->column_name.'\' .*?>.*?)(<\/td>)/is', '$1 '.$quick_edit.'$3', $single_row);
					}
				}
			}

			return $single_row;
		}

		public function upload_external_images($post_id){
			$bulk	= (int)wpjam_get_post_parameter('bulk');
			$result	= wpjam_post($post_id)->upload_external_images();

			if(is_wp_error($result) && $bulk == 2){
				return true;
			}

			return $result;
		}
	}

	class WPJAM_Basic_Term_Builtin_Page extends WPJAM_Builtin_Page{
		public function __construct($screen){
			parent::__construct($screen);

			$style	= [];

			if($screen->base == 'edit-tags'){
				WPJAM_Basic_Posts::set_list_table_option($screen);

				add_filter('wpjam_single_row',	[$this, 'filter_single_row'], 10, 2);

				$style	= [
					'.fixed th.column-slug{ width:16%; }',
					'.fixed th.column-description{width:22%;}',
					'.form-field.term-parent-wrap p{display: none;}',
					'.form-field span.description{color:#666;}'
				];
			}

			foreach(['slug', 'description', 'parent'] as $key){
				if(!$this->supports($key)){
					$style[]	= '.form-field.term-'.$key.'-wrap{display: none;}'."\n";
				}
			}

			if($style){
				wp_add_inline_style('list-tables', "\n".implode("\n", $style));
			}
		}

		public function filter_single_row($single_row, $term_id){
			if(wpjam_basic_get_setting('post_list_set_thumbnail', 1) && $this->supports('thumbnail')){
				$thumb_url	= wpjam_get_term_thumbnail_url($term_id, [100, 100]);

				if($thumb_url){
					$thumbnail	= wpjam_tag('img', ['class'=>'wp-term-image', 'src'=>$thumb_url, 'width'=>50, 'height'=>50]);
				}else{
					$thumbnail	= wpjam_tag('span', ['no-thumbnail'], '暂无图片');
				}

				$thumbnail	= '[row_action name="set" class="wpjam-thumbnail-wrap" fallback="1"]'.$thumbnail.'[/row_action]';
				$single_row	= str_replace('<a class="row-title" ', $thumbnail.'<a class="row-title" ', $single_row);
			}

			return $this->link_replace($single_row, $term_id);
		}
	}
}

wpjam_register_option('wpjam-basic', [
	'plugin_page'	=> 'wpjam-posts',
	'current_tab'	=> 'posts',
	'site_default'	=> true,
	'model'			=> 'WPJAM_Basic_Posts',
]);
