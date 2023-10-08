<?php
function wpjam_load($hooks, $callback){
	if(!$callback && !wpjam_is_callable($callback)){
		return;
	}

	$todo	= [];

	foreach((array)$hooks as $hook){
		if(!did_action($hook)){
			$todo[]	= $hook;
		}
	}

	if(empty($todo)){
		call_user_func($callback);
	}elseif(count($todo) == 1){
		add_action(current($todo), $callback);
	}else{
		$object	= new WPJAM_Args([
			'hooks'		=> $todo,
			'callback'	=> $callback,
			'invoke'	=> function(){
				foreach($this->hooks as $hook){
					if(!did_action($hook)){
						return;
					}
				}

				call_user_func($this->callback);
			}
		]);

		foreach($todo as $hook){
			add_action($hook, [$object, 'invoke']);
		}
	}
}

function wpjam_try($callback, ...$args){
	wpjam_throw_if_error($callback);

	if(wpjam_is_callable($callback)){
		try{
			if(is_array($callback) && !is_object($callback[0])){
				$result	= wpjam_call_method($callback[0], $callback[1], ...$args);
			}else{
				$result	= call_user_func_array($callback, $args);
			}

			if(is_wp_error($result)){
				throw new WPJAM_Exception($result);
			}

			return $result;
		}catch(Exception $e){
			throw $e;
		}
	}
}

function wpjam_map($value, $callback, ...$args){
	if(wpjam_is_callable($callback)){
		if($value && is_array($value)){
			foreach($value as &$item){
				$item	= wpjam_try($callback, $item, ...$args);
			}
		}
	}

	return $value;
}

function wpjam_call($callback, ...$args){
	if(wpjam_is_callable($callback)){
		try{
			if(is_array($callback) && !is_object($callback[0])){
				return wpjam_call_method($callback[0], $callback[1], ...$args);
			}else{
				return call_user_func_array($callback, $args);
			}
		}catch(WPJAM_Exception $e){
			return $e->get_wp_error();
		}catch(Exception $e){
			return new WP_Error($e->getCode(), $e->getMessage());
		}
	}
}

function wpjam_hooks($hooks){
	if(is_callable($hooks)){
		$hooks	= call_user_func($hooks);
	}

	if(!$hooks || !is_array($hooks)){
		return;
	}

	if(is_array(current($hooks))){
		foreach($hooks as $hook){
			add_filter(...$hook);
		}
	}else{
		add_filter(...$hooks);
	}
}

function wpjam_is_callable($callback){
	if(!is_callable($callback)){
		trigger_error('invalid_callback'.var_export($callback, true));
		return false;
	}

	return true;
}

function wpjam_ob_get_contents($callback, ...$args){
	ob_start();

	call_user_func_array($callback, $args);

	return ob_get_clean();
}

function wpjam_parse_method($model, $method, &$args=[], $number=1){
	if(is_object($model)){
		$object	= $model;
		$model	= get_class($model);
	}else{
		$object	= null;

		if(!class_exists($model)){
			return new WP_Error('invalid_model', [$model]);
		}
	}

	if(!method_exists($model, $method)){
		if(method_exists($model, '__callStatic')){
			$is_public = true;
			$is_static = true;
		}elseif(method_exists($model, '__call')){
			$is_public = true;
			$is_static = false;
		}else{
			return new WP_Error('undefined_method', [$model.'->'.$method.'()']);
		}
	}else{
		$reflection	= new ReflectionMethod($model, $method);
		$is_public	= $reflection->isPublic();
		$is_static	= $reflection->isStatic();
	}

	if($is_static){
		return $is_public ? [$model, $method] : $reflection->getClosure();
	}

	if(is_null($object)){
		if(!method_exists($model, 'get_instance')){
			return new WP_Error('undefined_method', [$model.'->get_instance()']);
		}

		for($i=0; $i < $number; $i++){ 
			$params[]	= $param = array_shift($args);

			if(is_null($param)){
				return new WP_Error('instance_required', '实例方法对象才能调用');
			}
		}

		$object	= call_user_func_array([$model, 'get_instance'], $params);

		if(!$object){
			return new WP_Error('invalid_id', [$model]);
		}
	}

	return $is_public ? [$object, $method] : $reflection->getClosure($object);
}

function wpjam_call_method($model, $method, ...$args){
	$parsed	= wpjam_parse_method($model, $method, $args);

	return is_wp_error($parsed) ? $parsed : call_user_func_array($parsed, $args);
}

function wpjam_value_callback($callback, $name, $id){
	if(is_array($callback) && !is_object($callback[0])){
		$args	= [$id, $name];
		$parsed	= wpjam_parse_method($callback[0], $callback[1], $args);

		if(is_wp_error($parsed)){
			return $parsed;
		}elseif(is_object($parsed[0])){
			return call_user_func_array($parsed, $args);
		}
	}

	return call_user_func($callback, $name, $id);
}

function wpjam_get_callback_parameters($callback){
	if(is_array($callback)){
		$reflection	= new ReflectionMethod(...$callback);
	}else{
		$reflection	= new ReflectionFunction($callback);
	}

	return $reflection->getParameters();
}

function wpjam_get_current_priority($name=null){
	$name	= $name ?: current_filter();
	$hook	= $GLOBALS['wp_filter'][$name] ?? null;

	return $hook ? $hook->current_priority() : null;
}

function wpjam_autoload(){
	foreach(get_declared_classes() as $class){
		if(is_subclass_of($class, 'WPJAM_Register') && method_exists($class, 'autoload')){
			// trigger_error($class);
			call_user_func([$class, 'autoload']);
		}
	}
}

function wpjam_activation(){
	$actives = get_option('wpjam-actives', null);

	if(is_array($actives)){
		foreach($actives as $active){
			if(is_array($active) && isset($active['hook'])){
				add_action($active['hook'], $active['callback']);
			}else{
				add_action('wp_loaded', $active);
			}
		}

		update_option('wpjam-actives', []);
	}elseif(is_null($actives)){
		update_option('wpjam-actives', []);
	}
}

function wpjam_register_activation($callback, $hook=null){
	$actives	= get_option('wpjam-actives', []);
	$actives[]	= $hook ? compact('hook', 'callback') : $callback;

	update_option('wpjam-actives', $actives);
}

function wpjam_register_route($module, $args){
	if(!is_array($args) || wp_is_numeric_array($args)){
		$args	= is_callable($args) ? ['callback'=>$args] : (array)$args;
	}

	return WPJAM_Route::register($module, $args);
}

function wpjam_doing_ajax($action=''){
	if(!wp_doing_ajax()){
		return false;
	}

	if(!$action){
		return true;
	}

	if($action == 'list_action'){
		$action = 'wpjam-list-table-action';
	}elseif($action == 'option'){
		$action	= 'wpjam-option-action';
	}elseif($action == 'page'){
		$action	= 'wpjam-page-action';
	}

	return isset($_REQUEST['action']) && $_REQUEST['action'] == $action;
}

function wpjam_is_module($module='', $action=''){
	$current_module	= wpjam_get_current_module();

	if($module){
		if($action && $action != wpjam_get_current_action()){
			return false;
		}

		return $module == $current_module;
	}else{
		return $current_module ? true : false;
	}
}

function wpjam_get_query_var($key, $wp=null){
	$wp	= $wp ?: $GLOBALS['wp'];

	return $wp->query_vars[$key] ?? null;
}

function wpjam_get_current_module($wp=null){
	return wpjam_get_query_var('module', $wp);
}

function wpjam_get_current_action($wp=null){
	return wpjam_get_query_var('action', $wp);
}

function wpjam_get_current_user($required=false){
	$user	= wpjam_get_current_var('user', $isset);

	if(!$isset){
		$user	= apply_filters('wpjam_current_user', null);

		if(!is_null($user)){
			wpjam_set_current_user($user);
		}
	}

	if($required){
		if(is_null($user)){
			return new WP_Error('bad_authentication');
		}
	}else{
		if(is_wp_error($user)){
			return null;
		}
	}

	return $user;
}

function wpjam_set_current_user($user){
	wpjam_set_current_var('user', $user);
}

function wpjam_get_current_commenter(){
	$commenter	= wp_get_current_commenter();

	if(empty($commenter['comment_author_email'])){
		return new WP_Error('access_denied');
	}

	return $commenter;
}

function wpjam_generate_jwt($payload, $key='', $header=[]){
	if(is_array($payload)){
		$header	= wp_parse_args($header, [
			'alg'	=> 'HS256',
			'typ'	=> 'JWT'
		]);

		if($header['alg'] == 'HS256'){
			$header		= base64_urlencode(wpjam_json_encode($header));
			$payload	= base64_urlencode(wpjam_json_encode($payload));
			$jwt		= $header.'.'.$payload;
			$key		= $key ?: wp_salt();

			return $jwt.'.'.base64_urlencode(hash_hmac('sha256', $jwt, $key, true));
		}
	}

	return false;
}

function wpjam_verify_jwt($token, $key=''){
	$tokens	= $token ? explode('.', $token) : [];

	if(count($tokens) != 3){
		return false;
	}

	list($header, $payload, $sign) = $tokens;

	$jwt		= $header.'.'.$payload;
	$key		= $key ?: wp_salt();
	$header		= wpjam_json_decode(base64_urldecode($header));
	$payload	= wpjam_json_decode(base64_urldecode($payload));

	if(empty($header['alg']) || $header['alg'] != 'HS256'){
		return false;
	}

	if(!hash_equals(base64_urlencode(hash_hmac('sha256', $jwt, $key, true)), $sign)){
		return false;
	}

	//签发时间大于当前服务器时间验证失败
	if(isset($payload['iat']) && $payload['iat'] > time()){
		return false;
	}

	//该nbf时间之前不接收处理该Token
	if(isset($payload['nbf']) && $payload['nbf'] > time()){
		return false;
	}

	//过期时间小于当前服务器时间验证失败
	if(empty($payload['exp']) || $payload['exp'] < time()){
		return false;
	}

	return $payload;
}

function wpjam_get_jwt($key='access_token', $required=false){
	$headers	= getallheaders();

	if(isset($headers['Authorization']) && str_starts_with($headers['Authorization'], 'Bearer')){
		return trim(wpjam_remove_prefix($headers['Authorization'], 'Bearer'));
	}else{
		return wpjam_get_parameter($key, ['required'=>$required]);
	}
}

function wpjam_json_encode($data){
	return WPJAM_JSON::encode($data, JSON_UNESCAPED_UNICODE);
}

function wpjam_json_decode($json, $assoc=true){
	return WPJAM_JSON::decode($json, $assoc);
}

function wpjam_send_json($data=[], $status_code=null){
	WPJAM_JSON::send($data, $status_code);
}

function wpjam_register_json($name, $args=[]){
	return WPJAM_JSON::register($name, $args);
}

function wpjam_get_json_object($name){
	return WPJAM_JSON::get($name);
}

function wpjam_add_json_module_parser($type, $callback){
	$object = wpjam_get_items_object('json_module_parser');

	$object->add_item($type, $callback);
}

function wpjam_get_json_module_parser($type){
	$object	= wpjam_get_items_object('json_module_parser');

	return $object->get_item($type);
}

function wpjam_parse_json_module($module){
	return WPJAM_JSON::parse_module($module);
}

function wpjam_get_current_json($output='name'){
	return WPJAM_JSON::get_current($output);
}

function wpjam_is_json_request(){
	if(get_option('permalink_structure')){
		if(preg_match("/\/api\/.*\.json/", $_SERVER['REQUEST_URI'])){
			return true;
		}
	}else{
		if(isset($_GET['module']) && $_GET['module'] == 'json'){
			return true;
		}
	}

	return false;
}

function wpjam_send_error_json($errcode, $errmsg=''){
	wpjam_send_json(new WP_Error($errcode, $errmsg));
}

function wpjam_die_if_error($result){
	if(is_wp_error($result)){
		wp_die($result);
	}

	return $result;
}

function wpjam_throw_if_error($result){
	if(is_wp_error($result)){
		throw new WPJAM_Exception($result);
	}

	return $result;
}

function wpjam_exception($errmsg, $errcode=null){
	throw new WPJAM_Exception($errmsg, $errcode);
}

function wpjam_parse_error($data){
	return WPJAM_Error::parse($data);
}

function wpjam_register_error_setting($code, $message, $modal=[]){
	return WPJAM_Error::add_setting($code, $message, $modal);
}

function wpjam_register_source($name, $callback, $query_args=['source_id']){
	if(!wpjam_get_items('source')){
		add_filter('wpjam_pre_json', function($pre){
			$name	= wpjam_get_parameter('source');
			$source	= $name ? wpjam_get_item('source', $name) : null;

			if($source){
				$query_data	= wpjam_generate_query_data($source['query_args'], '');

				call_user_func($source['callback'], $query_data);
			}

			return $pre;
		});
	}

	return wpjam_add_item('source', $name, ['callback'=>$callback, 'query_args'=>$query_args]);
}

// wpjam_register_config($key, $value)
// wpjam_register_config($name, $args)
// wpjam_register_config($args)
// wpjam_register_config($name, $callback])
// wpjam_register_config($callback])
function wpjam_register_config(...$args){
	$group	= '';

	if(count($args) >= 3){
		$group	= array_shift($args);
	}

	$group 	= $group ? $group.':config' : 'config';
	$args	= array_filter($args, 'is_exists');

	if($args){
		if(count($args) >= 2){
			$name	= $args[0];
			$args	= $args[1];
			$args	= is_callable($args) ? ['name'=>$name, 'callback'=>$args] : [$name=>$args];
		}else{
			$args	= $args[0];
			$args	= is_callable($args) ? ['callback'=>$args] : $args;
		}

		wpjam_add_item($group, $args);
	}
}

function wpjam_get_config($group=''){
	$group	= is_array($group) ? array_get($group, 'group') : $group;
	$group 	= $group ? $group.':config' : 'config';
	$config	= [];

	foreach(wpjam_get_items($group) as $item){
		$callback	= $item['callback'] ?? '';

		if($callback){
			$name	= $item['name'] ?? '';

			if($name){
				$item	= [$name => call_user_func($callback, $name)];
			}else{
				$item	= call_user_func($callback);
			}
		}

		$config	= array_merge($config, $item);
	}

	return $config;
}

function wpjam_get_parameter($name='', $args=[]){
	return (WPJAM_Parameter::get_instance())->get_value($name, $args);
}

function wpjam_get_post_parameter($name='', $args=[]){
	return wpjam_get_parameter($name, array_merge($args, ['method'=>'POST']));
}

function wpjam_get_request_parameter($name='', $args=[]){
	return wpjam_get_parameter($name, array_merge($args, ['method'=>'REQUEST']));
}

function wpjam_get_data_parameter($name='', $args=[]){
	return (WPJAM_Data_Parameter::get_instance())->get_value($name, $args);
}

function wpjam_method_allow($method){
	if($_SERVER['REQUEST_METHOD'] != strtoupper($method)){
		return wpjam_die('method_not_allow', '接口不支持 '.$_SERVER['REQUEST_METHOD'].' 方法，请使用 '.$method.' 方法！');
	}

	return true;
}

function wpjam_http_request($url, $args=[], $err_args=[], &$headers=null){
	$object	= WPJAM_Request::get_instance();

	try{
		return $object->request($url, $args, $err_args, $headers);
	}catch(WPJAM_Exception $e){
		return $e->get_wp_error();
	}
}

function wpjam_remote_request($url, $args=[], $err_args=[], &$headers=null){
	return wpjam_http_request($url, $args, $err_args, $headers);
}

wpjam_load_extends(WPJAM_BASIC_PLUGIN_DIR.'components', [
	'hook'		=> 'wpjam_loaded',
	'priority'	=> 0,
]);

wpjam_register_extend_option('wpjam-extends', WPJAM_BASIC_PLUGIN_DIR.'extends', [
	'sitewide'	=> true,
	'ajax'		=> false,
	'title'		=> '扩展管理',
	'hook'		=> 'plugins_loaded',
	'priority'	=> 1,
	'menu_page'	=> [
		'network'		=> true,
		'parent'		=> 'wpjam-basic',
		'order'			=> 3,
		'function'		=> 'option',
	]
]);

wpjam_load_extends(get_template_directory().'/extends', [
	'hierarchical'	=> true,
	'hook'			=> 'plugins_loaded',
	'priority'		=> 0,
]);

wpjam_register_route('json', [
	'callback'		=> ['WPJAM_JSON', 'redirect'],
	'rewrite_rule'	=> ['WPJAM_JSON', 'get_rewrite_rule']
]);

wpjam_register_route('txt', [
	'callback'		=> ['WPJAM_Verify_TXT', 'redirect'],
	'rewrite_rule'	=> ['WPJAM_Verify_TXT',	'get_rewrite_rule']
]);

wpjam_add_json_module_parser('post_type',	['WPJAM_Posts', 'parse_json_module']);
wpjam_add_json_module_parser('taxonomy',	['WPJAM_Terms', 'parse_json_module']);
wpjam_add_json_module_parser('setting',		['WPJAM_Setting', 'parse_json_module']);
wpjam_add_json_module_parser('media',		['WPJAM_Posts', 'parse_media_json_module']);
wpjam_add_json_module_parser('data_type',	['WPJAM_Data_Type', 'parse_json_module']);
wpjam_add_json_module_parser('config',		'wpjam_get_config');

wpjam_register_error_setting('invalid_post_type',	'无效的文章类型');
wpjam_register_error_setting('invalid_taxonomy',	'无效的分类模式');
wpjam_register_error_setting('invalid_menu_page',	'页面%s「%s」未定义。');
wpjam_register_error_setting('invalid_item_key',	'「%s」已存在，无法%s。');
wpjam_register_error_setting('invalid_page_key',	'无效的%s页面。');
wpjam_register_error_setting('invalid_name',		'%s不能为纯数字。');
wpjam_register_error_setting('invalid_nonce',		'验证失败，请刷新重试。');
wpjam_register_error_setting('invalid_code',		'验证码错误。');
wpjam_register_error_setting('invalid_password',	'两次输入的密码不一致。');
wpjam_register_error_setting('incorrect_password',	'密码错误。');
wpjam_register_error_setting('bad_authentication',	'无权限');
wpjam_register_error_setting('access_denied',		'操作受限');
wpjam_register_error_setting('value_required',		'%s的值为空或无效。');
wpjam_register_error_setting('undefined_method',	['WPJAM_Error', 'callback']);
wpjam_register_error_setting('quota_exceeded',		['WPJAM_Error', 'callback']);

add_action('plugins_loaded', 'wpjam_activation', 0);

add_action('init',	'wpjam_autoload');	// 放弃

add_action('wp_loaded',		['WPJAM_Route', 'on_loaded']);
add_action('parse_request',	['WPJAM_Route', 'on_parse_request']);
add_filter('query_vars',	['WPJAM_Route', 'filter_query_vars']);

// add_filter('determine_current_user',	[self::class, 'filter_determine_current_user']);
add_filter('wp_get_current_commenter',	['WPJAM_Route', 'filter_current_commenter']);
add_filter('pre_get_avatar_data',		['WPJAM_Route', 'filter_pre_avatar_data'], 10, 2);

add_filter('register_post_type_args',	['WPJAM_Post_Type', 'filter_register_args'], 999, 2);
add_filter('register_taxonomy_args',	['WPJAM_Taxonomy', 'filter_register_args'], 999, 3);

add_action('parse_request',		['WPJAM_Posts', 'on_parse_request'], 1);
add_filter('posts_clauses',		['WPJAM_Posts', 'filter_clauses'], 1, 2);
add_filter('post_type_link',	['WPJAM_Post', 'filter_link'], 1, 2);
add_filter('content_save_pre',	['WPJAM_Post', 'filter_content_save_pre'], 1);
add_filter('content_save_pre',	['WPJAM_Post', 'filter_content_save_pre'], 11);

add_action('wp_enqueue_scripts',	['WPJAM_AJAX', 'on_enqueue_scripts'], 1);
add_action('login_enqueue_scripts',	['WPJAM_AJAX', 'on_enqueue_scripts'], 1);

if(wpjam_is_json_request()){
	add_filter('wp_die_handler', ['WPJAM_Error', 'filter_wp_die_handler']);

	ini_set('display_errors', 0);

	remove_filter('the_title', 'convert_chars');

	remove_action('init', 'wp_widgets_init', 1);
	remove_action('init', 'maybe_add_existing_user_to_blog');
	remove_action('init', 'check_theme_switched', 99);

	remove_action('plugins_loaded', 'wp_maybe_load_widgets', 0);
	remove_action('plugins_loaded', 'wp_maybe_load_embeds', 0);
	remove_action('plugins_loaded', '_wp_customize_include');
	remove_action('plugins_loaded', '_wp_theme_json_webfonts_handler');

	remove_action('wp_loaded', '_custom_header_background_just_in_time');
	remove_action('wp_loaded', '_add_template_loader_filters');
}
