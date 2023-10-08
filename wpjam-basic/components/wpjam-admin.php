<?php
if(!is_admin()){
	return;
}

class WPJAM_Basic_Admin{
	public static function on_admin_init(){
		self::add_sub_page('dashicons',	[
			'menu_title'	=> 'Dashicons',
			'order'			=> 9,
			'function'		=> [self::class, 'dashicons_page'],
			'summary'		=> [
				'Dashicons 功能列出所有的 Dashicons 以及每个的名称和 HTML 代码',
				'https://mp.weixin.qq.com/s/4BEv7KUDVacrX6lRpTd53g',
			]
		]);

		self::add_sub_page('wpjam-about',	[
			'menu_title'	=> '关于WPJAM',
			'order'			=> 1,
			'function'		=> [self::class, 'about_page'],
		]);

		foreach([
			'wpjam-page'	=> ['menu_title'=>'页面设置',		'order'=>15],
			'wpjam-links'	=> ['menu_title'=>'链接设置',		'order'=>14],
			'wpjam-seo'		=> ['menu_title'=>'SEO 设置',	'order'=>12],
			'wpjam-develop'	=> ['menu_title'=>'开发设置',		'order'=>8],
		] as $plugin_page => $args){
			if(WPJAM_Tab_Page::get_registereds(['plugin_page'=>$plugin_page])){
				wpjam_add_menu_page($plugin_page, wp_parse_args($args, [
					'parent'	=> 'wpjam-basic',
					'function'	=> 'tab',
					'network'	=> false
				]));
			}
		}
	}

	public static function add_sub_page($slug, $sub){
		wpjam_add_menu_page($slug, array_merge($sub, ['parent'=>'wpjam-basic']));
	}

	public static function add_separator(){
		$GLOBALS['menu']['58.88']	= ['',	'read',	'separator'.'58.88', '', 'wp-menu-separator'];
	}

	public static function dashicons_page(){
		$file	= fopen(ABSPATH.'/'.WPINC.'/css/dashicons.css','r') or die("Unable to open file!");
		$html	= wpjam_tag();

		while(!feof($file)) {
			$line	= fgets($file);

			if($line && preg_match_all('/.dashicons-(.*?):before/i', $line, $matches) && $matches[1][0] != 'before'){
				wpjam_tag('span', ['dashicons-before', 'dashicons-'.$matches[1][0]])->after('<br />'.$matches[1][0])->wrap('p', ['data-dashicon'=>'dashicons-'.$matches[1][0]])->insert_after($html);
			}
		}

		fclose($file);

		echo '<div class="wpjam-icons">'.$html.'</div>'.'<div class="clear"></div>';
		?>
		<style type="text/css">
		div.wpjam-icons{max-width: 800px; display: flex; flex-wrap: wrap;}
		div.wpjam-icons p{ margin:0px 10px 10px 0; padding: 10px; width:70px; text-align: center; cursor: pointer;}
		div.wpjam-icons .dashicons-before:before{font-size:32px; width: 32px; height: 32px;}
		</style>
		<script type="text/javascript">
		jQuery(function($){
			$('body').on('click', 'div.wpjam-icons p', function(){
				let dashicon	= $(this).data('dashicon');
				let html 		= '<div style="display:flex;"><p><span style="font-size:100px; width: 100px; height: 100px;" class="dashicons '+dashicon+'"></span></p><p style="margin-left:20px; font-size:20px;">'+dashicon+'<br /><br />HTML：<br /><code>&lt;span class="dashicons '+dashicon+'"&gt;&lt;/span&gt;</code></p></dov>';
				
				$.wpjam_show_modal('tb_modal', html, dashicon, 680);
			});
		});
		</script>
		<?php
	}

	public static function about_page(){
		$jam_plugins = get_transient('about_jam_plugins');

		if($jam_plugins === false){
			$response	= wpjam_remote_request('https://jam.wpweixin.com/api/template/get.json?id=5644');

			if(!is_wp_error($response)){
				$jam_plugins	= $response['template']['table']['content'];
				set_transient('about_jam_plugins', $jam_plugins, DAY_IN_SECONDS );
			}
		}

		?>
		<div style="max-width: 900px;">
			<table id="jam_plugins" class="widefat striped">
				<tbody>
				<tr>
					<th colspan="2">
						<h2>WPJAM 插件</h2>
						<p>加入<a href="https://97866.com/s/zsxq/">「WordPress果酱」知识星球</a>即可下载：</p>
					</th>
				</tr>
				<?php foreach($jam_plugins as $jam_plugin){ ?>
				<tr>
					<th style="width: 100px;"><p><strong><a href="<?php echo $jam_plugin['i2']; ?>"><?php echo $jam_plugin['i1']; ?></a></strong></p></th>
					<td><?php echo wpautop($jam_plugin['i3']); ?></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>

			<div class="card">
				<h2>WPJAM Basic</h2>

				<p><strong><a href="https://blog.wpjam.com/project/wpjam-basic/">WPJAM Basic</a></strong> 是 <strong><a href="https://blog.wpjam.com/">我爱水煮鱼</a></strong> 的 Denis 开发的 WordPress 插件。</p>

				<p>WPJAM Basic 除了能够优化你的 WordPress ，也是 「WordPress 果酱」团队进行 WordPress 二次开发的基础。</p>
				<p>为了方便开发，WPJAM Basic 使用了最新的 PHP 7.2 语法，所以要使用该插件，需要你的服务器的 PHP 版本是 7.2 或者更高。</p>
				<p>我们开发所有插件都需要<strong>首先安装</strong> WPJAM Basic，其他功能组件将以扩展的模式整合到 WPJAM Basic 插件一并发布。</p>
			</div>

			<div class="card">
				<h2>WPJAM 优化</h2>
				<p>网站优化首先依托于强劲的服务器支撑，这里强烈建议使用<a href="https://wpjam.com/go/aliyun/">阿里云</a>或<a href="https://wpjam.com/go/qcloud/">腾讯云</a>。</p>
				<p>更详细的 WordPress 优化请参考：<a href="https://blog.wpjam.com/article/wordpress-performance/">WordPress 性能优化：为什么我的博客比你的快</a>。</p>
				<p>我们也提供专业的 <a href="https://blog.wpjam.com/article/wordpress-optimization/">WordPress 性能优化服务</a>。</p>
			</div>
		</div>
		<style type="text/css">
			.card {max-width: 320px; float: left; margin-top:20px;}
			.card a{text-decoration: none;}
			table#jam_plugins{margin-top:20px; width: 520px; float: left; margin-right: 20px;}
			table#jam_plugins th{padding-left: 2em; }
			table#jam_plugins td{padding-right: 2em;}
			table#jam_plugins th p, table#jam_plugins td p{margin: 6px 0;}
		</style>
		<?php 
	}

	public static function builtin_page_load($screen){
		$base	= $screen->base;

		if(in_array($base, ['dashboard', 'dashboard-network', 'dashboard-user'])){
			$name	= str_replace(['dashboard', '-'], '', $base);
			$action	= $name ? 'wp_'.$name.'_dashboard_setup' : 'wp_dashboard_setup';

			add_action($action,	[self::class, 'on_dashboard_setup'], 1);

			wp_add_inline_style('list-tables', "\n".join("\n",[
				'#dashboard_wpjam .inside{margin:0; padding:0;}',
				'a.jam-post {border-bottom:1px solid #eee; margin: 0 !important; padding:6px 0; display: block; text-decoration: none; }',
				'a.jam-post:last-child{border-bottom: 0;}',
				'a.jam-post p{display: table-row; }',
				'a.jam-post img{display: table-cell; width:40px; height: 40px; margin:4px 12px; }',
				'a.jam-post span{display: table-cell; height: 40px; vertical-align: middle;}'
			]));
		}else{
			if(str_starts_with($base, 'plugins') || str_starts_with($base, 'update-core')){
				wpjam_register_plugin_updater('blog.wpjam.com', 'https://jam.wpweixin.com/api/template/get.json?name=wpjam-plugin-versions');

				add_action('admin_head', [self::class, 'on_admin_head']);

				// delete_site_transient( 'update_plugins' );
				// wpjam_print_r(get_site_transient( 'update_plugins' ));
			}

			if(str_starts_with($base, 'themes') || str_starts_with($base, 'update-core')){
				wpjam_register_theme_updater('blog.wpjam.com', 'https://jam.wpweixin.com/api/template/get.json?name=wpjam-theme-versions');

				add_action('admin_head', [self::class, 'on_admin_head']);

				// delete_site_transient( 'update_themes' );
				// wpjam_print_r(get_site_transient( 'update_themes' ));
			}
		}
	}

	public static function on_admin_head(){
		?>
		<script type="text/javascript">
		jQuery(function($){
			$('tr.plugin-update-tr').each(function(){
				let detail_link	= $(this).find('a.open-plugin-details-modal');

				if(detail_link.attr('href').indexOf('https://blog.wpjam.com/') === 0){
					let detail_href	= detail_link.attr('href');
					detail_href		= detail_href.substring(0,  detail_href.indexOf('?TB_iframe'));

					detail_link.attr('href', detail_href).removeClass('thickbox open-plugin-details-modal').attr('target','_blank');
				}
			});
		});
		</script>
		<?php
	}

	public static function update_dashboard_widget(){
		$jam_posts = get_transient('dashboard_jam_posts');

		if($jam_posts === false){
			$response	= wpjam_remote_request('https://jam.wpweixin.com/api/post/list.json', ['timeout'=>1]);
			$jam_posts	= is_wp_error($response) ? [] : $response['posts'];

			set_transient('dashboard_jam_posts', $jam_posts, 12 * HOUR_IN_SECONDS );
		}

		echo '<div class="rss-widget">';

		if($jam_posts){
			$i = 0;
			foreach ($jam_posts as $jam_post){
				if($i == 5) break;
				echo '<a class="jam-post" target="_blank" href="http://blog.wpjam.com'.$jam_post['post_url'].'"><p>'.'<img src="'.str_replace('imageView2/1/w/200/h/200/', 'imageView2/1/w/100/h/100/', $jam_post['thumbnail']).'" /><span>'.$jam_post['title'].'</span></p></a>';
				$i++;
			}
		}

		echo '</div>';
	}

	public static function filter_dashboard_posts_query($query_args){
		$query_args['post_type']		= get_post_types(['show_ui'=>true, 'public'=>true, '_builtin'=>false])+['post'];
		$query_args['cache_results']	= true;

		return $query_args;
	}

	public static function on_pre_get_comments($query){
		$query->query_vars['post_type']	= get_post_types(['show_ui'=>true, 'public'=>true, '_builtin'=>false])+['post'];
		$query->query_vars['type']		= 'comment';
	}

	public static function on_dashboard_setup(){
		$screen	= get_current_screen();

		remove_meta_box('dashboard_primary', $screen, 'side');

		if(is_multisite() && !is_user_member_of_blog()){
			remove_meta_box('dashboard_quick_press', $screen, 'side');
		}

		add_filter('dashboard_recent_posts_query_args',		[self::class, 'filter_dashboard_posts_query']);
		add_filter('dashboard_recent_drafts_query_args',	[self::class, 'filter_dashboard_posts_query']);

		add_action('pre_get_comments',	[self::class, 'on_pre_get_comments']);
		
		$widgets	= apply_filters('wpjam_dashboard_widgets', ['wpjam_update'=>[
			'title'		=> 'WordPress资讯及技巧',
			'context'	=> 'side',	// 位置，normal 左侧, side 右侧
			'callback'	=> [self::class, 'update_dashboard_widget']
		]]);

		foreach($widgets as $widget_id => $widget){
			$title		= $widget['title'];
			$callback	= $widget['callback'] ?? wpjam_get_filter_name($widget_id, 'dashboard_widget_callback');
			$context	= $widget['context'] ?? 'normal';	// 位置，normal 左侧, side 右侧
			$args		= $widget['args'] ?? [];

			add_meta_box($widget_id, $title, $callback, $screen, $context, 'core', $args);
		}
	}
}

class WPJAM_Verify{
	public static function verify(){
		$verify_user	= get_user_meta(get_current_user_id(), 'wpjam_weixin_user', true);

		if(empty($verify_user)){
			return false;
		}elseif(time() - $verify_user['last_update'] < DAY_IN_SECONDS){
			return true;
		}

		$openid		= $verify_user['openid'];
		$hash		= $verify_user['hash']	?? '';
		$user_id	= get_current_user_id();

		if(get_transient('fetching_wpjam_weixin_user_'.$openid)){
			return false;
		}

		set_transient('fetching_wpjam_weixin_user_'.$openid, 1, 10);

		if($hash){
			$response	= wpjam_remote_request('http://wpjam.wpweixin.com/api/weixin/verify.json', [
				'method'	=> 'POST',
				'body'		=> ['openid'=>$openid, 'hash'=>$hash]
			]);
		}else{
			$response	= wpjam_remote_request('http://jam.wpweixin.com/api/topic/user/get.json?openid='.$openid);
		}

		if(is_wp_error($response) && $response->get_error_code() != 'invalid_openid'){
			$failed_times	= (int)get_user_meta($user_id, 'wpjam_weixin_user_failed_times');
			$failed_times ++;

			if($failed_times >= 3){	// 重复三次
				delete_user_meta($user_id, 'wpjam_weixin_user_failed_times');
				delete_user_meta($user_id, 'wpjam_weixin_user');
			}else{
				update_user_meta($user_id, 'wpjam_weixin_user_failed_times', $failed_times);
			}

			return false;
		}

		if($hash){
			$verify_user	= $response;
		}else{
			$verify_user	= $response['user'];
		}

		delete_user_meta($user_id, 'wpjam_weixin_user_failed_times');

		if(empty($verify_user) || !$verify_user['subscribe']){
			delete_user_meta($user_id, 'wpjam_weixin_user');

			return false;
		}else{
			update_user_meta($user_id, 'wpjam_weixin_user', array_merge($verify_user, ['last_update'=>time()]));

			return true;
		}
	}

	public static function get_form(){
		wp_add_inline_style('list-tables', "\n".'.form-table th{width: 100px;}');

		$qrcode	= wpjam_tag('img', ['src'=>'https://open.weixin.qq.com/qr/code?username=wpjamcom', 'style'=>'max-width:250px;'])->wrap('p')->before('p', [], '使用微信扫描下面的二维码：');

		return [
			'submit_text'	=> '验证',
			'response'		=> 'redirect',
			'callback'		=> [self::class, 'ajax_callback'],
			'fields'		=> [
				'qr_view'	=> ['title'=>'1. 二维码',		'type'=>'view',		'value'=>$qrcode],
				'keyword'	=> ['title'=>'2. 关键字',		'type'=>'view',		'value'=>'回复关键字「<strong>验证码</strong>」。'],
				'code'		=> ['title'=>'3. 验证码',		'type'=>'number',	'before'=>'将获取验证码输入提交即可'],
				'notes'		=> ['title'=>'4. 注意事项',	'type'=>'view',		'value'=>'<p>验证码5分钟内有效！</p><p>如果验证不通过，请使用 Chrome 浏览器验证，并在验证之前清理浏览器缓存。<br />如多次测试无法通过，可以尝试重新关注公众号测试！</p>'],
			]
		];
	}

	public static function ajax_callback(){
		// $url	= 'http://jam.wpweixin.com/api/weixin/qrcode/verify.json';
		$url	= 'https://wpjam.wpweixin.com/api/weixin/verify.json';
		$data	= wpjam_get_post_parameter('data', ['sanitize_callback'=>'wp_parse_args']);

		$verify_user	= wpjam_remote_request($url, [
			'method'	=> 'POST',
			'body'		=> $data
		]);

		if(is_wp_error($verify_user)){
			return $verify_user;
		}

		update_user_meta(get_current_user_id(), 'wpjam_weixin_user', array_merge($verify_user, ['last_update'=>time(), 'subscribe'=>1]));

		return ['url'=>admin_url('page=wpjam-extends')];
	}

	public static function on_admin_init(){
		$menu_filter	= 'wpjam_'.wpjam_get_admin_prefix().'pages';

		if(get_transient('wpjam_basic_verify')){
			add_filter($menu_filter, [self::class, 'filter_menu_pages']);
		}elseif(self::verify()){
			if(isset($_GET['unbind_wpjam_user'])){
				delete_user_meta(get_current_user_id(), 'wpjam_weixin_user');

				wp_redirect(admin_url('page=wpjam-verify'));
			}
		}else{
			add_filter($menu_filter, [self::class, 'filter_menu_pages']);

			wpjam_add_menu_page('wpjam-verify', [
				'parent'		=> 'wpjam-basic',
				'order'			=> 3,
				'menu_title'	=> '扩展管理',
				'page_title'	=> '验证 WPJAM',
				'function'		=> 'form',
				'form'			=> [self::class, 'get_form']
			]);
		}
	}

	public static function filter_menu_pages($menu_pages){
		$subs	= &$menu_pages['wpjam-basic']['subs'];

		if(get_transient('wpjam_basic_verify')){
			$subs	= array_except($subs, ['wpjam-about']);
		}elseif(!self::verify()){
			$subs	= wp_array_slice_assoc($subs, ['wpjam-basic', 'wpjam-verify']);
		}

		return $menu_pages;
	}
}

wpjam_add_admin_load([
	'type'	=> 'builtin_page',
	'model'	=> 'WPJAM_Basic_Admin'
]);


add_action('admin_menu',		['WPJAM_Basic_Admin', 'add_separator']);
add_action('wpjam_admin_init',	['WPJAM_Basic_Admin', 'on_admin_init'], 99);
add_action('wpjam_admin_init',	['WPJAM_Verify', 'on_admin_init'], 100);