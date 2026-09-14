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
						'rl_window' => ['UINT', 0],
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
			['permission.add', ['m_hide_override', false]],
		];
	}
}