<?php
namespace gospodinenotik\advancedhide\migrations;

if (!defined('IN_PHPBB'))
{
	exit;
}

class v120_audit_bans extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\gospodinenotik\advancedhide\migrations\v110_modules'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'advancedhide_logs' => [
					'COLUMNS' => [
						'log_id'           => ['UINT', null, 'auto_increment'],
						'post_id'          => ['UINT', 0],
						'block_index'      => ['UINT', 0],
						'user_id'          => ['UINT', 0],
						'user_ip'          => ['VCHAR:40', ''],
						'attempt_time'     => ['UINT:11', 0],
						'status'           => ['VCHAR:32', ''],
						'masked_pass'      => ['VCHAR:64', ''],
						'password_used'    => ['VCHAR:64', ''],
						'attempt_count'    => ['UINT', 1],
						'aggregated_count' => ['UINT', 1],
						'details_json'     => ['TEXT_UNI', ''],
					],
					'PRIMARY_KEY' => 'log_id',
					'KEYS' => [
						'post_blk'         => ['INDEX', ['post_id', 'block_index']],
						'user_ip_idx'      => ['INDEX', ['user_id', 'user_ip']],
						'attempt_time_idx' => ['INDEX', ['attempt_time']],
					],
				],
				$this->table_prefix . 'advancedhide_bans' => [
					'COLUMNS' => [
						'ban_id'        => ['UINT', null, 'auto_increment'],
						'post_id'       => ['UINT', 0],
						'block_index'   => ['UINT', 0],
						'user_id'       => ['UINT', 0],
						'banned_by'     => ['UINT', 0],
						'ban_start'     => ['UINT:11', 0],
						'ban_end'       => ['UINT:11', 0],
						'ban_reason'    => ['TEXT_UNI', ''],
						'appeal_status' => ['VCHAR:32', ''],
						'appeal_reason' => ['TEXT_UNI', ''],
						'appeal_time'   => ['UINT:11', 0],
					],
					'PRIMARY_KEY' => 'ban_id',
					'KEYS' => [
						'blk_user' => ['INDEX', ['post_id', 'block_index', 'user_id']],
					],
				],
				$this->table_prefix . 'advancedhide_reports' => [
					'COLUMNS' => [
						'report_id'     => ['UINT', null, 'auto_increment'],
						'post_id'       => ['UINT', 0],
						'block_index'   => ['UINT', 0],
						'reporter_id'   => ['UINT', 0],
						'report_time'   => ['UINT:11', 0],
						'report_reason' => ['TEXT_UNI', ''],
						'report_status' => ['VCHAR:32', 'open'],
						'report_closed' => ['BOOL', 0],
					],
					'PRIMARY_KEY' => 'report_id',
					'KEYS' => [
						'post_blk_rep' => ['INDEX', ['post_id', 'block_index']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [
				$this->table_prefix . 'advancedhide_logs',
				$this->table_prefix . 'advancedhide_bans',
				$this->table_prefix . 'advancedhide_reports',
			],
		];
	}

	public function update_data()
	{
		return [
			// Негативные модули
			['config.add', ['advancedhide_mod_not_groups', 1]],
			['config.add', ['advancedhide_mod_not_users', 1]],
			['config.add', ['advancedhide_icon_not_groups', 'fa-user-times']],
			['config.add', ['advancedhide_icon_not_users', 'fa-ban']],

			// Новые права доступа
			['permission.add', ['u_hide_use', true]],
			['permission.add', ['u_hide_pass', true]],
			['permission.add', ['f_hide_post', false]],
			['permission.add', ['m_hide_ban', false]],

			// Назначение стандартным ролям
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_hide_use']],
			['permission.permission_set', ['ROLE_USER_FULL', 'u_hide_use']],
			['permission.permission_set', ['ROLE_USER_STANDARD', 'u_hide_pass']],
			['permission.permission_set', ['ROLE_USER_FULL', 'u_hide_pass']],
			['permission.permission_set', ['ROLE_FORUM_STANDARD', 'f_hide_post']],
			['permission.permission_set', ['ROLE_FORUM_FULL', 'f_hide_post']],
			['permission.permission_set', ['ROLE_FORUM_ON_FULL', 'f_hide_post']],
			['permission.permission_set', ['ROLE_MOD_FULL', 'm_hide_ban']],
			['permission.permission_set', ['ROLE_ADMIN_FULL', 'm_hide_ban']],

			// ACP Модуль аудита и блокировок
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				[
					'module_basename' => '\gospodinenotik\advancedhide\acp\main_module',
					'modes'           => ['audit'],
				]
			]],
		];
	}
}