<?php
/*
Name: 301 跳转
URI: https://mp.weixin.qq.com/s/e9jU49ASszsY95TrmT34TA
Description: 301跳转扩展支持设置网站上的 404 页面正确跳转到正常页面。
Version: 1.0
*/
class WPJAM_301_Redirect extends WPJAM_Model{
	public static function get_handler(){
		$handler	= wpjam_get_handler('301-redirects');

		return $handler ?: wpjam_register_handler([
			'option_name'	=> '301-redirects',
			'primary_key'	=> 'id',
			'max_items'		=> 50,
		]);
	}

	public static function get_fields($action_key='', $id=0){
		return [
			'request'		=> ['title'=>'原地址',	'type'=>'url',	'show_admin_column'=>true],
			'destination'	=> ['title'=>'目标地址',	'type'=>'url',	'show_admin_column'=>true]
		];
	}

	public static function get_list_table(){
		return [
			'plural'		=> 'redirects',
			'singular'		=> 'redirect',
			'model'			=> self::class,
			'per_page'		=> 50,
		];
	}

	public static function on_template_redirect(){
		if(!is_404()){
			return;
		}

		$request_url =  wpjam_get_current_page_url();

		if(strpos($request_url, 'feed/atom/') !== false){
			wp_redirect(str_replace('feed/atom/', '', $request_url), 301);
			exit;
		}

		if(strpos($request_url, 'comment-page-') !== false){
			wp_redirect(preg_replace('/comment-page-(.*)\//', '',  $request_url), 301);
			exit;
		}

		if(strpos($request_url, 'page/') !== false){
			wp_redirect(preg_replace('/page\/(.*)\//', '',  $request_url), 301);
			exit;
		}

		$redirects	= get_option('301-redirects') ?: [];

		foreach($redirects as $redirect){
			if($redirect['request'] == $request_url){
				wp_redirect($redirect['destination'], 301);
				exit;
			}
		}
	}
}

wpjam_add_menu_page('301-redirects', [
	'plugin_page'	=> 'wpjam-links',
	'title'			=> '301跳转',
	'function'		=> 'list',
	'list_table'	=> 'WPJAM_301_Redirect',
	'summary'		=> __FILE__,
	'hooks'			=> ['template_redirect', ['WPJAM_301_Redirect', 'on_template_redirect'], 99]
]);
