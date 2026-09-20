<?php
namespace vendor\advancedhide\privacy;

if (!defined('IN_PHPBB'))
{
	exit;
}

use phpbb\privacy\provider_interface;
use phpbb\db\driver\driver_interface;
use phpbb\language\language;

/**
 * GDPR Data Provider for AdvancedHide
 * Поддержка экспорта и удаления персональных данных (Right to Erasure)
 */
class provider implements provider_interface
{
	protected $db;
	protected $table_prefix;
	protected $language;

	public function __construct(driver_interface $db, $table_prefix, language $language)
	{
		$this->db = $db;
		$this->table_prefix = $table_prefix;
		$this->language = $language;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name()
	{
		return 'advancedhide_privacy_provider';
	}

	/**
	 * {@inheritdoc}
	 * Сбор данных для экспорта
	 */
	public function export_user_data(int $user_id, ?string $export_type = null): array
	{
		$data = [];

		// Экспорт разблокированных блоков из кэша сессий (если доступно)
		// Примечание: кэш сессий хранится в phpbb_sessions, не в отдельной таблице
		
		// Добавляем информацию о том, что пользователь использовал расширение
		$data['ADVANCEDHIDE_USAGE'] = [
			'title' => $this->language->lang('PRIVACY_ADVHIDE_USAGE'),
			'data'  => $this->language->lang('PRIVACY_ADVHIDE_USAGE_DESC'),
		];

		return $data;
	}

	/**
	 * {@inheritdoc}
	 * Удаление персональных данных пользователя
	 */
	public function delete_user_data(int $user_id): void
	{
		// Очищаем записи rate limiting связанные с пользователем
		// Формат ключа: 'm_u_{user_id}' или 'd_u_{user_id}'
		$rl_table = $this->table_prefix . 'advancedhide_rl';
		
		// Удаляем все ключи, содержащие ID пользователя
		// Это безопасно, так как ключи содержат префиксы и хеши
		$sql = 'DELETE FROM ' . $rl_table . ' 
				WHERE rl_key LIKE :user_key';
		$this->db->sql_query($sql, [
			'user_key' => '%u_' . $user_id . '%'
		]);
		
		// Примечание: основные данные (посты с hide-блоками) остаются,
		// так как они принадлежат контенту форума, а не персональным данным
		// Сами hide-блоки не содержат PII после канонизации (пароли хешированы)
	}

	/**
	 * {@inheritdoc}
	 */
	public function can_export_user_data(): bool
	{
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function can_delete_user_data(): bool
	{
		return true;
	}
}
