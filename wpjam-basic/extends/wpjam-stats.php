<?php
/*
Name: 统计代码
URI: https://mp.weixin.qq.com/s/C_Dsjy8ahr_Ijmcidk61_Q
Description: 统计代码扩展最简化插入 Google 分析和百度统计的代码。
Version: 1.0
*/
class WPJAM_Site_Stats extends WPJAM_Option_Model{
	public static function baidu_tongji(){
		$id	= self::get_setting('baidu_tongji_id');

		if($id){ ?>

		<script type="text/javascript">
			var _hmt = _hmt || [];
			(function(){
			var hm = document.createElement("script");
			hm.src = "https://hm.baidu.com/hm.js?<?php echo $id;?>";
			hm.setAttribute('async', 'true');
			document.getElementsByTagName('head')[0].appendChild(hm);
			})();
		</script>

		<?php } 
	}

	public static function google_analytics(){
		$id	= self::get_setting('google_analytics_id');

		if($id){ ?>
		
		<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $id; ?>"></script>
		<script>
		window.dataLayer = window.dataLayer || [];
		function gtag(){dataLayer.push(arguments);}
		gtag('js', new Date());

		gtag('config', '<?php echo $id; ?>');
		</script>

		<?php }
	}

	public static function get_fields(){
		return [
			'baidu_tongji_id'		=>['title'=>'百度统计',		'type'=>'text'],
			'google_analytics_id'	=>['title'=>'Google 分析',	'type'=>'text'],
		];
	}

	public static function get_menu_page(){
		return [
			'plugin_page'	=> 'wpjam-page',
			'tab_slug'		=> 'stats',
			'title'			=> '统计代码',
			'function'		=> 'option',
			'option_name'	=> 'wpjam-basic',
			'summary'		=> __FILE__,
		];	
	}

	public static function on_head(){
		if(is_preview()){
			return;
		}

		self::google_analytics(); 
		self::baidu_tongji(); 
	}

	public static function add_hooks(){
		add_action('wp_head', [self::class, 'on_head'], 11);
	}
}

wpjam_register_option('wpjam-basic', [
	'plugin_page'	=> 'wpjam-page',
	'current_tab'	=> 'stats',
	'site_default'	=> true,
	'model'			=> 'WPJAM_Site_Stats',
]);