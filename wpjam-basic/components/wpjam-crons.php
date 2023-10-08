<?php
/*
Name: 定时作业
URI: https://mp.weixin.qq.com/s/mSqzZdslhxwkNHGRpa3WmA
Description: 定时作业让你可以可视化管理 WordPress 的定时作业
Version: 2.0
*/
class WPJAM_Cron extends WPJAM_Args{
	public function schedule(){
		if(is_null($this->callback)){
			$this->callback	= [$this, 'callback'];
		}

		if(is_callable($this->callback)){
			add_action($this->hook, $this->callback);

			if(!self::is_scheduled($this->hook)){
				$args	= $this->args['args'] ?? [];

				if($this->recurrence){
					wp_schedule_event($this->time, $this->recurrence, $this->hook, $args);
				}else{
					wp_schedule_single_event($this->time, $this->hook, $args);
				}
			}
		}
	}

	public function callback(){
		if(get_site_transient($this->hook.'_lock')){
			return;
		}

		set_site_transient($this->hook.'_lock', 1, 5);

		if($jobs = $this->get_jobs()){
			$callbacks	= array_column($jobs, 'callback');
			$total		= count($callbacks);
			$index		= get_transient($this->hook.'_index') ?: 0;
			$index		= $index >= $total ? 0 : $index;
			$callback	= $callbacks[$index];

			set_transient($this->hook.'_index', $index+1, DAY_IN_SECONDS);

			$this->increment();

			if(is_callable($callback)){
				call_user_func($callback);
			}else{
				trigger_error('invalid_job_callback'.var_export($callback, true));
			}
		}
	}

	public function get_jobs($jobs=null){
		if(is_null($jobs)){
			$jobs	= $this->jobs;

			if($jobs && is_callable($jobs)){
				$jobs	= call_user_func($jobs);
			}
		}

		$jobs	= $jobs ?: [];

		if(!$jobs || !$this->weight){
			return array_values($jobs);
		}

		$queue	= [];
		$next	= [];

		foreach($jobs as $job){
			if(is_object($job)){
				$job->weight	= $job->weight ?? 1;

				if($job->weight){
					$queue[]	= $job;

					if($job->weight > 1){
						$job->weight --;
						$next[]	= $job;
					}
				}
			}else{
				$queue[]	= $job;
			}
		}

		if($next){
			$queue	= array_merge($queue, $this->get_jobs($next));
		}

		return $queue;
	}

	public function get_counter($increment=false){
		$today		= wpjam_date('Y-m-d');
		$counter	= get_transient($this->hook.'_counter:'.$today) ?: 0;

		if($increment){
			$counter ++;
			set_transient($this->hook.'_counter:'.$today, $counter, DAY_IN_SECONDS);
		}

		return $counter;
	}

	public function increment(){
		return $this->get_counter(true);
	}

	public static function add_hooks(){
		add_action('init',	['WPJAM_Cron_Job', 'cron']);

		add_filter('cron_schedules',	[self::class, 'filter_schedules']);
	}

	public static function is_scheduled($hook) {	// 不用判断参数
		foreach(self::get_all() as $timestamp => $cron){
			if(isset($cron[$hook])){
				return true;
			}
		}

		return false;
	}

	public static function cleanup(){
		$invalid	= 0;

		foreach(_get_cron_array() as $timestamp => $wp_cron){
			foreach($wp_cron as $hook => $dings){
				if(!has_filter($hook)){			// 系统不存在的定时作业，清理掉
					foreach($dings as $key=>$data){
						wp_unschedule_event($timestamp, $hook, $data['args']);
					}

					$invalid++;
				}
			}
		}

		return $invalid;
	}

	public static function filter_schedules($schedules){
		return array_merge($schedules, [
			'five_minutes'		=> ['interval'=>300,	'display'=>'每5分钟一次'],
			'fifteen_minutes'	=> ['interval'=>900,	'display'=>'每15分钟一次'],
		]);
	}

	public static function create($name, $args){
		$object	= new self(wp_parse_args($args, [
			'hook'			=> $name,
			'recurrence'	=> '',
			'time'			=> time(),
			'args'			=> []
		]));

		wpjam_add_item('cron', $name, $object);

		$object->schedule();

		return $object;
	}

	public static function get($id){
		list($timestamp, $hook, $key)	= explode('--', $id);

		$wp_crons = self::get_all();

		if(isset($wp_crons[$timestamp][$hook][$key])){
			$data	= $wp_crons[$timestamp][$hook][$key];

			$data['hook']		= $hook;
			$data['timestamp']	= $timestamp;
			$data['time']		= wpjam_date('Y-m-d H:i:s', $timestamp);
			$data['cron_id']	= $id;
			$data['interval']	= $data['interval'] ?? 0;

			return $data;
		}

		return false;
	}

	public static function get_all(){
		return _get_cron_array() ?: [];
	}

	public static function insert($data){
		if(!has_filter($data['hook'])){
			wp_die('无效的 Hook');
		}

		$timestamp	= wpjam_strtotime($data['time']);

		if($data['interval']){
			wp_schedule_event($timestamp, $data['interval'], $data['hook'], $data['_args']);
		}else{
			wp_schedule_single_event($timestamp, $data['hook'], $data['_args']);
		}

		return true;
	}

	public static function do($id){
		$data	= self::get($id);

		if($data){
			wpjam_throw_if_error(do_action_ref_array($data['hook'], $data['args']));
		}

		return true;
	}

	public static function delete($id){
		$data = self::get($id);

		if($data){
			return wp_unschedule_event($data['timestamp'], $data['hook'], $data['args']);
		}

		return true;
	}

	public static function query_items($limit, $offset){
		$items	= [];

		foreach(self::get_all() as $timestamp => $wp_cron){
			foreach($wp_cron as $hook => $dings){
				foreach($dings as $key=>$data){
					if(!has_filter($hook)){
						wp_unschedule_event($timestamp, $hook, $data['args']);	// 系统不存在的定时作业，自动清理
						continue;
					}

					$items[] = [
						'cron_id'	=> $timestamp.'--'.$hook.'--'.$key,
						'time'		=> wpjam_date('Y-m-d H:i:s', $timestamp),
						'hook'		=> $hook,
						'interval'	=> $data['interval'] ?? 0
					];
				}
			}
		}

		return ['items'=>$items, 'total'=>count($items)];
	}

	public static function get_actions(){
		return [
			'add'		=> ['title'=>'新建',		'response'=>'list'],
			'do'		=> ['title'=>'立即执行',	'direct'=>true,	'confirm'=>true,	'bulk'=>2,		'response'=>'list'],
			'delete'	=> ['title'=>'删除',		'direct'=>true,	'confirm'=>true,	'bulk'=>true,	'response'=>'list']
		];
	}

	public static function get_fields($action_key='', $id=0){
		$schedule_options	= [0=>'只执行一次']+wp_list_pluck(wp_get_schedules(), 'display', 'interval');

		return [
			'hook'		=> ['title'=>'Hook',	'type'=>'text',		'show_admin_column'=>true],
			'time'		=> ['title'=>'运行时间',	'type'=>'text',		'show_admin_column'=>true,	'value'=>wpjam_date('Y-m-d H:i:s')],
			'interval'	=> ['title'=>'频率',		'type'=>'select',	'show_admin_column'=>true,	'options'=>$schedule_options],
		];
	}

	public static function get_list_table(){
		return [
			'plural'		=> 'crons',
			'singular'		=> 'cron',
			'model'			=> self::class,
			'primary_key'	=> 'cron_id',
		];
	}

	public static function get_tabs(){
		$tabs	= [];

		$tabs['crons']	= [
			'title'			=> '定时作业',
			'function'		=> 'list',
			'list_table'	=> self::class,
			'order'			=> 20,
		];

		$cron	= WPJAM_Cron_Job::cron();

		if($cron){
			$tabs['jobs'] = [
				'title'			=> '作业列表',
				'function'		=> 'list',
				'list_table'	=> 'WPJAM_Cron_Job',
				'summary'		=> '今天已经运行 <strong>'.$cron->get_counter().'</strong> 次'
			];
		}

		return $tabs;
	}
}

class WPJAM_Cron_Job extends WPJAM_Register{
	public static function get_objects(){
		$objects	= self::get_registereds();
		$day		= (wpjam_date('H') > 2 && wpjam_date('H') < 6) ? 0 : 1;

		foreach($objects as $name => $object){
			if($object->day != -1 && $object->day != $day){
				unset($objects[$name]);
			}
		}

		return $objects;
	}

	public static function create($name, $args=[]){
		if(is_numeric($args)){
			$args	= ['weight'	=> $args];
		}else{
			$args	= is_array($args) ? $args : [];
		}

		if(is_callable($name)){
			$args['callback']	= $name;

			if(is_object($name)){
				$name	= get_class($name);
			}elseif(is_array($name)){
				$name	= implode(':', $name);
			}
		}else{
			if(empty($args['callback']) || !is_callable($args['callback'])){
				return null;
			}
		}

		return self::register($name, wp_parse_args($args, ['weight'=>1, 'day'=>-1]));
	}

	public static function get_actions(){
		return [];
	}

	public static function get_fields($action_key='', $id=0){
		return [
			'function'	=> ['title'=>'回调函数',	'type'=>'view',	'show_admin_column'=>true],
			'weight'	=> ['title'=>'作业权重',	'type'=>'view',	'show_admin_column'=>true],
			'day'		=> ['title'=>'运行时间',	'type'=>'view',	'show_admin_column'=>true,	'options'=>['-1'=>'全天','1'=>'白天','0'=>'晚上']],
		];
	}

	public static function query_items($limit, $offset){
		$items	= [];

		foreach(self::get_registereds() as $name => $object){
			$item	= $object->to_array();

			if(is_array($item['callback'])){
				if(is_object($item['callback'][0])){
					$item['function']	= '<p>'.get_class($item['callback'][0]).'->'.(string)$item['callback'][1].'</p>';
				}else{
					$item['function']	= '<p>'.$item['callback'][0].'->'.(string)$item['callback'][1].'</p>';
				}
			}elseif(is_object($item['callback'])){
				$item['function']	= '<pre>'.print_r($item['callback'], true).'</pre>';
			}else{
				$item['function']	= wpautop($item['callback']);
			}

			$item['job_id']	= $name;
			$items[]		= $item;
		}
		
		return ['items'=>$items, 'total'=>count($items)];
	}

	public static function get_list_table(){
		return [
			'plural'		=> 'jobs',
			'singular'		=> 'job',
			'primary_key'	=> 'job_id',
			'model'			=> 'WPJAM_Cron_Job',
		];
	}

	public static function cron(){
		if(self::get_registereds()){
			$name	= 'wpjam_scheduled';

			return wpjam_get_cron($name) ?: wpjam_register_cron($name, [
				'recurrence'	=> 'five_minutes',
				'jobs'			=> [self::class, 'get_objects'],
				'weight'		=> true
			]);
		}
	}
}

function wpjam_register_cron($name, $args=[]){
	if(is_callable($name)){
		return wpjam_register_job($name, $args);
	}

	return WPJAM_Cron::create($name, $args);
}

function wpjam_get_cron($name){
	return wpjam_get_item('cron', $name);
}

function wpjam_register_job($name, $args=[]){
	return WPJAM_Cron_Job::create($name, $args);
}

function wpjam_is_scheduled_event($hook) {	// 不用判断参数
	return WPJAM_Cron::is_scheduled($hook);
}

wpjam_add_menu_page('wpjam-crons',	[
	'parent'		=> 'wpjam-basic',
	'menu_title'	=> '定时作业',
	'order'			=> 9,
	'summary'		=> __FILE__,
	'function'		=> 'tab',
	'network'		=> false,
	'tabs'			=> ['WPJAM_Cron', 'get_tabs'],
	'hooks'			=> ['WPJAM_Cron', 'add_hooks'],
]);