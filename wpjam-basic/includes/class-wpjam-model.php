<?php
trait WPJAM_Instance_Trait{
	use WPJAM_Call_Trait;

	protected static $_instances	= [];

	protected static function call_instance($action, $name=null, $instance=null){
		$group	= wpjam_remove_prefix(strtolower(get_called_class()), 'wpjam_');

		if($action == 'get_all'){
			return self::$_instances[$group] ?? [];
		}elseif($action == 'get'){
			return self::$_instances[$group][$name] ?? null;
		}elseif($action == 'add'){
			self::$_instances[$group][$name]	= $instance;

			return $instance;
		}elseif($action == 'remove'){
			unset(self::$_instances[$group][$name]);
		}
	}

	protected static function get_instances(){
		return self::call_instance('get_all');
	}

	public static function instance_exists($name){
		return self::call_instance('get', $name) ?: false;
	}

	protected static function create_instance(...$args){
		return new static(...$args);
	}

	public static function add_instance($name, $instance){
		return self::call_instance('add', $name, $instance);
	}

	public static function remove_instance($name){
		self::call_instance('remove', $name);
	}

	public static function instance(...$args){
		$name	= $args ? implode(':', array_filter($args, 'is_exists')) : 'singleton';

		return self::instance_exists($name) ?: self::add_instance($name, static::create_instance(...$args));
	}
}

abstract class WPJAM_Model implements ArrayAccess, IteratorAggregate{
	use WPJAM_Instance_Trait;

	protected $_id;
	protected $_data	= []; 

	public function __construct($data=[], $id=null){
		if($id){
			$this->_id	= $id;

			if($data){
				$raw			= static::get($this->id);
				$this->_data	= array_diff_assoc($data, $raw);
			}
		}else{
			$this->_data	= $data;

			$key	= static::get_primary_key();

			if(isset($data[$key])){
				$raw	= static::get($data[$key]);

				if($raw){
					$this->_id		= $data[$key];
					$this->_data	= array_diff_assoc($data, $raw);
				}
			}
		}
	}

	public function __get($key){
		$data	= $this->get_data();

		if(array_key_exists($key, $data)){
			return $data[$key];
		}

		$meta_type	= self::get_meta_type();

		return $meta_type ? wpjam_get_metadata($meta_type, $this->_id, $key) : null;
	}

	public function __isset($key){
		if(array_key_exists($key, $this->_data)){
			return !is_null($this->_data[$key]);
		}

		$data	= $this->get_data();

		if(array_key_exists($key, $data)){
			return true;
		}

		$meta_type	= self::get_meta_type();

		return $meta_type ? metadata_exists($meta_type, $this->_id, $key) : false;
	}

	public function __set($key, $value){
		$this->set_data($key, $value);
	}

	public function __unset($key){
		$this->unset_data($key);
	}

	#[ReturnTypeWillChange]
	public function offsetExists($key){
		$data	= $this->get_data();

		return array_key_exists($key, $data);
	}

	#[ReturnTypeWillChange]
	public function offsetGet($key){
		return $this->get_data($key);
	}

	#[ReturnTypeWillChange]
	public function offsetSet($key, $value){
		$this->set_data($key, $value);
	}

	#[ReturnTypeWillChange]
	public function offsetUnset($key){
		$this->unset_data($key);
	}

	#[ReturnTypeWillChange]
	public function getIterator(){
		return new ArrayIterator($this->get_data());
	}

	public function get_primary_id(){
		$key	= static::get_primary_key();

		return $this->get_data($key);
	}

	public function get_data($key=''){
		$data	= is_null($this->_id) ? [] : static::get($this->_id);
		$data	= array_merge($data, $this->_data);

		if($key){
			return $data[$key] ?? null;
		}

		return $data;
	}

	public function set_data($key, $value){
		if(!is_null($this->_id) && self::get_primary_key() == $key){
			trigger_error('不能修改主键的值');
		}else{
			$this->_data[$key]	= $value;
		}

		return $this;
	}

	public function unset_data($key){
		$this->_data[$key]	= null;
	}

	public function reset_data($key=''){
		if($key){
			unset($this->_data[$key]);
		}else{
			$this->_data	= [];
		}
	}

	public function to_array(){
		return $this->get_data();
	}

	public function save($data=[]){
		$meta_type	= self::get_meta_type();
		$meta_input	= $meta_type ? array_pull($data, 'meta_input') : null;

		$data	= array_merge($this->_data, $data);

		if($this->_id){
			$data	= array_except($data, static::get_primary_key());
			$result	= $data ? static::update($this->_id, $data) : false;
		}else{
			$result	= static::insert($data);

			if(!is_wp_error($result)){
				$this->_id	= $result;
			}
		}

		if(!is_wp_error($result)){
			if($this->_id && $meta_input){
				$this->meta_input($meta_input);
			}

			$this->reset_data();
		}

		return $result;
	}

	public function meta_input(...$args){
		if($args && $this->_id){
			$meta_type	= self::get_meta_type();

			return $meta_type ? wpjam_update_metadata($meta_type, $this->_id, ...$args) : null;
		}
	}

	public static function find($id){
		return static::get_instance($id);
	}

	public static function get_instance($id){
		$object	= self::instance_exists($id);

		if(!$object){
			$data	= $id ? static::get($id) : null;
			$object	= $data ? static::add_instance($id, new static([], $id)) : null;
		}

		return $object;
	}

	protected static function get_handler_name(){
		return wpjam_remove_prefix(strtolower(get_called_class()), 'wpjam_');
	}

	public static function get_handler(){
		$handler	= wpjam_get_handler(self::get_handler_name());

		if(!$handler && property_exists(get_called_class(), 'handler')){
			return static::$handler;
		}

		return $handler;
	}

	public static function set_handler($handler){
		wpjam_register_handler(self::get_handler_name(), $handler);
	}

	public static function get_primary_key(){
		return static::call_handler('get_primary_key');
	}

	protected static function validate_data($data, $id=0){
		return true;
	}

	protected static function sanitize_data($data, $id=0){
		return $data;
	}

	public static function insert($data){
		$result	= static::validate_data($data);

		if(is_wp_error($result)){
			return $result;
		}

		$data	= static::sanitize_data($data);

		return static::call_handler('insert', $data);
	}

	public static function update($id, $data){
		$result	= static::validate_data($data, $id);

		if(is_wp_error($result)){
			return $result;
		}

		$data	= static::sanitize_data($data, $id);

		return static::call_handler('update', $id, $data);
	}

	public static function get($id){
		return static::call_handler('get', $id);
	}

	public static function delete($id){
		return static::call_handler('delete', $id);
	}

	public static function delete_multi($ids){
		if(static::method_exists('delete_multi')){
			return static::call_handler('delete_multi', $ids);
		}

		foreach($ids as $id){
			$result	= static::call_handler('delete', $id);

			if(is_wp_error($result)){
				return $result;
			}
		}

		return true;
	}

	public static function insert_multi($datas){
		foreach($datas as &$data){
			$result	= static::validate_data($data);

			if(is_wp_error($result)){
				return $result;
			}

			$data	= static::sanitize_data($data);
		}

		if(static::method_exists('insert_multi')){
			return static::call_handler('insert_multi', $datas);
		}

		foreach($datas as $data){
			$result	= static::call_handler('insert', $data);

			if(is_wp_error($result)){
				return $result;
			}
		}

		return true;
	}

	public static function get_actions(){
		return [
			'add'		=> ['title'=>'新建',	'dismiss'=>true],
			'edit'		=> ['title'=>'编辑'],
			'delete'	=> ['title'=>'删除',	'direct'=>true, 'confirm'=>true,	'bulk'=>true],
		];
	}

	protected static function method_exists($method){
		$handler	= static::get_handler();

		return $handler && method_exists($handler, $method);
	}

	// get_by($field, $value, $order='ASC')
	// get_by_ids($ids)
	// get_searchable_fields()
	// get_filterable_fields()
	// update_caches($values)
	// move($id, $data)

	// get_cache_key($key)
	// get_last_changed
	// get_cache_group
	// cache_get($key)
	// cache_set($key, $data, $cache_time=DAY_IN_SECONDS)
	// cache_add($key, $data, $cache_time=DAY_IN_SECONDS)
	// cache_delete($key)
	public static function call_handler($method, ...$args){
		if(in_array($method, ['item_callback', 'render_item', 'parse_item', 'render_date'])){
			return $args[0];
		}

		$handler	= static::get_handler();

		if(!$handler){
			return new WP_Error('undefined_handler');
		}

		if(is_a($handler, 'WPJAM_DB')){
			if($method == 'get_one_by'){
				$items	= $handler->get_by(...$args);

				return $items ? current($items) : [];
			}elseif(in_array($method, ['query_items', 'query_data'])){
				if(is_array($args[0])){
					$query	= $handler->query($args[0], 'object');

					return ['items'=>$query->items, 'total'=>$query->total];
				}else{
					return $handler->query_items(...$args);
				}
			}elseif(strtolower($method) == 'query'){
				if($args){
					return $handler->query($args[0], 'object');
				}else{
					return $handler;
				}
			}
		}

		$map	= [
			'list'		=> 'query_items',
			'get_ids'	=> 'get_by_ids',
			'get_all'	=> 'get_results'
		];

		$method	= $map[$method] ?? $method;

		if(method_exists($handler, $method) || method_exists($handler, '__call')){
			// WPJAM_DB 可能因为 cache 设置为 false
			// 不能直接调用 WPJAM_DB 的 cache_xxx 方法
			if(in_array($method, ['cache_get', 'cache_set', 'cache_add', 'cache_delete'])){
				$method	.= '_force';
			}

			return call_user_func_array([$handler, $method], $args);
		}

		return new WP_Error('undefined_method', [$method]);
	}

	public static function __callStatic($method, $args){
		return static::call_handler($method, ...$args);
	}
}

abstract class WPJAM_Items_Model extends WPJAM_Model{
	public static function get_handler(){
		$name		= self::get_handler_name();
		$handler	= wpjam_get_handler($name);

		if(!$handler){
			$args	= wp_parse_args(static::get_items_args(), [
				'get_items'		=> [get_called_class(), 'get_items'],
				'update_items'	=> [get_called_class(), 'update_items'],
				'delete_items'	=> [get_called_class(), 'delete_items'],
			]);

			$handler	= wpjam_register_handler($name, new WPJAM_Items($args));
		}

		return $handler;
	}

	abstract protected static function get_items_args();
	abstract public static function get_items();
	abstract public static function update_items($items);
	abstract public static function delete_items();
}

class WPJAM_Items extends WPJAM_Args{
	use WPJAM_Instance_Trait;

	public function __construct($args=[]){
		$this->args = wp_parse_args($args, [
			'item_type'		=> 'array',
			'primary_key'	=> 'id',
			'primary_title'	=> 'ID'
		]);

		if($this->item_type != 'array'){
			$this->primary_key	= null;
		}
	}

	public function __call($method, $args){
		if(in_array($method, [
			'insert',
			'add',
			'update',
			'replace',
			'set',
			'delete',
			'remove',
			'empty',
			'move',
			'increment',
			'decrement'
		])){
			$retry	= $this->retry_times ?: 1;

			if($method == 'decrement'){
				$method		= 'increment';
				$args[1]	= 0 - ($args[1] ?? 1);
			}elseif($method == 'replace'){
				$method		= 'update';
			}elseif($method == 'remove'){
				$method		= 'delete';
			}

			try{
				do{
					$result	= call_user_func_array([$this, '_'.$method], $args);
					$retry	-= 1;
				}while($result === false && $retry > 0);

				return $result;
			}catch(WPJAM_Exception $e){
				return $e->get_wp_error();
			}
		}elseif(in_array($method, [
			'get_primary_key',
			'get_searchable_fields',
			'get_filterable_fields'
		])){
			return $this->{substr($method, 4)};
		}
	}

	public function call_method($method, ...$args){
		if($this->$method && is_callable($this->$method)){
			return call_user_func_array($this->$method, $args);
		}
	}

	protected function exception($code, $msg, $type=''){
		if($type){
			$code	.= '_'.$this->{$type.'_key'};
			$msg	= $this->{$type.'_title'}.$msg;
		}

		throw new WPJAM_Exception($msg, $code);
	}

	public function get_items(){
		$result	= $this->call_method('get_items', $this);

		return $result ?? [];
	}

	public function update_items($items){
		$result	= $this->call_method('update_items', $items, $this);

		return $result ?? true;
	}

	public function delete_items(){
		$result	= $this->call_method('delete_items', $this);

		return $result ?? true;
	}

	public function query_items($args){
		$items	= $this->parse_items();

		return ['items'=>$items, 'total'=>count($items)];
	}

	public function parse_items($items=null){
		$items	= $items ?? $this->get_items();

		if($items && is_array($items)){
			foreach($items as $id => &$item){
				$item	= $this->parse_item($item, $id);
			}

			return $items;
		}

		return [];
	}

	public function parse_item($item, $id){
		if($this->item_type == 'array'){
			$item	= is_array($item) ? $item : [];

			return array_merge($item, [$this->primary_key => $id]);
		}

		return $item;
	}

	protected function sanitizes($items){
		if($this->item_type == 'array' && in_array($this->primary_key, ['option_key','id']) && ($items && is_array($items))){
			foreach($items as $id => &$item){
				$item	= array_except($item, $this->primary_key);

				if($this->parent_key){
					$item	= array_except($item, $this->parent_key);
				}
			}
		}

		return $items;
	}

	public function get_results(){
		return $this->parse_items();
	}

	public function reset(){
		return $this->delete_items();
	}

	public function exists($value, $type='unique'){
		$items	= $this->get_items();

		if($items){
			if($this->item_type == 'array'){
				if($type == 'unique'){
					return in_array($value, array_column($items, $this->unique_key));
				}else{
					return isset($items[$value]);
				}
			}else{
				return in_array($value, $items);
			}
		}

		return false;
	}

	public function get($id){
		$items	= $this->get_items();
		$item	= $items[$id] ?? false;

		return $item ? $this->parse_item($item, $id) : false;
	}

	protected function validate($item=null, $id=null, $action=null){
		$items	= $this->get_items();
		$action	= $action ?? (isset($id) ? '' : 'add');

		if(isset($id)){
			if(isset($items[$id])){
				if($action == 'add'){
					$this->exception('duplicate', '「'.$id.'」已存在', 'primary');
				}
			}else{
				if($action == ''){
					$this->exception('invalid', '为「'.$id.'」的数据的不存在', 'primary');
				}else{
					$action == 'add';	// set => add
				}
			}

			if(!isset($item)){
				return true;
			}
		}

		if($action == 'add' && $this->max_items && count($items) >= $this->max_items){
			$this->exception('over_max_items', '最大允许数量：'.$this->max_items);
		}

		if($this->item_type == 'array'){
			if(in_array($this->primary_key, ['option_key', 'id'])){
				if($this->unique_key){
					$value	= $item[$this->unique_key] ?? null;

					if(isset($id) && is_null($value)){
						return $item;
					}

					if(!$value){
						$this->exception('empty', '不能为空', 'unique');
					}

					foreach($items as $_id => $_item){
						if(isset($id) && $id == $_id){
							continue;
						}

						if($_item[$this->unique_key] == $value){
							$this->exception('duplicate', '值重复', 'unique');
						}
					}
				}
			}else{
				if(is_null($id)){
					$id	= $item[$this->primary_key] ?? null;

					if(!$id){
						$this->exception('empty', '不能为空', 'primary');
					}

					if(isset($items[$id])){
						$this->exception('duplicate', '值重复', 'primary');
					}
				}
			}
		}

		return true;
	}

	protected function sanitize($item, $id=null){
		if($this->item_type == 'array'){
			$item	= filter_deep($item, 'is_exists');

			if(isset($id)){
				$item[$this->primary_key] = $id;
			}
		}

		return $item;
	}

	protected function get_id($item){
		if(in_array($this->primary_key, ['option_key', 'id'])){
			$items	= $this->get_items();

			if($items){
				$ids	= array_keys($items);
				$ids	= array_map(function($id){return (int)(str_replace('option_key_', '', $id)); }, $ids);
				$id		= max($ids);
				$id		= $id+1;
			}else{
				$id		= 1;
			}

			if($this->primary_key == 'option_key'){
				$id		= 'option_key_'.$id;
			}

			return $id;
		}

		return $item[$this->primary_key];
	}

	protected function _insert($item){
		$item	= $this->sanitize($item);
		$result	= $this->validate($item);
		$items	= $this->get_items();

		if($this->item_type == 'array'){
			$id	= $this->get_id($item);

			if($this->last){
				$items[$id]	= $item;
			}else{
				$items		= [$id=>$item]+$items;
			}
		}else{
			if($this->last){
				$items[]	= $item;
			}else{
				array_unshift($items, $item);
			}
		}

		$items	= $this->sanitizes($items);
		$result	= $this->update_items($items);

		if($this->item_type == 'array'){
			return ['id'=>$id,	'last'=>(bool)$this->last];
		}else{
			return $result;
		}
	}

	protected function _add(...$args){
		if($this->item_type == 'array'){
			return;
		}

		$items	= $this->get_items();

		if(count($args) >= 2){
			$id		= $args[0];
			$item	= $args[1];
		}else{
			$id	= null;
			$item	= $args[0];
		}

		$result	= $this->validate($item, $id, 'add');
		$item	= $this->sanitize($item, $id);

		if(isset($id)){
			$items[$id]	= $item;
		}else{
			$items[]	= $item;
		}

		return $this->update_items($items);
	}

	protected function _update($id, $item){
		$result	= $this->validate($item, $id);
		$items	= $this->get_items();

		if($this->item_type == 'array'){
			$item	= wp_parse_args($item, $items[$id]);
		}

		$items[$id]	= $this->sanitize($item, $id);
		$items		= $this->sanitizes($items);

		return $this->update_items($items);
	}

	protected function _set($id, $item){
		$result		= $this->validate($item, $id, 'set');
		$items		= $this->get_items();
		$items[$id] = $this->sanitize($item, $id);
		$items		= $this->sanitizes($items);

		return $this->update_items($items);
	}

	protected function _empty(){
		$items	= $this->get_items();

		if($items == []){
			return $items;
		}

		$result = $this->update_items([]);

		return $result ? $items : $result;
	}

	protected function _delete($id){
		$result	= $this->validate(null, $id);
		$items	= $this->get_items();
		$items	= array_except($items, $id);

		return $this->update_items($items);
	}

	protected function _move($id, $data){
		$result	= $this->validate(null, $id);
		$next	= $data['next'] ?? false;
		$prev	= $data['prev'] ?? false;

		if(!$next && !$prev){
			$this->exception('error', '无效移动位置');
		}

		$pos	= $next ?: $prev;
		$result	= $this->validate(null, $pos);
		$items	= $this->get_items();
		$item	= array_pull($items, $id);
		$offset	= array_search($pos, array_keys($items));

		if($prev && !$next){
			$offset++;
		}

		if($offset){
			$items	= array_slice($items, 0, $offset, true) +  [$id => $item] + array_slice($items, $offset, null, true);
		}else{
			$items	= [$id => $item] + $items;
		}

		return $this->update_items($items);
	}

	protected function _increment($id, $offset=1){
		if($this->item_type == 'array'){
			return;
		}

		$items	= $this->get_items();

		if(isset($items[$id])){
			$item	= (int)$items[$id] + $offset;
		}else{
			$item	= $offset;
		}

		$items[$id] = $item;
		$result 	= $this->update_items($items);

		return $result ? $item : $result;
	}
}

class WPJAM_Option_Items extends WPJAM_Items{
	public function __construct($option_name, $args=[]){
		$args	= is_array($args) ? wp_parse_args($args, ['primary_key'=>'option_key']) : ['primary_key'=>$args];

		parent::__construct(array_merge($args, ['option_name'=>$option_name]));
	}

	public function get_items(){
		return get_option($this->option_name) ?: [];
	}

	public function update_items($items){
		return update_option($this->option_name, $items);
	}

	public function delete_items(){
		return delete_option($this->option_name);
	}

	public static function get_instance(){
		$r	= new ReflectionMethod(get_called_class(), '__construct');

		return $r->getNumberOfParameters() ? null : static::instance();
	}
}

class WPJAM_Meta_Items extends WPJAM_Items{
	public function __construct($meta_type, $object_id, $meta_key, $args=[]){
		parent::__construct(array_merge($args, [
			'meta_type'		=> $meta_type,
			'object_id'		=> $object_id,
			'meta_key'		=> $meta_key,
			'parent_key'	=> $meta_type.'_id',
		]));
	}

	public function get_items(){
		return get_metadata($this->meta_type, $this->object_id, $this->meta_key, true) ?: [];
	}

	public function update_items($items){
		return update_metadata($this->meta_type, $this->object_id, $this->meta_key, $items);
	}

	public function delete_items(){
		return delete_metadata($this->meta_type, $this->object_id, $this->meta_key);
	}
}

class WPJAM_Content_Items extends WPJAM_Items{
	public function __construct($post_id, $args=[]){
		parent::__construct(array_merge($args, ['post_id'=>$post_id, 'parent_key'=>'post_id']));
	}

	public function get_items(){
		$_post	= get_post($this->post_id);

		return ($_post && $_post->post_content) ? maybe_unserialize($_post->post_content) : [];
	}

	public function update_items($items){
		$items	= $items ? maybe_serialize($items) : '';

		return WPJAM_Post::update($this->post_id, ['post_content'=>$items]);
	}

	public function delete_items(){
		return WPJAM_Post::update($this->post_id, ['post_content'=>'']);
	}
}

class WPJAM_Cache_Items extends WPJAM_Items{
	public function __construct($key, $args=[]){
		parent::__construct(wp_parse_args($args, [
			'item_type'		=> '',
			'retry_times'	=> 10,
			'key'			=> $key,
			'group'			=> 'list_cache',
		]));

		$this->cache	= is_object($this->group) ? $this->group : wpjam_cache($this->group, $this->get_args());
	}

	public function get_items(){
		$items	= $this->cache->get_with_cas($this->key, $token);

		if(!is_array($items)){
			$this->cache->set($this->key, []);

			$items	= $this->cache->get_with_cas($this->key, $token);
		}

		$this->cas_token	= $token;

		return $items;
	}

	public function update_items($items){
		return $this->cache->cas($this->cas_token, $this->key, $items);
	}

	public function delete_items(){
		$items	= $this->get_items();

		return $this->update_items([]);
	}
}