<?php
namespace vendor\advancedhide\cron;

if (!defined('IN_PHPBB'))
{
	exit;
}

use phpbb\config\config;
use phpbb\db\driver\driver_interface;

class tidy_logs extends \phpbb\cron\task\base
{
	protected $config;
	protected $db;
	protected $table_prefix;

	public function __construct(config $config, driver_interface $db, $table_prefix)
	{
		$this->config = $config;
		$this->db = $db;
		$this->table_prefix = $table_prefix;
	}

	public function is_runnable()
	{
		return true;
	}

	public function should_run()
	{
		$last_gc = (int)($this->config['advancedhide_last_gc'] ?? 0);
		return $last_gc < (time() - 86400);
	}

	public function run()
	{
		$threshold = time() - (30 * 86400); // 30 дней
		$table = $this->table_prefix . 'advancedhide_logs';
		$sql = 'DELETE FROM ' . $table . ' WHERE attempt_time < ' . (int)$threshold;
		$this->db->sql_query($sql);
		$this->config->set('advancedhide_last_gc', time(), true);
	}
}