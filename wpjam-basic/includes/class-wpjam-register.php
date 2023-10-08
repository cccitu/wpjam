<?php
trait WPJAM_Call_Trait{
	protected static $_closures	= [];

	public static function add_dynamic_method($method, Closure $closure){
		if(is_callable($closure)){
			$name	= strtolower(get_called_class());

			self::$_closures[$name][$method]	= $closure;
		}
	}

	public static function remove_dynamic_method($method){
		$name	= strtolower(get_called_class());

		unset(self::$_closures[$name]);
	}

	protected static function get_dynamic_method($method){
		$called	= get_called_class();
		$names	= array_values(class_parents($called));

		array_unshift($names, $called);

		foreach($names as $name){
			$name	= strtolower($name);

			if(isset(self::$_closures[$name][$method])){
				return self::$_closures[$name][$method];
			}
		}
	}

	protected function call_dynamic_method($method, ...$args){
		$closure	= is_closure($method) ? $method : self::get_dynamic_method($method);
		$callback	= $closure ? $closure->bindTo($this, get_called_class()) : null;

		return $callback ? call_user_func_array($callback, $args) : null;
	}

	public function call($method, ...$args){
		try{
			if(!is_closure($method) && method_exists($this, $method)){
				return call_user_func_array([$this, $method], $args);
			}else{
				return $this->call_dynamic_method($method, ...$args);
			}
		}catch(WPJAM_Exception $e){
			return $e->get_wp_error();
		}catch(Exception $e){
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}

	public function try($method, ...$args){
		if(is_callable([$this, $method])){
			try{
				$result	= call_user_func_array([$this, $method], $args);

				return wpjam_throw_if_error($result);
			}catch(Exception $e){
				throw $e;
			}
		}

		trigger_error(get_called_class().':'.$method, true);
	}

	public function map($value, $method, ...$args){
		if($value && is_array($value)){
			foreach($value as &$item){
				$item	= $this->try($method, $item, ...$args);
			}
		}

		return $value;
	}
}

trait WPJAM_Items_Trait{
	public function get_items($field=null){
		$items	= $this->_items;

		return is_array($items) ? $items : [];
	}

	public function update_items($items, $field=null){
		$this->_items	= $items;

		return $this;
	}

	public function get_item_keys($field=null){
		return array_keys($this->get_items($field));
	}

	public function item_exists($key, $field=null){
		return array_key_exists($key, $this->get_items($field));
	}

	public function get_item($key, $field=null){
		$items	= $this->get_items($field);

		return $items[$key] ?? null;
	}

	public function add_item(...$args){
		if(count($args) >= 2){
			$key	= $args[0];
			$item	= $args[1];
			$field	= $args[2] ?? null;
		}else{
			$item	= $args[0];
			$key	= null;
			$field	= null;
		}

		return $this->item_action('add', $key, $item, $field);
	}

	public function edit_item($key, $item, $field=null){
		return $this->item_action('edit', $key, $item, $field);
	}

	public function replace_item($key, $item, $field=null){
		return $this->item_action('replace', $key, $item, $field);
	}

	public function set_item($key, $item, $field=null){
		return $this->item_action('set', $key, $item, $field);
	}

	public function delete_item($key, $field=null){
		$result	= $this->item_action('delete', $key, null, $field);

		if(!is_wp_error($result)){
			$this->after_delete_item($key, $field);
		}

		return $result;
	}

	public function del_item($key, $field=null){
		return $this->delete_item($key, $field);
	}

	public function move_item($orders, $field=null){
		$new	= [];
		$items	= $this->get_items($field);

		foreach($orders as $i){
			if(isset($items[$i])){
				$new[]	= array_pull($items, $i);
			}
		}

		return $this->update_items(array_merge($new, $items), $field);
	}

	protected function item_action($action, $key, $item, $field=null){
		$result	= $this->validate_item($item, $key, $action, $field);

		if(is_wp_error($result)){
			return $result;
		}

		$items	= $this->get_items($field);

		if(isset($key)){
			if($this->item_exists($key, $field)){
				if($action == 'add'){
					return new WP_Error('invalid_item_key', '「'.$key.'」已存在，无法添加');
				}
			}else{
				if(in_array($action, ['edit', 'replace'])){
					return new WP_Error('invalid_item_key', '「'.$key.'不存在，无法编辑');
				}elseif($action == 'delete'){
					return new WP_Error('invalid_item_key', '「'.$key.'不存在，无法删除');
				}
			}

			if(isset($item)){
				$items[$key]	= $this->sanitize_item($item, $key, $action, $field);	
			}else{
				$items	= array_except($items, $key);
			}
		}else{
			if($action == 'add'){
				$items[]	= $this->sanitize_item($item, $key, $action, $field);
			}else{
				return new WP_Error('invalid_item_key', '必须设置key');
			}
		}

		return $this->update_items($items, $field);
	}

	protected function validate_item($item=null, $key=null, $action='', $field=null){
		return true;
	}

	protected function sanitize_item($item, $id=null, $field=null){
		return $item;
	}

	protected function after_delete_item($key, $field=null){
	}

	protected function call_items($method, ...$args){
		if(str_starts_with($method, 'get_')){
			$type	= 'get_';
			$return	= [];
		}else{
			$return	= null;
			$type	= '';
		}

		if(static::get_config('item_arg')){
			foreach($this->get_item_keys() as $item){
				$result	= call_user_func([$this, $method], $item, ...$args);

				if(is_wp_error($result)){
					return $result;
				}

				if($type == 'get_'){
					if($result && is_array($result)){
						$return	= array_merge($return, $result);
					}
				}else{
					$return	= $result;
				}
			}
		}

		return $return;
	}

	public static function item_list_action($id, $data, $action_key=''){
		$object	= static::get_instance($id);

		if(!$object){
			wp_die('invaid_id');
		}

		$i	= wpjam_get_data_parameter('i');

		if($action_key == 'add_item'){
			return $object->add_item($i, $data);
		}elseif($action_key == 'edit_item'){
			return $object->edit_item($i, $data);
		}elseif($action_key == 'del_item'){
			return $object->del_item($i);
		}elseif($action_key == 'move_item'){
			$orders	= wpjam_get_data_parameter('item') ?: [];

			return $object->move_item($orders);
		}
	}

	public static function item_data_action($id){
		$object	= static::get_instance($id);

		if(!$object){
			wp_die('invaid_id');
		}

		$i	= wpjam_get_data_parameter('i');

		return $object->get_item($i);
	}
	
	public static function get_item_actions(){
		$item_action	= [
			'callback'		=> [self::class, 'item_list_action'],
			'data_callback'	=> [self::class, 'item_data_action'],
			'row_action'	=> false,
		];

		return [
			'add_item'	=>['title'=>'添加项目',	'page_title'=>'添加项目',	'dismiss'=>true]+$item_action,
			'edit_item'	=>['title'=>'修改项目',	'page_title'=>'修改项目',	]+$item_action,
			'del_item'	=>['title'=>'删除项目',	'page_title'=>'删除项目',	'direct'=>true,	'confirm'=>true]+$item_action,
			'move_item'	=>['title'=>'移动项目',	'page_title'=>'移动项目',	'direct'=>true]+$item_action,
		];
	}
}

class WPJAM_Args implements ArrayAccess, IteratorAggregate, JsonSerializable{
	use WPJAM_Call_Trait;

	protected $args;
	protected $_archives	= [];

	public function __construct($args=[]){
		$this->args	= $args;
	}

	public function __get($key){
		$args	= $this->get_args();

		if(array_key_exists($key, $args)){
			return $args[$key];
		}

		return $key == 'args' ? $args : null;
	}

	public function __set($key, $value){
		$this->filter_args();

		$this->args[$key]	= $value;
	}

	public function __isset($key){
		if(array_key_exists($key, $this->get_args())){
			return true;
		}

		return $this->$key !== null;
	}

	public function __unset($key){
		$this->filter_args();

		unset($this->args[$key]);
	}

	#[ReturnTypeWillChange]
	public function offsetGet($key){
		$args	= $this->get_args();

		return $args[$key] ?? null;
	}

	#[ReturnTypeWillChange]
	public function offsetSet($key, $value){
		$this->filter_args();

		if(is_null($key)){
			$this->args[]		= $value;
		}else{
			$this->args[$key]	= $value;
		}
	}

	#[ReturnTypeWillChange]
	public function offsetExists($key){
		return array_key_exists($key, $this->get_args());
	}

	#[ReturnTypeWillChange]
	public function offsetUnset($key){
		$this->filter_args();

		unset($this->args[$key]);
	}

	#[ReturnTypeWillChange]
	public function getIterator(){
		return new ArrayIterator($this->get_args());
	}

	#[ReturnTypeWillChange]
	public function jsonSerialize(){
		return $this->get_args();
	}

	public function invoke(...$args){
		$invoke	= $this->invoke;

		if($invoke){
			return $this->call_dynamic_method($invoke, ...$args);
		}
	}

	protected function error($errcode, $errmsg){
		return new WP_Error($errcode, $errmsg);
	}

	protected function filter_args(){
		if(!$this->args && !is_array($this->args)){
			return $this->args = [];
		}

		return $this->args;
	}

	public function get_args(){
		return $this->filter_args();
	}

	public function set_args($args){
		$this->args	= $args;

		return $this;
	}

	public function update_args($args){
		foreach($args as $key => $value){
			$this->$key	= $value;
		}

		return $this;
	}

	public function to_array(){
		return $this->get_args();
	}

	protected function sanitize_value($value){
		if($this->sanitize_callback && is_callable($this->sanitize_callback)){
			return call_user_func($this->sanitize_callback, $value);
		}

		return $value;
	}

	protected function validate_value($value){
		if($this->validate_callback && is_callable($this->validate_callback)){
			return call_user_func($this->validate_callback, $value);
		}

		return true;
	}

	public function get_archives(){
		return $this->_archives;
	}

	public function archive(){
		array_push($this->_archives, $this->get_args());

		return $this;
	}

	public function restore(){
		if($this->_archives){
			$this->args	= array_pop($this->_archives);
		}

		return $this;
	}

	public function sandbox($callback, ...$args){
		$this->archive();

		$result	= call_user_func_array($callback, $args);

		$this->restore();

		return $result;
	}

	public function get_arg($key, $default=null){
		return array_get($this->get_args(), $key, $default);
	}

	public function update_arg($key, $value=null){
		$this->filter_args();

		array_set($this->args, $key, $value);

		return $this;
	}

	public function delete_arg($key){
		$this->args	= array_except($this->get_args(), $key);

		return $this;
	}

	public function push_arg($key, ...$values){
		$value	= array_wrap($this->$key);
		$values	= array_filter($values, 'is_exists');

		if($values){
			array_push($value, ...$values);

			$this->$key	= $value;
		}

		return $this;
	}

	public function pull($key, $default=null){
		if(isset($this->$key)){
			$value	= $this->$key;

			unset($this->$key);

			return $value;
		}

		return $default;
	}

	public function pulls($keys){
		$data	= [];

		foreach($keys as $key){
			if(isset($this->$key)){
				$data[$key]	= $this->pull($key);
			}
		}

		return $data;
	}

	public function filter_parameter_default($default, $name){
		return $this->defaults[$name] ?? $default;
	}
}

class WPJAM_Singleton extends WPJAM_Args{
	protected function __construct(){}

	public static function get_instance(){
		$name	= strtolower(get_called_class()).'_object';

		return $GLOBALS[$name] = ($GLOBALS[$name] ?? new static());
	}
}

class WPJAM_Register extends WPJAM_Args{
	use WPJAM_Items_Trait;

	protected $name;
	protected $_group;
	protected $_filtered	= false;

	public function __construct($name, $args=[], $group=''){
		$this->name		= $name;
		$this->_group	= $group = self::parse_group($group);

		if($this->is_active() || !empty($args['active'])){
			$args	= static::preprocess_args($args, $name);
		}

		$this->args	= $args;
	}

	public function __get($key){
		if($key == 'name'){
			return $this->name;
		}

		return parent::__get($key);
	}

	public function __set($key, $value){
		if($key != 'name'){
			parent::__set($key, $value);
		}
	}

	public function __isset($key){
		if($key == 'name'){
			return true;
		}

		return parent::__isset($key);
	}

	protected function parse_method($method, $type=null, $args=null){
		if($type == 'model'){
			$model	= $args ? array_get($args, 'model') : $this->model;

			if($model && method_exists($model, $method)){
				return [$model, $method];
			}
		}elseif($type == 'property'){
			if($this->$method && is_callable($this->$method)){
				return $this->$method;
			}
		}else{
			foreach(['model', 'property'] as $type){
				$called = $this->parse_method($method, $type);

				if($called){
					return $called;
				}
			}
		}
	}

	protected function method_exists($method, $type=null){
		return $this->parse_method($method, $type) ? true : false;
	}

	protected function call_method($method, ...$args){
		$called	= $this->parse_method($method);

		if($called){
			return call_user_func_array($called, $args);
		}

		if(str_starts_with($method, 'filter_')){
			return $args[0] ?? null;
		}
	}

	protected function filter_args(){
		if(!$this->_filtered){
			$this->_filtered	= true;

			if(method_exists($this, 'parse_args')){
				$args	= $this->parse_args();

				trigger_error(get_called_class().'还有「parse_args」方法');
			}else{
				$args	= null;
			}

			$args	= is_null($args) ? $this->args : $args;
			$filter	= $this->get_filter();

			if($filter){
				$args	= apply_filters($filter, $args, $this->name);
			}

			$this->args	= $args;
		}

		return $this->args;
	}

	protected function get_filter(){
		$class	= strtolower(get_called_class());

		if($class == 'wpjam_register'){
			return 'wpjam_'.$this->_group.'_args';
		}else{
			return $class.'_args';
		}
	}

	public function get_arg($key='', $item=null, $callback=true){
		$args	= [$this->name];

		if($item){
			$args[]	= $item;
			$item	= $this->get_item($item);

			if(!$item){
				return null;
			}

			$value		= array_get($item, $key);
			$by_model	= (is_null($value) && static::get_config('item_arg') == 'model');
		}else{
			$value		= parent::get_arg($key);
			$by_model	= (is_null($value) && $this->model);
		}

		if($by_model && $key && is_string($key) && !str_contains($key, '.')){
			$value	= $this->parse_method('get_'.$key, 'model', $item);
		}

		if($callback && $value && is_callable($value)){
			return call_user_func_array($value, $args);
		}else{
			return $value;
		}
	}

	public function get_item_arg($item, $key, $callback=false){
		return $this->get_arg($key, $item, $callback);
	}

	public function is_active(){
		return true;
	}

	// match($args=[], $operator='AND')
	// match($key, $value)
	public function match(...$args){
		$args[0]	= $args[0] ?? [];

		if(is_array($args[0])){
			if(!$args[0]){
				return true;
			}

			$operator	= $args[1] ?? 'AND';
			$match_args	= [];

			foreach($args[0] as $key => $value){
				if(is_string($value) && str_starts_with($value, 'match:')){
					$key	= $key.':'.wpjam_remove_prefix($value, 'match:');
					$value	= function($key){ return $this->match($key, null); };
				}
				
				$match_args[$key]	= $value;
			}

			return wpjam_match($this, $match_args, $operator);
		}else{
			$key	= $args[0];
			$value	= $args[1] ?? null;
			$null	= $args[2] ?? true;

			if(is_null($value) && str_contains($key, ':')){
				$parts	= explode(':', $key);
				$key	= array_shift($parts);
				$value	= implode(':', $parts);
			}

			if($null && is_null($this->$key)){
				return true;
			}

			if(is_callable($this->$key)){
				if(wpjam_call($this->$key, $value, $this)){
					return true;
				}
			}else{
				if(wpjam_compare($value, (array)$this->$key)){
					return true;
				}
			}

			return false;
		}
	}

	public function data_type($slice){
		if($this->data_type){
			$data_type	= $slice['data_type'] ?? '';

			if($data_type != $this->data_type){
				return false;
			}

			if($this->$data_type){
				$type_value	= $slice[$data_type] ?? '';

				if(!$this->match($data_type, $type_value)){
					return false;
				}
			}
		}

		return true;
	}

	public function add_menu_page($item=''){
		$menu_page	= $this->get_arg('menu_page', $item);

		if($menu_page){
			wpjam_add_menu_page($menu_page);
		}

		return $this;
	}

	public function add_admin_load($item=''){
		$admin_load	= $this->get_arg('admin_load', $item);

		if($admin_load){
			wpjam_add_admin_load($admin_load);
		}

		return $this;
	}

	protected static $_registereds	= [];
	protected static $_hooked		= [];

	protected static function get_config($key){
		return null;
	}

	protected static function validate_name($name){
		if(empty($name)){
			trigger_error(self::class.'的注册 name 为空');
			return;
		}elseif(is_numeric($name)){
			trigger_error(self::class.'的注册 name「'.$name.'」'.'为纯数字');
			return;
		}elseif(!is_string($name)){
			trigger_error(self::class.'的注册 name「'.var_export($name, true).'」不为字符串');
			return;
		}

		return $name;
	}

	protected static function parse_group($group=null){
		if($group){
			return strtolower($group);
		}else{
			$group	= wpjam_remove_prefix(strtolower(get_called_class()), 'wpjam_');

			return $group == 'register' ? '' : $group;
		}
	}

	protected static function preprocess_args($args, $name){
		$model_config	= static::get_config('model');
		$model_config	= $model_config ?? true;

		$model	= $model_config ? array_get($args, 'model') : null;
		$hooks	= array_pull($args, 'hooks');
		$init	= array_pull($args, 'init');

		if($model || $hooks || $init){
			$file	= array_pull($args, 'file');

			if($file && is_file($file)){
				include_once $file;
			}
		}

		if($model && is_subclass_of($model, 'WPJAM_Register')){
			$model_class	= is_object($model) ? get_class($model) : $model;
			trigger_error('「'.$model_class.'」是 WPJAM_Register 子类');
		}

		if($model_config === 'object'){
			if(!$model){
				trigger_error('model 不存在');
			}

			if(!is_object($model)){
				if(!class_exists($model)){
					trigger_error('model 无效');
				}

				$model = $args['model']	= new $model($args);
			}
		}

		if($model){
			if($hooks === true || is_null($hooks)){
				if(method_exists($model, 'add_hooks')){
					$hooks	= [$model, 'add_hooks'];
				}
			}

			if($init === true || (is_null($init) && static::get_config('init'))){
				if(method_exists($model, 'init')){
					$init	= [$model, 'init'];
				}
			}
		}

		if($init && $init !== true){
			wpjam_load('init', $init);
		}

		if($hooks && $hooks !== true){
			wpjam_hooks($hooks);
		}

		$group	= self::parse_group();

		if($group && empty(self::$_hooked[$group])){
			self::$_hooked[$group]	= true;

			if(static::get_config('register_json')){
				add_action('wpjam_api', [get_called_class(), 'on_register_json']);
			}

			if(is_admin()){
				if(static::get_config('menu_page') || static::get_config('admin_load')){
					add_action('wpjam_admin_init', [get_called_class(), 'on_admin_init']);
				}
			}
		}

		return $args;
	}

	public static function register_by_group($group, ...$args){
		$group			= self::parse_group($group);
		$registereds	= self::$_registereds[$group] ?? [];

		if(is_object($args[0])){
			$args	= $args[0];
			$name	= $args->name;
		}elseif(is_array($args[0])){
			$args	= $args[0];
			$name	= '__'.count($registereds);
		}else{
			$name	= self::validate_name($args[0]);
			$args	= $args[1] ?? [];

			if(is_null($name)){
				return;
			}
		}

		if(is_object($args)){
			$object	= $args;
		}else{
			if(!empty($args['admin']) && !is_admin()){
				return;
			}

			$object	= new static($name, $args, $group);
			$name	= self::sanitize_name($name, $args);
		}

		if(isset($registereds[$name])){
			trigger_error($group.'「'.$name.'」已经注册。');
		}

		$orderby	= static::get_config('orderby');

		if($orderby){
			$orderby	= $orderby === true ? 'order' : $orderby;
			$current	= $object->$orderby = $object->$orderby ?? 10;
			$order		= static::get_config('order');
			$order		= $order ? strtoupper($order) : 'DESC';
			$sorted		= [];

			foreach($registereds as $_name => $_registered){
				if(!isset($sorted[$name])){
					$value	= $current - $_registered->$orderby;
					$value	= $order == 'DESC' ? $value : (0 - $value);

					if($value > 0){
						$sorted[$name]	= $object;
					}
				}

				$sorted[$_name]	= $_registered;
			}

			$sorted[$name]	= $object;

			self::$_registereds[$group]	= $sorted;
		}else{
			self::$_registereds[$group][$name]	= $object;
		}

		$registered	= static::get_config('registered');

		if($registered && method_exists($object, $registered)){
			if($registered == 'init'){
				wpjam_load('init', [$object, 'init']);
			}else{
				call_user_func([$object, $registered]);
			}
		}

		return $object;
	}

	public static function unregister_by_group($group, $name, $args=[]){
		$group	= self::parse_group($group);
		$name	= self::sanitize_name($name, $args);

		if(isset(self::$_registereds[$group][$name])){
			unset(self::$_registereds[$group][$name]);
		}
	}

	public static function get_by_group($group=null, $name=null, $args=[], $operator='AND'){
		if($name){
			if($args && static::get_config('data_type')){
				$objects	= self::get_by_group($group, null, wpjam_slice_data_type($args), $operator);
			}else{
				$objects	= self::get_by_group($group);
			}

			if(static::get_config('data_type') && !isset($args['data_type'])){
				$objects	= wp_filter_object_list($objects, ['name'=>$name]);

				if($objects && count($objects) == 1){
					return current($objects);
				}
			}else{
				if(isset($objects[$name])){
					return $objects[$name];
				}
			}

			return null;
		}

		$group		= self::parse_group($group);
		$objects	= self::$_registereds[$group] ?? [];

		if($args){
			if(static::get_config('data_type')){
				$data_type	= !empty($args['data_type']);
				$slice		= wpjam_slice_data_type($args, true);
			}else{
				$data_type	= false;
			}

			$filtered	= [];

			foreach($objects as $name => $object){
				if(static::get_config('data_type') && !$object->data_type($slice)){
					continue;
				}

				if($object->match($args, $operator)){
					if($data_type){
						$filtered[$object->name]	= $object;
					}else{
						$filtered[$name]	= $object;
					}
				}
			}

			return $filtered;
		}

		return $objects;
	}

	public static function register(...$args){
		return self::register_by_group(null, ...$args);
	}

	public static function registers($items){
		foreach($items as $name => $args){
			if(!self::get_by_group(null, $name, $args)){
				self::register($name, $args);
			}
		}
	}

	public static function unregister($name){
		self::unregister_by_group(null, $name);
	}

	public static function get_registereds($args=[], $output='objects', $operator='and'){
		$defaults	= static::get_config('defaults');

		if($defaults){
			self::registers($defaults);
		}

		$objects	= self::get_by_group(null, null, $args, $operator);

		if($output == 'names'){
			return array_keys($objects);
		}elseif(in_array($output, ['args', 'settings'])){
			return array_map(function($registered){
				return $registered->to_array();
			}, $objects);
		}else{
			return $objects;
		}
	}

	public static function get_by(...$args){
		if($args){
			$args	= is_array($args[0]) ? $args[0] : [$args[0] => $args[1]];
		}

		return self::get_registereds($args);
	}

	public static function get_by_model($model, $top=''){
		while($model && strcasecmp($model, $top) !== 0){
			foreach(self::get_registereds() as $object){
				if($object->model && is_string($object->model) && strcasecmp($object->model, $model) === 0){
					return $object;
				}

				if(static::get_config('item_arg') == 'model'){
					foreach($object->get_items() as $item){
						if(!empty($item['model']) && is_string($item['model']) && strcasecmp($item['model'], $model) === 0){
							return $object;
						}
					}
				}
			}

			$model	= get_parent_class($model);
		}

		return null;
	}

	public static function get_options_fields($args=[]){
		$args	= wp_parse_args($args, [
			'name'				=> self::parse_group(),
			'title'				=> '',
			'title_field'		=> 'title',
			'show_option_none'	=> __('&mdash; Select &mdash;'),
			'option_none_value'	=> '',
		]);

		$name		= $args['name'];
		$fields		= [$name => ['title'=>$args['title'], 'type'=>'select']];
		$options	= wp_list_pluck(self::get_registereds(), $args['title_field']);

		if($args['show_option_none']){
			$options	= array_merge([$args['option_none_value'] => $args['show_option_none']], $options);
		}

		$custom_fields	= static::get_config('custom_fields');

		if($custom_fields){
			$options['custom']	= '自定义';

			foreach($custom_fields as $field_key => $custom_field){
				$fields[$field_key]	= array_merge($custom_field, ['show_if'=>['key'=>$name, 'value'=>'custom']]);
			} 
		}

		$fields[$name]['options']	= $options;

		return $fields;
	}

	public static function get_setting_fields(){
		$fields	= [];

		foreach(self::get_registereds() as $name => $object){
			if(is_null($object->active)){
				$fields[$name]	= wp_parse_args(($object->field ?: []), ['type'=>'checkbox', 'description'=>$object->title]);
			}
		}

		return $fields;
	}

	public static function get($name, $args=[]){
		if($name){
			$object = self::get_by_group(null, $name, $args);

			if(!$object){
				if($name == 'custom'){
					$custom_args	= static::get_config('custom_args');

					if(is_array($custom_args)){
						$object	= self::register($name, $custom_args);
					}
				}else{
					$defaults	= static::get_config('defaults');
					$default	= $defaults[$name] ?? null;

					if(is_array($default)){
						$object	= self::register($name, $default);
					}
				}
			}

			return $object;
		}

		return null;
	}

	public static function exists($name){
		return self::get($name) ? true : false;
	}

	protected static function sanitize_name($name, $args){
		if(static::get_config('data_type') && !empty($args['data_type'])){
			return $name.'__'.md5(maybe_serialize(wpjam_slice_data_type($args)));
		}

		return $name;
	}

	public static function get_active($key=null){
		$return	= [];

		foreach(self::get_registereds() as $name => $object){
			$active	= $object->active ?? $object->is_active();

			if($active){
				if($key){
					$value	= $object->get_arg($key);

					if(!is_null($value)){
						$return[$name]	= $value;
					}
				}else{
					$return[$name]	= $object;
				}
			}
		}

		return $return;
	}

	public static function call_active($method, ...$args){
		if(str_starts_with($method, 'filter_')){
			$type	= 'filter_';
		}elseif(str_starts_with($method, 'get_')){
			$return	= [];
			$type	= 'get_';
		}else{
			$type	= '';
		}

		foreach(self::get_active() as $object){
			$result	= $object->call_method($method, ...$args);

			if(is_wp_error($result)){
				return $result;
			}

			if($type == 'filter_'){
				$args[0]	= $result;
			}elseif($type == 'get_'){
				if($result && is_array($result)){
					$return	= array_merge($return, $result);
				}
			}
		}

		if($type == 'filter_'){
			return $args[0];
		}elseif($type == 'get_'){
			return $return;
		}
	}

	public static function on_admin_init(){
		foreach(self::get_active() as $object){
			if(static::get_config('menu_page')){
				$object->add_menu_page()->call_items('add_menu_page');
			}

			if(static::get_config('admin_load')){
				$object->add_admin_load()->call_items('add_admin_load');
			}
		}
	}

	public static function on_register_json($json){
		return self::call_active('register_json', $json);
	}

	protected static function get_model($args){	// 兼容
		$file	= array_pull($args, 'file');

		if($file && is_file($file)){
			include_once $file;
		}

		return $args['model'] ?? null;
	}
}

class WPJAM_Meta_Type extends WPJAM_Register{
	public function __construct($name, $args=[]){
		$name	= sanitize_key($name);
		$args	= wp_parse_args($args, [
			'table_name'	=> $name.'meta',
			'table'			=> $GLOBALS['wpdb']->prefix.$name.'meta',
		]);

		if(!isset($GLOBALS['wpdb']->{$args['table_name']})){
			$GLOBALS['wpdb']->{$args['table_name']} = $args['table'];
		}

		parent::__construct($name, $args);
	}

	public function __call($method, $args){
		if(str_ends_with($method, '_option')){
			$method	= wpjam_remove_postfix($method, '_option');	// get_option register_option unregister_option
			$name	= array_shift($args);

			if($method == 'register'){
				$args	= array_merge(array_shift($args), ['meta_type'=>$this->name]);

				if($this->name == 'post'){
					$args	= wp_parse_args($args, ['fields'=>[], 'priority'=>'default']);

					if(!isset($args['post_type']) && isset($args['post_types'])){
						$args['post_type']	= array_pull($args, 'post_types') ?: null;
					}
				}elseif($this->name == 'term'){
					if(!isset($args['taxonomy']) && isset($args['taxonomies'])){
						$args['taxonomy']	= array_pull($args, 'taxonomies') ?: null;
					}

					if(!isset($args['fields'])){
						$args['fields']		= [$name => array_except($args, 'taxonomy')];
						$args['from_field']	= true;
					}
				}

				$object	= new WPJAM_Meta_Option($name, $args);
				$args	= [$object];
			}

			return call_user_func(['WPJAM_Meta_Option', $method], $this->name.':'.$name, ...$args); 
		}elseif(in_array($method, ['get_data', 'add_data', 'update_data', 'delete_data', 'data_exists'])){
			array_unshift($args, $this->name);

			$callback	= str_replace('data', 'metadata', $method);
		}elseif(str_ends_with($method, '_by_mid')){
			array_unshift($args, $this->name);

			$callback	= str_replace('_by_mid', '_metadata_by_mid', $method);
		}elseif(str_ends_with($method, '_meta')){
			$callback	= [$this, str_replace('_meta', '_data', $method)];
		}elseif(str_contains($method, '_meta')){
			$callback	= [$this, str_replace('_meta', '', $method)];
		}else{
			return;
		}

		if($callback){
			return call_user_func_array($callback, $args);
		}else{
			trigger_error('无效的方法'.$method);
		}
	}

	public function register_lazyloader(){
		return wpjam_register_lazyloader($this->name.'_meta', [
			'filter'	=> 'get_'.$this->name.'_metadata',
			'callback'	=> [$this, 'update_cache']
		]);
	}

	public function lazyload_data($ids){
		wpjam_lazyload($this->name.'_meta', $ids);
	}

	public function get_options($args=[]){
		$args		= array_merge($args, ['meta_type'=>$this->name]);
		$objects	= [];

		foreach(WPJAM_Meta_Option::get_by($args) as $option){
			$objects[$option->name]	= $option;
		}

		return $objects;
	}

	public function get_table(){
		return _get_meta_table($this->name);
	}

	public function get_column($name='object'){
		if($name == 'object'){
			return $this->name.'_id';
		}elseif($name == 'id'){
			return 'user' == $this->name ? 'umeta_id' : 'meta_id';
		}
	}

	public function get_data_with_default($id, ...$args){
		if(!$args){
			return $this->get_data($id);
		}

		if(is_array($args[0])){
			$data	= [];

			if($id && $args[0]){
				foreach($this->parse_defaults($args[0]) as $key => $default){
					$data[$key]	= $this->get_data_with_default($id, $key, $default);
				}
			}

			return $data;
		}else{
			if($id && $args[0]){
				if($args[0] == 'meta_input'){
					$data	= $this->get_data($id);

					foreach($data as $key => &$value){
						$value	= maybe_unserialize($value[0]);
					}

					return $data;
				}

				if($this->data_exists($id, $args[0])){
					return $this->get_data($id, $args[0], true);
				}
			}

			return $args[1] ?? null;
		}
	}

	public function get_by_key(...$args){
		global $wpdb;

		if(empty($args)){
			return [];
		}

		if(is_array($args[0])){
			$key	= $args[0]['meta_key'] ?? ($args[0]['key'] ?? '');
			$value	= $args[0]['meta_value'] ?? ($args[0]['value'] ?? '');
		}else{
			$key	= $args[0];
			$value	= $args[1] ?? null;
		}

		$where	= [];

		if($key){
			$where[]	= $wpdb->prepare('meta_key=%s', $key);
		}

		if(!is_null($value)){
			$where[]	= $wpdb->prepare('meta_value=%s', maybe_serialize($value));
		}

		if(!$where){
			return [];
		}

		$where	= implode(' AND ', $where);
		$table	= $this->get_table();
		$data	= $wpdb->get_results("SELECT * FROM {$table} WHERE {$where}", ARRAY_A) ?: [];

		foreach($data as &$item){
			$item['meta_value']	= maybe_unserialize($item['meta_value']);
		}

		return $data;
	}

	public function update_data_with_default($id, ...$args){
		if(is_array($args[0])){
			$data	= $args[0];

			if(wpjam_is_assoc_array($data)){
				if((isset($args[1]) && is_array($args[1]))){
					$defaults	= $this->parse_defaults($args[1]);
				}else{
					$defaults	= array_fill_keys(array_keys($data), null);
				}

				if(isset($data['meta_input']) && wpjam_is_assoc_array($data['meta_input'])){
					$this->update_data_with_default($id, array_pull($data, 'meta_input'), array_pull($defaults, 'meta_input'));
				}

				foreach($data as $key => $value){
					$this->update_data_with_default($id, $key, $value, array_pull($defaults, $key));
				}
			}

			return true;
		}else{
			$key		= $args[0];
			$value		= $args[1];
			$default	= $args[2] ?? null;

			if(is_array($value)){
				if($value && (!is_array($default) || array_diff_assoc($default, $value))){
					return $this->update_data($id, $key, $value);
				}
			}else{
				if(isset($value) && ((is_null($default) && $value) || (!is_null($default) && $value != $default))){
					return $this->update_data($id, $key, $value);
				}
			}

			return $this->delete_data($id, $key);
		}
	}

	public function cleanup(){
		if($this->object_key){
			$object_key		= $this->object_key;
			$object_table	= $GLOBALS['wpdb']->{$this->name.'s'};
		}else{
			$object_model	= $this->object_model;

			if($object_model && is_callable([$object_model, 'get_table'])){
				$object_table	= call_user_func([$object_model, 'get_table']);
				$object_key		= call_user_func([$object_model, 'get_primary_key']);
			}else{
				$object_table	= '';
				$object_key		= '';
			}
		}

		$this->delete_orphan_data($object_table, $object_key);
	}

	public function delete_orphan_data($object_table=null, $object_key=null){
		if($object_table && $object_key){
			$wpdb	= $GLOBALS['wpdb'];
			$mids	= $wpdb->get_col("SELECT m.".$this->get_column('id')." FROM ".$this->get_table()." m LEFT JOIN ".$object_table." t ON t.".$object_key." = m.".$this->get_column('object')." WHERE t.".$object_key." IS NULL") ?: [];

			foreach($mids as $mid){
				$this->delete_by_mid($mid);
			}
		}
	}

	public function delete_empty_data(){
		$wpdb	= $GLOBALS['wpdb'];
		$mids	= $wpdb->get_col("SELECT ".$this->get_column('id')." FROM ".$this->get_table()." WHERE meta_value = ''");

		foreach($mids as $mid){
			$this->delete_by_mid($mid);
		}
	}

	public function delete_by_key($key, $value=''){
		return delete_metadata($this->name, null, $key, $value, true);
	}

	public function delete_by_id($id){
		$wpdb	= $GLOBALS['wpdb'];
		$table	= $this->get_table();
		$column	= $this->get_column();
		$mids	= $wpdb->get_col($wpdb->prepare("SELECT meta_id FROM {$table} WHERE {$column} = %d ", $id));
		
		foreach($mids as $mid){
			$this->delete_by_mid($mid);
		}
	}

	public function update_cache($ids){
		if($ids){
			update_meta_cache($this->name, $ids);
		}
	}

	public function create_table(){
		$table	= $this->get_table();

		if($GLOBALS['wpdb']->get_var("show tables like '{$table}'") != $table){
			$column	= $this->name.'_id';

			$GLOBALS['wpdb']->query("CREATE TABLE {$table} (
				meta_id bigint(20) unsigned NOT NULL auto_increment,
				{$column} bigint(20) unsigned NOT NULL default '0',
				meta_key varchar(255) default NULL,
				meta_value longtext,
				PRIMARY KEY  (meta_id),
				KEY {$column} ({$column}),
				KEY meta_key (meta_key(191))
			)");
		}
	}

	public static function parse_defaults($defaults){
		$return	= [];

		foreach($defaults as $key => $default){
			if(is_numeric($key)){
				if(is_numeric($default)){
					continue;
				}

				$key		= $default;
				$default	= null;
			}

			$return[$key]	= $default;
		}

		return $return;
	}

	public static function get_config($key){
		if($key == 'orderby'){
			return true;
		}elseif($key == 'defaults'){
			$defaults	= [
				'post'	=> ['order'=>50,	'object_model'=>'WPJAM_Post',	'object_column'=>'title',		'object_key'=>'ID'],
				'term'	=> ['order'=>40,	'object_model'=>'WPJAM_Term',	'object_column'=>'name',		'object_key'=>'term_id'],
				'user'	=> ['order'=>30,	'object_model'=>'WPJAM_User',	'object_column'=>'display_name','object_key'=>'ID'],
			];

			if(is_multisite()){
				$defaults['blog']	= ['order'=>5,	'object_key'=>'blog_id'];
				$defaults['site']	= ['order'=>5];
			}

			return $defaults;
		}
	}
}

class WPJAM_Meta_Option extends WPJAM_Register{
	public function __call($method, $args){
		if(str_ends_with($method, '_by_fields')){
			$id		= array_shift($args);
			$fields	= $this->get_fields($id);
			$object	= wpjam_fields($fields);
			$method	= wpjam_remove_postfix($method, '_by_fields');

			return call_user_func_array([$object, $method], $args);
		}
	}

	public function __get($key){
		$value	= parent::__get($key);

		if($key == 'list_table'){
			if(is_null($value) && did_action('current_screen') && !empty($GLOBALS['plugin_page'])){
				return true;
			}
		}elseif($key == 'callback'){
			if(!$value){
				return $this->update_callback;
			}
		}

		return $value;
	}

	public function get_fields($id=null){
		$fields	= $this->fields;

		return is_callable($fields) ? call_user_func($fields, $id, $this->name) : $fields;
	}

	public function parse_list_table_args(){
		return wp_parse_args($this->get_args(), [
			'page_title'	=> '设置'.$this->title,
			'submit_text'	=> '设置',
			'meta_type'		=> $this->name,
			'fields'		=> [$this, 'get_fields']
		]);
	}

	public function prepare($id=null){
		if($this->callback){
			return [];
		}

		return $this->prepare_by_fields($id, array_merge($this->get_args(), ['id'=>$id]));
	}

	public function validate($id=null, $data=null){
		return $this->validate_by_fields($id, $data);
	}

	public function render($id, $args=[]){
		echo $this->render_by_fields($id, array_merge($this->get_args(), ['id'=>$id], $args));
	}

	public function callback($id, $data=null){
		$fields	= $this->get_fields($id);
		$object	= wpjam_fields($fields);
		$data	= $object->validate($data);

		if(is_wp_error($data)){
			return $data;
		}elseif(!$data){
			return true;
		}

		if($this->callback){
			$result	= is_callable($this->callback) ? call_user_func($this->callback, $id, $data, $fields) : false;

			return $result === false ? new WP_Error('invalid_callback') : $result;
		}else{
			return wpjam_update_metadata($this->meta_type, $id, $data, $object->get_defaults());
		}
	}

	public function list_table($value=null){
		if($this->title){
			if($value){
				return (bool)$this->list_table;
			}else{
				return $this->list_table !== 'only';
			}
		}

		return false;
	}

	public static function create($name, $args){
		$meta_type	= array_get($args, 'meta_type');

		if($meta_type){
			$object	= new self($name, $args);

			return self::register($meta_type.':'.$name, $object);
		}
	}

	public static function get_by(...$args){
		$args		= is_array($args[0]) ? $args[0] : [$args[0] => $args[1]];
		$list_table	= array_pull($args, 'list_table');
		$meta_type	= array_get($args, 'meta_type');

		if(!$meta_type){
			return [];
		}

		if(isset($list_table)){
			$list_table_key	= 'list_table:'.(int)$list_table;

			$args[$list_table_key]	= function($value){
				$parts	= explode(':', $value);
				$value	= $parts[1] ?? null;

				return $this->list_table($value);
			};
		}

		if($meta_type == 'post'){
			$post_type	= array_pull($args, 'post_type');

			if($post_type){
				$object	= wpjam_get_post_type_object($post_type);

				if($object){
					$object->register_option($list_table);
				}

				$args['post_type']	= 'match:'.$post_type;
			}
		}elseif($meta_type == 'term'){
			$taxonomy	= array_pull($args, 'taxonomy');
			$action		= array_pull($args, 'action');

			if($taxonomy){
				$object	= wpjam_get_taxonomy_object($taxonomy);

				if($object){
					$object->register_option($list_table);
				}

				$args['taxonomy']	= 'match:'.$taxonomy;
			}

			if($action){
				$args['action']		= 'match:'.$action;
			}
		}

		return static::get_registereds($args);
	}

	public static function get_config($key){
		if($key == 'orderby'){
			return 'order';
		}
	}
}

class WPJAM_Lazyloader extends WPJAM_Register{
	private $pending_objects	= [];

	public function callback($check){
		if($this->pending_objects){
			if($this->accepted_args && $this->accepted_args > 1){
				foreach($this->pending_objects as $object){
					call_user_func($this->callback, $object['ids'], ...$object['args']);
				}
			}else{
				call_user_func($this->callback, $this->pending_objects);
			}

			$this->pending_objects	= [];
		}

		remove_filter($this->filter, [$this, 'callback']);

		return $check;
	}

	public function queue_objects($object_ids, ...$args){
		if(!$object_ids){
			return;
		}

		if($this->accepted_args && $this->accepted_args > 1){
			if((count($args)+1) >= $this->accepted_args){
				$key	= wpjam_json_encode($args);

				if(!isset($this->pending_objects[$key])){
					$this->pending_objects[$key]	= ['ids'=>[], 'args'=>$args];
				}

				$this->pending_objects[$key]['ids']	= array_merge($this->pending_objects[$key]['ids'], $object_ids);
				$this->pending_objects[$key]['ids']	= array_unique($this->pending_objects[$key]['ids']);
			}
		}else{
			$this->pending_objects	= array_merge($this->pending_objects, $object_ids);
			$this->pending_objects	= array_unique($this->pending_objects);
		}

		add_filter($this->filter, [$this, 'callback']);
	}
}

class WPJAM_AJAX extends WPJAM_Register{
	public function __construct($name, $args=[]){
		parent::__construct($name, $args);

		add_action('wp_ajax_'.$name, [$this, 'callback']);

		if(!empty($args['nopriv'])){
			add_action('wp_ajax_nopriv_'.$name, [$this, 'callback']);
		}
	}

	public function callback(){
		if(!$this->callback || !is_callable($this->callback)){
			wp_die('0', 400);
		}

		$data	= wpjam_get_data_parameter();
		
		if($this->verify !== false){
			if(!check_ajax_referer($this->get_nonce_action($data, 'verify'), false, false)){
				wpjam_send_error_json('invalid_nonce');
			}
		}

		$result	= wpjam_call($this->callback, $data, $this->name);
		$result	= $result === true ? [] : $result;  

		wpjam_send_json($result);
	}

	public function get_attr($data=[], $return=null){
		$attr	= ['action'=>$this->name, 'data'=>$data];

		if($this->verify !== false){
			$attr['nonce']	= wp_create_nonce($this->get_nonce_action($data, 'create'));
		}

		return $return ? $attr : wpjam_attr($attr, 'data');
	}

	protected function get_nonce_action($data, $type='create'){
		$nonce_action	= $this->name;

		if($this->nonce_keys){
			foreach($this->nonce_keys as $key){
				$value	= $data[$key] ?? '';

				if($value){
					$nonce_action	.= ':'.$value;
				}
			}
		}

		return $nonce_action;
	}

	public static function on_enqueue_scripts(){
		wp_register_style('remixicon',		'https://cdn.staticfile.org/remixicon/3.4.0/remixicon.min.css');
		wp_register_script('wpjam-ajax',	WPJAM_BASIC_PLUGIN_URL.'static/ajax.js', ['jquery']);

		$scripts	= '
		if(typeof ajaxurl == "undefined"){
			var ajaxurl	= "'.admin_url('admin-ajax.php').'";
		}';

		wp_add_inline_script('wpjam-ajax', str_replace("\n\t\t", "\n", $scripts), 'before');
	}
}

class WPJAM_Verification_Code extends WPJAM_Register{
	public function is_over($key){
		if($this->failed_times && (int)$this->cache->get($key.':failed_times') > $this->failed_times){
			return new WP_Error('quota_exceeded', ['尝试的失败次数', '请15分钟后重试。']);
		}

		return false;
	}

	public function generate($key){
		if($over = $this->is_over($key)){
			return $over;
		}

		if($this->interval && $this->cache->get($key.':time') !== false){
			return new WP_Error('error', '验证码'.((int)($this->interval/60)).'分钟前已发送了。');
		}

		$code = rand(100000, 999999);

		$this->cache->set($key.':code', $code, $this->cache_time);

		if($this->interval){
			$this->cache->set($key.':time', time(), MINUTE_IN_SECONDS);
		}

		return $code;
	}

	public function verify($key, $code){
		if($over = $this->is_over($key)){
			return $over;
		}

		$current	= $this->cache->get($key.':code');

		if(!$code || $current === false){
			return new WP_Error('invalid_code');
		}

		if($code != $current){
			if($this->failed_times){
				$failed_times	= $this->cache->get($key.':failed_times') ?: 0;
				$failed_times	= $failed_times + 1;

				$this->cache->set($key.':failed_times', $failed_times, $this->cache_time/2);
			}

			return new WP_Error('invalid_code');
		}

		return true;
	}

	protected static function preprocess_args($args, $name){
		$args	= parent::preprocess_args($args, $name);

		return wp_parse_args($args, [
			'failed_times'	=> 5,
			'cache_time'	=> MINUTE_IN_SECONDS*30,
			'interval'		=> MINUTE_IN_SECONDS,
			'cache'			=> wpjam_cache('verification_code', ['global'=>true, 'prefix'=>$name]),
		]);
	}

	public static function get_instance($name, $args=[]){
		return self::get($name) ?: self::register($name, $args);
	}
}

class WPJAM_Verify_TXT extends WPJAM_Register{
	public function get_fields(){
		return [
			'name'	=>['title'=>'文件名称',	'type'=>'text',	'required', 'value'=>$this->get_data('name'),	'class'=>'all-options'],
			'value'	=>['title'=>'文件内容',	'type'=>'text',	'required', 'value'=>$this->get_data('value')]
		];
	}

	public function get_data($key=''){
		$data	= wpjam_get_setting('wpjam_verify_txts', $this->name) ?: [];

		return $key ? ($data[$key] ?? '') : $data;
	}

	public function set_data($data){
		return wpjam_update_setting('wpjam_verify_txts', $this->name, $data) || true;
	}

	public static function __callStatic($method, $args){	// 放弃
		$name	= $args[0];

		if($object = self::get($name)){
			if(in_array($method, ['get_name', 'get_value'])){
				return $object->get_data(str_replace('get_', '', $method));
			}elseif($method == 'set' || $method == 'set_value'){
				return $object->set_data(['name'=>$args[1], 'value'=>$args[2]]);
			}
		}
	}

	public static function filter_root_rewrite_rules($root_rewrite){
		if(empty($GLOBALS['wp_rewrite']->root)){
			$home_path	= parse_url(home_url());

			if(empty($home_path['path']) || '/' == $home_path['path']){
				$root_rewrite	= array_merge(['([^/]+)\.txt?$'=>'index.php?module=txt&action=$matches[1]'], $root_rewrite);
			}
		}

		return $root_rewrite;
	}

	public static function get_rewrite_rule(){
		add_filter('root_rewrite_rules',	[self::class, 'filter_root_rewrite_rules']);
	}

	public static function redirect($action){
		$txts = wpjam_get_option('wpjam_verify_txts');

		if($txts){
			$name	= str_replace('.txt', '', $action).'.txt';

			foreach($txts as $txt) {
				if($txt['name'] == $name){
					header('Content-Type: text/plain');
					echo $txt['value'];

					exit;
				}
			}
		}
	}
}