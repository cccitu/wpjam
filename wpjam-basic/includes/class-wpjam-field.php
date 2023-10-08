<?php
class WPJAM_Field extends WPJAM_Attr{
	protected function __construct($args){
		$this->args	= $args;

		$this->_data_type	= wpjam_get_data_type_object($this->data_type);

		if($this->_data_type){
			$this->query_args	= $this->_data_type->parse_query_args($this) ?: new StdClass;
		}
	}

	public function __get($key){
		if($key == 'default'){
			return $this->get_arg('show_in_rest.default', $this->value);
		}elseif($key == 'names'){
			return preg_match('/\[([^\]]*)\]/', $this->name) ? wpjam_parse_name($this->name) : [$this->name];
		}elseif($key == 'editable'){
			return $this->show_admin_column !== 'only' && !$this->disabled && !$this->readonly;
		}elseif($key == '_for'){
			if(!$this->is(['view', 'mu', 'fieldset', 'img', 'uploader', 'radio', 'checkbox'])
				|| ($this->is('checkbox') && !$this->options)
			){
				return $this->id;
			}
		}

		$value	= parent::__get($key);

		if(is_null($value)){
			if($key == '_title'){
				return $this->title.'「'.$this->key.'」';
			}elseif($key == '_schema'){
				return $this->parse_schema();
			}elseif($key == 'show_in_rest'){
				return $this->editable;
			}
		}

		if($key == 'show_if'){
			if($value && !is_object($value)){
				return $this->show_if = $this->parse_show_if($value);
			}
		}elseif($key == 'sortable'){
			return $this->editable && $value !== false ? 'sortable' : '';
		}elseif(in_array($key, ['min', 'max', 'minlength', 'maxlength', 'max_items', 'min_items'])){
			return is_numeric($value) ? $value : null;
		}

		return $value;
	}

	public function __call($method, $args){
		if(strpos($method, '_by_')){
			list($method, $type)	= explode('_by_', $method);

			if($this->{'_'.$type}){
				if($type == 'data_type'){
					$args[]	= array_merge((array)$this->query_args, ['title'=>$this->_title]);
				}

				return wpjam_try([$this->{'_'.$type}, $method], ...$args);
			}
		}elseif($method == 'get_schema'){
			return $this->_schema;
		}

		trigger_error($method);
	}

	public function is($type, $strict=false){
		$type	= (array)$type;

		if(!$strict){
			if(in_array('fieldset', $type) && is_a($this, 'WPJAM_Fieldset')){
				return true;
			}

			if(in_array('mu', $type) && is_a($this, 'WPJAM_MU_Field')){
				return true;
			}

			if(in_array('view', $type)){
				$type	= array_merge($type, ['hr', 'br']);
			}
		}

		return in_array($this->type, (array)$type, $strict);
	}

	protected function prepend_name($prepend){
		$this->name	= $prepend.'['.implode('][', $this->names).']';
	}

	protected function prepare_schema(){
		$type	= $this->type;
		$schema	= ['type'=>'string'];

		if($type == 'email'){
			$schema['format']	= 'email';
		}elseif($type == 'color'){
			$schema['format']	= 'hex-color';
		}elseif($type == 'url'){
			$schema['format']	= 'uri';
		}elseif(in_array($type, ['number', 'range'])){
			$step	= $this->step ?? 1;

			if($step == 'any' || strpos($step, '.')){
				$schema['type']	= 'number';
			}else{
				$schema['type']	= 'integer';

				if($step && $step != 1){
					$schema['multipleOf']	= $step;
				}
			}
		}elseif($type == 'timestamp'){
			$schema['type']	= 'integer';
		}elseif($type == 'checkbox'){
			$schema['type']	= 'boolean';
		}

		return $schema;
	}

	protected function parse_schema($schema=null){
		if(is_null($schema)){
			$schema	= $this->prepare_schema();
			$map	= [];

			if(in_array($schema['type'], ['number', 'integer'])){
				$map	= [
					'min'	=> 'minimum',
					'max'	=> 'maximum',
				];
			}elseif($schema['type'] == 'string'){
				$map	= [
					'minlength'	=> 'minLength',
					'maxlength'	=> 'maxLength',
					'pattern'	=> 'pattern',
				];
			}elseif($schema['type'] == 'array'){
				$map	= [
					'max_items'		=> 'maxItems',
					'min_items'		=> 'minItems',
					'unique_items'	=> 'uniqueItems',
				];
			}

			foreach($map as $key => $attr){
				if(isset($this->$key)){
					$schema[$attr]	= $this->$key;
				}
			}

			$_schema	= $this->get_arg('show_in_rest.schema');
			$_type		= $this->get_arg('show_in_rest.type');

			if(is_array($_schema)){
				$schema	= merge_deep($schema, $_schema);
			}

			if($_type){
				if($schema['type'] == 'array' && $_type != 'array'){
					$schema['items']['type']	= $_type;
				}else{
					$schema['type']	= $_type;
				}
			}

			if($this->required){
				$schema['required']	= true;
			}
		}

		$type	= $schema['type'];

		if($type != 'object'){
			unset($schema['properties']);
		}elseif($type != 'array'){
			unset($schema['items']);
		}

		if(isset($schema['enum'])){
			if($type == 'integer'){
				$callback	= 'intval';
			}elseif($type == 'number'){
				$callback	= 'floatval';
			}else{
				$callback	= 'strval';
			}

			$schema['enum']	= array_map($callback, $schema['enum']);
		}elseif(isset($schema['properties'])){
			foreach($schema['properties'] as &$property){
				$property	= $this->parse_schema($property, false);
			}
		}elseif(isset($schema['items'])){
			$schema['items']	= $this->parse_schema($schema['items'], false);
		}

		return $schema;
	}

	protected function parse_show_if($show_if){
		$show_if	= wpjam_parse_show_if($show_if);

		if($show_if){
			foreach(['postfix', 'prefix'] as $fix){
				$value	= array_pull($show_if, $fix, $this->{'_'.$fix});

				if($value){
					$show_if['key']	= wpjam_call('wpjam_add_'.$fix, $show_if['key'], $value);
				}
			}
		}

		return $show_if;
	}

	public function validate($value){
		$this->required($value);

		$value	= wpjam_try([$this, 'validate_value'], $value);

		$this->required($value);

		if(is_array($value) || is_populated($value)){	// 空值只需用 required 验证
			wpjam_try('rest_validate_value_from_schema', $value, $this->_schema, $this->_title);
		}

		return $value;
	}

	protected function required($value){
		if($this->required && is_blank($value)){
			throw new WPJAM_Exception([$this->_title], 'value_required');
		}
	}

	public function validate_value($value){
		if($this->_data_type){
			$value	= $this->validate_value_by_data_type($value);
		}

		if($this->is('timestamp')){
			$value	= wpjam_strtotime($value);
		}

		return $this->sanitize_value($value);
	}

	protected function sanitize_value($value, $schema=null){
		$schema	= $schema ?: $this->_schema;
		$type	= array_get($schema, 'type');

		if(is_null($value)){
			if(array_get($schema, 'required')){
				$value	= false;
			}
		}else{
			$value	= parent::sanitize_value($value);
		}

		if($type == 'array'){
			$schema	= array_get($schema, 'items');
			$value	= $this->map($value, 'sanitize_value', $schema);
		}elseif($type == 'integer'){
			if(is_numeric($value)){
				$value	= (int)$value;
			}
		}elseif($type == 'number'){
			if(is_numeric($value)){
				$value	= (float)$value;
			}
		}elseif($type == 'string'){
			if(is_scalar($value)){
				$value	= (string)$value;
			}
		}elseif($type == 'null'){
			if(is_blank($value)){
				$value	= null;
			}
		}elseif($type == 'boolean'){
			if(is_scalar($value) || is_null($value)){
				$value	= rest_sanitize_boolean($value);
			}
		}

		return $value;
	}

	public function pack($value){
		foreach(array_reverse($this->names) as $sub){
			$value	= [$sub => $value];
		}

		return $value;
	}

	public function unpack($data){
		return _wp_array_get($data, $this->names);
	}

	protected function value_callback($args){
		if(!$args || ($this->is('view') && !is_null($this->value))){
			return $this->default;
		}

		$value	= null;
		$name	= $this->names[0];
		$id		= array_pull($args, 'id');
		$arg	= $id ?? $args;

		if($this->value_callback && is_callable($this->value_callback)){
			$value	= wpjam_value_callback($this->value_callback, $name, $arg);
		}elseif($args){
			if(!empty($args['data']) && isset($args['data'][$name])){
				$value	= $args['data'][$name];
			}elseif(!empty($args['value_callback']) && $arg !== false){
				$value	= wpjam_value_callback($args['value_callback'], $name, $arg);
			}elseif(!empty($args['meta_type']) && $id){
				$value	= wpjam_get_metadata($args['meta_type'], $id, $name);
			}
		}

		if(!is_wp_error($value) && !is_null($value)){
			$value	= $this->unpack([$name=>$value]);

			if(!is_null($value)){
				return $value;
			}
		}

		return $this->default;
	}

	public function prepare($args){
		$value	= $this->value_callback($args);
		$value	= rest_sanitize_value_from_schema($value, $this->_schema, $this->_title);
		$value	= $this->prepare_value($value);

		return $value;
	}

	public function prepare_value($value){
		return $this->_data_type ? $this->prepare_value_by_data_type($value, $this->parse_required) : $value;
	}

	public function wrap($tag, $args=[]){
		$args	= wpjam_array($args);
		$args	= $args->push_arg('wrap_class', $this->disabled, $this->readonly, ($this->is('hidden') ? 'hidden' : ''));
		$data	= ['show_if'=>$this->show_if];

		if($data['show_if']){
			$args->push_arg('wrap_class', 'show_if');

			$tag	= $tag ?: 'span';
		}

		$class	= array_merge(array_wrap($this->wrap_class), array_wrap($args->pull('wrap_class')));
		$html	= wpjam_wrap_tag($this->render($args));
		$label	= $this->title ? wpjam_wrap_tag($this->title) : '';

		$sub_field	= in_array('sub-field', $class);

		if($label){
			$label->wrap('label', ['for'=>$this->_for]);

			if($sub_field){
				$label->push_arg('class', 'sub-field-label');
				$html->wrap('div', ['sub-field-detail']);
			}
		}

		if($tag == 'tr'){
			if($label){
				$label->wrap('th', ['scope'=>'row']);
			}

			$html->wrap('td', ['colspan'=>($label ? false : 2)]);
		}elseif($tag == 'p'){
			$label	.= $label ? wpjam_tag('br') : '';
		}

		return $html->before($label)->wrap($tag, ['class'=>$class, 'data'=>$data, 'id'=>$tag.'_'.esc_attr($this->id)]);
	}

	public function render($args=[]){
		if(is_null($this->class)){
			if($this->is('textarea')){
				$this->class	= ['large-text'];
			}elseif($this->is(['text', 'password', 'url', 'image', 'file', 'mu-image', 'mu-file'], true)){
				$this->class	= ['regular-text'];
			}
		}

		$this->class	= array_wrap($this->class);
		$this->value	= $this->value_callback($args);

		$rendered	= $this->render_component();
		$rendered	= $args ? $this->after_render($rendered) : $rendered;

		return (string)$rendered;
	}

	protected function render_component(){
		$args	= [];
		$value	= $this->value;

		if($this->is('hr')){
			return wpjam_tag('hr');
		}elseif($this->is('view')){
			if($this->options){
				$value	= $value ? [$value] : ['', 0];

				foreach($value as $v){
					if(isset($this->options[$v])){
						return $this->options[$v];
					}
				}
			}

			return $this->value;
		}elseif($this->is(['editor', 'textarea'])){
			$this->cols	= $this->cols ?: 50;
			$this->rows	= $this->rows ?: ($this->is('editor') ? 12 : 6);

			if($this->is('editor')){
				if(!wp_doing_ajax()){
					return wpjam_tag('div', ['style'=>$this->style], wpjam_ob_get_contents('wp_editor', $value, $this->id, [
						'textarea_name'	=> $this->name,
						'textarea_rows'	=> $this->rows
					]));
				}

				$args	= [
					'class'	=> ['editor', 'large-text'],
					'data'	=> ['settings'=>[
						'tinymce'		=> true,
						'quicktags'		=> true,
						'mediaButtons'	=> current_user_can('upload_files')
					]]
				];
			}

			return $this->tag('textarea', $args, esc_textarea(implode("\n", (array)$value)));
		}

		$query	= $label = '';

		if($this->is('color')){
			$args	= ['type'=>'text', 'class'=>'color'];
		}elseif($this->is('checkbox')){
			$label	= $this->label;
			$args	= ['value'=>1,	'checked'=>($value == 1)];
		}elseif($this->is('timestamp')){
			$args	= ['type'=>'datetime-local', 'value'=>($value ? wpjam_date('Y-m-d\TH:i', $value) : '')];
		}elseif($this->_data_type){
			$query	= $this->query_label_by_data_type($this->value) ?: '';
			$args	= ['class'=>['autocomplete', ($query ? 'hidden' : '')]];
			$query	= $this->render_query($query);
		}

		return $this->tag('input', $args)->after($label)->after($query);
	}

	protected function render_query($label){
		return self::get_icon('dismiss')->after($label, 'span', ['query-text'])->wrap('span', array_merge($this->class, ['query-title']));
	}

	protected function after_render($tag){
		$tag	= wpjam_wrap_tag($tag)->before($this->before)->after($this->after);

		if($this->_for && ($this->before || $this->after || $this->label)){
			$tag->wrap('label', ['id'=>'label_'.$this->_for, 'for'=>$this->_for]);
		}

		if($this->description){
			$tag->after($this->description, 'p', ['description']);
		}

		if($this->buttons){
			foreach($this->buttons as $key => $button){
				$tag->after(' '.self::create($button, $key));
			}
		}

		return $tag;
	}

	protected function tag($tag='input', $attr=[], $text=''){
		$class	= array_merge($this->class, array_wrap(array_pull($attr, 'class')), ['field-key-'.$this->key]);
		$attr	= merge_deep($this->get_args(), $attr);
		$data	= array_merge(array_wrap(array_pull($attr, 'data'), []), array_pulls($attr, ['key', 'data_type', 'query_args']));
		$attr	= array_merge($attr, ['data'=>$data, 'class'=>$class]);
		$attr	= array_except($attr, ['default', 'options', 'title', 'label', 'before', 'after', 'description', 'item_type', 'item_class', 'max_items', 'min_items', 'unique_items', 'direction', 'group', 'buttons', 'button_text', 'custom_input', 'size', 'post_type', 'taxonomy', 'sep', 'fields', 'mime_types', 'drap_drop', 'parse_required', 'show_if', 'show_in_rest', 'sortable_column', 'column_style', 'show_admin_column', 'wrap_class']);

		if($tag == 'input'){
			if(!isset($attr['inputmode'])){
				if(in_array($attr['type'], ['url', 'tel', 'email', 'search'])){
					$attr['inputmode']	= $attr['type'];
				}elseif($attr['type'] == 'number'){
					$step	= $attr['step'] ?? 1;

					$attr['inputmode']	= ($step == 'any' || strpos($step, '.')) ? 'decimal' : 'numeric';
				}
			}
		}else{
			$attr	= array_except($attr, ['type', 'value']);
		}

		return wpjam_tag($tag, $attr, $text);
	}

	public function affix($affix_by, $i=null, $item=null){
		$prepend	= $affix_by->name;
		$prefix		= $affix_by->key.'__';
		$postfix	= '';

		if(isset($i)){
			$prepend	.= '['.$i.']';
			$postfix	= $this->_postfix = '__'.$i;

			if(is_array($item) && isset($item[$this->name])){
				$this->value	= $item[$this->name];
			}
		}

		$this->prepend_name($prepend);

		$this->_prefix	= $prefix.$this->_prefix ;
		$this->id		= $prefix.$this->id.$postfix;
		$this->key		= $prefix.$this->key.$postfix;
	}

	public function callback($args=[]){
		return $this->render($args);
	}

	public static function get_icon($name){
		$icon	= wpjam_tag();

		foreach(wp_parse_list($name) as $name){
			if($name == 'sortable'){
				$icon->after('span', ['dashicons', 'dashicons-menu']);
			}elseif($name == 'multiply'){
				$icon->after('span', ['dashicons', 'dashicons-no-alt']);
			}elseif($name == 'dismiss'){
				$icon->after('span', ['dashicons', 'dashicons-dismiss', 'init']);
			}elseif($name == 'del_btn'){
				$icon->after('a', ['button', 'del-item'], '删除');
			}elseif(in_array($name, ['del_icon', 'del_img'])){
				$class	= $name == 'del_icon' ? 'del-item' : 'del-img';
				$icon->after('a', ['dashicons', 'dashicons-no-alt', $class]);
			}
		}

		return $icon;
	}

	public static function create($args, $key=''){
		if(empty($args['key']) && $key){
			$args['key']	= $key;
		}

		if(is_numeric($args['key'])){
			trigger_error('Field 的 key「'.$args['key'].'」'.'不能为纯数字');
			return;
		}elseif(!$args['key']){
			trigger_error('Field 的 key 不能为空');
			return;
		}

		$total	= array_pull($args, 'total');

		if($total && !isset($args['max_items'])){
			$args['max_items']	= $total;
		}

		$field	= self::parse_attr($args);

		if(!empty($field['size'])){
			$size	= $field['size'] = wpjam_parse_size($field['size']);

			if(!isset($field['description']) && !empty($size['width']) && !empty($size['height'])){
				$field['description']	= '建议尺寸：'.$size['width'].'x'.$size['height'];
			}
		}

		if(empty($fields['buttons']) && !empty($field['button'])){
			$fields['buttons']	= [$field['button']];
		}

		$field['before']	= empty($field['before']) ? '' : $field['before'].' ';
		$field['after']		= empty($field['after']) ? '' : ' '.$field['after'];
		$field['options']	= array_wrap(array_pull($field, 'options'), 'wp_parse_args');
		$field['id']		= array_pull($field, 'id') ?: $field['key'];
		$field['name']		= array_pull($field, 'name') ?: $field['key'];
		$field['type']		= $type	= array_pull($field, 'type') ?: ($field['options'] ? 'select' : 'text');

		if(in_array($type, ['image', 'mu-image'])){
			$field['item_type']	= 'image';
		}elseif($type == 'mu-text'){
			$field['item_type']		= $field['item_type'] ?? 'text';
			$field['item_class']	= $field['class'] ?? null;
		}

		if($type == 'checkbox'){
			if($field['options']){
				return new WPJAM_Options_Field($field);
			}else{
				if(!isset($field['label']) && !empty($field['description'])){
					$field['label']	= array_pull($field, 'description');
				}
			}
		}elseif(in_array($type, ['select', 'radio'])){
			return new WPJAM_Options_Field($field);
		}elseif(in_array($type, ['fieldset', 'fields'])){
			return new WPJAM_Fieldset($field);
		}elseif(in_array($type, ['img', 'image', 'file', 'uploader'], true)){
			return new WPJAM_Image_Field($field);
		}elseif(str_starts_with($type, 'mu-')){
			return new WPJAM_MU_Field($field);
		}elseif(in_array($type, ['view', 'hr', 'br'], true)){
			$field['disabled']	= 'disabled';
		}

		return new WPJAM_Field($field);
	}
}

class WPJAM_Options_Field extends WPJAM_Field{
	public function __get($key){
		$value	= parent::__get($key);

		if(is_null($value)){
			if($key == '_values'){
				return $this->_values = array_keys(wpjam_parse_options($this->options));
			}elseif($key == '_custom' && $this->custom_input){
				$input	= $this->custom_input;
				$custom	= is_array($input) ? $input : [];
				$custom	= self::create(wp_parse_args($custom, [
					'title'			=> is_string($input) ? $input : '其他',
					'placeholder'	=> '请输入其他选项',
					'id'			=> $this->id.'__custom_input',
					'key'			=> $this->key.'__custom_input',
					'type'			=> 'text',
					'class'			=> '',
					'required'		=> true,
					'data-wrap_id'	=> $this->is('select') ? '' : $this->id.'_options',
					'show_if'		=> ['key'=>$this->key, 'value'=>'__custom'],
				]));

				$custom->_title	= $this->_title.'-「'.$custom->title.'」';

				return $this->_custom = $custom;
			}
		}

		return $value;
	}

	public function __call($method, $args){
		if(str_ends_with($method, '_by_custom')){
			$field	= $this->_custom;
			$value	= $args[0];

			if(!$field){
				return $value;
			}

			$custom	= null;
			$values	= array_map('strval', $this->_values);

			if($this->is('checkbox')){
				$value	= array_diff($value, ['__custom']);
				$diff	= array_diff($value, $values);

				if($method == 'validate_by_custom' && count($diff) > 1){
					throw new WPJAM_Exception($field->_title.'只能传递一个其他选项值', 'too_many_custom_value');
				}

				if($diff){
					$custom	= current($diff);
				}
			}else{
				if($value && !in_array($value, $values)){
					$custom	= $value;
				}
			}

			if($method == 'render_by_custom'){
				$field->value	= $custom;
			}elseif($method == 'validate_by_custom'){
				if(isset($custom)){
					$field->_schema	= $this->is('checkbox') ? $this->get_arg('_schema.items') : $this->_schema;
					$field->validate($custom);
				}
			}

			return $value;
		}

		return parent::__call($method, $args);
	}

	protected function prepare_schema(){
		$schema	= ['type'=>'string'];

		if(!$this->_custom){
			$schema['enum']	= $this->_values;
		}

		return $this->is('checkbox') ? ['type'=>'array', 'items'=>$schema] : $schema;
	}

	public function prepare_value($value){
		if($this->is('checkbox')){
			return $this->prepare_by_custom($value);
		}

		return $value;
	}

	public function validate_value($value){
		if($this->is('checkbox')){
			$value	= $value ?: [];
		}

		return $this->sanitize_value($this->validate_by_custom($value));
	}

	protected function render_component(){
		if(!$this->is('select')){
			$this->update_args(['data-wrap_id'=>$this->id.'_options']);
		}

		if($this->is('checkbox')){
			$this->name	.= '[]';

			if(!is_array($this->value) && !is_blank($this->value)){
				$this->value	= [$this->value];
			}
		}else{
			$this->value	= $this->value ?? current($this->_values);
		}

		$items	= $this->options;
		$custom	= $this->_custom;

		if($custom){
			$this->value	= $this->render_by_custom($this->value);

			$items	= array_replace($items, ['__custom'=>$custom->title]);
			$custom	= $custom->update_args(['title'=>'', 'name'=>$this->name])->wrap('span');
		}

		$items	= $this->render_options($items);

		if($this->is('select')){
			if($custom){
				$this->after	.= '&emsp;'.$custom;
			}

			return $this->tag('select', [], implode('', $items));
		}else{
			if($custom){
				$items[]	= $custom;
			}

			$dir	= $this->direction ?: ($this->sep ? '' : 'row');
			$sep	= $this->sep ?? ($dir ? '' : '&emsp;');
			$class	= [($dir ? 'direction-'.$dir : ''), ($this->type == 'checkbox' ? 'mu-checkbox' : '')];

			return wpjam_wrap_tag(implode($sep, $items), 'span', [
				'id'	=> $this->id.'_options',
				'class'	=> $class,
				'data'	=> ['max_items'=>$this->max_items]
			]);
		}
	}

	protected function render_options($options){
		$field	= $this->_custom;
		$items	= [];

		foreach($options as $opt => $label){
			$attr	= $data = $class = [];

			if(is_array($label)){
				$opt_arr	= $label;
				$label		= array_pull($opt_arr, 'label', array_pull($opt_arr, 'title'));
				$bool_attr	= ['disabled', 'required', 'hidden'];

				foreach($opt_arr as $k => $v){
					if(is_numeric($k)){
						if(in_array($v, $bool_attr)){
							$attr[$v]	= $v;
						}
					}elseif(in_array($k, $bool_attr)){
						if($v){
							$attr[$k]	= $k;
						}
					}elseif($k == 'show_if'){
						$show_if	= $this->parse_show_if($v);

						if($show_if){
							$class[]	= 'show_if';
							$data		+= ['show_if'=>$show_if];
						}
					}elseif($k == 'class'){
						$class	= array_merge($class, array_wrap($v));
					}elseif($k == 'options'){
						$attr[$k]	= $v;
					}elseif(!is_array($v)){
						$data[$k]	= $v;
					}
				}
			}

			if($opt === '__custom'){
				$checked	= $field && !is_null($field->value);
			}else{
				if($this->is('checkbox')){
					$checked	= is_array($this->value) && in_array($opt, $this->value);
				}else{
					$checked	= $this->value ? ($opt == $this->value) : !$opt;
				}
			}

			if($this->is('select')){
				$attr		= $attr+['data'=>$data, 'class'=>$class];
				$sub_opts	= array_pull($attr, 'options');

				if(isset($sub_opts)){
					if($sub_opts){
						$sub_opts	= implode('', $this->render_options($sub_opts));
						$items[]	= wpjam_tag('optgroup', array_merge($attr, ['label'=>$label]), $sub_opts);
					}
				}else{
					$items[]	= wpjam_tag('option', array_merge($attr, ['value'=>$opt, 'selected'=>$checked]), $label);
				}
			}else{
				$class[]	= $checked ? 'checked' : '';
				$opt_id		= $this->id.'_'.$opt;
				$attr		= array_merge($attr, ['required'=>false, 'checked'=>$checked, 'id'=>$opt_id, 'value'=>$opt]);
				$items[]	= $this->tag('input', $attr)->after($label)->wrap('label', ['data'=>$data, 'class'=>$class, 'id'=>'label_'.$opt_id, 'for'=>$opt_id]);
			}
		}

		return $items;
	}
}

class WPJAM_Image_Field extends WPJAM_Field{
	protected function prepare_schema(){
		if($this->is('uploader')){
			return ['type'=>'string'];
		}elseif($this->is('img')){
			if($this->item_type != 'url'){
				return ['type'=>'integer'];
			}
		}

		return ['type'=>'string', 'format'=>'uri'];
	}

	public function prepare_value($value){
		return $this->is('uploader') ? $value : wpjam_get_thumbnail($value, $this->size);
	}

	protected function render_component(){
		if(!current_user_can('upload_files')){
			$this->disabled	= 'disabled';
		}

		if($this->is('uploader')){
			$class		= ['hide-if-no-js', 'plupload'];
			$mime_types	= $this->mime_types ?: ['title'=>'图片', 'extensions'=>'jpeg,jpg,gif,png'];
			$mime_types	= wp_is_numeric_array($mime_types) ? $mime_types : [$mime_types];
			$btn_id		= 'plupload_button__'.$this->key;
			$btn_text	= $this->button_text ?: __('Select Files');
			$btn_attr	= ['type'=>'button', 'class'=>'button', 'id'=>$btn_id, 'value'=>$btn_text];
			$container	= 'plupload_container__'.$this->key;
			$plupload	= [
				'browse_button'		=> $btn_id,
				'container'			=> $container,
				'file_data_name'	=> $this->key,
				'filters'			=> [
					'mime_types'	=> $mime_types,
					'max_file_size'	=> (wp_max_upload_size()?:0).'b'
				],
				'multipart_params'	=> [
					'_ajax_nonce'	=> wp_create_nonce('upload-'.$this->key),
					'action'		=> 'wpjam-upload',
					'file_name'		=> $this->key,
				]
			];

			$data	= ['key'=>$this->key, 'plupload'=>&$plupload];
			$title	= $this->value ? array_slice(explode('/', $this->value), -1)[0] : '';
			$args	= ['type'=>'hidden', 'class'=>($this->value ? 'hidden' : '')];
			$tag	= $this->tag('input', $args)->before('input', $btn_attr)->after($this->render_query($title));

			if($this->drap_drop && !wp_is_mobile()){
				$dd_id		= 'plupload_drag_drop__'.$this->key;
				$plupload	+= ['drop_element'=>$dd_id];
				$class[]	= 'drag-drop';

				$tag->wrap('p', ['drag-drop-buttons'])
				->before('p', [], _x('or', 'Uploader: Drop files here - or - Select Files'))
				->before('p', ['drag-drop-info'], __('Drop files to upload'))
				->wrap('div', ['drag-drop-inside'])
				->wrap('div', ['id'=>$dd_id, 'class'=>'plupload-drag-drop']);
			}

			$progress	= wpjam_tag('div', ['progress', 'hidden'], ['div', ['percent']])->append('div', ['bar']);

			return $tag->after($progress)->wrap('div', ['id'=>$container, 'class'=>$class, 'data'=>$data]);
		}elseif($this->is('img')){
			$size	= $this->size ?: '600x0';
			$size	= wpjam_constrain_size($size, 600, 600);
			$attr	= array_filter(['width'=>(int)($size['width']/2), 'height'=>(int)($size['height']/2)]);
			$data	= ['item_type'=>$this->item_type, 'thumb_args'=>wpjam_get_thumbnail_args($size)];
			$img	= $this->value ? wpjam_get_thumbnail($this->value, $size) : '';
			$img	= wpjam_tag('img', $attr+['src'=>$img, 'class'=>($img ? '' : 'hidden')]);
			$button	= wpjam_tag('span', ['wp-media-buttons-icon'])->after($this->button_text ?: '添加图片')->wrap('button', ['button', 'add_media'])->wrap('div', ['wp-media-buttons']);

			return $this->tag('input', ['type'=>'hidden'])->before($img.$button.self::get_icon('del_img'), 'div', ['class'=>'wpjam-img', 'data'=>$data]);
		}else{
			$title	= '选择'.($this->is('image') ? '图片' : '文件');

			return $this->tag('input', ['type'=>'url'])->after($title, 'a', ['class'=>'button', 'data'=>['item_type'=>$this->item_type]])->wrap('div', ['wpjam-file']);
		}
	}
}

class WPJAM_MU_Field extends WPJAM_Field{
	public function __get($key){
		$value	= parent::__get($key);

		if(is_null($value)){
			if($key == '_item'){
				$args	= array_except($this->get_args(), ['required', 'show_in_rest']);	// 提交时才验证

				$args['type']	= $this->item_type;
				$args['class']	= $this->item_class;

				if($this->item_type != 'select' && $this->direction == 'row' && is_null($args['class'])){
					$args['class']	= 'medium-text';
				}

				return $this->_item = self::create($args);
			}elseif($key == '_fields'){
				return $this->_fields = WPJAM_Fields::create($this->fields, $this);
			}
		}

		return $value;
	}

	protected function prepare_schema(){
		if($this->is('mu-fields')){
			$items	= $this->get_schema_by_fields();
		}elseif($this->is('mu-text')){
			$items	= $this->get_schema_by_item();
		}else{
			$items	= ['type'=>'string', 'format'=>'uri'];

			if($this->is('mu-img') && $this->item_type != 'url'){
				$items	= ['type'=>'integer'];
			}
		}

		return ['type'=>'array', 'items'=>$items];
	}

	public function prepare_value($value){
		if($this->is('mu-fields')){
			return $this->map($value, 'prepare_value_by_fields');
		}elseif($this->is('mu-text')){
			return $this->map($value, 'prepare_value_by_item');
		}else{
			if($value && is_array($value)){
				$value	= wpjam_map($value, 'wpjam_get_thumbnail', $this->size);
				$value	= array_filter($value);
			}

			return $value;
		}
	}

	public function validate_value($value){
		if($value){
			$value	= is_array($value) ? filter_deep($value, 'is_populated') : wpjam_json_decode($value);
		}

		if(!$value || is_wp_error($value)){
			return $this->required ? null : [];
		}

		$value	= array_values($value);

		if($this->is('mu-fields')){
			return $this->map($value, 'validate_value_by_fields');
		}elseif($this->is('mu-text')){
			return $this->map($value, 'validate_value_by_item');
		}

		return $value;
	}

	protected function render_component(){
		if($this->is(['mu-img', 'mu-image', 'mu-file'])){
			if(!current_user_can('upload_files')){
				$this->disabled	= 'disabled';
			}

			$data	= ['item_type'=>$this->item_type];
		}

		$value	= $this->value ?: [];

		if(!is_blank($value)){
			if(is_array($value)){
				$value	= filter_deep($value, 'is_populated');
				$value	= array_values($value);
			}else{
				$value	= (array)$value;
			}
		}

		$last		= count($value);
		$value[]	= null;

		if($this->is('mu-text')){
			if(count($value) <= 1 && $this->direction == 'row' && $this->item_type != 'select'){
				$last ++;

				$value[]	= null;
			}
		}elseif($this->is('mu-img')){
			$this->direction	= 'row';
		}

		if(!$this->is(['mu-fields', 'mu-img']) && $this->max_items && $last >= $this->max_items){
			unset($value[$last]);

			$last --;
		}

		$args	= ['id'=>'', 'name'=>$this->name.'[]'];
		$items	= [];
		$text	= $this->button_text ?: '添加'.(($this->title && mb_strwidth($this->title) <= 8) ? $this->title : '选项');

		foreach($value as $i => $item){
			$args['value']	= $item;

			if($this->is('mu-fields')){
				if($last === $i){
					$item	= $this->render_by_fields(['i'=>'{{ data.i }}']);
					$item	= wpjam_tag('script', ['type'=>'text/html', 'id'=>'tmpl-'.md5($this->name)], $item);
				}else{
					$item	= $this->render_by_fields(['i'=>$i, 'item'=>$item]);
				}
			}elseif($this->is('mu-text')){
				if($this->item_type == 'select' && $last === $i){
					$options	= $this->get_arg_by_item('options');

					if(!in_array('', array_keys($options))){
						$args['options']	= array_replace([''=>['title'=>'请选择', 'disabled', 'hidden']], $options);
					}
				}

				$item	= $this->archive_by_item()->update_args($args)->render();

				$this->restore_by_item();
			}elseif($this->is('mu-img')){
				$img	= $item ? wpjam_get_thumbnail($item) : '';
				$thumb	= wpjam_get_thumbnail($item, [200, 200]);
				$item	= $this->tag('input', array_merge($args, ['type'=>'hidden']));

				if($img){
					$item->before('a', ['href'=>$img, 'class'=>'wpjam-modal'], ['img', ['src'=>$thumb]]);
				}
			}else{
				$item	= $this->tag('input', array_merge($args, ['type'=>'url']));
			}

			$icon	= ($this->direction == 'row' ? 'del_icon' : 'del_btn').','.$this->sortable;
			$item	.= self::get_icon($icon);

			if($last === $i){
				$class	= 'button';

				if($this->is('mu-text')){
					$data	= [];
				}elseif($this->is('mu-fields')){
					$data	= ['i'=>$i, 'tmpl_id'=>md5($this->name)];
				}elseif($this->is('mu-img')){
					$data	+= ['thumb_args'=>wpjam_get_thumbnail_args([200, 200])];
					$text	= '';
					$class	= 'dashicons dashicons-plus-alt2';
				}else{
					$data	+= ['title'=>($this->item_type == 'image' ? '选择图片' : '选择文件')];
					$text	= $data['title'].'[多选]';
				}

				$item	.= wpjam_tag('a', ['class'=>'new-item '.$class, 'data'=>$data], $text);
			}

			$items[]	= wpjam_tag('div', ['mu-item', ($this->group ? 'field-group' : '')], $item);
		}

		return wpjam_wrap_tag(implode("\n", $items), 'div', [
			'id'	=> $this->id,
			'class'	=> [$this->type, $this->sortable, 'direction-'.($this->direction ?: 'column')],
			'data'	=> ['max_items'=>$this->max_items]
		]);
	}
}

class WPJAM_FieldSet extends WPJAM_Field{
	public function __get($key){
		if($key == 'fieldset_type' && $this->_data_type){
			return 'array';
		}

		$value	= parent::__get($key);

		if(is_null($value) && $key == '_fields'){
			if($this->is('fields')){
				$this->fields	= $this->fields ?: [];

				if($this->fields_type == 'size'){
					$fields	= [];

					foreach(['width', 'x', 'height'] as $key){
						if($key == 'x'){
							$fields['x']	= ['type'=>'view',	'value'=>self::get_icon('multiply')];
						}else{
							$field	= $this->fields[$key] ?? [];
							$field	= wp_parse_args($field, ['type'=>'number', 'class'=>'small-text']);
							$key	= array_pull($field, 'key') ?: $key;

							$fields[$key]	= $field;
						}
					}

					$this->fields	= $fields;
				}
			}

			return $this->_fields = WPJAM_Fields::create($this->fields, $this);
		}

		return $value;
	}

	protected function prepare_schema(){
		if($this->_data_type){
			return parent::prepare_schema();
		}

		return $this->get_schema_by_fields();
	}

	public function validate_value($value){
		if($this->_data_type){
			return parent::validate_value($value);
		}

		return $this->validate_value_by_fields($value);
	}

	public function render($args=[]){
		$fields	= $this->render_by_fields($args);
		$fields	= $this->after_render($fields);

		if($this->summary){
			$fields->before([$this->summary, 'strong'], 'summary')->wrap('details');
		}

		$class	= array_wrap($this->class);
		$data	= $this->data ?: [];

		if($this->is('fieldset', true) && $this->group){
			$class[]	= 'field-group';
		}

		if($this->fieldset_type == 'array'){
			$data['key']	= $this->key;

			if($this->_data_type){
				$data['value']	= $this->render_value_by_data_type($this->value_callback($args)) ?: new StdClass;
			}
		}

		if($class || $data || $this->style){
			$fields->wrap('div', ['data'=>$data, 'class'=>$class, 'style'=>$this->style]);
		}

		return (string)$fields;
	}
}

class WPJAM_Fields extends WPJAM_Attr{
	private $fields		= [];
	private $creator	= null;

	private function __construct($fields, $creator=null){
		$this->fields	= $fields;
		$this->creator	= $creator;
	}

	public function	__call($method, $args){
		$data	= wpjam_array();

		foreach($this->fields as $field){
			if(in_array($method, ['get_schema', 'get_defaults', 'get_show_if_values'])){
				if(!$field->editable){
					continue;
				}
			}elseif($method == 'prepare'){
				if(!$field->show_in_rest){
					continue;
				}
			}

			if($field->is('fieldset') && !$field->_data_type){
				$value	= wpjam_try([$field, $method.'_by_fields'], ...$args);
			}else{
				if($method == 'prepare'){
					$value	= $field->pack($field->prepare(...$args));
				}elseif($method == 'get_defaults'){
					$value	= $field->pack($field->default);
				}elseif($method == 'get_show_if_values'){ // show_if 判断基于key，并且array类型的fieldset的key是 ${key}__{$sub_key}
					$item	= wpjam_call([$field, 'validate'], $field->unpack($args[0]));
					$value	= [$field->key => is_wp_error($item) ? null : $item];
				}elseif(in_array($method, ['get_schema', 'prepare_value', 'validate_value'])){
					$name	= array_value_last($field->names);

					if($method == 'get_schema'){
						$value	= [$name => $field->_schema];
					}else{
						$item	= $args[0][$name] ?? null;
						$value	= is_null($item) ? [] : [$name => wpjam_try([$field, $method], $item)];
					}
				}else{
					$value	= wpjam_try([$field, $method], ...$args);
				}
			}

			$data->merge($value);
		}

		return $data->to_array();
	}

	public function	__invoke($args=[]){
		return $this->render($args);
	}

	public function validate($values=null){
		$values	= $values ?? wpjam_get_post_parameter();

		if($this->creator && isset($this->creator->_if_values)){
			$if_values	= $this->creator->_if_values;
			$if_show	= $this->creator->_if_show;
		}else{
			$if_values	= $this->get_show_if_values($values);
			$if_show	= true;
		}

		$data	= wpjam_array();

		foreach($this->fields as $field){
			if(!$field->editable){
				continue;
			}

			$show	= $if_show ? wpjam_show_if($if_values, $field->show_if) : false;

			if($field->is('fieldset') && $field->fieldset_type != 'array'){
				$field->_if_values	= $if_values;
				$field->_if_show	= $show;

				$value	= $field->validate_by_fields($values);
			}else{
				if($show){
					$value	= $field->unpack($values);
					$value	= $field->validate($value, true);
				}else{	// 第一次获取的值都是经过 json schema validate 的，可能存在 show_if 的字段在后面
					$value	= $if_values[$field->key] = null;
				}

				$value	= $field->pack($value);
			}

			$data->merge($value);
		}

		return $data->to_array();
	}

	public function get_schema(){
		return ['type'=>'object', 'properties'=>$this->__call('get_schema',[])];
	}

	public function render($args=null){
		$fields		= [];
		$grouped	= [];
		$pre_group	= '';
		$creator	= '';

		$sep	= "\n";
		$args	= $args ?? $this->get_args();
		$args	= wpjam_array($args);

		if($this->creator){
			$creator	= $this->creator->type;

			$type	= '';
			$tag	= 'div';

			if($creator == 'fields'){
				$sep	= $this->creator->sep ?? ' ';
				$tag	= '';
			}else{
				$args->push_arg('wrap_class', 'sub-field');

				if($creator == 'mu-fields'){
					$i		= $args->pull('i');
					$item	= $args->pull('item');
				}
			}
		}else{
			$type	= $args->pull('fields_type', 'table');

			if(isset($args->wrap_tag)){
				$tag	= $args->pull('wrap_tag');
			}else{
				$map	= ['table'=>'tr', 'list'=>'li'];
				$tag	= $map[$type] ?? $type;
			}
		}

		foreach($this->fields as $field){
			if($field->show_admin_column === 'only'){
				continue;
			}

			$field->archive();

			if($creator == 'mu-fields'){
				$field->affix($this->creator, $i, $item);
			}

			$wrapped	= $field->wrap($tag, $args);

			$field->restore();

			if($creator && $creator != 'fields'){
				if($field->group != $pre_group){
					$fields[]	= $this->group($grouped, $pre_group);
					$grouped	= [];
					$pre_group	= $field->group;
				}

				$grouped[]	= $wrapped;
			}else{
				$fields[]	= $wrapped;
			}
		}

		if($grouped){
			$fields[]	= $this->group($grouped, $pre_group);
		}

		$fields	= implode($sep, array_filter($fields));

		if($type == 'table'){
			$fields	= wpjam_tag('table', ['cellspacing'=>0, 'class'=>'form-table'], [$fields, 'tbody']);
		}elseif($type == 'list'){
			$fields	= wpjam_tag('ul', [], $fields);
		}

		return (string)$fields;
	}

	protected function group($grouped, $pre_group){
		$grouped	= implode('', $grouped);

		return $pre_group ? wpjam_tag('div', ['field-group'], $grouped) : $grouped;
	}

	public function callback($args=[]){
		return $this->render($args);
	}

	public static function create($fields, $creator=null){
		$objects	= [];
		$prefix		= '';
		$propertied	= false;

		if($creator){
			if($creator->is('fieldset')){
				if($creator->fieldset_type == 'array'){
					$propertied	= true;
				}else{
					if($creator->prefix){
						$prefix	= $creator->prefix;
						$prefix	= $prefix === true ? $creator->key : $prefix;
					}
				}
			}elseif($creator->is('mu-fields')){
				$propertied	= true;
			}
		}

		foreach((array)$fields as $key => $field){
			if(isset($field['type']) && $field['type'] == 'fields'){
				$field['prefix']	= $prefix;
			}else{
				$key	= ($prefix ? $prefix.'_' : '').$key;
			}

			$object	= WPJAM_Field::create($field, $key);

			if(!$object){
				continue;
			}

			if($propertied){
				if(count($object->names) > 1){
					trigger_error($creator->_title.'子字段不允许[]模式:'.$object->name);

					continue;
				}

				if($object->is('fieldset', true) || $object->is('mu-fields')){
					trigger_error($creator->_title.'子字段不允许'.$object->type.':'.$object->name);

					continue;
				}
			}

			$objects[$key]	= $object;

			if($creator){
				if($creator->is('fieldset')){
					if($creator->fieldset_type == 'array'){
						$object->affix($creator);
					}else{
						if(!isset($object->show_in_rest)){
							$object->show_in_rest	= $creator->show_in_rest;
						}
					}
				}

				foreach(['readonly', 'disabled'] as $key){
					if($creator->$key){
						$object->$key	= $creator->$key;
					}
				}
			}
		}

		return new self($objects, $creator);
	}
}