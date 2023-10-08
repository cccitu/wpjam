<?php
/*
Plugin Name: WPJAM BASIC
Plugin URI: https://blog.wpjam.com/project/wpjam-basic/
Description: WPJAM 常用的函数和接口，屏蔽所有 WordPress 不常用的功能。
Version: 6.3.1.1
Requires at least: 6.1
Tested up to: 6.3
Requires PHP: 7.2
Author: Denis
Author URI: http://blog.wpjam.com/
Update URI: http://blog.wpjam.com/project/wpjam-basic/
*/
define('WPJAM_BASIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPJAM_BASIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPJAM_BASIC_PLUGIN_FILE', __FILE__);

include __DIR__.'/includes/class-wpjam-register.php';	// 数据注册类
include __DIR__.'/includes/class-wpjam-model.php';		// Model 类
include __DIR__.'/includes/class-wpjam-db.php';			// DB 操作类
include __DIR__.'/includes/class-wpjam-platform.php';	// 平台路径类
include __DIR__.'/includes/class-wpjam-util.php';		// 常用工具类
include __DIR__.'/includes/class-wpjam-field.php';		// 字段解析类
include __DIR__.'/includes/class-wpjam-api.php';		// 路由接口类
include __DIR__.'/includes/class-wpjam-setting.php';	// 选项设置类
include __DIR__.'/includes/class-wpjam-post.php';		// 文章处理类
include __DIR__.'/includes/class-wpjam-term.php';		// 分类处理类
include __DIR__.'/includes/class-wpjam-user.php';		// 用户处理类

if(is_admin()){
	include __DIR__.'/includes/class-wpjam-admin.php';		// 后台页面处理类
	include __DIR__.'/includes/class-wpjam-list-table.php';	// 自定义后台列表类
	include __DIR__.'/includes/class-wpjam-builtin.php';	// 内置页面处理类
	include __DIR__.'/includes/class-wpjam-chart.php';		// 后台图表处理类
}

include __DIR__.'/public/wpjam-compat.php';		// 兼容代码
include __DIR__.'/public/wpjam-functions.php';	// 常用函数
include __DIR__.'/public/wpjam-utils.php';		// 工具函数
include __DIR__.'/public/wpjam-route.php';		// 路由接口

if(is_admin()){
	include __DIR__.'/public/wpjam-admin.php';	// 后台函数
}


do_action('wpjam_loaded');