<?php
class WPJAM_Builtin_Page{
	protected $screen;

	protected function __construct($screen){
		$this->screen	= $screen;
	}

	public function __get($key){
		$screen	= $this->screen;

		if(isset($screen->$key)){
			return $screen->$key;
		}else{
			$object	= $screen->get_option('object');

			return $object ? $object->$key : null;
		}
	}

	public function __call($method, $args){
		$object	= $this->screen->get_option('object');

		if($object){
			return call_user_func_array([$object, $method], $args);
		}
	}

	public static function init($screen){
		$admin_url	= set_url_scheme('http://'.$_SERVER['HTTP_HOST'].parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

		if($GLOBALS['plugin_page']){
			$admin_url	= add_query_arg(['page' => $GLOBALS['plugin_page']], $admin_url);
		}else{
			$args	= [];

			foreach(['taxonomy', 'post_type'] as $key){
				if($screen->$key && isset($_REQUEST[$key])){
					$args[$key]	= $_REQUEST[$key];
				}
			}

			if($args){
				$admin_url	= add_query_arg($args, $admin_url);
			}
		}

		$GLOBALS['current_admin_url']	= $admin_url;

		if(in_array($screen->base, ['edit', 'upload', 'post', 'term', 'edit-tags'])){
			if(in_array($screen->base, ['edit', 'upload', 'post'])){
				$object	= wpjam_get_post_type_object($screen->post_type);
			}elseif(in_array($screen->base, ['term', 'edit-tags'])){
				$object	= wpjam_get_taxonomy_object($screen->taxonomy);
			}

			if(!$object){
				return;
			}

			$screen->add_option('object', $object);
		}

		wpjam_add_admin_load([
			[
				'base'	=> 'post',
				'model'	=> 'WPJAM_Post_Builtin_Page'
			],
			[
				'base'	=> ['edit', 'upload'],
				'model'	=> 'WPJAM_Posts_List_Table'
			],
			[
				'base'	=> 'users',
				'model'	=> 'WPJAM_Users_List_Table'
			],
			[
				'base'	=> ['term', 'edit-tags'],
				'model'	=> 'WPJAM_Term_Builtin_Page'
			],
			[
				'base'	=> 'edit-tags',
				'model'	=> 'WPJAM_Terms_List_Table'
			],
		]);

		wpjam_admin_load($screen);
	}

	public static function load($screen){
		return new static($screen);
	}
}

class WPJAM_Post_Builtin_Page extends WPJAM_Builtin_Page{
	protected function __construct($screen){
		parent::__construct($screen);

		$style	= [];

		foreach($this->get_taxonomies() as $tax_object){
			if($tax_object->levels == 1){
				$style[]	= '#new'.$tax_object->name.'_parent{display:none;}';
			}
		}

		if($style){
			wp_add_inline_style('list-tables', "\n".implode("\n", $style));
		}

		$edit_form_hook	= $GLOBALS['typenow'] == 'page' ? 'edit_page_form' : 'edit_form_advanced';

		add_action($edit_form_hook,			[$this, 'on_edit_form'], 99);
		add_action('add_meta_boxes',		[$this, 'on_add_meta_boxes'], 10, 2);
		add_action('wp_after_insert_post',	[$this, 'on_after_insert_post'], 999, 2);

		add_filter('post_updated_messages',		[$this, 'filter_updated_messages']);
		add_filter('redirect_post_location',	[$this, 'filter_redirect_location']);
		add_filter('admin_post_thumbnail_html',	[$this, 'filter_admin_thumbnail_html']);

		add_filter('post_edit_category_parent_dropdown_args',	[$this, 'filter_edit_category_parent_dropdown_args']);
	}

	public function on_edit_form($post){	// 下面代码 copy 自 do_meta_boxes
		$meta_boxes		= $GLOBALS['wp_meta_boxes'][$this->id]['wpjam'] ?? [];
		$tab_title		= wpjam_tag('ul');
		$tab_content	= wpjam_tag('div', ['inside']);
		$tab_count		= 0;

		foreach(['high', 'core', 'default', 'low'] as $priority){
			if(empty($meta_boxes[$priority])){
				continue;
			}

			foreach((array)$meta_boxes[$priority] as $meta_box){
				if(empty($meta_box['id']) || empty($meta_box['title'])){
					continue;
				}

				$tab_count++;

				$meta_id	= 'tab_'.$meta_box['id'];

				$tab_title->append('li', [], wpjam_tag('a', ['class'=>'nav-tab', 'href'=>'#'.$meta_id], $meta_box['title']));
				$tab_content->append('div', ['id'=>$meta_id], wpjam_ob_get_contents($meta_box['callback'], $post, $meta_box));
			}
		}

		if(!$tab_count){
			return;
		}

		if($tab_count == 1){
			$tab_title	= wpjam_tag('h2', ['hndle'], strip_tags($tab_title))->wrap('div', ['postbox-header']);
		}else{
			$tab_title->wrap('h2', ['nav-tab-wrapper']);
		}

		echo $tab_title->after($tab_content)->wrap('div', ['id'=>'wpjam', 'class'=>['postbox','tabs']])->wrap('div', ['id'=>'wpjam-sortables']);
	}

	public function meta_box_cb($post, $meta_box){
		$object	= array_shift($meta_box['args']);

		echo $object->summary ? wpautop($object->summary) : '';

		$id		= $GLOBALS['current_screen']->action == 'add' ? false : $post->ID;
		$type	= $object->context == 'side' ? 'list' : 'table';

		$object->render($id, ['fields_type'=>$type]);
	}

	public function on_add_meta_boxes($post_type, $post){
		$context	= use_block_editor_for_post_type($post_type) ? 'normal' : 'wpjam';

		foreach(wpjam_get_post_options($this->post_type, ['list_table'=>false]) as $object){
			$context	= $object->context ?: $context;
			$callback	= $object->meta_box_cb ?: [$this, 'meta_box_cb'];

			add_meta_box($object->name, $object->title, $callback, $post_type, $context, $object->priority, [$object]);
		}
	}

	public function on_after_insert_post($post_id, $post){
		// 非 POST 提交不处理
		// 自动草稿不处理
		// 自动保存不处理
		// 预览不处理
		if($_SERVER['REQUEST_METHOD'] != 'POST'
			|| $post->post_status == 'auto-draft'
			|| (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			|| (!empty($_POST['wp-preview']) && $_POST['wp-preview'] == 'dopreview')
		){
			return;
		}

		foreach(wpjam_get_post_options($this->post_type, ['list_table'=>false]) as $object){
			$result	= $object->callback($post_id);

			wpjam_die_if_error($result);
		}
	}

	public function filter_updated_messages($messages){
		$key	= $this->hierarchical ? 'page' : 'post';

		if(isset($messages[$key])){
			$search		= $key == 'post' ? '文章':'页面';
			$replace	= $this->labels->name;

			foreach($messages[$key] as &$message){
				$message	= str_replace($search, $replace, $message);
			}
		}

		return $messages;
	}

	public function filter_admin_thumbnail_html($content){
		$size	= $this->thumbnail_size;

		return $content.($size ? wpautop('尺寸：'.$size) : '');
	}

	public function filter_edit_category_parent_dropdown_args($args){
		$object	= wpjam_get_taxonomy_object($args['taxonomy']);
		$levels	= $object ? (int)$object->levels : 0;

		if($levels == 1){
			$args['parent']	= -1;
		}elseif($levels > 1){
			$args['depth']	= $levels - 1;
		}

		return $args;
	}

	public function filter_redirect_location($location){
		if(parse_url($location, PHP_URL_FRAGMENT)){
			return $location;
		}

		if($fragment = parse_url(wp_get_referer(), PHP_URL_FRAGMENT)){
			return $location.'#'.$fragment;
		}

		return $location;
	}
}

class WPJAM_Term_Builtin_Page extends WPJAM_Builtin_Page{
	protected function __construct($screen){
		parent::__construct($screen);

		add_filter('term_updated_messages',			[$this, 'filter_updated_messages']);
		add_filter('taxonomy_parent_dropdown_args',	[$this, 'filter_parent_dropdown_args'], 10, 3);

		if($this->base == 'edit-tags'){
			if(wp_doing_ajax()){
				if($_POST['action'] == 'add-tag'){
					add_filter('pre_insert_term',	[$this, 'filter_pre_insert'], 10, 2);
					add_action('created_term',		[$this, 'on_created'], 10, 3);
				}
			}else{
				add_action('edited_term',	[$this, 'on_edited'], 10, 3);
			}

			add_action($GLOBALS['taxnow'].'_add_form_fields',	[$this, 'on_add_form_fields']);
		}else{
			add_action($GLOBALS['taxnow'].'_edit_form_fields',	[$this, 'on_edit_form_fields']);
		}
	}

	public function get_form_fields($action, $args){
		foreach(wpjam_get_term_options($this->taxonomy, ['action'=>$action, 'list_table'=>false]) as $object){
			$object->render($args['id'], wp_parse_args($args, $object->to_array()));
		}
	}

	protected function update_data($action, $term_id=null){
		foreach(wpjam_get_term_options($this->taxonomy, ['action'=>$action, 'list_table'=>false]) as $object){
			$result	= $term_id ? $object->callback($term_id) : $object->validate();

			wpjam_die_if_error($result);
		}

		return true;
	}

	public function on_add_form_fields($taxonomy){
		$this->get_form_fields('add', [
			'fields_type'	=> 'div',
			'wrap_class'	=> 'form-field',
			'id'			=> false,
		]);
	}

	public function on_edit_form_fields($term){
		$this->get_form_fields('edit', [
			'fields_type'	=> 'tr',
			'wrap_class'	=> 'form-field',
			'id'			=> $term->term_id,
		]);
	}

	public function on_created($term_id, $tt_id, $taxonomy){
		if($taxonomy == $this->taxonomy){
			$this->update_data('add', $term_id);
		}
	}

	public function on_edited($term_id, $tt_id, $taxonomy){
		if($taxonomy == $this->taxonomy){
			$wp_list_table	= _get_list_table('WP_Terms_List_Table');

			if($wp_list_table->current_action() == 'editedtag'){
				$this->update_data('edit', $term_id);
			}
		}
	}

	public function filter_pre_insert($term, $taxonomy){
		if($taxonomy == $this->taxonomy){
			$this->update_data('add');
		}

		return $term;
	}

	public function filter_updated_messages($messages){
		if(!in_array($this->taxonomy, ['post_tag', 'category'])){
			$label	= $this->labels->name;

			foreach($messages['_item'] as $key => $message){
				$messages[$this->taxonomy][$key]	= str_replace(['项目', 'Item'], [$label, ucfirst($label)], $message);
			}
		}

		return $messages;
	}

	public function filter_parent_dropdown_args($args, $taxonomy, $action_type){
		$object	= wpjam_get_taxonomy_object($args['taxonomy']);
		$levels	= $object ? (int)$object->levels : 0;

		if($levels > 1){
			$args['depth']	= $levels - 1;

			if($action_type == 'edit'){
				$depth	= wpjam_term($args['exclude_tree'])->depth;

				if($depth < $args['depth']){
					$args['depth']	-= $depth;
				}else{
					$args['parent']	= -1;
				}
			}
		}

		return $args;
	}
}

class WPJAM_Builtin_List_Table extends WPJAM_List_Table{
	public function __construct($args, $class_name){
		$screen	= get_current_screen();

		if(wp_doing_ajax()){
			wpjam_add_admin_ajax('wpjam-list-table-action',	[$this, 'ajax_response']);

			$args['_builtin']	= _get_list_table($class_name, ['screen'=>$screen]);
		}else{
			$args['_builtin']	= true;
		}

		if(!wp_doing_ajax() || !wp_is_json_request()){
			add_filter('wpjam_html',	[$this, 'filter_html']);
		}

		add_filter('views_'.$screen->id,		[$this, 'filter_views']);
		add_filter('bulk_actions-'.$screen->id,	[$this, 'filter_bulk_actions']);

		add_filter('manage_'.$screen->id.'_columns',			[$this, 'filter_columns']);
		add_filter('manage_'.$screen->id.'_sortable_columns',	[$this, 'filter_sortable_columns']);

		$data_type	= $args['data_type'] ?? '';

		if($data_type){
			$screen->add_option('data_type', $data_type);

			$object		= wpjam_get_data_type_object($data_type);
			$meta_type	= $object ? $object->get_meta_type($args) : '';

			if($meta_type){
				$screen->add_option('meta_type', $meta_type);
			}
		}

		$this->_args	= $this->parse_args($args);	// 一定要最后执行
	}

	public function filter_views($views){
		return array_merge($views, $this->views);
	}

	public function filter_bulk_actions($bulk_actions=[]){
		return array_merge($bulk_actions, $this->get_bulk_actions());
	}

	public function filter_columns($columns){
		$columns	= array_merge(array_slice($columns, 0, -1), $this->columns, array_slice($columns, -1));
		$removed	= wpjam_get_items($this->id.'_removed_columns');

		return array_except($columns, $removed);
	}

	public function filter_sortable_columns($sortable_columns){
		return array_merge($sortable_columns, $this->sortable_columns);
	}

	public function filter_custom_column($value, $name, $id){
		return $this->get_column_value($id, $name, $value);
	}

	public function filter_html($html){
		return $this->single_row_replace($html);
	}

	public function get_single_row($id){
		return $this->filter_single_row(parent::get_single_row($id), $id);
	}

	public function get_list_table(){
		return $this->single_row_replace(parent::get_list_table());
	}

	public function single_row_replace($html){
		return preg_replace_callback('/<tr id="'.$this->singular.'-(\d+)".*?>.*?<\/tr>/is', function($matches){
			return $this->filter_single_row($matches[0], $matches[1]);
		}, $html);
	}

	protected function filter_single_row($single_row, $id){
		return $this->do_shortcode(apply_filters('wpjam_single_row', $single_row, $id), $id);
	}

	public function prepare_items(){
		$data	= wpjam_get_data_parameter();

		foreach($data as $key=>$value){
			$_GET[$key]	= $_POST[$key]	= $value;
		}

		$this->_builtin->prepare_items();
	}

	public function wp_list_table(){	// 兼容
		return _get_list_table($this->builtin_class, ['screen'=>$this->screen]);
	}

	public static function load($screen){
		$GLOBALS['wpjam_list_table']	= new static($screen);
	}
}

class WPJAM_Posts_List_Table extends WPJAM_Builtin_List_Table{
	public function __construct($screen){
		$post_type		= $screen->post_type;
		$type_object	= $screen->get_option('object');
		$args			= [
			'type_object'	=> $type_object,
			'title'			=> $type_object->label,
			'model'			=> $type_object->model,
			'singular'		=> 'post',
			'capability'	=> 'edit_post',
			'data_type'		=> 'post_type',
			'post_type'		=> $post_type,
			'form_id'		=> 'posts-filter'
		];

		if($post_type == 'attachment'){
			$row_action_filter_part	= 'media';
			$column_filter_part		= 'media';

			$class_name	= 'WP_Media_List_Table';
		}else{
			$row_action_filter_part	= $type_object->hierarchical ? 'page' : 'post';
			$column_filter_part		= $post_type.'_posts';

			$class_name	= 'WP_Posts_List_Table';

			add_filter('map_meta_cap',					[$this, 'filter_map_meta_cap'], 10, 4);
			add_filter('post_column_taxonomy_links',	[$this, 'filter_taxonomy_links'], 10, 3);
		}

		add_action('manage_posts_extra_tablenav', [$this, 'extra_tablenav']);

		add_action('pre_get_posts',	[$this, 'on_pre_get_posts']);

		add_filter('request',		[$this, 'filter_request']);

		add_filter($row_action_filter_part.'_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_action('manage_'.$column_filter_part.'_custom_column',	[$this, 'on_custom_column'], 10, 2);

		parent::__construct($args, $class_name);
	}

	public function filter_request($query_vars){
		$tax_query	= [];

		foreach($this->type_object->get_taxonomies() as $taxonomy => $tax_object){
			if(!$tax_object->show_ui){
				continue;
			}

			$tax	= $taxonomy == 'post_tag' ? 'tag' : $taxonomy;

			if($tax != 'category'){
				$tax_id	= wpjam_get_data_parameter($tax.'_id');

				if($tax_id){
					$query_vars[$tax.'_id']	= $tax_id;
				}
			}

			$tax_arg	= ['taxonomy'=>$taxonomy,	'field'=>'term_id'];
			$tax_data	= [];

			foreach(['in', 'and', 'not_in'] as $type){
				$tax_data[$type]	= wpjam_get_data_parameter($tax.'__'.$type,	['sanitize_callback'=>'wp_parse_id_list']);
			}

			if($tax_data['and']){
				if(count($tax_data['and']) == 1){
					$tax_data['in']		= $tax_data['in'] ?: [];
					$tax_data['in'][]	= reset($tax_data['and']);
				}else{
					$tax_query[]	= array_merge($tax_arg, ['terms'=>$tax_data['and'],	'operator'=>'AND']);	// 'include_children'	=> false,
				}
			}

			if($tax_data['in']){
				$tax_query[]	= array_merge($tax_arg, ['terms'=>$tax_data['in']]);
			}

			if($tax_data['not_in']){
				$tax_query[]	= array_merge($tax_arg, ['terms'=>$tax_data['not_in'],	'operator'=>'NOT IN']);
			}
		}

		if($tax_query){
			$tax_query['relation']		= wpjam_get_data_parameter('tax_query_relation',	['default'=>'and']);
			$query_vars['tax_query']	= $tax_query;
		}

		return $query_vars;
	}

	public function filter_taxonomy_links($term_links, $taxonomy, $terms){
		if($taxonomy == 'post_format'){
			foreach($term_links as &$term_link){
				$term_link	= str_replace('post-format-', '', $term_link);
			}
		}else{
			$tax_object	= wpjam_get_taxonomy_object($taxonomy);

			if($tax_object){
				foreach($terms as $i => $term){
					$term_links[$i]	= $tax_object->link_replace($term_links[$i], $term);
				}
			}
		}

		return $term_links;
	}

	public function filter_map_meta_cap($caps, $cap, $user_id, $args){
		if($cap == 'edit_post' && empty($args[0])){
			return $this->type_object->map_meta_cap ? [$this->type_object->cap->edit_posts] : [$this->type_object->cap->$cap];
		}

		return $caps;
	}

	public function prepare_items(){
		$_GET['post_type']	= $this->post_type;

		parent::prepare_items();
	}

	public function list_table(){
		if($this->post_type == 'attachment'){
			ob_start();

			$this->_builtin->views();
		}else{
			$this->_builtin->views();

			ob_start();

			$status	= wpjam_get_data_parameter('post_status', ['default'=>'all']);

			echo wpjam_field(['key'=>'post_status',	'type'=>'hidden',	'class'=>'post_status_page',	'value'=>$status]);
			echo wpjam_field(['key'=>'post_type',	'type'=>'hidden',	'class'=>'post_type_page',		'value'=>$this->post_type]);

			if(wpjam_get_data_parameter('show_sticky')){
				echo wpjam_field(['key'=>'show_sticky', 'type'=>'hidden', 'value'=>1]);
			}

			$this->_builtin->search_box($this->type_object->labels->search_items, 'post');
		}

		$this->_builtin->display(); 

		echo wpjam_wrap_tag(ob_get_clean(), 'form', ['id'=>'posts-filter', 'method'=>'get']);
	}

	public function single_row($raw_item){
		global $post, $authordata;

		if($post = is_numeric($raw_item) ? get_post($raw_item) : $raw_item){
			$authordata = get_userdata($post->post_author);

			if($post->post_type == 'attachment'){
				$post_owner = (get_current_user_id() == $post->post_author) ? 'self' : 'other';

				echo '<tr id="post-'.$post->ID.'" class="'.trim(' author-' . $post_owner . ' status-' . $post->post_status).'">';

				$this->_builtin->single_row_columns($post);

				echo '</tr>';
			}else{
				$this->_builtin->single_row($post);
			}
		}
	}

	public function filter_bulk_actions($bulk_actions=[]){
		$split	= array_search((isset($bulk_actions['trash']) ? 'trash' : 'untrash'), array_keys($bulk_actions), true);

		return array_merge(array_slice($bulk_actions, 0, $split), $this->get_bulk_actions(), array_slice($bulk_actions, $split));
	}

	public function filter_row_actions($row_actions, $post){
		foreach($this->get_row_actions($post->ID) as $key => $row_action){
			$object	= $this->get_object($key);
			$status	= get_post_status($post);

			if($status == 'trash'){
				if($object->post_status && in_array($status, (array)$object->post_status)){
					$row_actions[$key]	= $row_action;
				}
			}else{
				if(is_null($object->post_status) || in_array($status, (array)$object->post_status)){
					$row_actions[$key]	= $row_action;
				}
			}
		}

		foreach(['trash', 'view'] as $key){
			$row_actions[$key]	= array_pull($row_actions, $key);
		}

		return array_merge(array_filter($row_actions), ['id'=>'ID: '.$post->ID]);
	}

	public function on_custom_column($name, $post_id){
		echo $this->get_column_value($post_id, $name, null) ?: '';
	}

	public function filter_html($html){
		if(!wp_doing_ajax()){
			$object	= $this->get_object('add');

			if($object){
				$button	= $object->get_row_action(['class'=>'page-title-action']);
				$html	= preg_replace('/<a href=".*?" class="page-title-action">.*?<\/a>/i', $button, $html);
			}
		}

		return parent::filter_html($html);
	}

	public function on_pre_get_posts($wp_query){
		$orderby	= $wp_query->get('orderby');
		$object		= ($orderby && is_string($orderby)) ? $this->get_object($orderby, 'column') : null;

		if($object){
			$orderby_type	= $object->sortable_column ?? 'meta_value';

			if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
				$wp_query->set('meta_key', $orderby);
				$wp_query->set('orderby', $orderby_type);
			}else{
				$wp_query->set('orderby', $orderby);
			}
		}
	}

	public static function load($screen){
		if($screen->base == 'upload'){
			$mode	= get_user_option('media_library_mode', get_current_user_id()) ?: 'grid';

			if(isset($_GET['mode']) && in_array($_GET['mode'], ['grid', 'list'], true)){
				$mode	= $_GET['mode'];
			}

			if($mode == 'grid'){
				return;
			}
		}else{
			// if(!$GLOBALS['typenow'] || !post_type_exists($GLOBALS['typenow'])){
			//	return;
			// }
		}

		$GLOBALS['wpjam_list_table']	= new static($screen);
	}
}

class WPJAM_Terms_List_Table extends WPJAM_Builtin_List_Table{
	public function __construct($screen){
		$taxonomy	= $screen->taxonomy;
		$tax_object	= $screen->get_option('object');
		$args		= [
			'tax_object'	=> $tax_object,
			'title'			=> $tax_object->label,
			'capability'	=> $tax_object->cap->edit_terms,
			'levels'		=> $tax_object->levels,
			'hierarchical'	=> $tax_object->hierarchical,
			'model'			=> $tax_object->model,
			'singular'		=> 'tag',
			'data_type'		=> 'taxonomy',
			'taxonomy'		=> $taxonomy,
			'post_type'		=> $screen->post_type,
			'form_id'		=> 'posts-filter'
		];

		if($tax_object->hierarchical){
			if($tax_object->sortable){
				$args['sortable']	= [
					'items'		=> $this->get_sorteable_items(),
					'action'	=> ['row_action'=>false, 'callback'=>['WPJAM_Term', 'move']]
				];

				add_filter('edit_'.$taxonomy.'_per_page',	[$this, 'filter_per_page']);
			}

			if(!is_null($tax_object->levels)){
				wpjam_register_list_table_action('children', ['title'=>'下一级']);

				add_filter('pre_insert_term',	[$this, 'filter_pre_insert'], 10, 2);
			}
		}

		add_action('parse_term_query',	[$this, 'on_parse_term_query'], 0);

		add_filter($taxonomy.'_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_filter('manage_'.$taxonomy.'_custom_column',	[$this, 'filter_custom_column'], 10, 3);

		parent::__construct($args, 'WP_Terms_List_Table');
	}

	public function list_table(){
		ob_start();

		echo wpjam_field(['key'=>'taxonomy',	'type'=>'hidden',	'value'=>$this->taxonomy]);
		echo wpjam_field(['key'=>'post_type',	'type'=>'hidden',	'value'=>$this->post_type]);

		$this->_builtin->display(); 

		$sortable_items	= ($this->hierarchical && $this->sortable) ? $this->get_sorteable_items() : null;

		echo wpjam_wrap_tag(ob_get_clean(), 'form', ['id'=>'posts-filter', 'method'=>'get', 'data-sortable_items'=>$sortable_items]);
	}

	public function get_list_table(){
		return $this->append_extra_tablenav(parent::get_list_table());
	}

	public function filter_html($html){
		return parent::filter_html($this->append_extra_tablenav($html));
	}

	public function get_sorteable_items(){
		$parent	= $this->get_parent();
		$object	= $parent ? wpjam_term($parent) : null;
		$level	= $object ? ($object->level+1) : 0;

		return 'tr.level-'.$level;
	}

	public function get_parent($type=''){
		$parent	= wpjam_get_data_parameter('parent');

		if(is_null($parent)){
			return $this->levels == 1 ? 0 : null;
		}

		return (int)$parent;
	}

	public function get_edit_tags_link($args=[]){
		$args	= array_filter($args, 'is_exists');
		$args	= wp_parse_args($args, ['taxonomy'=>$this->taxonomy, 'post_type'=>$this->post_type]);

		return admin_url(add_query_arg($args, 'edit-tags.php'));
	}

	public function append_extra_tablenav($html){
		$extra	= '';

		if($this->hierarchical && $this->levels > 1){
			$parent	= $this->get_parent();

			if(is_null($parent)){
				$to		= 0;
				$text	= '只显示第一级';
			}elseif($parent > 0){
				$to		= 0;
				$text	= '返回第一级';
			}else{
				$to		= null;
				$text	= '显示所有';
			}

			$extra	= '<div class="alignleft actions"><a href="'.$this->get_edit_tags_link(['parent'=>$to]).'" class="button button-primary list-table-href">'.$text.'</a></div>';
		}

		if($extra = apply_filters('wpjam_terms_extra_tablenav', $extra, $this->taxonomy)){
			$html	= preg_replace('#(<div class="tablenav top">\s+?<div class="alignleft actions bulkactions">.*?</div>)#is', '$1 '.$extra, $html);
		}

		return $html;
	}

	public function single_row($raw_item){
		$term	= is_numeric($raw_item) ? get_term($raw_item) : $raw_item;

		if($term){
			$object = wpjam_term($term);
			$level	= $object ? $object->level : 0;

			$this->_builtin->single_row($term, $level);
		}
	}

	public function filter_row_actions($row_actions, $term){
		$row_actions	= array_merge($row_actions, $this->get_row_actions($term->term_id));

		if(isset($row_actions['children'])){
			$parent	= $this->get_parent();

			if((empty($parent) || $parent != $term->term_id) && get_term_children($term->term_id, $term->taxonomy)){
				$row_actions['children']	= '<a href="'.$this->get_edit_tags_link(['parent'=>$term->term_id]).'">下一级</a>';
			}else{
				unset($row_actions['children']);
			}
		}

		foreach(['delete', 'view'] as $key){
			if($row_action = array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		$row_actions	= array_except($row_actions, ['inline hide-if-no-js']);

		return array_merge($row_actions, ['term_id'=>'ID：'.$term->term_id]);
	}

	public function filter_columns($columns){
		$columns	= parent::filter_columns($columns);

		foreach(['slug', 'description'] as $key){
			if(!$this->tax_object->supports($key)){
				unset($columns[$key]);
			}
		}

		return $columns;
	}

	public function filter_per_page($per_page){
		$parent	= $this->get_parent();

		return is_null($parent) ? $per_page : 9999;
	}

	public function filter_pre_insert($term, $taxonomy){
		if($this->levels && $taxonomy == $this->taxonomy){
			$parent	= wpjam_get_post_parameter('parent');

			if($parent && $parent != -1){
				$object	= wpjam_term($parent);

				if($object && $object->level >= $this->levels - 1){
					return new WP_Error('error', '不能超过'.$this->levels.'级');
				}
			}
		}

		return $term;
	}

	public function sort_column_callback($term_id){
		$parent	= $this->get_parent();

		if(is_null($parent) || wpjam_get_data_parameter('orderby') || wpjam_get_data_parameter('s')){
			return wpjam_admin_tooltip('<span class="dashicons dashicons-editor-help"></span>', '如要进行排序，请先点击「只显示第一级」按钮。');
		}elseif(get_term($term_id)->parent == $parent){
			$sortable_row_actions	= '';

			foreach(['move', 'up', 'down'] as $action_key){
				$sortable_row_actions	.= '<span class="'.$action_key.'">[row_action name="'.$action_key.'" id="'.$term_id.'"]</span>';
			}

			return '<div class="row-actions">'.$sortable_row_actions.'</div>';
		}else{
			return '';
		}
	}

	public function on_parse_term_query($term_query){
		if(!in_array('WP_Terms_List_Table', array_column(debug_backtrace(), 'class'))){
			return;
		}

		$term_query->query_vars['list_table_query']	= true;

		$orderby	= $term_query->query_vars['orderby'];
		$object		= ($orderby && is_string($orderby)) ? $this->get_object($orderby, 'column') : null;

		if($object){
			$orderby_type	= $object->sortable_column ?? 'meta_value';

			if(in_array($orderby_type, ['meta_value_num', 'meta_value'])){
				$term_query->query_vars['meta_key']	= $orderby;
				$term_query->query_vars['orderby']	= $orderby_type;
			}else{
				$term_query->query_vars['orderby']	= $orderby;
			}
		}

		if($this->hierarchical){
			$parent	= $this->get_parent();

			if($parent){
				$hierarchy	= _get_term_hierarchy($this->taxonomy);
				$term_ids	= $hierarchy[$parent] ?? [];
				$term_ids[]	= $parent;

				if($ancestors = get_ancestors($parent, $this->taxonomy)){
					$term_ids	= array_merge($term_ids, $ancestors);
				}

				$term_query->query_vars['include']	= $term_ids;
				// $term_query->query_vars['pad_counts']	= true;
			}elseif($parent === 0){
				$term_query->query_vars['parent']	= $parent;
			}
		}
	}
}

class WPJAM_Users_List_Table extends WPJAM_Builtin_List_Table{
	public function __construct($screen){
		add_filter('user_row_actions',	[$this, 'filter_row_actions'], 1, 2);

		add_filter('manage_users_custom_column',	[$this, 'filter_custom_column'], 10, 3);

		parent::__construct([
			'title'			=> '用户',
			'singular'		=> 'user',
			'capability'	=> 'edit_user',
			'data_type'		=> 'user',
			'model'			=> 'WPJAM_User',
		], 'WP_Users_List_Table');
	}

	public function single_row($raw_item){
		if($user = is_numeric($raw_item) ? get_userdata($raw_item) : $raw_item){
			echo $this->_builtin->single_row($raw_item);
		}
	}

	public function filter_row_actions($row_actions, $user){
		foreach($this->get_row_actions($user->ID) as $key => $row_action){
			$action	= $this->get_object($key);

			if(is_null($action->roles) || array_intersect($user->roles, (array)$action->roles)){
				$row_actions[$key]	= $row_action;
			}
		}

		foreach(['delete', 'remove', 'view'] as $key){
			if($row_action = array_pull($row_actions, $key)){
				$row_actions[$key]	= $row_action;
			}
		}

		return array_merge($row_actions, ['id'=>'ID: '.$user->ID]);
	}
}