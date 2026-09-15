<?php
namespace vendor\advancedhide\migrations;

if (!defined('IN_PHPBB'))
{
	exit;
}

class v130_cron extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\vendor\advancedhide\migrations\v120_audit_bans'];
	}

	public function update_data()
	{
		return [
			['config.add', ['advancedhide_last_gc', 0]],
		];
	}

	public function revert_data()
	{
		return [
			['config.remove', ['advancedhide_last_gc']],
		];
	}
}