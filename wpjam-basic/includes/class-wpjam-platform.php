<?php
class WPJAM_Platform extends WPJAM_Register{
	public function verify(){
		return call_user_func($this->verify);
	}

	public function get_tabbar($page_key=''){
		if($page_key){
			$tabbar	= $this->get_item_arg($page_key, 'tabbar');

			if($tabbar){
				$default	= ['text'=>$this->get_item_arg($page_key, 'title')];

				if(is_array($tabbar)){
					$tabbar	= wp_parse_args($tabbar, $default);
				}else{
					$tabbar	= $tabbar === true ? $default : ['text'=>$tabbar];
				}
			}

			return $tabbar;
		}else{
			$parsed	= [];

			foreach($this->get_items() as $page_key => $item){
				$tabbar	= $this->get_tabbar($page_key);

				if($tabbar){
					$parsed[$page_key]	= $tabbar;
				}
			}

			return $parsed;
		}
	}

	public function get_page($page_key=''){
		if($page_key){
			$path	= $this->get_item_arg($page_key, 'path');

			if($path){
				return current(explode('?', $path));
			}
		}else{
			$parsed	= [];

			foreach($this->get_items() as $page_key => $item){
				$page	= $this->get_page($page_key);

				if($page){
					$parsed[]	= [
						'page_key'	=> $page_key,
						'page'		=> $page,
					];
				}
			}

			return $parsed;
		}
	}

	public function get_date_type($page_key){
		$page_type	= $this->get_item_arg($page_key, 'page_type');

		return $page_type ? wpjam_get_data_type_object($page_type) : null;
	}

	public function get_fields($page_key){
		$item	= $this->get_item($page_key);
		$fields	= $this->get_item_arg($page_key, 'fields') ?: [];

		if($fields){
			if(is_callable($fields)){
				$fields	= call_user_func($fields, $item, $page_key);
			}
		}else{
			$date_type	= $this->get_date_type($page_key);

			if($date_type){
				$fields	= $date_type->get_fields($item);
			}
		}

		return $fields ?: [];
	}

	public function get_path($page_key, $args=[], $postfix='', $title=''){
		if($postfix && is_array($args)){
			$_args	= [];

			foreach($this->get_fields($page_key) as $sub_key => $sub_field){
				$_args[$sub_key]	= $args[$sub_key.$postfix] ?? '';
			}

			$args	= $_args;
		}

		$item	= $this->get_item($page_key);

		if($item){
			$callback	= array_pull($item, 'callback');

			if(is_array($args)){
				$args	= array_filter($args, 'is_exists');
				$args	= wp_parse_args($args, $item);
			}

			if($callback){
				if(is_callable($callback) && is_array($args)){
					return call_user_func($callback, $args, $page_key) ?: '';
				}
			}else{
				$date_type	= $this->get_date_type($page_key);

				if($date_type){
					$cb_args	= is_array($args) ? [$args] : [$args, $item];

					return call_user_func_array([$date_type, 'get_path'], $cb_args);
				}
			}

			if(isset($item['path'])){
				return (string)$item['path'];
			}
		}

		return new WP_Error('invalid_page_key', [$title]);
	}

	public function get_paths($page_key, $query_args=[]){
		$paths	= [];
		$object	= $this->get_date_type($page_key);

		if($object){
			$args		= $this->get_item($page_key);
			$data_type	= $object->name;

			if(!empty($args[$data_type])){
				$query_args[$data_type]	= $args[$data_type];
			}

			$items	= $object->query_items($query_args, false) ?: [];

			foreach($items as $item){
				$path	= $this->get_path($page_key, $item['value']);

				if($path && !is_wp_error($path)){
					$paths[]	= $path;
				}
			}
		}

		return $paths;
	}

	public function has_path($page_key, $strict=false){
		$has	= false;
		$item	= $this->get_item($page_key);

		if($item){
			$has	= isset($item['path']) || isset($item['callback']);

			if($strict && $has && isset($item['path']) && $item['path'] === false){
				$has	= false;
			}
		}

		return $has;
	}

	public function parse_path($item, $args=[]){
		$args	= wp_parse_args($args, [
			'postfix'	=> '',
			'title'		=> '',
			'default'	=> ''
		]);

		$page_key	= array_pull($item, 'page_key'.$args['postfix']) ?: $args['default'];
		$parsed		= [];

		if($page_key == 'none'){
			if(!empty($item['video'])){
				$parsed['type']		= 'video';
				$parsed['video']	= $item['video'];
				$parsed['vid']		= wpjam_get_qqv_id($item['video']);
			}else{
				$parsed['type']		= 'none';
			}
		}elseif($page_key == 'external'){
			if(in_array($this->name, ['web', 'template'])){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['url'];
			}
		}elseif($page_key == 'web_view'){
			if(in_array($this->name, ['web', 'template'])){
				$parsed['type']		= 'external';
				$parsed['url']		= $item['src'];
			}else{
				$parsed['type']		= 'web_view';
				$parsed['src']		= $item['src'];
			}
		}

		if(!$parsed && $page_key){			
			$path	= $this->get_path($page_key, $item, $args['postfix'], $args['title']);

			if(isset($path) && !is_wp_error($path)){
				if(is_array($path)){
					$parsed	= $path;
				}else{
					$parsed['type']		= '';
					$parsed['page_key']	= $page_key;
					$parsed['path']		= $path;
				}
			}
		}

		return $parsed;
	}

	public function validate_path($item, $args=[]){
		$args	= wp_parse_args($args, [
			'postfix'	=> '',
			'title'		=> '',
			'default'	=> ''
		]);

		$page_key	= $item['page_key'.$args['postfix']] ?: $args['default'];

		if($page_key == 'none'){
			return true;
		}elseif($page_key == 'web_view'){
			if(in_array($this->name, ['web', 'template'])){
				return true;
			}
		}

		$result	= $this->get_path($page_key, $item, $args['postfix'], $args['title']);

		return is_wp_error($result) ? $result : true;
	}

	public function register_path(){
		if($this->name == 'template'){
			wpjam_register_path('home',		'template',	['title'=>'首页',		'path'=>home_url(),	'group'=>'tabbar']);
			wpjam_register_path('category',	'template',	['title'=>'分类页',		'path'=>'',	'page_type'=>'taxonomy']);
			wpjam_register_path('post_tag',	'template',	['title'=>'标签页',		'path'=>'',	'page_type'=>'taxonomy']);
			wpjam_register_path('author',	'template',	['title'=>'作者页',		'path'=>'',	'page_type'=>'author']);
			wpjam_register_path('post',		'template',	['title'=>'文章详情页',	'path'=>'',	'page_type'=>'post_type']);
			wpjam_register_path('external', 'template',	['title'=>'外部链接',		'path'=>'',	'fields'=>[
				'url'	=> ['title'=>'',	'type'=>'url',	'required'=>true,	'placeholder'=>'请输入外部链接地址，仅适用网页版。']
			]]);
		}
	}

	public static function get_options($output=''){
		$objects	= [];

		foreach(self::get_registereds() as $key => $object){
			if($object->bit){
				$object->key	= $key;

				$objects[$object->bit]	= $object;
			}
		}

		if($output == 'bit'){
			return wp_list_pluck($objects, 'title');
		}else{
			return wp_list_pluck($objects, 'title', 'name');
		}
	}

	public static function get_by(...$args){
		if($args){
			$args	= is_array($args[0]) ? $args[0] : [$args[0] => $args[1]];
			$path	= array_pull($args, 'path');

			if($path){
				$args['has_path']	= function(){ return $this->get_items(); };
			}
		}

		return self::get_registereds($args);
	}

	public static function get_current($args=[], $output='bit'){
		$names	= wp_is_numeric_array($args) ? $args : array_keys(self::get_by($args));

		foreach(self::get_registereds() as $name => $object){
			if($object->verify()){
				$value	= $output == 'bit' ? $object->bit : $name;

				if(($names && in_array($value, $names)) || empty($names)){
					return $output == 'object' ? $object : $value;
				}
			}
		}

		return '';
	}

	protected static function get_config($key){
		if($key == 'defaults'){
			return [
				'weapp'		=> ['bit'=>1,	'order'=>4,		'title'=>'小程序',	'verify'=>'is_weapp'],
				'weixin'	=> ['bit'=>2,	'order'=>4,		'title'=>'微信网页',	'verify'=>'is_weixin'],
				'mobile'	=> ['bit'=>4,	'order'=>8,		'title'=>'移动网页',	'verify'=>'wp_is_mobile'],
				'web'		=> ['bit'=>8,	'order'=>10,	'title'=>'网页',		'verify'=>'__return_true'],
				'template'	=> ['bit'=>8,	'order'=>10,	'title'=>'网页',		'verify'=>'__return_true']
			];
		}elseif($key == 'orderby'){
			return true;
		}elseif($key == 'order'){
			return 'ASC';
		}elseif($key == 'registered'){
			return 'register_path';
		}
	}
}

class WPJAM_Path extends WPJAM_Register{
	public function add_platform($platform, $args){
		$args['path_type']	= $platform;

		parent::add_item($platform, $args);

		$this->args	= $this->args+$args;
	}

	public function has($platforms, $operator='AND', $strict=false){
		foreach((array)$platforms as $platform){
			$has	= wpjam_has_path($platform, $this->name, $strict);

			if($operator == 'AND'){
				if(!$has){
					return false;
				}
			}elseif($operator == 'OR'){
				if($has){
					return true;
				}
			}
		}

		if($operator == 'AND'){
			return true;
		}elseif($operator == 'OR'){
			return false;
		}
	}

	public function get_path($platform, $args=[]){	// 兼容
		return wpjam_get_path($platform, $this->name, $args);
	}

	public function get_tabbar($platform){	// 兼容
		return wpjam_get_tabbar($platform, $this->name);
	}

	public static function get_fields($platforms=null, $args=[]){
		$platforms	= $platforms ?? array_keys(WPJAM_Platform::get_by(['path'=>true]));

		if(empty($platforms)){
			return [];
		}

		$pf_objects = [];

		foreach((array)$platforms as $platform){
			$pf_objects[$platform]	= WPJAM_Platform::get($platform);
		}

		$platforms	= array_keys($pf_objects);

		if(is_array($args)){
			$for	= array_pull($args, 'for');
			$strict	= false;
		}else{
			$for	= $args;
			$strict	= ($for == 'qrcode');
			$args	= [];
		}

		$options	= array_merge(
			['tabbar'=>['title'=>'菜单栏/常用', 'options'=>[]]],
			wpjam_get_items('path_group'),
			['others'=>['title'=>'其他页面', 'options'=>[]]]
		);

		$fields		= ['page_key'=>['options'=>&$options]];

		$backup_required	= count($platforms) > 1 && !$strict;

		if($backup_required){
			$backup_options	= $options;
			$backup_fields	= ['page_key_backup'=>['options'=>&$backup_options,	'description'=>'页面不生效时将启用备用页面']];
			$show_if_keys	= [];
		}

		foreach(self::get_registereds($args) as $page_key => $object){
			if(!$object->has($platforms, 'OR', $strict)){
				continue;
			}

			if($object->group){
				$group	= $object->group;
			}else{
				$group	= $object->tabbar ? 'tabbar' : 'others';
			}

			$options[$group]['options'][$object->name]	= $object->title;

			$sub_fields	= [];

			foreach($pf_objects as $platform => $pf_object){
				$sub_fields	= array_merge($sub_fields, $pf_object->get_fields($page_key));
			}

			foreach($sub_fields as $sub_key => $sub_field){
				if(isset($sub_field['show_if'])){
					$fields[$sub_key]	= $sub_field;
				}else{
					if(isset($fields[$sub_key])){
						$fields[$sub_key]['show_if']['value'][]	= $page_key;
					}else{
						$fields[$sub_key]	= array_merge($sub_field, [
							'title'		=> '',
							'show_if'	=> ['key'=>'page_key','compare'=>'IN','value'=>[$page_key]]
						]);
					}
				}
			}

			if($backup_required){
				if($object->has($platforms, 'AND')){
					if(($page_key != 'module_page' && empty($sub_fields)) || ($page_key == 'module_page' && $sub_fields)){
						$backup_options[$group]['options'][$object->name]	= $object->title;
					}

					if($page_key == 'module_page' && $sub_fields){
						foreach($sub_fields as $sub_key => $sub_field){
							$sub_field['show_if']	= ['key'=>'page_key_backup','value'=>$page_key];
							$backup_fields[$sub_key.'_backup']	= $sub_field;
						}
					}
				}else{
					if($page_key == 'web_view'){
						if(!$object->has(array_diff($platforms, ['web','template']), 'AND')){
							$show_if_keys[]	= $page_key;
						}
					}else{
						$show_if_keys[]	= $page_key;
					}
				}
			}
		}

		// 只有一个分组，则不分组显示
		if(count($fields['page_key']['options']) == 1){
			$options	= current($options)['options'];

			if($backup_required){
				$backup_options	= current($backup_options)['options'];
			}
		}

		$fields	= ['page_key_set'=>['title'=>'页面',	'type'=>'fieldset',	'fields'=>$fields]];

		if($for == 'qrcode'){
			return $fields;
		}

		$options['tabbar']['options']['none']	= '只展示不跳转';

		if($backup_required){
			$backup_options['tabbar']['options']['none']	= '只展示不跳转';

			$fields['page_key_backup_set']	= [
				'title'		=> '备用',
				'type'		=> 'fieldset',
				'fields'	=> $backup_fields,
				'show_if'	=> ['key'=>'page_key','compare'=>'IN','value'=>$show_if_keys]
			];
		}

		return $fields;
	}

	public static function get_link_tag($parsed, $text){
		if($parsed['type'] == 'none'){
			return $text;
		}elseif($parsed['type'] == 'external'){
			return '<a href_type="web_view" href="'.$parsed['url'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'web_view'){
			return '<a href_type="web_view" href="'.$parsed['src'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'mini_program'){
			return '<a href_type="mini_program" href="'.$parsed['path'].'" appid="'.$parsed['appid'].'">'.$text.'</a>';
		}elseif($parsed['type'] == 'contact'){
			return '<a href_type="contact" href="" tips="'.$parsed['tips'].'">'.$text.'</a>';
		}elseif($parsed['type'] == ''){
			return '<a href_type="path" page_key="'.$parsed['page_key'].'" href="'.$parsed['path'].'">'.$text.'</a>';
		}
	}

	public static function get_by(...$args){
		if($args){
			$args		= is_array($args[0]) ? $args[0] : [$args[0] => $args[1]];
			$platform	= self::parse_platform($args);

			if($platform){
				$args[$platform]	= function($platform){ return $this->has($platform); };
			}
		}

		return self::get_registereds($args);
	}

	protected static function parse_platform(&$args){
		$platform	= array_pulls($args, ['platform', 'path_type']);

		return $platform ? current($platform) : null;
	}

	public static function create($page_key, ...$args){
		$object	= self::get($page_key);
		$object	= $object ?: self::register($page_key, []);
		$args	= count($args) == 2 ? $args[1]+['platform'=>$args[0]] : $args[0];
		$args	= wp_is_numeric_array($args) ? $args : [$args];

		foreach($args as $_args){
			foreach((array)self::parse_platform($_args) as $pf){
				$pf_object	= WPJAM_Platform::get($pf);

				if(!$pf_object){
					continue;
				}

				$page_type	= $_args['page_type'] ?? '';

				if($page_type && in_array($page_type, ['post_type', 'taxonomy']) && empty($_args[$page_type])){
					$_args[$page_type]	= $page_key;
				}

				if(isset($_args['group']) && is_array($_args['group'])){
					$group	= array_pull($_args, 'group');

					if(isset($group['key'], $group['title'])){
						wpjam_add_item('path_group', $group['key'], ['title'=>$group['title'], 'options'=>[]]);

						$_args['group']	= $group['key'];
					}
				}

				$_args['platform']	= $pf;

				$object->add_platform($pf, $_args);

				$pf_object->add_item($page_key, $_args);
			}
		}

		return $object;
	}

	public static function remove($page_key, $platform=''){
		if($platform){
			$object		= self::get($page_key);
			$pf_object	= WPJAM_Platform::get($platform);

			if($object){
				$object->delete_item($platform);
			}

			if($pf_object){
				$pf_object->delete_item($page_key);
			}
		}else{
			self::unregister($page_key);

			foreach(WPJAM_Platform::get_registereds() as $pf_object){
				$pf_object->delete_item($page_key);
			}
		}
	}
}

class WPJAM_Data_Type extends WPJAM_Register{
	public function __call($method, $args){
		if($this->parse_method($method)){
			return $this->call_method($method, ...$args);
		}

		if(in_array($method, ['parse_value', 'validate_value', 'render_value', 'parse_item', 'query_label', 'filter_query_args'])){
			return $args[0];
		}elseif(in_array($method, ['get_field', 'get_fields'])){
			return [];
		}

		return null;
	}

	public function get_meta_type($args){
		return $this->meta_type ?: $this->call_method('get_meta_type', $args);
	}

	public function prepare_value($value, $parse, $args=[]){
		return $parse ? $this->parse_value($value, $args) : $value;
	}

	public function query_items($args, $wp_error=true){
		if(!$this->parse_method('query_items')){
			return $wp_error ? new WP_Error('undefined_method', ['query_items', '回调函数']) : [];
		}

		$args	= array_filter($args, 'is_exists');
		$items	= $this->call_method('query_items', $args) ?: [];

		if(is_wp_error($items)){
			return $wp_error ? $items : [];
		}

		foreach($items as &$item){
			$item	= $this->parse_item($item, $args);
		}

		return array_values($items);
	}

	public function parse_query_args($args){
		$query_args	= $args['query_args'] ?? [];
		$query_args	= $query_args ? wp_parse_args($query_args) : [];

		if(!empty($args[$this->name])){
			$query_args[$this->name]	= $args[$this->name];
		}

		return $this->filter_query_args($query_args, $args);
	}

	public static function strip($args){
		$data_type	= array_pull($args, 'data_type');

		if($data_type){
			$args	= array_except($args, $data_type);
		}

		return $args;
	}

	public static function slice(&$args, $strip=false){
		$slice		= [];
		$data_type	= array_get($args, 'data_type');

		if($data_type){
			$slice	= [
				'data_type'	=> $data_type,
				$data_type 	=> array_get($args, $data_type) ?: ''
			];
		}

		if($strip){
			$args	= self::strip($args);
		}

		return $slice;
	}

	public static function parse_json_module($args){
		$data_type	= array_pull($args, 'data_type');
		$object		= self::get($data_type);

		if(!$object){
			return new WP_Error('invalid_data_type');
		}

		$query_args	= array_get($args, 'query_args', $args);
		$query_args	= $query_args ? wp_parse_args($query_args) : [];
		$query_args	= array_merge($query_args, ['search'=>wpjam_get_parameter('s')]);

		return ['items'=>$object->query_items($query_args, false)];
	}

	public static function get_config($key){
		if($key == 'defaults'){
			return [
				'post_type'	=> ['model'=>'WPJAM_Post_Type_Data_Type',	'meta_type'=>'post'],
				'taxonomy'	=> ['model'=>'WPJAM_Taxonomy_Data_Type',	'meta_type'=>'term'],
				'author'	=> ['model'=>'WPJAM_Author_Data_Type',		'meta_type'=>'user'],
				'model'		=> ['model'=>'WPJAM_Model_Data_Type'],
				'video'		=> ['model'=>'WPJAM_Video_Data_Type'],
			];
		}	
	}
}

class WPJAM_Post_Type_Data_Type{
	public static function filter_query_args($query_args, $args){
		if(!empty($args['size'])){
			$query_args['thumbnal_size']	= $args['size'];
		}

		return $query_args;
	}

	public static function query_items($args){
		if(!isset($args['s']) && isset($args['search'])){
			$args['s']	= $args['search'];
		}

		return get_posts(wp_parse_args($args, [
			'posts_per_page'	=> $args['number'] ?? 10,
			'suppress_filters'	=> false,
		])) ?: [];
	}

	public static function parse_item($post){
		return ['label'=>$post->post_title, 'value'=>$post->ID];
	}

	public static function query_label($post_id){
		if($post_id && is_numeric($post_id)){
			return get_the_title($post_id) ?: (int)$post_id;
		}

		return '';
	}

	public static function validate_value($value, $args){
		if(!$value){
			return null;
		}

		$current 	= is_numeric($value) ? get_post_type($value) : null;

		if($current){
			$post_type	= array_get($args, 'post_type') ?: $current;

			if(in_array($current, (array)$post_type, true)){
				return (int)$value;
			}
		}

		return new WP_Error('invalid_post_id', [$args['title']]);
	}

	public static function parse_value($value, $args=[]){
		return wpjam_get_post($value, $args);
	}

	public static function update_caches($ids){
		return WPJAM_Post::update_caches($ids);
	}

	public static function get_path(...$args){
		if(is_array($args[0])){
			$post_id	= null;
			$args		= $args[0];
		}else{
			$post_id	= (int)$args[0];
			$args		= $args[1];
		}

		$post_type	= $args['post_type'];
		$post_id	= $post_id ?? (int)($args[$post_type.'_id'] ?? 0);

		if(!$post_id){
			return new WP_Error('invalid_post_id', [get_post_type_object($post_type)->label]);
		}

		if($args['platform'] == 'template'){
			return get_permalink($post_id);
		}

		return str_replace('%post_id%', $post_id, $args['path']);
	}

	public static function get_field($args){
		$title		= array_pull($args, 'title');
		$post_type	= array_pull($args, 'post_type');

		if(is_null($title)){
			$object	= ($post_type && is_string($post_type)) ? get_post_type_object($post_type) : null;
			$title	= $object ? $object->labels->singular_name : '';
		}

		return wp_parse_args($args, [
			'title'			=> $title,
			'type'			=> 'text',
			'class'			=> 'all-options',
			'data_type'		=> 'post_type',
			'post_type'		=> $post_type,
			'placeholder'	=> '请输入'.$title.'ID或者输入关键字筛选',
			'show_in_rest'	=> ['type'=>'integer']
		]);
	}

	public static function get_fields($args){
		$post_type	= $args['post_type'];

		if(get_post_type_object($post_type)){
			return [$post_type.'_id' => self::get_field(['post_type'=>$post_type, 'required'=>true])];
		}

		return [];
	}
}

class WPJAM_Taxonomy_Data_Type{
	public static function filter_query_args($query_args, $args){
		if($args['creatable']){
			$query_args['creatable']	= $args['creatable'];
		}

		unset($args['creatable']);

		return $query_args;
	}

	public static function query_items($args){
		return get_terms(wp_parse_args($args, [
			'number'		=> (isset($args['parent']) ? 0 : 10),
			'hide_empty'	=> 0
		])) ?: [];
	}

	public static function parse_item($term){
		if(is_object($term)){
			return ['label'=>$term->name, 'value'=>$term->term_id];
		}else{
			return ['label'=>$term['name'], 'value'=>$term['id']];
		}
	}

	public static function query_label($term_id, $args){
		if($term_id && is_numeric($term_id)){
			return get_term_field('name', $term_id, $args['taxonomy']) ?: (int)$term_id;
		}

		return '';
	}

	protected static function parse_tax_object($args){
		$taxonomy	= $args['taxonomy'];

		return wpjam_get_taxonomy_object($taxonomy);
	}

	public static function validate_value($value, $args){
		if(!$value){
			return null;
		}

		$taxonomy	= $args['taxonomy'];
		$tax_object	= self::parse_tax_object($args);

		if(is_numeric($value)){
			if(get_term($value, $taxonomy)){
				return (int)$value; 
			}
		}elseif(is_array($value)){
			$levels	= $tax_object ? $tax_object->levels : 0;
			$prev	= 0;

			for($level=0; $level < $levels; $level++){
				$_value	= $value['level_'.$level] ?? 0;

				if(!$_value){
					return $prev;
				}

				$prev	= $_value;
			}

			return $prev;
		}else{
			$result	= term_exists($value, $taxonomy);

			if($result){
				return is_array($result) ? $result['term_id'] : $result;
			}elseif(!empty($args['creatable'])){
				return WPJAM_Term::insert(['name'=>$value, 'taxonomy'=>$taxonomy]);
			}
		}

		return new WP_Error('invalid_term_id', [$args['title']]);
	}

	public static function parse_value($value, $args=[]){
		return wpjam_get_term($value, $args);
	}

	public static function render_value($value, $args){
		$taxonomy	= $args['taxonomy'];
		$tax_object	= self::parse_tax_object($args);
		$levels		= $tax_object ? $tax_object->levels : 0;

		if($levels && $value){
			$ancestors	= get_ancestors($value, $taxonomy, 'taxonomy');
			$term_ids	= array_merge([$value], $ancestors);
			$term_ids	= array_reverse($term_ids);
			$terms		= wpjam_get_terms(['taxonomy'=>$taxonomy, 'hide_empty'=>0]);

			$value		= [];

			for($level=0; $level < $levels; $level++){
				$term_id	= $term_ids[$level] ?? 0;

				if($level == 0){
					$value['level_'.$level]	=  ['value'=>$term_id, 'parent'=>0];
				}else{
					if(!$parent){
						break;
					}

					foreach($terms as $term){
						if($term['id'] == $parent){
							$terms	= $term['children'];
						}
					}

					$value['level_'.$level]	=  [
						'value'		=> $term_id,
						'parent'	=> $parent,
						'items'		=> array_map([self::class, 'parse_item'], $terms)
					];
				}

				$parent	= $term_id;
			}
		}

		return $value;
	}

	public static function update_caches($ids){
		return WPJAM_Term::update_caches($ids);
	}

	public static function get_path(...$args){
		if(is_array($args[0])){
			$args	= $args[0];
		}else{
			$args	= array_merge($args[1], ['term_id'=>$args[0]]);
		}

		$tax_object	= self::parse_tax_object($args);

		return $tax_object ? $tax_object->get_path($args) : '';
	}

	public static function get_field($args){
		$taxonomy	= array_pull($args, 'taxonomy');
		$tax_object	= ($taxonomy && is_string($taxonomy)) ? wpjam_get_taxonomy_object($taxonomy) : null;

		return $tax_object ? $tax_object->get_id_field($args) : [];
	}

	public static function get_fields($args){
		$tax_object	= self::parse_tax_object($args);

		return $tax_object ? $tax_object->get_id_field(['required'=>true, 'wrap'=>true]) : [];
	}
}

class WPJAM_Author_Data_Type{
	public static function get_path(...$args){
		if(is_array($args[0])){
			$args	= $args[0];
			$author	= (int)array_pull($args, 'author');
		}else{
			$author	= $args[0];
			$args	= $args[1];
		}

		if(!$author){
			return new WP_Error('invalid_author', ['作者']);
		}

		if($args['platform'] == 'template'){
			return get_author_posts_url($author);
		}

		return str_replace('%author%', $author, $args['path']);
	}

	public static function get_fields(){
		return ['author' => ['title'=>'',	'type'=>'select',	'options'=>wp_list_pluck(wpjam_get_authors(), 'display_name', 'ID')]];
	}
}

class WPJAM_Video_Data_Type{
	public static function get_video_mp4($id_or_url){
		if(filter_var($id_or_url, FILTER_VALIDATE_URL)){
			if(preg_match('#http://www.miaopai.com/show/(.*?).htm#i',$id_or_url, $matches)){
				return 'http://gslb.miaopai.com/stream/'.esc_attr($matches[1]).'.mp4';
			}elseif(preg_match('#https://v.qq.com/x/page/(.*?).html#i',$id_or_url, $matches)){
				return self::get_qqv_mp4($matches[1]);
			}elseif(preg_match('#https://v.qq.com/x/cover/.*/(.*?).html#i',$id_or_url, $matches)){
				return self::get_qqv_mp4($matches[1]);
			}else{
				return wpjam_zh_urlencode($id_or_url);
			}
		}else{
			return self::get_qqv_mp4($id_or_url);
		}
	}

	public static function get_qqv_id($id_or_url){
		if(filter_var($id_or_url, FILTER_VALIDATE_URL)){
			foreach([
				'#https://v.qq.com/x/page/(.*?).html#i',
				'#https://v.qq.com/x/cover/.*/(.*?).html#i'
			] as $pattern){
				if(preg_match($pattern,$id_or_url, $matches)){
					return $matches[1];
				}
			}

			return '';
		}else{
			return $id_or_url;
		}
	}

	public static function get_qqv_mp4($vid){
		if(strlen($vid) > 20){
			return new WP_Error('error', '无效的腾讯视频');
		}

		$mp4 = wp_cache_get($vid, 'qqv_mp4');

		if($mp4 === false){
			$response	= wpjam_remote_request('http://vv.video.qq.com/getinfo?otype=json&platform=11001&vid='.$vid, [
				'timeout'				=> 4,
				'json_decode_required'	=> false
			]);

			if(is_wp_error($response)){
				return $response;
			}

			$response	= trim(substr($response, strpos($response, '{')),';');
			$response	= wpjam_try('wpjam_json_decode', $response);

			if(empty($response['vl'])){
				return new WP_Error('error', '腾讯视频不存在或者为收费视频！');
			}

			$u		= $response['vl']['vi'][0];
			$p0		= $u['ul']['ui'][0]['url'];
			$p1		= $u['fn'];
			$p2		= $u['fvkey'];
			$mp4	= $p0.$p1.'?vkey='.$p2;

			wp_cache_set($vid, $mp4, 'qqv_mp4', HOUR_IN_SECONDS*6);
		}

		return $mp4;
	}

	public static function query_items($args){
		return [];
	}

	public static function parse_value($value, $args=[]){
		return self::get_video_mp4($value);
	}
}

class WPJAM_Model_Data_Type{
	public static function filter_query_args($query_args, $args){
		$model	= array_get($query_args, 'model');

		if(!$model || !class_exists($model)){
			wp_die(' model 未定义');
		}

		return $query_args;
	}

	public static function query_items($args){
		$args	= array_except($args, ['label_key', 'id_key']);
		$args	= wp_parse_args($args, ['number'=>10]);
		$model	= array_pull($args, 'model');
		$query	= wpjam_call_method($model, 'query', $args);

		return is_wp_error($query) ? $query : $query->items;
	}

	public static function parse_item($item, $args){
		$label_key	= array_pull($args, 'label_key', 'title');
		$id_key		= array_pull($args, 'id_key', 'id');

		return ['label'=>$item[$label_key], 'value'=>$item[$id_key]];
	}

	public static function query_label($id, $args){
		$model	= array_pull($args, 'model');
		$data	= wpjam_call_method($model, 'get', $id);

		if($data && !is_wp_error($data)){
			$label_key	= $args['label_key'];

			return $data[$label_key] ?: $id;
		}

		return '';
	}

	public static function validate_value($value, $args){
		if($value){
			$model	= array_pull($args, 'model');
			$result	= wpjam_call_method($model, 'get', $value);

			return is_wp_error($result) ? $result : $value;
		}

		return null;
	}

	public static function get_meta_type($args){
		$model		= array_pull($args, 'model');
		$meta_type	= wpjam_call_method($model, 'get_meta_type');

		return is_wp_error($meta_type) ? '' : $meta_type;
	}
}