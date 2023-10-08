<?php
class WPJAM_Attr extends WPJAM_Args{
	public function __set($key, $value){
		if($key == 'attr'){
			$this->args	= $value;
		}else{
			parent::__set($key, $value);
		}
	}

	public function __toString(){
		return $this->render();
	}

	public function jsonSerialize(){
		return $this->render();
	}

	public function render(){
		$args	= $this->get_args();
		$data	= array_wrap(array_pull($args, 'data'), []);
		$attr	= ['data'=>$data];
		$class	= array_wrap(array_pull($args, 'class'));
		$class	= array_filter($class);
		$style	= (array)array_pull($args, 'style', []);

		foreach(['readonly', 'disabled'] as $key){
			if($this->$key){
				$class[]	= $key;
			}
		}

		if($class){
			$attr['class']	= implode(' ', array_unique($class));
		}

		if($style){
			foreach($style as $k => &$v){
				$v	= $v ? ((is_numeric($k) ? '' : $k.':').rtrim($v, ';')) : '';
			}
			
			$attr['style']	= implode(' ', array_unique(array_filter($style)));
		}

		if(isset($this->value)){
			$attr['value']	= $this->value;
		}

		foreach($args as $key => $value){
			if(str_ends_with($key, '_callback') || str_starts_with($key, '_') || is_blank($value)){
				continue;
			}

			if(str_starts_with($key, 'data-')){
				$key	= wpjam_remove_prefix($key, 'data-');

				$attr['data'][$key]	= $value;
			}elseif($this->is_bool($key)){
				$attr[$key]	= $key;
			}elseif(is_scalar($value)){
				$attr[$key]	= $value;
			}else{
				trigger_error($key.' '.var_export($value, true).var_export($this, true));
			}
		}

		$items	= [];

		foreach($attr as $key => $value){
			if($key == 'data'){
				$items[]	= self::create($value, 'data');
			}else{
				$items[]	= $key.'="'.esc_attr($value).'"';
			}
		}

		return $items ? ' '.implode(' ', array_filter($items)) : '';
	}

	public static function parse_attr($attr){
		$parsed	= [];

		foreach($attr as $key => $value){
			if(is_numeric($key)){
				$key	= $value = strtolower(trim($value));

				if(!self::is_bool($key)){
					continue;
				}
			}else{
				$key	= strtolower(trim($key));

				if(self::is_bool($key)){
					if(!$value){
						continue;
					}

					$value	= $key;
				}
			}

			$parsed[$key]	= $value;
		}

		return $parsed;
	}

	public static function create($attr, $type=''){
		if($type == 'data'){
			return new WPJAM_Data_Attr($attr);
		}

		return new WPJAM_Attr($attr);
	}

	public static function is_bool($attr){
		return in_array($attr, ['allowfullscreen', 'allowpaymentrequest', 'allowusermedia', 'async', 'autofocus', 'autoplay', 'checked', 'controls', 'default', 'defer', 'disabled', 'download', 'formnovalidate', 'hidden', 'ismap', 'itemscope', 'loop', 'multiple', 'muted', 'nomodule', 'novalidate', 'open', 'playsinline', 'readonly', 'required', 'reversed', 'selected', 'typemustmatch']);
	}
}

class WPJAM_Data_Attr extends WPJAM_Attr{
	public function render(){
		$items	= [];

		foreach($this as $key => $value){
			if(isset($value) && $value !== false){
				if(is_scalar($value)){
					$value	= esc_attr($value);
				}else{
					if($key == 'data'){
						$value	= http_build_query($value);
					}else{
						if($key == 'show_if'){
							$value	= array_except($value, '_parsed');
						}

						$value	= wpjam_json_encode($value);
					}
				}

				$items[]	= 'data-'.$key.'=\''.$value.'\'';
			}
		}

		return $items ? implode(' ', $items) : '';
	}
}

class WPJAM_Tag extends WPJAM_Attr{
	protected $tag		= '';
	protected $text		= '';
	protected $_before	= [];
	protected $_after	= [];
	protected $_prepend	= [];
	protected $_append	= [];

	public function __construct($tag='', $attr=[], $text=''){
		$this->tag	= $tag;
		$this->attr	= ($attr && wp_is_numeric_array($attr)) ? ['class'=>$attr] : $attr;

		if($text && is_array($text)){
			$this->text(...$text);
		}else{
			$this->text	= $text ?: '';
		}
	}

	public function __call($method, $args){
		if(in_array($method, ['text', 'tag', 'before', 'after', 'prepend', 'append'])){
			if($args){
				if(count($args) > 1){
					if(!is_array($args[1])){
						$attr	= $args[2] ?? [];

						$args[0]	= new self($args[1], $attr, $args[0]);
					}else{
						$args[0]	= new self(...$args);
					}
				}else{
					if(!$args[0] && !in_array($method, ['text', 'tag'])){
						return $this;
					}
				}

				if($method == 'text'){
					$this->text	= $args[0];
				}elseif($method == 'tag'){
					$this->tag	= $args[0];
				}elseif(in_array($method, ['before', 'prepend'])){
					array_unshift($this->{'_'.$method}, $args[0]);
				}elseif(in_array($method, ['after', 'append'])){
					array_push($this->{'_'.$method}, $args[0]);
				}

				return $this;
			}else{
				if($method == 'text'){
					return $this->text;
				}elseif($method == 'tag'){
					return $this->tag;
				}else{
					return $this->{'_'.$method};
				}
			}
		}elseif(in_array($method, ['insert_before', 'insert_after', 'append_to', 'prepend_to'])){
			$method	= str_replace(['insert_', '_to'], '', $method);

			return call_user_func([$args[0], $method], $this);
		}elseif(in_array($method, ['render_before', 'render_after', 'render_append', 'render_prepend'])){
			$key	= str_replace('render', '', $method);

			return $this->$key ? implode('', $this->$key) : '';
		}elseif($method == 'get_children'){
			$children	= $this->_prepend;

			if($this->text && $this->text instanceof self){
				$children[]	= $this->text;
			}

			return array_merge($children, $this->_append);
		}
	}

	public function render(){
		if($this->tag == 'a' && is_null($this->href)){
			$this->href	= 'javascript:;';
		}

		$single	= $this->is_single($this->tag);
		$result	= $this->render_before();

		if($this->tag){
			$result	.= '<'.$this->tag.parent::render();
			$result	.= $single ? ' />' : '>';
		}

		if(!$single){
			$result	.= $this->render_prepend();
			$result	.= $this->text;
			$result	.= $this->render_append();

			if($this->tag){
				$result	.= '</'.$this->tag.'>';
			}
		}

		return $result	.= $this->render_after();
	}

	public function wrap($tag, $attr=[]){
		if($tag){
			$this->text	= $this->render();
			$this->tag	= $tag;

			if($attr && (!is_array($attr) || wp_is_numeric_array($attr))){
				$this->attr	= ['class'=>$attr];
			}else{
				$this->attr	= $attr;
			}

			return $this->flush();
		}

		return $this;
	}

	public function flush(){
		$this->_before	= $this->_after = $this->_prepend = $this->_append = [];

		return $this;
	}

	public static function is_single($tag){
		return in_array($tag, ['area', 'base', 'basefont', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'isindex', 'link', 'meta', 'param', 'source', 'track', 'wbr']);
	}
}

class WPJAM_Array extends WPJAM_Args{
	public function __construct($array=[]){
		parent::__construct(array_wrap($array, []));
	}

	public function exists($key){
		return $this->get($key) !== null;
	}

	public function get($key, $default=null){
		return $this->get_arg($key, $default);
	}

	public function set($key, $value){
		return $this->update_arg($key, $value);
	}

	public function add(...$args){
		if(count($args) >= 2){
			if(!$this->exists($args[0])){
				return $this->set(...$args);
			}
		}elseif($args){
			$this->args[]	= $value;
		}

		return $this;
	}

	public function delete($key){
		return $this->delete_arg($key);
	}

	public function merge($data){
		$this->args	= merge_deep($this->get_args(), $data);

		return $this;
	}

	public function filter($callback){
		$this->args	= filter_deep($this->get_args(), $callback);

		return $this;
	}
}

class WPJAM_Compare extends WPJAM_Singleton{
	public function parse($args){
		$this->args	= $args;

		if(!$this->_parsed && (isset($this->compare) || !isset($this->query_arg))){
			$this->_parsed	= true;
			$this->value	= $this->value ?? '';
			$this->compare	= $this->compare ? strtoupper($this->compare) : (is_array($this->value) ? 'IN' : '=');

			if(in_array($this->compare, ['IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN'])){
				$this->value	= wp_parse_list($this->value);

				if(count($this->value) == 1){
					$this->value	= strval(current($this->value));
					$this->compare	= in_array($this->compare, ['IN', 'BETWEEN']) ? '=' : '!=';
				}else{
					$this->value	= array_map('strval', $this->value);	// JS Array.indexof is strict
				}
			}else{
				if(is_string($this->value)){
					$this->value	= trim($this->value);
				}
			}
		}

		return $this;
	}

	public function compare($item){
		if($this->key){
			if(is_object($item)){
				$value	= $item->{$this->key} ?? null;
			}elseif(is_array($item)){
				$value	= $item[$this->key] ?? null;
			}else{
				$value	= null;
			}

			if(is_null($value)){
				return null;	// 没有比较
			}
		}else{
			$value	= $item;
		}

		if(is_array($value)){
			if($this->compare == '='){
				return in_array($this->value, $value);
			}elseif($this->compare == '!='){
				return !in_array($this->value, $value);
			}elseif($this->compare == 'IN'){
				return array_intersect($value, $this->value) == $this->value;
			}elseif($this->compare == 'NOT IN'){
				return array_intersect($value, $this->value) == [];
			}
		}else{
			if($this->compare == '='){
				return $value == $this->value;
			}elseif($this->compare == '!='){
				return $value != $this->value;
			}elseif($this->compare == '>'){
				return $value > $this->value;
			}elseif($this->compare == '>='){
				return $value >= $this->value;
			}elseif($this->compare == '<'){
				return $value < $this->value;
			}elseif($this->compare == '<='){
				return $value <= $this->value;
			}elseif($this->compare == 'IN'){
				return in_array($value, $this->value);
			}elseif($this->compare == 'NOT IN'){
				return !in_array($value, $this->value);
			}elseif($this->compare == 'BETWEEN'){
				return $value > $this->value[0] && $value < $this->value[1];
			}elseif($this->compare == 'NOT BETWEEN'){
				return $value < $this->value[0] && $value > $this->value[1];
			}
		}

		return false;
	}
}

class WPJAM_User_Agent extends WPJAM_Args{
	private $user_agent;

	public function __construct($user_agent=null){
		$this->user_agent	= $user_agent ?? wpjam_get_user_agent();
	}

	public function get_parsed(){
		if(!$this->args){
			$this->parse_os();
			$this->parse_browser();
			$this->parse_app();
		}

		$parsed	= [];

		foreach(['os', 'device', 'app', 'browser', 'os_version', 'browser_version', 'app_version'] as $name){
			$parsed[$name]	= $this->$name;
		}

		return $parsed;
	}

	public function parse_os(){
		$this->os			= 'unknown';
		$this->device		= '';
		$this->os_version	= 0;

		foreach([
			['iPhone',			'iOS',	'iPhone'],
			['iPad',			'iOS',	'iPad'],
			['iPod',			'iOS',	'iPod'],
			['Android',			'Android'],
			['Windows NT',		'Windows'],
			['Macintosh',		'Macintosh'],
			['Windows Phone',	'Windows Phone'],
			['BlackBerry',		'BlackBerry'],
			['BB10',			'BlackBerry'],
			['Symbian',			'Symbian'],
		] as $rule){
			if(stripos($this->user_agent, $rule[0])){
				$this->os	= $rule[1];

				if(isset($rule[2])){
					$this->device	= $rule[2];
				}

				break;
			}
		}

		if($this->os == 'iOS'){
			if(preg_match('/OS (.*?) like Mac OS X[\)]{1}/i', $this->user_agent, $matches)){
				$this->os_version	= (float)(trim(str_replace('_', '.', $matches[1])));
			}
		}elseif($this->os == 'Android'){
			if(preg_match('/Android ([0-9\.]{1,}?); (.*?) Build\/(.*?)[\)\s;]{1}/i', $this->user_agent, $matches)){
				if(!empty($matches[1]) && !empty($matches[2])){
					$this->os_version	= trim($matches[1]);

					if(strpos($matches[2],';')!==false){
						$this->device	= substr($matches[2], strpos($matches[2],';')+1, strlen($matches[2])-strpos($matches[2],';'));
					}else{
						$this->device	= $matches[2];
					}

					$this->device	= trim($this->device);
					// $build	= trim($matches[3]);
				}
			}
		}
	}

	public function parse_browser(){
		$this->browser 			= '';
		$this->browser_version	= 0;

		foreach([
			['lynx',	'/lynx\/([\d\.]+)/i'],
			['safari',	'/version\/([\d\.]+).*safari/i'],
			['edge',	'/edge\/([\d\.]+)/i'],
			['chrome',	'/chrome\/([\d\.]+)/i'],
			['firefox',	'/firefox\/([\d\.]+)/i'],
			['opera', 	'/(?:opera|opr).([\d\.]+)/i'],
			['ie',		'/msie ([\d\.]+)/i'],
			['ie',		'/rv:([\d.]+) like Gecko/i'],
			// ['Gecko',	'gecko'],

		] as $rule){
			if(preg_match($rule[1], $this->user_agent, $matches)){
				$this->browser 	= $rule[0];
				$this->browser_version	= (float)(trim($matches[1]));

				break;
			}
		}
	}

	public function parse_app(){
		$referer	= $_SERVER['HTTP_REFERER'] ?? '';
		$this->app 		= '';
		$this->app_version	= 0;

		if(strpos($this->user_agent, 'MicroMessenger') !== false){
			if(strpos($referer, 'https://servicewechat.com') !== false){
				$this->app	= 'weapp';
			}else{
				$this->app	= 'weixin';
			}

			if(preg_match('/MicroMessenger\/(.*?)\s/', $this->user_agent, $matches)){
				$this->app_version = (float)$matches[1];
			}

			// if(preg_match('/NetType\/(.*?)\s/', $this->user_agent, $matches)){
			// 	$net_type = $matches[1];
			// }
		}elseif(strpos($this->user_agent, 'ToutiaoMicroApp') !== false 
			|| strpos($referer, 'https://tmaservice.developer.toutiao.com') !== false
		){
			$this->app	= 'bytedance';
		}
	}
}

class WPJAM_File{
	protected $value;
	protected $type;

	public function __construct($value='', $type=''){
		$this->value	= $value;
		$this->type		= $type ?: (is_numeric($value) ? 'id' : 'url');
	}

	public function __get($key){
		if($key == $this->type){
			return $this->value;
		}elseif($this->type == 'id'){
			return wpjam_get_attachment_value($this->value, $key);
		}elseif(in_array($this->type, ['url', 'file', 'path'])){
			return self::convert($this->value, $this->type, $key);
		}
	}

	public function __isset($key){
		return $this->$key !== null;
	}

	public static function convert($value, $from='path', $to='file'){
		$dir	= wp_get_upload_dir();

		if($from == 'path'){
			$path	= $value;
		}else{
			if($from == 'url'){
				$value	= parse_url($value, PHP_URL_PATH);
				$base	= parse_url($dir['baseurl'], PHP_URL_PATH);
			}elseif($from == 'file'){
				$base	= $dir['basedir'];
			}

			if(!str_starts_with($value, $base)){
				return null;
			}

			$path	= wpjam_remove_prefix($value, $base);
		}

		if($to == 'path'){
			return $path;
		}elseif($to == 'file'){
			return $dir['basedir'].$path;
		}elseif($to == 'url'){
			return $dir['baseurl'].$path;
		}elseif($to == 'size'){
			$file	= $dir['basedir'].$path;
			$size	= file_exists($file) ? wp_getimagesize($file) : [];

			if($size){
				return ['width'=>$size[0], 'height'=>$size[1]];
			}
		}

		$id		= self::get_id_by_meta($path);

		return $id ? wpjam_get_attachment_value($id, $to) : null;
	}

	public static function get_id_by_meta($value, $key='_wp_attached_file'){
		if($key == '_wp_attached_file'){
			$value	= ltrim($value, '/');
		}

		$meta	= wpjam_get_by_meta('post', $key, $value);

		if($meta){
			$id	= current($meta)['post_id'];

			if(get_post_type($id) == 'attachment'){
				return $id;
			}
		}

		return '';
	}

	public static function add_to_media($file, $post_id=0){
		if(is_array($file)){
			$upload	= $file;
			$file	= $upload['file'] ?? '';
			$url	= $upload['url'] ?? '';
			$type	= $upload['type'] ?? '';
		}else{
			$url	= $type = '';
		}

		if(!$file){
			return;
		}

		$path	= self::convert($file, 'file', 'path');
		$id		= self::get_id_by_meta($path);

		if($id){
			return $id;
		}

		$url	= $url ?: self::convert($file, 'file', 'url');
		$type	= $type ?: mime_content_type($file);

		if(!$url){
			return;
		}

		require_once ABSPATH.'wp-admin/includes/image.php';

		$title		= preg_replace('/\.[^.]+$/', '', wp_basename($file));
		$content	= '';
		$image_meta	= wp_read_image_metadata($file);

		if($image_meta ) {
			if(trim($image_meta['title']) && ! is_numeric(sanitize_title($image_meta['title']))){
				$title	= $image_meta['title'];
			}

			if(trim($image_meta['caption'])){
				$content	= $image_meta['caption'];
			}
		}

		$id	= wp_insert_attachment([
			'post_title'		=> $title,
			'post_content'		=> $content,
			'post_parent'		=> $post_id,
			'post_mime_type'	=> $type,
			'guid'				=> $url,
		], $file, $post_id, true);

		if(!is_wp_error($id)){
			wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $file));
		}

		return $id;
	}
}

class WPJAM_Image extends WPJAM_File{
	public function is_valid(){
		if($this->type == 'url'){
			$ext_types	= wp_get_ext_types();
			$img_exts	= $ext_types['image'];
			$img_parts	= explode('?', $this->value);
			$img_url	= wpjam_remove_postfix($img_parts[0], '#');

			return preg_match('/\.('.implode('|', $img_exts).')$/i', $img_url);
		}elseif($this->type == 'file'){
			return !empty($this->size);
		}elseif($this->type == 'id'){
			return wp_attachment_is_image($this->value);
		}
	}

	public function get_size(){
		$size	= $this->size ?: [];
		$size	= apply_filters('wpjam_image_size', $size, $this->value, $this->type);

		if($size){
			$size = array_map('intval', $size);

			$size['orientation']	= $size['height'] > $size['width'] ? 'portrait' : 'landscape';
		}

		return $size;
	}

	public function get_thumbnail(...$args){
		if($this->url){
			$img_url	= wpjam_zh_urlencode($this->url);	// 中文名
			$img_url	= remove_query_arg(['orientation', 'width', 'height'], $img_url);

			return apply_filters('wpjam_thumbnail', $img_url, self::parse_thumbnail_args(...$args));
		}

		return '';
	}

	public function parse_query(){
		$query	= parse_url($this->url, PHP_URL_QUERY);
		$query	= wp_parse_args($query);

		foreach(['width', 'height'] as $key){
			if(isset($query[$key])){
				$query[$key]	= (int)$query[$key];
			}
		}

		return $query;
	}

	public static function parse_thumbnail_args(...$args){
		$args_num	= count($args);

		if($args_num == 0){
			// 1. 无参数
			return [];
		}elseif($args_num == 1){
			// 2. ['width'=>100, 'height'=>100]	// 这个为最标准版本
			// 3. [100,100]
			// 4. 100x100
			// 5. 100

			return self::parse_size($args[0]);
		}else{
			if(is_numeric($args[0])){
				// 6. 100, 100, $crop=1

				return [
					'width'		=> $args[0] ?? 0,
					'height'	=> $args[1] ?? 0,
					'crop'		=> $args[2] ?? 1
				];
			}else{
				// 7.【100,100], $crop=1

				return array_merge(self::parse_size($args[0]), ['crop'=>$args[1] ?? 1]);
			}
		}
	}

	public static function parse_size($size, $ratio=1){
		if(is_array($size)){
			if(!wp_is_numeric_array($size)){
				$size['width']	= !empty($size['width']) ? ((int)$size['width'])*$ratio : 0;
				$size['height']	= !empty($size['height']) ? ((int)$size['height'])*$ratio : 0;
				$size['crop']	= $size['crop'] ?? ($size['width'] && $size['height']);

				return $size;
			}else{
				$width	= $size[0] ?? 0;
				$height	= $size[1] ?? 0;
			}
		}else{
			$size	= str_replace(['*','X'], 'x', $size);

			if(strpos($size, 'x') !== false){
				$size	= explode('x', $size);
				$width	= $size[0];
				$height	= $size[1];
				$crop	= true;
			}elseif(is_numeric($size)){
				$width	= $size;
				$height	= 0;
			}elseif($size == 'thumb' || $size == 'thumbnail'){
				$width	= get_option('thumbnail_size_w') ?: 100;
				$height = get_option('thumbnail_size_h') ?: 100;
				$crop	= get_option('thumbnail_crop');
			}elseif($size == 'medium'){
				$width	= get_option('medium_size_w') ?: 300;
				$height	= get_option('medium_size_h') ?: 300;
				$crop	= false;
			}else{
				if($size == 'medium_large'){
					$width	= get_option('medium_large_size_w');
					$height	= get_option('medium_large_size_h');
					$crop	= false;
				}elseif($size == 'large'){
					$width	= get_option('large_size_w') ?: 1024;
					$height	= get_option('large_size_h') ?: 1024;
					$crop	= false;
				}else{
					$_sizes = wp_get_additional_image_sizes();

					if(isset($_sizes[$size])){
						$width	= $_sizes[$size]['width'];
						$height	= $_sizes[$size]['height'];
						$crop	= $_sizes[$size]['crop'];
					}else{
						$width	= $height = 0;
					}
				}

				if($width && !empty($GLOBALS['content_width'])){
					$max_width	= $GLOBALS['content_width'] * $ratio;
					$width		= min($max_width, $width);
				}
			}
		}

		return [
			'crop'		=> $crop ?? ($width && $height),
			'width'		=> (int)$width * $ratio,
			'height'	=> (int)$height * $ratio
		];
	}

	public static function constrain_size($size, $max_width, $max_height){
		$size	= self::parse_size($size);

		if($size['width'] && $size['height']){
			list($size['width'], $size['height'])	= wp_constrain_dimensions($size['width'], $size['height'], $max_width, $max_height);
		}elseif($size['width']){
			$size['width']	= $size['width'] < $max_width ? $size['width'] : $max_width;
		}else{
			$size['height']	= $size['height'] < $max_height ? $size['height'] : $max_height;
		}

		return $size;
	}
}

class WPJAM_Bit{
	protected $value	= 0;

	public function __construct($value=0){
		$this->value	= (int)$value;
	}

	public function __get($name){
		return $name == 'value' ? $this->value : null;
	}

	public function has($bit){
		$bit	= (int)$bit;

		return ($this->value & $bit) == $bit;
	}

	public function add($bit){
		$this->value = $this->value | (int)$bit;

		return $this;
	}

	public function remove($bit){
		$this->value = $this->value & (~(int)$bit);

		return $this;
	}
}

class WPJAM_Crypt extends WPJAM_Args{
	public function __construct($args=[]){
		$this->args	= wp_parse_args($args, [
			'method'		=> 'aes-256-cbc',
			'key'			=> '',
			'iv'			=> '',
			'options'		=> OPENSSL_ZERO_PADDING,
			'block_size'	=> 32,	// 注意 PHP 默认 aes cbc 算法的 block size 都是 16 位
		]);
	}

	public function encrypt($text){
		if($this->options == OPENSSL_ZERO_PADDING && $this->block_size){
			$text	= $this->pkcs7_pad($text, $this->block_size);	//使用自定义的填充方式对明文进行补位填充
		}

		return openssl_encrypt($text, $this->method, $this->key, $this->options, $this->iv);
	}

	public function decrypt($encrypted_text){
		try{
			$text	= openssl_decrypt($encrypted_text, $this->method, $this->key, $this->options, $this->iv);
		}catch(Exception $e){
			return new WP_Error('decrypt_aes_failed', 'aes 解密失败');
		}

		if($this->options == OPENSSL_ZERO_PADDING && $this->block_size){
			$text	= $this->pkcs7_unpad($text, $this->block_size);	//去除补位字符
		}

		return $text;
	}

	public static function pkcs7_pad($text, $block_size=32){	//对需要加密的明文进行填充 pkcs#7 补位
		//计算需要填充的位数
		$amount_to_pad	= $block_size - (strlen($text) % $block_size);
		$amount_to_pad	= $amount_to_pad ?: $block_size;

		//获得补位所用的字符
		return $text.str_repeat(chr($amount_to_pad), $amount_to_pad);
	}

	public static function pkcs7_unpad($text, $block_size){	//对解密后的明文进行补位删除
		$pad	= ord(substr($text, -1));

		if($pad < 1 || $pad > $block_size){
			$pad	= 0;
		}

		return substr($text, 0, (strlen($text) - $pad));
	}

	public static function weixin_pad($text, $appid){
		$random = self::generate_random_string(16);		//获得16位随机字符串，填充到明文之前
		return $random.pack("N", strlen($text)).$text.$appid;
	}

	public static function weixin_unpad($text, &$appid){	//去除16位随机字符串,网络字节序和AppId
		$text		= substr($text, 16, strlen($text));
		$len_list	= unpack("N", substr($text, 0, 4));
		$text_len	= $len_list[1];
		$appid		= substr($text, $text_len + 4);

		return substr($text, 4, $text_len);
	}

	public static function sha1(...$args){
		sort($args, SORT_STRING);

		return sha1(implode($args));
	}

	public static function generate_weixin_signature($token, &$timestamp='', &$nonce='', $encrypt_msg=''){
		$timestamp	= $timestamp ?: time();
		$nonce		= $nonce ?: self::generate_random_string(8);
		return self::sha1($encrypt_msg, $token, $timestamp, $nonce);
	}

	public static function generate_random_string($length){
		$alphabet	= "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		$max		= strlen($alphabet);
		$token		= '';

		for($i = 0; $i < $length; $i++){
			$token	.= $alphabet[self::crypto_rand_secure(0, $max - 1)];
		}

		return $token;
	}

	private static function crypto_rand_secure($min, $max){
		$range	= $max - $min;

		if($range < 1){
			return $min;
		}

		$log	= ceil(log($range, 2));
		$bytes	= (int)($log / 8) + 1;		// length in bytes
		$bits	= (int)$log + 1;			// length in bits
		$filter	= (int)(1 << $bits) - 1;	// set all lower bits to 1

		do {
			$rnd	= hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
			$rnd	= $rnd & $filter;	// discard irrelevant bits
		}while($rnd > $range);

		return $min + $rnd;
	}
}

class WPJAM_Updater extends WPJAM_Args{
	public function get_data($file){
		$key		= 'update_'.$this->plural.':'.$this->hostname;
		$response	= get_transient($key);

		if($response === false){
			$response	= wpjam_remote_request($this->update_url);	// https://api.wordpress.org/plugins/update-check/1.1/

			if(!is_wp_error($response)){
				if(isset($response['template']['table'])){
					$response	= $response['template']['table'];
				}else{
					$response	= $response[$this->plural];
				}

				set_transient($key, $response, MINUTE_IN_SECONDS);
			}else{
				$response	= false;
			}
		}

		if($response){
			if(isset($response['fields']) && isset($response['content'])){
				$fields	= array_column($response['fields'], 'index', 'title');
				$index	= $fields[$this->label];

				foreach($response['content'] as $item){
					if($item['i'.$index] == $file){
						$data	= [];

						foreach($fields as $name => $index){
							$data[$name]	= $item['i'.$index] ?? '';
						}

						return [
							$this->type		=> $file,
							'url'			=> $data['更新地址'],
							'package'		=> $data['下载地址'],
							'icons'			=> [],
							'banners'		=> [],
							'banners_rtl'	=> [],
							'new_version'	=> $data['版本'],
							'requires_php'	=> $data['PHP最低版本'],
							'requires'		=> $data['最低要求版本'],
							'tested'		=> $data['最新测试版本'],
						];
					}
				}
			}else{
				return $response[$file] ?? [];
			}
		}
	}

	public function filter_update($update, $data, $file, $locales){
		$new_data	= $this->get_data($file);

		if($new_data){
			return wp_parse_args($new_data, [
				'id'		=> $data['UpdateURI'], 
				'version'	=> $data['Version'],
			]);
		}

		return $update;
	}

	public static function create($type, $hostname, $update_url){
		if(in_array($type, ['plugin', 'theme'])){
			$plural	= $type.'s';
			$object	= new self([
				'type'			=> $type,
				'plural'		=> $plural,
				'label'			=> $type == 'plugin' ? '插件' : '主题',
				'hostname'		=> $hostname,
				'update_url'	=> $update_url
			]);

			add_filter('update_'.$plural.'_'.$hostname, [$object, 'filter_update'], 10, 4);
		}
	}
}

class WPJAM_Cache extends WPJAM_Args{
	use WPJAM_Instance_Trait;

	public function __construct($group, $args=[]){
		$this->args		= $args;
		$this->group	= $group;

		if($this->pull('global')){
			wp_cache_add_global_groups($group);
		}
	}

	public function __call($method, $args){
		if(str_starts_with($method, 'cache_')){
			$method	= wpjam_remove_prefix($method, 'cache_');
		}

		$key	= array_pull($args, 0);
		$key	= $this->key($key);

		if(in_array($method, ['get', 'delete'])){
			return call_user_func('wp_cache_'.$method, $key, $this->group);
		}elseif(in_array($method, ['add', 'replace', 'set'])){
			$value	= $args[1];
			$time	= $args[2] ?? ($this->cache_time ?: DAY_IN_SECONDS);

			return call_user_func('wp_cache_'.$method, $key, $value, $this->group, $time);
		}elseif(str_ends_with($method, '_item') || $method == 'empty'){
			trigger_error('2023-03-01删除');
			$method			= wpjam_remove_postfix($method, '_item');
			$items_object	= new WPJAM_Cache_Items($key, ['group'=>$this]);

			return call_user_func_array([$items_object, $method], $args);
		}
	}

	protected function key($key){
		return implode(':', array_filter([$this->prefix, $key]));
	}

	public function get_with_cas($key, &$cas_token){
		return wp_cache_get_with_cas($this->key($key), $this->group, $cas_token);
	}

	public function cas($cas_token, $key, $value, $expire=null){
		$expire	= $expire ?? ($this->cache_time ?: DAY_IN_SECONDS);

		return wp_cache_cas($cas_token, $this->key($key), $value, $this->group, $expire);
	}

	public function cache_get_with_cas($key, &$cas_token){
		return $this->get_with_cas($key, $cas_token);
	}

	public function cache_cas($cas_token, $key, $value, $expire=null){
		return $this->cas($cas_token, $key, $value, $expire);
	}

	public static function get_instance($group, $args=[]){
		$prefix	= $args['prefix'] ?? '';
		$name	= $group.($prefix ? ':'.$prefix : '');
		$object	= self::instance_exists($name);

		if(!$object && !is_null($args)){
			$object	= self::add_instance($name, new self($group, $args));
		}

		return $object;
	}

	/* HTML 片段缓存
	Usage:

	if (!WPJAM_Cache::output('unique-key')) {
		functions_that_do_stuff_live();
		these_should_echo();
		WPJAM_Cache::store(3600);
	}
	*/
	public static function output($key) {
		$output	= get_transient($key);

		if(!empty($output)) {
			echo $output;
			return true;
		}else{
			ob_start();
			return false;
		}
	}

	public static function store($key, $cache_time='600') {
		$output = ob_get_flush();
		set_transient($key, $output, $cache_time);
		echo $output;
	}
}

class IP{
	private static $ip = null;
	private static $fp = null;
	private static $offset = null;
	private static $index = null;
	private static $cached = [];

	public static function find($ip){
		if (empty( $ip ) === true) {
			return 'N/A';
		}

		$nip	= gethostbyname($ip);
		$ipdot	= explode('.', $nip);

		if ($ipdot[0] < 0 || $ipdot[0] > 255 || count($ipdot) !== 4) {
			return 'N/A';
		}

		if (isset( self::$cached[$nip] ) === true) {
			return self::$cached[$nip];
		}

		if (self::$fp === null) {
			self::init();
		}

		$nip2 = pack('N', ip2long($nip));

		$tmp_offset	= (int) $ipdot[0] * 4;
		$start		= unpack('Vlen',
			self::$index[$tmp_offset].self::$index[$tmp_offset + 1].self::$index[$tmp_offset + 2].self::$index[$tmp_offset + 3]);

		$index_offset = $index_length = null;
		$max_comp_len = self::$offset['len'] - 1024 - 4;
		for ($start = $start['len'] * 8 + 1024; $start < $max_comp_len; $start += 8) {
			if (self::$index[$start].self::$index[$start+1].self::$index[$start+2].self::$index[$start+3] >= $nip2) {
				$index_offset = unpack('Vlen',
					self::$index[$start+4].self::$index[$start+5].self::$index[$start+6]."\x0");
				$index_length = unpack('Clen', self::$index[$start+7]);

				break;
			}
		}

		if ($index_offset === null) {
			return 'N/A';
		}

		fseek(self::$fp, self::$offset['len'] + $index_offset['len'] - 1024);

		self::$cached[$nip] = explode("\t", fread(self::$fp, $index_length['len']));

		return self::$cached[$nip];
	}

	private static function init(){
		if(self::$fp === null){
			self::$ip = new self();

			self::$fp = fopen(WP_CONTENT_DIR.'/uploads/17monipdb.dat', 'rb');
			if (self::$fp === false) {
				throw new Exception('Invalid 17monipdb.dat file!');
			}

			self::$offset = unpack('Nlen', fread(self::$fp, 4));
			if (self::$offset['len'] < 4) {
				throw new Exception('Invalid 17monipdb.dat file!');
			}

			self::$index = fread(self::$fp, self::$offset['len'] - 4);
		}
	}

	public function __destruct(){
		if(self::$fp !== null){
			fclose(self::$fp);
		}
	}
}