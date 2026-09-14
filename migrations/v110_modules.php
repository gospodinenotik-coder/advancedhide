<?php
namespace vendor\advancedhide\migrations;

if (!defined('IN_PHPBB'))
{
	exit;
}

class v110_modules extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\vendor\advancedhide\migrations\v100_initial'];
	}

	public function update_schema()
	{
		return [
			'change_columns' => [
				$this->table_prefix . 'advancedhide_rl' => [
					'rl_window' => ['UINT:11', 0],
				],
			],
		];
	}

	public function update_data()
	{
		return [
			['config.add', ['advancedhide_show_buttons', 1]],
			['config.add', ['advancedhide_enable_captcha', 0]],
			['config.add', ['advancedhide_rl_minute_limit', 5]],
			['config.add', ['advancedhide_rl_day_limit', 30]],

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
		];
	}
}