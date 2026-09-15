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
}