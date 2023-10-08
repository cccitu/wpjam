<?php
class WPJAM_DB extends WPJAM_Args{
	protected $meta_query	= null;
	protected $query_vars	= [];
	protected $where		= [];

	public function __construct($table, array $args=[]){
		$this->args	= wp_parse_args($args, [
			'table'				=> $table,
			'primary_key'		=> 'id',
			'meta_type'			=> '',
			'cache'				=> true,
			'cache_key'			=> '',
			'cache_prefix'		=> '',
			'cache_group'		=> $table,
			'cache_time'		=> DAY_IN_SECONDS,
			'group_cache_key'	=> [],
			'field_types'		=> [],
			'searchable_fields'	=> [],
			'filterable_fields'	=> []
		]);

		$this->group_cache_key	= (array)$this->group_cache_key;

		if($this->cache_key	== $this->primary_key){
			$this->cache_key	= '';
		}

		if(is_array($this->cache_group)){
			$group	= $this->cache_group[0];
			$global	= $this->cache_group[1] ?? false;

			if($global){
				wp_cache_add_global_groups($group);
			}

			$this->cache_group	= $group;
		}

		$this->clear();
	}

	public function __get($key){
		if(isset($this->query_vars[$key])){
			return $this->query_vars[$key];
		}

		return parent::__get($key);
	}

	public function __call($method, $args){
		if($method == 'get_operators'){
			return [
				'not'		=> '!=',
				'lt'		=> '<',
				'lte'		=> '<=',
				'gt'		=> '>',
				'gte'		=> '>=',
				'in'		=> 'IN',
				'not_in'	=> 'NOT IN',
				'like'		=> 'LIKE',
				'not_like'	=> 'NOT LIKE',
			];
		}elseif(str_starts_with($method, 'where_')){
			$type	= wpjam_remove_prefix($method, 'where_');

			if(in_array($type, ['any', 'all'])){
				$data		= $args[0];
				$output		= $args[1] ?? 'object';
				$fragment	= '';

				if($data && is_array($data)){
					$where	= [];

					foreach($data as $column => $value){
						$where[]	= $this->where($column, $value, 'value');
					}

					$type		= $type == 'any' ? 'OR' : 'AND';
					$fragment	= $this->parse_where($where, $type);
				}

				if($output != 'object'){
					return $fragment ?: '';
				}

				$type		= 'fragment';
				$args[0]	= $fragment;
			}

			if($type == 'fragment'){
				if($args[0]){
					$this->where[] = ['compare'=>'fragment', 'fragment'=>' ( '.$args[0].' ) '];
				}
			}elseif(isset($args[1])){
				$operators	= $this->get_operators();
				$compare	= $operators[$type] ?? '';

				if($compare){
					$this->where[]	= ['column'=>$args[0], 'value'=>$args[1], 'compare'=>$compare];
				}
			}

			return $this;
		}elseif(in_array($method, [
			'found_rows',
			'limit',
			'offset',
			'orderby',
			'order',
			'groupby',
			'having',
			'search',
			'order_by',
			'group_by',
		])){
			$map	= [
				'search'	=> 'search_term',
				'order_by'	=> 'orderby',
				'group_by'	=> 'groupby',
			];

			$key	= $map[$method] ?? $method;

			if($key == 'order'){
				$value	= $args[0] ?? 'DESC';
			}elseif($key == 'found_rows'){
				$value	= $args[0] ?? true;
				$value	= (bool)$value;
			}else{
				$value	= $args[0] ?? null;
			}

			if(!is_null($value)){
				if(in_array($key, ['limit', 'offset'])){
					$value	= (int)$value;
				}elseif($key == 'order'){
					$value	= (strtoupper($value) == 'ASC') ? 'ASC' : 'DESC';
				}

				$this->query_vars[$key]	= $value;
			}

			return $this;
		}elseif(in_array($method, [
			'get_col',
			'get_var',
			'get_row',
		])){
			if($method != 'get_col'){
				$this->limit(1);
			}

			$field	= $args[0] ?? '';
			$args	= [$this->get_sql($field)];

			if($method == 'get_row'){
				$args[]	= ARRAY_A;
			}

			return call_user_func_array([$GLOBALS['wpdb'], $method], $args);
		}elseif(in_array($method, [
			'get_table',
			'get_primary_key',
			'get_meta_type',
			'get_cache_group',
			'get_cache_prefix',
			'get_searchable_fields',
			'get_filterable_fields'
		])){
			return $this->{substr($method, 4)};
		}elseif(in_array($method, [
			'set_searchable_fields',
			'set_filterable_fields'
		])){
			$this->{substr($method, 4)}	= $args[0];
		}elseif(str_starts_with($method, 'cache_')){
			$key	= array_shift($args);

			if(!is_scalar($key)){
				trigger_error(var_export($key, true));
				return false;
			}

			if($method == 'cache_key'){
				$primary	= array_shift($args);
				$prefix		= $this->cache_prefix;

				if(!$primary && $this->cache_key){
					$key	= $this->cache_key.':'.$key;
				}

				return $prefix ? $prefix.':'.$key : $key;
			}

			if(str_ends_with($method, '_force')){
				$method		= wpjam_remove_postfix($method, '_force');
			}else{
				if(!$this->cache){
					return false;
				}
			}

			$primary	= str_ends_with($method, '_by_primary_key');

			if($primary){
				$method	= wpjam_remove_postfix($method, '_by_primary_key');
			}

			$key	= $this->cache_key($key, $primary);
			$group	= $this->cache_group;

			if(in_array($method, ['cache_get', 'cache_delete'])){
				return call_user_func('wp_'.$method, $key, $group);
			}else{
				$data	= array_shift($args);
				$time	= array_shift($args);
				$time	= $time ? (int)$time : $this->cache_time;

				return call_user_func('wp_'.$method, $key, $data, $group, $time);
			}
		}elseif(in_array($method, [
			'get_meta',
			'add_meta',
			'update_meta',
			'delete_meta',
			'delete_orphan_meta',
			'lazyload_meta',
			'delete_meta_by_key',
			'delete_meta_by_mid',
			'delete_meta_by_id',
			'update_meta_cache',
			'create_meta_table',
			'get_meta_table',
			'get_meta_column',
		])){
			$object	= wpjam_get_meta_type_object($this->meta_type);

			if($object){
				return call_user_func([$object, $method], ...$args);
			}
		}elseif(in_array($method, [
			'get_last_changed',
			'delete_last_changed',
		])){
			$key	= 'last_changed';
			$group	= $this->cache_group;

			$query_vars	= array_shift($args);

			if($query_vars && is_array($query_vars) && $this->group_cache_key){
				$query_vars	= wp_array_slice_assoc($query_vars, $this->group_cache_key);

				if($query_vars && count($query_vars) == 1){
					$group_key	= array_key_first($query_vars);
					$query_var	= current($query_vars);

					if(!is_array($query_var)){
						$key	.= ':'.$group_key.':'.$query_var;
					}
				}
			}

			if($method == 'get_last_changed'){
				$value	= wp_cache_get($key, $group);

				if(!$value){
					$value	= microtime();

					wp_cache_set($key, $value, $group);
				}

				return $value;
			}else{
				return wp_cache_delete($key, $group);
			}
		}

		return new WP_Error('undefined_method', [$method]);
	}

	public function clear(){
		$this->query_vars	= [
			'found_rows'	=> false,
			'limit'			=> 0,
			'offset'		=> 0,
			'orderby'		=> null,
			'order'			=> null,
			'groupby'		=> null,
			'having'		=> null,
			'search_term'	=> null,
		];

		$this->meta_query	= null;
		$this->where		= [];
	}

	public function find_by($field, $value, $order='ASC', $method='get_results'){
		$wpdb	= $GLOBALS['wpdb'];
		$format	= $this->process_formats($field);
		$sql	= "SELECT * FROM `{$this->table}` WHERE `{$field}` = {$format}";
		$sql	.= $order ? " ORDER BY `{$this->primary_key}` {$order}" : '';
		$sql	= $wpdb->prepare($sql, $value);

		return call_user_func([$wpdb, $method], $sql, ARRAY_A);
	}

	public function find_one_by($field, $value, $order=''){
		return $this->find_by($field, $value, $order, 'get_row');
	}

	public function find_one($id){
		return $this->find_one_by($this->primary_key, $id);
	}

	public function get($id){
		$result	= $this->cache ? $this->cache_get_by_primary_key($id) : false;

		if($result === false){
			$result	= $this->find_one($id);

			if($this->cache){
				$time	= $result ? $this->cache_time : MINUTE_IN_SECONDS;

				$this->cache_set_by_primary_key($id, $result, $time);
			}
		}

		return $result;
	}

	public function get_by($field, $value, $order='ASC'){
		if($this->cache && $field == $this->primary_key){
			return $this->get($value);
		}

		$cache	= $this->cache && $field == $this->cache_key;
		$result	= $cache ? $this->cache_get($value) : false;

		if($result === false){
			$result	= $this->find_by($field, $value, $order);

			if($cache){
				$time	= $result ? $this->cache_time : MINUTE_IN_SECONDS;

				$this->cache_set($value, $result, $time);
			}
		}

		return $result;
	}

	public function update_caches($keys, $primary=false){
		$keys	= wp_parse_list($keys);
		$keys	= array_filter($keys);
		$keys	= array_unique($keys);
		$data	= [];

		if(!$keys){
			return $data;
		}

		$primary	= $primary || !$this->cache_key;

		if($this->cache){
			$cache_keys	= [];

			foreach($keys as $key){
				$cache_keys[$key]	= $this->cache_key($key, $primary);
			}

			$cache_map		= array_flip($cache_keys);
			$cache_values	= wp_cache_get_multiple($cache_keys, $this->cache_group);

			foreach($cache_values as $cache_key => $cache_value){
				if($cache_value !== false){
					$key	= $cache_map[$cache_key];

					$data[$key]	= $cache_value;
				}
			}
		}

		if(count($data) != count($keys)){
			$data	= [];
			$field	= $primary ? $this->primary_key : $this->cache_key;
			$result = $GLOBALS['wpdb']->get_results($this->where_in($field, $keys)->get_sql(), ARRAY_A);

			if($result){
				if($primary){
					$data	= array_combine(array_column($result, $this->primary_key), $result);
				}else{
					foreach($keys as $key){
						$data[$key]	= array_values(wp_list_filter($result, [$field => $key]));
					}
				}
			}

			if($this->cache){
				foreach($cache_keys as $key => $cache_key){
					$value	= $data[$key] ?? [];
					$time	= $value ? $this->cache_time : MINUTE_IN_SECONDS;

					wp_cache_set($cache_key, $value, $this->cache_group, $time);
				}
			}
		}

		if($this->meta_type){
			$ids	= [];

			if($primary){
				foreach($data as $id => $item){
					if($item){
						$ids[]	= $id;
					}
				}
			}else{
				foreach($data as $items){
					if($items){
						$ids	= array_merge($ids, array_column($items, $this->primary_key));
					}
				}
			}

			$this->lazyload_meta($ids);
		}

		return $data;
	}

	public function get_ids($ids){
		return self::update_caches($ids, true);
	}

	public function get_by_ids($ids){
		return self::get_ids($ids);
	}

	public function get_clauses($fields=[]){
		$distinct	= '';
		$where		= '';
		$join		= '';
		$groupby	= $this->groupby ?: '';

		if($this->meta_query){
			$clauses	= $this->meta_query->get_sql($this->meta_type, $this->table, $this->primary_key, $this);

			$where	= $clauses['where'];
			$join	= $clauses['join'];

			if(!empty($this->meta_query->queries)){
				$groupby	= $groupby ?: $this->table.'.'.$this->primary_key;
				$fields		= $fields ?: $this->table.'.*';
			}
		}

		if($fields){
			if(is_array($fields)){
				$fields	= '`'.implode( '`, `', $fields ).'`';
				$fields	= esc_sql($fields);
			}
		}else{
			$fields = '*';
		}

		if($groupby){
			if(!str_contains($groupby, ',') && !str_contains($groupby, '(') && !str_contains($groupby, '.')){
				$groupby	= '`'.$groupby.'`';
			}

			$groupby	= ' GROUP BY '.$groupby;
		}

		$having		= $this->having ? ' HAVING '.$having : '';
		$orderby	= $this->orderby;

		if(is_null($orderby) && !$groupby && !$having){
			$orderby	= $this->primary_key;
		}

		if($orderby){
			if(is_array($orderby)){
				$parsed	= [];

				foreach($orderby as $_orderby => $order){
					$parsed[]	= $this->parse_orderby($_orderby, $order);
				}

				$parsed		= array_filter($parsed);
				$orderby	= $parsed ? implode(', ', $parsed) : '';
			}elseif(str_contains($orderby, ',') || (str_contains($orderby, '(') && str_contains($orderby, ')'))){
				$orderby	= esc_sql($orderby);
			}else{
				$orderby	= $this->parse_orderby($orderby, $this->order);
			}

			$orderby	= $orderby ? ' ORDER BY '.$orderby : '';
		}else{
			$orderby	= '';
		}

		$limits		= $this->limit ? ' LIMIT '.$this->limit : '';
		$limits		.= $this->offset ? ' OFFSET '.$this->offset : '';
		$found_rows	= ($limits && $this->found_rows) ? 'SQL_CALC_FOUND_ROWS' : '';
		$conditions	= $this->get_conditions();

		if(!$conditions && $where){
			$where	= 'WHERE 1=1 '.$where;
		}else{
			$where	= $conditions.$where;
			$where	= $where ? ' WHERE '.$where : '';
		}

		return compact('found_rows', 'distinct', 'fields', 'join', 'where', 'groupby', 'having', 'orderby', 'limits');
	}

	public function get_request($clauses=null){
		$clauses	= $clauses ?: $this->get_clauses();

		return sprintf("SELECT %s %s %s FROM `{$this->table}` %s %s %s %s %s %s", ...array_values($clauses));
	}

	public function get_sql($fields=[]){
		return $this->get_request($this->get_clauses($fields));
	}

	public function get_results($fields=[]){
		$clauses	= $this->get_clauses($fields);
		$sql		= $this->get_request($clauses);
		$results	= $GLOBALS['wpdb']->get_results($sql, ARRAY_A);

		return $this->filter_results($results, $clauses['fields']);
	}

	protected function filter_results($results, $fields){
		if($results && in_array($fields, ['*', $this->table.'.*'])){
			$ids	= [];

			foreach($results as $result){
				if(!empty($result[$this->primary_key])){
					$id		= $result[$this->primary_key];
					$ids[]	= $id;

					$this->cache_set_by_primary_key($id, $result);
				}
			}

			if($ids){
				if($this->lazyload_callback){
					call_user_func($this->lazyload_callback, $ids, $results);
				}

				if($this->meta_type){
					$this->lazyload_meta($ids);
				}
			}	
		}

		return $results;
	}

	public function find($fields=[]){
		return $this->get_results($fields);
	}

	public function find_total(){
		return $GLOBALS['wpdb']->get_var("SELECT FOUND_ROWS();");
	}

	protected function parse_orderby($orderby, $order){
		if($orderby == 'rand'){
			return 'RAND()';
		}elseif(preg_match('/RAND\(([0-9]+)\)/i', $orderby, $matches)){
			return sprintf('RAND(%s)', (int)$matches[1]);
		}elseif(str_ends_with($orderby, '__in')){
			return '';
			// $field	= str_replace('__in', '', $orderby);
		}

		$order	= (is_string($order) && 'ASC' === strtoupper($order)) ? 'ASC' : 'DESC';

		if($this->meta_query){
			$primary_meta_key	= '';
			$primary_meta_query	= false;
			$meta_clauses		= $this->meta_query->get_clauses();

			if(!empty($meta_clauses)){
				$primary_meta_query	= reset($meta_clauses);

				if(!empty($primary_meta_query['key'])){
					$primary_meta_key	= $primary_meta_query['key'];
				}

				if($orderby == $primary_meta_key || $orderby == 'meta_value'){
					if(!empty($primary_meta_query['type'])){
						return "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']}) ".$order;
					}else{
						return "{$primary_meta_query['alias']}.meta_value ".$order;
					}
				}elseif($orderby == 'meta_value_num'){
					return "{$primary_meta_query['alias']}.meta_value+0 ".$order;
				}elseif(array_key_exists($orderby, $meta_clauses)){
					$meta_clause	= $meta_clauses[$orderby];

					return "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']}) ".$order;
				}
			}
		}

		if($orderby == 'meta_value_num' || $orderby == 'meta_value'){
			return '';
		}

		return '`'.$orderby.'` '.$order;
	}

	public function insert_multi($datas){	// 使用该方法，自增的情况可能无法无法删除缓存，请注意
		if(empty($datas)){
			return 0;
		}

		$datas		= array_values($datas);

		$this->delete_last_changed();
		$this->cache_delete_by_conditions([], $datas);

		$wpdb		= $GLOBALS['wpdb'];
		$data		= current($datas);
		$formats	= $this->process_formats($data);
		$values		= [];
		$fields		= '`'.implode('`, `', array_keys($data)).'`';
		$updates	= implode(', ', array_map(function($field){ return "`$field` = VALUES(`$field`)"; }, array_keys($data)));

		foreach($datas as $data){
			if($data){
				foreach($data as $k => $v){
					if(is_array($v)){
						trigger_error($k.'的值是数组：'.var_export($data, true));
						continue;
					}
				}

				$values[]	= $wpdb->prepare('('.implode(', ', $formats).')', array_values($data));
			}
		}

		$values	= implode(',', $values);
		$sql	= "INSERT INTO `$this->table` ({$fields}) VALUES {$values} ON DUPLICATE KEY UPDATE {$updates}";
		$result	= $wpdb->query($sql);

		if(false === $result){
			return new WP_Error('insert_error', $wpdb->last_error);
		}

		return $result;
	}

	public function insert($data){
		$this->delete_last_changed();
		$this->cache_delete_by_conditions([], $data);

		$wpdb	= $GLOBALS['wpdb'];

		if(!empty($data[$this->primary_key])){
			$data		= array_filter($data, 'is_exists');
			$formats	= $this->process_formats($data);
			$fields		= implode(', ', array_keys($data));
			$values		= $wpdb->prepare(implode(', ',$formats), array_values($data));
			$updates	= implode(', ', array_map(function($field){ return "`$field` = VALUES(`$field`)"; }, array_keys($data)));

			$wpdb->check_current_query = false;

			if(false === $wpdb->query("INSERT INTO `$this->table` ({$fields}) VALUES ({$values}) ON DUPLICATE KEY UPDATE {$updates}")){
				return new WP_Error('insert_error', $wpdb->last_error);
			}

			return $data[$this->primary_key];
		}else{
			$formats	= $this->process_formats($data);
			$result		= $wpdb->insert($this->table, $data, $formats);

			if($result === false){
				return new WP_Error('insert_error', $wpdb->last_error);
			}

			$this->cache_delete_by_primary_key($wpdb->insert_id);

			return $wpdb->insert_id;
		}
	}

	/*
	用法：
	update($id, $data);
	update($data, $where);
	update($data); // $where各种 参数通过 where() 方法事先传递
	*/
	public function update(...$args){
		$this->delete_last_changed();

		$wpdb		= $GLOBALS['wpdb'];
		$args_num	= count($args);

		if($args_num == 2){
			if(is_array($args[0])){
				$data	= $args[0];
				$where	= $args[1];

				$conditions	= $this->where_all($where, 'fragment');
			}else{
				$id		= $args[0];
				$data	= $args[1];
				$where	= $conditions = [$this->primary_key => $id];

				$this->cache_delete_by_primary_key($id);
			}

			$this->cache_delete_by_conditions($conditions, $data);

			$result	= $wpdb->update($this->table, $data, $where, $this->process_formats($data), $this->process_formats($where));

			return $result === false ? new WP_Error('update_error', $wpdb->last_error) : $result;
		}
		// 如果为空，则需要事先通过各种 where 方法传递进去
		elseif($args_num == 1){
			$data	= $args[0];
			$where	= $this->get_conditions();

			if($data && $where){
				$this->cache_delete_by_conditions($where, $data);

				$fields = $values = [];

				foreach($data as $field => $value){
					if(is_null($value)){
						$fields[] = "`$field` = NULL";
					}else{
						$fields[] = "`$field` = ".$this->process_formats($field);
						$values[]	= $value;
					}
				}

				$fields = implode(', ', $fields);
				$sql	= $wpdb->prepare("UPDATE `{$this->table}` SET {$fields} WHERE {$where}", $values);

				return $wpdb->query($sql);
			}else{
				return 0;
			}
		}
	}

	/*
	用法：
	delete($where);
	delete($id);
	delete(); // $where 参数通过各种 where() 方法事先传递
	*/
	public function delete($where = ''){
		$this->delete_last_changed();

		$wpdb	= $GLOBALS['wpdb'];
		$id		= null;

		// 如果传递进来字符串或者数字，认为根据主键删除，否则传递进来数组，使用 wpdb 默认方式
		if($where){
			if(is_array($where)){
				$this->cache_delete_by_conditions($this->where_all($where, 'fragment'));
			}else{
				$id		= $where;
				$where	= [$this->primary_key => $id];

				$this->cache_delete_by_primary_key($id);
				$this->cache_delete_by_conditions($where);
			}

			$result	= $wpdb->delete($this->table, $where, $this->process_formats($where));
		}
		// 如果为空，则 $where 参数通过各种 where() 方法事先传递
		else{
			$where	= $this->get_conditions();

			if(!$where){
				return 0;
			}

			$this->cache_delete_by_conditions($where);

			$sql	= "DELETE FROM `{$this->table}` WHERE {$where}";
			$result = $wpdb->query($sql);
		}

		if(false === $result){
			return new WP_Error('delete_error', $wpdb->last_error);
		}

		if($id){
			$this->delete_meta_by_id($id);
		}else{
			$this->delete_orphan_meta($this->table, $this->primary_key);
		}

		return $result;
	}

	public function delete_by($field, $value){
		return $this->delete([$field => $value]);
	}

	public function delete_multi($ids){
		if(empty($ids)){
			return 0;
		}

		$this->delete_last_changed();

		foreach($ids as $id){
			$this->cache_delete_by_primary_key($id);
		}

		$this->cache_delete_by_conditions([$this->primary_key => $ids]);

		$wpdb	= $GLOBALS['wpdb'];
		$values = [];

		foreach($ids as $id){
			$values[]	= $wpdb->prepare($this->process_formats($this->primary_key), $id);
		}

		$where	= 'WHERE `'.$this->primary_key.'` IN ('.implode(',', $values).') ';
		$sql	= "DELETE FROM `{$this->table}` {$where}";
		$result = $wpdb->query($sql);

		if(false === $result ){
			return new WP_Error('delete_error', $wpdb->last_error);
		}

		return $result ;
	}

	protected function cache_delete_by_conditions($conditions, $data=[]){
		if($this->cache || $this->group_cache_key){
			if($data){
				$conditions	= $conditions ? (array)$conditions : [];
				$datas		= wp_is_numeric_array($data) ? $data : [$data];

				foreach($datas as $data){
					foreach(['primary_key', 'cache_key'] as $k){
						$key	= $this->$k;

						if($k == 'primary_key'){
							if(empty($data[$key])){
								continue;
							}

							$this->cache_delete_by_primary_key($data[$key]);
						}else{
							if(!$this->cache_key || !isset($data[$key])){
								continue;
							}

							$this->cache_delete($data[$key]);
						}

						$conditions[$key]	= isset($conditions[$key]) ? (array)$conditions[$key] : [];
						$conditions[$key][]	= $data[$key];
					}

					foreach($this->group_cache_key as $group_cache_key){
						if(isset($data[$group_cache_key])){
							$this->delete_last_changed([$group_cache_key => $data[$group_cache_key]]);
						}
					}
				}
			}

			if(is_array($conditions)){
				if(!$this->cache_key && !$this->group_cache_key){
					if(count($conditions) == 1 && isset($conditions[$this->primary_key])){
						$conditions	= [];
					}
				}
			
				$conditions	= $conditions ? $this->where_any($conditions, 'fragment') : null;
			}

			if($conditions){
				$fields	= [$this->primary_key];

				if($this->cache_key){
					$fields[]	= $this->cache_key;
				}

				foreach($this->group_cache_key as $group_cache_key){
					$fields[]	= $group_cache_key;
				}

				$fields		= implode(', ', $fields);
				$results	= $GLOBALS['wpdb']->get_results("SELECT {$fields} FROM `{$this->table}` WHERE {$conditions}", ARRAY_A) ?: [];

				foreach($results as $result){
					$this->cache_delete_by_primary_key($result[$this->primary_key]);

					if($this->cache_key){
						$this->cache_delete($result[$this->cache_key]);
					}

					foreach($this->group_cache_key as $group_cache_key){
						$this->delete_last_changed([$group_cache_key => $result[$group_cache_key]]);
					}
				}
			}
		}
	}

	protected function get_conditions(){
		$where	= $this->parse_where($this->where, 'AND');

		if($this->searchable_fields && $this->search_term){
			$wpdb	= $GLOBALS['wpdb'];
			$like	= '%'.$wpdb->esc_like($this->search_term).'%';
			$search	= [];

			foreach($this->searchable_fields as $field){
				$search[]	= $wpdb->prepare('`'.$field.'` LIKE  %s', $like);
			}

			$where	.= $where ? ' AND ' : ''; 
			$where	.= '('.implode(' OR ', $search).')';
		}

		$this->clear();

		return $where;
	}

	public function get_wheres(){	// 以后放弃，目前统计在用
		return $this->get_conditions();
	}

	protected function process_formats($data){
		if(is_array($data)){
			$format	= [];

			foreach($data as $field => $value){
				$format[]	= $this->process_formats($field);
			}

			return $format;
		}else{
			return $this->field_types[$data] ?? '%s';
		}
	}

	protected function parse_where($qs=null, $type=''){
		$wpdb	= $GLOBALS['wpdb'];
		$where	= [];
		$qs		= $qs ?? $this->where;

		foreach($qs as $q){
			if(!$q || empty($q['compare'])){
				continue;
			}

			$compare	= strtoupper($q['compare']);

			if($compare == strtoupper('fragment')){
				$where[]	= $q['fragment'];

				continue;
			}

			$value	= $q['value'];
			$column	= $q['column'];
			
			if(str_contains($column, '(')){
				$format	= '%s';
			}else{
				$format	= $this->process_formats($column);
				$column	= '`'.$column.'`';
			}

			if(in_array($compare, ['IN', 'NOT IN'])){
				$value	= is_array($value) ? $value : explode(',', $value);
				$value	= array_values(array_unique($value));

				foreach($value as &$v){
					$v	= $wpdb->prepare($format, $v);
				}

				if(count($value) > 1){
					$value		= '('.implode(',', $value).')';
				}else{
					$compare	= $compare == 'IN' ? '=' : '!=';
					$value		= $value ? current($value) : '\'\'';
				}
			}elseif(in_array($compare, ['LIKE', 'NOT LIKE'])){
				$left	= str_starts_with($value, '%');
				$right	= str_ends_with($value, '%');
				$value	= trim($value, '%');
				$value	= ($left ? '%' : '').$wpdb->esc_like($value).($right ? '%' : '');
				$value	= $wpdb->prepare('%s', $value);
			}else{
				$value	= $wpdb->prepare($format, $value);
			}

			$where[]	= $column.' '.$compare.' '.$value;
		}

		return $type ? implode(' '.$type.' ', $where) : $where;
	}

	public function where($column, $value, $output='object'){
		if(is_array($value)){
			if(wp_is_numeric_array($value)){
				$value	= ['value'=>$value];
			}

			if(!isset($value['value'])){
				$value	= [];
			}else{
				if(is_numeric($column) || is_null($column)){
					if(!isset($value['column'])){
						$value = [];
					}
				}else{
					$value['column']	= $column;
				}

				if($value){
					if(!isset($value['compare']) || !in_array(strtoupper($value['compare']), $this->get_operators())){
						$value['compare']	= is_array($value['value']) ? 'IN' : '=';
					}
				}
			}
		}else{
			if(is_null($value)){
				$value	= [];
			}else{
				if(is_numeric($column) || is_null($column)){
					$value	= ['compare'=>'fragment', 'fragment'=>'( '.$value.' )'];
				}else{
					$value	= ['compare'=>'=', 'column'=>$column, 'value'=>$value];
				}
			}
		}

		if($output != 'object'){
			return $value;
		}else{
			$this->where[]	= $value;

			return $this;
		}
	}

	public function query_items($limit, $offset){
		$this->limit($limit)->offset($offset)->found_rows();

		if(is_null($this->orderby)){
			$this->orderby(wpjam_get_data_parameter('orderby'));
		}

		if(is_null($this->order)){
			$this->order(wpjam_get_data_parameter('order'));
		}

		if($this->searchable_fields && is_null($this->search_term)){
			$this->search(wpjam_get_data_parameter('s'));
		}

		foreach($this->filterable_fields as $filter_key){
			$this->where($filter_key, wpjam_get_data_parameter($filter_key));
		}

		return ['items'=>$this->get_results(), 'total'=>$this->find_total()];
	}

	public function query($query_vars, $output='array'){
		$query_vars	= apply_filters('wpjam_query_vars', $query_vars, $this);

		if(isset($query_vars['groupby'])){
			$query_vars	= array_except($query_vars, ['first', 'cursor']);

			$query_vars['no_found_rows']	= true;
		}else{
			if(!isset($query_vars['number']) && empty($query_vars['no_found_rows'])){
				$query_vars['number']	= 50;
			}
		}
		
		$qv				= $query_vars;
		$no_found_rows	= $qv['no_found_rows'] ?? false;
		$cache_results	= $qv['cache_results'] ?? true;
		$fields			= $qv['fields'] ?? null;
		$orderby		= $qv['orderby'] ?? $this->primary_key;

		if($cache_results && str_contains(strtoupper($orderby), ' RAND(')){
			$cache_results	= false;
		}

		if($this->meta_type){
			$this->meta_query	= new WP_Meta_Query();
			$this->meta_query->parse_query_vars($qv);

			$qv	= array_except($qv, [
				'meta_key',
				'meta_value',
				'meta_value_num',
				'meta_compare',
				'meta_query'
			]);
		}

		foreach($qv as $key => $value){
			if(is_null($value) || in_array($key, ['no_found_rows', 'cache_results', 'fields'])){
				continue;
			}

			if($key == 'number'){
				if($value == -1){
					$no_found_rows	= true;
				}else{
					$this->limit($value);
				}
			}elseif($key == 'offset'){
				$this->offset($value);
			}elseif($key == 'orderby'){
				$this->orderby($value);
			}elseif($key == 'order'){
				$this->order($value);
			}elseif($key == 'groupby'){
				$this->groupby($value);
			}elseif($key == 'cursor'){
				if($value > 0){
					$this->where_lt($orderby, $value);
				}
			}elseif($key == 'search' || $key == 's'){
				$this->search($value);
			}else{
				foreach($this->get_operators() as $operator => $compare){
					if(str_ends_with($key, '__'.$operator)){
						$key	= wpjam_remove_postfix($key, '__'.$operator);
						$value	= ['value'=>$value, 'compare'=>$compare];
						
						break;
					}
				}

				$this->where($key, $value);
			}
		}

		if(!$no_found_rows){
			$this->found_rows(true);
		}

		$clauses	= apply_filters_ref_array('wpjam_clauses', [$this->get_clauses($fields), &$this]);
		$request	= apply_filters_ref_array('wpjam_request', [$this->get_request($clauses), &$this]);
		$result		= false;

		if($cache_results){
			$last_changed	= $this->get_last_changed($query_vars);
			$cache_key		= 'wpjam_query:'.md5(maybe_serialize($query_vars).$request).':'.$last_changed;
			$result			= $this->cache_get_force($cache_key);
		}

		if($result === false || !isset($result['items'])){
			$items	= $GLOBALS['wpdb']->get_results($request, ARRAY_A);
			$items	= $this->filter_results($items, $clauses['fields']);
			$result	= ['items'=>$items];

			if(!$no_found_rows){
				$result['total']	= $this->find_total();
			}

			if($cache_results){
				$this->cache_set_force($cache_key, $result, DAY_IN_SECONDS);
			}
		}

		if(!$no_found_rows){
			$number	= $qv['number'] ?? null;

			if($number && $number != -1){
				$result['max_num_pages']	= ceil($result['total'] / $number);

				if($result['max_num_pages'] > 1){
					$result['next_cursor']	= (int)(end($result['items'])[$orderby]);
				}else{
					$result['next_cursor']	= 0;
				}
			}
		}else{
			$result['total']	= count($result['items']);
		}

		$result['items']		= $result['datas']	= apply_filters_ref_array('wpjam_queried_items', [$result['items'], &$this]);
		$result['found_rows']	= $result['total'];
		$result['request']		= $request;

		return $output == 'object' ? (object)$result : $result;
	}
}

class WPJAM_DBTransaction{
	public static function beginTransaction(){
		return $GLOBALS['wpdb']->query("START TRANSACTION;");
	}

	public static function queryException(){
		$error = $GLOBALS['wpdb']->last_error;
		if(!empty($error)){
			throw new Exception($error);
		}
	}

	public static function commit(){
		self::queryException();
		return $GLOBALS['wpdb']->query("COMMIT;");
	}

	public static function rollBack(){
		return $GLOBALS['wpdb']->query("ROLLBACK;");
	}
}