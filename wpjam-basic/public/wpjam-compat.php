<?php
class_alias('WPJAM_Verify_TXT', 'WPJAM_VerifyTXT');
class_alias('WPJAM_Option_Items', 'WPJAM_Option');
class_alias('WPJAM_Items', 'WPJAM_Item');
class_alias('WPJAM_Post', 'WPJAM_PostType');
class_alias('WPJAM_Cache_Items', 'WPJAM_List_Cache');
class_alias('WPJAM_Cache_Items', 'WPJAM_ListCache');
class_alias('WPJAM_Cache', 'WPJAM_Cache_Group');

if(!function_exists('function_alias')){
	function function_alias($original, $alias){
		if(!function_exists($original) || function_exists($alias)){
			return false;
		}

		eval('function '.$alias.'(...$args){
			return call_user_func(\''.$original.'\', ...$args);
		}');

		return true;
	}
}

if(!function_exists('update_usermeta_cache')){
	function update_usermeta_cache($user_ids){
		return update_meta_cache('user', $user_ids);
	}
}

if(!function_exists('str_replace_deep')){
	function str_replace_deep($search, $replace, $value){
		return map_deep($value, function($value) use($search, $replace){
			return str_replace($search, $replace, $value);
		});
	}
}

if(!function_exists('user_can_for_blog')){
	function user_can_for_blog($user, $blog_id, $capability, ...$args){
		$switched	= is_multisite() ? switch_to_blog( $blog_id ) : false;
		$can		= user_can( $user, $capability, ...$args );

		if($switched){
			restore_current_blog();
		}

		return $can;
	}
}

if(!function_exists('get_metadata_by_value')){
	function get_metadata_by_value($meta_type, $meta_value, $meta_key=''){
		$data	= wpjam_get_by_meta($meta_type, $meta_key, $meta_value);

		return $data ? (object)current($data) : false;
	}
}

if(!function_exists('wp_cache_delete_multi')){
	function wp_cache_delete_multi($keys, $group=''){
		return wp_cache_delete_multiple($keys, $group);
	}
}

if(!function_exists('wp_cache_get_multi')){
	function wp_cache_get_multi($keys, $group='', $force=false){
		return wp_cache_get_multiple($keys, $group, $force);
	}
}

if(!function_exists('wp_cache_get_with_cas')){
	function wp_cache_get_with_cas($key, $group='', &$cas_token=null){
		return wp_cache_get($key, $group);
	}
}

if(!function_exists('wp_cache_cas')){
	function wp_cache_cas($cas_token, $key, $data, $group='', $expire=0){
		return wp_cache_set($key, $data, $group, $expire);
	}
}

if(!function_exists('array_value_first')){
	function array_value_first(array $arr){
		if(empty($arr)){
			return null;
		}

		return $arr[array_key_first($arr)];
	}
}

if(!function_exists('array_value_last')){
	function array_value_last(array $arr){
		if(empty($arr)){
			return null;
		}

		return $arr[array_key_last($arr)];
	}
}

function wpjam_ajax_enqueue_scripts(){
	wp_enqueue_script('wpjam-ajax');
}

function wpjam_register_verification_code_group($name, $args=[]){
	return WPJAM_Verification_Code::register($name, $args);
}

function wpjam_sanitize_option_value($value){
	return WPJAM_Setting::sanitize_option($value);
}

function wpjam_option_get_setting($option, $setting='', $default=null){
	$object = wpjam_get_option_object($option);

	return $object ? $object->get_setting($setting, $default) : $default;
}

function wpjam_option_update_setting($option, $setting, $value){
	$object = wpjam_get_option_object($option);

	return $object ? $object->update_setting($setting, $value) : null;
}

function wpjam_get_option_setting($name){
	return WPJAM_Option_Setting::get($name)->to_array();
}

function wpjam_add_option_section_fields($option_name, $section_id, $fields){
	return wpjam_add_option_section($option_name, $section_id, $fields);
}

function wpjam_call_list_table_model_method($method, ...$args){
	return null;
}

function wpjam_register_builtin_page_load(...$args){
	$args	= is_array($args[0]) ? $args[0] : $args[1];

	return wpjam_add_admin_load(array_merge($args, ['type'=>'builtin_page']));
}

function wpjam_register_plugin_page_load(...$args){
	$args	= is_array($args[0]) ? $args[0] : $args[1];

	return wpjam_add_admin_load(array_merge($args, ['type'=>'plugin_page']));
}

function wpjam_admin_add_error($message='', $type='success'){
	return wpjam_add_admin_error($message, $type);
}

function wpjam_get_ajax_button($args){
	$name	= array_pull($args, 'action');

	return $name ? wpjam_register_page_action($name, $args)->get_button() : '';
}

function wpjam_get_ajax_form($args){
	$name	= array_pull($args, 'action');

	return $name ? wpjam_register_page_action($name, $args)->get_form() : '';
}

function wpjam_ajax_button($args){
	echo wpjam_get_ajax_button($args);
}

function wpjam_ajax_form($args){
	echo wpjam_get_ajax_form($args);
}

function wpjam_get_nonce_action($key){
	$prefix	= $GLOBALS['plugin_page'] ?? $GLOBALS['current_screen']->id;

	return $prefix.'-'.$key;
}

function wpjam_get_plugin_page_query_data(){
	$value	= wpjam_get_plugin_page_setting('query_data') ?: [];
		
	if($query_data = wpjam_get_current_tab_setting('query_data', true)){
		$value	= array_merge($value, $query_data);
	}

	return $value;
}

function wpjam_set_plugin_page_summary($summary, $append=true){
	wpjam_set_page_summary($summary, 'page', $append);
}

function wpjam_set_builtin_page_summary($summary, $append=true){
	wpjam_set_page_summary($summary, 'page', $append);
}

function wpjam_get_plugin_page_type(){
	return wpjam_get_plugin_page_setting('function');
}

function wpjam_get_list_table_setting($key){
	return isset($GLOBALS['wpjam_list_table']) ? $GLOBALS['wpjam_list_table']->$key : null;
}

function wpjam_get_list_table_filter_link($filters, $title, $class=''){
	return $GLOBALS['wpjam_list_table']->get_filter_link($filters, $title, $class);
}

function wpjam_get_list_table_row_action($name, $args=[]){
	return $GLOBALS['wpjam_list_table']->get_row_action($name, $args);
}

function wpjam_render_list_table_column_items($id, $items, $args=[]){
	return $GLOBALS['wpjam_list_table']->render_column_items($id, $items, $args);
}

function wpjam_get_post_option_fields($post_type, $post_id=null){
	return [];
}

function wpjam_validate_term($term_id, $taxonomy=''){
	return WPJAM_Term::validate($term_id, $taxonomy);
}

function wpjam_get_term_level($term){
	$object	= wpjam_term($term);

	return $object ? $object->level : null;
}

function wpjam_array_first($array, $callback=null){
	return array_first($array, $callback);
}

function wpjam_array_filter($array, $callback){
	return filter_deep($array, $callback);
}

function wpjam_array_get($array, $key, $default=null){
	return array_get($array, $key, $default);
}

function wpjam_array_pull(&$array, $key, $default=null){
	return array_pull($array, $key, $default);
}

function wpjam_array_except($array, ...$keys){
	foreach($keys as $key){
		$array	= array_except($array, $key);
	}

	return $array;
}

function wpjam_array_merge($array, $data, $key=null){
	return merge_deep($array, $data);
}

function wpjam_array_push(&$array, $data, $key=null){
	if(!is_array($data) || !$data || !is_array($array)){
		return false;
	}

	$array	= array_merge($array, $data);

	if(!is_null($key)){
		$array	= array_merge($array, $data);
		$offset	= array_search($key, array_keys($array), true);

		if($offset !== false){
			$array	= array_merge(array_slice($array, 0, $offset), $data, array_slice($array, $offset));
		}
	}

	return true;
}

function wpjam_list_sort($list, $orderby='order', $order='DESC'){
	if(!is_array($list)){
		return $list;
	}

	$index	= 0;
	$scores	= [];

	foreach($list as $key => $item){
		$value	= is_object($item) ? ($item->$orderby ?? 10) : ($item[$orderby] ?? 10);
		$index 	= $index+1;

		$scores[$key]	= [$orderby=>$value, 'index'=>$index];
	}

	$scores	= wp_list_sort($scores, [$orderby=>$order, 'index'=>'ASC'], '', true);

	return wp_array_slice_assoc($list, array_keys($scores));
}

function wpjam_list_filter($list, $args=[], $operator='AND'){
	if(!is_array($list) || empty($args)){
		return $list;
	}

	$filtered	= [];

	foreach($list as $key => $item){
		if(wpjam_match($item, $args, $operator)){
			$filtered[$key]	= $item;
		}
	}

	return $filtered;
}

function wpjam_list_flatten($list, $depth=0, $args=[]){
	if(!is_array($list)){
		return $list;
	}

	$flat	= [];

	$name		= $args['name'] ?? 'name'; 
	$children	= $args['children'] ?? 'children'; 

	foreach($list as $item){
		$item[$name]	= str_repeat('&emsp;', $depth).$item[$name];
		$flat[]			= $item;

		if(!empty($item[$children])){
			$flat	= array_merge($flat, wpjam_list_flatten($item[$children], $depth+1, $args));
		}
	}

	return $flat;
}

function wpjam_field_get_icon($name){
	return WPJAM_Field::get_icon($name);
}

function wpjam_form_field_tmpls($echo=true){}

function wpjam_urlencode_img_cn_name($img_url){
	return $img_url;
}

function wpjam_image_hwstring($size){
	$width	= (int)($size['width']);
	$height	= (int)($size['height']);
	return image_hwstring($width, $height);
}

function wpjam_get_taxonomy_levels($taxonomy){
	return wpjam_get_taxonomy_setting($taxonomy, 'levels', 0);
}

function wpjam_get_taxonomy_fields($taxonomy){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	return $object ? $object->get_fields() : [];
}

function wpjam_is_json($json=''){
	$current = wpjam_get_current_json();

	if($current){
		return $json ? $current == $json : true;
	}

	return false;
}

function wpjam_get_tabbar_options($platform){
	return wp_list_pluck(wpjam_get_tabbar($platform), 'text');
}

function wpjam_get_path_object($page_key){
	return WPJAM_Path::get($page_key);
}

function wpjam_get_paths($platform){
	return WPJAM_Path::get_by(['platform'=>$platform]);
}

function wpjam_get_paths_by_post_type($post_type, $path_type){
	return WPJAM_Path::get_by(compact('post_type', 'path_type'));
}

function wpjam_get_paths_by_taxonomy($taxonomy, $path_type){
	return WPJAM_Path::get_by(compact('taxonomy', 'path_type'));
}

function wpjam_generate_path($data){
	$page_key	= $data['page_key'] ?? '';
	$path_type	= $data['path_type'] ?? '';
	$path_type	= $path_type ?: 'weapp';

	return wpjam_get_path($path_type, $page_key, $data);
}

function wpjam_render_path_item($item, $text, $platforms=[]){
	$platform	= wpjam_get_current_platform($platforms);
	$parsed		= wpjam_parse_path_item($item, $platform);

	return wpjam_get_path_item_link_tag($parsed, $text);
}

function wpjam_parse_query_vars($query_vars, &$args=[]){
	return WPJAM_Posts::parse_query_vars($query_vars, $args);
}

function wpjam_validate_post($post_id, $post_type=null){
	return WPJAM_Post::validate($post_id, $post_type);
}

function wpjam_new_posts($args=[]){
	echo wpjam_get_new_posts($args);
}

function wpjam_top_viewd_posts($args=[]){
	echo wpjam_get_top_viewd_posts($args);
}

function wpjam_get_post_type_fields($post_type){
	$object	= WPJAM_Post_Type::get($post_type);

	return $object ? $object->get_fields() : [];
}

function wpjam_attachment_url_to_postid($url){
	$post_id = wp_cache_get($url, 'attachment_url_to_postid');

	if($post_id === false){
		global $wpdb;

		$upload_dir	= wp_get_upload_dir();
		$path		= str_replace(parse_url($upload_dir['baseurl'], PHP_URL_PATH).'/', '', parse_url($url, PHP_URL_PATH));

		$post_id	= $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $path));

		wp_cache_set($url, $post_id, 'attachment_url_to_postid', DAY_IN_SECONDS);
	}

	return (int) apply_filters( 'attachment_url_to_postid', $post_id, $url );
}

// 获取远程图片
function wpjam_get_content_remote_image_url($img_url, $post_id=null){
	return $img_url;
}

function wpjam_get_content_remote_img_url($img_url, $post_id=0){
	return wpjam_get_content_remote_image_url($img_url, $post_id);
}

function wpjam_image_remote_method($img_url=''){
	return '';
}

function wpjam_is_remote_image($img_url, $strict=true){
	if($strict){
		return !wpjam_is_cdn_url($img_url);
	}else{
		return wpjam_is_external_url($img_url);
	}
}

function wpjam_get_content_width(){
	return (int)apply_filters('wpjam_content_image_width', wpjam_cdn_get_setting('width'));
}

function wpjam_cdn_content($content){
	return wpjam_content_images($content);
}

function wpjam_content_images($content){
	return WPJAM_CDN::filter_content($content);
}

function wpjam_sha1(...$args){
	return WPJAM_Crypt::sha1(...$args);
}

function wpjam_get_post_image_url($image_id, $size='full'){
	if($thumb = wp_get_attachment_image_src($image_id, $size)){
		return $thumb[0];
	}

	return false;
}

function wpjam_has_post_thumbnail(){
	return wpjam_get_post_thumbnail_url() ? true : false;
}

function wpjam_post_thumbnail($size='thumbnail', $crop=1, $class='wp-post-image', $ratio=2){
	echo wpjam_get_post_thumbnail(null, $size, $crop, $class, $ratio);
}

function wpjam_get_post_thumbnail($post=null, $size='thumbnail', $crop=1, $class='wp-post-image', $ratio=2){
	$size	= wpjam_parse_size($size, $ratio);
	if($post_thumbnail_url = wpjam_get_post_thumbnail_url($post, $size, $crop)){
		$image_hwstring	= image_hwstring($size['width']/$ratio, $size['height']/$ratio);
		return '<img src="'.$post_thumbnail_url.'" alt="'.the_title_attribute(['echo'=>false]).'" class="'.$class.'"'.$image_hwstring.' />';
	}else{
		return '';
	}
}

function wpjam_has_term_thumbnail(){
	return wpjam_get_term_thumbnail_url()? true : false;
}

function wpjam_term_thumbnail($size='thumbnail', $crop=1, $class="wp-term-image", $ratio=2){
	echo wpjam_get_term_thumbnail(null, $size, $crop, $class);
}

function wpjam_get_term_thumbnail($term=null, $size='thumbnail', $crop=1, $class="wp-term-image", $ratio=2){
	$size	= wpjam_parse_size($size, $ratio);

	if($term_thumbnail_url = wpjam_get_term_thumbnail_url($term, $size, $crop)){
		$image_hwstring	= image_hwstring($size['width']/$ratio, $size['height']/$ratio);

		return  '<img src="'.$term_thumbnail_url.'" class="'.$class.'"'.$image_hwstring.' />';
	}else{
		return '';
	}
}

function wpjam_display_errors(){}

function wpjam_parse_field_value($field, $args=[]){
	return wpjam_field($field)->value_callback($args);
}

function wpjam_get_field_value($field, $args=[]){
	return wpjam_parse_field_value($field, $args);
}

function wpjam_get_form_fields($admin_column=false){
	return [];
}

function wpjam_validate_fields_value($fields, $values=null){
	return wpjam_fields($fields)->validate($values);
}

function wpjam_validate_field_value($field, $value){
	return wpjam_field($field)->validate($value);
}

function wpjam_prepare_fields_value($fields, $args=[]){
	return wpjam_fields($fields)->prepare($args);
}

function wpjam_get_fields_defaults($fields){
	return wpjam_fields($fields)->get_defaults();
}

function wpjam_get_form_post($fields, $nonce_action='', $capability='manage_options'){
	check_admin_referer($nonce_action);

	if(!current_user_can($capability)){
		ob_clean();
		wp_die('无权限');
	}

	return wpjam_validate_fields_value($fields);
}

function wpjam_form($fields, $form_url, $nonce_action='', $submit_text=''){
	echo '<form method="post" action="'.$form_url.'" enctype="multipart/form-data" id="form">';

	echo wpjam_fields($fields);

	wp_nonce_field($nonce_action);
	wp_original_referer_field(true, 'previous');

	if($submit_text!==false){
		submit_button($submit_text);
	}

	echo '</form>';
}

function wpjam_api_validate_quota(){}
function wpjam_api_validate_access_token(){}

function wpjam_stats_header($args=[]){
	global $wpjam_stats_labels;

	$wpjam_stats_labels	= [];

	WPJAM_Chart::init($args);
	WPJAM_Chart::form($args);

	// do_action('wpjam_stats_header');

	foreach(['start_date', 'start_timestamp', 'end_date', 'end_timestamp', 'date', 'timestamp', 'start_date_2', 'start_timestamp_2', 'end_date_2', 'end_timestamp_2', 'date_type', 'date_format', 'compare'] as $key){
		$wpjam_stats_labels['wpjam_'.$key]	= WPJAM_Chart::get_parameter($key);
	}

	$wpjam_stats_labels['compare_label']	= WPJAM_Chart::get_parameter('start_date').' '.WPJAM_Chart::get_parameter('end_date');
	$wpjam_stats_labels['compare_label_2']	= WPJAM_Chart::get_parameter('start_date_2').' '.WPJAM_Chart::get_parameter('end_date_2');
}

function wpjam_sub_summary($tabs){
	?>
	<h2 class="nav-tab-wrapper nav-tab-small">
	<?php foreach($tabs as $key => $tab){ ?>
		<a class="nav-tab" href="javascript:;" id="tab-title-<?php echo $key;?>"><?php echo $tab['name'];?></a>  	<?php }?>
	</h2>

	<?php foreach($tabs as $key => $tab){ ?>
	<div id="tab-<?php echo $key;?>" class="div-tab" style="margin-top:1em;">
	<?php
	global $wpdb;

	$counts = $wpdb->get_results($tab['counts_sql']);
	$total  = $wpdb->get_var($tab['total_sql']);
	$labels = isset($tab['labels'])?$tab['labels']:'';
	$base   = isset($tab['link'])?$tab['link']:'';

	$new_counts = $new_types = array();
	foreach($counts as $count){
		$link   = $base?($base.'&'.$key.'='.$count->label):'';

		if(is_super_admin() && $tab['name'] == '手机型号'){
			$label  = ($labels && isset($labels[$count->label]))?$labels[$count->label]:'<span style="color:red;">'.$count->label.'</span>';
		}else{
			$label  = ($labels && isset($labels[$count->label]))?$labels[$count->label]:$count->label;
		}

		$new_counts[] = array(
			'label' => $label,
			'count' => $count->count,
			'link'  => $link
		);
	}

	wpjam_donut_chart($new_counts, array('total'=>$total,'show_line_num'=>1,'table_width'=>'420'));

	?>
	</div>
	<?php }
}

function wpjam_send_user_message(...$args){
	if(count($args) == 2){
		$receiver	= $args[0];
		$message	= $args[1];
	}else{
		$message	= $args[0];
		$receiver	= $message['receiver'];
	}

	return WPJAM_User_Message::get_instance($receiver)->add($message);
}

function wpjam_api_set_response(&$response){}

function wpjam_api_signon(){}

function wpjam_register_theme_upgrader($upgrader_url){
	$object	= WPJAM_Theme_Upgrader::register(get_template(), ['upgrader_url'=>$upgrader_url]);

	add_filter('site_transient_update_themes',	[$object, 'filter_site_transient']);
}

function wpjam_data_attribute_string($attr){
	return wpjam_attr($attr, 'data');
}

function wpjam_get_ajax_attribute_string($name, $data=[]){
	return wpjam_get_ajax_data_attr($name, $data);
}

function wpjam_get_ajax_attributes($name, $data=[]){
	return wpjam_get_ajax_data_attr($name, $data, '[]');
}

function_alias('is_login', 'wpjam_is_login');

add_action('wpjam_loaded', function(){
	if(!function_exists('get_post_excerpt')){
		function_alias('wpjam_get_post_excerpt', 'get_post_excerpt');
	}

	function_alias('wpjam_attr', 'wpjam_attribute_string');
	function_alias('wpjam_download_url', 'wpjam_download_image');
	function_alias('wpjam_is_external_url', 'wpjam_is_external_image');

	function_alias('get_post_type_support', 'get_post_type_support_value');

	function_alias('wpjam_get_post_option_fields', 'wpjam_get_post_fields');

	function_alias('wpjam_get_items', 'wpjam_get_current_items');
	function_alias('wpjam_add_item', 'wpjam_add_current_item');

	function_alias('wpjam_parse_ip', 'wpjam_get_ipdata');
	function_alias('wpjam_get_user_agent', 'wpjam_get_ua');
	function_alias('is_macintosh', 'is_mac');
	function_alias('wp_is_mobile', 'wpjam_is_mobile');

	function_alias('wpjam_list_flatten', 'wpjam_flatten_terms');
	function_alias('wpjam_list_sort', 'wpjam_sort_items');

	function_alias('wpjam_is_module', 'is_module');

	function_alias('wpjam_is_json', 'is_wpjam_json');
	function_alias('wpjam_get_json_object', 'wpjam_get_api_setting');
	function_alias('wpjam_get_json_object', 'wpjam_get_api');
	function_alias('wpjam_get_current_json', 'wpjam_get_json');

	function_alias('wpjam_get_path_object', 'wpjam_get_path_obj');
	function_alias('wpjam_get_paths', 'wpjam_get_path_objs');

	function_alias('wpjam_render_query', 'wpjam_get_post_list');
	function_alias('wpjam_get_post_first_image_url', 'wpjam_get_post_first_image');
	function_alias('wpjam_get_post_first_image_url', 'get_post_first_image');

	function_alias('wpjam_cdn_host_replace', 'wpjam_cdn_replace_local_hosts');
	function_alias('wpjam_field', 'wpjam_get_field_html');
	function_alias('wpjam_field', 'wpjam_render_field');
	function_alias('wpjam_parse_options', 'wpjam_parse_field_options');

	function_alias('wpjam_get_qqv_id', 'wpjam_get_qqv_vid');
	function_alias('wpjam_get_qqv_id', 'wpjam_get_qq_vid');

	function_alias('wpjam_has_term_thumbnail', 'wpjam_has_category_thumbnail');
	function_alias('wpjam_has_term_thumbnail', 'wpjam_has_tag_thumbnail');

	function_alias('wpjam_get_term_thumbnail', 'wpjam_get_category_thumbnail');
	function_alias('wpjam_get_term_thumbnail', 'wpjam_get_tag_thumbnail');
	function_alias('wpjam_term_thumbnail', 'wpjam_category_thumbnail');
	function_alias('wpjam_term_thumbnail', 'wpjam_tag_thumbnail');

	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_category_thumbnail_url');
	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_tag_thumbnail_url');

	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_term_thumbnail_src');
	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_category_thumbnail_src');
	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_tag_thumbnail_src');

	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_term_thumbnail_uri');
	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_category_thumbnail_uri');
	function_alias('wpjam_get_term_thumbnail_url', 'wpjam_get_tag_thumbnail_uri');

	function_alias('wpjam_get_post_thumbnail_url', 'wpjam_get_post_thumbnail_src');
	function_alias('wpjam_get_post_thumbnail_url', 'wpjam_get_post_thumbnail_uri');

	function_alias('wpjam_get_default_thumbnail_url', 'wpjam_get_default_thumbnail_src');
	function_alias('wpjam_get_default_thumbnail_url', 'wpjam_get_default_thumbnail_uri');
});

function wpjam_register_route_module($name, $args){
	return wpjam_register_route($name, $args);
}

function wpjam_register_api($name, $args=[]){
	return wpjam_register_json($name, $args);
}

add_filter('wpjam_html', function($html){
	if(has_filter('wpjam_html_replace')){
		$html	= apply_filters_deprecated('wpjam_html_replace', [$html], 'WPJAM Basic 3.4', 'wpjam_html');
	}

	return $html;
},9);

add_action('wpjam_api', function($json){
	if(has_action('wpjam_api_template_redirect')){
		do_action('wpjam_api_template_redirect', $json);
	}
});

// add_action('wpjam_admin_init', function(){
// 	$user_id	= get_current_user_id();
// 	$instance	= WPJAM_User_Message::get_instance($user_id);

// 	wpjam_add_menu_page('wpjam-messages', [
// 		'menu_title'	=>'站内消息',
// 		'capability'	=>'read',
// 		'parent'		=>'users',
// 		'function'		=>[$instance, 'plugin_page'],
// 		'load_callback'	=>[$instance, 'load_plugin_page']
// 	]);
// });

add_filter('rewrite_rules_array', function($rules){
	if(has_filter('wpjam_rewrite_rules')){
		return array_merge(apply_filters('wpjam_rewrite_rules', []), $rules);
	}
	return $rules;
});

class WPJAM_Query{
	public $handler;
	public $query;
	public $query_vars;
	public $result		= [];

	public function __construct($handler, $query=''){
		$this->handler	= $handler;

		if($query){
			$this->query($query);
		}
	}

	public function __get($key){
		if(in_array($key, ['items', 'datas'])){
			return $this->result['items'] ?? [];
		}elseif(in_array($key, ['total', 'found_rows'])){
			return $this->result['total'] ?? 0;
		}elseif($key == 'max_num_pages'){
			return $this->result['max_num_pages'] ?? 0;
		}elseif($key == 'next_cursor'){
			return $this->result['next_cursor'] ?? 0;
		}elseif($key == 'request'){
			return $this->result['request'] ?? '';
		}
	}

	public function __isset($key){
		return $this->$key !== null;
	}

	public function query($query){
		$this->query_vars	= $this->query = wp_parse_args($query);
		$this->result		= $this->handler->query_items($this->query_vars);

		return $this;
	}
}

class WPJAM_Post_Option{
	public static function get($name){
		return wpjam_get_post_option($name);
	}

	public static function get_registereds(){
		return wpjam_get_post_options();
	}
}

class WPJAM_Term_Option{
	public static function get($name){
		return wpjam_get_term_option($name);
	}

	public static function get_registereds(){
		return wpjam_get_term_options();
	}
}

class WPJAM_Theme_Upgrader extends WPJAM_Register{
	public function filter_site_transient($transient){
		if($this->upgrader_url){
			$theme	= $this->name;

			if(empty($transient->checked[$theme])){
				return $transient;
			}

			$remote	= get_transient('wpjam_theme_upgrade_'.$theme);

			if(false == $remote){
				$remote = wpjam_remote_request($this->upgrader_url);

				if(!is_wp_error($remote)){
					set_transient('wpjam_theme_upgrade_'.$theme, $remote, HOUR_IN_SECONDS*12);
				}
			}

			if($remote && !is_wp_error($remote)){
				if(version_compare($transient->checked[$theme], $remote['new_version'], '<')){
					$transient->response[$theme]	= $remote;
				}
			}
		}

		return $transient;
	}
}

class WPJAM_PlatformBit extends WPJAM_Bit{
	public function set_platform($bit){}

	public function get_platform(){}
}

class WPJAM_OPENSSL_Crypt{
	private $key;
	private $method = 'aes-128-cbc';
	private $iv = '';
	private $options = OPENSSL_RAW_DATA;

	public function __construct($key, $args=[]){
		$this->key		= $key;
		$this->method	= $args['method'] ?? $this->method;
		$this->options	= $args['options'] ?? $this->options;
		$this->iv		= $args['iv'] ?? '';
	}

	public function encrypt($text){
		return openssl_encrypt($text, $this->method, $this->key, $this->options, $this->iv);
	}

	public function decrypt($encrypted_text){
		return openssl_decrypt($encrypted_text, $this->method, $this->key, $this->options, $this->iv);
	}
}

class WPJAM_PostContent extends WPJAM_Content_Items{
	public function __construct($args=[]){}
}

class WPJAM_MetaItem extends WPJAM_Meta_Items{
	public function __construct($meta_type, $meta_key, $args=[]){}
}

class WPJAM_User_Message{
	private $user_id	= 0;
	private $messages	= [];

	private static $instances	= [];

	public static function get_instance($user_id){
		if(!isset(self::$instances[$user_id])){
			self::$instances[$user_id] = new self($user_id);
		}

		return self::$instances[$user_id];
	}

	private function __construct($user_id){
		$this->user_id	= $user_id;

		if($user_id && ($messages = get_user_meta($user_id, 'wpjam_messages', true))){
			$this->messages	= array_filter($messages, function($message){ return $message['time'] > time() - MONTH_IN_SECONDS * 3; });
		}
	}

	public function get_messages(){
		return $this->messages;
	}

	public function get_unread_count(){
		$messages	= array_filter($this->messages, function($message){ return $message['status'] == 0; });

		return count($messages);
	}

	public function set_all_read(){
		array_walk($this->messages, function(&$message){ $message['status'] == 1; });

		return $this->save();
	}

	public function add($message){
		$message	= wp_parse_args($message, [
			'sender'	=> '',
			'receiver'	=> '',
			'type'		=> '',
			'content'	=> '',
			'status'	=> 0,
			'time'		=> time()
		]);

		$message['content'] = wp_strip_all_tags($message['content']);

		$this->messages[]	= $message;

		return $this->save();
	}

	public function delete($i){
		if(isset($this->messages[$i])){
			unset($this->messages[$i]);
			return $this->save();
		}

		return true;
	}

	public function save(){
		if(empty($this->messages)){
			return delete_user_meta($this->user_id, 'wpjam_messages');
		}else{
			return update_user_meta($this->user_id, 'wpjam_messages', $this->messages);
		}
	}

	public function load_plugin_page(){
		wpjam_register_page_action('delete_message', [
			'button_text'	=> '删除',
			'class'			=> 'message-delete',
			'callback'		=> [$this, 'ajax_delete'],
			'direct'		=> true,
			'confirm'		=> true
		]);
	}

	public function ajax_delete(){
		$message_id	= (int)wpjam_get_data_parameter('message_id');
		$messages	= $this->get_messages();

		if($messages && isset($messages[$message_id])){
			$result	= $this->delete($message_id);

			if(is_wp_error($result)){
				wpjam_send_json($result);
			}else{
				wpjam_send_json(['message_id'=>$message_id]);
			}
		}

		wpjam_send_error_json('invalid_id', ['消息']);
	}

	public function plugin_page(){
		$messages	= $this->data;

		if(empty($messages)){
			echo '<p>暂无站内消息</p>';
			return;
		}

		if($this->get_unread_count()){
			$this->set_all_read();
		}

		$sender_ids			= [];
		$post_ids_list		= [];
		$comment_ids_list	= [];

		foreach($messages as $message){
			$sender_ids[]	= $message['sender'];
			$blog_id		= $message['blog_id'];
			$post_id		= $message['post_id'];
			$comment_id		= $message['comment_id'];
			if($blog_id){
				if($post_id){
					$post_ids_list[$blog_id][]		= $post_id;
				}

				if($comment_id){
					$comment_ids_list[$blog_id][]	= $comment_id;
				}
			}
		}

		$senders	= get_users(['blog_id'=>0, 'include'=>$sender_ids]);

		foreach($post_ids_list as $blog_id => $post_ids){
			$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

			WPJAM_Post::update_caches($post_ids);

			if($switched){
				restore_current_blog();
			}
		}

		foreach($comment_ids_list as $blog_id => $comment_ids){
			$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

			get_comments(['include'=>$comment_ids]);

			if($switched){
				restore_current_blog();
			}
		}
		?>

		<ul class="messages">
		<?php foreach($messages as $i => $message){
			$alternate	= empty($alternate)?'alternate':'';
			$sender		= get_userdata($message['sender']);

			$type		= $message['type'];
			$content	= $message['content'];
			$blog_id	= $message['blog_id'];
			$post_id	= $message['post_id'];
			$comment_id	= $message['comment_id'];


			if(empty($sender)){
				continue;
			}

			if($blog_id && $post_id){
				$switched	= is_multisite() ? switch_to_blog($blog_id) : false;

				$post		= get_post($post_id);

				if($post){
					$topic_title	= $post->post_title;
				}

				if($switched){
					restore_current_blog();
				}
			}else{
				$topic_title		= '';
			}
		?>
			<li id="message_<?php echo $i; ?>" class="<?php echo $alternate; echo empty($message['status'])?' unread':'' ?>">
				<div class="sender-avatar"><?php echo get_avatar($message['sender'], 60);?></div>
				<div class="message-time"><?php echo wpjam_human_time_diff($message['time']);?><p><?php echo wpjam_get_page_button('delete_message',['data'=>['message_id'=>$i]]);?></p></div>
				<div class="message-content">

				<?php

				if($type == 'topic_comment'){
					$prompt	= '<span class="message-sender">'.$sender->display_name.'</span>在你的帖子「<a href="'.admin_url('admin.php?page=wpjam-topics&action=comment&id='.$post_id.'#comment_'.$comment_id).'">'.$topic_title.'</a>」给你留言了：'."\n\n";
				}elseif($type == 'comment_reply' || $type == 'topic_reply'){
					$prompt	= '<span class="message-sender">'.$sender->display_name.'</span>在帖子「<a href="'.admin_url('admin.php?page=wpjam-topics&action=comment&id='.$post_id.'#comment_'.$comment_id).'">'.$topic_title.'</a>」回复了你的留言：'."\n\n";
				}else{
					$prompt	= '<span class="message-sender">'.$sender->display_name.'：'."\n\n";
				}

				echo wpautop($prompt.$content);

				?>
				</div>
			</li>
			<?php } ?>
		</ul>

		<style type="text/css">
			ul.messages{ max-width:640px; }
			ul.messages li {margin: 10px 0; padding:10px; margin:10px 0; background: #fff; min-height: 60px;}
			ul.messages li.alternate{background: #f9f9f9;}
			ul.messages li.unread{font-weight: bold;}
			ul.messages li a {text-decoration:none;}
			ul.messages li div.sender-avatar {float:left; margin:0px 10px 0px 0;}
			ul.messages li div.message-time{float: right; width: 60px;}
			ul.messages li .message-delete{color: #a00;}
			ul.messages li div.message-content p {margin: 0 70px 10px 70px; }
		</style>

		<script type="text/javascript">
		jQuery(function($){
			$('body').on('page_action_success', function(e, response){
				var action		= response.page_action;
				var action_type	= response.page_action_type;

				if(action == 'delete_message'){
					var message_id	= response.message_id;
					$('#message_'+message_id).animate({opacity: 0.1}, 500, function(){ $(this).remove();});
				}
			});
		});
		</script>

		<?php
	}
}

trait WPJAM_Setting_Trait{
	private $settings		= [];
	private $option_name	= '';
	private $site_default	= false;

	private function init($option_name, $site_default=false){
		$this->option_name	= $option_name;
		$this->site_default	= $site_default;

		$this->reset_settings();
	}

	public function __get($name){
		if(in_array($name, ['option_name', 'site_default'])){
			return $this->$name;
		}

		if(is_null(get_option($option_name, null))){
			add_option($option_name, []);
		}

		return $this->get_setting($name);
	}

	public function __set($name, $value){
		return $this->update_setting($name, $value);
	}

	public function __isset($name){
		return isset($this->settings[$name]);
	}

	public function __unset($name){
		$this->delete_setting($name);
	}

	public function get_settings(){
		return $this->settings;
	}

	public function reset_settings(){
		$value	= wpjam_get_option($this->option_name);

		$this->settings	= is_array($value) ? $value : [];

		if($this->site_default){
			$site_value	= wpjam_get_site_option($this->option_name);
			$site_value	= is_array($site_value) ? $site_value : [];

			$this->settings	+= $site_value;
		}
	}

	public function get_setting($name='', $default=null){
		return $name ? ($this->settings[$name] ?? $default) : $this->settings;
	}

	public function update_setting($name, $value){
		$this->settings[$name]	= $value;

		return $this->save();
	}

	public function delete_setting($name){
		$this->settings	= array_except($this->settings, $name);

		return $this->save();
	}

	private function save($settings=[]){
		if($settings){
			$this->settings	= array_merge($this->settings, $settings);
		}

		return update_option($this->option_name, $this->settings);
	}

	private static $instances	= [];

	public static function get_instance(){
		$blog_id = get_current_blog_id();	//多站点情况下，switch_to_blog 之后还能从正确的站点获取设置

		if(!isset(self::$instances[$blog_id])){
			self::$instances[$blog_id] = new self();
		}

		return self::$instances[$blog_id];
	}

	public static function register_option($args=[]){
		$instance	= self::get_instance();
		$defaults	= [];

		$defaults['site_default']	= $instance->site_default;

		if(method_exists($instance, 'sanitize_callback')){
			$defaults['sanitize_callback']	= [$instance, 'sanitize_callback'];
		}

		if(method_exists($instance, 'get_summary')){
			$defaults['summary']	= [$instance, 'get_summary'];
		}

		if(method_exists($instance, 'get_sections')){
			$defaults['sections']	= [$instance, 'get_sections'];
		}elseif(method_exists($instance, 'get_fields')){
			$defaults['fields']		= [$instance, 'get_fields'];
		}

		if(current_user_can('manage_options') && isset($_GET['reset'])){
			delete_option($instance->option_name);
		}

		return wpjam_register_option($instance->option_name, wp_parse_args($args, $defaults));
	}
}

trait WPJAM_Register_Trait{
	protected $name;
	protected $args;
	protected $filtered	= false;

	public function __construct($name, $args=[]){
		$this->name	= $name;
		$this->args	= $args;
	}

	protected function get_args(){
		if(!$this->filtered){
			$filter	= strtolower(get_called_class()).'_args';

			$this->args		= apply_filters($filter, $this->args, $this->name);
			$this->filtered	= true;
		}

		return $this->args;
	}

	public function __get($key){
		if($key == 'name'){
			return $this->name;
		}else{
			$args	= $this->get_args();
			return $args[$key] ?? null;
		}
	}

	public function __set($key, $value){
		if($key != 'name'){
			$this->args	= $this->get_args();
			$this->args[$key]	= $value;
		}
	}

	public function __isset($key){
		$args	= $this->get_args();
		return isset($args[$key]);
	}

	public function __unset($key){
		$this->args	= $this->get_args();
		unset($this->args[$key]);
	}

	public function to_array(){
		return $this->get_args();
	}

	protected static $_registereds	= [];

	public static function parse_name($name){
		if(empty($name)){
			trigger_error(self::class.'的注册 name 为空');
			return null;
		}elseif(is_numeric($name)){
			trigger_error(self::class.'的注册 name「'.$name.'」'.'为纯数字');
			return null;
		}elseif(!is_string($name)){
			trigger_error(self::class.'的注册 name「'.var_export($name, true).'」不为字符串');
			return null;
		}

		return $name;
	}

	public static function register(...$args){
		if(count($args) == 1){
			$object	= $args[0];
			$name	= $object->name;
		}else{
			$name	= self::parse_name($args[0]);

			if(is_null($name)){
				return null;
			}

			$object	= new static($name, $args[1]);
		}

		self::$_registereds[$name]	= $object;

		return $object;
	}

	protected static function register_instance($name, $object){
		self::$_registereds[$name]	= $object;

		return $object;
	}

	public static function unregister($name){
		unset(self::$_registereds[$name]);
	}

	public static function get_by($args=[], $output='objects'){
		return self::get_registereds($args, $output);
	}

	public static function get_registereds($args=[], $output='objects', $operator='and'){
		$registereds	= $args ? wp_filter_object_list(self::$_registereds, $args, $operator, false) : self::$_registereds;

		if($output == 'names'){
			return array_keys($registereds);
		}elseif(in_array($output, ['args', 'settings'])){
			return array_map(function($registered){
				return $registered->to_array();
			}, $registereds);
		}else{
			return $registereds;
		}
	}

	public static function get($name){
		return self::$_registereds[$name] ?? null;
	}

	public static function exists($name){
		return self::get($name) ? true : false;
	}
}

trait WPJAM_Type_Trait{
	use WPJAM_Register_Trait;
}

// 直接在 handler 里面定义即可。
// 需要在使用的 CLASS 中设置 public static $meta_type
trait WPJAM_Meta_Trait{
	public static function get_meta_type_object(){
		return wpjam_get_meta_type_object(self::$meta_type);
	}

	public static function add_meta($id, $meta_key, $meta_value, $unique=false){
		return self::get_meta_type_object()->add_data($id, $meta_key, $meta_value, $unique);
	}

	public static function delete_meta($id, $meta_key, $meta_value=''){
		return self::get_meta_type_object()->delete_data($id, $meta_key, $meta_value);
	}

	public static function get_meta($id, $key = '', $single = false){
		return self::get_meta_type_object()->get_data($id, $key, $single);
	}

	public static function update_meta($id, $meta_key, $meta_value, $prev_value=''){
		return self::get_meta_type_object()->update_data($id, $meta_key, wp_slash($meta_value), $prev_value);
	}

	public static function delete_meta_by_key($meta_key){
		return self::get_meta_type_object()->delete_by_key($meta_key);
	}

	public static function update_meta_cache($object_ids){
		self::get_meta_type_object()->update_cache($object_ids);
	}

	public static function create_meta_table(){
		self::get_meta_type_object()->create_table();
	}

	public static function get_meta_table(){
		return self::get_meta_type_object()->get_table();
	}
}
