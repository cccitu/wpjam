<?php
/*
Name: 定时作业
URI: https://mp.weixin.qq.com/s/kqlag2-RWn_n481R0QCJHw
Description: 系统信息让你在后台一个页面就能够快速实时查看当前的系统状态。
Version: 2.0
*/
if(!is_admin()){
	return;
}

class WPJAM_Server_Status{
	public static function server_widget($dashboard, $meta_box){
		$items		= [];

		$items[]	= ['title'=>'服务器',	'value'=>gethostname().'（'.$_SERVER['HTTP_HOST'].'）'];
		$items[]	= ['title'=>'服务器IP',	'value'=>'内网：'.gethostbyname(gethostname())];
		$items[]	= ['title'=>'系统',		'value'=>php_uname('s')];

		if(strpos(ini_get('open_basedir'), ':/proc') !== false){
			if(@is_readable('/proc/cpuinfo')){
				$cpus	= trim(file_get_contents('/proc/cpuinfo'));
				$cpus	= explode("\n\n", $cpus);
				$cpu_count	= count($cpus);
			}else{
				$cpu_count	= 0;
			}
			
			if(@is_readable('/proc/meminfo')){
				$mems	= trim(file_get_contents('/proc/meminfo'));
				$mems	= explode("\n", $mems);

				$mems_list = [];

				foreach ($mems as $mem) {
					list($k, $v)	= explode(':', $mem);
					$mems_list[$k]	= (int)$v;
				}

				$mem_total	= $mems_list['MemTotal'];
			}else{
				$mem_total	= 0;
			}

			$value	= [];

			if($cpu_count){
				$value[]	= $cpu_count.'核CPU';
			}

			if($mem_total){
				$value[]	= round($mem_total/1024/1024).'G内存';
			}

			if($value){
				$value	= implode('&nbsp;&nbsp;/&nbsp;&nbsp;', $value);
			}else{
				$value	= '无法获取信息';	
			}

			$items[]	= ['title'=>'配置',	'value'=>$value];
		
			if(@is_readable('/proc/meminfo')){
				$uptime = trim(file_get_contents('/proc/uptime'));
				$uptime	= explode(' ', $uptime);
				$value	= human_time_diff(time()-$uptime[0]);
			}else{
				$value	='无法获取信息';	
			}

			$items[]	= ['title'=>'运行时间',	'value'=>$value];
			$items[]	= ['title'=>'空闲率',	'value'=>round($uptime[1]*100/($uptime[0]*$cpu_count), 2).'%'];
			$items[]	= ['title'=>'系统负载',	'value'=>'<strong>'.implode('&nbsp;&nbsp;',sys_getloadavg()).'</strong>'];
		}

		$items[]	= ['title'=>'文档根目录',	'value'=>$_SERVER['DOCUMENT_ROOT']];
		
		self::output($items);
	}

	public static function php_widget($dashboard, $meta_box){
		self::output([['title'=>'',	'value'=>implode(', ', get_loaded_extensions())]]);
	}

	public static function apache_widget($dashboard, $meta_box){
		self::output([['title'=>'',	'value'=>implode(', ', apache_get_modules())]]);
	}

	public static function version_widget($dashboard, $meta_box){
		global $wpdb, $required_mysql_version, $required_php_version, $wp_version,$wp_db_version, $tinymce_version;

		$http_server	= explode('/', $_SERVER['SERVER_SOFTWARE'])[0];

		self::output([
			['title'=>$http_server,	'value'=>$_SERVER['SERVER_SOFTWARE']],
			['title'=>'MySQL',		'value'=>$wpdb->db_version().'（最低要求：'.$required_mysql_version.'）'],
			['title'=>'PHP',		'value'=>phpversion().'（最低要求：'.$required_php_version.'）'],
			['title'=>'Zend',		'value'=>Zend_Version()],
			['title'=>'WordPress',	'value'=>$wp_version.'（'.$wp_db_version.'）'],
			['title'=>'TinyMCE',	'value'=>$tinymce_version]
		]);
	}

	public static function opcache_status_widget($dashboard, $meta_box){
		$items	= [];
		$status	= opcache_get_status();

		foreach($status['opcache_statistics'] as $key => $value){
			$items[]	= ['title'=>$key, 'value'=>$value];
		}
		
		self::output($items);
	}

	public static function opcache_usage_widget($dashboard, $meta_box){
		global $current_admin_url;

		$capability	= is_multisite() ? 'manage_site':'manage_options';

		if(current_user_can($capability)){
			$action	= $_GET['action'] ?? '';
			if($action == 'flush'){
				check_admin_referer('flush-opcache');
				opcache_reset();

				$redirect_to = add_query_arg(['deleted' => 'true'], wpjam_get_referer());
				wp_redirect($redirect_to);
			}
			 
			?>
			<p><a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=flush', 'flush-opcache'))?>" class="button-primary">刷新缓存</a></p>
			<?php 
		}

		$status	= opcache_get_status();

		$counts	= [
			['label'=>'已用内存',	'count'=>round($status['memory_usage']['used_memory']/(1024*1024),2)],
			['label'=>'剩余内存',	'count'=>round($status['memory_usage']['free_memory']/(1024*1024),2)],
			['label'=>'浪费内存',	'count'=>round($status['memory_usage']['wasted_memory']/(1024*1024),2)]
		];

		$total	= round(($status['memory_usage']['used_memory']+$status['memory_usage']['free_memory']+$status['memory_usage']['wasted_memory'])/(1024*1024),2);

		wpjam_donut_chart($counts, ['title'=>'内存使用', 'total'=>$total, 'chart_width'=>150, 'table_width'=>320]);

		$counts		= [
			['label'=>'命中',		'count'=>$status['opcache_statistics']['hits']],
			['label'=>'未命中',		'count'=>$status['opcache_statistics']['misses']]
		];

		$total	= $status['opcache_statistics']['hits']+$status['opcache_statistics']['misses'];

		wpjam_donut_chart($counts, ['title'=>'命中率', 'total'=>$total, 'chart_width'=>150, 'table_width'=>320]);

		$counts	= [
			['label'=>'已用Keys',	'count'=>$status['opcache_statistics']['num_cached_keys']],
			['label'=>'剩余Keys',	'count'=>$status['opcache_statistics']['max_cached_keys']-$status['opcache_statistics']['num_cached_keys']]
		];

		$total	= $status['opcache_statistics']['max_cached_keys'];

		wpjam_donut_chart($counts, ['title'=>'存储Keys','total'=>$total,'chart_width'=>150,'table_width'=>320]);

		$counts	= [
			['label'=>'已用内存',	'count'=>round($status['interned_strings_usage']['used_memory']/(1024*1024),2)],
			['label'=>'剩余内存',	'count'=>round($status['interned_strings_usage']['free_memory']/(1024*1024),2)]
		];

		$total	= round($status['interned_strings_usage']['buffer_size']/(1024*1024),2);

		wpjam_donut_chart($counts, ['title'=>'临时字符串存储内存','total'=>$total,'chart_width'=>150,'table_width'=>320]);
	}

	public static function opcache_configuration_widget($dashboard, $meta_box){
		$items	= [];

		$configuration = opcache_get_configuration();

		foreach($configuration['version'] as $key => $value){
			$items[]	= ['title'=>$key, 'value'=>$value];
		}

		foreach($configuration['directives'] as $key => $value){
			$items[]	= ['title'=>str_replace('opcache.', '', $key), 'value'=>$value];
		}
		
		self::output($items);
	}

	public static function memcached_status_widget($dashboard, $meta_box){
		global $wp_object_cache;

		$items	= [];

		foreach($wp_object_cache->get_mc()->getStats() as $key => $details){
			// $items[]	= ['title'=>'Memcached进程ID',	'value'=>$details['pid']];
			$items[]	= ['title'=>'Memcached地址',		'value'=>$key];
			$items[]	= ['title'=>'Memcached版本',		'value'=>$details['version']];
			$items[]	= ['title'=>'启动时间',			'value'=>wpjam_date('Y-m-d H:i:s',($details['time']-$details['uptime']))];
			$items[]	= ['title'=>'运行时间',			'value'=>human_time_diff(0,$details['uptime'])];
			$items[]	= ['title'=>'已用/分配的内存',		'value'=>size_format($details['bytes']).' / '.size_format($details['limit_maxbytes'])];
			$items[]	= ['title'=>'启动后总数量',		'value'=>$details['curr_items'].' / '.$details['total_items']];
			$items[]	= ['title'=>'为获取内存踢除数量',	'value'=>$details['evictions']];
			$items[]	= ['title'=>'当前/总打开连接数',	'value'=>$details['curr_connections'] . ' / ' . $details['total_connections']];
			$items[]	= ['title'=>'命中次数',			'value'=>$details['get_hits']];
			$items[]	= ['title'=>'未命中次数',			'value'=>$details['get_misses']];
			$items[]	= ['title'=>'总获取请求次数',		'value'=>$details['cmd_get']];
			$items[]	= ['title'=>'总设置请求次数',		'value'=>$details['cmd_set']];
			$items[]	= ['title'=>'Item平均大小',		'value'=>size_format($details['bytes']/$details['curr_items'])];
		}

		self::output($items);
	}

	public static function memcached_usage_widget($dashboard, $meta_box){
		global $current_admin_url, $wp_object_cache;

		$capability	= is_multisite() ? 'manage_site' : 'manage_options';

		if(current_user_can($capability)){
			$action	= $_GET['action'] ?? '';
			if($action == 'flush'){
				check_admin_referer('flush-memcached');
				wp_cache_flush();

				wp_redirect(add_query_arg(['deleted' => 'true'], wpjam_get_referer()));
			}
			
			?>
			<p><a href="<?php echo esc_url(wp_nonce_url($current_admin_url.'&action=flush', 'flush-memcached'))?>" class="button-primary">刷新缓存</a></p>
			<?php 
		}

		$items	= [];

		foreach($wp_object_cache->get_mc('defaul')->getStats() as $key => $details){
			$counts	= [
				['label'=>'命中次数',	'count'=>$details['get_hits']],
				['label'=>'未命中次数',	'count'=>$details['get_misses']]
			];

			$total	= $details['cmd_get'];

			wpjam_donut_chart($counts, ['title'=>'命中率','total'=>$total,'chart_width'=>150,'table_width'=>320]);

			$counts	= [
				['label'=>'已用内存',	'count'=>round($details['bytes']/(1024*1024),2)],
				['label'=>'剩余内存',	'count'=>round(($details['limit_maxbytes']-$details['bytes'])/(1024*1024),2)]
			];

			$total	= round($details['limit_maxbytes']/(1024*1024),2);

			wpjam_donut_chart($counts, ['title'=>'内存使用','total'=>$total,'chart_width'=>150,'table_width'=>320]);
		}
	}

	public static function memcached_usage_efficiency_widget(){
		global $wp_object_cache;

		foreach($wp_object_cache->get_mc('defaul')->getStats() as $key => $details){
			self::output([
				['title'=>'每秒命中次数',		'value'=>round($details['get_hits']/$details['uptime'],2)],
				['title'=>'每秒未命中次数',	'value'=>round($details['get_misses']/$details['uptime'],2)],
				['title'=>'每秒获取请求次数',	'value'=>round($details['cmd_get']/$details['uptime'],2)],
				['title'=>'每秒设置请求次数',	'value'=>round($details['cmd_set']/$details['uptime'],2)],
			]);
		}
	}

	public static function output($items){
		?>
		<table class="widefat striped" style="border:none;">
			<tbody><?php foreach($items as $item){ ?>
				<tr><?php if($item['title']){ ?>
					<td><?php echo $item['title'] ?></td>
					<td><?php echo $item['value'] ?></td>
				<?php }else{ ?>
					<td colspan="2"><?php echo $item['value'] ?></td>
				<?php } ?></tr>
			<?php } ?></tbody>
		</table>
		<?php
	}

	public static function get_tabs(){
		$tabs	= [];

		$tabs['server']	= ['title'=>'服务器',	'function'=>'dashboard',	'dashboard_name'=>'server'];

		wpjam_register_dashboard('server', ['widgets'=>[]]);

		if(strtoupper(substr(PHP_OS,0,3)) !== 'WIN'){
			wpjam_register_dashboard_widget('server',	['title'=>'服务器信息',	'dashboard'=>'server',	'callback'=>[self::class, 'server_widget']]);
		}

		wpjam_register_dashboard_widget('version',		['title'=>'服务器版本',	'dashboard'=>'server',	'callback'=>[self::class, 'version_widget'],	'context'=>'side']);
		wpjam_register_dashboard_widget('php',			['title'=>'PHP扩展',		'dashboard'=>'server',	'callback'=>[self::class, 'php_widget']]);

		if($GLOBALS['is_apache'] && function_exists('apache_get_modules')){
			wpjam_register_dashboard_widget('apache',	['title'=>'Apache模块',	'dashboard'=>'server',	'callback'=>[self::class, 'apache_widget'],	'context'=>'side']);
		}

		if(function_exists('opcache_get_status')){
			$tabs['opcache']	= ['title'=>'Opcache',	'function'=>'dashboard',	'widgets'=>[
				'opcache-usage'			=> ['title'=>'OPCache使用率',	'callback'=>[self::class, 'opcache_usage_widget']],
				'opcache-status'		=> ['title'=>'OPCache状态',		'callback'=>[self::class, 'opcache_status_widget'],			'context'=>'side'],
				'opcache-configuration'	=> ['title'=>'OPCache配置信息',	'callback'=>[self::class, 'opcache_configuration_widget'],	'context'=>'side']
			]];
		}

		if(method_exists('WP_Object_Cache', 'get_mc')){
			$tabs['memcached']	= ['title'=>'Memcached',	'function'=>'dashboard',	'dashboard_name'=>'memcached'];

			wpjam_register_dashboard('memcached',		['widgets'=>[
				'memcached-usage'		=> ['title'=>'Memcached使用率',	'callback'=>[self::class, 'memcached_usage_widget']],
				'memcached-efficiency'	=> ['title'=>'Memcached效率',	'callback'=>[self::class, 'memcached_usage_efficiency_widget']],
				'memcached-status'		=> ['title'=>'Memcached状态',	'callback'=>[self::class, 'memcached_status_widget'],	'context'=>'side']
			]]);
		}

		return $tabs;
	}
}

wpjam_add_menu_page('server-status',	[
	'parent'		=> 'wpjam-basic',
	'menu_title'	=> '系统信息',
	'summary'		=> __FILE__,
	'order'			=> 9,
	'function'		=> 'tab',
	'tabs'			=> ['WPJAM_Server_Status', 'get_tabs']
]);