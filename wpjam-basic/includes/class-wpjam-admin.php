<?php
class WPJAM_Admin{
	public static function filter_admin_url($url, $path, $blog_id=null, $scheme='admin'){
		if($path && is_string($path) && str_starts_with($path, 'page=')){
			$url	= get_site_url($blog_id, 'wp-admin/', $scheme);
			$url	.= 'admin.php?'.$path;
		}

		return $url;
	}

	public static function filter_html($html){
		if(WPJAM_Plugin_Page::get_current()){
			$queried	= wpjam_get_items('queried_menu');
			$search		= array_column($queried, 'search');
			$replace	= array_column($queried, 'replace');
		}else{
			$search		= '<hr class="wp-header-end">';
			$replace	= $search.wpautop(get_screen_option('page_summary'));
		}

		return str_replace($search, $replace, $html);
	}

	public static function filter_parent_file($parent_file){
		if($GLOBALS['submenu_file']){
			$parent_files	= wpjam_get_items('parent_files');

			if(isset($parent_files[$GLOBALS['submenu_file']])){
				return $parent_files[$GLOBALS['submenu_file']];
			}
		}

		return $parent_file;
	}

	public static function on_current_screen($screen=null){
		$object	= WPJAM_Plugin_Page::get_current();

		if($object){
			if(wpjam_get_items('queried_menu')){
				add_filter('wpjam_html', [self::class, 'filter_html']);
			}

			$object->load($screen);
		}else{
			if(wpjam_get_items('parent_files')){
				add_filter('parent_file', [self::class, 'filter_parent_file']);
			}

			if($screen){
				WPJAM_Builtin_Page::init($screen);

				if(!wp_doing_ajax() && $screen->get_option('page_summary')){
					add_filter('wpjam_html', [self::class, 'filter_html']);
				}
			}
		}
	}
}

class WPJAM_Admin_Action extends WPJAM_Register{
	protected function parse_submit_button($button, $name=null, $render=true){
		$button	= $button ?: [];
		$button	= is_array($button) ? $button : [$this->name => $button];

		foreach($button as $key => &$item){
			$item	= is_array($item) ? $item : ['text'=>$item];
			$item	= wp_parse_args($item, ['response'=>($this->response ?? $this->name), 'class'=>'primary']);

			if($render){
				$item	= get_submit_button($item['text'], $item['class'], $key, false);
			}
		}

		if($name){
			if(!isset($button[$name])){
				wp_die('无效的提交按钮');
			}

			return $button[$name];
		}else{
			return $render ? implode('', $button) : $button;
		}
	}

	protected function parse_nonce_action($args=[]){
		$prefix	= $GLOBALS['plugin_page'] ?? $GLOBALS['current_screen']->id;
		$key	= $this->name;

		if($args){
			if(!empty($args['bulk'])){
				$key	= 'bulk_'.$key;
			}elseif(!empty($args['id'])){
				$key	= $key.'-'.$args['id'];
			}
		}

		return $prefix.'-'.$key;
	}

	public function create_nonce($args=[]){
		$action	= $this->parse_nonce_action($args);

		return wp_create_nonce($action);
	}

	public function verify_nonce($args=[]){
		$action	= $this->parse_nonce_action($args);

		return check_ajax_referer($action, false, false);
	}
}

class WPJAM_Page_Action extends WPJAM_Admin_Action{
	public function is_allowed($type=''){
		$capability	= $this->capability ?? ($type ? 'manage_options' : 'read');

		return current_user_can($capability, $this->name);
	}

	public function callback($type=''){
		if($type == 'form'){
			$form	= $this->get_form();
			$width	= $this->width ?: 720;
			$modal	= $this->modal_id ?: 'tb_modal';
			$title	= wpjam_get_post_parameter('page_title');

			if(!$title){
				foreach(['page_title', 'button_text', 'submit_text'] as $key){
					if(!empty($this->$key) && !is_array($this->$key)){
						$title	= $this->$key;
						break;
					}
				}
			}

			return ['form'=>$form, 'width'=>$width, 'modal_id'=>$modal, 'page_title'=>$title];
		}

		if(!$this->verify_nonce()){
			wp_die('invalid_nonce');
		}

		if(!$this->is_allowed($type)){
			wp_die('access_denied');
		}

		$callback		= '';
		$submit_name	= null;

		if($type == 'submit'){
			$submit_name	= wpjam_get_post_parameter('submit_name',	['default'=>$this->name]);
			$submit_button	= $this->get_submit_button($submit_name);

			$callback	= $submit_button['callback'] ?? '';
			$response	= $submit_button['response'];
		}else{
			$response	= $this->response ?? $this->name;
		}

		$callback	= $callback ?: $this->callback;

		if(!$callback || !is_callable($callback)){
			wp_die('无效的回调函数');
		}

		$cb_args	= [$this->name, $submit_name];

		if($this->validate){
			$data	= wpjam_get_data_parameter();
			$fields	= $this->get_fields();
			$data	= $fields ? wpjam_fields($fields)->validate($data) : $data;

			$cb_args	= array_merge([$data], $cb_args);
		}

		$result		= wpjam_try($callback, ...$cb_args);
		$response	= ['type'=>$response];

		if(is_array($result)){
			$response	= array_merge($response, $result);
		}elseif($result === false || is_null($result)){
			$response	= new WP_Error('invalid_callback', ['返回错误']);
		}elseif($result !== true){
			if($this->response == 'redirect'){
				$response['url']	= $result;
			}else{
				$response['data']	= $result;
			}
		}

		return apply_filters('wpjam_ajax_response', $response);
	}

	public function get_data(){
		$data		= $this->data ?: [];
		$callback	= $this->data_callback;

		if($callback && is_callable($callback)){
			$_data	= wpjam_try($callback, $this->name, $this->get_fields());

			return array_merge($data, $_data);
		}

		return $data;
	}

	public function get_button($args=[]){
		if(!$this->is_allowed()){
			return '';
		}

		$data	= array_pull($args, 'data') ?: [];

		$this->update_args($args);

		$tag	= $this->tag ?: 'a';
		$text	= $this->button_text ?? '保存';
		$class	= $this->class ?? 'button-primary large';
		$title	= $this->page_title ?: $text;
		$attr	= [
			'title'	=> $title,
			'class'	=> array_merge(array_wrap($class), ['wpjam-button']),
			'style'	=> $this->style,
			'data'	=> $this->generate_data_attr(['data'=>$data])
		];

		return wpjam_tag($tag, $attr, $text);
	}

	public function get_form(){
		if(!$this->is_allowed()){
			return '';
		}

		$attr	= [
			'method'	=> 'post',
			'action'	=> '#',
			'id'		=> $this->form_id ?: 'wpjam_form',
			'data'		=> $this->generate_data_attr([], 'form')
		];

		$fields	= wpjam_fields($this->get_fields())->render(array_merge($this->args, ['data'=>$this->get_data()]));
		$form	= wpjam_tag('form', $attr, $fields);
		$button	= $this->get_submit_button();

		if($button){
			$form->append('p', ['submit'], $button);
		}

		return $form;
	}

	public function get_fields(){
		$fields	= $this->fields;

		if($fields && is_callable($fields)){
			$fields	= wpjam_try($fields, $this->name);
		}

		return $fields ?: [];
	}

	protected function get_submit_button($name=null, $render=null){
		$render	= $render ?? is_null($name);

		if(!is_null($this->submit_text)){
			$button	= $this->submit_text;

			if($button && is_callable($button)){
				$button	= wpjam_try($button, $this->name);
			}
		}else{
			$button = wp_strip_all_tags($this->page_title);
		}

		return $this->parse_submit_button($button, $name, $render);
	}

	public function generate_data_attr($args=[], $type='button'){
		$attr	= [
			'action'	=> $this->name,
			'nonce'		=> $this->create_nonce()
		];

		if($type == 'button'){
			$args	= wp_parse_args($args, ['data'=>[]]);
			$data	= $this->data ?: [];

			return array_merge($attr, [
				'title'		=> $this->page_title ?: $this->button_text,
				'data'		=> wp_parse_args($args['data'], $data),
				'direct'	=> $this->direct,
				'confirm'	=> $this->confirm
			]);
		}

		return $attr;
	}

	public static function get_nonce_action($key){	// 兼容
		return wpjam_get_nonce_action($key);
	}
}

class WPJAM_Plugin_Page extends WPJAM_Register{
	protected function include(){
		if(!$this->_included){
			$this->_included	= true;

			$key	= $this->page_type.'_file';
			$file	= $this->$key ?: [];

			foreach((array)$file as $_file){
				include $_file;
			}
		}
	}

	protected function tab_load($screen){
		$tabs	= $this->tabs ?: [];
		$tabs	= is_callable($tabs) ? call_user_func($tabs, $this->name) : $tabs;
		$tabs	= apply_filters(wpjam_get_filter_name($this->name, 'tabs'), $tabs);

		foreach($tabs as $name => $args){
			WPJAM_Tab_Page::register($name, $args);
		}

		$current_tab	= wp_doing_ajax() ? wpjam_get_post_parameter('current_tab') : wpjam_get_parameter('tab');
		$current_tab	= sanitize_key($current_tab);

		$tabs	= [];

		foreach(WPJAM_Tab_Page::get_registereds() as $name => $tab){
			if($tab->plugin_page && $tab->plugin_page != $this->name){
				continue;
			}

			if($tab->network === false && is_network_admin()){
				continue;
			}

			if($tab->capability){
				if($tab->map_meta_cap && is_callable($tab->map_meta_cap)){
					wpjam_register_capability($tab->capability, $tab->map_meta_cap);
				}

				if(!current_user_can($tab->capability)){
					continue;
				}
			}

			if(!$current_tab){
				$current_tab	= $name;
			}

			if($tab->query_args){
				$query_data	= wpjam_generate_query_data($tab->query_args);

				if($null_queries = array_filter($query_data, 'is_null')){
					if($current_tab == $name){
						wp_die('「'.implode('」,「', array_keys($null_queries)).'」参数无法获取');
					}else{
						continue;
					}
				}else{
					if($current_tab == $name){
						$GLOBALS['current_admin_url']	= add_query_arg($query_data, $GLOBALS['current_admin_url']);
					}
				}

				$tab->query_data	= $query_data;
			}

			$tabs[$name]	= $tab;
		}

		if(!$tabs){
			throw new WPJAM_Exception('Tabs 未设置');
		}

		$this->tabs	= $tabs;

		$GLOBALS['current_tab']			= $current_tab;
		$GLOBALS['current_admin_url']	= $GLOBALS['current_admin_url'].'&tab='.$current_tab;

		$tab_object	= $tabs[$current_tab] ?? null;

		if(!$tab_object){
			throw new WPJAM_Exception('无效的 Tab');
		}elseif(!$tab_object->function){
			throw new WPJAM_Exception('Tab 未设置 function');
		}elseif(!$tab_object->function == 'tab'){
			throw new WPJAM_Exception('Tab 不能嵌套 Tab');
		}

		$tab_object->page_hook	= $this->page_hook;
		$this->tab_object		= $tab_object;

		$tab_object->load($screen);
	}

	protected function render_nav_tab(){
		$tag	= wpjam_tag('nav', ['nav-tab-wrapper', 'wp-clearfix']);

		foreach($this->tabs as $tab_name => $tab_object){
			$tab_title	= $tab_object->tab_title ?: $tab_object->title;
			$tab_url	= $this->admin_url.'&tab='.$tab_name;

			if($tab_object->query_data){
				$tab_url	= add_query_arg($tab_object->query_data, $tab_url);
			}

			$class	= ['nav-tab'];

			if($this->tab_object && $this->tab_object->name == $tab_name){
				$class[]	= 'nav-tab-active';
			}

			$tag->append('a', ['class'=>$class, 'href'=>$tab_url], $tab_title);
		}

		return $tag;
	}

	public function load($screen){
		if($this->function != 'tab'){
			$page_model	= 'WPJAM_Admin_Page';
			$page_name	= null;

			if(!$this->function){
				$this->function	= wpjam_get_filter_name($this->name, 'page');
			}elseif(is_string($this->function)){
				$function	= $this->function == 'list' ? 'list_table' : $this->function;

				if(in_array($function, ['option', 'list_table', 'form', 'dashboard'])){
					$page_model	= 'WPJAM_'.ucwords($function, '_').'_Page';
					$page_name	= $this->{$function.'_name'} ?: $GLOBALS['plugin_page'];
				}
			}

			$args	= wpjam_try([$page_model, 'preprocess'], $page_name, $this);
			$args	= ($args && is_array($args)) ? wpjam_slice_data_type($args) : [];

			if($args){
				$this->update_args($args);
			}

			if($this->data_type){
				$data_type	= $this->data_type;
				$type_value	= $this->$data_type;
				$object		= wpjam_get_data_type_object($data_type);
				$meta_type	= $object ? $object->get_meta_type($args) : '';

				$screen->add_option('data_type', $data_type);

				if($meta_type){
					$screen->add_option('meta_type', $meta_type);
				}

				if(in_array($data_type, ['post_type', 'taxonomy']) && $type_value && !$screen->$data_type){
					$screen->$data_type	= $type_value;
				}
			}
		}

		do_action('wpjam_plugin_page_load', $GLOBALS['plugin_page'], $this->load_arg);

		wpjam_admin_load($GLOBALS['plugin_page'], $this->load_arg);

		// 一般 load_callback 优先于 load_file 执行
		// 如果 load_callback 不存在，尝试优先加载 load_file
		if($this->load_callback){
			$load_callback	= $this->load_callback;

			if(!is_callable($load_callback)){
				$this->include();
			}

			if(is_callable($load_callback)){
				call_user_func($load_callback, $this->name);
			}
		}

		$this->include();

		if($this->chart){
			WPJAM_Chart::init($this->chart);
		}

		if($this->editor){
			add_action('admin_footer', 'wp_enqueue_editor');
		}

		$this->set_defaults();

		try{
			if($this->function == 'tab'){
				return $this->tab_load($screen);
			}

			$object	= wpjam_try([$page_model, 'create'], $page_name, $this);

			if(wp_doing_ajax()){
				return $object->load();
			}

			add_action('load-'.$this->page_hook, [$object, 'load']);

			$this->page_object	= $object;

			if($page_name){
				$this->page_title	= $object->title ?: $this->page_title;
				$this->subtitle		= $object->get_subtitle() ?: $this->subtitle;
				$this->summary		= $this->summary ?: $object->get_summary();
				$this->query_data	= $this->query_data ?: [];
				$this->query_data	+= wpjam_generate_query_data($object->query_args);
			}
		}catch(WPJAM_Exception $e){
			wpjam_add_admin_error($e->get_wp_error());
		}
	}

	public function render(){
		$page_title	= $this->page_title ?? $this->title;
		$summary	= $this->summary;

		if($this->tab_page){
			$tag	= wpjam_tag('h2', [], $page_title.$this->subtitle);
		}else{
			$tag	= wpjam_tag('h1', ['wp-heading-inline'], $page_title)->after($this->subtitle)->after('hr', ['wp-header-end']);
		}

		if($summary){
			if(is_callable($summary)){
				$summary	= call_user_func($summary, $GLOBALS['plugin_page'], $this->load_arg);
			}elseif(is_array($summary)){
				$summ_arr	= $summary;
				$summary	= $summ_arr[0];

				if(!empty($summ_arr[1])){
					$summary	.= '，详细介绍请点击：'.wpjam_tag('a', ['href'=>$summ_arr[1], 'target'=>'_blank'], $this->menu_title);
				}
			}elseif(is_file($summary)){
				$summary	= wpjam_get_file_summary($summary);
			}
		}

		$summary	.= get_screen_option($this->page_type.'_summary');

		if($summary){
			$tag->after($summary, 'p');
		}

		if($this->function == 'tab'){
			$callback	= wpjam_get_filter_name($GLOBALS['plugin_page'], 'page');

			if(is_callable($callback)){
				$tag->after(wpjam_ob_get_contents($callback));	// 所有 Tab 页面都执行的函数
			}

			if(count($this->tabs) > 1){
				$tag->after($this->render_nav_tab());
			}

			if($this->tab_object){
				$tag->after($this->tab_object->render());
			}
		}else{
			$tag->after(wpjam_ob_get_contents([$this->page_object, 'render']));
		}

		if($this->tab_page){
			return $tag;
		}

		echo $tag->wrap('div', ['wrap']);
	}

	public function set_defaults($defaults=[]){
		$this->defaults	= $this->defaults ?: [];
		$this->defaults	= array_merge($this->defaults, $defaults);

		if($this->defaults){
			add_filter('wpjam_parameter_default', [$this, 'filter_parameter_default'], 10, 2);
		}
	}

	protected static function preprocess_args($args, $name){
		if(empty($args['tab_page'])){
			$args	= array_merge($args, [
				'page_type'	=> 'page',
				'load_arg'	=> '',
			]);
		}

		return parent::preprocess_args($args, $name);
	}

	public static function get_current(){
		return self::get($GLOBALS['plugin_page']);
	}
}

class WPJAM_Tab_Page extends WPJAM_Plugin_Page{
	protected static function preprocess_args($args, $name){
		return parent::preprocess_args(array_merge($args, [
			'page_type'	=> 'tab',
			'tab_page'	=> true,
			'load_arg'	=> $name,
		]), $name);
	}

	protected static function get_config($key){
		if($key == 'orderby'){
			return true;
		}elseif($key == 'model'){
			return false;
		}
	}
}

class WPJAM_Admin_Page extends WPJAM_Args{
	public function __call($method, $args){
		if($this->object && method_exists($this->object, $method)){
			return call_user_func_array([$this->object, $method], $args);
		}elseif(in_array($method, ['get_subtitle', 'get_summary'])){
			$key	= wpjam_remove_prefix($method, 'get_');

			return $this->$key;
		}
	}

	public function __get($key){
		if(empty($this->args['object']) || in_array($key, ['object', 'tab_page'])){
			return parent::__get($key);
		}else{
			return $this->object->$key;
		}
	}

	public function load(){
	}

	public function render(){
		if($this->chart){
			WPJAM_Chart::form();
		}

		if(is_callable($this->function)){
			call_user_func($this->function);
		}
	}

	public static function preprocess($name, $menu){
		return [];
	}

	public static function create($name, $menu){
		if(!is_callable($menu->function)){
			return new WP_Error('invalid_menu_page', ['函数', $menu->function]);
		}

		return new self($menu->to_array());
	}
}

class WPJAM_Form_Page extends WPJAM_Admin_Page{
	public function render(){
		try{
			echo $this->get_form();
		}catch(WPJAM_Exception $e){
			wp_die($e->get_wp_error());
		}
	}

	public static function preprocess($name, $menu){
		$object	= WPJAM_Page_Action::get($name);

		if($object){
			return $object->to_array();
		}

		if($menu->form && is_callable($menu->form)){
			$menu->form	= call_user_func($menu->form, $name);
		}

		return $menu->form;
	}

	public static function create($name, $menu){
		$object	= WPJAM_Page_Action::get($name);

		if(!$object){
			$args	= self::preprocess($name, $menu);
			$args	= $args ?: ($menu->callback ? $menu->to_array() : []);

			if(!$args){
				return new WP_Error('invalid_menu_page', ['Page Action', $name]);
			}

			$object	= WPJAM_Page_Action::register($name, $args);
		}

		return new self(array_merge($menu->to_array(), ['object'=>$object]));
	}
}

class WPJAM_Option_Page extends WPJAM_Admin_Page{
	public function load(){
		if(wp_doing_ajax()){
			wpjam_add_admin_ajax('wpjam-option-action',	[$this, 'ajax_response']);
		}else{
			add_action('admin_action_update', [$this, 'register_settings']);

			if(isset($_POST['response_type'])) {
				$message	= $_POST['response_type'] == 'reset' ? '设置已重置。' : '设置已保存。';

				wpjam_add_admin_error($message);
			}

			$this->register_settings();
		}
	}

	public function render(){
		echo $this->render_sections($this->tab_page);
	}

	public static function preprocess($name, $menu){
		$object	= WPJAM_Option_Setting::get($name);

		if($object){
			return $object->to_array();
		}

		if($menu->option && is_callable($menu->option)){
			$menu->option	= call_user_func($menu->option, $name);
		}

		return $menu->option;
	}

	public static function create($name, $menu){
		$object	= WPJAM_Option_Setting::get($name);

		if(!$object){
			if($menu->model && method_exists($menu->model, 'register_option')){	// 舍弃 ing
				$object	= call_user_func([$menu->model, 'register_option'], $menu->delete_arg('model')->to_array());
			}else{
				$args	= self::preprocess($name, $menu);
				$args	= $args ?: (($menu->sections || $menu->fields) ? $menu->to_array() : []);

				if(!$args){
					$args	= apply_filters(wpjam_get_filter_name($name, 'setting'), []); // 舍弃 ing

					if(!$args){
						return new WP_Error('invalid_menu_page', ['Option', $name]);
					}
				}

				$object	= WPJAM_Option_Setting::create($name, $args);
			}
		}

		return new self(array_merge($menu->to_array(), ['object'=>$object]));
	}
}

class WPJAM_List_Table_Page extends WPJAM_Admin_Page{
	public function load(){
		if(wp_doing_ajax()){
			wpjam_add_admin_ajax('wpjam-list-table-action',	[$this, 'ajax_response']);
		}elseif(wpjam_get_parameter('export_action')){
			$this->export_action();
		}else{
			$result = wpjam_call([$this, 'prepare_items']);

			if(is_wp_error($result)){
				wpjam_add_admin_error($result);
			}
		}
	}

	public function render(){
		$layout		= $this->layout;
		$list_table	= $this->get_list_table();

		if($layout == 'left'){
			$list_table	= wpjam_tag('div', ['col-wrap', 'list-table'], $list_table)->wrap('div', ['id'=>'col-right']);
			$col_left	= wpjam_tag('div', ['col-wrap', 'left'], $this->get_col_left())->wrap('div', ['id'=>'col-left']);

			echo $list_table->before($col_left)->wrap('div', ['id'=>'col-container', 'class'=>'wp-clearfix']);
		}else{
			$layout_class	= $layout ? ' layout-'.$layout : '';

			echo wpjam_tag('div', ['list-table', $layout_class], $list_table);
		}
	}

	public static function preprocess($name, $menu){
		$args	= wpjam_get_item('list_table', $name) ?: $menu->list_table;

		if($args){
			if(is_string($args) && class_exists($args) && method_exists($args, 'get_list_table')){
				$args	= [$args, 'get_list_table'];
			}

			if(is_callable($args)){
				$args	= call_user_func($args, $name);
			}

			return $menu->list_table = $args;
		}
	}

	public static function create($name, $menu){
		$args	= self::preprocess($name, $menu);

		if($args){
			if(isset($args['defaults'])){
				$menu->set_defaults($args['defaults']);
			}
		}else{
			if($menu->model){
				$args	= array_except($menu->to_array(), 'defaults');
			}else{
				$args	= apply_filters(wpjam_get_filter_name($name, 'list_table'), []);
			}

			if(!$args){
				return new WP_Error('invalid_menu_page', ['List Table', $name]);
			}
		}

		if(empty($args['model']) || !class_exists($args['model'])){
			return new WP_Error('invalid_menu_page', ['List Table 的 Model', $args['model']]);
		}

		foreach(['admin_head', 'admin_footer'] as $admin_hook){
			if(method_exists($args['model'], $admin_hook)){
				add_action($admin_hook,	[$args['model'], $admin_hook]);
			}
		}

		$args	= wp_parse_args($args, ['primary_key'=>'id', 'name'=>$name, 'singular'=>$name, 'plural'=>$name.'s', 'layout'=>'']);

		if($args['layout'] == 'left' || $args['layout'] == '2'){
			$args['layout']	= 'left';

			$object	= new WPJAM_Left_List_Table($args);
		}elseif($args['layout'] == 'calendar'){
			$args['query_args']	= $args['query_args'] ?? [];
			$args['query_args']	= array_merge($args['query_args'], ['year', 'month']);

			$object	= new WPJAM_Calendar_List_Table($args);
		}else{
			$object	= new WPJAM_List_Table($args);
		}

		return new self(array_merge($menu->to_array(), ['object'=>$object]));
	}
}

class WPJAM_Dashboard_Page extends WPJAM_Admin_Page{
	public function load(){
		require_once ABSPATH . 'wp-admin/includes/dashboard.php';
		// wp_dashboard_setup();

		wp_enqueue_script('dashboard');

		if(wp_is_mobile()){
			wp_enqueue_script('jquery-touch-punch');
		}

		$widgets	= $this->widgets ?: [];
		$widgets	= is_callable($widgets) ? call_user_func($widgets, $this->name) : $widgets;
		$widgets	= array_merge($widgets, wpjam_get_items('dashboard_widget'));

		foreach($widgets as $widget_id => $widget){
			if(!isset($widget['dashboard']) || $widget['dashboard'] == $this->name){
				$title		= $widget['title'];
				$callback	= $widget['callback'] ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
				$context	= $widget['context'] ?? 'normal';	// 位置，normal 左侧, side 右侧
				$priority	= $widget['priority'] ?? 'core';
				$args		= $widget['args'] ?? [];

				// 传递 screen_id 才能在中文的父菜单下，保证一致性。
				add_meta_box($widget_id, $title, $callback, get_current_screen()->id, $context, $priority, $args);
			}
		}
	}

	public function render(){
		$tag	= wpjam_tag('div', ['id'=>'dashboard-widgets-wrap'], wpjam_ob_get_contents('wp_dashboard'));

		if($this->welcome_panel && is_callable($this->welcome_panel)){
			$welcome_panel	= wpjam_ob_get_contents($this->welcome_panel, $this->name);

			$tag->before('div', ['id'=>'welcome-panel', 'class'=>'welcome-panel wpjam-welcome-panel'], $welcome_panel);
		}

		echo $tag;
	}

	public static function preprocess($name, $menu){
		return wpjam_get_item('dashboard', $name) ?: $menu->dashboard;
	}

	public static function create($name, $menu){
		$args	= self::preprocess($name, $menu);
		$args	= $args ?: ($menu->widgets ? $menu->to_array() : []);

		if(!$args){
			return new WP_Error('invalid_menu_page', ['Dashboard', $name]);
		}

		return new self(array_merge($args, ['name'=>$name]));
	}
}