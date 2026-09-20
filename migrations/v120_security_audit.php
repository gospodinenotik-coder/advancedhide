<?php
namespace vendor\advancedhide\migrations;

if (!defined('IN_PHPBB'))
{
	exit;
}

class v120_security_audit extends \phpbb\db\migration\migration
{
	public static function depends_on()
	{
		return ['\vendor\advancedhide\migrations\v110_modules'];
	}

	public function update_schema()
	{
		return [
			'add_tables' => [
				// Таблица журналов аудита безопасности (Sorokin Action Journal)
				$this->table_prefix . 'advancedhide_audit_log' => [
					'COLUMNS' => [
						'log_id'       => ['UINT:auto', null],
						'log_time'     => ['TIMESTAMP', 0],
						'user_id'      => ['UINT', 0],
						'user_ip'      => ['VCHAR:45', ''],
						'action_type'  => ['VCHAR:50', ''], // unlock_attempt, unlock_success, emergency_trigger, etc.
						'post_id'      => ['UINT', 0],
						'block_index'  => ['UINT', 0],
						'result'       => ['VCHAR:20', ''], // success, failure, rate_limited
						'details'      => ['TEXT_UNI', ''], // JSON с дополнительной информацией
					],
					'PRIMARY_KEY' => ['log_id'],
					'KEYS' => [
						'user_id'    => ['INDEX', ['user_id']],
						'log_time'   => ['INDEX', ['log_time']],
						'post_id'    => ['INDEX', ['post_id']],
						'action_type'=> ['INDEX', ['action_type']],
					],
				],
			],
		];
	}

	public function revert_schema()
	{
		return [
			'drop_tables' => [$this->table_prefix . 'advancedhide_audit_log'],
		];
	}

	public function update_data()
	{
		return [
			// Конфигурация Emergency Kill-Switch
			['config.add', ['advancedhide_emergency_shutdown', 0]],
			['config.add', ['advancedhide_emergency_reason', '']],
			['config.add', ['advancedhide_emergency_trigger_time', 0]],
			['config.add', ['advancedhide_emergency_trigger_user', 0]],
			
			// Конфигурация Cron-очистки
			['config.add', ['advancedhide_cron_last_gc', 0]],
			['config.add', ['advancedhide_cron_gc_interval', 86400]], // раз в сутки по умолчанию
			
			// Настройки для MCP интеграции
			['config.add', ['advancedhide_mcp_enabled', 1]],
			
			// Продуктовые метрики (анонимизированные)
			['config.add', ['advancedhide_metrics_enabled', 0]],
			['config.add', ['advancedhide_metrics_unlocks_total', 0]],
			['config.add', ['advancedhide_metrics_locks_total', 0]],
			['config.add', ['advancedhide_metrics_last_reset', time()]],
		];
	}
}
