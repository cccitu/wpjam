<?php
/*
Name: 百度站长
URI: https://mp.weixin.qq.com/s/_nPXcLPS2pFZZVhCH9SNaQ
Description: 百度站长扩展实现主动，被动，自动以及批量方式提交链接到百度站长，让博客的文章能够更快被百度收录。
Version: 1.0
*/
class WPJAM_Baidu_ZZ extends WPJAM_Option_Model{
	public static function get_fields(){
		return [
			'site'	=> ['title'=>'站点 (site)',	'type'=>'text',	'class'=>'all-options'],
			'token'	=> ['title'=>'密钥 (token)',	'type'=>'text'],
			'no_js'	=> ['title'=>'不加载推送JS',	'type'=>'checkbox', 'description'=>'插件已支持主动推送，不加载百度推送JS'],
		];
	}

	public static function get_set_fields(){
		$fields	= self::get_fields();

		foreach($fields as $key => &$field){
			$field['value']	= self::get_setting($key);
		}

		return $fields;
	}

	public static function get_batch_fields(){
		return ['view'	=> [
			'type'	=> 'view',
			'value'	=> '已设置百度站长的站点和密钥（'.wpjam_get_page_button('set_baidu_zz', ['button_text' => '修改', 'class'=>'']).'），可以使用百度站长更新内容接口批量将博客中的所有内容都提交给百度搜索资源平台。'
		]];
	}

	public static function submittable(){
		return self::get_setting('site') && self::get_setting('token');
	}

	public static function get_menu_page(){
		$tab_page	= [
			'tab_slug'		=> 'baidu-zz',
			'plugin_page'	=> 'wpjam-seo',
			'summary'		=> __FILE__,
		];

		if(self::submittable()){
			wpjam_register_page_action('set_baidu_zz', [
				'title' 		=> '设置',
				'submit_text'	=> '设置',
				'validate'		=> true,
				'callback'		=> [self::class, 'set'],
				'fields'		=> [self::class, 'get_set_fields'],
				'response'		=> 'redirect'
			]);

			return array_merge($tab_page, [
				'function'		=> 'form',
				'submit_text'	=> '批量提交',
				'callback'		=> [self::class, 'batch_submit'],
				'fields'		=> [self::class, 'get_batch_fields'],
			]);
		}else{
			return array_merge($tab_page, [
				'function'		=> 'option',
				'option_name'	=> 'baidu-zz',
			]);
		}
	}

	public static function get_admin_load(){
		if(self::submittable()){
			return [
				'base'	=> ['post','edit'], 
				'model'	=> self::class
			];
		}
	}

	public static function set($data){
		foreach(self::get_fields() as $key => $field){
			$value	= $data[$key] ?? '';

			self::update_setting($key, $value);
		}

		return self::submittable() ? true : $GLOBALS['current_admin_url'];
	}

	public static function submit($urls, $type=''){
		if(!$urls || !self::submittable()){
			return;
		}

		$site	= self::get_setting('site');
		$token	= self::get_setting('token');
		$args	= compact('site', 'token');

		if($type){
			$args['type']	= $type;
		}

		if(is_array($urls)){
			$current	= parse_url(site_url(), PHP_URL_HOST);
			$body		= '';

			foreach($urls as &$url){
				if($current != $site){
					$url	= str_replace($current, $site, $url);
				}

				$body	.= $url."\n";
			}
		}else{
			$body	= $urls;
		}

		return wpjam_remote_request(add_query_arg($args, 'http://data.zz.baidu.com/urls'), [
			'headers'	=> ['Accept-Encoding'=>'', 'Content-Type'=>'text/plain'],
			'sslverify'	=> false,
			'body'		=> $body
		]);
	}

	public static function submit_post($post_id, $type=''){
		if(!self::submittable()){
			return;
		}

		if(is_array($post_id)){
			$wp_error	= false;
			$post_ids	= $post_id;
		}else{
			$wp_error	= wp_doing_ajax();
			$post_ids	= [$post_id];
		}
		
		$urls	= [];

		foreach($post_ids as $post_id){
			if(get_post_status($post_id) == 'publish'){
				if(wp_cache_get($post_id, 'baidu_zz_notified') === false){
					wp_cache_set($post_id, true, 'baidu_zz_notified', HOUR_IN_SECONDS);

					$urls[]	= get_permalink($post_id);
				}else{
					if($wp_error){
						wp_die('一小时内已经提交过了');
					}
				}
			}else{
				if($wp_error){
					wp_die('未发布的文章不能同步到百度站长');
				}
			}
		}

		if(!$urls){
			if($wp_error){
				wp_die('没有需要提交到百度站长的链接');
			}else{
				return true;
			}
		}

		return self::submit($urls, $type);
	}

	public static function batch_submit(){
		$submited	= (int)self::get_setting('submited');

		if(time() - (int)self::get_setting('last') < DAY_IN_SECONDS){
			if($submited == -1){
				return ['notice_type'=>'info', 'errmsg'=>'所有页面都已提交'];
			}else{
				wp_die('批量提交的配额已用完，请稍后重试');
			}
		}

		if($submited == -1){
			$submited	= 0;
		}

		$per_page	= 500;
		$offset		= (int)wpjam_get_data_parameter('offset',	['default'=>0]);
		$query		= new WP_Query([
			'post_type'			=> 'any',
			'post_status'		=> 'publish',
			'order'				=> 'ASC',
			'fields'			=> 'ids',
			'posts_per_page'	=> $per_page,
			'offset'			=> $offset
		]);

		if($query->have_posts()){
			$result	= self::submit_post($query->posts);
			$count	= count($query->posts);
			$number	= $offset+$count;

			if(is_array($result) && $result['remain'] <= 500){
				self::update_setting('last', time());
				self::update_setting('submited', $submited+$offset-$per_page);

				wp_die('今日提交了'.$number.'个页面，批量提交的配额已用完，请明日接着提交');
			}
		}else{
			$count	= 0;
			$number	= $offset+$count;
		}

		if($count < $per_page){
			self::update_setting('last', time());
			self::update_setting('submited', -1);

			return [
				'notice_type'	=> 'success',
				'errmsg'		=> '提交成功，本次提交了'.$number.'个页面。',
			];
		}else{
			return [
				'done'			=> 0,
				'errmsg'		=> '批量提交中，请勿关闭浏览器，本次提交了'.$number.'个页面。',
				'notice_type'	=> 'info',
				'args'			=> http_build_query(['offset'=>$number])
			];
		}
	}

	public static function on_after_insert_post($post_id, $post, $update, $post_before){
		if((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			|| get_post_status($post) != 'publish' 
			|| !current_user_can('edit_post', $post_id)
		){
			return;
		}

		$daily	= wpjam_get_post_parameter('baidu_zz_daily');
		$type	= $daily ? 'daily' : '';

		self::submit_post($post_id, $type);
	}

	public static function on_publish_future_post($post_id){
		self::submit_post($post_id);
	}

	public static function on_enqueue_scripts(){
		if(self::get_setting('no_js') || is_404() || is_preview() || (is_singular() && get_post_status() != 'publish')){
			return;
		}

		$push_js	= is_ssl() ? 'https://zz.bdstatic.com/linksubmit/push.js' : 'http://push.zhanzhang.baidu.com/push.js';

		wp_enqueue_script('baidu_zz_push', $push_js, '', '', true);
	}

	public static function on_post_submitbox_misc_actions(){ ?>
		<div class="misc-pub-section" id="baidu_zz_section">
			<input type="checkbox" name="baidu_zz_daily" id="baidu_zz" value="1">
			<label for="baidu_zz_daily">提交给百度站长快速收录</label>
		</div>
	<?php }

	public static function builtin_page_load($screen){
		if($screen->base == 'edit'){
			if(is_post_type_viewable($screen->post_type)){
				wpjam_register_list_table_action('notify_baidu_zz', [
					'title'			=> '提交到百度',
					'post_status'	=> ['publish'],
					'callback'		=> [self::class, 'submit_post'],
					'bulk_callback'	=> [self::class, 'submit_post'],
					'row_action'	=> false,
					'bulk'			=> true,
					'direct'		=> true
				]);
			}
		}elseif($screen->base == 'post'){
			if(is_post_type_viewable($screen->post_type)){
				add_action('wp_after_insert_post',			[self::class, 'on_after_insert_post'], 10, 4);
				add_action('post_submitbox_misc_actions',	[self::class, 'on_post_submitbox_misc_actions'],11);

				wp_add_inline_style('list-tables', '#post-body #baidu_zz_section:before{content: "\f103"; color:#82878c; font: normal 20px/1 dashicons; speak: none; display: inline-block; margin-left: -1px; padding-right: 3px; vertical-align: top; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }');
			}
		}
	}

	public static function add_hooks(){
		add_action('publish_future_post',	[self::class, 'on_publish_future_post'], 11);
		add_action('wp_enqueue_scripts',	[self::class, 'on_enqueue_scripts']);
	}
}

wpjam_register_option('baidu-zz',	['model'=>'WPJAM_Baidu_ZZ', 'title'=>'百度站长', 'ajax'=>false]);	