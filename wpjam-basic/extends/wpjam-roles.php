<?php
/*
Name: 用户角色
URI: https://mp.weixin.qq.com/s/NOOjbhtg6l4YhXGYZ9lBWg
Description: 用户角色管理，以及用户额外权限设置。
Version: 1.0
*/
class WPJAM_Role{
	public static function get_all(){
		return $GLOBALS['wp_roles']->roles;
	}

	public static function get($role){
		$roles	= self::get_all();
		$data	= $roles[$role] ?? [];

		$user_counts	= count_users();
		$user_counts	= $user_counts['avail_roles'];

		if($data){
			$data['role']			= $role;
			$data['capabilities']	= array_keys($data['capabilities']);
			$data['cap_count']		= count($data['capabilities']);
			$data['user_count']		= isset($user_counts[$role]) ? '<a href="'.admin_url('users.php?role='.$role).'">'.$user_counts[$role].'</a>' : 0;
		}

		return $data;
	}

	public static function set($data, $role=''){
		$data['capabilities']	= array_filter($data['capabilities']);
		$data['capabilities']	= array_fill_keys($data['capabilities'], 1);

		if($role){
			remove_role($role);

			$label	= '修改';
		}else{
			$role	= $data['role'];
			$label	= '新建';
		}
		
		$result	= add_role($role, $data['name'], $data['capabilities']);

		return is_null($result) ? new WP_Error('error', $label.'失败，可能重名或者其他原因。') : $role;
	}

	public static function insert($data){
		return self::set($data);
	}

	public static function update($role, $data){
		return self::set($data, $role);
	}

	public static function delete($role){
		if($role == 'administrator'){
			wp_die('不能删除超级管理员角色。');
		}

		return remove_role($role);
	}

	public static function reset(){
		require_once ABSPATH . 'wp-admin/includes/schema.php';

		foreach(self::get_all() as $role => $data){
			remove_role($role);
		}

		populate_roles();
	}

	public static function query_items($args){
		$items	= [];

		$user_counts	= count_users();
		$user_counts	= $user_counts['avail_roles'];

		foreach(self::get_all() as $key => $role){
			$role['role']		= $key;
			$role['name']		= translate_user_role($role['name']);
			$role['user_count']	= isset($user_counts[$key]) ? '<a href="'.admin_url('users.php?role='.$key).'">'.$user_counts[$key].'</a>' : 0;
			$role['cap_count']	= count($role['capabilities']);

			$items[]	= $role;
		}

		return ['items'=>$items, 'totla'=>count($items)];
	}

	public static function capability_callback($user_id, $args){
		if(isset($args[0]) && $args[0] === 'administrator' && (empty($args[1]) || $args[1] == 'delete')){
			return ['do_not_allow'];
		}
		
		return is_multisite() ? ['manage_site'] : ['manage_options'];
	}

	public static function get_fields($action_key='', $id=0){
		return [
			'role'			=> ['title'=>'角色',		'type'=>'text',		'show_admin_column'=>true],
			'name'			=> ['title'=>'名称',		'type'=>'text',		'show_admin_column'=>true],
			'capabilities'	=> ['title'=>'权限',		'type'=>'mu-text'],
			'user_count'	=> ['title'=>'用户数',	'type'=>'view',		'show_admin_column'=>'only'],
			'cap_count'		=> ['title'=>'权限',		'type'=>'view',		'show_admin_column'=>'only'],
		];
	}

	public static function get_actions(){
		return [
			'add'		=> ['title'=>'新建',	'last'=>true],
			'edit'		=> ['title'=>'编辑'],
			'delete'	=> ['title'=>'删除',	'direct'=>true,	'confirm'=>true,	'bulk'=>true],
			'reset'		=> ['title'=>'重置',	'direct'=>true,	'confirm'=>true,	'overall'=>true]
		];
	}

	public static function get_additional_capabilities($user){
		$caps	= [];

		foreach($user->caps as $cap => $value){
			if($value && !$GLOBALS['wp_roles']->is_role($cap)){
				$caps[]	= $cap;
			}
		}

		return $caps;
	}

	public static function set_additional_capabilities($user, $caps){
		$caps		= array_diff($caps, ['manage_sites', 'manage_options']);
		$current	= self::get_additional_capabilities($user);
		$removed	= array_diff($current, $caps);
		$added		= array_diff($caps, $current);

		if($removed){
			foreach($removed as $cap){
				$user->remove_cap($cap);
			}
		}

		if($added){
			foreach($added as $cap){
				$user->add_cap($cap);
			}
		}

		return $caps;
	}

	public static function get_list_table(){
		return [
			'singular'		=> 'wpjam-role',
			'plural'		=> 'wpjam-roles',
			'primary_key'	=> 'role',
			'capability'	=> 'edit_roles',
			'model'			=> self::class,
		];
	}

	public static function on_user_profile($profileuser){
		$caps	= self::get_additional_capabilities($profileuser);

		echo '<h3>额外权限</h3>';

		echo wpjam_fields(['capabilities'=> ['title'=>'权限',	'type'=>'mu-text',	'value'=>$caps]]);
	}

	public static function on_user_profile_update($user_id){
		$user	= get_userdata($user_id);
		$caps	= wpjam_get_post_parameter('capabilities',	['default'=>[]]);
		$caps	= array_diff($caps, ['manage_sites', 'manage_options']);

		self::set_additional_capabilities($user, $caps);
	}

	public static function builtin_page_load($screen_base){
		add_filter('additional_capabilities_display', '__return_false' );

		$capability	= is_multisite() ? 'manage_sites' : 'manage_options';

		if(current_user_can($capability)){
			add_action('show_user_profile',			[self::class, 'on_user_profile']);
			add_action('edit_user_profile',			[self::class, 'on_user_profile']);
			add_action('personal_options_update',	[self::class, 'on_user_profile_update']);
			add_action('edit_user_profile_update',	[self::class, 'on_user_profile_update']);
		}
	}
}

function wpjam_get_additional_capabilities($user){
	return WPJAM_Role::get_additional_capabilities($user);
}

function wpjam_set_additional_capabilities($user, $caps){
	return WPJAM_Role::set_additional_capabilities($user, $caps);
}

if(is_admin()){
	wpjam_add_menu_page('roles', [
		'parent'		=> 'users',
		'menu_title'	=> '角色管理',
		'order'			=> 8,
		'function'		=> 'list',
		'list_table'	=> 'WPJAM_Role',
		'capability'	=> 'edit_roles',
		'map_meta_cap'	=> ['WPJAM_Role', 'capability_callback']
	]);

	wpjam_add_admin_load([
		'base'	=> ['user-edit', 'profile'], 
		'model'	=> 'WPJAM_Role' 
	]);
}