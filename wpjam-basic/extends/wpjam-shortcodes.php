<?php
/*
Name: 常用简码
URI: https://blog.wpjam.com/m/wpjam-basic-shortcode/
Description: 添加 email, list, table, bilibili, youku, qqv 等常用简码，并在后台罗列系统的所有可用的简码。
Version: 1.0
*/
class WPJAM_Shortcode{
	public static function callback($attr, $content, $tag){
		$attr		= array_map('esc_attr', (array)$attr);
		$content	= wp_kses($content, 'post');

		if($tag == 'email'){
			$attr	= shortcode_atts(['mailto'=>false], $attr);

			return antispambot($content, $attr['mailto']);
		}elseif($tag == 'code'){
			$attr	= shortcode_atts(['type'=>'php'], $attr);
			$type	= $attr['type'] == 'html' ? 'markup' : $attr['type'];

			$content	= str_replace("<br />\n", "\n", $content);
			$content	= str_replace("</p>\n", "\n\n", $content);
			$content	= str_replace("\n<p>", "\n", $content);
			$content	= str_replace('&amp;', '&', esc_textarea($content)); // wptexturize 会再次转化 & => &#038;

			$content	= trim($content);

			return $type ? '<pre><code class="language-'.$type.'">'.$content.'</code></pre>' : '<pre>'.$content.'</pre>';
		}elseif($tag == 'list'){
			$attr		= shortcode_atts(['type'=>'', 'class'=>''], $attr);
			$content	= str_replace(["\r\n", "<br />\n", "</p>\n", "\n<p>"], "\n", $content);
			$output		= '';

			foreach(explode("\n", $content) as $li){
				if($li = trim($li)){
					$output .= "<li>".do_shortcode($li)."</li>\n";
				}
			}

			$class	= $attr['class'] ? ' class="'.$attr['class'].'"' : '';
			$tag	= in_array($attr['type'], ['order', 'ol']) ? 'ol' : 'ul';

			return '<'.$tag.$class.">\n".$output."</".$tag.">\n";
		}elseif($tag == 'table'){
			$attr	= shortcode_atts([
				'border'		=> '0',
				'cellpading'	=> '0',
				'cellspacing'   => '0',
				'width'			=> '',
				'class'			=> '',
				'caption'		=> '',
				'th'			=> '0',  // 0-无，1-横向，2-纵向，4-横向并且有 footer 
			], $attr);

			$output		= $thead = $tbody = '';
			$content	= str_replace(["\r\n", "<br />\n", "\n<p>", "</p>\n"], ["\n", "\n", "\n", "\n\n"], $content);

			if($attr['caption']){
				$output	.= '<caption>'.$attr['caption'].'</caption>';
			}

			$th		= $attr['th'];
			$tr_i	= 0;

			foreach(explode("\n\n", $content) as $tr){
				if($tr = trim($tr)){
					$tds	= explode("\n", $tr);

					if(($th == 1 || $th == 4) && $tr_i == 0){
						foreach($tds as $td){
							if($td = trim($td)){
								$thead .= "\t\t\t".'<th>'.$td.'</th>'."\n";
							}
						}

						$thead = "\t\t".'<tr>'."\n".$thead."\t\t".'</tr>'."\n";
					}else{
						$tbody .= "\t\t".'<tr>'."\n";
						$td_i	= 0;

						foreach($tds as $td){
							if($td = trim($td)){
								if($th == 2 && $td_i ==0){
									$tbody .= "\t\t\t".'<th>'.$td.'</th>'."\n";
								}else{
									$tbody .= "\t\t\t".'<td>'.$td.'</td>'."\n";
								}

								$td_i++;
							}
						}

						$tbody .= "\t\t".'</tr>'."\n";
					}

					$tr_i++;
				}
			}

			if($th == 1 || $th == 4){ $output .=  "\t".'<thead>'."\n".$thead."\t".'</thead>'."\n"; }
			if($th == 4){ $output .=  "\t".'<tfoot>'."\n".$thead."\t".'</tfoot>'."\n"; }

			$output	.= "\t".'<tbody>'."\n".$tbody."\t".'</tbody>'."\n";
			$attr	= wp_array_slice_assoc($attr, ['border', 'cellpading', 'cellspacing', 'width', 'class']);
			
			return wpjam_wrap_tag($output, 'table', $attr);
		}elseif(in_array($tag, ['video', 'bilibili', 'youku', 'tudou', 'qqv', 'sohutv'])){
			$attr	= shortcode_atts(['width'=>0, 'height'=>0], $attr);

			if($attr['width'] || $attr['height']){
				$attr_string	= image_hwstring($attr['width'], $attr['height']).' style="aspect-ratio:4/3;"';
			}else{
				$attr_string	= 'style="width:98%; aspect-ratio:4/3;"';
			}

			if(preg_match('#//www.bilibili.com/video/(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.$attr_string.' src="https://player.bilibili.com/player.html?bvid='.esc_attr($matches[1]).'" scrolling="no" border="0" frameborder="no" framespacing="0" allowfullscreen="true"></iframe>';
			}elseif(preg_match('#//v.qq.com/(.*)iframe/(player|preview).html\?vid=(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.$attr_string.' src="https://v.qq.com/'.esc_attr($matches[1]).'iframe/player.html?vid='.esc_attr($matches[3]).'" frameborder=0 allowfullscreen></iframe>';
			}elseif(preg_match('#//v.youku.com/v_show/id_(.*?).html#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.$attr_string.' src="https://player.youku.com/embed/'.esc_attr($matches[1]).'" frameborder=0 allowfullscreen></iframe>';
			}elseif(preg_match('#//www.tudou.com/programs/view/(.*?)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.$attr_string.' src="https://www.tudou.com/programs/view/html5embed.action?code='. esc_attr($matches[1]) .'" frameborder=0 allowfullscreen></iframe>';
			}elseif(preg_match('#//tv.sohu.com/upload/static/share/share_play.html\#(.+)#i',$content, $matches)){
				return '<iframe class="wpjam_video" '.$attr_string.' src="https://tv.sohu.com/upload/static/share/share_play.html#'.esc_attr($matches[1]).'" frameborder=0 allowfullscreen></iframe>';
			}else{
				return wp_video_shortcode($attr, $content);
			}
		}
	}

	public static function query_items($limit, $offset){
		$items	= [];

		foreach($GLOBALS['shortcode_tags'] as $tag => $function){
			if(is_array($function)){
				if(is_object($function[0])){
					$function	= '<p>'.get_class($function[0]).'->'.(string)$function[1].'</p>';
				}else{
					$function	= '<p>'.$function[0].'->'.(string)$function[1].'</p>';
				}
			}elseif(is_object($function)){
				$function	= '<pre>'.print_r($function, true).'</pre>';
			}else{
				$function	= wpautop($function);
			}

			$items[]	= ['tag'=>wpautop($tag), 'function'=>$function];
		}

		return ['items'=>$items, 'total'=>count($items)];
	}

	public static function get_actions(){
		return [];
	}

	public static function get_fields($action_key='', $id=0){
		return [
			'tag'		=> ['title'=>'简码',	'type'=>'view',	'show_admin_column'=>true],
			'function'	=> ['title'=>'函数',	'type'=>'view',	'show_admin_column'=>true]
		];
	}

	public static function get_list_table(){
		return [
			'model'			=> self::class,
			'primary_key'	=> 'tag',
			'per_page'		=> 300
		];
	}
}

add_shortcode('hide',		'__return_empty_string');
add_shortcode('email',		['WPJAM_Shortcode', 'callback']);
add_shortcode('list',		['WPJAM_Shortcode', 'callback']);
add_shortcode('table',		['WPJAM_Shortcode', 'callback']);
add_shortcode('code',		['WPJAM_Shortcode', 'callback']);
add_shortcode('video',		['WPJAM_Shortcode', 'callback']);
add_shortcode('youku',		['WPJAM_Shortcode', 'callback']);
add_shortcode('qqv',		['WPJAM_Shortcode', 'callback']);
add_shortcode('bilibili',	['WPJAM_Shortcode', 'callback']);
add_shortcode('tudou',		['WPJAM_Shortcode', 'callback']);
add_shortcode('sohutv',		['WPJAM_Shortcode', 'callback']);

wpjam_add_menu_page('wpjam-shortcodes', [
	'parent'		=> 'wpjam-basic',
	'menu_title'	=> '常用简码',
	'network'		=> false,
	'function'		=> 'list',
	'list_table'	=> 'WPJAM_Shortcode',
	'summary'		=> __FILE__,
	
]);