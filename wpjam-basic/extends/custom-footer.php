<?php
/*
Name: 文章页代码
URI: https://mp.weixin.qq.com/s/xbaOgxyGHs9ysL5-bTEbcw
Description: 在文章编辑页面可以单独设置每篇文章 head 和 Footer 代码。
Version: 1.0
*/
class WPJAM_Post_Custom_Code{
	public static function get_sections(){
		$options	= [
			0		=> '不在文章列表页设置文章页代码', 
			1		=> '在文章列表页设置文章页代码', 
			'only'	=> '只在文章列表页设置文章页代码'
		];

		return ['posts'=>['fields'=>['custom-post'	=> ['title'=>'文章页代码',	'type'=>'select',	'options'=>$options]]]];
	}

	public static function match_callback($post_type){
		return is_post_type_viewable($post_type) && post_type_supports($post_type, 'editor');
	}

	public static function on_footer(){
		if(is_singular()){
			echo get_post_meta(get_the_ID(), 'custom_footer', true);
		}
	}

	public static function on_head(){
		if(is_singular()){
			echo get_post_meta(get_the_ID(), 'custom_head', true);
		}
	}

	public static function filter_post_json($post_json, $post_id){
		if(is_singular()){
			$post_json['custom_head']	= (string)get_post_meta($post_id, 'custom_head', true);
			$post_json['custom_footer']	= (string)get_post_meta($post_id, 'custom_footer', true);
		}

		return $post_json;
	}

	public static function add_hooks(){
		add_filter('wp_footer',			[self::class, 'on_footer']);
		add_filter('wp_head',			[self::class, 'on_head']);
		add_filter('wpjam_post_json',	[self::class, 'filter_post_json'], 10, 3);

		wpjam_register_post_option('custom_post', [
			'title'			=> '文章页代码',
			'post_type'		=> [self::class, 'match_callback'],
			'summary'		=> '自定义文章代码可以让你在当前文章插入独有的 JS，CSS，iFrame 等类型的代码，让你可以对具体一篇文章设置不同样式和功能，展示不同的内容。',
			'list_table'	=> wpjam_basic_get_setting('custom-post'),
			'fields'		=> [
				'custom_head'	=>['title'=>'头部代码',	'type'=>'textarea'],
				'custom_footer'	=>['title'=>'底部代码',	'type'=>'textarea']
			]
		]);
	}
}

wpjam_add_option_section('wpjam-basic', ['model'=>'WPJAM_Post_Custom_Code']);