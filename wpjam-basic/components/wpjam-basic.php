<?php
/*
Name: 优化设置
URI: https://mp.weixin.qq.com/s/zkA0Nx4u81PCZWByQq3iiA
Description: 优化设置通过屏蔽和增强功能来加快 WordPress 的加载
Version: 2.0
*/
class WPJAM_Basic extends WPJAM_Option_Model{
	public static function get_sections(){
		return [
			'disabled'	=>['title'=>'功能屏蔽',	'fields'=>self::get_fields('disabled')],
			'enhance'	=>['title'=>'增强优化',	'fields'=>self::get_fields('enhance')],
		];
	}

	public static function get_fields($type){
		$fields	= [];

		if($type == 'disabled'){
			return self::parse_fields([
				'disable_revision'			=>['title'=>'屏蔽文章修订',		'short'=>'S9PBDUtk0jax7eL5kDFiQg',	'label'=>'屏蔽文章修订功能，精简文章表数据。',	'value'=>1],
				'disable_trackbacks'		=>['title'=>'屏蔽Trackbacks',	'short'=>'FZ7zOYOTnqo65U-lx6QpYw',	'label'=>'彻底关闭Trackbacks，防止垃圾留言。',	'value'=>1],
				'disable_emoji'				=>['title'=>'屏蔽Emoji图片',		'short'=>'BMYGDB7GfK5rb4PlwD5xIg',	'label'=>'屏蔽Emoji图片转换功能，直接使用Emoji。',	'value'=>1],
				'disable_texturize'			=>['title'=>'屏蔽字符转码',		'short'=>'9sSXaK5r5XO7xB-3yjV1zQ',	'label'=>'屏蔽字符换成格式化的HTML实体功能。',	'value'=>1],
				'disable_feed'				=>['title'=>'屏蔽站点Feed',		'short'=>'YgJT8Mlhv08p9lvVLC5L1Q',	'label'=>'屏蔽站点Feed，防止文章被快速被采集。'],
				'disable_admin_email_check'	=>['title'=>'屏蔽邮箱验证',		'short'=>'GUPxPQQo3Qa2AMuKuM7CzQ',	'label'=>'屏蔽站点管理员邮箱定期验证功能。'],
				'disable_auto_update'		=>['title'=>'屏蔽自动更新',		'short'=>'bxVdrLhGo075s4TKmi6p3A',	'label'=>'关闭自动更新功能，通过手动或SSH方式更新。'],
				'disable_privacy'			=>['title'=>'屏蔽后台隐私',		'short'=>'aXx1ggscmzvxAgo4tM9bWQ',	'label'=>'移除为欧洲通用数据保护条例而生成的隐私页面。',	'value'=>1],
				'disable_autoembed'			=>['title'=>'屏蔽Auto Embeds',	'short'=>'cg5cVPGj2Hwq0Jxn7DJw6Q',	'label'=>'禁用Auto Embeds功能，加快页面解析速度。'],
				'disable_post_embed'		=>['title'=>'屏蔽文章Embed',		'short'=>'Flm2ggE3VE-qcoZnY4Nycg',	'label'=>'屏蔽嵌入其他WordPress文章的Embed功能。'],
				'disable_block_editor'		=>['title'=>'屏蔽古腾堡编辑器',	'short'=>'LsVZX7p64-fcICwwA6IEQw',	'label'=>'屏蔽Gutenberg编辑器，换回经典编辑器。'],
				'disable_xml_rpc'			=>['title'=>'屏蔽XML-RPC',		'short'=>'hYAe_a497ZkkwlkM-cFsOg',	'label'=>'关闭XML-RPC功能，只在后台发布文章。']
			]);
		}elseif($type == 'enhance'){
			$x_frame_options	= [''=>'所有网页', 'SAMEORIGIN'=>'只允许同域名网页', 'DENY'=>'不允许任何网页'];

			return self::parse_fields([
				'google_fonts_set'		=>['title'=>'Google字体加速','type'=>'fieldset',	'short'=>'Sz0QlZ-kW0C70NkbpoDpag',	'fields'=>WPJAM_Google_Font::get_options_fields(['name'=>'google_fonts'])],
				'gravatar_set'			=>['title'=>'Gravatar加速',	'type'=>'fieldset',	'short'=>'eyHr2r-vrqSqADwMkPh95Q',	'fields'=>WPJAM_Gravatar::get_options_fields()],
				'x-frame-options'		=>['title'=>'Frame嵌入',		'short'=>'P__N3Srj-4WvZdkLORlBRg',	'options'=>$x_frame_options],
				'no_category_base_set'	=>['title'=>'分类链接简化',	'type'=>'fieldset',	'group'=>true,	'fields'=>self::get_fields('no_category_base')],
				'timestamp_file_name'	=>['title'=>'图片时间戳',		'short'=>'yodHw7W-qlnkbYqBPbHTEA',	'label'=>'给上传的图片加上时间戳，防止大量的SQL查询。'],
				'frontend_set'			=>['title'=>'前台页面优化',	'type'=>'fieldset',	'fields'=>self::get_fields('frontend')],
				'backend_set'			=>['title'=>'后台页面优化',	'type'=>'fieldset',	'fields'=>self::get_fields('backend')],
				'optimized_by_wpjam'	=>['title'=>'WPJAM Basic',	'label'=>'在网站底部显示：Optimized by WPJAM Basic。']
			]);
		}elseif($type == 'frontend'){
			return [
				'remove_head_links'			=>['short'=>'7W119jeuzNNehWzokGEb3A',	'label'=>'移除页面头部版本号和服务发现标签代码。',	'value'=>1],
				'remove_admin_bar'			=>['short'=>'_YpFZ4gttJObcD7-CbWNhg',	'label'=>'移除工具栏和后台个人资料中工具栏相关选项。'],
				'remove_capital_P_dangit'	=>['short'=>'0tVQbq9cWvFjpcDYwSVY_w',	'label'=>'移除 WordPress 大小写修正。',	'value'=>1],
			];
		}elseif($type == 'backend'){
			return [
				'remove_help_tabs'			=>['short'=>'p6HWVHIos2h5h6M78Lbclw',	'label'=>'移除后台界面右上角的帮助。'],
				'remove_screen_options'		=>['short'=>'p6HWVHIos2h5h6M78Lbclw',	'label'=>'移除后台界面右上角的选项。'],
			];
		}elseif($type == 'no_category_base'){
			if($GLOBALS['wp_rewrite']->use_verbose_page_rules){
				$fields['no_category_base']	= ['type'=>'view',	'value'=>'站点当前的固定链接设置不支持去掉分类目录链接中的 category，请先修改固定链接设置。'];
			}else{
				$taxonomies	= get_taxonomies(['public'=>true, 'hierarchical'=>true], 'objects');
				$options	= array_column($taxonomies, 'label', 'name');

				if(count($options) > 1){
					$field	= ['title'=>'分类模式：',	'options'=>$options,	'show_if'=>['key'=>'no_category_base','value'=>1]];
				}else{
					$field	= ['type'=>'hidden',	'value'=>'category'];
				}

				$fields['no_category_base']		= ['short'=>'AD0w1d8NLibtc6CFYsLiJg',	'label'=>'去掉分类目录链接中的 category。'];
				$fields['no_category_base_for']	= $field;
			}
		}

		return $fields;
	}

	public static function parse_fields($fields){
		foreach($fields as &$field){
			if(empty($field['type']) && !isset($field['options'])){
				$field['type']	= 'checkbox';
			}

			$field['type']	= $field['type'] ?? '';

			if(in_array($field['type'], ['fieldset', 'fields'])){
				$field['fields']	= self::parse_fields($field['fields']);
			}

			$short = array_pull($field, 'short');

			if($short){
				$link	= 'https://mp.weixin.qq.com/s/'.$short;
				$key	= $field['type'] == 'view' ? 'value' : 'label';

				if(isset($field[$key])){
					$field[$key]	= '<a target="_blank" href="'.$link.'">'.$field[$key].'</a>';
				}else{
					$field['title']	.= '<a target="_blank" href="'.$link.'" class="dashicons-before dashicons-editor-help"></a>';
				}
			}
		}

		return $fields;
	}

	public static function sanitize_callback(){
		flush_rewrite_rules();
	}

	public static function get_menu_page(){
		return [
			'menu_title'	=> 'WPJAM',
			'icon'			=> 'dashicons-performance',
			'position'		=> '58.99',
			'sub_title'		=> '优化设置',
			'function'		=> 'option',
			'summary'		=> __FILE__,
		];
	}

	public static function get_defaults(){
		return [
			'disable_revision'			=> 1,
			'disable_trackbacks'		=> 1,
			'disable_emoji'				=> 1,
			'disable_texturize'			=> 1,
			'disable_privacy'			=> 1,
			'remove_head_links'			=> 1,
			'remove_capital_P_dangit'	=> 1,
		];
	}

	public static function get_setting($name='', $default=null){
		$value	= parent::get_setting($name, $default);

		if($name == 'no_category_base'){
			if(!$value || $GLOBALS['wp_rewrite']->use_verbose_page_rules){
				return '';
			}

			return self::get_setting('no_category_base_for', 'category');
		}

		return $value;
	}

	public static function filter_html($html){
		$html	= WPJAM_Google_Font::filter_html($html);

		return apply_filters('wpjam_html', $html);
	}

	public static function filter_loader_src($src){
		$ver	= $GLOBALS['wp_version'];

		return ($src && $ver) ? preg_replace('/[\&\?]ver='.preg_quote($ver).'(&|$)/', '', $src) : $src;
	}

	public static function filter_pre_upload($file){
		if(empty($file['md5_filename']) && self::get_setting('timestamp_file_name')){
			return array_merge($file, ['name'=> time().'-'.$file['name']]);
		}

		return $file;
	}

	public static function filter_register_post_type_args($args, $post_type){
		if(did_action('init') && !empty($args['supports']) && is_array($args['supports'])){
			foreach(['trackbacks'=>'disable_trackbacks', 'revisions'=>'disable_revision'] as $support => $setting_name){
				if(self::get_setting($setting_name, 1) && in_array($support, $args['supports'])){
					$args['supports']	= array_diff($args['supports'], [$support]);

					remove_post_type_support($post_type, $support);	// create_initial_post_types 会执行两次
				}
			}
		}

		return $args;
	}

	public static function filter_register_taxonomy_args($args, $taxonomy){
		if(did_action('init') && $taxonomy == self::get_setting('no_category_base')){
			$args['permastruct']	= '%'.$taxonomy.'%';	// 去掉URL中category
		}

		return $args;
	}

	public static function filter_update_attachment_metadata($data){
		if(isset($data['thumb'])){
			$data['thumb'] = basename($data['thumb']);
		}

		return $data;
	}

	public static function init(){
		if(self::get_setting('disable_trackbacks', 1)){
			$GLOBALS['wp']->remove_query_var('tb');
		}

		if(self::get_setting('disable_post_embed')){
			$GLOBALS['wp']->remove_query_var('embed');
		}
	}
}

class WPJAM_Gravatar extends WPJAM_Register{
	public static function get_config($key){
		if($key == 'defaults'){
			return [
				'cravatar'	=> ['title'=>'Cravatar加速服务',	'url'=>'https://cravatar.cn/avatar/'],
				'geekzu'	=> ['title'=>'极客族加速服务',		'url'=>'https://sdn.geekzu.org/avatar/'],
				'loli'		=> ['title'=>'loli加速服务',		'url'=>'https://gravatar.loli.net/avatar/'],
				'sep_cc'	=> ['title'=>'sep.cc加速服务',	'url'=>'https://cdn.sep.cc/avatar/'],
			];
		}elseif($key == 'custom_args'){
			$custom = wpjam_basic_get_setting('gravatar_custom');

			return $custom ? ['title'=>'自定义', 'url'=>$custom] : null;
		}elseif($key == 'custom_fields'){
			return ['gravatar_custom'=>['type'=>'text',	'placeholder'=>'请输入 Gravatar 加速服务地址']];
		}
	}

	public static function filter_url($url){
		$name	= wpjam_basic_get_setting('gravatar');
		$object	= self::get($name);

		if($object){
			$search = [
				'https://secure.gravatar.com/avatar/',
				'http://0.gravatar.com/avatar/',
				'http://1.gravatar.com/avatar/',
				'http://2.gravatar.com/avatar/',
			];

			return str_replace($search, $object->url, $url);
		}

		return $url;
	}
}

class WPJAM_Google_Font extends WPJAM_Register{
	protected static function get_domains(){
		return [
			'googleapis_fonts'			=> 'fonts.googleapis.com',
			'googleapis_ajax'			=> 'ajax.googleapis.com',
			'googleusercontent_themes'	=> 'themes.googleusercontent.com',
			'gstatic_fonts'				=> 'fonts.gstatic.com'
		];
	}

	public static function get_config($key){
		if($key == 'defaults'){
			return [
				'geekzu'	=> ['title'=>'极客族加速服务',	'replace'=>[
					'//fonts.geekzu.org',
					'//gapis.geekzu.org/ajax',
					'//gapis.geekzu.org/g-themes',
					'//gapis.geekzu.org/g-fonts'
				]],
				'loli'		=> ['title'=>'loli加速服务',	'replace'=>[
					'//fonts.loli.net',
					'//ajax.loli.net',
					'//themes.loli.net',
					'//gstatic.loli.net'
				]],
				'ustc'		=> ['title'=>'中科大加速服务',	'replace'=>[
					'//fonts.lug.ustc.edu.cn',
					'//ajax.lug.ustc.edu.cn',
					'//google-themes.lug.ustc.edu.cn',
					'//fonts-gstatic.lug.ustc.edu.cn'
				]]
			];
		}elseif($key == 'custom_args'){
			$replace = [];

			foreach(self::get_domains() as $font_key => $domain){
				$replace[]	= str_replace(['http://','https://'], '//', wpjam_basic_get_setting($font_key));
			}

			return ['title'=>'自定义', 'replace'=>$replace];
		}elseif($key == 'custom_fields'){
			$fields	= [];

			foreach(self::get_domains() as $font_key => $domain){
				$fields[$font_key]	= ['type'=>'text',	'placeholder'=>'请输入'.$domain.'加速服务地址'];
			}

			return $fields;
		}
	}

	public static function filter_html($html){
		$name	= wpjam_basic_get_setting('google_fonts');
		$object	= self::get($name);

		if($object){
			$search	= preg_filter('/^/', '//', array_values(self::get_domains()));
			$html	= str_replace($search, $object->replace, $html);
		}

		return $html;
	}
}

wpjam_register_option('wpjam-basic', [
	'title'			=> '优化设置',
	'plugin_page'	=> 'wpjam-basic',
	'site_default'	=> true,
	'model'			=> 'WPJAM_Basic',
]);

function wpjam_basic_get_setting($name, $default=null){
	return WPJAM_Basic::get_setting($name, $default);
}

function wpjam_basic_update_setting($name, $value){
	return WPJAM_Basic::update_setting($name, $value);
}

function wpjam_basic_delete_setting($name){
	return WPJAM_Basic::delete_setting($name);
}

function wpjam_basic_get_default_settings(){
	return WPJAM_Basic::get_defaults();
}

function wpjam_basic_parse_fields($fields){
	return WPJAM_Basic::parse_fields($fields);
}

function wpjam_add_basic_sub_page($sub_slug, $args=[]){
	wpjam_add_menu_page($sub_slug, array_merge($args, ['parent'=>'wpjam-basic']));
}

// Gravatar
function wpjam_register_gravatar_services($name, $args){
	return WPJAM_Gravatar::register($name, $args);
}

// Google Font
function wpjam_register_google_font_services($name, $args){
	return WPJAM_Google_Font::register($name, $args);
}

add_filter('register_post_type_args',	['WPJAM_Basic', 'filter_register_post_type_args'], 10, 2);
add_filter('register_taxonomy_args',	['WPJAM_Basic', 'filter_register_taxonomy_args'], 10, 2);

// 修正任意文件删除漏洞
add_filter('wp_update_attachment_metadata',	['WPJAM_Basic', 'filter_update_attachment_metadata']);

// 防止重名造成大量的 SQL 请求
add_filter('wp_handle_sideload_prefilter',	['WPJAM_Basic', 'filter_pre_upload']);
add_filter('wp_handle_upload_prefilter',	['WPJAM_Basic', 'filter_pre_upload']);

add_filter('get_avatar_url',	['WPJAM_Gravatar', 'filter_url']);

add_action('wp_loaded', function(){
	ob_start(['WPJAM_Basic', 'filter_html']);
});

add_action('template_redirect', function(){
	if(is_feed()){
		if(wpjam_basic_get_setting('disable_feed')){	// 屏蔽站点 Feed
			wp_die('Feed已经关闭, 请访问<a href="'.get_bloginfo('url').'">网站首页</a>！', 'Feed关闭'	, 200);
		}
	}else{
		$taxonomy	= WPJAM_Basic::get_setting('no_category_base');

		if($taxonomy){	// 开启去掉URL中category，跳转到 no base 的 link
			if((is_category() && $taxonomy == 'category') || is_tax($taxonomy)){
				if(strpos($_SERVER['REQUEST_URI'], '/'.$taxonomy.'/') !== false){
					wp_redirect(site_url(str_replace('/'.$taxonomy, '', $_SERVER['REQUEST_URI'])), 301);
					exit;
				}
			}
		}
	}
});

//移除 WP_Head 无关紧要的代码
if(wpjam_basic_get_setting('remove_head_links', 1)){
	add_filter('the_generator', '__return_empty_string');

	add_filter('style_loader_src',	['WPJAM_Basic', 'filter_loader_src']);
	add_filter('script_loader_src',	['WPJAM_Basic', 'filter_loader_src']);

	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );

	remove_action( 'wp_head', 'feed_links_extra', 3 );
	//remove_action( 'wp_head', 'feed_links', 2 );

	remove_action( 'wp_head', 'index_rel_link' );
	remove_action( 'wp_head', 'parent_post_rel_link', 10);
	remove_action( 'wp_head', 'start_post_rel_link', 10);
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10);

	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );
	remove_action( 'wp_head', 'rest_output_link_wp_head', 10);

	remove_action( 'template_redirect',	'wp_shortlink_header', 11);
	remove_action( 'template_redirect',	'rest_output_link_header', 11);
}

//让用户自己决定是否书写正确的 WordPress
if(wpjam_basic_get_setting('remove_capital_P_dangit', 1)){
	remove_filter( 'the_content', 'capital_P_dangit', 11 );
	remove_filter( 'the_title', 'capital_P_dangit', 11 );
	remove_filter( 'wp_title', 'capital_P_dangit', 11 );
	remove_filter( 'document_title', 'capital_P_dangit', 11 );
	remove_filter( 'comment_text', 'capital_P_dangit', 31 );
	remove_filter( 'widget_text_content', 'capital_P_dangit', 11 );
}

// 屏蔽字符转码
if(wpjam_basic_get_setting('disable_texturize', 1)){
	add_filter('run_wptexturize', '__return_false');
}

//移除 admin bar
if(wpjam_basic_get_setting('remove_admin_bar')){
	add_filter('show_admin_bar', '__return_false');
}

//禁用 XML-RPC 接口
if(wpjam_basic_get_setting('disable_xml_rpc')){
	add_filter('xmlrpc_enabled', '__return_false');
	add_filter('xmlrpc_methods', '__return_empty_array');
	remove_action('xmlrpc_rsd_apis', 'rest_output_rsd');
}

// 屏蔽古腾堡编辑器
if(wpjam_basic_get_setting('disable_block_editor')){
	remove_action('wp_enqueue_scripts', 'wp_common_block_scripts_and_styles');
	remove_action('admin_enqueue_scripts', 'wp_common_block_scripts_and_styles');
	remove_filter('the_content', 'do_blocks', 9);
}

// 屏蔽站点管理员邮箱验证功能
if(wpjam_basic_get_setting('disable_admin_email_check')){
	add_filter('admin_email_check_interval', '__return_false');
}

// 屏蔽 Emoji
if(wpjam_basic_get_setting('disable_emoji', 1)){
	add_action('admin_init', function(){
		remove_action('admin_print_scripts',	'print_emoji_detection_script');
		remove_action('admin_print_styles',		'print_emoji_styles');
	});

	remove_action('wp_head',			'print_emoji_detection_script',	7);
	remove_action('wp_print_styles',	'print_emoji_styles');

	remove_action('embed_head',			'print_emoji_detection_script');

	remove_filter('the_content_feed',	'wp_staticize_emoji');
	remove_filter('comment_text_rss',	'wp_staticize_emoji');
	remove_filter('wp_mail',			'wp_staticize_emoji_for_email');

	add_filter('emoji_svg_url',		'__return_false');

	add_filter('tiny_mce_plugins',	function($plugins){
		return array_diff($plugins, ['wpemoji']);
	});
}

//禁用文章修订功能
if(wpjam_basic_get_setting('disable_revision', 1)){
	if(!defined('WP_POST_REVISIONS')){
		define('WP_POST_REVISIONS', false);
	}

	remove_action('pre_post_update', 'wp_save_post_revision');
}

// 屏蔽Trackbacks
if(wpjam_basic_get_setting('disable_trackbacks', 1)){
	if(!wpjam_basic_get_setting('disable_xml_rpc')){
		//彻底关闭 pingback
		add_filter('xmlrpc_methods', function($methods){
			return array_merge($methods, [
				'pingback.ping'						=> '__return_false',
				'pingback.extensions.getPingbacks'	=> '__return_false'
			]);
		});
	}

	//禁用 pingbacks, enclosures, trackbacks
	remove_action('do_pings', 'do_all_pings', 10);

	//去掉 _encloseme 和 do_ping 操作。
	remove_action('publish_post','_publish_post_hook',5);
}

//禁用 Auto OEmbed
if(wpjam_basic_get_setting('disable_autoembed')){
	remove_filter('the_content',			[$GLOBALS['wp_embed'], 'autoembed'], 8);
	remove_filter('widget_text_content',	[$GLOBALS['wp_embed'], 'autoembed'], 8);
	remove_filter('widget_block_content',	[$GLOBALS['wp_embed'], 'autoembed'], 8);

	remove_action('edit_form_advanced',	[$GLOBALS['wp_embed'], 'maybe_run_ajax_cache']);
	remove_action('edit_page_form',		[$GLOBALS['wp_embed'], 'maybe_run_ajax_cache']);
}

// 屏蔽文章Embed
if(wpjam_basic_get_setting('disable_post_embed')){
	remove_action('wp_head', 'wp_oembed_add_discovery_links');
	remove_action('wp_head', 'wp_oembed_add_host_js');
}

// 屏蔽自动更新和更新检查作业
if(wpjam_basic_get_setting('disable_auto_update')){
	add_filter('automatic_updater_disabled', '__return_true');

	remove_action('init', 'wp_schedule_update_checks');
	remove_action('wp_version_check', 'wp_version_check');
	remove_action('wp_update_plugins', 'wp_update_plugins');
	remove_action('wp_update_themes', 'wp_update_themes');
}

add_action('send_headers', function(){
	$x_frame_options = wpjam_basic_get_setting('x-frame-options');

	if($x_frame_options){
		header('X-Frame-Options: '.$x_frame_options);
	}
});

// 屏蔽后台隐私
if(wpjam_basic_get_setting('disable_privacy', 1)){
	remove_action('user_request_action_confirmed', '_wp_privacy_account_request_confirmed');
	remove_action('user_request_action_confirmed', '_wp_privacy_send_request_confirmation_notification', 12);
	remove_action('wp_privacy_personal_data_exporters', 'wp_register_comment_personal_data_exporter');
	remove_action('wp_privacy_personal_data_exporters', 'wp_register_media_personal_data_exporter');
	remove_action('wp_privacy_personal_data_exporters', 'wp_register_user_personal_data_exporter', 1);
	remove_action('wp_privacy_personal_data_erasers', 'wp_register_comment_personal_data_eraser');
	remove_action('init', 'wp_schedule_delete_old_privacy_export_files');
	remove_action('wp_privacy_delete_old_export_files', 'wp_privacy_delete_old_export_files');

	add_filter('option_wp_page_for_privacy_policy', '__return_zero');
}

if(is_admin()){
	if(wpjam_basic_get_setting('disable_auto_update')){
		remove_action('admin_init', '_maybe_update_core');
		remove_action('admin_init', '_maybe_update_plugins');
		remove_action('admin_init', '_maybe_update_themes');
	}

	if(wpjam_basic_get_setting('disable_block_editor')){
		add_filter('use_block_editor_for_post_type', '__return_false');
	}

	if(wpjam_basic_get_setting('remove_help_tabs')){
		add_action('in_admin_header', function(){
			$GLOBALS['current_screen']->remove_help_tabs();
		});
	}

	if(wpjam_basic_get_setting('remove_screen_options')){
		add_filter('screen_options_show_screen', '__return_false');
		add_filter('hidden_columns', '__return_empty_array');
	}

	if(wpjam_basic_get_setting('disable_privacy', 1)){
		add_action('admin_menu', function(){
			remove_submenu_page('options-general.php', 'options-privacy.php');
			remove_submenu_page('tools.php', 'export-personal-data.php');
			remove_submenu_page('tools.php', 'erase-personal-data.php');
		}, 11);

		add_action('admin_init', function(){
			remove_action('admin_init', ['WP_Privacy_Policy_Content', 'text_change_check'], 100);
			remove_action('edit_form_after_title', ['WP_Privacy_Policy_Content', 'notice']);
			remove_action('admin_init', ['WP_Privacy_Policy_Content', 'add_suggested_content'], 1);
			remove_action('post_updated', ['WP_Privacy_Policy_Content', '_policy_page_updated']);
			remove_filter('list_pages', '_wp_privacy_settings_filter_draft_page_titles', 10, 2);
		}, 1);
	}
}
