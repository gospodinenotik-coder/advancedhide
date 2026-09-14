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
use phpbb\captcha\factory as captcha_factory;

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
	protected $captcha_factory;
	protected $phpbb_root_path;
	protected $php_ext;

	protected static $thanks_table_exists = null;
	protected static $user_groups_cache = [];
	protected static $schema_checked = false;

	public function __construct(config $config, user $user, auth $auth, driver_interface $db, tools_interface $db_tools, language $language, cache_interface $cache, $table_prefix, captcha_factory $captcha_factory, $phpbb_root_path, $php_ext)
	{
		$this->config = $config;
		$this->user = $user;
		$this->auth = $auth;
		$this->db = $db;
		$this->db_tools = $db_tools;
		$this->language = $language;
		$this->cache = $cache;
		$this->table_prefix = $table_prefix;
		$this->captcha_factory = $captcha_factory;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function is_module_enabled($type)
	{
		$key = 'advancedhide_mod_' . strtolower(trim($type));
		if (isset($this->config[$key]))
		{
			return (bool)$this->config[$key];
		}
		return true;
	}

	public function get_module_title($type)
	{
		$key = 'ADVHIDE_MOD_' . strtoupper(trim($type));
		return $this->language->is_set($key) ? $this->language->lang($key) : $type;
	}

	public function generate_captcha_html()
	{
		if (empty($this->config['advancedhide_enable_captcha']))
		{
			return '';
		}

		$plugin_name = $this->config['captcha_plugin'];
		if (empty($plugin_name))
		{
			return '';
		}

		try
		{
			$captcha = $this->captcha_factory->get_instance($plugin_name);
			if (!$captcha->is_available())
			{
				return '';
			}

			$captcha->init(CONFIRM_POST);

			// 1. Q&A Captcha
			if ($captcha instanceof \phpbb\captcha\plugins\qa)
			{
				$q_text = $captcha->question_text;
				$c_id   = $captcha->confirm_id;
				return '<div class="advhide-captcha-box advhide-captcha-qa">' .
					'<label class="advhide-captcha-q"><strong>' . htmlspecialchars($q_text, ENT_QUOTES, 'UTF-8') . '</strong></label>' .
					'<input type="hidden" name="qa_confirm_id" value="' . htmlspecialchars($c_id, ENT_QUOTES, 'UTF-8') . '" />' .
					'<input type="text" name="qa_answer" class="inputbox autowidth advhide-captcha-input" placeholder="' . htmlspecialchars($this->language->lang('HIDE_CAPTCHA_QA_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '" autocomplete="off" />' .
				'</div>';
			}

			// 2. reCAPTCHA v2
			if ($captcha instanceof \phpbb\captcha\plugins\recaptcha)
			{
				$sitekey = $this->config['recaptcha_sitekey'] ?? '';
				return '<div class="advhide-captcha-box advhide-captcha-recaptcha">' .
					'<script src="https://www.google.com/recaptcha/api.js" async defer></script>' .
					'<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($sitekey, ENT_QUOTES, 'UTF-8') . '"></div>' .
				'</div>';
			}

			// 3. reCAPTCHA v3
			if ($captcha instanceof \phpbb\captcha\plugins\recaptcha_v3)
			{
				$sitekey = $this->config['recaptcha_v3_sitekey'] ?? '';
				return '<div class="advhide-captcha-box advhide-captcha-recaptcha-v3">' .
					'<script src="https://www.google.com/recaptcha/api.js?render=' . urlencode($sitekey) . '"></script>' .
					'<input type="hidden" name="g-recaptcha-response" class="advhide-g-recaptcha-v3-token" />' .
					'<script>if (typeof grecaptcha !== "undefined") { grecaptcha.ready(function() { grecaptcha.execute("' . htmlspecialchars($sitekey, ENT_QUOTES, 'UTF-8') . '", {action: "advancedhide_unlock"}).then(function(token) { $(".advhide-g-recaptcha-v3-token").val(token); }); }); }</script>' .
				'</div>';
			}

			// 4. GD / GD Wave / Nogd (Image Captchas)
			if ($captcha instanceof \phpbb\captcha\plugins\captcha_abstract)
			{
				$c_id = $captcha->confirm_id;
				$img_url = append_sid($this->phpbb_root_path . 'ucp.' . $this->php_ext, 'mode=confirm&confirm_id=' . $c_id . '&type=' . CONFIRM_POST);
				return '<div class="advhide-captcha-box advhide-captcha-gd">' .
					'<div class="advhide-captcha-img"><img src="' . $img_url . '" alt="" /></div>' .
					'<input type="hidden" name="confirm_id" value="' . htmlspecialchars($c_id, ENT_QUOTES, 'UTF-8') . '" />' .
					'<input type="text" name="confirm_code" class="inputbox autowidth advhide-captcha-input" placeholder="' . htmlspecialchars($this->language->lang('HIDE_CAPTCHA_CODE_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '" autocomplete="off" />' .
				'</div>';
			}
		}
		catch (\Exception $e)
		{
			return '';
		}

		return '';
	}

	protected function ensure_schema()
	{
		if (self::$schema_checked)
		{
			return;
		}
		self::$schema_checked = true;

		$table = $this->table_prefix . 'advancedhide_rl';
		try
		{
			$this->db_tools->sql_column_change($table, 'rl_window', ['UINT:11', 0]);
		}
		catch (\Exception $e)
		{
		}
	}

	public function is_block_unlocked($post_id, hide_block $block)
	{
		$cache_key = '_advhide_unlock_' . $this->user->data['session_id'];
		$unlocked = $this->cache->get($cache_key) ?: [];

		if (empty($unlocked[$post_id][$block->block_index]))
		{
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

	public function consume_rate_limit($identity)
	{
		$minute_window = (int) floor(time() / 60);
		$day_window = (int) floor(time() / 86400);

		$ident_prefix = substr($identity, 0, 62);
		$minute_limit = max(1, (int)($this->config['advancedhide_rl_minute_limit'] ?? 5));
		$day_limit    = max(1, (int)($this->config['advancedhide_rl_day_limit'] ?? 30));

		$minute_ok = $this->rl_consume('m_' . $ident_prefix, $minute_window, $minute_limit);
		if (!$minute_ok)
		{
			return false;
		}

		$day_ok = $this->rl_consume('d_' . $ident_prefix, $day_window, $day_limit);
		if (!$day_ok)
		{
			return false;
		}

		if (mt_rand(1, 200) === 1)
		{
			$this->rl_gc($minute_window, $day_window);
		}

		return true;
	}

	protected function rl_consume($key, $window, $limit)
	{
		$this->ensure_schema();

		$table = $this->table_prefix . 'advancedhide_rl';
		$safe_key = $this->db->sql_escape($key);

		$sql = 'UPDATE ' . $table .
			' SET rl_count = rl_count + 1' .
			' WHERE rl_key = \'' . $safe_key . '\'' .
			' AND rl_window = ' . (int) $window .
			' AND rl_count < ' . (int) $limit;
		$this->db->sql_query($sql);
		if ($this->db->sql_affectedrows() > 0)
		{
			return true;
		}

		$this->db->sql_return_on_error(true);
		$sql = 'INSERT INTO ' . $table . ' (rl_key, rl_window, rl_count) VALUES (\'' . $safe_key . '\', ' . (int) $window . ', 1)';
		$inserted = $this->db->sql_query($sql);
		$this->db->sql_return_on_error(false);
		if ($inserted)
		{
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
		if ($mod_override)
		{
			return ['can_view' => true, 'failed_conditions' => [], 'override' => 'mod', 'has_disabled_module' => false];
		}

		if ($this->config['advancedhide_author_override'] && $poster_id > 0 && $viewer_id === $poster_id && $is_registered)
		{
			return ['can_view' => true, 'failed_conditions' => [], 'override' => 'author', 'has_disabled_module' => false];
		}

		$can_view = true;
		$failed_conditions = [];
		$has_disabled_module = false;
		$now = time();

		foreach ($block->normalized_conditions as $cond)
		{
			$type = $cond['type'];
			$args = $cond['args'];

			// Проверка отключения модуля в ACP
			if (!$this->is_module_enabled($type))
			{
				$can_view = false;
				$has_disabled_module = true;
				$failed_conditions[] = $this->language->lang('HIDE_COND_MODULE_DISABLED', $this->get_module_title($type));
				continue;
			}

			switch ($type)
			{
				case 'guest':
					if (!$is_registered)
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_GUEST_FAILED');
					}
					break;
				case 'posts':
					$req = (int)($args[0] ?? 0);
					if (!$is_registered || (int)$this->user->data['user_posts'] < $req)
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_POSTS_FAILED', $req, (int)$this->user->data['user_posts']);
					}
					break;
				case 'days':
					$req = (int)($args[0] ?? 0);
					$user_days = floor(($now - (int)$this->user->data['user_regdate']) / 86400);
					if (!$is_registered || $user_days < $req)
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_DAYS_FAILED', $req, max(0, $user_days));
					}
					break;
				case 'regdate':
					$target_ts = strtotime(($args[0] ?? '') . ' 23:59:59 UTC');
					if (!$is_registered || $target_ts === false || (int)$this->user->data['user_regdate'] > $target_ts)
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_REGDATE_FAILED', $args[0] ?? '');
					}
					break;
				case 'time':
					$time_arg = implode(',', $args);
					$target_ts = is_numeric($time_arg) ? (int)$time_arg : strtotime($time_arg . ' UTC');
					if ($target_ts === false || $now < $target_ts)
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_TIME_FAILED', date('Y-m-d H:i:s UTC', $target_ts ?: 0));
					}
					break;
				case 'reply':
					if (!$is_registered || !$this->check_replied($topic_id, $viewer_id))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_REPLY_FAILED');
					}
					break;
				case 'thanks':
					if (!$is_registered || !$this->check_thanked($post_id, $viewer_id))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_THANKS_FAILED');
					}
					break;
				case 'groups':
					$gids = array_map('intval', $args);
					if (!$is_registered || !$this->check_groups($viewer_id, $gids))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_GROUPS_FAILED');
					}
					break;
				case 'users':
					$uids = array_map('intval', $args);
					if (!$is_registered || !in_array($viewer_id, $uids, true))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_USERS_FAILED');
					}
					break;
				case 'pass':
					if (!$password_verified && !$this->is_block_unlocked($post_id, $block))
					{
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
			'can_view'            => $can_view,
			'failed_conditions'   => $failed_conditions,
			'override'            => false,
			'has_disabled_module' => $has_disabled_module,
		];
	}

	protected function check_replied($topic_id, $user_id)
	{
		if ($topic_id <= 0 || $user_id <= 0)
		{
			return false;
		}
		$sql = 'SELECT 1 FROM ' . POSTS_TABLE . ' WHERE topic_id = ' . (int)$topic_id . ' AND poster_id = ' . (int)$user_id . ' AND post_visibility = 1';
		$res = $this->db->sql_query_limit($sql, 1);
		$row = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);
		return !empty($row);
	}

	protected function check_thanked($post_id, $user_id)
	{
		if ($post_id <= 0 || $user_id <= 0)
		{
			return false;
		}

		$tbl_cfg = $this->config['advancedhide_thanks_table'];
		$tbl = !empty($tbl_cfg) ? $tbl_cfg : ($this->table_prefix . 'thanks');
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $tbl))
		{
			$tbl = $this->table_prefix . 'thanks';
		}

		if (self::$thanks_table_exists === null)
		{
			self::$thanks_table_exists = $this->db_tools->sql_table_exists($tbl);
		}

		if (!self::$thanks_table_exists)
		{
			return false;
		}

		$sql = 'SELECT 1 FROM ' . $tbl . ' WHERE post_id = ' . (int)$post_id . ' AND user_id = ' . (int)$user_id;
		$res = $this->db->sql_query_limit($sql, 1);
		$row = $res ? $this->db->sql_fetchrow($res) : false;
		if ($res)
		{
			$this->db->sql_freeresult($res);
		}
		return !empty($row);
	}

	protected function check_groups($user_id, array $gids)
	{
		if ($user_id <= 0)
		{
			return false;
		}

		if (!isset(self::$user_groups_cache[$user_id]))
		{
			$user_gids = [(int)$this->user->data['group_id']];
			$sql = 'SELECT group_id FROM ' . USER_GROUP_TABLE . ' WHERE user_id = ' . (int)$user_id . ' AND user_pending = 0';
			$res = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($res))
			{
				$user_gids[] = (int)$row['group_id'];
			}
			$this->db->sql_freeresult($res);
			self::$user_groups_cache[$user_id] = array_unique($user_gids);
		}

		return (bool)array_intersect($gids, self::$user_groups_cache[$user_id]);
	}
}