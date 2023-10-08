<?php
class WPJAM_Post{
	use WPJAM_Instance_Trait;

	protected $id;

	protected function __construct($id){
		$this->id	= (int)$id;
	}

	public function __get($key){
		if(in_array($key, ['id', 'post_id'])){
			return $this->id;
		}elseif($key == 'views'){
			return (int)get_post_meta($this->id, 'views', true);
		}elseif($key == 'permalink'){
			return get_permalink($this->id);
		}elseif($key == 'ancestors'){
			return get_post_ancestors($this->id);
		}elseif($key == 'children'){
			return get_children($this->id);
		}elseif($key == 'viewable'){
			return is_post_publicly_viewable($this->id);
		}elseif($key == 'format'){
			return get_post_format($this->id) ?: '';
		}elseif($key == 'taxonomies'){
			return get_object_taxonomies($this->post);
		}elseif($key == 'type_object'){
			return wpjam_get_post_type_object($this->post_type);
		}elseif($key == 'icon'){
			return $this->type_object ? (string)$this->type_object->icon : '';
		}elseif($key == 'thumbnail'){
			if($this->supports('thumbnail')){
				return get_the_post_thumbnail_url($this->id, 'full');
			}

			return '';
		}elseif($key == 'images'){
			if($this->supports('images')){
				return get_post_meta($this->id, 'images', true) ?: [];
			}

			return [];
		}elseif($key == 'post'){
			return get_post($this->id);
		}else{
			$data	= get_post($this->id, ARRAY_A);

			if($key == 'data'){
				return $data;
			}elseif(!str_starts_with($key, 'post_') && isset($data['post_'.$key])){
				return $data['post_'.$key];
			}elseif(isset($data[$key])){
				return $data[$key];
			}else{
				return wpjam_get_metadata('post', $this->id, $key, null);
			}
		}
	}

	public function __isset($key){
		return $this->$key !== null;
	}

	public function __call($method, $args){
		if(in_array($method, ['supports', 'get_sizes', 'get_size'])){
			return $this->type_object ? call_user_func_array([$this->type_object, $method], $args) : null;
		}elseif(in_array($method, ['get_content', 'get_excerpt', 'get_first_image_url'])){
			$function	= 'wpjam_get_post_'.wpjam_remove_prefix($method, 'get_');

			return call_user_func($function, $this->post, ...$args);
		}elseif(in_array($method, ['get_thumbnail_url', 'get_images'])){
			$method	= str_replace('get_', 'parse_', $method);

			return call_user_func([$this, $method], ...$args);
		}

		return $this->call_dynamic_method($method, ...$args);
	}

	public function save($data){
		$status	= array_get($data, 'post_status') ?: array_get($data, 'status');

		if($status == 'publish'){
			$result	= $this->is_publishable();

			if(is_wp_error($result) || !$result){
				return $result ?: new WP_Error('unpublishable', '不可发布');
			}
		}

		return self::update($this->id, $data, false);
	}

	public function set_status($status){
		return $this->save(['post_status'=>$status]);
	}

	public function publish(){
		return $this->set_status('publish');
	}

	public function unpublish(){
		return $this->set_status('draft');
	}

	public function is_publishable(){
		return true;
	}

	public function get_unserialized(){
		$content	= $this->content;

		if($content && is_serialized($content)){
			$unserialized	= @unserialize($content);

			if(!$unserialized){
				$unserialized	= wpjam_unserialize($content);

				if($unserialized && is_array($unserialized)){
					$this->save(['content'=>$content]);
				}
			}

			return $unserialized ?: [];
		}

		return [];
	}

	public function get_terms($taxonomy='post_tag'){
		return get_the_terms($this->id, $taxonomy) ?: [];
	}

	public function set_terms($terms='', $taxonomy='post_tag', $append=false){
		return wp_set_post_terms($this->id, $terms, $taxonomy, $append);
	}

	public function in_term($taxonomy, $terms=null){
		return is_object_in_term($this->id, $taxonomy, $terms);
	}

	public function in_taxonomy($taxonomy){
		return is_object_in_taxonomy($this->post, $taxonomy);
	}

	public function parse_thumbnail_url($size='thumbnail', $crop=1){
		if($this->thumbnail){
			$thumbnail	= $this->thumbnail;
		}elseif($this->images){
			$thumbnail	= $this->images[0];
		}else{
			$thumbnail	= apply_filters('wpjam_post_thumbnail_url', '', $this->post);
		}

		if($thumbnail){
			$size	= $size ?: $this->get_size('thumbnail');
			$size	= $size	?: 'thumbnail';

			return wpjam_get_thumbnail($thumbnail, $size, $crop);
		}

		return '';
	}

	public function parse_images($large_size='', $thumbnail_size='', $full_size=true){
		$images	= [];

		if($this->images){
			$sizes	= [];
			$count	= count($this->images);

			if($count == 1){
				$image	= current($this->images);
				$query	= wpjam_parse_image_query($image);

				if(!$query){
					$query	= wpjam_get_image_size($image, 'url');
					$query	= $query ?: ['width'=>0, 'height'=>0];
					$image	= add_query_arg($query, $image);

					update_post_meta($this->id, 'images', [$image]);
				}

				$orientation	= $query['orientation']  ?? '';
			}else{
				$orientation	= '';
			}

			$sizes	= $this->get_sizes($orientation) ?: [];

			foreach(['large'=>$large_size, 'thumbnail'=>$thumbnail_size] as $key => $value){
				if($value === false){
					unset($sizes[$key]);
				}elseif($value){
					$sizes[$key]	= $value;
				}
			}

			foreach($this->images as $image){
				$image_arr = [];

				foreach($sizes as $name => $size){
					$image_arr[$name]	= wpjam_get_thumbnail($image, $size);

					if($name == 'thumbnail'){
						$query	= wpjam_parse_image_query($image);
						$size	= wpjam_parse_size($size);

						if($query && !empty($query['orientation'])){
							$image_arr['orientation']	= $query['orientation'];
						}

						foreach(['width', 'height'] as $key){
							if($query){
								$image_arr[$key]		= $query[$key] ?? 0;
							}

							$image_arr[$name.'_'.$key]	= $size[$key] ?? 0;
						}
					}
				}

				if($full_size){
					$sizes['full']		= true;
					$image_arr['full']	= wpjam_get_thumbnail($image);
				}

				$images[]	= count($sizes) == 1 ? current($image_arr) : $image_arr;
			}
		}

		return $images;
	}

	public function parse_for_json($args=[]){
		$args	= wp_parse_args($args, [
			'list_query'		=> false,
			'content_required'	=> false,
			'raw_content'		=> false,
		]);

		$size	= $args['thumbnail_size'] ?? ($args['size'] ?? null);
		$json	= array_merge(
			[
				'id'		=> $this->id,
				'type'		=> $this->type,
				'post_type'	=> $this->post_type,
				'status'	=> $this->status,
				'views'		=> $this->views,
				'icon'		=> $this->icon,
				'title'		=> $this->parse_field('title'),
				'excerpt'	=> $this->parse_field('excerpt'),
				'thumbnail'	=> $this->get_thumbnail_url($size),
				'user_id'	=> (int)$this->author,
			],
			$this->parse_field('author', $args),
			$this->parse_field('date', $args),
			$this->parse_field('modified'),
			$this->parse_field('password'),
			$this->parse_field('name')
		);

		if($this->supports('page-attributes')){
			$json['menu_order']	= (int)$this->menu_order;
		}

		if($this->supports('post-formats')){
			$json['format']	= $this->format;
		}

		if($this->supports('images')){
			$json['images']	= $this->parse_images();
		}

		if($args['list_query']){
			return $json;
		}

		$json	= array_merge(
			$json, 
			$this->parse_field('meta', $args),
			$this->parse_field('taxonomies', $args),
			$this->parse_field('content', $args)
		);

		return apply_filters('wpjam_post_json', $json, $this->id, $args);
	}

	public function parse_field($field, $args=[]){
		$parsed	= [];

		if(in_array($field, ['title', 'excerpt'])){
			return $this->supports($field) ? html_entity_decode(call_user_func('get_the_'.$field, $this->id)) : '';
		}elseif($field == 'name'){
			if($this->viewable){
				$parsed['name']		= urldecode($this->name);
				$parsed['post_url']	= str_replace(home_url(), '', $this->permalink);
			}
		}elseif($field == 'author'){
			if($this->supports('author')){
				$parsed['author']	= wpjam_get_user($this->author);
			}
		}elseif($field == 'taxonomies'){
			foreach($this->taxonomies as $taxonomy){
				if($taxonomy != 'post_format' && is_taxonomy_viewable($taxonomy)){
					$parsed[$taxonomy]	= array_map('wpjam_get_term', $this->get_terms($taxonomy));
				}
			}
		}elseif($field == 'content'){
			if((is_single($this->id) || is_page($this->id) || $args['content_required'])){
				if($this->supports('editor')){
					if($args['raw_content']){
						$parsed['raw_content']	= $this->content;
					}

					$parsed['content']		= wpjam_get_post_content($this->post);
					$parsed['multipage']	= (bool)$GLOBALS['multipage'];

					if($parsed['multipage']){
						$parsed['numpages']	= $GLOBALS['numpages'];
						$parsed['page']		= $GLOBALS['page'];
					}
				}else{
					if(is_serialized($this->content)){
						$parsed['content']	= $this->get_unserialized();
					}
				}
			}
		}elseif(in_array($field, ['date', 'modified'])){
			$timestamp	= get_post_timestamp($this->id, $field);
			$prefix		= $field == 'modified' ? 'modified_' : '';
			$parsed		= [
				$prefix.'timestamp'	=> $timestamp,
				$prefix.'time'		=> wpjam_human_time_diff($timestamp),
				$prefix.'date'		=> wpjam_date('Y-m-d', $timestamp),
			];

			if($field == 'date' && !$args['list_query'] && is_main_query()){
				$current_posts	= $GLOBALS['wp_query']->posts;

				if($current_posts && in_array($this->id, array_column($current_posts, 'ID'))){
					if(is_new_day()){
						$GLOBALS['previousday']	= $GLOBALS['currentday'];

						$parsed['day']	= wpjam_human_date_diff($parsed['date']);
					}else{
						$parsed['day']	= '';
					}
				}
			}
		}elseif($field == 'password'){
			if($this->password){
				return [
					'password_protected'	=> true,
					'password_required'		=> post_password_required($this->id),
				];
			}
		}elseif($field == 'meta'){
			foreach(wpjam_get_post_options($this->post_type) as $option){
				$parsed	= array_merge($parsed, $option->prepare($this->id));
			}
		}

		return $parsed;
	}

	public function meta_input(...$args){
		if($args){
			return wpjam_update_metadata('post', $this->id, ...$args);
		}
	}

	public function value_callback($field){
		if($field == 'tax_input'){
			$value	= [];

			foreach($this->taxonomies as $taxonomy){
				$terms	= get_the_terms($this->id, $taxonomy);

				$value[$taxonomy]	= $terms ? array_column($terms, 'term_id') : [];
			}

			return $value;
		}elseif(isset($this->data[$field])){
			return $this->data[$field];
		}else{
			return wpjam_get_metadata('post', $this->id, $field);
		}
	}

	// update/insert 方法同时支持 title 和 post_xxx 字段写入 post 中，meta 字段只支持 meta_input
	// update_callback 方法只支持 post_xxx 字段写入 post 中，其他字段都写入 meta_input
	public function update_callback($data, $defaults){
		$post_data	= [];

		foreach($this->data as $post_key => $old_value){
			$value	= array_pull($data, $post_key);

			if(!is_null($value)){
				unset($defaults[$post_key]);

				if($old_value != $value){
					$post_data[$post_key]	= $value;
				}
			}
		}

		$tax_input	= array_pull($data, 'tax_input');

		if($tax_input){
			$post_data['tax_input']	= $tax_input;
		}

		$result	= $post_data ? $this->save($post_data) : true;

		if(!is_wp_error($result) && $data){
			return $this->meta_input($data, $defaults);
		}

		return $result;
	}

	public function upload_external_images(){
		$content	= $this->content;

		if(preg_match_all('/<img.*?src=[\'"](.*?)[\'"].*?>/i', $content, $matches)){
			$img_urls	= array_unique($matches[1]);
			$replace	= wpjam_fetch_external_images($img_urls, $this->id);

			if($replace){
				return $this->save(['post_content'=>str_replace($img_urls, $replace, $content)]);
			}

			return new WP_Error('error', '文章中无外部图片');
		}

		return new WP_Error('error', '文章中无图片');
	}

	public static function get_instance($post=null, $post_type=null, $wp_error=false){
		$post	= $post ?: get_post();
		$post	= static::validate($post, $post_type);

		if(is_wp_error($post)){
			return $wp_error ? $post : null;
		}

		$post_type	= get_post_type($post); 
		$object		= wpjam_get_post_type_object($post_type);
		$model		= $object ? $object->model : 'WPJAM_Post';

		return call_user_func([$model, 'instance'], $post->ID);
	}

	public static function validate($post_id, $post_type=null){
		$post	= $post_id ? self::get_post($post_id) : null;

		if(!$post || !($post instanceof WP_Post)){
			return new WP_Error('invalid_post');
		}

		if(!post_type_exists($post->post_type)){
			return new WP_Error('invalid_post_type');
		}

		$post_type	= $post_type ?? static::get_current_post_type();

		if($post_type && $post_type !== 'any' && !in_array($post->post_type, (array)$post_type)){
			return new WP_Error('invalid_post_type');
		}

		return $post;
	}

	public static function get($post){
		$data	= self::get_post($post, ARRAY_A);

		if($data){
			$data['id']	= $data['ID'];

			if(is_serialized($data['post_content'])){
				$data['post_content']	= maybe_unserialize($data['post_content']);
			}

			foreach($data as $key => $value){
				if(str_starts_with($key, 'post_')){
					$data[wpjam_remove_prefix($key, 'post_')]	= $value;
				}
			}
		}

		return $data;
	}

	public static function insert($data){
		$result	= static::validate_data($data);

		if(is_wp_error($result)){
			return $result;
		}

		if(isset($data['post_type'])){
			if(!post_type_exists($data['post_type'])){
				return new WP_Error('invalid_post_type');
			}
		}else{
			$data['post_type']	= static::get_current_post_type() ?: 'post';
		}

		if(empty($data['post_status'])){
			$cap	= get_post_type_object($data['post_type'])->cap->publish_posts;

			$data['post_status']	= current_user_can($cap) ? 'publish' : 'draft';
		}

		$data	= static::sanitize_data($data);
		$data	= wp_parse_args($data, [
			'post_author'	=> get_current_user_id(),
			'post_date'		=> wpjam_date('Y-m-d H:i:s'),
		]);

		$meta_input	= array_pull($data, 'meta_input');
		$post_id	= wp_insert_post(wp_slash($data), true, true);

		if(!is_wp_error($post_id) && $meta_input){
			wpjam_update_metadata('post', $post_id, $meta_input);
		}

		return $post_id;
	}

	public static function update($post_id, $data, $validate=true){
		if($validate){
			$result	= self::validate($post_id);

			if(is_wp_error($result)){
				return $result;
			}
		}

		$result	= static::validate_data($data, $post_id);

		if(is_wp_error($result)){
			return $result;
		}

		$data		= static::sanitize_data($data, $post_id);
		$meta_input	= array_pull($data, 'meta_input');
		$result		= wp_update_post(wp_slash($data), true, true);

		if(!is_wp_error($result) && $meta_input){
			wpjam_update_metadata('post', $post_id, $meta_input);
		}

		return $result;
	}

	public static function delete($post_id, $force_delete=true){
		$result	= self::validate($post_id);

		if(is_wp_error($result)){
			return $result;
		}

		$result	= wp_delete_post($post_id, $force_delete);

		return $result ? true : new WP_Error('delete_error', '删除失败');
	}

	protected static function validate_data($data, $post_id=0){
		return true;
	}

	protected static function sanitize_data($data, $post_id=0){
		foreach([
			'title',
			'content',
			'excerpt',
			'name',
			'status',
			'author',
			'parent',
			'password',
			'date',
			'date_gmt',
			'modified',
			'modified_gmt'
		] as $key){
			if(!isset($data['post_'.$key]) && isset($data[$key])){
				$data['post_'.$key]	= $data[$key];
			}
		}

		if(isset($data['post_content']) && is_array($data['post_content'])){
			$data['post_content']	= maybe_serialize($data['post_content']);
		}

		if($post_id){
			$data['ID'] = $post_id;

			if(isset($data['post_date']) && !isset($data['post_date_gmt'])){
				$current_date_gmt	= get_post($post_id)->post_date_gmt;

				if($current_date_gmt && $current_date_gmt != '0000-00-00 00:00:00'){
					$data['post_date_gmt']	= get_gmt_from_date($data['post_date']);
				}
			}
		}

		return $data;
	}

	public static function get_by_ids($post_ids){
		return static::update_caches($post_ids);
	}

	public static function update_caches($post_ids){
		$post_ids 	= array_filter($post_ids);
		$post_ids	= array_unique($post_ids);

		_prime_post_caches($post_ids, false, false);

		$posts	= wp_cache_get_multiple($post_ids, 'posts');
		$posts	= array_filter($posts);

		do_action('wpjam_update_post_caches', $posts);

		return array_map('get_post', $posts);
	}

	public static function get_post($post, $output=OBJECT, $filter='raw'){
		if($post && is_numeric($post)){	// 不存在情况下的缓存优化
			$found	= false;
			$cache	= wp_cache_get($post, 'posts', false, $found);

			if($found){
				if(is_wp_error($cache)){
					return $cache;
				}elseif(!$cache){
					return null;
				}
			}else{
				$_post	= WP_Post::get_instance($post);

				if(!$_post){	// 防止重复 SQL 查询。
					wp_cache_add($post, false, 'posts', 10);
					return null;
				}
			}
		}

		return get_post($post, $output, $filter);
	}

	public static function get_current_post_type(){
		$object	= WPJAM_Post_Type::get_by_model(get_called_class(), 'WPJAM_Post');

		return $object ? $object->name : null;
	}

	public static function query_items($args){
		$layout	= array_pull($args, 'layout');

		if($layout == 'calendar'){
			$args['monthnum']		= $args['month'];
			$args['posts_per_page']	= -1;
		}else{
			if(isset($args['number'])){
				$args['posts_per_page']	= $args['number'];
			}
		}

		$args['post_status']	= array_pull($args, 'status') ?: 'any';
		$args['post_type']		= static::get_current_post_type();

		$wp_query	= $GLOBALS['wp_query'];
		$wp_query->query($args);

		if($layout == 'calendar'){
			$items	= [];

			foreach($wp_query->posts as $post){
				$date	= explode(' ', $post->post_date)[0];

				$items[$date][]	= $post;
			}

			return $items;
		}else{
			return [
				'items'	=> $wp_query->posts,
				'total'	=> $wp_query->found_posts
			];
		}
	}

	public static function get_filterable_fields(){
		return ['status'];
	}

	public static function get_views(){
		$post_type	= static::get_current_post_type();

		if($post_type && get_current_screen()->base != 'edit'){
			$counts	= wp_count_posts($post_type);
			$views	= ['all'=>['filter'=>['status'=>null, 'show_sticky'=>null], 'label'=>'全部', 'count'=>array_sum((array)$counts)]];

			foreach(get_post_stati(['show_in_admin_status_list'=>true], 'objects') as $status => $object){
				if(!empty($counts->$status)){
					$views[$status]	= ['filter'=>['status'=>$status], 'label'=>$object->label, 'count'=>$counts->$status];
				}
			}

			return $views;
		}
	}

	public static function filter_fields($fields, $id){
		if($id && !is_array($id) && !isset($fields['title']) && !isset($fields['post_title'])){
			$object	= self::get_instance($id);
			$field	= ['title'=>$object->type_object->label.'标题', 'type'=>'view', 'value'=>$object->title];
			$fields	= array_merge(['title'=>$field], $fields);
		}

		return $fields;
	}

	public static function filter_link($post_link, $post){
		$post_type	= get_post_type($post);

		if(array_search('%'.$post_type.'_id%', $GLOBALS['wp_rewrite']->rewritecode, true)){
			$post_link	= str_replace('%'.$post_type.'_id%', $post->ID, $post_link);
		}

		if(strpos($post_link, '%') !== false){
			$search	= $replace = [];

			foreach(get_object_taxonomies($post_type, 'objects') as $taxonomy => $tax_object){
				if($tax_object->rewrite){
					$tax_slug	= $tax_object->rewrite['slug'];

					if(strpos($post_link, '%'.$tax_slug.'%') !== false){
						$search[]	= '%'.$tax_slug.'%';
						$terms		= get_the_terms($post->ID, $taxonomy);
						$replace[]	= $terms ? current($terms)->slug : $taxonomy;
					}
				}
			}

			if($search){
				$post_link	= str_replace($search, $replace, $post_link);
			}
		}

		return $post_link;
	}

	public static function filter_content_save_pre($content){
		if($content && is_serialized($content)){
			$hook_name	= 'content_save_pre';
			$callback	= 'wp_filter_post_kses';
			$priority	= wpjam_get_current_priority($hook_name);
			$var 		= 'content_save_pre_filter_removed';

			if($priority < 10){
				if(has_filter($hook_name, $callback)){
					remove_filter($hook_name, $callback);

					wpjam_set_current_var($var, true);
				}
			}else{
				if(wpjam_get_current_var($var)){
					add_filter($hook_name, $callback);

					wpjam_set_current_var($var, false);
				}
			}
		}

		return $content;
	}

	public static function get_meta($post_id, ...$args){
		// _deprecated_function(__METHOD__, 'WPJAM Basic 6.0', 'wpjam_get_metadata');
		return wpjam_get_metadata('post', $post_id, ...$args);
	}

	public static function update_meta($post_id, ...$args){
		// _deprecated_function(__METHOD__, 'WPJAM Basic 6.0', 'wpjam_update_metadata');
		return wpjam_update_metadata('post', $post_id, ...$args);
	}

	public static function update_metas($post_id, $data, $meta_keys=[]){
		return static::update_meta($post_id, $data, $meta_keys);
	}
}

class WPJAM_Post_Type extends WPJAM_Register{
	private $_fields	= [];

	public function __get($key){
		if($key == 'name' || $key == 'post_type'){
			return $this->name;
		}

		if(property_exists('WP_Post_Type', $key)){
			$object	= get_post_type_object($this->name);

			if($object){
				return $object->$key;
			}
		}

		$value	= parent::__get($key);

		if($key == 'model' && (!$value || !class_exists($value) || !is_subclass_of($value, 'WPJAM_Post'))){
			return 'WPJAM_Post';
		}

		return $value;
	}

	public function __set($key, $value){
		if($key != 'name' && property_exists('WP_Post_Type', $key)){
			$object	= get_post_type_object($this->name);

			if($object){
				$object->$key = $value;
			}
		}

		parent::__set($key, $value);
	}

	public function get_menu_slug(){
		if(is_null($this->menu_slug)){
			$menu_page	= $this->get_arg('menu_page');

			if($menu_page && is_array($menu_page)){
				if(wp_is_numeric_array($menu_page)){
					$this->menu_slug	= current($menu_page)['menu_slug'];
				}else{
					$this->menu_slug	= $menu_page['menu_slug'];
				}
			}
		}

		return $this->menu_slug;
	}

	public function to_array(){
		$this->filter_args();

		if(doing_filter('register_post_type_args')){
			if(!$this->_builtin && $this->permastruct){
				$this->permastruct	= str_replace(
					['%post_id%', '%postname%'],
					['%'.$this->name.'_id%', '%'.$this->name.'%'],
					$this->permastruct
				);

				if(strpos($this->permastruct, '%'.$this->name.'_id%')){
					if($this->hierarchical){
						$this->permastruct	= false;
					}else{
						$this->query_var	= $this->query_var ?? false;
					}
				}

				if($this->permastruct){
					$this->rewrite	= $this->rewrite ?: true;
				}
			}

			if($this->_jam){
				if($this->hierarchical){
					$this->supports		= array_merge($this->supports, ['page-attributes']);
				}

				if($this->rewrite){
					$this->rewrite	= is_array($this->rewrite) ? $this->rewrite : [];
					$this->rewrite	= wp_parse_args($this->rewrite, ['with_front'=>false, 'feeds'=>false]);
				}
			}
		}

		return $this->args;
	}

	public function add_field(...$args){
		$fields	= is_array($args[0]) ? $args[0] : [$args[0]=>$args[1]];

		$this->_fields	= array_merge($this->_fields, $fields);

		return $this;
	}

	public function remove_field($key){
		$this->_fields	= array_except($this->_fields, $key);

		return $this;
	}

	public function get_fields($id=0, $action_key=''){
		$fields	= [];

		if(in_array($action_key, ['add', 'set'])){
			if($action_key == 'add'){
				$fields['post_type']	= ['type'=>'hidden',	'value'=>$this->name];
				$fields['post_status']	= ['type'=>'hidden',	'value'=>'draft'];
			}

			$fields['post_title']	= ['title'=>'标题',	'type'=>'text',	'required'];

			if($this->supports('excerpt')){
				$fields['post_excerpt']	= ['title'=>'摘要',	'type'=>'textarea',	'class'=>'',	'rows'=>3];
			}

			if($this->supports('thumbnail')){
				$fields['_thumbnail_id']	= ['title'=>'头图', 'type'=>'img', 'size'=>'600x0',	'name'=>'meta_input[_thumbnail_id]'];
			}
		}

		if($this->supports('images')){
			$size	= $this->images_sizes ? $this->images_sizes[0] : '';

			$fields['images']	= [
				'title'			=> '头图',
				'name'			=> 'meta_input[images]',
				'type'			=> 'mu-img',
				'item_type'		=> 'url',
				'show_in_rest'	=> false,
				'size'			=> $size,
				'description'	=> $size ? '尺寸：'.$size : '',
				'max_items'		=> $this->images_max_items
			];
		}

		if($this->supports('video')){
			$fields['video']	= ['title'=>'视频',	'type'=>'url',	'name'=>'meta_input[video]'];
		}

		foreach($this->_fields as $key => $field){
			if(in_array($action_key, ['add', 'set']) && empty($field['name']) && !property_exists('WP_Post', $key)){
				$field['name']	= 'meta_input['.$key.']';
			}

			$fields[$key]	= $field;
		}

		return $fields;
	}

	public function register_option($list_table=false){
		if(!wpjam_get_post_option($this->name.'_base')){
			$fields	= $list_table ? [$this, 'get_fields'] : $this->get_fields();

			if($fields){
				wpjam_register_post_option($this->name.'_base', [
					'post_type'		=> $this->name,
					'title'			=> '基础信息',
					'page_title'	=> '设置'.$this->label,
					'fields'		=> $fields,
					'list_table'	=> $this->show_ui,
					'action_name'	=> 'set',
					'row_action'	=> false,
					'order'			=> 99,
				]);
			}
		}
	}

	public function get_support($feature){
		if($this->supports($feature)){
			$supports	= get_all_post_type_supports($this->name);
			$support	= $supports[$feature];

			if(is_array($support) && wp_is_numeric_array($support) && count($support) == 1){
				return current($support);
			}else{
				return $support;
			}
		}

		return false;
	}

	public function supports($feature){
		return post_type_supports($this->name, $feature);
	}

	public function get_sizes($orientation=null){
		$sizes	= [];

		if($this->images_sizes){
			$sizes['large']		= $this->images_sizes[0];
			$sizes['thumbnail']	= $this->images_sizes[1];

			if($orientation == 'landscape'){
				if(!empty($this->images_sizes[2])){
					$sizes['thumbnail']	= $this->images_sizes[2];
				}
			}elseif($orientation == 'portrait'){
				if(!empty($this->images_sizes[3])){
					$sizes['thumbnail']	= $this->images_sizes[3];
				}
			}

			return $sizes;
		}else{
			return [
				'large'		=> $this->get_size('large'),
				'thumbnail'	=> $this->get_size('thumbnail'),
			];
		}
	}

	public function get_size($type='thumbnail', $orientation=null){
		return $this->{$type.'_size'} ?: $type;
	}

	public function get_taxonomies($output='objects'){
		$taxonomies	= get_object_taxonomies($this->name);

		if($output == 'names'){
			return $taxonomies;
		}

		$objects	= [];

		foreach($taxonomies as $taxonomy){
			$tax_object	= wpjam_get_taxonomy_object($taxonomy);

			if($tax_object){
				$objects[$taxonomy]	= $tax_object;
			}
		}

		return $objects;
	}

	public function in_taxonomy($taxonomy){
		return is_object_in_taxonomy($this->name, $taxonomy);
	}

	public function is_viewable(){
		return is_post_type_viewable($this->name);
	}

	public function reset_invalid_parent($value=0){
		$wpdb		= $GLOBALS['wpdb'];
		$post_ids	= $wpdb->get_col($wpdb->prepare("SELECT p1.ID FROM {$wpdb->posts} p1 LEFT JOIN  {$wpdb->posts} p2 ON p1.post_parent = p2.ID WHERE p1.post_type=%s AND p1.post_parent > 0 AND p2.ID is null", $this->name)) ?: [];

		foreach($post_ids as $post_id){
			wp_update_post(['ID'=>$post_id, 'post_parent'=>$value]);	
		}

		return count($post_ids);
	}

	public function filter_labels($labels){
		$_labels	= (array)($this->labels ?? []);
		$labels		= (array)$labels;
		$name		= $labels['name'];
		$search		= $this->hierarchical ? ['撰写新', '写文章', '页面', 'page', 'Page'] : ['撰写新', '写文章', '文章', 'post', 'Post'];
		$replace	= ['新建', '新建'.$name, $name, $name, ucfirst($name)];

		foreach ($labels as $key => &$label) {
			if($label && empty($_labels[$key])){
				if($key == 'all_items'){
					$label	= '所有'.$name;
				}elseif($key == 'archives'){
					$label	= $name.'归档';
				}elseif($label != $name){
					$label	= str_replace($search, $replace, $label);
				}
			}
		}

		return $labels;
	}

	public function registered_callback($post_type, $object){
		if($this->name == $post_type){
			if($this->permastruct){
				if(strpos($this->permastruct, '%'.$post_type.'_id%')){
					add_rewrite_tag('%'.$post_type.'_id%', '([0-9]+)', 'post_type='.$post_type.'&p=');

					remove_rewrite_tag('%'.$post_type.'%');
				}

				add_permastruct($post_type, $this->permastruct, array_merge($object->rewrite, ['feed'=>$this->rewrite['feeds']]));
			}

			$callback	= $this->registered_callback;

			if($callback && is_callable($callback)){
				call_user_func($callback, $post_type, $object);
			}
		}
	}

	public function init(){
		if($this->_jam){
			if(is_admin() && $this->show_ui){
				add_filter('post_type_labels_'.$this->name,	[$this, 'filter_labels']);
			}

			add_action('registered_post_type_'.$this->name,	[$this, 'registered_callback'], 10, 2);

			register_post_type($this->name, $this->to_array());
		}
	}

	public static function filter_register_args($args, $post_type){
		if(did_action('init') || empty($args['_builtin'])){
			$object	= self::get($post_type);

			if($object){
				$object->update_args($args);
			}else{
				$args	= array_merge($args, ['_jam'=>false]);
				$object	= self::register($post_type, $args);
			}

			return $object->to_array();
		}

		return $args;
	}

	protected static function preprocess_args($args, $name){
		$args	= parent::preprocess_args($args, $name);
		$args	= wp_parse_args($args, ['plural'=>$name.'s', '_jam'=>true]);

		if($args['_jam']){
			if(isset($args['taxonomies']) && !$args['taxonomies']){
				unset($args['taxonomies']);
			}

			$args	= wp_parse_args($args, [
				'public'		=> true,
				'show_ui'		=> true,
				'hierarchical'	=> false,
				'rewrite'		=> true,
				'permastruct'	=> false,
				'supports'		=> ['title'],
			]);
		}

		return $args;
	}

	protected static function get_config($key){
		if(in_array($key, ['init', 'menu_page', 'admin_load', 'register_json'])){
			return true;
		}elseif($key == 'registered'){
			return 'init';
		}
	}
}

class WPJAM_Posts{
	public static function parse($query, $args=[]){
		$parsed		= [];

		if(is_string($query) || wp_is_numeric_array($query)){
			$posts	= WPJAM_Post::get_by_ids(wp_parse_id_list($query));
			$filter	= array_pull($args, 'filter');

			foreach($posts as $post){
				$object	= wpjam_post($post);

				if($object){
					$json		= $object->parse_for_json($args);
					$parsed[]	= $filter ? apply_filters($filter, $json, $post_id, $args) : $json;
				}
			}
		}else{
			$args	= array_merge($args, ['list_query'=>true]);
			$query	= self::get_query($query, $args);
			$parsed	= self::parse_query($query, $args); 

			wp_reset_postdata();
		}

		return $parsed;
	}

	protected static function parse_query($query, $args=[], $format=''){
		$parsed	= [];
		$filter	= array_pull($args, 'filter');

		if($format == 'date'){
			$day	= array_pull($args, 'day');
		}

		if($query->have_posts()){
			while($query->have_posts()){
				$query->the_post();

				$post_id	= get_the_ID();
				$json		= wpjam_get_post($post_id, $args);
				$json		= $filter ? apply_filters($filter, $json, $post_id, $args) : $json;

				if($format == 'date'){
					$date	= explode(' ', $json['date'])[0];
					$number	= (int)(explode('-', $date)[2]);

					if($day && $number != $day){
						continue;
					}

					$parsed[$date]		= $parsed[$date] ?? [];
					$parsed[$date][]	= $json;
				}else{
					$parsed[]	= $json;
				}
			}
		}

		return $parsed;
	}

	public static function render($query, $args=[]){
		$output	= '';
		$query	= self::get_query($query, $args);

		if($query){
			$item_callback	= self::parse_callback($args, 'item_callback');
			$wrap_callback	= self::parse_callback($args, 'wrap_callback');
			$title_number	= array_pull($args, 'title_number');
			$total_number	= count($query->posts);

			if($query->have_posts()){
				while($query->have_posts()){
					$query->the_post();

					if($title_number){
						$args['title_number']	= zeroise($query->current_post+1, strlen($total_number));
					}

					$output .= call_user_func($item_callback, get_the_ID(), $args);
				}
			}

			wp_reset_postdata();

			$output	= call_user_func($wrap_callback, $output, $args);
		}

		return $output;
	}

	public static function item_callback($post_id, $args){
		$args	= wp_parse_args($args, [
			'title_number'	=> 0,
			'excerpt'		=> false,
			'thumb'			=> true,
			'size'			=> 'thumbnail',
			'thumb_class'	=> 'wp-post-image',
			'wrap_tag'		=> 'li'
		]);

		$title	= get_the_title($post_id);
		$item	= wpjam_wrap_tag($title);

		if($args['title_number']){
			$item->before('span', ['title-number'], $args['title_number'].'. ');
		}

		if($args['thumb'] || $args['excerpt']){
			$item->wrap('h4');

			if($args['thumb']){
				$item->before(get_the_post_thumbnail($post_id, $args['size'], ['class'=>$args['thumb_class']]));
			}

			if($args['excerpt']){
				$item->after(wpautop(get_the_excerpt($post_id)));
			}
		}

		$item->wrap('a', ['href'=>get_permalink($post_id), 'title'=>strip_tags($title)], $item);

		if($args['wrap_tag']){
			$item->wrap($args['wrap_tag']);
		}

		return $item->render();
	}

	public static function wrap_callback($output, $args){
		if(!$output){
			return '';
		}

		$args	= wp_parse_args($args, [
			'title'		=> '',
			'div_id'	=> '',
			'class'		=> [],
			'thumb'		=> true,
			'wrap_tag'	=> 'ul'
		]);

		$output	= wpjam_wrap_tag($output);

		if($args['wrap_tag']){
			$args['class']	= (array)$args['class'];

			if($args['thumb']){
				$args['class'][]	= 'has-thumb';
			}

			$output->wrap($args['wrap_tag'], $args['class']);
		}

		if($args['title']){
			$output->before('h3', [], $args['title']);
		}

		if($args['div_id']){
			$output->wrap('div', ['id'=>$args['div_id']], $output);
		}

		return $output->render();
	}

	public static function parse_query_vars($query_vars, &$args=[]){
		$tax_query	= $query_vars['tax_query'] ?? [];
		$date_query	= $query_vars['date_query'] ?? [];

		$taxonomies	= array_values(get_taxonomies(['_builtin'=>false]));

		foreach(array_merge($taxonomies, ['category', 'post_tag']) as $taxonomy){
			$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
			$term_id	= array_pull($query_vars, $query_key);

			if($term_id){
				if($taxonomy == 'category' && $term_id != 'none'){
					$query_vars[$query_key]	= $term_id;
				}else{
					$tax_query[]	= self::parse_tax_query($taxonomy, $term_id);
				}
			}
		}

		if(!empty($query_vars['taxonomy']) && empty($query_vars['term'])){
			$term_id	= array_pull($query_vars, 'term_id');

			if($term_id){
				if(is_numeric($term_id)){
					$taxonomy		= array_pull($query_vars, 'taxonomy');
					$tax_query[]	= self::parse_tax_query($taxonomy, $term_id);
				}else{
					$query_vars['term']	= $term_id;
				}
			}
		}

		foreach(['cursor'=>'before', 'since'=>'after'] as $key => $query_key){
			$value	= array_pull($query_vars, $key);

			if($value){
				$date_query[]	= [$query_key => wpjam_date('Y-m-d H:i:s', $value)];
			}
		}

		if($args){
			$post_type	= array_pull($args, 'post_type');
			$orderby	= array_pull($args, 'orderby');
			$number		= array_pull($args, 'number');
			$days		= array_pull($args, 'days');

			if($post_type){
				$query_vars['post_type']	= $post_type;
			}

			if($orderby){
				$query_vars['orderby']	= $orderby;
			}

			if($number){
				$query_vars['posts_per_page']	= $number;
			}

			if($days){
				$after	= wpjam_date('Y-m-d', time() - DAY_IN_SECONDS * $days).' 00:00:00';
				$column	= array_pull($args, 'column') ?: 'post_date_gmt';

				$date_query[]	= ['column'=>$column, 'after'=>$after];
			}
		}

		if($tax_query){
			$query_vars['tax_query']	= $tax_query;
		}

		if($date_query){
			$query_vars['date_query']	= $date_query;
		}

		return $query_vars;
	}

	protected static function parse_tax_query($taxonomy, $term_id){
		if($term_id == 'none'){
			return ['taxonomy'=>$taxonomy,	'field'=>'term_id',	'operator'=>'NOT EXISTS'];
		}else{
			return ['taxonomy'=>$taxonomy,	'field'=>'term_id',	'terms'=>[$term_id]];
		}
	}

	protected static function parse_callback(&$args, $name='item_callback'){
		$callback	= array_pull($args, $name);

		if(!$callback || !is_callable($callback)){
			$callback	= [self::class, $name];
		}

		return $callback;
	}

	protected static function get_query($query, &$args=[]){
		return is_object($query) ? $query : wpjam_query(self::parse_query_vars($query, $args));
	}

	public static function get_related_query($post, $args=[]){
		$post	= get_post($post);

		if($post){
			$post_id	= $post->ID;
			$post_type	= [get_post_type($post)];
			$tt_ids		= [];

			foreach(get_object_taxonomies($post) as $taxonomy){
				$terms	= $taxonomy == 'post_format' ? [] : get_the_terms($post_id, $taxonomy);

				if($terms){
					$post_type	= array_merge($post_type, get_taxonomy($taxonomy)->object_type);
					$tt_ids		= array_merge($tt_ids, array_column($terms, 'term_taxonomy_id'));
				}
			}

			if($tt_ids){
				return self::get_query([
					'related_query'		=> true,
					'post_status'		=> 'publish',
					'post__not_in'		=> [$post_id],
					'post_type'			=> array_unique($post_type),
					'term_taxonomy_ids'	=> array_unique(array_filter($tt_ids)),
				], $args);
			}
		}

		return false;
	}

	public static function get_related_object_ids($tt_ids, $number, $page=1){
		$id_str		= implode(',', array_map('intval', $tt_ids));
		$cache_key	= 'related_object_ids:'.$id_str.':'.$page.':'.$number;
		$object_ids	= wp_cache_get($cache_key, 'terms');

		if($object_ids === false){
			$object_ids	= $GLOBALS['wpdb']->get_col('SELECT object_id, count(object_id) as cnt FROM '.$GLOBALS['wpdb']->term_relationships.' WHERE term_taxonomy_id IN ('.$id_str.') GROUP BY object_id ORDER BY cnt DESC, object_id DESC LIMIT '.(($page-1) * $number).', '.$number);

			wp_cache_set($cache_key, $object_ids, 'terms', DAY_IN_SECONDS);
		}

		return $object_ids;
	}

	public static function parse_json_module($args){
		$action	= array_pull($args, 'action');

		if(!$action){
			return;
		}

		$wp	= $GLOBALS['wp'];

		if(isset($wp->raw_query_vars)){
			$wp->query_vars		= $wp->raw_query_vars;
		}else{
			$wp->raw_query_vars	= $wp->query_vars;
		}

		if($action == 'list'){
			return self::parse_list_json_module($args);
		}elseif($action == 'calendar'){
			return self::parse_calendar_json_module($args);
		}elseif($action == 'get'){
			return self::parse_get_json_module($args);
		}elseif($action == 'upload'){
			return self::parse_media_json_module($args, 'post_type');
		}
	}

	protected static function parse_json_query_vars($query_vars){
		$post_type	= $query_vars['post_type'] ?? '';

		if(is_string($post_type) && strpos($post_type, ',') !== false){
			$query_vars['post_type']	= wp_parse_list($post_type);
		}

		$taxonomies	= $post_type ? get_object_taxonomies($post_type) : get_taxonomies(['public'=>true]);
		$taxonomies	= array_diff($taxonomies, ['post_format']);

		foreach($taxonomies as $taxonomy){	// taxonomy 参数处理，同时支持 $_GET 和 $query_vars 参数
			if($taxonomy == 'category'){
				if(empty($query_vars['cat'])){
					foreach(['category_id', 'cat_id'] as $cat_key){
						$term_id	= (int)wpjam_get_parameter($cat_key);

						if($term_id){
							$query_vars['cat']	= $term_id;
							break;
						}
					}
				}
			}else{
				$query_key	= wpjam_get_taxonomy_query_key($taxonomy);
				$term_id	= (int)wpjam_get_parameter($query_key);

				if($term_id){
					$query_vars[$query_key]	= $term_id;
				}
			}
		}

		$term_id	= (int)wpjam_get_parameter('term_id');
		$taxonomy	= wpjam_get_parameter('taxonomy');

		if($term_id && $taxonomy){
			$query_vars['term_id']	= $term_id;
			$query_vars['taxonomy']	= $taxonomy;
		}

		return self::parse_query_vars($query_vars);
	}

	protected static function parse_json_output($query_vars){
		$post_type	= $query_vars['post_type'] ?? '';

		if($post_type && is_string($post_type)){
			$object	= wpjam_get_post_type_object($post_type);
			$plural	= $object ? $object->plural : '';

			return $plural ?: $post_type.'s';
		}

		return 'posts';
	}

	/* 规则：
	** 1. 分成主的查询和子查询（$query_args['sub']=1）
	** 2. 主查询支持 $_GET 参数 和 $_GET 参数 mapping
	** 3. 子查询（sub）只支持 $query_args 参数
	** 4. 主查询返回 next_cursor 和 total_pages，current_page，子查询（sub）没有
	** 5. $_GET 参数只适用于 post.list
	** 6. term.list 只能用 $_GET 参数 mapping 来传递参数
	*/
	public static function parse_list_json_module($args){
		$output	= array_pull($args, 'output');
		$sub	= array_pull($args, 'sub');

		$is_main_query	= !$sub;	// 子查询不支持 $_GET 参数，置空之前要把原始的查询参数存起来

		if($is_main_query){
			$wp			= $GLOBALS['wp'];
			$wp_query	= $GLOBALS['wp_query'];
			$query_vars	= array_merge($wp->query_vars, $args);

			$number	= (int)wpjam_get_parameter('number',	['fallback'=>'posts_per_page']);
			$offset	= (int)wpjam_get_parameter('offset');

			if($number && $number != -1){
				$query_vars['posts_per_page']	= $number > 100 ? 100 : $number;
			}

			if($offset){
				$query_vars['offset']	= $offset;
			}

			$post__in	= wpjam_get_parameter('post__in');
			$post__in	= $post__in ? wp_parse_id_list($post__in) : [];

			if($post__in){
				$query_vars['post__in']	= $post__in;

				if(!isset($query_vars['orderby'])){
					$query_vars['orderby']	= 'post__in';
				}

				if(!isset($query_vars['posts_per_page'])){
					$query_vars['posts_per_page']	= -1;	
				}
			}

			$orderby	= $query_vars['orderby'] ?? 'date';
			$use_cursor	= empty($query_vars['paged']) && empty($query_vars['s']) && !is_array($orderby) && in_array($orderby, ['date', 'post_date']);

			if($use_cursor){
				foreach(['cursor', 'since'] as $key){
					$query_vars[$key]	= (int)wpjam_get_parameter($key);

					if($query_vars[$key]){
						$query_vars['ignore_sticky_posts']	= true;
					}
				}
			}

			$query_vars	= $wp->query_vars = self::parse_json_query_vars($query_vars);

			$wp->query_posts();
		}else{
			$query_vars	= self::parse_query_vars($args);
			$wp_query	= new WP_Query($query_vars);
		}

		$posts_json	= [];
		$parsed		= self::parse_query($wp_query, $args);

		if($is_main_query){
			if(is_category() || is_tag() || is_tax()){
				if($current_term = get_queried_object()){
					$taxonomy		= $current_term->taxonomy;
					$current_term	= wpjam_get_term($current_term, $taxonomy);

					$posts_json['current_taxonomy']		= $taxonomy;
					$posts_json['current_'.$taxonomy]	= $current_term;
				}else{
					$posts_json['current_taxonomy']		= null;
				}
			}elseif(is_author()){
				if($author = $wp_query->get('author')){
					$posts_json['current_author']	= wpjam_get_user($author);
				}else{
					$posts_json['current_author']	= null;
				}
			}

			$nopaging	= $wp_query->get('nopaging');
			$total		= $wp_query->found_posts;

			$posts_json['total']	= $total;

			if($nopaging){
				$posts_json['total_pages']	= $total ? 1 : 0;
				$posts_json['current_page']	= 1;
			}else{
				$posts_json['total_pages']	= $wp_query->max_num_pages;
				$posts_json['current_page']	= $wp_query->get('paged') ?: 1;
			}

			if($use_cursor){
				$posts_json['next_cursor']	= ($parsed && $wp_query->max_num_pages > 1) ? end($parsed)['timestamp'] : 0;
			}
		}

		$output	= $output ?: self::parse_json_output($query_vars);

		$posts_json[$output]	= $parsed;

		return apply_filters('wpjam_posts_json', $posts_json, $wp_query, $output);
	}

	public static function parse_calendar_json_module($args){
		$wp			= $GLOBALS['wp'];
		$wp_query	= $GLOBALS['wp_query'];
		$output		= array_pull($args, 'output');
		$query_vars	= array_merge($wp->query_vars, $args);

		$query_vars['year']		= (int)wpjam_get_parameter('year') ?: wpjam_date('Y');
		$query_vars['monthnum']	= (int)wpjam_get_parameter('month') ?: wpjam_date('m');
		$args['day']			= (int)wpjam_get_parameter('day');

		$wp->query_vars	= $query_vars = self::parse_json_query_vars(array_except($query_vars, 'day'));

		$wp->query_posts();

		$parsed	= self::parse_query($wp_query, $args, 'date');
		$output	= $output ?: self::parse_json_output($query_vars);

		return [$output=>$parsed];
	}

	public static function parse_get_json_module($args){
		$wp			= $GLOBALS['wp'];
		$wp_query	= $GLOBALS['wp_query'];
		$post_type	= array_get($args, 'post_type') ?: wpjam_get_parameter('post_type');

		if(!$post_type || $post_type == 'any'){
			$post_id	= array_get($args, 'id') ?: (int)wpjam_get_parameter('id', ['required'=>true]);
			$post_type	= get_post_type($post_id);

			if(!$post_type){
				wp_die('无效的参数：id', 'invalid_parameter');
			}
		}else{
			if(!post_type_exists($post_type)){
				wp_die('invalid_post_type');
			}

			$post_id	= array_get($args, 'id') ?: (int)wpjam_get_parameter('id');

			if($post_id && get_post_type($post_id) != $post_type){
				wp_die('无效的参数：id', 'invalid_parameter');
			}
		}

		$post_status	= $args['post_status'] ?? '';

		if($post_status){
			$wp->set_query_var('post_status', $post_status);
		}

		$wp->set_query_var('post_type', $post_type);
		$wp->set_query_var('cache_results', true);

		if($post_id){
			$wp->set_query_var('p', $post_id);
			$wp->query_posts();
		}else{
			$orderby	= $args['orderby'] ?? '';

			if($orderby == 'rand'){
				$wp->set_query_var('orderby', 'rand');
				$wp->set_query_var('posts_per_page', 1);
				$wp->query_posts();
			}else{
				$hierarchical	= is_post_type_hierarchical($post_type);
				$name_key		= $hierarchical ? 'pagename' : 'name';

				$wp->set_query_var($name_key,	wpjam_get_parameter($name_key,	['required'=>true]));

				$wp->query_posts();

				if(!$post_status && !$wp_query->have_posts()){
					$post_id	= apply_filters('old_slug_redirect_post_id', null);

					if(!$post_id){
						wp_die('无效的文章 ID', 'invalid_post');
					}

					$wp->set_query_var('post_type', 'any');
					$wp->set_query_var('p', $post_id);
					$wp->set_query_var('name', '');
					$wp->set_query_var('pagename', '');
					$wp->query_posts();
				}
			}
		}

		if(!$wp_query->have_posts()){
			wp_die('参数错误', 'invalid_parameter');
		}

		$parsed		= current(self::parse_query($wp_query, $args));
		$output		= array_get($args, 'output') ?: $parsed['post_type'];
		$response	= array_pulls($parsed, ['share_title', 'share_image', 'share_data']);

		if(is_single($parsed['id']) || is_page($parsed['id'])){
			wpjam_update_post_views($parsed['id']);
		}

		return array_merge($response, [$output => $parsed]);
	}

	public static function parse_media_json_module($args, $type=''){
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media	= array_get($args, 'media') ?: 'media';
		$output	= array_get($args, 'output') ?: 'url';

		if(!isset($_FILES[$media])){
			wp_die('无效的参数：media', 'invalid_parameter');
		}

		if($type == 'post_type'){
			$pid	= (int)wpjam_get_post_parameter('post_id',	['default'=>0]);
			$id		= wpjam_try('media_handle_upload', $media, $pid);
			$url	= wp_get_attachment_url($id);
			$query	= wpjam_get_image_size($id);
		}else{
			$upload	= wpjam_try('wpjam_upload', $media);
			$url	= $upload['url'];
			$query	= wpjam_get_image_size($upload['file'], 'file');
		}

		if($query){
			$url	= add_query_arg($query, $url);
		}

		return $output ? [$output => $url] : $url;
	}

	public static function json_modules_callback($type='list', $args=[]){
		$modules	= [];

		if(strpos($type, '.')){
			$parts	= explode('.', $type);
			$type	= end($parts);
		}

		if($type == 'list'){
			$post_type	= wpjam_get_parameter('post_type');
			$args		= wp_parse_args($args, ['post_type'=>$post_type, 'action'=>$type, 'output'=>'posts']);
			$modules[]	= ['type'=>'post_type',	'args'=>array_filter($args)];

			if($post_type && is_string($post_type)){
				foreach(get_object_taxonomies($post_type, 'objects') as $taxonomy => $tax_object){
					if($tax_object->hierarchical && $tax_object->public){
						$modules[]	= ['type'=>'taxonomy',	'args'=>['taxonomy'=>$taxonomy, 'hide_empty'=>0]];
					}
				}
			}
		}elseif($type == 'calendar'){
			$args		= wp_parse_args($args, ['action'=>$type, 'output'=>'posts']);
			$modules[]	= ['type'=>'post_type', 'args'=>$args];
		}elseif($type == 'get'){
			$args		= wp_parse_args($args, ['action'=>$type, 'output'=>'post']);
			$modules[]	= ['type'=>'post_type', 'args'=>$args];
		}

		return $modules;
	}

	public static function filter_clauses($clauses, $wp_query){
		global $wpdb;

		if($wp_query->get('related_query')){
			$tt_ids	= $wp_query->get('term_taxonomy_ids');

			if($tt_ids){
				$clauses['join']	.= "INNER JOIN {$wpdb->term_relationships} AS tr ON {$wpdb->posts}.ID = tr.object_id";
				$clauses['where']	.= " AND tr.term_taxonomy_id IN (".implode(",",$tt_ids).")";
				$clauses['groupby']	.= " tr.object_id";
				$clauses['orderby']	= " count(tr.object_id) DESC, {$wpdb->posts}.ID DESC";
			}
		}else{
			$orderby	= $wp_query->get('orderby');
			$order		= $wp_query->get('order') ?: 'DESC';

			if($orderby == 'comment_date'){
				$comment_type	= $wp_query->get('comment_type') ?: 'comment';
				$type_str		= $comment_type	== 'comment' ? "'comment', ''" : "'".esc_sql($comment_type)."'";
				$ct_where		= "ct.comment_type IN ({$type_str}) AND ct.comment_parent=0 AND ct.comment_approved NOT IN ('spam', 'trash', 'post-trashed')";

				$clauses['join']	= "INNER JOIN {$wpdb->comments} AS ct ON {$wpdb->posts}.ID = ct.comment_post_ID AND {$ct_where}";
				$clauses['groupby']	= "ct.comment_post_ID";
				$clauses['orderby']	= "MAX(ct.comment_ID) {$order}";
			}elseif($orderby == 'views' || $orderby == 'comment_type'){
				$meta_key			= $orderby == 'comment_type' ? $wp_query->get('comment_count') : 'views';
				$clauses['join']	.= "LEFT JOIN {$wpdb->postmeta} jam_pm ON {$wpdb->posts}.ID = jam_pm.post_id AND jam_pm.meta_key = '{$meta_key}' ";
				$clauses['orderby']	= "(COALESCE(jam_pm.meta_value, 0)+0) {$order}, " . $clauses['orderby'];
			}elseif(in_array($orderby, ['', 'date', 'post_date'])){
				$clauses['orderby']	.= ", {$wpdb->posts}.ID {$order}";
			}
		}

		return $clauses;
	}

	public static function on_parse_request($wp){
		$wp->query_vars	= self::parse_query_vars($wp->query_vars);
	}
}