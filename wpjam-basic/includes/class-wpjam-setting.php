<?php
class WPJAM_Setting extends WPJAM_Args{
	use WPJAM_Instance_Trait;

	public function __call($method, $args){
		if(in_array($method, ['get_setting', 'update_setting', 'delete_setting'])){
			$values	= $this->get_option();
			$name	= array_shift($args);

			if($method == 'get_setting'){
				if($name && $values && is_array($values) && isset($values[$name])){
					$value	= $values[$name];

					if(is_wp_error($value)){
						return null;
					}elseif(is_string($value)){
						return str_replace("\r\n", "\n", trim($value));
					}

					return $value;
				}

				return $name ? null : $values;
			}

			if($method == 'update_setting'){
				$value	= array_shift($args);
				$values	= array_merge($values, [$name => $value]);
			}else{
				$values	= array_except($values, $name);
			}

			return $this->update_option($values);
		}

		$cb_args	= $this->type == 'blog_option' ? [$this->blog_id] : [];
		$cb_args[]	= $this->name;

		if($method == 'get_option'){
			$default	= array_shift($args);
			$default	= $default ?? [];
			$cb_args[]	= $default;

			if($default === [] && $this->option !== null){
				return $this->option;
			}
		}elseif(in_array($method, ['add_option', 'update_option'])){
			$value		= array_shift($args);
			$cb_args[]	= $value ? $this->sanitize_option($value) : $value;
		}

		$callback	= str_replace('option', $this->type, $method);
		$result		= call_user_func($callback, ...$cb_args);

		if($method == 'get_option'){
			$result	= $this->sanitize_option($result);

			$this->option	= $result;
		}else{
			$this->option	= null;
		}

		return $result;
	}

	public static function get_instance($type='', $name='', $blog_id=0){
		if(!in_array($type, ['option', 'site_option']) || !$name){
			return null;
		}

		$key	= $type.':'.$name;

		if(is_multisite() && $type == 'option'){
			$blog_id	= (int)$blog_id ?: get_current_blog_id();
			$key		.= ':'.$blog_id;
			$type		= 'blog_option';
		}

		return self::instance_exists($key) ?: self::add_instance($key, new static([
			'type'		=> $type,
			'name'		=> $name,
			'blog_id'	=> $blog_id
		]));
	}

	public static function sanitize_option($value){
		if(is_wp_error($value)){
			return [];
		}

		return $value ?: [];
	}

	public static function parse_json_module($args){
		$option		= array_get($args, 'option_name');

		if(!$option){
			return null;
		}

		$setting	= array_get($args, 'setting_name');
		$setting	= $setting ?? array_get($args, 'setting');
		$output		= array_get($args, 'output') ?: ($setting ?: $option);
		$object 	= WPJAM_Option_Setting::get($option);
		$value		= $object ? $object->prepare() : wpjam_get_option($option);

		if($setting){
			$value	= $value[$setting] ?? null;
		}

		return [$output	=> $value];
	}
}

class WPJAM_Option_Setting extends WPJAM_Register{
	public function __get($key){
		if(is_admin() && in_array($key, ['title', 'summary', 'ajax'])){
			return $this->get_current_arg($key);
		}

		return parent::__get($key);
	}

	public function __call($method, $args){
		if($method == 'get_defaults'){
			return $this->get_fields('object')->get_defaults();
		}elseif($method == 'get_site_setting'){
			$name	= array_shift($args);

			if($this->option_type == 'array'){
				return wpjam_get_site_setting($this->name, $name);
			}else{
				return $name ? wpjam_get_site_option($name, null) : null;
			}
		}elseif($method == 'get_setting'){
			$isset		= function($value, $name){ return $name ? isset($value) : $value !== []; };
			$name		= $args[0] ?? '';
			$default	= $args[1] ?? ($name ? null : []);
			$blog_id	= $args[2] ?? 0;

			if($this->option_type == 'array'){
				$value	= wpjam_get_setting($this->name, $name, $blog_id);
			}else{
				$value	= wpjam_get_option($name, $blog_id, null);
			}

			if(!$isset($value, $name)){
				if($this->site_default && is_multisite()){
					$value	= $this->get_site_setting($name);
				}
			}

			if(!$isset($value, $name)){
				if($default !== $value){
					return $default;
				}

				if($this->field_default){
					$defaults	= $this->get_defaults();

					return $name ? array_get($defaults, $name) : $defaults;
				}
			}

			return $value;
		}elseif(in_array($method, ['update_setting', 'delete_setting'])){
			$callback	= 'wpjam_'.$method;
			$args		= array_merge([$this->name], $args);

			return call_user_func($callback, ...$args);			
		}
	}

	protected function parse_method($method, $type=null, $args=null){
		if($method == 'get_menu_page' && is_network_admin() && !$this->site_default){
			return;
		}

		return parent::parse_method($method, $type, $args);
	}

	protected function get_filter(){
		return null;
	}

	protected function get_current_arg($key, $item='', $callback=false){
		if(is_admin()){
			$item	= $item ?: self::generate_item_key();
		}

		if($item && $this->get_item($item)){
			return $this->get_arg($key, $item, $callback);
		}

		if(!$item || is_admin()){
			return $this->get_arg($key, '', $callback);
		}
	}

	protected function get_item_sections($item=''){
		$sections	= $this->get_current_arg('sections', $item, true);

		if(!is_null($sections)){
			$sections	= is_array($sections) ? $sections : [];
		}else{
			$fields		= $this->get_current_arg('fields', $item);

			if(!is_null($fields)){
				$id	= $item ?: $this->name;
				$sections	= [$id => [
					'title'		=> $this->get_current_arg('title', $item), 	
					'fields'	=> $fields
				]];
			}else{
				$sections	= [];
			}
		}

		foreach($sections as $id => &$section){
			if(is_array($section)){
				$section['fields']	= $section['fields'] ?? [];

				if(is_callable($section['fields'])){
					$section['fields']	= call_user_func($section['fields'], $id, $this->name);
				}
			}else{
				unset($sections[$id]);
			}
		}

		return $sections;
	}

	protected function get_sections($call_items=null){
		$call_items	= $call_items ?? !is_admin();
		$sections	= $this->get_item_sections();

		if($call_items){
			$sections	= array_merge($sections, $this->call_items('get_item_sections'));
		}

		return WPJAM_Option_Section::filter($sections, $this->name);
	}

	public function get_fields($type='', $call_items=true){
		if($type == ''){
			return array_merge(...array_values(wp_list_pluck($this->get_sections($call_items), 'fields')));
		}

		if(!$call_items || is_null($this->_fields_object)){
			$object	= wpjam_fields($this->get_fields('', $call_items));

			if(!$call_items){
				return $object;
			}

			$this->_fields_object	= $object;
		}

		return $this->_fields_object;
	}

	public function get_summary(){
		return $this->summary;
	}

	public function prepare(){
		return $this->get_fields('object')->prepare(['value_callback'=>[$this, 'value_callback']]);
	}

	public function validate($value){
		return $this->get_fields('object', false)->validate($value);
	}

	public function value_callback($name=''){
		if(is_network_admin()){
			return $this->get_site_setting($name);
		}else{
			return $this->get_setting($name);
		}
	}

	public function add_menu_page($item=''){
		$menu_page	= $this->get_arg('menu_page', $item);
		
		if($menu_page){
			if(wp_is_numeric_array($menu_page)){
				foreach($menu_page as &$m){
					if(!empty($m['tab_slug'])){
						if(empty($m['plugin_page'])){
							$m	= null;
						}
					}elseif(!empty($m['menu_slug']) && $m['menu_slug'] == $this->name){
						$m	= wp_parse_args($m, ['menu_title'=>$this->title]);
					}
				}

				$menu_page	= array_filter($menu_page);
			}else{
				if(!empty($menu_page['tab_slug'])){
					if(empty($menu_page['plugin_page'])){
						$menu_page	= null;
					}else{
						$menu_page	= wp_parse_args($menu_page, ['title'=>$this->title]);
					}
				}else{
					$menu_page	= wp_parse_args($menu_page, ['menu_slug'=>$this->name, 'menu_title'=>$this->title]);
				}
			}

			if($menu_page){
				wpjam_add_menu_page($menu_page);
			}
		}

		return $this;
	}

	public function register_settings(){
		if($this->capability && $this->capability != 'manage_options'){
			add_filter('option_page_capability_'.$this->option_page, [$this, 'filter_capability']);
		}

		$args		= ['sanitize_callback'	=> [$this, 'sanitize_callback']];
		$settings	= [];

		if($this->option_type == 'single'){
			foreach($this->get_sections() as $section){
				foreach(wpjam_parse_fields($section['fields']) as $key => $field){
					$settings[$key]	= array_merge($args, ['field'=>$field]);

					register_setting($this->option_group, $key, $settings[$key]);
				}
			}
		}else{
			$settings[$this->name]	= array_merge($args, ['type'=>'object']);

			register_setting($this->option_group, $this->name, $settings[$this->name]);
		}

		return $settings;
	}

	public function render_sections($tab_page=false){
		$sections	= $this->get_sections();

		$count	= count($sections);
		$nav	= ($count > 1 && !$tab_page) ? wpjam_tag('ul') : null;
		$nonce	= wp_create_nonce($this->option_group);
		$form	= wpjam_tag('form', ['action'=>'options.php', 'method'=>'POST', 'id'=>'wpjam_option', 'data'=>['nonce'=>$nonce]]);

		foreach($sections as $id => $section){
			$tab	= wpjam_tag();

			if($count > 1){
				if(!$tab_page){
					$tab		= wpjam_tag('div', ['id'=>'tab_'.$id]);
					$show_if	= $section['show_if'] ?? null;
					$show_if	= wpjam_parse_show_if($show_if);
					$attr		= $show_if ? ['data'=>['show_if'=>$show_if], 'class'=>'show_if'] : [];

					$nav->append([$section['title'], 'a', ['class'=>'nav-tab', 'href'=>'#tab_'.$id]], 'li', $attr);
				}

				if(!empty($section['title'])){
					$tab->append($section['title'], ($tab_page ? 'h3' : 'h2'));
				}
			}

			if(!empty($section['callback'])) {
				$tab->append(wpjam_ob_get_contents($section['callback'], $section));
			}

			if(!empty($section['summary'])) {
				$tab->append(wpautop($section['summary']));
			}

			$tab->append(wpjam_fields($section['fields'])->render(['value_callback'=>[$this, 'value_callback']]));

			$form->append($tab);
		}

		$button	= wpjam_tag('p', ['submit'], get_submit_button('', 'primary', 'option_submit', false, ['data-action'=>'save']));

		if($this->reset){
			$button->append(get_submit_button('重置选项', 'secondary', 'option_reset', false, ['data-action'=>'reset']));
		}

		$form->append($button);

		return $nav ? $form->before($nav, 'h2', ['nav-tab-wrapper', 'wp-clearfix'])->wrap('div', ['tabs']) : $form;
	}

	public function sanitize_callback($value){
		try{
			if($this->option_type == 'array'){
				$option		= $this->name;
				$current	= $this->value_callback();
				$value		= $this->validate($value) ?: [];
				$value		= array_merge($current, $value);
				$value		= filter_deep($value, 'is_exists');
				$result		= $this->call_method('sanitize_callback', $value, $option);
				$result		= wpjam_throw_if_error($result);

				if(!is_null($result)){
					$value	= $result;
				}
			}else{
				$option		= str_replace('sanitize_option_', '', current_filter());
				$registered	= get_registered_settings();

				if(!isset($registered[$option])){
					return $value;
				}

				$fields	= [$option=>$registered[$option]['field']];
				$value	= wpjam_fields($fields)->validate([$option=>$value]);
				$value	= $value[$option] ?? null;
			}

			return $value;
		}catch(WPJAM_Exception $e){
			add_settings_error($option, $e->get_error_code(), $e->get_error_message());

			return $this->option_type == 'array' ? $current : get_option($option);
		}
	}

	public function ajax_response(){
		if(!check_ajax_referer($this->option_group, false, false)){
			wp_die('invalid_nonce');
		}

		if(!current_user_can($this->capability)){
			wp_die('access_denied');
		}

		$action	= wpjam_get_post_parameter('option_action');

		foreach($this->register_settings() as $option => $args){
			$option = trim($option);

			if($action == 'reset'){
				delete_option($option);
			}else{
				if($this->option_type == 'array'){
					$value	= wpjam_get_data_parameter();
				}else{
					$value	= wpjam_get_data_parameter($option);
				}

				if($this->update_callback){
					if(!is_callable($this->update_callback)){
						wp_die('无效的回调函数');
					}

					call_user_func($this->update_callback, $option, $value, is_network_admin());
				}else{
					$callback	= is_network_admin() ? 'update_site_option' : 'update_option';

					if($this->option_type == 'array'){
						$callback	= 'wpjam_'.$callback;
					}else{
						$value		= is_wp_error($value) ? null : $value;
					}

					call_user_func($callback, $option, $value);
				}
			}
		}

		$errmsg = '';

		foreach(get_settings_errors() as $key => $details){
			if(in_array($details['type'], ['updated', 'success', 'info'])){
				continue;
			}

			$errmsg	.= $details['message'].'&emsp;';
		}

		if($errmsg){
			wp_die($errmsg);
		}

		$response	= $this->response ?? ($this->ajax ? $action : 'redirect');
		$errmsg		= $action == 'reset' ? '设置已重置。' : '设置已保存。';

		return ['type'=>$response,	'errmsg'=>$errmsg];
	}

	public function filter_capability(){
		return $this->capability;
	}

	public static function generate_item_key($args=null){
		$args	= $args ?? $GLOBALS;
		$key	= $args['plugin_page'] ?? '';

		if($key && !empty($args['current_tab'])){
			$key	.= ':'.$args['current_tab'];
		}

		return $key;
	}

	public static function create($name, $args){
		$args	= is_callable($args) ? call_user_func($args, $name) : $args;
		$args	= apply_filters('wpjam_register_option_args', $args, $name);
		$args	= wp_parse_args($args, [
			'option_group'	=> $name, 
			'option_page'	=> $name, 
			'option_type'	=> 'array',
			'capability'	=> 'manage_options',
			'ajax'			=> true,
		]);

		$item	= self::generate_item_key($args);
		$object	= self::get($name);
		$except	= ['model', 'menu_page', 'admin_load', 'plugin_page', 'current_tab'];

		if($object){
			if(!$item || $object->get_item($item)){
				trigger_error('option_setting'.'「'.$name.'」已经注册。'.var_export($args, true));
			}else{
				$args	= self::preprocess_args($args, $name);

				$object->update_args(array_except($args, $except));
				$object->add_item($item, $args);
			}
		}else{
			if($args['option_type'] == 'array' && !doing_filter('sanitize_option_'.$name)){
				if(is_null(get_option($name, null))){
					add_option($name, []);
				}
			}

			if($item){
				$object	= self::register($name, array_except($args, $except));
				$object->add_item($item, $args);
			}else{
				$object	= self::register($name, $args);
			}
		}

		return $object;
	}

	protected static function get_config($key){
		if(in_array($key, ['menu_page', 'admin_load', 'register_json', 'init'])){
			return true;
		}elseif($key == 'item_arg'){
			return 'model';
		}
	}
}

class WPJAM_Option_Section extends WPJAM_Register{
	public static function filter($sections, $option_name){
		foreach(self::get_by('option_name', $option_name) as $object){
			$object_sections	= $object->get_arg('sections');
			$object_sections	= is_array($object_sections) ? $object_sections : [];

			foreach($object_sections as $id => $section){
				if(!empty($section['fields']) && is_callable($section['fields'])){
					$section['fields']	= call_user_func($section['fields'], $id, $option_name);
				}

				if(isset($sections[$id])){
					$sections[$id]	= merge_deep($sections[$id], $section);
				}else{
					if(isset($section['title']) && isset($section['fields'])){
						$sections[$id]	= $section;
					}
				}
			}
		}

		return apply_filters('wpjam_option_setting_sections', $sections, $option_name);
	}

	public static function add($option_name, ...$args){
		if(is_array($args[0])){
			$args	= $args[0];
		}else{
			$section	= isset($args[1]['fields']) ? $args[1] : ['fields'=>$args[1]];
			$args		= [$args[0] => $section];
		}

		if(!isset($args['model']) && !isset($args['sections'])){
			$args	= ['sections'=>$args];
		}

		return self::register(array_merge($args, ['option_name'=>$option_name]));
	}

	protected static function get_config($key){
		if(in_array($key, ['menu_page', 'admin_load', 'init'])){
			return true;
		}
	}
}

class WPJAM_Option_Model{
	protected static function call_method($method, ...$args){
		$object	= WPJAM_Option_Setting::get_by_model(get_called_class(), 'WPJAM_Option_Model');

		return $object ? call_user_func_array([$object, $method], $args) : null;
	}

	public static function get_setting($name='', $default=null){
		return self::call_method('get_setting', $name) ?? $default;
	}

	public static function update_setting($name, $value){
		return self::call_method('update_setting', $name, $value);
	}

	public static function delete_setting($name){
		return self::call_method('delete_setting', $name);
	}
}

class WPJAM_Extend extends WPJAM_Args{
	public function load(){
		if($this->name && is_admin()){
			if($this->sitewide && is_network_admin()){
				$this->summary	.= $this->summary ? '，' : '';
				$this->summary	.= '在管理网络激活将整个站点都会激活！';
			}

			$this->fields	= [$this, 'get_fields'];
			$this->ajax		= false;

			wpjam_register_option($this->name, $this->get_args());
		}

		foreach($this->get_data() as $extend => $value){
			$file	= $this->parse_file($extend);

			if($file){
				include $file;
			}
		}
	}

	public function get_data($type=''){
		if($this->name){
			if(!$type){
				$data	= $this->get_data('blog');

				if($this->sitewide && is_multisite()){
					return array_merge($data, $this->get_data('site'));
				}

				return $data;
			}

			if($type == 'blog'){
				$data	= wpjam_get_option($this->name);
			}elseif($type == 'site'){
				$data	= wpjam_get_site_option($this->name);
			}

			$data	= $data ? array_filter($data) : [];

			if($data && !$this->hierarchical){
				$update	= false;
				$keys	= array_keys($data);

				foreach($keys as &$key){
					if(str_ends_with($key, '.php')){
						$key	= wpjam_remove_postfix($key, '.php');
						$update	= true;
					}
				}

				if($update){
					$data	= array_fill_keys($keys, true);

					if($type == 'blog'){
						wpjam_update_option($this->name, $data);
					}elseif($type == 'site'){
						wpjam_update_site_option($this->name, $data);
					}
				}
			}
		}else{
			$data	= [];

			if($handle = opendir($this->dir)){
				while(false !== ($extend = readdir($handle))){
					$data[$extend]	= true;
				}

				closedir($handle);
			}
		}

		return $data;
	}

	public function get_fields(){
		$fields	= [];
		$values	= $this->get_data('blog');

		if(is_multisite() && $this->sitewide){
			$sitewide	= $this->get_data('site');

			if(is_network_admin()){
				$values	= $sitewide;
			}
		}

		if($handle = opendir($this->dir)){
			while(false  !== ($extend = readdir($handle))){
				if(!$this->hierarchical){
					$extend	= wpjam_remove_postfix($extend, '.php');
				}

				$file	= $this->parse_file($extend);
				$data	= $this->get_file_data($file);

				if($data && ($data['Name'] || $data['PluginName'])){
					if(is_multisite() && $this->sitewide && !is_network_admin()){
						if(!empty($sitewide[$extend])){
							continue;
						}
					}

					$title	= $data['Name'] ?: $data['PluginName'];
					$title	= $data['URI'] ? '<a href="'.$data['URI'].'" target="_blank">'.$title.'</a>' : $title;
					$value	= !empty($values[$extend]);

					$fields[$extend] = ['title'=>$title, 'type'=>'checkbox', 'value'=>$value, 'description'=>$data['Description']];
				}
			}

			closedir($handle);
		}

		return wp_list_sort($fields, 'value', 'DESC', true);
	}

	private function parse_file($extend){
		if($extend == '.' || $extend == '..'){
			return '';
		}

		if($this->hierarchical){
			if(is_dir($this->dir.'/'.$extend)){
				$file	= $this->dir.'/'.$extend.'/'.$extend.'.php';
			}else{
				$file	= '';
			}
		}else{
			if(pathinfo($extend, PATHINFO_EXTENSION) == 'php'){
				$file	= $this->dir.'/'.$extend;
			}else{
				$file	= $this->dir.'/'.$extend.'.php';
			}
		}

		return ($file && is_file($file)) ? $file : '';
	}

	public static function get_file_data($file){
		return $file ? get_file_data($file, [
			'Name'			=> 'Name',
			'URI'			=> 'URI',
			'PluginName'	=> 'Plugin Name',
			'PluginURI'		=> 'Plugin URI',
			'Version'		=> 'Version',
			'Description'	=> 'Description'
		]) : [];
	}

	public static function get_file_summay($file){
		$data	= self::get_file_data($file);

		foreach(['URI', 'Name'] as $key){
			if(empty($data[$key])){
				$data[$key]	= $data['Plugin'.$key] ?? '';
			}
		}

		$summary	= str_replace('。', '，', $data['Description']);
		$summary	.= '详细介绍请点击：<a href="'.$data['URI'].'" target="_blank">'.$data['Name'].'</a>。';

		return $summary;
	}

	public static function create($dir, $args=[], $name=''){
		if($dir && is_dir($dir)){
			$hook	= array_pull($args, 'hook');
			$object	= new self(array_merge($args, ['dir'=>$dir, 'name'=>$name]));

			if($hook){
				add_action($hook, [$object, 'load'], ($object->priority ?? 10));
			}else{
				$object->load();
			}
		}
	}
}

class WPJAM_Notice extends WPJAM_Singleton{
	public function __get($key){
		$value	= parent::__get($key);

		if(!$value){
			if($key == 'key'){
				return 'wpjam_notices';
			}elseif($key == 'blog_id'){
				return get_current_blog_id();
			}elseif($key == 'user_id'){
				return get_current_user_id();
			}
		}

		return $value;
	}

	protected function get_items(){
		$data	= is_multisite() ? get_blog_option($this->blog_id, $this->key) : get_option($this->key);

		return $data ? array_filter($data, [$this, 'filter_item']) : [];
	}

	protected function update_items($data){
		if(!$data){
			return is_multisite() ? delete_blog_option($this->blog_id, $this->key) : delete_option($this->key);
		}else{
			return is_multisite() ? update_blog_option($this->blog_id, $this->key, $data) : update_option($this->key, $data);
		}
	}

	protected function filter_item($item){
		if($item['time'] > time() - MONTH_IN_SECONDS * 3){
			return trim($item['notice']);
		}

		return false;
	}

	public function insert($item){
		$item	= is_array($item) ? $item : ['notice'=>$item];
		$key	= $item['key'] ?? '';
		$key	= $key ?: md5(maybe_serialize($item));
		$item	= wp_parse_args($item, ['notice'=>'', 'type'=>'error', 'time'=>time()]);
		$data	= $this->get_items();

		return $this->update_items(array_merge($data, [$key=>$item]));
	}

	public function update($key, $item){
		$data	= $this->get_items();

		if(isset($data[$key])){
			return $this->update_items(array_merge($data, [$key=>$item]));
		}

		return true;
	}

	public function delete($key){
		$data	= $this->get_items();

		if(isset($data[$key])){
			return $this->update_items(array_except($data, $key));
		}

		return true;
	}

	public static function render(){
		self::ajax_delete();

		$items	= (WPJAM_User_Notice::get_instance())->get_items();

		if(current_user_can('manage_options')){
			$items	= array_merge($items, (WPJAM_Notice::get_instance())->get_items());
		}

		if($items){
			uasort($items, function($n, $m){ return $m['time'] <=> $n['time']; });
		}

		foreach($items as $key => $item){
			$item	= wp_parse_args($item, [
				'type'		=> 'info',
				'class'		=> 'is-dismissible',
				'admin_url'	=> '',
				'notice'	=> '',
				'title'		=> '',
				'modal'		=> 0,
			]);

			$notice	= trim($item['notice']);

			if($item['admin_url']){
				$notice	.= $item['modal'] ? "\n\n" : ' ';
				$notice	.= '<a style="text-decoration:none;" href="'.add_query_arg(['notice_key'=>$key], home_url($item['admin_url'])).'">点击查看<span class="dashicons dashicons-arrow-right-alt"></span></a>';
			}

			$notice	= wpautop($notice).wpjam_get_page_button('delete_notice', ['data'=>['notice_key'=>$key]]);

			if($item['modal']){
				if(empty($modal)){	// 弹窗每次只显示一条
					$modal	= $notice;
					$title	= $item['title'] ?: '消息';

					echo '<div id="notice_modal" class="hidden" data-title="'.esc_attr($title).'">'.$modal.'</div>';
				}
			}else{
				echo '<div class="notice notice-'.$item['type'].' '.$item['class'].'">'.$notice.'</div>';
			}
		}
	}

	public static function ajax_delete(){
		$key = wpjam_get_data_parameter('notice_key');

		if($key){
			(WPJAM_User_Notice::get_instance())->delete($key);

			if(current_user_can('manage_options')){
				(WPJAM_Notice::get_instance())->delete($key);
			}

			wpjam_send_json(['notice_key'=>$key]);
		}
	}

	public static function add($item){	// 兼容函数
		return wpjam_add_admin_notice($item);
	}
}

class WPJAM_User_Notice extends WPJAM_Notice{
	protected function get_items(){
		$data	= get_user_meta($this->user_id, $this->key, true);

		return $data ? array_filter($data, [$this, 'filter_item']) : [];
	}

	protected function update_items($value){
		if(!$value){
			return delete_user_meta($this->user_id, $this->key);
		}else{
			return update_user_meta($this->user_id, $this->key, $value);
		}
	}
}