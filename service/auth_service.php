<?php
namespace vendor\advancedhide\service;

if (!defined('IN_PHPBB'))
{
	exit;
}

use phpbb\config\config;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\db\tools\tools_interface;
use phpbb\language\language;
use phpbb\cache\driver\driver_interface as cache_interface;

class auth_service
{
	protected $config;
	protected $user;
	protected $auth;
	protected $db;
	protected $db_tools;
	protected $language;
	protected $cache;
	protected $table_prefix;

	protected static $thanks_table_exists = null;
	protected static $user_groups_cache = [];

	public function __construct(config $config, user $user, auth $auth, driver_interface $db, tools_interface $db_tools, language $language, cache_interface $cache, $table_prefix)
	{
		$this->config = $config;
		$this->user = $user;
		$this->auth = $auth;
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->language = $language;
		$this->cache = $cache;
		$this->table_prefix = $table_prefix;
	}

	public function is_block_unlocked($post_id, hide_block $block)
	{
		$cache_key = '_advhide_unlock_' . $this->user->data['session_id'];
		$unlocked = $this->cache->get($cache_key) ?: [];

		if (empty($unlocked[$post_id][$block->block_index])) {
			return false;
		}

		$stored_hash = $unlocked[$post_id][$block->block_index];
		return hash_equals($stored_hash, $block->block_hash);
	}

	public function unlock_block($post_id, hide_block $block)
	{
		$cache_key = '_advhide_unlock_' . $this->user->data['session_id'];
		$unlocked = $this->cache->get($cache_key) ?: [];
		$unlocked[$post_id][$block->block_index] = $block->block_hash;
		$this->cache->put($cache_key, $unlocked, 1800);
	}

	const RL_MINUTE_LIMIT = 5;
	const RL_DAY_LIMIT = 30;

	public function consume_rate_limit($identity)
	{
		$minute_window = (int) floor(time() / 60);
		$day_window = (int) floor(time() / 86400);

		$ident_prefix = substr($identity, 0, 62);

		$minute_ok = $this->rl_consume('m_' . $ident_prefix, $minute_window, self::RL_MINUTE_LIMIT);
		if (!$minute_ok) {
			return false;
		}

		$day_ok = $this->rl_consume('d_' . $ident_prefix, $day_window, self::RL_DAY_LIMIT);
		if (!$day_ok) {
			return false;
		}

		if (mt_rand(1, 200) === 1) {
			$this->rl_gc($minute_window, $day_window);
		}

		return true;
	}

	protected function rl_consume($key, $window, $limit)
	{
		$table = $this->table_prefix . 'advancedhide_rl';
		$safe_key = $this->db->sql_escape($key);

		$sql = 'UPDATE ' . $table .
			' SET rl_count = rl_count + 1' .
			' WHERE rl_key = \'' . $safe_key . '\'' .
			' AND rl_window = ' . (int) $window .
			' AND rl_count < ' . (int) $limit;
		$this->db->sql_query($sql);
		if ($this->db->sql_affectedrows() > 0) {
			return true;
		}

		$this->db->sql_return_on_error(true);
		$sql = 'INSERT INTO ' . $table . ' (rl_key, rl_window, rl_count) VALUES (\'' . $safe_key . '\', ' . (int) $window . ', 1)';
		$inserted = $this->db->sql_query($sql);
		$this->db->sql_return_on_error(false);
		if ($inserted) {
			return true;
		}

		$sql = 'UPDATE ' . $table .
			' SET rl_count = rl_count + 1' .
			' WHERE rl_key = \'' . $safe_key . '\'' .
			' AND rl_window = ' . (int) $window .
			' AND rl_count < ' . (int) $limit;
		$this->db->sql_query($sql);
		return $this->db->sql_affectedrows() > 0;
	}

	protected function rl_gc($minute_window, $day_window)
	{
		$table = $this->table_prefix . 'advancedhide_rl';
		$this->db->sql_query('DELETE FROM ' . $table . ' WHERE rl_key LIKE \'m%\' AND rl_window < ' . ($minute_window - 5));
		$this->db->sql_query('DELETE FROM ' . $table . ' WHERE rl_key LIKE \'d%\' AND rl_window < ' . ($day_window - 3));
	}

	public function evaluate_block(hide_block $block, array $context = [])
	{
		$forum_id  = isset($context['forum_id']) ? (int)$context['forum_id'] : 0;
		$topic_id  = isset($context['topic_id']) ? (int)$context['topic_id'] : 0;
		$post_id   = isset($context['post_id'])  ? (int)$context['post_id']  : 0;
		$poster_id = isset($context['poster_id']) ? (int)$context['poster_id'] : 0;
		$password_verified = !empty($context['password_verified']);

		$viewer_id = (int)$this->user->data['user_id'];
		$is_registered = ($this->user->data['is_registered'] && !$this->user->data['is_bot']);

		$mod_override = $forum_id > 0 ? (bool)$this->auth->acl_get('m_hide_override', $forum_id) : (bool)$this->auth->acl_get('m_hide_override');
		if ($mod_override) {
			return ['can_view' => true, 'failed_conditions' => [], 'override' => 'mod'];
		}

		if ($this->config['advancedhide_author_override'] && $poster_id > 0 && $viewer_id === $poster_id && $is_registered) {
			return ['can_view' => true, 'failed_conditions' => [], 'override' => 'author'];
		}

		$can_view = true;
		$failed_conditions = [];
		$now = time();

		foreach ($block->normalized_conditions as $cond) {
			$type = $cond['type'];
			$args = $cond['args'];

			switch ($type) {
				case 'guest':
					if (!$is_registered) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_GUEST_FAILED');
					}
					break;
				case 'posts':
					$req = (int)($args[0] ?? 0);
					if (!$is_registered || (int)$this->user->data['user_posts'] < $req) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_POSTS_FAILED', $req, (int)$this->user->data['user_posts']);
					}
					break;
				case 'days':
					$req = (int)($args[0] ?? 0);
					$user_days = floor(($now - (int)$this->user->data['user_regdate']) / 86400);
					if (!$is_registered || $user_days < $req) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_DAYS_FAILED', $req, max(0, $user_days));
					}
					break;
				case 'regdate':
					$target_ts = strtotime(($args[0] ?? '') . ' 23:59:59 UTC');
					if (!$is_registered || $target_ts === false || (int)$this->user->data['user_regdate'] > $target_ts) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_REGDATE_FAILED', $args[0] ?? '');
					}
					break;
				case 'time':
					$time_arg = implode(',', $args);
					$target_ts = is_numeric($time_arg) ? (int)$time_arg : strtotime($time_arg . ' UTC');
					if ($target_ts === false || $now < $target_ts) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_TIME_FAILED', date('Y-m-d H:i:s UTC', $target_ts ?: 0));
					}
					break;
				case 'reply':
					if (!$is_registered || !$this->check_replied($topic_id, $viewer_id)) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_REPLY_FAILED');
					}
					break;
				case 'thanks':
					if (!$is_registered || !$this->check_thanked($post_id, $viewer_id)) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_THANKS_FAILED');
					}
					break;
				case 'groups':
					$gids = array_map('intval', $args);
					if (!$is_registered || !$this->check_groups($viewer_id, $gids)) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_GROUPS_FAILED');
					}
					break;
				case 'users':
					$uids = array_map('intval', $args);
					if (!$is_registered || !in_array($viewer_id, $uids, true)) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_USERS_FAILED');
					}
					break;
				case 'pass':
					if (!$password_verified && !$this->is_block_unlocked($post_id, $block)) {
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_PASS_REQUIRED');
					}
					break;
				case 'pass_limit_exceeded':
					$can_view = false;
					$failed_conditions[] = $this->language->lang('HIDE_PASS_LIMIT_EXCEEDED');
					break;
				default:
					$can_view = false;
					$failed_conditions[] = $this->language->lang('HIDE_COND_UNKNOWN');
					break;
			}
		}

		return [
			'can_view' => $can_view,
			'failed_conditions' => $failed_conditions,
			'override' => false
		];
	}

	protected function check_replied($topic_id, $user_id)
	{
		if ($topic_id <= 0 || $user_id <= 0) return false;
		$sql = 'SELECT 1 FROM ' . POSTS_TABLE . ' WHERE topic_id = ' . (int)$topic_id . ' AND poster_id = ' . (int)$user_id . ' AND post_visibility = 1';
		$res = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);
		return !empty($row);
	}

	protected function check_thanked($post_id, $user_id)
	{
		if ($post_id <= 0 || $user_id <= 0) return false;

		$tbl_cfg = $this->config['advancedhide_thanks_table'];
		$tbl = !empty($tbl_cfg) ? $tbl_cfg : ($this->table_prefix . 'thanks');
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $tbl)) {
			$tbl = $this->table_prefix . 'thanks';
		}

		if (self::$thanks_table_exists === null) {
			self::$thanks_table_exists = $this->db_tools->sql_table_exists($tbl);
		}

		if (!self::$thanks_table_exists) {
			return false;
		}

		$sql = 'SELECT 1 FROM ' . $tbl . ' WHERE post_id = ' . (int)$post_id . ' AND user_id = ' . (int)$user_id;
		$res = $this->db->sql_query_limit($sql, 1);
		$row = $res ? $this->db->sql_fetchrow($res) : false;
		if ($res) $this->db->sql_freeresult($res);
		return !empty($row);
	}

	protected function check_groups($user_id, array $gids)
	{
		if ($user_id <= 0) return false;

		if (!isset(self::$user_groups_cache[$user_id])) {
			$user_gids = [(int)$this->user->data['group_id']];
			$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . ' WHERE user_id = ' . (int)$user_id . ' AND user_pending = 0';
			$res = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($res)) {
				$user_gids[] = (int)$row['group_id'];
			}
			$this->db->sql_freeresult($res);
			self::$user_groups_cache[$user_id] = array_unique($user_gids);
		}

		return (bool)array_intersect($gids, self::$user_groups_cache[$user_id]);
	}
}