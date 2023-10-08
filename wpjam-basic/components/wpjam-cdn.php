<?php
/*
Name: CDN 加速
URI: https://mp.weixin.qq.com/s/bie4JkmExgULgvEgx-AjUw
Description: CDN 加速使用云存储对博客的静态资源进行 CDN 加速。
Version: 2.0
*/
class WPJAM_CDN extends WPJAM_Option_Model{
	public static function get_sections(){
		$cdn_fields	= array_merge(WPJAM_CDN_Type::get_options_fields(['name'=>'cdn_name', 'title'=>'云存储']), [
			'host'		=> ['title'=>'CDN 域名',	'type'=>'url',		'show_if'=>self::show_if('', '!='),	'description'=>'设置为在CDN云存储绑定的域名。'],
			'disabled'	=> ['title'=>'使用本站',	'type'=>'checkbox',	'show_if'=>self::show_if(''),	'description'=>'如使用 CDN 之后切换回使用本站图片，请勾选该选项，并将原 CDN 域名填回「本地设置」的「额外域名」中。'],
			'image'		=> ['title'=>'图片处理',	'type'=>'checkbox',	'show_if'=>self::show_if(['aliyun_oss', 'volc_imagex', 'qcloud_cos', 'qiniu']),	'value'=>1,	'description'=>'开启云存储图片处理功能，使用云存储进行裁图、添加水印等操作。<br />&emsp;<strong>*</strong> 注意：开启之后，文章和媒体库中的所有图片都会镜像到云存储。'],
		]);

		foreach(['aliyun_oss', 'volc_imagex', 'qcloud_cos'] as $type){
			$cdn_fields[$type.'_guide']	= self::get_guide_field($type);
		}

		$local_fields	= [
			'local'	=> ['title'=>'本地域名',	'type'=>'fieldset',	'fields'=>[
				'local'		=> ['type'=>'url',		'description'=>'将该域名填入<strong>云存储的镜像源</strong>',	'value'=>home_url()],
				'no_http'	=> ['type'=>'checkbox',	'description'=>'将无<code>http://</code>或<code>https://</code>的静态资源也进行镜像处理'],
			]],
			'exts'	=> ['title'=>'扩展名',	'type'=>'fieldset',	'fields'=>[
				'img_exts'	=> ['type'=>'checkbox',	'description'=>'支持所有图片扩展名'],
				'exts'		=> ['type'=>'mu-text',	'button_text'=>'添加扩展名',	'sortable'=>false,	'direction'=>'row',	'description'=>'镜像到云存储的静态文件扩展名'],
			]],
			'dirs'	=> ['title'=>'目录',		'type'=>'mu-text',	'sortable'=>false,	'direction'=>'row',	'style'=>'width:120px;', 'value'=>['wp-content','wp-includes'],	'description'=>'镜像到云存储的静态文件所在目录'],
			'locals'	=> ['title'=>'额外域名',	'type'=>'mu-text',	'item_type'=>'url'],
		];

		$sections	= [
			'cdn'	=> ['title'=>'云存储设置',	'fields'=>$cdn_fields],
			'local'	=> ['title'=>'本地设置',		'fields'=>$local_fields],
		];

		if(is_network_admin()){
			unset($sections['local']['fields']['local']);
		}else{
			$remote_fields	= [];
			$remote_summary	= '';

			if(!wpjam_basic_get_setting('upload_external_images')){
				if(!is_multisite() && $GLOBALS['wp_rewrite']->using_mod_rewrite_permalinks() && extension_loaded('gd')){
					$remote_options	= [
						''	=>'关闭外部图片镜像到云存储',
						'1'	=>'自动将外部图片镜像到云存储（不推荐）'
					];

					$remote_summary	= '*自动将外部图片镜像到云存储需要博客支持固定链接和服务器支持GD库（不支持gif图片）';

					$remote_fields['remote']	= ['title'=>'外部图片',	'type'=>'select',	'options'=>$remote_options];
				}else{
					$remote_fields['external']	= ['title'=>'外部图片',	'type'=>'view',	'value'=>'请先到「文章设置」中开启「支持在文章列表页上传外部图片」'];
				}
			}else{
				$remote_fields['external']	= ['title'=>'外部图片',	'type'=>'view',	'value'=>'已在「文章设置」中开启「支持在文章列表页上传外部图片」'];
			}

			$remote_fields['exceptions']	= ['title'=>'例外',	'type'=>'textarea',	'class'=>'',	'description'=>'如果外部图片的链接中包含以上字符串或域名，就不会被保存并镜像到云存储。'];

			$wm_options = [
				'SouthEast'	=> '右下角',
				'SouthWest'	=> '左下角',
				'NorthEast'	=> '右上角',
				'NorthWest'	=> '左上角',
				'Center'	=> '正中间',
				'West'		=> '左中间',
				'East'		=> '右中间',
				'North'		=> '上中间',
				'South'		=> '下中间',
			];

			$wm_fields		= ['title'=>'水印设置',	'type'=>'fieldset',	'show_if'=>self::show_if('volc_imagex','!='),	'fields'=>[
				'view'		=> ['type'=>'view',		'title'=>'使用说明：',	'value'=>'请使用云存储域名下的图片，水印设置仅应用于文章内容中的图片'],
				'watermark'	=> ['type'=>'image',	'title'=>'水印图片：'],
				'dissolve'	=> ['type'=>'number',	'title'=>'透明度：',	'class'=>'small-text',	'description'=>'1-100，默认100（不透明）', 'min'=>0, 'max'=>100],
				'gravity'	=> ['type'=>'select',	'title'=>'水印位置：',	'options'=>$wm_options],
				'd_size'	=> ['type'=>'fields',	'title'=>'水印边距：',	'fields_type'=>'size',	'fields'=>[
					'width'		=> ['key'=>'dx',	'value'=>10],
					'height'	=> ['key'=>'dy',	'value'=>10],
				]],
				'wm_size'	=> ['type'=>'fields',	'title'=>'最小尺寸：',	'fields_type'=>'size',	'show_if'=>self::show_if(['aliyun_oss', 'qcloud_cos']),	'description'=>'小于该尺寸的图片都不会加上水印',	'fields'=>[
					'width'		=> ['key'=>'wm_width'],
					'height'	=> ['key'=>'wm_height'],
				]]	
			]];

			$max_width		= $GLOBALS['content_width'] ?? 0;

			$image_fields	= [
				'thumb_set'	=> ['title'=>'缩图设置',	'type'=>'fieldset',	'fields'=>[
					'no_subsizes'	=> ['type'=>'checkbox',	'value'=>1,	'description'=>'云存储有更强大的缩图功能，本地不用再生成各种尺寸的缩略图。'],
					'thumbnail'		=> ['type'=>'checkbox',	'value'=>1,	'description'=>'使用云存储缩图功能对文章内容中的图片进行最佳尺寸显示处理。'],
					'max_width'		=> ['type'=>'number',	'value'=>$max_width,	'before'=>'文章中图片最大宽度：',	'show_if'=>['key'=>'thumbnail', 'value'=>1],	'class'=>'small-text',	'after'=>'px。']
				]],
				'webp'		=> ['title'=>'WebP 格式',	'type'=>'checkbox',	'description'=>'将图片转换成 WebP 格式。',	'show_if'=>self::show_if(['volc_imagex','aliyun_oss',	'qcloud_cos'])],
				'image_set'	=> ['title'=>'格式质量',	'type'=>'fieldset',	'show_if'=>self::show_if('volc_imagex','!='),	'fields'=>[
					'interlace'		=> ['type'=>'checkbox',	'description'=>'JPEG格式图片渐进显示。'],
					'quality'		=> ['type'=>'number',	'before'=>'图片质量：',	'class'=>'small-text',	'mim'=>0,	'max'=>100]
				]],
				'wm_set'	=> $wm_fields,
				'volc_imagex_template'	=> ['title'=>'火山引擎图片处理模板',	'type'=>'text',	'show_if'=>self::show_if('volc_imagex')]
			];

			$sections	+= [
				'image'		=> ['title'=>'图片设置',	'fields'=>$image_fields,	'show_if'=>['key'=>'image', 'compare'=>'=', 'value'=>1]],
				'remote'	=> ['title'=>'外部图片',	'fields'=>$remote_fields,	'show_if'=>self::show_if('', '!='),	'summary'=>$remote_summary],
			];
		}

		return $sections;
	}

	public static function get_guide_field($type){
		$guides	= [
			'aliyun_oss'	=> '请点击这里注册和申请<strong><a href="http://wpjam.com/go/aliyun/" target="_blank">阿里云</a></strong>可获得代金券，点击这里查看<strong><a href="https://blog.wpjam.com/m/aliyun-oss-cdn/" target="_blank">阿里云OSS详细使用指南</a></strong>。',
			'qcloud_cos'	=> '请点击这里注册和申请<strong><a href="http://wpjam.com/go/qcloud/" target="_blank">腾讯云</a></strong>可获得优惠券，点击这里查看<strong><a href="https://blog.wpjam.com/m/qcloud-cos-cdn/" target="_blank">腾讯云COS详细使用指南</a></strong>。',
			'volc_imagex'	=> '使用邀请码 <strong>CLEMNL</strong> 注册和申请<strong><a href="https://wpjam.com/go/volc-imagex" target="_blank">火山引擎</a></strong>，可以领取每月免费额度（10GB流量和10GB存储等），<br />以及HTTPS 访问免费和回源流量免费，点击这里查看<strong><a href="http://blog.wpjam.com/m/volc-veimagex/" target="_blank">火山引擎 veImageX 详细使用指南</a></strong>。'
		];

		return ['title'=>'使用说明',	'type'=>'view',	'value'=>$guides[$type],	'show_if'=>self::show_if($type)];
	}

	public static function show_if($value, $compare=null){
		$compare	= $compare ?? (is_array($value) ? 'IN' : '=');

		return ['key'=>'cdn_name', 'compare'=>$compare, 'value'=>$value];
	}

	public static function get_menu_page(){
		return [
			'parent'	=> 'wpjam-basic',
			'function'	=> 'option',
			'position'	=> 2,
			'summary'	=> __FILE__,
		];
	}

	public static function parse_items($name, $delimiter='|'){
		$items	= self::get_setting($name);

		if($items){
			if(!is_array($items)){
				$items	= explode($delimiter, $items);

				if($name != 'exceptions'){
					self::update_setting($name, $items);
				}
			}

			return array_unique(array_filter(array_map('trim', $items)));
		}

		return [];
	}

	public static function scheme_replace($url){
		if(parse_url($url, PHP_URL_SCHEME) == 'http'){
			return str_replace('http://', 'https://', $url);
		}else{
			return str_replace('https://', 'http://', $url);
		}
	}

	public static function host_replace($html, $to_cdn=true){
		$locals		= self::get_setting('locals') ?: [];
		$locals[]	= self::scheme_replace(LOCAL_HOST);

		if($to_cdn){
			$locals[]	= self::scheme_replace(CDN_HOST);
			$locals[]	= LOCAL_HOST;
			$to_host	= CDN_HOST;
		}else{
			$to_host	= LOCAL_HOST;
		}

		$locals	= apply_filters('wpjam_cdn_local_hosts', $locals);
		$locals	= array_map('untrailingslashit', array_unique($locals));

		return str_replace($locals, $to_host, $html);
	}

	public static function html_replace($html){
		$html	= self::host_replace($html, false);
		$exts	= self::parse_items('exts');
		$dirs	= self::parse_items('dirs');

		if(is_login()){
			$exts	= array_diff($exts, ['js','css']);
		}

		if(self::get_setting('img_exts')){
			$types	= wp_get_ext_types();
			$exts	= array_unique(array_merge($exts, $types['image']));
		}

		if(!$exts){
			return $html;
		}

		$local_host	= preg_quote(LOCAL_HOST, '/');

		if($no_http = self::get_setting('no_http')){
			$local_host	.= '|'.preg_quote(str_replace(['http://', 'https://'], '//', LOCAL_HOST), '/');
		}

		$pattern	= '('.$local_host.')\/(';
		$replace	= CDN_HOST.'/$2';

		if($dirs){
			$dirs		= array_map(function($dir){ return preg_quote(trim($dir, '/'), '/'); }, $dirs);
			$pattern	.= '('.implode('|', $dirs).')\/';
		}

		$pattern	.= '[^\s\?\\\'\"\;\>\<]{1,}\.('.implode('|', $exts).')';
		$pattern	.= '[\"\\\'\)\s\]\?]{1})';

		return preg_replace('/'.$pattern.'/', $replace, $html);
	}

	public static function downsize($size, $meta, $img_url){
		$downsize	= [];

		if(is_array($meta) && isset($meta['width'], $meta['height'])){
			$ratio	= 2;
			$size	= wpjam_parse_size($size, $ratio);

			if($size['crop']){
				$width	= min($size['width'],	$meta['width']);
				$height	= min($size['height'],	$meta['height']);
			}else{
				list($width, $height)	= wp_constrain_dimensions($meta['width'], $meta['height'], $size['width'], $size['height']);
			}

			if($width < $meta['width'] || $height <  $meta['height']){
				$img_url	= wpjam_get_thumbnail($img_url, compact('width', 'height'));
				$downsize	= [$img_url, (int)($width/$ratio), (int)($height/$ratio), true];
			}else{
				$img_url	= wpjam_get_thumbnail($img_url);
				$downsize	= [$img_url, $width, $height, false];
			}
		}

		return $downsize;
	}

	public static function filter_html($html){
		if(empty(CDN_NAME) && self::get_setting('disabled')){
			return self::host_replace($html, false);
		}else{
			if(self::get_setting('exts')){
				return self::html_replace($html);
			}
		}

		return $html;
	}

	public static function filter_content_img_tag($img_tag, $context, $attachment_id){
		if($context != 'the_content'){
			return $img_tag;
		}

		$proc	= wpjam_html_tag_processor($img_tag);
		$src	= $proc ? $proc->get_attribute('src') : '';
	
		if(!$src || wpjam_is_external_url($src)){
			return $img_tag;
		}

		$name	= $proc->get_attribute('data-size');
		$size	= [
			'width'		=> $proc->get_attribute('width') ?: 0,
			'height'	=> $proc->get_attribute('height') ?: 0,
			'content'	=> true
		];

		$max	= self::get_setting('max_width', ($GLOBALS['content_width'] ?? 0));
		$max	= (int)apply_filters('wpjam_content_image_width', $max);

		$meta	= $attachment_id ? wp_get_attachment_metadata($attachment_id) : null;
		$meta	= ($meta && is_array($meta)) ? $meta : null;

		if($meta && !$size['width'] && !$size['width']){
			if($name && $name != 'full' && isset($meta['sizes'][$name])){
				$size['width']	= $meta['sizes'][$name]['width'];
				$size['height']	= $meta['sizes'][$name]['height'];
			}else{
				$size['width']	= $meta['width'];
				$size['height']	= $meta['height'];
			}

			if($max && $size['width'] > $max){
				if($size['height']){
					$size['height']	= (int)($max/$size['width']*$size['height']);
				}

				$size['width']	= $max;
			}

			$proc->set_attribute('width', $size['width']);
			$proc->set_attribute('height', $size['height']);
		}else{
			if($max){
				if($size['width'] > $max){
					if($size['height']){
						$size['height']	= (int)(($max/$size['width'])*$size['height']);

						$proc->set_attribute('height', $size['height']);
					}

					$proc->set_attribute('width', $max);

					$size['width']	= $max;
				}elseif($size['width'] == 0){
					if($size['height'] == 0){
						$size['width']	= $max;
					}
				}
			}
		}

		if($meta && is_numeric($size['width']) && is_numeric($size['height'])){
			if($size['width']*2 >= $meta['width'] && $size['height']*2 >= $meta['height']){
				unset($size['width'], $size['height']);
			}elseif($size['width']*2 >= $meta['width'] && !$size['height']){
				unset($size['width']);
			}elseif($size['height']*2 >= $meta['height'] && !$size['width']){
				unset($size['height']);
			}
		}

		$size	= wpjam_parse_size($size, 2);
		$src	= wpjam_get_thumbnail($src, $size);

		$proc->set_attribute('src', $src);

		return $proc->get_updated_html();
	}

	public static function filter_block($block_content, $parsed_block){
		if($parsed_block['blockName'] == 'core/image'){
			$size	= $parsed_block['attrs']['sizeSlug'] ?? '';

			if($size && $size != 'full'){
				$proc	= wpjam_html_tag_processor($block_content, 'img');

				if($proc){
					$proc->set_attribute('data-size', $size);

					return (string)$proc;
				}
			}
		}

		return $block_content;
	}

	public static function filter_content($content){
		if(doing_filter('get_the_excerpt') || false === strpos($content, '<img')){
			return $content;
		}

		if(!wpjam_is_json_request()){
			$content	= self::host_replace($content, false);
		}

		if(self::get_setting('no_subsizes', 1)){
			add_filter('wp_img_tag_add_srcset_and_sizes_attr', '__return_false');
		}

		add_filter('wp_content_img_tag', [self::class, 'filter_content_img_tag'], 1, 3);

		return $content;
	}

	public static function filter_thumbnail($url){
		return self::host_replace($url);
	}

	public static function filter_is_external_url($status, $url, $scene){
		if($status){
			if($scene == 'fetch'){
				$exceptions	= self::parse_items('exceptions', "\n");

				foreach($exceptions as $exception){
					if(strpos($url, trim($exception)) !== false){
						return false;
					}
				}
			}

			return !wpjam_is_cdn_url($url);
		}

		return $status;
	}

	public static function filter_intermediate_image_sizes_advanced($sizes){
		return isset($sizes['full']) ? ['full'=>$sizes['full']] : [];
	}

	public static function filter_embed_thumbnail_image_size($image_size){
		return '160x120';
	}

	public static function filter_get_attachment_metadata($data, $id){
		if(wp_attachment_is_image($id) && is_array($data) && empty($data['sizes'])){
			$img_url	= wp_get_attachment_url($id);

			$data['sizes']	= [];

			foreach(wp_get_registered_image_subsizes() as $name => $size){
				$downsize	= self::downsize($size, $data, $img_url);

				if($downsize && !empty($downsize[3])){
					$file_arr	= explode('?', $downsize[0]);

					$data['sizes'][$name]	= [
						'file'			=> wp_basename($file_arr[0]). (isset($file_arr[1]) ? '?'.$file_arr[1] : ''),
						'url'			=> $downsize[0],
						'width'			=> $downsize[1],
						'height'		=> $downsize[2],
						'orientation'	=> $downsize[2] > $downsize[1] ? 'portrait' : 'landscape',
					];
				}
			}
		}

		return $data;
	}

	public static function filter_attachment_url($url, $id){
		if(wp_attachment_is_image($id)){
			return self::host_replace($url);
		}else{
			$exts	= self::parse_items('exts');

			if($exts && preg_match('/\.('.implode('|', $exts).')$/i', $url)){
				return self::host_replace($url);
			}
		}

		return $url;
	}

	public static function filter_mime_type_icon($icon){
		return self::host_replace($icon);
	}

	public static function filter_image_downsize($downsize, $id, $size){
		if(wp_attachment_is_image($id)){
			$img_url	= wp_get_attachment_url($id);
			$meta		= wp_get_attachment_metadata($id);
			$downsize	= self::downsize($size, $meta, $img_url);
		}

		return $downsize;
	}

	public static function filter_wp_resource_hints($urls, $relation_type){
		return $relation_type == 'dns-prefetch' ? $urls+[CDN_HOST] : $urls;
	}

	public static function on_plugins_loaded(){
		$local	= self::get_setting('local');

		define('CDN_NAME',		self::get_setting('cdn_name'));
		define('CDN_HOST',		untrailingslashit(self::get_setting('host') ?: site_url()));
		define('LOCAL_HOST',	untrailingslashit($local ? set_url_scheme($local): site_url()));

		if(CDN_NAME){
			do_action('wpjam_cdn_loaded');

			if(!is_admin()){
				$filter	= wpjam_is_json_request() ? 'the_content' : 'wpjam_html';

				add_filter($filter,	[self::class, 'filter_html'], 5);
			}

			add_filter('wpjam_is_external_url',	[self::class, 'filter_is_external_url'], 10, 3);
			add_filter('wp_resource_hints',		[self::class, 'filter_wp_resource_hints'], 10, 2);

			if(self::get_setting('image', 1)){
				WPJAM_CDN_Type::load(CDN_NAME);

				if(self::get_setting('no_subsizes', 1)){
					add_filter('intermediate_image_sizes_advanced',	[self::class, 'filter_intermediate_image_sizes_advanced']);
					add_filter('embed_thumbnail_image_size',		[self::class, 'filter_embed_thumbnail_image_size']);
					add_filter('wp_get_attachment_metadata',		[self::class, 'filter_get_attachment_metadata'], 10, 2);
					add_filter('wp_calculate_image_srcset_meta',	'__return_empty_array');
				}

				if(self::get_setting('thumbnail', 1)){
					add_filter('render_block',	[self::class, 'filter_block'], 5, 2);
					add_filter('the_content',	[self::class, 'filter_content'], 5);
				}

				add_filter('wpjam_thumbnail',		[self::class, 'filter_thumbnail'], 1);
				add_filter('wp_get_attachment_url',	[self::class, 'filter_attachment_url'], 10, 2);
				add_filter('wp_mime_type_icon',		[self::class, 'filter_mime_type_icon']);
				// add_filter('upload_dir',			[self::class, 'filter_upload_dir']);
				add_filter('image_downsize',		[self::class, 'filter_image_downsize'], 10 ,3);
			}

			if(!wpjam_basic_get_setting('upload_external_images')){
				if(self::get_setting('remote') === 'download'){
					if(is_admin()){
						wpjam_basic_update_setting('upload_external_images', 1);
						self::update_setting('remote', 0);
					}
				}elseif(self::get_setting('remote')){
					if(!is_multisite()){
						include dirname(__DIR__).'/cdn/remote.php';
					}
				}
			}
		}else{
			if(self::get_setting('disabled')){
				if(!is_admin() && !wpjam_is_json_request()){
					add_filter('wpjam_html',	[self::class, 'filter_html'], 9);
				}

				add_filter('the_content',		[self::class, 'filter_html'], 5);
				add_filter('wpjam_thumbnail',	[self::class, 'filter_html'], 9);
			}
		}
	}
}

class WPJAM_CDN_Type extends WPJAM_Register{
	public static function load($name){
		$object	= self::get($name);
		$file	= $object ? ($object->file ?: dirname(__DIR__).'/cdn/'.$name.'.php') : '';

		if($file && file_exists($file)){
			$callback	= include $file;

			if($callback !== 1 && is_callable($callback)){
				add_filter('wpjam_thumbnail', $callback, 10, 2);
			}
		}
	}

	public static function get_config($key){
		if($key == 'defaults'){
			return [
				'aliyun_oss'	=> ['title'=>'阿里云OSS'],
				'qcloud_cos'	=> ['title'=>'腾讯云COS'],
				'volc_imagex'	=> ['title'=>'火山引擎veImageX'],
				'ucloud'		=> ['title'=>'UCloud'],
				'qiniu'			=> ['title'=>'七牛云存储'],
			];
		}
	}
}

function wpjam_register_cdn($name, $args){
	return WPJAM_CDN_Type::register($name, $args);
}

function wpjam_unregister_cdn($name){
	WPJAM_CDN_Type::unregister($name);
}

function wpjam_cdn_get_setting($name, $default=null){
	$value	= WPJAM_CDN::get_setting($name, $default);

	if($name == 'watermark' && $value){
		return (explode('?', $value))[0];
	}

	return $value;
}

function wpjam_cdn_host_replace($html, $to_cdn=true){
	return WPJAM_CDN::host_replace($html, $to_cdn);
}

function wpjam_local_host_replace($html){
	return str_replace(CDN_HOST, LOCAL_HOST, $html);
}

function wpjam_is_cdn_url($url){
	$status	= strpos($url, CDN_HOST) !== false;

	return apply_filters('wpjam_is_cdn_url', $status, $url);
}

function wpjam_restore_attachment_file($id){
	$file = get_attached_file($id, true);

	if($file && !file_exists($file)){
		$dir	= dirname($file);

		if(!is_dir($dir)){
			mkdir($dir, 0777, true);
		}

		$image	= wpjam_cdn_host_replace(wp_get_attachment_url($id));
		$result	= wpjam_remote_request($image, ['stream'=>true, 'filename'=>$file]);

		if(is_wp_error($result)){
			return $result;
		}
	}

	return true;
}

wpjam_register_option('wpjam-cdn',	[
	'title'			=> 'CDN加速',
	'model'			=> 'WPJAM_CDN',
	'hooks'			=> ['plugins_loaded', ['WPJAM_CDN', 'on_plugins_loaded'], 99],
	'site_default'	=> true,
]);