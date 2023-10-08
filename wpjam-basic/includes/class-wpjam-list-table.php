<?php
if(!class_exists('WP_List_Table')){
	include ABSPATH.'wp-admin/includes/class-wp-list-table.php';
}

class WPJAM_List_Table extends WP_List_Table{
	use WPJAM_Call_Trait;

	public function __construct($args=[]){
		$this->_args	= $args	= wp_parse_args($args, [
			'title'			=> '',
			'plural'		=> '',
			'singular'		=> '',
			'data_type'		=> 'model',
			'capability'	=> 'manage_options',
			'per_page'		=> 50
		]);

		$primary_key	= $this->get_primary_key_by_model();

		if($primary_key){
			$args['primary_key']	= $primary_key;
		}

		$GLOBALS['wpjam_list_table']	= $this;

		parent::__construct($this->parse_args($args));
	}

	public function __get($name){
		if(in_array($name, $this->compat_fields, true)){
			return $this->$name;
		}

		return $this->_args[$name] ?? null;
	}

	public function __set($name, $value){
		if(in_array($name, $this->compat_fields, true)){
			return $this->$name	= $value;
		}

		return $this->_args[$name]	= $value;
	}

	public function __isset($name){
		return $this->$name !== null;
	}

	public function __call($method, $args){
		if(str_ends_with($method, '_by_locale')){
			$method	= wpjam_remove_postfix($method, '_by_locale');

			return wpjam_call([$GLOBALS['wp_locale'], $method], ...$args);
		}elseif(str_ends_with($method, '_by_model')){
			$method	= wpjam_remove_postfix($method, '_by_model');

			if(method_exists($this->model, $method)){
				return wpjam_call([$this->model, $method], ...$args);
			}

			$fallback	= [
				'render_item'	=> 'item_callback',
				'get_subtitle'	=> 'subtitle',
				'get_views'		=> 'views',
				'query_items'	=> 'list',
			];

			if(isset($fallback[$method]) && method_exists($this->model, $fallback[$method])){
				return wpjam_call([$this->model, $fallback[$method]], ...$args);
			}

			if(in_array($method, [
				'render_item',
				'render_date'
			])){
				return $args[0];
			}elseif(in_array($method, [
				'get_subtitle',
				'get_views',
				'get_fields',
				'extra_tablenav',
				'before_single_row',
				'after_single_row',
			])){
				return null;
			}else{
				if(method_exists($this->model, '__callStatic')){
					$result	= wpjam_call([$this->model, $method], ...$args);
				}else{
					$result	= new WP_Error('undefined_method', [$this->model.'->'.$method.'()']);
				}

				if(is_wp_error($result)){
					if(in_array($method, [
						'get_filterable_fields',
						'get_searchable_fields',
						'get_primary_key',
						'col_left',
					])){
						return null;
					}
				}

				return $result;
			}
		}else{
			return parent::__call($method, $args);
		}
	}

	protected function parse_args($args){
		$this->screen	= $args['screen'] = get_current_screen();
		$this->_args	= $this->_args ?? [];
		$this->_args	= array_merge($this->_args, $args);

		add_screen_option('list_table', $this);

		if(is_array($this->per_page)){
			add_screen_option('per_page', $this->per_page);
		}

		$style	= [$this->style];
		$keys	= ['row_actions', 'bulk_actions', 'overall_actions', 'next_actions', 'columns', 'sortable_columns', 'views'];
		$args	= array_merge($args, array_fill_keys($keys, []));

		foreach($this->get_objects('view') as $key => $object){
			$view	= $object->get_link();

			if($view && is_array($view)){
				$view	= $view['label'] ? $this->get_filter_link($view['filter'], $view['label'], $view['class']) : null;
			}

			if($view){
				$args['views'][$key]	= $view;
			}
		}

		foreach($this->get_objects('action') as $key => $object){
			if($object->overall){
				$args['overall_actions'][]	= $key;
			}else{
				if($object->bulk && $object->is_allowed()){
					$args['bulk_actions'][$key]	= $object;
				}

				if($object->next && $object->response == 'form'){
					$args['next_actions'][$key]	= $object->next;
				}

				if($key == 'add'){
					if($this->layout == 'left'){
						$args['overall_actions'][]	= $key;
					}
				}else{
					if($object->row_action){
						$args['row_actions'][$key]	= $key;
					}
				}
			}
		}

		foreach($this->get_objects('column') as $object){
			$style[]	= $object->get_style();
			$key		= $object->name;

			$args['columns'][$key]	= $object->column_title ?? $object->title;

			if($object->sortable_column){
				$args['sortable_columns'][$key] = [$key, true];
			}
		}

		$style	= array_filter($style);

		if($style){
			wp_add_inline_style('list-tables', implode("\n", $style));
		}

		add_shortcode('filter',		[$this, 'shortcode_callback']);
		add_shortcode('row_action',	[$this, 'shortcode_callback']);

		return $args;
	}

	protected function register($name, $args, $type='action'){
		if($type == 'column'){
			if(empty($args['show_admin_column'])){
				return;
			}

			$args	= wpjam_strip_data_type($args);
			$args	= array_except($args, 'style');
			$args	= wp_parse_args($args, ['order'=>10.5]);
		}

		return wpjam_call('wpjam_register_list_table_'.$type, $name, $args);
	}

	protected function get_objects($type='action'){
		if($type == 'action'){
			if($this->sortable){
				$sortable	= is_array($this->sortable) ? $this->sortable : ['items'=>' >tr'];
				$action		= array_pull($sortable, 'action', []);

				$this->register('move',	$action+['direct'=>true,	'page_title'=>'拖动',	'dashicon'=>'move']);
				$this->register('up',	$action+['direct'=>true,	'page_title'=>'向上移动',	'dashicon'=>'arrow-up-alt']);
				$this->register('down',	$action+['direct'=>true,	'page_title'=>'向下移动',	'dashicon'=>'arrow-down-alt']);
				$this->sortable	= $sortable;
			}

			if(isset($this->actions)){
				$actions	= $this->actions;
			}elseif(method_exists($this->model, 'get_actions')){
				$actions	= $this->get_actions_by_model();
			}else{
				$actions	= $this->_builtin ? [] : [
					'add'		=> ['title'=>'新建',	'dismiss'=>true],
					'edit'		=> ['title'=>'编辑'],
					'delete'	=> ['title'=>'删除',	'direct'=>true,	'confirm'=>true,	'bulk'=>true],
				];
			}

			foreach(array_wrap($actions, []) as $key => $action){
				$this->register($key, $action+['order'=>10.5]);
			}

			$data_type	= $this->data_type;
			$meta_type	= get_screen_option('meta_type');

			if($meta_type){
				$args	= ['list_table'=>true];

				if($data_type && in_array($data_type, ['post_type', 'taxonomy'])){
					$args[$data_type]	= $this->$data_type;
				}

				foreach(wpjam_get_meta_options($meta_type, $args) as $name => $option){
					$action_name	= $option->action_name ?: 'set_'.$name;

					if(!$this->get_object($action_name)){
						$this->register($action_name, $option->parse_list_table_args());
					}
				}
			}
		}elseif($type == 'column'){
			$fields	= $this->get_fields_by_model() ?: [];

			foreach(wpjam_parse_fields($fields) as $key => $field){
				$this->register($key, $field, $type);
			}
		}elseif($type == 'view'){
			$views	= $this->get_views_by_model() ?: [];

			foreach($views as $key => $view){
				$this->register($key, $view, $type);
			}
		}

		return wpjam_call(['WPJAM_List_Table_'.$type, 'get_registereds'], wpjam_slice_data_type($this->_args));
	}

	protected function get_object($name, $type='action'){
		return wpjam_call(['WPJAM_List_Table_'.$type, 'get'], $name, $this->_args);
	}

	public function shortcode_callback($attrs, $title, $tag){
		if($tag == 'filter'){
			$class	= array_pull($attrs, 'class', []);

			return $this->get_filter_link($attrs, $title, $class);
		}elseif($tag == 'row_action'){
			$name	= array_pull($attrs, 'name');

			if($title){
				$attrs['title']	= $title;
			}

			if(isset($attrs['data'])){
				$attrs['data']	= wp_parse_args($attrs['data']);
			}

			return $this->get_row_action($name, $attrs);
		}
	}

	public function get_setting(){
		return [
			'ajax'			=> true,
			'form_id'		=> $this->form_id ?: 'list_table_form',
			'left_key'		=> $this->left_key,
			'bulk_actions'	=> $this->bulk_actions,
			'sortable'		=> $this->sortable,
			'query_id'		=> $this->current_action() ? null : wpjam_get_parameter('id', ['sanitize_callback'=>'sanitize_text_field'])
		];
	}

	protected function do_shortcode($content, $id){
		return do_shortcode(str_replace('[row_action ', '[row_action id="'.$id.'" ', $content));
	}

	protected function get_row_actions($id){
		$actions	= [];

		foreach($this->row_actions as $key){
			if(!in_array($key, $this->next_actions)){
				$actions[$key] = $this->get_row_action($key, ['id'=>$id]);
			}
		}

		return array_filter($actions);
	}

	public function get_row_action($action, $args=[]){
		$object = $this->get_object($action);

		return $object ? $object->get_row_action($args) : '';
	}

	public function get_filter_link($filter, $label, $class=[]){
		$query_args	= $this->query_args ?: [];

		foreach($query_args as $query_arg){
			if(!array_key_exists($query_arg, $filter)){
				$filter[$query_arg]	= wpjam_get_data_parameter($query_arg);
			}
		}

		return "\n".wpjam_wrap_tag($label, 'a', [
			'title'	=> wp_strip_all_tags((string)$label, true),
			'class'	=> array_merge((array)$class, ['list-table-filter']),
			'data'	=> ['filter'=>($filter ?: new stdClass())],
		]);
	}

	protected function get_by_primary_key($item){
		return $this->primary_key ? $item[$this->primary_key] : null;
	}

	public function get_single_row($id){
		return wpjam_ob_get_contents([$this, 'single_row'], $id);
	}

	public function single_row($item){
		$raw	= $item	= $this->parse_item($item);

		if(!$item){
			return;
		}

		$this->before_single_row_by_model($item);

		$attr	= [];
		$id		= $this->get_by_primary_key($item);

		if($id){
			$item['row_actions']	= $this->get_row_actions($id);

			if($this->primary_key == 'id'){
				$item['row_actions']['id']	= 'ID：'.$id;
			}

			$id	= str_replace('.', '-', $id);

			$attr['id']		= $this->singular.'-'.$id;
			$attr['data']	= ['id'=>$id];
		}

		$item	= $this->render_item_by_model($item);

		$attr['class']	= $item['class'] ?? '';
		$attr['style']	= $item['style'] ?? '';

		if($id && $this->multi_rows){
			$attr['class']	= array_merge((array)$attr['class'], ['tr-'.$id]);
		}

		ob_start();

		$this->single_row_columns($item);

		echo wpjam_tag('tr', $attr, ob_get_clean());

		$this->after_single_row_by_model($item, $raw);
	}

	protected function parse_item($item){
		if(!is_array($item)){
			$result	= $this->get_by_model($item);
			$item 	= is_wp_error($result) ? null : $result;
			$item	= $item ? (array)$item : $item;
		}

		return $item;
	}

	protected function get_column_value($id, $name, $value=null){
		$object	= $this->get_object($name, 'column');

		if($object){
			if(is_null($value)){
				if(method_exists($this->model, 'value_callback')){
					$value	= wpjam_value_callback([$this->model, 'value_callback'], $name, $id);
				}else{
					$value	= $object->default;
				}
			}

			$value	= $object->callback($id, $value);
		}

		if(is_array($value)){
			$wrap	= array_get($value, 'wrap');

			if(isset($value['row_action'])){
				$action	= array_get($value, 'row_action');
				$args	= array_get($value, 'args', []);
				$value	= $this->get_row_action($action, array_merge($args, ['id'=>$id]));
			}elseif(isset($value['filter'])){
				$filter	= array_get($value, 'filter', []);
				$label	= array_get($value, 'label');
				$class	= array_get($value, 'class', []);
				$value	= $this->get_filter_link($filter, $label, $class);
			}elseif(isset($value['items'])){
				$items	= array_get($value, 'items', []);
				$args	= array_get($value, 'args', []);
				$value	= $this->render_column_items($id, $items, $args);
			}else{
				trigger_error(var_export($value, true));
				$value	= '';
			}

			return $wrap ? wpjam_wrap($value, $wrap) : $value;
		}

		return $this->_builtin ? $value : $this->do_shortcode($value, $id);
	}

	public function column_default($item, $name){
		$value	= $item[$name] ?? null;
		$id		= $this->get_by_primary_key($item);

		return $id ? $this->get_column_value($id, $name, $value) : $value;
	}

	public function column_cb($item){
		$id	= $this->get_by_primary_key($item);

		if(!is_null($id) && current_user_can($this->capability, $id)){
			$column	= $this->get_primary_column_name();
			$name	= isset($item[$column]) ? strip_tags($item[$column]) : $id;
			$cb_id	= 'cb-select-'.$id;

			return wpjam_wrap_tag('选择'.$name, 'label', ['for'=>$cb_id, 'class'=>'screen-reader-text'])->after('input', ['type'=>'checkbox', 'name'=>'ids[]', 'value'=>$id, 'id'=>$cb_id]);
		}

		return wpjam_tag('span', ['dashicons', 'dashicons-minus']);
	}

	public function render_column_items($id, $items, $args=[]){
		$item_type	= $args['item_type'] ?? 'image';
		$item_key	= $args[$item_type.'_key'] ?? $item_type;
		$max_items	= $args['max_items'] ?? 0;
		$per_row	= $args['per_row'] ?? 0;
		$sortable	= $args['sortable'] ?? 0;
		$width		= $args['width'] ?? 60;
		$height		= $args['height'] ?? 60;
		$style		= (array)($args['style'] ?? []);

		$add_item	= $args['add_item'] ?? 'add_item';
		$edit_item	= $args['edit_item'] ?? 'edit_item';
		$move_item	= $args['move_item'] ?? 'move_item';
		$del_item	= $args['del_item'] ?? 'del_item';

		$rendered	= wpjam_tag();

		foreach($items as $i => $item){
			$color	= $item['color'] ?? null;
			$data	= compact('i');
			$args	= ['id'=>$id, 'data'=>$data];
			$attr	= ['id'=>'item_'.$i, 'data'=>$data, 'class'=>'item'];

			if($item_type == 'image'){
				$image	= $item[$item_key] ? wpjam_get_thumbnail($item[$item_key], $width*2, $height*2) : '';
				$image	= $image ? wpjam_tag('img', ['src'=>$image, 'width'=>$width, 'height'=>$height]) : ' ';
				$item	= $image.(!empty($item['title']) ? wpjam_tag('span', ['item-title'], $item['title']) : '');
				$attr	+= ['style'=>'width:'.$width.'px;'];
			}else{
				$item	= $item[$item_key] ?: ' ';
			}

			$item	= $this->get_row_action($move_item,	$args+[
				'class'		=> 'move-item '.$item_type,
				'style'		=> ['color'=>$color],
				'title'		=> $item
			]).wpjam_tag('span', ['row-actions'], $this->get_row_action($move_item, $args+[
				'class'		=> 'move-item',
				'dashicon'	=> 'move',
				'wrap'		=> wpjam_tag('span', [$move_item]),
			]).$this->get_row_action($edit_item, $args+[
				'dashicon'	=> 'edit',
				'wrap'		=> wpjam_tag('span', [$edit_item]),
			]).$this->get_row_action($del_item, $args+[
				'class'		=> 'del-icon',
				'dashicon'	=> 'no-alt',
				'wrap'		=> wpjam_tag('span', [$del_item])
			]));

			$rendered->append('div', $attr, $item);
		}

		if(!$max_items || count($items) <= $max_items){
			$add_args	= ['id'=>$id, 'class'=>'add-item item'];

			if($item_type == 'image'){
				$add_args	+= ['dashicon'=>'plus-alt2', 'style'=>'width:'.$width.'px; height:'.$height.'px;'];
			}else{
				$add_args	+= ['title'=>'新增'];
			}

			$rendered->append($this->get_row_action($add_item, $add_args));
		}

		if($per_row){
			$style['width']	= ($per_row * ($width+30)).'px';
		}

		$class	= ['items', $item_type.'-list', ($sortable ? 'sortable' : '')];

		return $rendered->wrap('div', ['class'=>$class, 'style'=>$style]);
	}

	public function get_list_table(){
		if(wp_doing_ajax()){
			$this->prepare_items();
		}

		return wpjam_ob_get_contents([$this, 'list_table']);
	}

	public function list_table(){
		$this->views();

		ob_start();

		if($this->is_searchable()){
			$this->search_box('搜索', 'wpjam');
			echo wpjam_tag('br', ['clear']);
		}

		$this->display();

		echo wpjam_wrap_tag(ob_get_clean(), 'form', ['action'=>'#', 'id'=>'list_table_form', 'method'=>'POST']);
	}

	public function export_action(){
		return $this->callback('export');
	}
	
	public function ajax_response(){
		return $this->callback(wpjam_get_post_parameter('action_type'));
	}
	
	public function callback($type=''){
		if($type != 'export'){
			$referer	= wpjam_get_referer();
			$parts		= $referer ? parse_url($referer) : wp_die('非法请求');

			if($parts['host'] == $_SERVER['HTTP_HOST']){
				$_SERVER['REQUEST_URI']	= $parts['path'];
			}
		}

		if($type == 'query_item'){
			$id	= wpjam_get_post_parameter('id', ['default'=>'']);

			return ['type'=>'add',	'id'=>$id, 'data'=>$this->get_single_row($id)];
		}elseif($type == 'query_items'){
			foreach(wpjam_get_data_parameter() as $key=>$value){
				$_REQUEST[$key]	= $value;
			}

			return ['data'=>$this->get_list_table(), 'type'=>'list'];
		}else{
			if($type == 'export'){
				$action	= wpjam_get_parameter('export_action');
				$method	= 'get';
			}else{
				$action	= wpjam_get_post_parameter('list_action')	;
				$method	= 'post';
			}

			$object	= ($type && $action) ? $this->get_object($action) : null;

			if(!$object){
				wp_die('无效的操作');
			}

			$response	= $object->callback([
				'id'	=> wpjam_get_parameter('id',	['method'=>$method, 'default'=>'']),
				'bulk'	=> wpjam_get_parameter('bulk',	['method'=>$method, 'sanitize_callback'=>'intval']),
				'ids'	=> wpjam_get_parameter('ids',	['method'=>$method, 'sanitize_callback'=>'wp_parse_args', 'default'=>[]]),
				'data'	=> wpjam_get_data_parameter(),
			], $type);

			if($response['type'] == 'list'){
				$response['data']	= $this->get_list_table();
			}elseif($response['type'] == 'items'){
				if(isset($response['items'])){
					foreach($response['items'] as $id => &$item){
						$item['id']	= $id;

						if(!is_blank($id) && !in_array($item['type'], ['delete', 'append'])){
							$item['data']	= $this->get_single_row($id);
						}
					}
				}
			}elseif(!in_array($response['type'], ['append', 'redirect'])){
				if($this->layout == 'calendar'){
					if(!empty($response['data'])){
						$response['data']	= $this->render_dates($response['data']);
					}
				}else{
					if(!in_array($response['type'], ['delete', 'move', 'up', 'down', 'form'])){
						if($response['bulk']){
							$data	= [];

							$this->get_by_ids_by_model($response['ids']);

							foreach($response['ids'] as $_id){
								if(!is_blank($_id)){
									$data[$_id]	= $this->get_single_row($_id);
								}
							}

							$response['data']	= $data;
						}else{
							if(!is_blank($response['id'])){
								$response['data']	= $this->get_single_row($response['id']);
							}
						}
					}
				}
			}

			return $response;
		}
	}

	protected function parse_query_args($args){
		$filterable	= $this->get_filterable_fields_by_model();
		$query_vars	= $filterable ?: [];
		$query_vars	= array_merge($query_vars, ['orderby', 'order', 's']);

		foreach($query_vars as $query_var){
			$value	= wpjam_get_data_parameter($query_var);

			if(isset($value)){
				$args[$query_var]	= $value;
			}
		}

		return $args;
	}

	public function prepare_items(){
		foreach(['orderby', 'order'] as $key){
			$value	= wpjam_get_data_parameter($key);

			if($value){
				$_GET[$key] = $value;
			}
		}

		$per_page	= $this->get_per_page();
		$offset		= ($this->get_pagenum()-1) * $per_page;
		$args		= $this->parse_query_args(['number'=>$per_page, 'offset'=>$offset]);

		if(method_exists($this->model, 'query_data')){
			$result	= $this->query_data_by_model($args);	// 6.3 放弃
		}else{
			if(method_exists($this->model, 'query_items') || method_exists($this->model, 'list')){
				$method		= method_exists($this->model, 'query_items') ? 'query_items' : 'list';
				$parameters	= wpjam_get_callback_parameters([$this->model, $method]);
			}else{
				$parameters	= null;
			}

			if($parameters && count($parameters) >= 2){
				$result	= $this->query_items_by_model($per_page, $offset);
			}else{
				$result	= $this->query_items_by_model($args);
			}
		}

		$result			= wpjam_throw_if_error($result);
		$this->items	= $result['items'] ?? [];
		$total_items	= $result['total'] ?? count($this->items);

		if($total_items){
			$this->set_pagination_args([
				'total_items'	=> $total_items,
				'per_page'		=> $per_page
			]);
		}
	}

	protected function get_bulk_actions(){
		return wp_list_pluck($this->bulk_actions, 'title');
	}

	public function get_subtitle(){
		$subtitle	= $this->get_subtitle_by_model();
		$search		= wpjam_get_data_parameter('s');
		$subtitle 	.= $search ? ' “'.esc_html($search).'”的搜索结果' : '';
		$subtitle	= $subtitle ? wpjam_tag('span', ['subtitle'], $subtitle) : '';

		if($this->layout != 'left'){
			$subtitle	= ' '.$this->get_row_action('add', ['class'=>'page-title-action', 'subtitle'=>true]).$subtitle;
		}

		return $subtitle;
	}

	protected function get_table_classes() {
		$classes = parent::get_table_classes();

		return $this->fixed ? $classes : array_diff($classes, ['fixed']);
	}

	public function get_singular(){
		return $this->singular;
	}

	protected function get_primary_column_name(){
		$name	= $this->primary_column;

		if($this->columns && (!$name || !isset($this->columns[$name]))){
			return array_key_first($this->columns);
		}

		return $name;
	}

	protected function handle_row_actions($item, $column_name, $primary){
		return ($primary === $column_name && !empty($item['row_actions'])) ? $this->row_actions($item['row_actions'], false) : '';
	}

	public function row_actions($actions, $always_visible=true){
		return parent::row_actions($actions, $always_visible);
	}

	public function get_per_page(){
		if($this->per_page && is_numeric($this->per_page)){
			return $this->per_page;
		}

		$option		= get_screen_option('per_page', 'option');
		$default	= get_screen_option('per_page', 'default') ?: 50;

		return $option ? $this->get_items_per_page($option, $default) : $default;
	}

	public function get_columns(){
		if($this->bulk_actions){
			return array_merge(['cb'=>'checkbox'], $this->columns);
		}

		return $this->columns;
	}

	public function get_sortable_columns(){
		return $this->sortable_columns;
	}

	public function get_views(){
		return $this->views;
	}

	public function is_searchable(){
		return $this->search ?? $this->get_searchable_fields_by_model();
	}

	public function extra_tablenav($which='top'){
		$this->extra_tablenav_by_model($which);

		do_action(wpjam_get_filter_name($this->plural, 'extra_tablenav'), $which);

		if($which == 'top'){
			$overall	= '';

			foreach($this->overall_actions as $action){
				$overall	.= $this->get_row_action($action, ['class'=>'button']);
			}

			echo $overall ? wpjam_tag('div', ['alignleft', 'actions', 'overallactions'], $overall) : '';
		}
	}

	public function current_action(){
		return wpjam_get_request_parameter('list_action', ['default'=>parent::current_action()]);
	}

	public function filter_parameter_default($default, $name){
		return $this->defaults[$name] ?? $default;
	}
}

class WPJAM_Left_List_Table extends WPJAM_List_Table{
	public function col_left(){
		$result	= $this->col_left_by_model();

		if($result && is_array($result)){
			$args	= wp_parse_args($result, [
				'total_items'	=> 0,
				'total_pages'	=> 0,
				'per_page'		=> 10,
			]);

			$total_pages	= $args['total_pages'] ?: ($args['per_page'] ? ceil($args['total_items']/$args['per_page']) : 0);

			if($total_pages){
				$pages	= [];

				foreach(['prev', 'text', 'next', 'goto'] as $key){
					$pages[$key]	= $this->get_left_page_link($key, $total_pages);
				}

				$class	= ['tablenav-pages'];
				$class	= $total_pages < 2 ? array_merge($class, ['one-page']) : $class;

				echo wpjam_tag('span', ['left-pagination-links'], join(' ', array_filter($pages)))->wrap('div', $class)->wrap('div', ['tablenav', 'bottom']);
			}
		}
	}

	public function callback($type=''){
		if($type == 'left'){
			return ['data'=>$this->get_list_table(), 'left'=>$this->get_col_left(), 'type'=>'left'];
		}

		return parent::callback($type);
	}

	protected function get_left_page_link($type, $total){
		$current	= (int)wpjam_get_data_parameter('left_paged') ?: 1;

		if($type == 'text'){
			return wpjam_tag('span', ['current-page'], $current)
			->after(' / ')
			->after('span', ['total-pages'], number_format_i18n($total))
			->wrap('span', ['tablenav-paging-text']);
		}elseif($type == 'goto'){
			if($total < 2){
				return '';
			}

			return wpjam_tag('input', [
				'type'	=> 'text',
				'name'	=> 'paged',
				'value'	=> $current,
				'size'	=> strlen($total),
				'id'	=> 'left-current-page-selector',
				'class'	=> 'current-page',
				'aria-describedby'	=> 'table-paging',
			])->after('a', ['left-pagination', 'button', 'goto'], '&#10132;')
			->wrap('span', ['paging-input']);
		}elseif($type == 'prev'){
			$value	= 1;
			$paged	= max(1, $current - 1);
			$text	= '&lsaquo;';
			$reader	= __('Previous page');
		}else{
			$value	= $total;
			$paged	= min($value, $current + 1);
			$text	= '&rsaquo;';
			$reader	= __('Next page');
		}

		$attr	= ['aria-hidden'=>'true'];

		if($value == $current){
			$attr['class']	= ['tablenav-pages-navspan', 'button', 'disabled'];
		}

		$tag	= wpjam_tag('span', $attr, $text);

		if($value != $current){
			$tag->before('span', ['screen-reader-text'], $reader)->wrap('a', ['data'=>['left_paged'=>$paged], 'class'=>['left-pagination', 'button', $type.'-page']]);
		}

		return $tag;
	}

	public function get_col_left(){
		return wpjam_ob_get_contents([$this, 'col_left']);
	}
}

class WPJAM_Calendar_List_Table extends WPJAM_List_Table{
	public function __get($name){
		if($name == 'year'){
			$year	= (int)wpjam_get_data_parameter('year') ?: wpjam_date('Y');

			return max(min($year, 2200), 1970);
		}elseif($name == 'month'){
			$month	= (int)wpjam_get_data_parameter('month') ?: wpjam_date('m');

			return max(min($month, 12), 1);
		}

		return parent::__get($name);
	}

	public function prepare_items(){
		$args	= ['year'=>$this->year, 'month'=>$this->month, 'layout'=>$this->layout];
		$args	= $this->parse_query_args($args);

		$this->items	= wpjam_throw_if_error($this->query_items_by_model($args));
	}

	public function render_date($raw, $date){
		if(wp_is_numeric_array($raw)){
			foreach($raw as $key => &$_item){
				$_item	= $this->parse_item($_item);

				if(!$_item){
					unset($raw[$key]);
				}
			}
		}else{
			$raw	= $this->parse_item($raw);
		}

		$row_actions	= [];

		if(wpjam_is_assoc_array($raw)){
			$row_actions	= $this->get_row_actions($this->get_by_primary_key($raw));
		}else{
			$row_actions	= ['add'=>$this->get_row_action('add', ['data'=>['date'=>$date]])];
		}

		$links	= wpjam_tag('div', ['row-actions', 'alignright']);

		foreach($row_actions as $action => $link){
			$links->append('span', [$action], $link)->append(' ');
		}

		$item	= $this->render_date_by_model($raw, $date) ?: '';
		$day	= explode('-', $date)[2];
		$class	= $date == wpjam_date('Y-m-d') ? ['day', 'today'] :  ['day'];

		return $links->before('span', [$class], $day)
		->wrap('div', ['date-meta'])
		->after('div', ['date-content'], $item);
	}

	public function render_dates($result){
		$dates	= $result['dates'] ?? $result;
		$data	= [];

		foreach($dates as $date => $item){
			$data[$date]	= $this->render_date($item, $date);
		}

		return $data;
	}

	public function display(){
		$this->display_tablenav('top');

		$year	= $this->year;
		$month	= zeroise($this->month, 2);
		$m_ts	= mktime(0, 0, 0, $this->month, 1, $this->year);	// 每月开始的时间戳
		$days	= date('t', $m_ts);
		$start	= (int)get_option('start_of_week');
		$pad	= calendar_week_mod(date('w', $m_ts) - $start);
		$tr		= wpjam_tag('tr');

		for($wd_count = 0; $wd_count <= 6; $wd_count++){
			$weekday	= ($wd_count + $start) % 7;
			$name		= $this->get_weekday_by_locale($weekday);

			$tr->append('th', [
				'scope'	=> 'col',
				'class'	=> in_array($weekday, [0, 6]) ? 'weekend' : 'weekday',
				'title'	=> $name
			], $this->get_weekday_abbrev_by_locale($name));
		}

		$thead	= wpjam_tag('thead')->append(wp_clone($tr));
		$tfoot	= wpjam_tag('tfoot')->append(wp_clone($tr));
		$tbody	= wpjam_tag('tbody', ['id'=>'the-list', 'data'=>['wp-lists'=>'list:'.$this->singular]]);
		$tr		= wpjam_tag('tr');

		if($pad){
			$tr->append('td', ['colspan'=>$pad, 'class'=>'pad']);
		}

		for($day=1; $day<=$days; ++$day){
			$date	= $year.'-'.$month.'-'.zeroise($day, 2);
			$item	= $this->items[$date] ?? [];
			$item	= $this->render_date($item, $date);

			$tr->append('td', [
				'id'	=> 'date_'.$date,
				'class'	=> in_array($pad+$start, [0, 6, 7]) ? 'weekend' : 'weekday'
			], $item);

			$pad++;

			if($pad%7 == 0){
				$tbody->append($tr);

				$pad	= 0;
				$tr	= wpjam_tag('tr');
			}
		}

		if($pad){
			$tr->append('td', ['colspan'=>(7-$pad), 'class'=>'pad']);

			$tbody->append($tr);
		}

		echo $tbody->before($tfoot)->before($thead)->wrap('table', ['cellpadding'=>10, 'cellspacing'=>0, 'class'=>'widefat fixed']);

		$this->display_tablenav('bottom');
	}

	public function extra_tablenav($which='top'){
		if($which == 'top'){
			echo wpjam_tag('h2', [], sprintf(__('%1$s %2$d'), $this->get_month_by_locale($this->month), $this->year));
		}

		parent::extra_tablenav($which);
	}

	public function pagination($which){
		$pagination = wpjam_tag('span', ['pagination-links']);

		foreach(['prev', 'current', 'next'] as $type){
			$pagination->append($this->get_month_link($type));
		}

		echo $pagination->wrap('div', ['tablenav-pages']);
	}

	public function get_month_link($type=''){
		if($type == 'prev'){
			$text	= '&lsaquo;';
			$class	= 'prev-month';

			if($this->month == 1){
				$year	= $this->year - 1;
				$month	= 12;
			}else{
				$year	= $this->year;
				$month	= $this->month - 1;
			}
		}elseif($type == 'next'){
			$text	= '&rsaquo;';
			$class	= 'next-month';

			if($this->month == 12){
				$year	= $this->year + 1;
				$month	= 1;
			}else{
				$year	= $this->year;
				$month	= $this->month + 1;
			}
		}else{
			$text	= '今日';
			$class	= 'current-month';
			$year	= wpjam_date('Y');
			$month	= wpjam_date('m');
		}

		if($type){
			$reader	= sprintf(__('%1$s %2$d'), $this->get_month_by_locale($month), $year);
			$text	= wpjam_tag('span', ['aria-hidden'=>'true'], $text)->before('span', ['screen-reader-text'], $reader);
		}

		return $this->get_filter_link(['year'=>$year, 'month'=>$month], $text, $class.' button');
	}

	public function get_views(){
		return [];
	}

	public function get_bulk_actions(){
		return [];
	}

	public function is_searchable(){
		return false;
	}
}

class WPJAM_List_Table_Action extends WPJAM_Admin_Action{
	public function __get($key){
		$value	= parent::__get($key);

		if(is_null($value)){
			if(in_array($key, ['primary_key', 'layout', 'model', 'data_type', 'capability', 'next_actions']) 
				|| ($this->data_type && $this->data_type == $key)
			){
				return get_screen_option('list_table', $key);
			}elseif($key == 'page_title'){
				return $this->title ? wp_strip_all_tags($this->title.get_screen_option('list_table', 'title')) : '';
			}elseif($key == 'response'){
				return $this->overall ? 'list' : $this->name;
			}elseif($key == 'row_action'){
				return true;
			}elseif($key == 'width'){
				return 720;
			}
		}

		return $value;
	}

	public function __call($method, $args){
		if($method == 'get_next_action'){
			return self::get($this->next);
		}elseif($method == 'get_prev_action'){
			$prev	= $this->prev ?: array_search($this->name, $this->next_actions);

			return self::get($prev);
		}else{
			trigger_error('undefined_method「'.$method.'」');
		}
	}

	public function jsonSerialize(){
		return array_filter($this->generate_data_attr(['bulk'=>true]));
	}

	protected function parse_id_arg($args){
		if(wpjam_is_assoc_array($args)){
			return $args['bulk'] ? $args['ids'] : $args['id'];
		}else{
			return $args;
		}
	}

	public function callback($args, $type=null){
		$id		= $args['id'];
		$ids	= $args['ids'];
		$data	= $args['data'];

		if(!$type){
			if($args['bulk']){
				$callback	= $args['bulk_callback'];
				$cb_args	= [$ids, $data];

				if(!$callback && method_exists($this->model, 'bulk_'.$this->name)){
					$callback	= [$this->model, 'bulk_'.$this->name];
				}

				if(!$callback){
					return $this->bulk_callback($args);
				}
			}else{
				$callback	= $args['callback'];
				$cb_args	= [$id, $data];

				if(!$callback){
					return $this->call_by_model($id, $data, $args['fields']);
				}

				if($this->overall){
					$cb_args	= [$data];
				}elseif($this->response == 'add' && !is_null($data)){
					$parameters	= wpjam_get_callback_parameters($callback);

					if(count($parameters) == 1 || $parameters[0]->name == 'data'){
						$cb_args	= [$data];
					}else{
						trigger_error('「response = ADD」回调函数参数最好只用一个');
					}
				}
			}

			$errmsg	= '「'.$this->title.'」的回调函数';

			if(!is_callable($callback)){
				wp_die($errmsg.'无效');
			}

			$cb_args	= array_merge($cb_args, [$this->name, $args['submit_name']]);
			$result		= wpjam_try($callback, ...$cb_args);

			if(is_null($result)){
				wp_die($errmsg.'没有正确返回');
			}

			return $result;
		}

		if(!$this->is_allowed($args)){
			wp_die('access_denied');
		}

		$form_args	= $args;
		$response	= [
			'list_action'	=> $this->name,
			'page_title'	=> $this->page_title,
			'type'			=> $this->response,
			'layout'		=> $this->layout,
			'width'			=> $this->width,
			'bulk'			=> &$args['bulk'],
			'id'			=> &$id,
			'ids'			=> $ids
		];

		if($type == 'form'){
			return array_merge($response, ['type'=>'form',	'form'=>$this->get_form($form_args, $type)]);
		}

		if(!$this->verify_nonce($args)){
			wp_die('invalid_nonce');
		}

		if($args['bulk'] === 2){
			$args['bulk']	= 0;
		}

		$cb_keys	= ['callback', 'bulk_callback'];

		foreach($cb_keys as $cb_key){
			$args[$cb_key]	= $this->$cb_key;
		}

		$submit_name	= $fields = null;

		if($type == 'submit'){
			$fields	= $this->get_fields($args, true);
			$fields	= $fields ? wpjam_fields($fields) : null;
			$data	= $fields ? $fields->validate($data) : $data;

			if($this->response == 'form'){
				$form_args['data']	= $data;
			}else{
				$form_args['data']	= wpjam_get_post_parameter('defaults',	['sanitize_callback'=>'wp_parse_args', 'default'=>[]]);
				$submit_name		= wpjam_get_post_parameter('submit_name', ['default'=>$this->name]);
				$submit_button		= $this->get_submit_button($args, $submit_name);
				$response['type']	= $submit_button['response'];

				foreach($cb_keys as $cb_key){
					if(!empty($submit_button[$cb_key])){
						$args[$cb_key]	= $submit_button[$cb_key];
					}
				}
			}
		}

		if($this->response == 'form'){
			$result	= null;
		}else{
			$result	= $this->callback(array_merge($args, compact('data', 'fields', 'submit_name')));

			if(is_array($result) && !empty($result['errmsg']) && $result['errmsg'] != 'ok'){ // 有些第三方接口返回 errmsg ： ok
				$response['errmsg'] = $result['errmsg'];
			}elseif($type == 'submit'){
				$response['errmsg'] = $submit_button['text'].'成功';
			}
		}

		if(is_array($result) && array_intersect(array_keys($result), ['type', 'bulk', 'ids', 'id', 'items'])){
			$response	= array_merge($response, $result);
		}elseif(in_array($response['type'], ['add', 'duplicate']) || in_array($this->name, ['add', 'duplicate'])){
			if(is_array($result)){
				$dates	= $result['dates'] ?? $result;
				$date	= current($dates);
				$id		= (is_array($date) && $this->primary_key) ? $date[$this->primary_key] : null;

				if(is_null($id)){
					wp_die('无效的 ID');
				}
			}else{
				$id	= $result;
			}
		}

		if($response['type'] == 'append'){
			return array_merge($response, ['data'=>$result]);
		}elseif($response['type'] == 'redirect'){
			return is_string($result) ? array_merge($response, ['url'=>$result]) : $response;
		}else{
			if($this->layout == 'calendar'){
				if(is_array($result)){
					$response['data']	= $result;
				}
			}else{
				if(!$response['bulk'] && in_array($response['type'], ['add', 'duplicate'])){
					$form_args['id'] = $id;
				}
			}
		}

		if($type == 'submit'){
			if($response['type'] == 'delete'){
				$response['dismiss']	= true;
			}else{
				if($this->next){
					$response['next']		= $this->next;
					$response['page_title']	= $this->get_next_action()->page_title;

					if($response['type'] == 'form'){
						$response['errmsg']	= '';
					}
				}elseif($this->dismiss){
					$response['dismiss']	= true;
				}
			}

			if(empty($response['dismiss'])){
				$response['form']	= $this->get_form($form_args, $type);
			}
		}

		return $response;
	}

	protected function bulk_callback($args){
		$return	= wpjam_array();

		foreach($args['ids'] as $id){
			$result	= $this->callback(array_merge($args, ['id'=>$id, 'bulk'=>false]));

			if(is_array($result)){
				$return->merge($result);
			}
		}

		return $return->get(null) ?: $result;
	}

	protected function call_by_model($id, $data, $fields=null){
		$method	= $this->name;

		if($method == 'add'){
			$method	= 'insert';
		}elseif($method == 'edit'){
			$method	= 'update';
		}elseif(in_array($method, ['up', 'down'], true)){
			$method	= 'move';
		}elseif($method == 'duplicate' && !$this->direct){
			$method	= 'insert';
		}

		$errmsg		= '「'.$this->model.'」未定义相应的操作';
		$defaults	= $fields ? $fields->get_defaults() : null;
		$callback	= [$this->model, $method];

		if($this->overall || $method == 'insert' || $this->response == 'add'){
			if(!is_callable($callback)){
				wp_die($errmsg);
			}

			$cb_args	= [$data];
		}else{
			if(method_exists($this->model, $method)){
				$cb_args	= ($this->direct && is_null($data)) ? [$id] : [$id, $data];
				$callback 	= wpjam_parse_method($this->model, $method, $cb_args);
			}elseif(!$this->meta_type && method_exists($this->model, '__callStatic')){
				$cb_args	= [$id, $data];
			}elseif(method_exists($this->model, 'update_callback')){
				$cb_args	= [$id, $data, $defaults];
				$callback	= wpjam_parse_method($this->model, 'update_callback', $cb_args);
			}else{
				$meta_type	= get_screen_option('meta_type');

				if(!$meta_type){
					wp_die($errmsg);
				}

				$cb_args	= [$meta_type, $id, $data, $defaults];
				$callback	= 'wpjam_update_metadata';
			}
		}

		$result	= wpjam_try($callback, ...$cb_args);

		return is_null($result) ? true : $result;
	}

	protected function show_if($id){
		try{
			$show_if	= $this->show_if;

			if($show_if){
				if(is_callable($show_if)){
					return wpjam_try($show_if, $id, $this->name);
				}elseif(is_array($show_if) && $id){
					return wpjam_show_if($this->get_data($id), $show_if);
				}
			}

			return true;
		}catch(Exception $e){
			return false;
		}
	}

	public function is_allowed($id=0){
		$id	= $this->parse_id_arg($id);

		if($this->capability != 'read'){
			foreach((array)$id as $_id){
				if(!current_user_can($this->capability, $_id, $this->name)){
					return false;
				}
			}
		}

		return true;
	}

	public function get_data($id, $include_prev=false, $by_callback=false){
		$data	= null;

		if($include_prev || $by_callback){
			$callback	= $this->data_callback;

			if($callback){
				if(!is_callable($callback)){
					wp_die($this->title.'的 data_callback 无效');
				}

				$data	= wpjam_try($callback, $id, $this->name);
			}
		}

		if($include_prev){
			$prev	= $this->get_prev_action();
			$prev	= $prev ? $prev->get_data($id, true) : [];

			return $data ? array_merge($prev, $data) : $prev;
		}else{
			if($by_callback && !is_null($data)){
				return $data;
			}

			if(is_callable([$this->model, 'get'])){
				$data	= wpjam_try([$this->model, 'get'], $id);

				return $data ?: ($id ? wp_die('无效的 ID') : []);
			}

			wp_die($this->model.'->get() 未定义');
		}
	}

	public function get_form($args=[], $type=''){
		$object	= $this;
		$prev	= null;

		if($type == 'submit' && $this->next){
			if($this->response == 'form'){
				$prev	= $this;
			}

			$object	= $this->get_next_action();
		}

		$id	= $args['bulk'] ? 0 : $args['id'];

		$fields_args	= ['id'=>$id, 'data'=>$args['data']];

		if(!$args['bulk']){
			if($type != 'submit' || $this->response != 'form'){
				$data	= $object->get_data($id, false, true);
				$data	= is_array($data) ? array_merge($args['data'], $data) : $data;

				$fields_args['data']	= $data;
			}

			$fields_args['meta_type']	= get_screen_option('meta_type');

			if($object->value_callback){
				$fields_args['value_callback']	= $object->value_callback;
			}elseif(method_exists($object->model, 'value_callback')){
				$fields_args['value_callback']	= [$object->model, 'value_callback'];
			}
		}

		$fields	= $object->get_fields($args);
		$fields	= wpjam_fields($fields)->render($fields_args);
		$prev	= $prev ?: $object->get_prev_action();
		$button	= '';

		if($prev && !$args['bulk']){
			$button	.= wpjam_tag('input', [
				'type'	=> 'button',
				'value'	=> '上一步',
				'class'	=> ['list-table-action', 'button','large'],
				'data'	=> $prev->generate_data_attr($args)
			]);

			if($type == 'form'){
				$args['data']	= array_merge($args['data'], $prev->get_data($id, true));
			}
		}

		if($object->next && $object->response == 'form'){
			$button	.= get_submit_button('下一步', 'primary', 'next', false);
		}else{
			$button	.= $object->get_submit_button($args);
		}

		$form	= wpjam_wrap_tag($fields, 'form', [
			'method'	=> 'post',
			'action'	=> '#',
			'id'		=> 'list_table_action_form',
			'data'		=> $object->generate_data_attr($args, 'form')
		]);

		if($button){
			$form->append($button, 'p', ['submit']);
		}

		return $form;
	}

	public function get_fields($args, $include_prev=false){
		if($this->direct){
			return [];
		}

		$fields	= $this->fields;
		$id_arg	= $this->parse_id_arg($args);

		if($fields && is_callable($fields)){
			$fields	= wpjam_try($fields, $id_arg, $this->name);
		}

		$fields	= $fields ?: wpjam_try([$this->model, 'get_fields'], $this->name, $id_arg);
		$fields	= is_array($fields) ? $fields : [];

		if($include_prev){
			$prev	= $this->get_prev_action();

			if($prev){
				$fields	= array_merge($fields, $prev->get_fields($id_arg, true));
			}
		}

		if(method_exists($this->model, 'filter_fields')){
			$fields	= wpjam_try([$this->model, 'filter_fields'], $fields, $id_arg, $this->name);
		}else{
			if(!in_array($this->name, ['add', 'duplicate']) && $this->primary_key && isset($fields[$this->primary_key])){
				$fields[$this->primary_key]['type']	= 'view';
			}
		}

		return $fields;
	}

	public function get_submit_button($args, $name=null, $render=null){
		$id_arg	= $this->parse_id_arg($args);
		$render	= $render ?? is_null($name);

		if(!is_null($this->submit_text)){
			$button	= $this->submit_text;

			if($button && is_callable($button)){
				$button	= wpjam_try($button, $id_arg, $this->name);
			}
		}else{
			$button = wp_strip_all_tags($this->title) ?: $this->page_title;
		}

		return $this->parse_submit_button($button, $name, $render);
	}

	public function get_row_action($args=[]){
		$args	= wp_parse_args($args, ['id'=>0, 'data'=>[], 'bulk'=>false, 'ids'=>[], 'class'=>[], 'style'=>'', 'title'=>'']);

		if(($this->layout == 'calendar' && !$this->calendar) || !$this->show_if($args['id'])){
			return '';
		}

		if(!$this->is_allowed($args['id'])){
			$fallback	= array_get($args, 'fallback');

			return $fallback === true ? $args['title'] : (string)$fallback;
		}

		$tag	= $args['tag'] ?? 'a';
		$attr	= ['title'=>$this->page_title, 'style'=>$args['style'], 'class'=>(array)$args['class']];

		if($this->redirect){
			$tag	= 'a';

			$attr['href']		= str_replace('%id%', $args['id'], $this->redirect);
			$attr['class'][]	= 'list-table-redirect';
		}elseif($this->export){
			$tag	= 'a';
			$nonce	= $this->create_nonce($args);
			$query	= ['export_action'=>$this->name, '_wpnonce'=>$this->create_nonce($args)];

			if(!$this->overall){
				$query['id']	= $args['id'];
			}

			$attr['href']		= add_query_arg($query, $GLOBALS['current_admin_url']);
			$attr['class'][]	= 'list-table-redirect';
		}elseif($this->filter){
			$item	= (array)$this->get_data($args['id']);
			$data	= $this->data ?: [];
			$data	= array_merge($data, wp_array_slice_assoc($item, wp_parse_list($this->filter)));

			$attr['data']		= ['filter'=>wp_parse_args($args['data'], $data)];
			$attr['class'][]	= 'list-table-filter';
		}else{
			$attr['data']		= $this->generate_data_attr($args);
			$attr['class'][]	= 'list-table-'.(in_array($this->response, ['move', 'move_item']) ? 'move-' : '').'action';
		}

		if(!empty($args['dashicon'])){
			$title	= wpjam_tag('span', ['dashicons dashicons-'.$args['dashicon']]);
		}elseif(!is_blank($args['title'])){
			$title	= $args['title'];
		}elseif($this->dashicon && empty($args['subtitle']) && ($this->layout == 'calendar' || !$this->title)){
			$title	= wpjam_tag('span', ['dashicons dashicons-'.$this->dashicon]);
		}else{
			$title	= $this->title ?: $this->page_title;
		}

		$action	= (string)wpjam_tag($tag, $attr, $title);
		$wrap	= array_get($args, 'wrap');

		return $wrap ? wpjam_wrap($action, $wrap, $this->name) : $action;
	}

	public function generate_data_attr($args=[], $type='button'){
		$args	= wp_parse_args($args, ['id'=>0, 'data'=>[], 'bulk'=>false, 'ids'=>[]]);
		$data	= $this->data ?: [];
		$attr	= [
			'action'	=> $this->name,
			'nonce'		=> $this->create_nonce($args),
			'data'		=> wp_parse_args($args['data'], $data),
		];

		if($args['bulk']){
			$attr['ids']	= $args['ids'];
			$attr['bulk']	= $this->bulk;
			$attr['title']	= $this->title;
		}else{
			$attr['id']		= $args['id'];
		}

		if($type == 'button'){
			$attr['direct']		= $this->direct;
			$attr['confirm']	= $this->confirm;
		}else{
			$attr['next']		= $this->next;
		}

		return $attr;
	}

	protected static function get_config($key){
		if(in_array($key, ['data_type', 'orderby'])){
			return true;
		}
	}
}

class WPJAM_List_Table_Column extends WPJAM_Register{
	public function callback($id, $value){
		$callback	= $this->column_callback ?: $this->callback;

		if($callback && is_callable($callback)){
			return wpjam_call($callback, $id, $this->name, $value);
		}

		return $this->parse_value($value);
	}

	protected function parse_value($value){
		$parsed	= $value;

		if($this->options){
			if(is_array($value)){
				return implode(',', array_map([$this, 'parse_value'], $value));
			}

			$options	= wpjam_parse_options($this->options);
			$parsed		= $options[$value] ?? $value;
		}

		if(isset($this->filterable)){
			$filterable	= $this->filterable;
		}else{
			$list_table	= get_screen_option('list_table');
			$fields		= $list_table->get_filterable_fields_by_model();
			$filterable	= $fields && in_array($this->name, $fields);
		}

		if($filterable && !has_shortcode($value, 'filter')){
			return '[filter '.$this->name.'="'.$value.'"]'.$parsed.'[/filter]';
		}

		return $parsed;
	}

	public function get_style(){
		$style	= $this->column_style ?: $this->style;

		if($style && !preg_match('/\{([^\}]*)\}/', $style)){
			return '.manage-column.column-'.$this->name.'{ '.$style.' }';
		}

		return $style;
	}

	protected static function get_config($key){
		if(in_array($key, ['data_type', 'orderby'])){
			return true;
		}
	}
}

class WPJAM_List_Table_View extends WPJAM_Register{
	public function get_link(){
		if($this->view){
			return $this->view;
		}

		$callback	= $this->callback;

		if($callback && is_callable($callback)){
			$result	= wpjam_call($callback, $this->name);

			if(is_wp_error($result)){
				return null;
			}elseif(!is_array($result)){
				return $result;
			}

			$this->update_args($result);
		}

		if($this->label){
			if(is_numeric($this->count)){
				$this->label	.= wpjam_tag('span', ['count'], '（'.$this->count.'）');
			}

			$this->filter	= $this->filter ?? [];
			$this->class	= $this->class ?? $this->parse_class();

			return $this->get_args();
		}

		return null;
	}

	protected function parse_class(){
		foreach($this->filter as $key => $value){
			$current	= wpjam_get_data_parameter($key);

			if((is_null($value) && !is_null($current)) || $current != $value){
				return '';
			}
		}

		return 'current';
	}

	protected static function get_config($key){
		if(in_array($key, ['data_type', 'orderby'])){
			return true;
		}
	}
}