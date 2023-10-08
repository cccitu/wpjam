<?php
// register
function wpjam_register($group, ...$args){
	return WPJAM_Register::register_by_group($group, ...$args);
}

function wpjam_unregister($group, $name, $args=[]){
	WPJAM_Register::unregister_by_group($group, $name, $args);
}

function wpjam_get_registereds($group){
	return WPJAM_Register::get_by_group($group);
}

function wpjam_get_registered_object($group, $name){
	return $name ? WPJAM_Register::get_by_group($group, $name) : null;
}

function wpjam_generate_query_data($args, $type='data_parameter'){
	$data	= [];
	$args	= $args ?: [];

	foreach($args as $arg){
		$callback	= $type == 'data_parameter' ? 'wpjam_get_data_parameter' : 'wpjam_get_parameter';
		$data[$arg]	= call_user_func($callback, $arg);
	}

	return $data;
}

// handler
function wpjam_register_handler(...$args){
	if(count($args) >= 2){
		$name	= $args[0];
		$args	= $args[1];
	}else{
		$name	= null;
		$args	= $args[0];
	}

	if(is_object($args)){
		$object	= $args;

		if(!$name){
			return;
		}
	}else{
		$map	= ['option_items'=>'option_name', 'db'=>'table_name'];
		$type	= array_pull($args, 'type');

		if($type && isset($map[$type])){
			$type_name	= array_pull($args, $map[$type]) ?: $name;
		}else{
			foreach($map as $type_key => $name_key){
				$type_name	= array_pull($args, $name_key);

				if($type_name){
					$type	= $type_key;

					break;
				}
			}
		}

		if(empty($type_name)){
			return;
		}

		$name	= $name ?: $type_name;
		$class	= 'WPJAM_'.$type;
		$object	= new $class($type_name, $args);
	}

	wpjam_add_item('handler', $name, $object);

	return $object;
}

function wpjam_get_handler($name){
	return wpjam_get_item('handler', $name);
}

// Platform
function wpjam_register_platform($name, $args){
	return WPJAM_Platform::register($name, $args);
}

function wpjam_get_platform_object($name){
	return WPJAM_Platform::get($name);
}

// wpjam_get_current_platform(['weapp', 'template'], $ouput);	// 从一组中（空则全部）根据优先级获取
// wpjam_get_current_platform(['path'=>true], $ouput);			// 从已注册路径的根据优先级获取
function wpjam_get_current_platform($args=[], $output='name'){
	return WPJAM_Platform::get_current($args, $output);
}

// 获取已经注册路径的平台
function wpjam_get_current_platforms($output='names'){
	$objects	= WPJAM_Platform::get_by(['path'=>true]);

	return $output == 'names' ? array_keys($objects) : $objects;
}

function wpjam_is_platform($name){
	return (WPJAM_Platform::get($name))->verify();
}

function wpjam_get_platform_options($output='bit'){
	return WPJAM_Platform::get_options($output);
}

// Path
function wpjam_has_path($platform, $page_key, $strict=false){
	$object	= WPJAM_Platform::get($platform);

	return $object ? $object->has_path($page_key, $strict) : false;
}

function wpjam_get_path($platform, $page_key, $args=[]){
	$object	= WPJAM_Platform::get($platform);

	if($object){
		if(is_array($page_key)){
			$args		= $page_key;
			$page_key	= array_pull($args, 'page_key');
		}

		return $object->get_path($page_key, $args);
	}

	return '';
}

function wpjam_get_tabbar($platform, $page_key=''){
	$object	= WPJAM_Platform::get($platform);

	return $object ? $object->get_tabbar($page_key) : [];
}

function wpjam_get_page_keys($platform, $args=null, $operator='AND'){
	$object	= WPJAM_Platform::get($platform);

	if($object){
		if(is_string($args) && in_array($args, ['with_page', 'page'])){
			return $object->get_page();
		}else{
			$items	= $object->get_items();

			if($args && is_array($args)){
				$items	= wp_list_filter($items, $args, $operator);
			}

			return array_keys($items);
		}
	}

	return [];
}

function wpjam_parse_path_item($item, $platform=null, $parse_backup=true){
	if(!$platform || is_array($platform)){
		$platforms 		= $platform ?: wpjam_get_current_platforms();
		$platform		= wpjam_get_current_platform($platforms);
		$parse_backup	= count($platforms) > 1;
	}

	$parsed	= null;
	$object	= WPJAM_Platform::get($platform);

	if($object){
		$parsed	= $object->parse_path($item);

		if(!$parsed && $parse_backup && !empty($item['page_key_backup'])){
			$parsed	= $object->parse_path($item, [
				'postfix'	=> '_backup',
				'title'		=> '备用',
				'default'	=> 'none'
			]);
		}
	}

	return $parsed ?: ['type'=>'none'];
}

function wpjam_validate_path_item($item, $platforms, $type=''){
	$args	= $type == 'backup' ? [
		'postfix'	=> '_backup',
		'title'		=> '备用',
		'default'	=> 'none'
	] : [];

	foreach($platforms as $platform){
		$object	= WPJAM_Platform::get($platform);
		$result	= $object->validate_path($item, $args);

		if(is_wp_error($result)){
			if($type == '' && $result->get_error_code() == 'invalid_page_key' && count($platforms) > 1){
				return wpjam_validate_path_item($item, $platforms, 'backup');
			}else{
				return $result;
			}
		}
	}

	return $result;
}

function wpjam_register_path($page_key, ...$args){
	return WPJAM_Path::create($page_key, ...$args);
}

function wpjam_unregister_path($page_key, $platform=''){
	return WPJAM_Path::remove($page_key, $platform);
}

function wpjam_get_path_fields($platforms=null, $args=[]){
	return WPJAM_Path::get_fields($platforms, $args);
}

function wpjam_get_path_item_link_tag($parsed, $text){
	return WPJAM_Path::get_link_tag($parsed, $text);
}

// Items
function wpjam_get_items_object($name){
	$object	= wpjam_get_registered_object('items', $name);

	return $object ?: wpjam_register('items', $name, []);
}

function wpjam_get_items($name){
	return wpjam_get_items_object($name)->get_items();
}

function wpjam_get_item($name, $key){
	return wpjam_get_items_object($name)->get_item($key);
}

function wpjam_add_item($name, ...$args){
	return wpjam_get_items_object($name)->add_item(...$args);
}

// Data Type
function wpjam_register_data_type($name, $args=[]){
	return WPJAM_Data_Type::register($name, $args);
}

function wpjam_get_data_type_object($name){
	return WPJAM_Data_Type::get($name);
}

function wpjam_strip_data_type($args){
	return WPJAM_Data_Type::strip($args);
}

function wpjam_slice_data_type(&$args, $strip=false){
	return WPJAM_Data_Type::slice($args, $strip);
}

function wpjam_get_data_type_field($name, $args){
	$object	= WPJAM_Data_Type::get($name);

	return $object ? $object->get_field($args) : [];
}

function wpjam_get_post_id_field($post_type='post', $args=[]){
	return WPJAM_Post_Type_Data_Type::get_field(array_merge($args, ['post_type'=>$post_type]));
}

function wpjam_get_authors($args=[], $return='users'){
	return get_users(array_merge($args, ['capability'=>'edit_posts']));
}

function wpjam_get_video_mp4($id_or_url){
	return WPJAM_Video_Data_Type::get_video_mp4($id_or_url);
}

function wpjam_get_qqv_mp4($vid){
	return WPJAM_Video_Data_Type::get_qqv_mp4($vid);
}

function wpjam_get_qqv_id($id_or_url){
	return WPJAM_Video_Data_Type::get_qqv_id($id_or_url);
}

// Setting
function wpjam_setting($type, $option, $blog_id=0){
	return WPJAM_Setting::get_instance($type, $option, $blog_id);
}

function wpjam_get_setting_object($type, $option, $blog_id=0){
	return wpjam_setting($type, $option, $blog_id);
}

function wpjam_get_setting($option, $name, $blog_id=0){
	return wpjam_setting('option', $option, $blog_id)->get_setting($name);
}

function wpjam_update_setting($option, $name, $value, $blog_id=0){
	return wpjam_setting('option', $option, $blog_id)->update_setting($name, $value);
}

function wpjam_delete_setting($option, $name, $blog_id=0){
	return wpjam_setting('option', $option, $blog_id)->delete_setting($name);
}

function wpjam_get_option($option, $blog_id=0, $default=[]){
	return wpjam_setting('option', $option, $blog_id)->get_option($default);
}

function wpjam_update_option($option, $value, $blog_id=0){
	return wpjam_setting('option', $option, $blog_id)->update_option($value);
}

function wpjam_get_site_setting($option, $name){
	return wpjam_setting('site_option', $option)->get_setting($name);
}

function wpjam_get_site_option($option, $default=[]){
	return wpjam_setting('site_option', $option)->get_option($default);
}

function wpjam_update_site_option($option, $value){
	return wpjam_setting('site_option', $option)->update_option($value);
}

// Option
function wpjam_register_option($name, $args=[]){
	return WPJAM_Option_Setting::create($name, $args);
}

function wpjam_get_option_object($name){
	return WPJAM_Option_Setting::get($name);
}

function wpjam_add_option_section($option_name, ...$args){
	return WPJAM_Option_Section::add($option_name, ...$args);
}

function wpjam_register_extend_option($name, $dir, $args=[]){
	return WPJAM_Extend::create($dir, $args, $name);
}

function wpjam_register_extend_type($name, $dir, $args=[]){
	return wpjam_register_extend_option($name, $dir, $args);
}

function wpjam_load_extends($dir, $args=[]){
	WPJAM_Extend::create($dir, $args);
}

function wpjam_get_file_summary($file){
	return WPJAM_Extend::get_file_summay($file);
}

function wpjam_get_extend_summary($file){
	return WPJAM_Extend::get_file_summay($file);
}

// Permastruct
function wpjam_get_permastruct($name){
	return $GLOBALS['wp_rewrite']->get_extra_permastruct($name);
}

function wpjam_set_permastruct($name, $value){
	return $GLOBALS['wp_rewrite']->extra_permastructs[$name]['struct']	= $value;
}

// Meta Type
function wpjam_register_meta_type($name, $args=[]){
	return WPJAM_Meta_Type::register($name, $args);
}

function wpjam_get_meta_type_object($name){
	return WPJAM_Meta_Type::get($name);
}

function wpjam_register_meta_option($meta_type, $name, $args){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->register_option($name, $args) : null;
}

function wpjam_unregister_meta_option($meta_type, $name){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->unregister_option($name) : null;
}

function wpjam_get_meta_options($meta_type, $args=[]){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->get_options($args) : [];
}

function wpjam_get_meta_option($meta_type, $name, $return='object'){
	$object	= WPJAM_Meta_Type::get($meta_type);
	$option	= $object ? $object->get_option($name) : null;

	if($return == 'object'){
		return $option;
	}else{
		return $option ? $option->to_array() : [];
	}
}

function wpjam_get_by_meta($meta_type, ...$args){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->get_by_key(...$args) : [];
}

// wpjam_get_metadata($meta_type, $object_id, $meta_keys)
// wpjam_get_metadata($meta_type, $object_id, $meta_key, $default)
function wpjam_get_metadata($meta_type, $object_id, ...$args){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->get_data_with_default($object_id, ...$args) : null;
}

// wpjam_update_metadata($meta_type, $object_id, $data, $defaults=[])
// wpjam_update_metadata($meta_type, $object_id, $meta_key, $meta_value, $default=null)
function wpjam_update_metadata($meta_type, $object_id, ...$args){
	$object	= WPJAM_Meta_Type::get($meta_type);

	return $object ? $object->update_data_with_default($object_id, ...$args) : null;
}

function wpjam_delete_metadata($meta_type, $object_id, $key){
	$object	= WPJAM_Meta_Type::get($meta_type);

	if($object && $key){
		foreach((array)$key as $k){
			$object->delete_data($object_id, $k);
		}
	}

	return true;
}

// LazyLoader
function wpjam_register_lazyloader($name, $args){
	if(!in_array($name, ['term_meta', 'comment_meta'])){
		return WPJAM_Lazyloader::register($name, $args);
	}
}

function wpjam_lazyload($name, $ids, ...$args){
	$ids	= array_unique($ids);
	$ids	= array_filter($ids);

	if(!$ids){
		return;
	}

	if(in_array($name, ['term_meta', 'comment_meta'])){
		$name	= wpjam_remove_postfix($name, '_meta');
		$object	= wp_metadata_lazyloader();
		$object->queue_objects($name, $ids);
	}else{
		$object	= WPJAM_Lazyloader::get($name);

		if(!$object && str_ends_with($name, '_meta')){
			$meta_type	= wpjam_remove_postfix($name, '_meta');
			$mt_object	= wpjam_get_meta_type_object($meta_type);

			if($mt_object){
				$object	= $mt_object->register_lazyloader();
			}
		}

		if($object){
			$object->queue_objects($ids, ...$args);
		}
	}
}

// Post Type
function wpjam_register_post_type($name, $args=[]){
	return WPJAM_Post_Type::register($name, $args);
}

function wpjam_get_post_type_object($name){
	return WPJAM_Post_Type::get($name);
}

function wpjam_add_post_type_field($post_type, ...$args){
	$object	= WPJAM_Post_Type::get($post_type);

	return $object ? $object->add_field(...$args) : null;
}

function wpjam_remove_post_type_field($post_type, $key){
	$object	= WPJAM_Post_Type::get($post_type);

	return $object ? $object->remove_field($key) : null;
}

function wpjam_get_post_type_setting($post_type, $key, $default=null){
	$object	= WPJAM_Post_Type::get($post_type);

	if($object && isset($object->$key)){
		return $object->$key;
	}

	return $default;
}

function wpjam_update_post_type_setting($post_type, $key, $value){
	$object	= WPJAM_Post_Type::get($post_type);

	if($object){
		$object->$key	= $value;
	}
}

// Post Option
function wpjam_register_post_option($meta_box, $args=[]){
	return wpjam_register_meta_option('post', $meta_box, $args);
}

function wpjam_unregister_post_option($meta_box){
	wpjam_unregister_meta_option('post', $meta_box);
}

function wpjam_get_post_options($post_type='', $args=[]){
	return wpjam_get_meta_options('post', array_merge($args, ['post_type'=>$post_type]));
}

function wpjam_get_post_option($name, $return='object'){
	return wpjam_get_meta_option('post', $name, $return);
}

// Post Column
function wpjam_register_posts_column($name, ...$args){
	if(is_admin()){
		$field	= is_array($args[0]) ? $args[0] : ['title'=>$args[0], 'callback'=>($args[1] ?? null)];

		return wpjam_register_list_table_column($name, array_merge($field, ['data_type'=>'post_type']));
	}
}

function wpjam_unregister_posts_column($name){
	if(is_admin() && did_action('current_screen')){
		wpjam_add_item(get_current_screen()->id.'_removed_columns', $name);
	}
}

// Post
function wpjam_post($post, $wp_error=false){
	return WPJAM_Post::get_instance($post, null, $wp_error);
}

function wpjam_get_post_object($post, $post_type=null){
	return WPJAM_Post::get_instance($post, $post_type);
}

function wpjam_get_post($post, $args=[]){
	$object	= wpjam_post($post);

	return $object ? $object->parse_for_json($args) : null;
}

function wpjam_get_post_views($post=null){
	$post	= get_post($post);

	return $post ? (int)get_post_meta($post->ID, 'views', true) : 0;
}

function wpjam_update_post_views($post=null, $addon=1){
	$post	= get_post($post);

	if($post){
		$views	= wpjam_get_post_views($post);

		if(is_single() && $post->ID == get_queried_object_id()){
			static $viewd = false;

			if($viewd){	// 确保只加一次
				return $views;
			}

			$viewd	= true;
		}

		$views	+= $addon;

		update_post_meta($post->ID, 'views', $views);

		return $views;
	}

	return null;
}

function wpjam_get_post_excerpt($post=null, $length=0, $more=null){
	$post	= get_post($post);

	if($post){
		if($post->post_excerpt){
			return wp_strip_all_tags($post->post_excerpt, true);
		}

		$excerpt	= get_the_content('', false, $post);
		$excerpt	= strip_shortcodes($excerpt);
		$excerpt	= excerpt_remove_blocks($excerpt);
		$excerpt	= wp_strip_all_tags($excerpt, true);
		$length		= $length ?: apply_filters('excerpt_length', 200);
		$more		= $more ?? apply_filters('excerpt_more', ' &hellip;');

		return mb_strimwidth($excerpt, 0, $length, $more, 'utf-8');
	}

	return '';
}

function wpjam_get_post_content($post=null, $raw=false){
	$content	= get_the_content('', false, $post);

	return $raw ? $content : str_replace(']]>', ']]&gt;', apply_filters('the_content', $content));
}

function wpjam_get_post_first_image_url($post=null, $size='full'){
	$post		= get_post($post);
	$content	= $post ? $post->post_content : '';

	if($content){
		if(preg_match('/class=[\'"].*?wp-image-([\d]*)[\'"]/i', $content, $matches)){
			return wp_get_attachment_image_url($matches[1], $size);
		}

		if(preg_match('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)){
			return wpjam_get_thumbnail($matches[1], $size);
		}
	}

	return '';
}

function wpjam_get_post_images($post=null, $large='', $thumbnail='', $full=true){
	$object	= wpjam_post($post);

	return $object ? $object->parse_images($large, $thumbnail, $full) : [];
}

function wpjam_get_post_thumbnail_url($post=null, $size='full', $crop=1){
	$object	= wpjam_post($post);

	return $object ? $object->get_thumbnail_url($size, $crop) : '';
}

// Post Query
function wpjam_query($args=[]){
	return new WP_Query(wp_parse_args($args, [
		'no_found_rows'			=> true,
		'ignore_sticky_posts'	=> true,
	]));
}

function wpjam_parse_query($wp_query, $args=[], $parse=true){
	if($parse){
		return WPJAM_Posts::parse($wp_query, $args);
	}else{
		return wpjam_render_query($wp_query, $args);
	}
}

function wpjam_get_posts($query_vars, $args=[]){
	return wpjam_parse_query($query_vars, $args);
}

function wpjam_render_query($wp_query, $args=[]){
	return WPJAM_Posts::render($wp_query, $args);
}

// wpjam_get_related_posts_query($number);
// wpjam_get_related_posts_query($post_id, $args);
function wpjam_get_related_posts_query(...$args){
	if(count($args) <= 1){
		$post	= get_the_ID();
		$args	= ['number'=>$args[0] ?? 5];
	}else{
		$post	= $args[0];
		$args	= $args[1];
	}

	return WPJAM_Posts::get_related_query($post, $args);
}

function wpjam_get_related_object_ids($tt_ids, $number, $page=1){
	return WPJAM_Posts::get_related_object_ids($tt_ids, $number, $page);
}

function wpjam_related_posts($args=[]){
	echo wpjam_get_related_posts(null, $args, false);
}

function wpjam_get_related_posts($post=null, $args=[], $parse=false){
	$wp_query	= wpjam_get_related_posts_query($post, $args);

	if($parse){
		$args['filter']	= 'wpjam_related_post_json';
	}

	return wpjam_parse_query($wp_query, $args, $parse);
}

function wpjam_get_new_posts($args=[], $parse=false){
	return wpjam_parse_query([
		'posts_per_page'	=> 5,
		'orderby'			=> 'date',
	], $args, $parse);
}

function wpjam_get_top_viewd_posts($args=[], $parse=false){
	return wpjam_parse_query([
		'posts_per_page'	=> 5,
		'orderby'			=> 'meta_value_num',
		'meta_key'			=> 'views',
	], $args, $parse);
}


// Taxonomy
function wpjam_register_taxonomy($name, ...$args){
	if(count($args) == 2){
		$args	= array_merge($args[1], ['object_type'=>$args[0]]);
	}else{
		$args	= $args[0];
	}

	return WPJAM_Taxonomy::register($name, $args);
}

function wpjam_get_taxonomy_object($name){
	return WPJAM_Taxonomy::get($name);
}

function wpjam_add_taxonomy_field($taxonomy, ...$args){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	return $object ? $object->add_field(...$args) : null;
}

function wpjam_remove_taxonomy_field($taxonomy, $key){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	return $object ? $object->remove_field($key) : null;
}

function wpjam_get_taxonomy_setting($taxonomy, $key, $default=null){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	if($object && isset($object->$key)){
		return $object->$key;
	}

	return $default;
}

function wpjam_update_taxonomy_setting($taxonomy, $key, $value){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	if($object){
		$object->$key	= $value;
	}
}

function wpjam_get_taxonomy_query_key($taxonomy){
	$query_keys	= ['category'=>'cat', 'post_tag'=>'tag_id'];

	return $query_keys[$taxonomy] ?? $taxonomy.'_id';
}

function wpjam_get_term_id_field($taxonomy='category', $args=[]){
	$object	= WPJAM_Taxonomy::get($taxonomy);

	return $object ? $object->get_id_field($args) : [];
}

// Term Option
function wpjam_register_term_option($name, $args=[]){
	return wpjam_register_meta_option('term', $name, $args);
}

function wpjam_unregister_term_option($name){
	wpjam_unregister_meta_option('term', $name);
}

function wpjam_get_term_options($taxonomy='', $args=[]){
	return wpjam_get_meta_options('term', array_merge($args, ['taxonomy'=>$taxonomy]));
}

function wpjam_get_term_option($name, $return='object'){
	return wpjam_get_meta_option('term', $name, $return);
}

// Term Column
function wpjam_register_terms_column($name, ...$args){
	if(is_admin()){
		$field	= is_array($args[0]) ? $args[0] : ['title'=>$args[0], 'callback'=>($args[1] ?? null)];

		return wpjam_register_list_table_column($name, array_merge($field, ['data_type'=>'taxonomy']));
	}
}

function wpjam_unregister_terms_column($name){
	if(is_admin() && did_action('current_screen')){
		wpjam_add_item(get_current_screen()->id.'_removed_columns', $name);
	}
}

// Term
function wpjam_term($term, $wp_error=false){
	return WPJAM_Term::get_instance($term, null, $wp_error);
}

function wpjam_get_term_object($term, $taxonomy=''){
	return WPJAM_Term::get_instance($term, $taxonomy);
}

function wpjam_get_term($term, $taxonomy=''){
	$object	= wpjam_term($term, $taxonomy);

	return $object ? $object->parse_for_json() : null;
}

if(!function_exists('get_term_taxonomy')){
	function get_term_taxonomy($id){
		$term	= get_term($id);

		return ($term && !is_wp_error($term)) ? $term->taxonomy : null;
	}
}

function wpjam_get_term_thumbnail_url($term=null, $size='full', $crop=1){
	$object	= wpjam_term($term);

	return $object ? $object->get_thumbnail_url($size, $crop) : '';
}

function wpjam_get_terms($args, $max_depth=null){
	return WPJAM_Terms::parse($args, $max_depth);
}

function wpjam_get_all_terms($taxonomy){
	return get_terms([
		'suppress_filter'	=> true,
		'taxonomy'			=> $taxonomy,
		'hide_empty'		=> false,
		'orderby'			=> 'none',
		'get'				=> 'all'
	]);
}

// User
function wpjam_user($user, $wp_error=false){
	return WPJAM_User::get_instance($user, $wp_error);
}

function wpjam_get_user_object($user){
	return wpjam_user($user);
}

function wpjam_get_user($user, $size=96){
	$object	= wpjam_user($user);

	return $object ? $object->parse_for_json($size) : null;
}

// Bind
function wpjam_register_bind($type, $appid, $args){
	$object	= wpjam_get_bind_object($type, $appid);

	return $object ?: WPJAM_Bind::create($type, $appid, $args);
}

function wpjam_get_bind_object($type, $appid){
	return WPJAM_Bind::get($type.':'.$appid);
}

// User Signup
function wpjam_register_user_signup($name, $args){
	return WPJAM_User_Signup::create($name, $args);
}

function wpjam_get_user_signups($args=[], $output='objects', $operator='and'){
	return WPJAM_User_Signup::get_registereds($args, $output, $operator);
}

function wpjam_get_user_signup_object($name){
	return WPJAM_User_Signup::get($name);
}

// AJAX
function wpjam_register_ajax($name, $args){
	return WPJAM_AJAX::register($name, $args);
}

function wpjam_get_ajax_data_attr($name, $data=[], $return=null){
	$object	= WPJAM_AJAX::get($name);

	return $object ? $object->get_attr($data, $return) : ($return ? null : []);
}

// Capability
function wpjam_register_capability($cap, $map_meta_cap){
	if(!has_filter('map_meta_cap', 'wpjam_filter_map_meta_cap')){
		add_filter('map_meta_cap', 'wpjam_filter_map_meta_cap', 10, 4);
	}

	wpjam_add_item('capability', $cap, $map_meta_cap);
}

function wpjam_filter_map_meta_cap($caps, $cap, $user_id, $args){
	if(!in_array('do_not_allow', $caps) && $user_id){
		$callback = wpjam_get_item('capability', $cap);

		if($callback){
			return call_user_func($callback, $user_id, $args, $cap);
		}
	}

	return $caps;
}

// Verification Code
function wpjam_generate_verification_code($key, $group='default'){
	$object	= WPJAM_Verification_Code::get_instance($group);

	return $object->generate($key);
}

function wpjam_verify_code($key, $code, $group='default'){
	$object	= WPJAM_Verification_Code::get_instance($group);

	return $object->verify($key, $code);
}

// Verify TXT
function wpjam_register_verify_txt($name, $args){
	return WPJAM_Verify_TXT::register($name, $args);
}

// Upgrader
function wpjam_register_plugin_updater($hostname, $update_url){
	return WPJAM_Updater::create('plugin', $hostname, $update_url);
}

function wpjam_register_theme_updater($hostname, $update_url){
	return WPJAM_Updater::create('theme', $hostname, $update_url);
}

// Notice
function wpjam_admin_notice($blog_id=0){
	if(is_multisite()){
		if($blog_id && !get_site($blog_id)){
			return;
		}
	}

	$object	= WPJAM_Notice::get_instance();

	return $object->set_args(['blog_id'=>$blog_id]);
}

function wpjam_add_admin_notice($notice, $blog_id=0){
	$object	= wpjam_admin_notice($blog_id);

	return $object ? $object->insert($notice) : null;
}

function wpjam_user_notice($user_id=0){
	if($user_id && !get_userdata($user_id)){
		return;
	}

	$object	= WPJAM_User_Notice::get_instance();

	return $object->set_args(['user_id'=>$user_id]);
}

function wpjam_add_user_notice($user_id, $notice){
	$object	= wpjam_user_notice($user_id);

	return $object ? $object->insert($notice) : null;
}

function wpjam_preprocess_args($args){
	$hooks	= array_pull($args, 'hooks');
	$init	= array_pull($args, 'init');

	if($init && $init !== true){
		wpjam_load('init', $init);
	}

	if($hooks && $hooks !== true){
		wpjam_hooks($hooks);
	}

	return $args;
}

// Menu Page
function wpjam_add_menu_page(...$args){
	if(is_array($args[0])){
		$menu_page	= $args[0];
	}else{
		$page_type	= !empty($args[1]['plugin_page']) ? 'tab_slug' : 'menu_slug';
		$menu_page	= array_merge($args[1], [$page_type => $args[0]]);

		if(!is_admin() && isset($menu_page['function']) && $menu_page['function'] == 'option'){
			if(!empty($menu_page['sections']) || !empty($menu_page['fields'])){
				$option_name	= $menu_page['option_name'] ?? $menu_slug;

				wpjam_register_option($option_name, $menu_page);
			}
		}
	}

	$menu_pages	= wp_is_numeric_array($menu_page) ? $menu_page : [$menu_page];

	foreach($menu_pages as $menu_page){
		$menu_page	= wpjam_preprocess_args($menu_page);

		if(is_admin()){
			if(!empty($menu_page['tab_slug'])){
				if(!empty($menu_page['title'])){
					$name	= array_pull($menu_page, 'tab_slug');

					wpjam_register_tab_page($name, $menu_page);
				}
			}else{
				wpjam_add_admin_menu($menu_page);
			}
		}
	}
}


