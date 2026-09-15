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

		$plugin_name = $this->config['captcha_plugin'] ?? '';
		if (empty($plugin_name))
		{
			return '<div class="advhide-captcha-box advhide-captcha-error"><span class="error">' . htmlspecialchars($this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE'), ENT_QUOTES, 'UTF-8') . '</span></div>';
		}

		try
		{
			$captcha = $this->captcha_factory->get_instance($plugin_name);
			if (!$captcha->is_available())
			{
				return '<div class="advhide-captcha-box advhide-captcha-error"><span class="error">' . htmlspecialchars($this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE'), ENT_QUOTES, 'UTF-8') . '</span></div>';
			}

			$captcha->init(CONFIRM_POST);

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

			if ($captcha instanceof \phpbb\captcha\plugins\recaptcha)
			{
				$sitekey = $this->config['recaptcha_sitekey'] ?? '';
				return '<div class="advhide-captcha-box advhide-captcha-recaptcha">' .
					'<script src="https://www.google.com/recaptcha/api.js" async defer></script>' .
					'<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($sitekey, ENT_QUOTES, 'UTF-8') . '"></div>' .
				'</div>';
			}

			if ($captcha instanceof \phpbb\captcha\plugins\recaptcha_v3)
			{
				$sitekey = (string)($this->config['recaptcha_v3_sitekey'] ?? '');
				$safe_js_sitekey = json_encode($sitekey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
				return '<div class="advhide-captcha-box advhide-captcha-recaptcha-v3">' .
					'<script src="https://www.google.com/recaptcha/api.js?render=' . urlencode($sitekey) . '"></script>' .
					'<input type="hidden" name="g-recaptcha-response" class="advhide-g-recaptcha-v3-token" />' .
					'<script>if (typeof grecaptcha !== "undefined") { grecaptcha.ready(function() { grecaptcha.execute(' . $safe_js_sitekey . ', {action: "advancedhide_unlock"}).then(function(token) { $(".advhide-g-recaptcha-v3-token").val(token); }); }); }</script>' .
				'</div>';
			}

			if ($captcha instanceof \phpbb\captcha\plugins\captcha_abstract)
			{
				$c_id = $captcha->confirm_id;
				$img_url = append_sid($this->phpbb_root_path . 'ucp.' . $this->php_ext, 'mode=confirm&confirm_id=' . $c_id . '&type=' . CONFIRM_POST . '&t=' . time());
				return '<div class="advhide-captcha-box advhide-captcha-gd">' .
					'<div class="advhide-captcha-img"><img src="' . $img_url . '" alt="" /></div>' .
					'<input type="hidden" name="confirm_id" value="' . htmlspecialchars($c_id, ENT_QUOTES, 'UTF-8') . '" />' .
					'<input type="text" name="confirm_code" class="inputbox autowidth advhide-captcha-input" placeholder="' . htmlspecialchars($this->language->lang('HIDE_CAPTCHA_CODE_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '" autocomplete="off" />' .
				'</div>';
			}
		}
		catch (\Exception $e)
		{
			return '<div class="advhide-captcha-box advhide-captcha-error"><span class="error">' . htmlspecialchars($this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE'), ENT_QUOTES, 'UTF-8') . '</span></div>';
		}

		return '';
	}

	public function is_block_unlocked($post_id, hide_block $block)
	{
		$session_id = !empty($this->user->data['session_id']) ? $this->user->data['session_id'] : ($this->user->session_id ?? 'guest');
		$cache_key = '_advhide_unlock_' . $session_id;
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
		$session_id = !empty($this->user->data['session_id']) ? $this->user->data['session_id'] : ($this->user->session_id ?? 'guest');
		$cache_key = '_advhide_unlock_' . $session_id;
		$unlocked = $this->cache->get($cache_key) ?: [];
		$unlocked[$post_id][$block->block_index] = $block->block_hash;
		$this->cache->put($cache_key, $unlocked, 1800);
	}

	/**
	 * Резервирование счетчиков с компенсирующим откатом при отказе любого уровня.
	 *
	 * @param string $user_identity Идентификатор учетной записи (u_<id>) или сессии (s_<id>)
	 * @param string $ip             IP-адрес клиента
	 * @param int    $post_id        ID сообщения
	 * @param string $block_hash     Хэш содержимого блока
	 * @return array|false Дескриптор резервации при успехе, false при исчерпании лимита
	 */
	public function acquire_rate_limit($user_identity, $ip, $post_id, $block_hash)
	{
		$minute_window = (int) floor(time() / 60);
		$day_window    = (int) floor(time() / 86400);

		$key_id    = 'm_' . substr($user_identity, 0, 62);
		$key_d_id  = 'd_' . substr($user_identity, 0, 62);
		$key_ip    = 'm_ip_' . substr(hash('sha256', $ip), 0, 59);
		$key_block = 'm_bk_' . substr(hash('sha256', $post_id . '_' . $block_hash), 0, 59);

		$minute_limit = max(1, (int)($this->config['advancedhide_rl_minute_limit'] ?? 5));
		$day_limit    = max(1, (int)($this->config['advancedhide_rl_day_limit'] ?? 30));
		$ip_limit     = max(10, $minute_limit * 4);
		$block_limit  = max(15, $minute_limit * 6);

		// 1. Минутный контур пользователя (User / minute)
		if (!$this->rl_consume($key_id, $minute_window, $minute_limit))
		{
			return false;
		}

		// 2. Суточный контур пользователя (User / day)
		if (!$this->rl_consume($key_d_id, $day_window, $day_limit))
		{
			$this->rl_refund($key_id, $minute_window);
			return false;
		}

		// 3. Контур IP (IP / minute)
		if (!$this->rl_consume($key_ip, $minute_window, $ip_limit))
		{
			$this->rl_refund($key_id, $minute_window);
			$this->rl_refund($key_d_id, $day_window);
			return false;
		}

		// 4. Контур блока (Block / minute)
		if (!$this->rl_consume($key_block, $minute_window, $block_limit))
		{
			$this->rl_refund($key_id, $minute_window);
			$this->rl_refund($key_d_id, $day_window);
			$this->rl_refund($key_ip, $minute_window);
			return false;
		}

		if (mt_rand(1, 200) === 1)
		{
			$this->rl_gc($minute_window, $day_window);
		}

		return [
			'minute_window' => $minute_window,
			'day_window'    => $day_window,
			'key_id'        => $key_id,
			'key_d_id'      => $key_d_id,
			'key_ip'        => $key_ip,
			'key_block'     => $key_block,
			'refunded'      => false,
		];
	}

	/**
	 * Идемпотентный возврат зарезервированного слота строго в исходные окна
	 * с защитой от повторного вызова (single-descriptor refund guard).
	 *
	 * @param array $reservation Дескриптор, полученный из acquire_rate_limit
	 */
	public function refund_rate_limit(array &$reservation)
	{
		if (
			!empty($reservation['refunded']) ||
			!isset(
				$reservation['minute_window'],
				$reservation['day_window'],
				$reservation['key_id'],
				$reservation['key_d_id'],
				$reservation['key_ip'],
				$reservation['key_block']
			)
		)
		{
			return;
		}

		$reservation['refunded'] = true;

		$this->rl_refund($reservation['key_id'], $reservation['minute_window']);
		$this->rl_refund($reservation['key_d_id'], $reservation['day_window']);
		$this->rl_refund($reservation['key_ip'], $reservation['minute_window']);
		$this->rl_refund($reservation['key_block'], $reservation['minute_window']);
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

	protected function rl_refund($key, $window)
	{
		$table = $this->table_prefix . 'advancedhide_rl';
		$safe_key = $this->db->sql_escape($key);

		$sql = 'UPDATE ' . $table .
			' SET rl_count = rl_count - 1' .
			' WHERE rl_key = \'' . $safe_key . '\'' .
			' AND rl_window = ' . (int) $window .
			' AND rl_count > 0';
		$this->db->sql_query($sql);
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

		if ($post_id > 0 && $viewer_id > 0)
		{
			$ban_info = $this->get_user_block_ban($post_id, $block->block_index, $viewer_id);
			if ($ban_info !== null)
			{
				$reason_text = !empty($ban_info['ban_reason']) ? $this->language->lang('HIDE_BANNED_WITH_REASON', $ban_info['ban_reason']) : $this->language->lang('HIDE_BANNED_FROM_BLOCK');
				return [
					'can_view'            => false,
					'failed_conditions'   => [$reason_text],
					'override'            => false,
					'has_disabled_module' => false,
					'is_banned'           => true,
					'ban_info'            => $ban_info,
				];
			}
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
				case 'not_groups':
					$gids = array_map('intval', $args);
					if ($is_registered && $this->check_groups($viewer_id, $gids))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_NOT_GROUPS_FAILED');
					}
					break;
				case 'not_users':
					$uids = array_map('intval', $args);
					if ($is_registered && in_array($viewer_id, $uids, true))
					{
						$can_view = false;
						$failed_conditions[] = $this->language->lang('HIDE_COND_NOT_USERS_FAILED');
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

	public function get_user_block_ban($post_id, $block_index, $user_id)
	{
		$post_id = (int)$post_id;
		$block_index = (int)$block_index;
		$user_id = (int)$user_id;

		if ($post_id <= 0 || $user_id <= 0)
		{
			return null;
		}

		$table = $this->table_prefix . 'advancedhide_bans';
		$now = time();
		$sql = 'SELECT * FROM ' . $table . '
			WHERE post_id = ' . $post_id . '
				AND block_index = ' . $block_index . '
				AND user_id = ' . $user_id . '
				AND (ban_end = 0 OR ban_end > ' . $now . ')';
		$result = $this->db->sql_query_limit($sql, 1);
		$ban = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		return $ban ?: null;
	}

	public function add_block_ban($post_id, $block_index, $user_id, $banned_by, $days = 0, $reason = '')
	{
		$post_id = (int)$post_id;
		$block_index = (int)$block_index;
		$user_id = (int)$user_id;
		$banned_by = (int)$banned_by;
		$days = (int)$days;
		$now = time();
		$ban_end = $days > 0 ? ($now + $days * 86400) : 0;

		$table = $this->table_prefix . 'advancedhide_bans';
		$sql = 'DELETE FROM ' . $table . '
			WHERE post_id = ' . $post_id . ' AND block_index = ' . $block_index . ' AND user_id = ' . $user_id;
		$this->db->sql_query($sql);

		$sql_ary = [
			'post_id'     => $post_id,
			'block_index' => $block_index,
			'user_id'     => $user_id,
			'banned_by'   => $banned_by,
			'ban_start'   => $now,
			'ban_end'     => $ban_end,
			'ban_reason'  => (string)$reason,
		];
		$this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return (int)$this->db->sql_nextid();
	}

	public function remove_block_ban($ban_id)
	{
		$table = $this->table_prefix . 'advancedhide_bans';
		$sql = 'DELETE FROM ' . $table . ' WHERE ban_id = ' . (int)$ban_id;
		$this->db->sql_query($sql);
		return $this->db->sql_affectedrows() > 0;
	}
}