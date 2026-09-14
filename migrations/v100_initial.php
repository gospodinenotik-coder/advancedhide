<?php
namespace vendor\advancedhide\migrations;

if (!defined('IN_PHPBB'))
{
	exit;
}

class v100_initial extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\phpbb\db\migration\data\v330\v330'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				$this->table_prefix . 'advancedhide_rl' => [
					'COLUMNS' => [
						'rl_key'    => ['VCHAR:64', ''],
						'rl_window' => ['UINT:11', 0],
						'rl_count'  => ['UINT', 0],
					],
					'PRIMARY_KEY' => ['rl_key', 'rl_window'],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [$this->table_prefix . 'advancedhide_rl'],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['advancedhide_author_override', 1]],
			['config.add', ['advancedhide_thanks_table', '']],
			['config.add', ['advancedhide_max_blocks', 20]],
			['config.add', ['advancedhide_show_buttons', 1]],
			['config.add', ['advancedhide_enable_captcha', 0]],
			['config.add', ['advancedhide_rl_minute_limit', 5]],
			['config.add', ['advancedhide_rl_day_limit', 30]],

			// Статусы модулей (1 - включен, 0 - отключен)
			['config.add', ['advancedhide_mod_guest', 1]],
			['config.add', ['advancedhide_mod_posts', 1]],
			['config.add', ['advancedhide_mod_days', 1]],
			['config.add', ['advancedhide_mod_time', 1]],
			['config.add', ['advancedhide_mod_regdate', 1]],
			['config.add', ['advancedhide_mod_reply', 1]],
			['config.add', ['advancedhide_mod_thanks', 1]],
			['config.add', ['advancedhide_mod_groups', 1]],
			['config.add', ['advancedhide_mod_users', 1]],
			['config.add', ['advancedhide_mod_pass', 1]],

			// Иконки кнопок BBCode редактора (FontAwesome)
			['config.add', ['advancedhide_icon_guest', 'fa-eye-slash']],
			['config.add', ['advancedhide_icon_posts', 'fa-comments']],
			['config.add', ['advancedhide_icon_days', 'fa-calendar']],
			['config.add', ['advancedhide_icon_time', 'fa-clock-o']],
			['config.add', ['advancedhide_icon_regdate', 'fa-history']],
			['config.add', ['advancedhide_icon_reply', 'fa-reply']],
			['config.add', ['advancedhide_icon_thanks', 'fa-thumbs-up']],
			['config.add', ['advancedhide_icon_groups', 'fa-users']],
			['config.add', ['advancedhide_icon_users', 'fa-user']],
			['config.add', ['advancedhide_icon_pass', 'fa-key']],

			['permission.add', ['m_hide_override', false]],
			['permission.permission_set', ['ROLE_MOD_FULL', 'm_hide_override']],
			['permission.permission_set', ['ROLE_ADMIN_FULL', 'm_hide_override']],
			['module.add', [
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_ADVANCEDHIDE_TITLE'
			]],
			['module.add', [
				'acp',
				'ACP_ADVANCEDHIDE_TITLE',
				[
					'module_basename' => '\vendor\advancedhide\acp\main_module',
					'modes'           => ['settings'],
				]
			]],
		];
	}
}