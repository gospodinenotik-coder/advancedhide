<?php
namespace gospodinenotik\advancedhide\controller;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\HttpFoundation\JsonResponse;
use phpbb\config\config;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbb\language\language;
use phpbb\cache\driver\driver_interface as cache_interface;
use phpbb\captcha\factory as captcha_factory;
use gospodinenotik\advancedhide\service\block_parser;
use gospodinenotik\advancedhide\service\auth_service;

class main_controller
{
	protected $config;
	protected $user;
	protected $auth;
	protected $db;
	protected $request;
	protected $language;
	protected $cache;
	protected $parser;
	protected $auth_service;
	protected $captcha_factory;
	protected $phpbb_root_path;
	protected $php_ext;
	protected $table_prefix;

	public function __construct(config $config, user $user, auth $auth, driver_interface $db, request_interface $request, language $language, cache_interface $cache, block_parser $parser, auth_service $auth_service, captcha_factory $captcha_factory, $phpbb_root_path, $php_ext, $table_prefix = null)
	{
		$this->config = $config;
		$this->user = $user;
		$this->auth = $auth;
		$this->db = $db;
		$this->request = $request;
		$this->language = $language;
		$this->cache = $cache;
		$this->parser = $parser;
		$this->auth_service = $auth_service;
		$this->captcha_factory = $captcha_factory;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix ?: (defined('POSTS_TABLE') ? substr(POSTS_TABLE, 0, -5) : 'phpbb_');
		$this->language->add_lang('common', 'gospodinenotik/advancedhide');
	}

	protected function mask_password($pass)
	{
		return '******';
	}

	protected function log_attempt($post_id, $block_index, $user_id = null, $ip = null, $status = 'failed', $entered_pass = '')
	{
		$post_id = (int)$post_id;
		$block_index = (int)$block_index;

		if (!is_numeric($user_id) && is_string($user_id))
		{
			// Called as ($post_id, $block_index, $status, $entered_pass)
			$entered_pass = (string)$ip;
			$status = $user_id;
			$user_id = (int)$this->user->data['user_id'];
			$ip = $this->user->ip;
		}
		else
		{
			$user_id = (int)$user_id;
			$ip = (string)$ip;
		}

		$status = substr((string)$status, 0, 32);
		$masked = $entered_pass !== '' ? $this->mask_password($entered_pass) : '';
		$now = time();
		$window_start = $now - 300; // 5-минутное окно схлопывания для активной серии

		$table = $this->table_prefix . 'advancedhide_logs';

		$sql = 'SELECT log_id, attempt_count, aggregated_count, details_json FROM ' . $table . '
			WHERE post_id = ' . $post_id . '
				AND block_index = ' . $block_index . '
				AND user_id = ' . $user_id . '
				AND attempt_time >= ' . $window_start . '
			ORDER BY attempt_time DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$existing = $this->db->sql_fetchrow($result);
		$this->db->sql_freeresult($result);

		if ($existing)
		{
			$details = !empty($existing['details_json']) ? (array)@json_decode($existing['details_json'], true) : [];
			$details[] = [
				'time' => $now,
				'ip'   => $ip,
				'pass' => $masked,
			];
			$new_count = ((int)($existing['aggregated_count'] ?: $existing['attempt_count'])) + 1;

			$sql = 'UPDATE ' . $table . "
				SET attempt_count = " . $new_count . ",
					aggregated_count = " . $new_count . ",
					details_json = '" . $this->db->sql_escape(json_encode($details)) . "',
					attempt_time = " . $now . ",
					status = '" . $this->db->sql_escape($status) . "',
					masked_pass = '" . $this->db->sql_escape($masked) . "',
					password_used = '" . $this->db->sql_escape($masked) . "'
				WHERE log_id = " . (int)$existing['log_id'];
			$this->db->sql_query($sql);
		}
		else
		{
			$details = [
				[
					'time' => $now,
					'ip'   => $ip,
					'pass' => $masked,
				]
			];
			$sql_ary = [
				'post_id'          => $post_id,
				'block_index'      => $block_index,
				'user_id'          => $user_id,
				'user_ip'          => $ip,
				'attempt_time'     => $now,
				'status'           => $status,
				'masked_pass'      => $masked,
				'password_used'    => $masked,
				'attempt_count'    => 1,
				'aggregated_count' => 1,
				'details_json'     => json_encode($details),
			];
			$this->db->sql_query('INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));
		}
	}

	public function unlock()
	{
		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('INVALID_REQUEST')], 400);
		}

		if (!check_form_key('advancedhide_unlock'))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FORM_INVALID')], 403);
		}

		if (!$this->auth_service->is_module_enabled('pass'))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_COND_MODULE_DISABLED', $this->auth_service->get_module_title('pass'))], 403);
		}

		$post_id  = $this->request->variable('post_id', 0);
		$block_id = $this->request->variable('block_id', 0);
		$pass     = $this->request->variable('password', '', true, request_interface::POST);

		$sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.poster_id, p.post_text, p.post_visibility, p.bbcode_uid, p.bbcode_bitfield, p.enable_bbcode, p.enable_smilies, p.enable_magic_url, t.topic_visibility
				FROM ' . POSTS_TABLE . ' p
				JOIN ' . TOPICS_TABLE . ' t ON (p.topic_id = t.topic_id)
				WHERE p.post_id = ' . (int)$post_id;
		$res = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$post)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$forum_id = (int)$post['forum_id'];
		$can_approve = $this->auth->acl_get('m_approve', $forum_id);

		if (!$this->auth->acl_get('f_read', $forum_id) ||
			($post['post_visibility'] != ITEM_APPROVED && !$can_approve) ||
			($post['topic_visibility'] != ITEM_APPROVED && !$can_approve))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('SORRY_AUTH_READ')], 403);
		}

		$sql_f = 'SELECT forum_password FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int)$forum_id;
		$res_f = $this->db->sql_query($sql_f);
		$forum_data = $this->db->sql_fetchrow($res_f);
		$this->db->sql_freeresult($res_f);

		if (!empty($forum_data['forum_password']))
		{
			$session_passwords = !empty($this->user->data['session_forum_passwords'])
				? (array)@unserialize($this->user->data['session_forum_passwords'], ['allowed_classes' => false])
				: [];
			if (empty($session_passwords[$forum_id]))
			{
				return new JsonResponse(['success' => false, 'message' => $this->language->lang('SORRY_AUTH_READ')], 403);
			}
		}

		$blocks = $this->parser->parse_blocks($post['post_text']);
		if (!isset($blocks[$block_id - 1]))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$block = $blocks[$block_id - 1];
		if (!$block->has_password)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 400);
		}

		$user_id = (int)$this->user->data['user_id'];
		$user_ip = $this->user->ip;

		// Ранняя отсечка: если зарегистрированный пользователь забанен к этому блоку,
		// не даём проверять пароль и не расходуем ресурсы сервера
		if ($user_id > 1)
		{
			$ban_info = $this->auth_service->get_user_block_ban($post_id, $block_id, $user_id);
			if ($ban_info !== null && $ban_info !== false && !$this->auth->acl_get('m_hide_override', $forum_id))
			{
				$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'banned', $pass);
				$reason_msg = !empty($ban_info['ban_reason']) ? $this->language->lang('HIDE_BANNED_WITH_REASON', $ban_info['ban_reason']) : $this->language->lang('HIDE_BANNED_FROM_BLOCK');
				return new JsonResponse([
					'success'   => false,
					'message'   => $reason_msg,
					'is_banned' => true,
					'post_id'   => $post_id,
					'block_id'  => $block_id,
				], 403);
			}
		}

		$session_id = !empty($this->user->session_id) ? $this->user->session_id : $this->user->ip;
		$user_token = ($user_id > 1) ? 'u_' . $user_id : 's_' . $session_id;
		$user_identity = hash('sha256', $user_token . '|' . $post_id . '|' . $block->block_hash);

		$reservation = $this->auth_service->acquire_rate_limit($user_identity, $user_ip, $post_id, $block->block_hash);
		if ($reservation === false)
		{
			$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'rate_limited', $pass);
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_RATE_LIMIT_EXCEEDED')], 429);
		}

		// Валидация Captcha
		if (!empty($this->config['advancedhide_enable_captcha']))
		{
			$plugin_name = $this->config['captcha_plugin'] ?? '';
			if (empty($plugin_name))
			{
				$this->auth_service->refund_rate_limit($reservation);
				return new JsonResponse(['success' => false, 'message' => $this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE')], 503);
			}

			try
			{
				$captcha = $this->captcha_factory->get_instance($plugin_name);
				if (!$captcha->is_available())
				{
					$this->auth_service->refund_rate_limit($reservation);
					return new JsonResponse(['success' => false, 'message' => $this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE')], 503);
				}

				$captcha->init(CONFIRM_POST);
				$vc_response = $captcha->validate();
				if ($vc_response !== false)
				{
					$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'captcha_failed', $pass);
					$err_text = $this->language->is_set($vc_response) ? $this->language->lang($vc_response) : ($vc_response ?: $this->language->lang('CONFIRM_CODE_WRONG'));
					return new JsonResponse([
						'success'       => false,
						'message'       => $err_text,
						'captcha_error' => true,
						'new_captcha'   => $this->auth_service->generate_captcha_html(),
					], 400);
				}
			}
			catch (\Exception $e)
			{
				$this->auth_service->refund_rate_limit($reservation);
				return new JsonResponse(['success' => false, 'message' => $this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE')], 503);
			}
		}

		// Проверка пароля
		if ($block->password_hash !== '' && password_verify($pass, $block->password_hash))
		{
			$context = [
				'forum_id'          => $forum_id,
				'topic_id'          => (int)$post['topic_id'],
				'post_id'           => (int)$post['post_id'],
				'poster_id'         => (int)$post['poster_id'],
				'block_index'       => $block_id,
				'password_verified' => true,
			];

			$re_eval = $this->auth_service->evaluate_block($block, $context);
			if (!$re_eval['can_view'])
			{
				$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'conditions_failed', $pass);
				$this->auth_service->refund_rate_limit($reservation);
				return new JsonResponse([
					'success' => false,
					'message' => $this->language->lang('HIDE_PASS_OK_OTHER_FAILED_GENERIC')
				], 403);
			}

			$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'unlocked', $pass);
			$this->auth_service->refund_rate_limit($reservation);
			$this->auth_service->unlock_block($post_id, $block);

			if (!function_exists('generate_text_for_display'))
			{
				include_once($this->phpbb_root_path . 'includes/functions_content.' . $this->php_ext);
			}

			$bbcode_options = ($post['enable_bbcode'] ? 1 : 0) | ($post['enable_smilies'] ? 2 : 0) | ($post['enable_magic_url'] ? 4 : 0);
			$rendered_all = generate_text_for_display($post['post_text'], $post['bbcode_uid'], $post['bbcode_bitfield'], $bbcode_options);
			$rendered_inner = '';
			if (preg_match_all('/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is', $rendered_all, $matches))
			{
				$rendered_inner = $matches[2][$block_id - 1] ?? '';
			}

			$html = '<div class="advancedhide-box hide-unlocked" id="hide-' . $post_id . '-' . $block->block_index . '">' .
				'<div class="hide-header"><i class="fa fa-unlock-alt"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_UNLOCKED'), ENT_QUOTES, 'UTF-8') . '</div>' .
				'<div class="hide-content">' . $rendered_inner . '</div>' .
			'</div>';

			return new JsonResponse(['success' => true, 'html' => $html]);
		}

		$this->log_attempt($post_id, $block_id, $user_id, $user_ip, 'failed', $pass);
		return new JsonResponse([
			'success'     => false,
			'message'     => $this->language->lang('HIDE_PASS_INCORRECT'),
			'new_captcha' => !empty($this->config['advancedhide_enable_captcha']) ? $this->auth_service->generate_captcha_html() : '',
		], 401);
	}

	public function audit()
	{
		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('INVALID_REQUEST')], 400);
		}

		$post_id  = $this->request->variable('post_id', 0);
		$block_id = $this->request->variable('block_id', 0);

		$sql = 'SELECT poster_id, forum_id FROM ' . POSTS_TABLE . ' WHERE post_id = ' . (int)$post_id;
		$res = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$post)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$forum_id = (int)$post['forum_id'];
		$poster_id = (int)$post['poster_id'];
		$user_id = (int)$this->user->data['user_id'];
		$is_mod = $this->auth->acl_get('m_hide_override', $forum_id) || $this->auth->acl_get('m_hide_ban', $forum_id) || $this->auth->acl_get('a_');
		$is_author = ($poster_id > 0 && $user_id === $poster_id);

		if (!$is_mod && !$is_author)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NO_AUTH_OPERATION')], 403);
		}

		$logs_table = $this->table_prefix . 'advancedhide_logs';
		$sql = 'SELECT l.*, u.username, u.user_colour FROM ' . $logs_table . ' l
			LEFT JOIN ' . USERS_TABLE . ' u ON (l.user_id = u.user_id)
			WHERE l.post_id = ' . (int)$post_id . ($block_id > 0 ? (' AND l.block_index = ' . (int)$block_id) : '') . '
			ORDER BY l.attempt_time DESC';
		$res = $this->db->sql_query_limit($sql, 50);

		$entries = [];
		while ($row = $this->db->sql_fetchrow($res))
		{
			$ip = $row['user_ip'];
			if (!$is_mod && !empty($ip))
			{
				if (strpos($ip, ':') !== false)
				{
					$parts = explode(':', $ip);
					$ip = (!empty($parts[0]) ? $parts[0] : '2001') . ':*:*:*';
				}
				else
				{
					$parts = explode('.', $ip);
					$ip = (count($parts) === 4) ? $parts[0] . '.' . $parts[1] . '.*.*' : '*.*.*.*';
				}
			}

			$status_key = 'ADVHIDE_AUDIT_' . strtoupper($row['status']);
			$status_label = $this->language->is_set($status_key) ? $this->language->lang($status_key) : $row['status'];

			$entries[] = [
				'log_id'        => (int)$row['log_id'],
				'block_index'   => (int)$row['block_index'],
				'username'      => !empty($row['username']) ? $row['username'] : $this->language->lang('GUEST'),
				'user_id'       => (int)$row['user_id'],
				'user_ip'       => $ip,
				'attempt_time'  => $this->user->format_date($row['attempt_time']),
				'status'        => $status_label,
				'status_raw'    => $row['status'],
				'attempt_count' => (int)$row['attempt_count'],
				'masked_pass'   => $row['masked_pass'],
			];
		}
		$this->db->sql_freeresult($res);

		$bans_table = $this->table_prefix . 'advancedhide_bans';
		$sql_b = 'SELECT b.*, u.username FROM ' . $bans_table . ' b
			LEFT JOIN ' . USERS_TABLE . ' u ON (b.user_id = u.user_id)
			WHERE b.post_id = ' . (int)$post_id . ($block_id > 0 ? (' AND b.block_index = ' . (int)$block_id) : '') . '
			ORDER BY b.ban_start DESC';
		$res_b = $this->db->sql_query($sql_b);
		$bans = [];
		while ($row = $this->db->sql_fetchrow($res_b))
		{
			$bans[] = [
				'ban_id'      => (int)$row['ban_id'],
				'block_index' => (int)$row['block_index'],
				'user_id'     => (int)$row['user_id'],
				'username'    => $row['username'],
				'ban_start'   => $this->user->format_date($row['ban_start']),
				'ban_end'     => $row['ban_end'] > 0 ? $this->user->format_date($row['ban_end']) : $this->language->lang('ADVHIDE_BAN_PERMANENT'),
				'ban_reason'  => $row['ban_reason'],
			];
		}
		$this->db->sql_freeresult($res_b);

		return new JsonResponse([
			'success'        => true,
			'logs'           => $entries,
			'bans'           => $bans,
			'can_ban'        => ($is_mod || $is_author),
			'is_mod'         => (bool)$is_mod,
			'post_author_id' => (int)$poster_id,
		]);
	}

	public function ban_user()
	{
		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('INVALID_REQUEST')], 400);
		}

		if (!check_form_key('advancedhide_ban') && !check_form_key('advancedhide_unlock'))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FORM_INVALID')], 403);
		}

		$post_id         = $this->request->variable('post_id', 0);
		$block_id        = $this->request->variable('block_id', 0);
		$target_user_id  = $this->request->variable('target_user_id', 0) ?: $this->request->variable('user_id', 0);
		$target_username = $this->request->variable('username', '', true);
		$days            = $this->request->variable('duration_days', 0) ?: $this->request->variable('days', 0);
		$reason          = mb_substr(trim($this->request->variable('reason', '', true)), 0, 500);
		$action          = $this->request->variable('action', 'ban');
		$ban_id          = $this->request->variable('ban_id', 0);

		if ($action === 'ban' && $target_user_id <= 1 && $target_username === '')
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NO_USER')], 400);
		}

		$sql = 'SELECT poster_id, forum_id, topic_id FROM ' . POSTS_TABLE . ' WHERE post_id = ' . (int)$post_id;
		$res = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$post)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$forum_id        = (int)$post['forum_id'];
		$poster_id       = (int)$post['poster_id'];
		$current_user_id = (int)$this->user->data['user_id'];
		$is_mod          = $this->auth->acl_get('m_hide_ban', $forum_id) || $this->auth->acl_get('m_hide_override', $forum_id) || $this->auth->acl_get('a_');
		$is_author       = ($poster_id > 0 && $current_user_id === $poster_id);

		if (!$is_mod && !$is_author)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NO_AUTH_OPERATION')], 403);
		}

		if ($action === 'unban' && $ban_id > 0)
		{
			if (!$is_mod)
			{
				$sql_check = 'SELECT ban_id FROM ' . $this->table_prefix . 'advancedhide_bans
					WHERE ban_id = ' . (int)$ban_id . ' AND post_id = ' . (int)$post_id;
				$res_check = $this->db->sql_query($sql_check);
				$ban_match = $this->db->sql_fetchrow($res_check);
				$this->db->sql_freeresult($res_check);
				if (!$ban_match)
				{
					return new JsonResponse(['success' => false, 'message' => $this->language->lang('NO_AUTH_OPERATION')], 403);
				}
			}

			$this->auth_service->remove_block_ban($ban_id);
			return new JsonResponse(['success' => true, 'message' => $this->language->lang('ADVHIDE_UNBAN_SUCCESS')]);
		}

		if ($target_user_id <= 1 && $target_username !== '')
		{
			$sql_u = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE username_clean = \'' . $this->db->sql_escape(utf8_clean_string($target_username)) . '\'';
			$res_u = $this->db->sql_query($sql_u);
			$u_row = $this->db->sql_fetchrow($res_u);
			$this->db->sql_freeresult($res_u);
			if ($u_row)
			{
				$target_user_id = (int)$u_row['user_id'];
			}
		}

		if ($target_user_id <= 1)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NO_USER')], 400);
		}

		// Иммунитет автора: запрет на блокировку автора на его собственном сообщении
		if ($target_user_id === $poster_id)
		{
			return new JsonResponse([
				'success' => false,
				'message' => $this->language->lang('HIDE_BAN_AUTHOR_ERROR'),
			], 400);
		}

		$flood_key = '_advhide_ban_' . (int)$current_user_id;
		if ($this->cache->get($flood_key))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FLOOD_ERROR')], 429);
		}
		$this->cache->put($flood_key, 1, 3);

		$this->auth_service->add_block_ban($post_id, $block_id, $target_user_id, $current_user_id, $days, $reason);

		if (function_exists('add_log'))
		{
			add_log('mod', $forum_id, (int)$post['topic_id'], 'LOG_ADVHIDE_BAN_USER', (string)$target_user_id, (string)$post_id, (string)$block_id);
		}

		return new JsonResponse(['success' => true, 'message' => $this->language->lang('HIDE_BAN_SUCCESS')]);
	}

	public function report_bruteforce()
	{
		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('INVALID_REQUEST')], 400);
		}

		if (!check_form_key('advancedhide_report'))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FORM_INVALID')], 403);
		}

		$post_id  = $this->request->variable('post_id', 0);
		$block_id = $this->request->variable('block_id', 0);
		$reason   = mb_substr(trim($this->request->variable('reason', '', true)), 0, 500);

		$sql = 'SELECT poster_id, forum_id, topic_id FROM ' . POSTS_TABLE . ' WHERE post_id = ' . (int)$post_id;
		$res = $this->db->sql_query($sql);
		$post = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$post)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$current_user_id = (int)$this->user->data['user_id'];
		$poster_id       = (int)$post['poster_id'];
		$forum_id        = (int)$post['forum_id'];

		$is_author = ($poster_id > 0 && $current_user_id === $poster_id);
		$is_mod    = $this->auth->acl_get('m_hide_override', $forum_id) || $this->auth->acl_get('m_hide_ban', $forum_id);

		if (!$is_author && !$is_mod)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('SORRY_AUTH_READ')], 403);
		}

		$flood_key = '_advhide_rep_' . (int)$current_user_id;
		if ($this->cache->get($flood_key))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FLOOD_ERROR')], 429);
		}
		$this->cache->put($flood_key, 1, 15);

		$reports_table = $this->table_prefix . 'advancedhide_reports';
		$sql_ary = [
			'post_id'       => (int)$post_id,
			'block_index'   => (int)$block_id,
			'reporter_id'   => $current_user_id,
			'report_time'   => time(),
			'report_reason' => $reason,
			'report_status' => 'open',
			'report_closed' => 0,
		];
		$this->db->sql_query('INSERT INTO ' . $reports_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return new JsonResponse(['success' => true, 'message' => $this->language->lang('HIDE_REPORT_SUBMITTED')]);
	}

	public function submit_appeal()
	{
		if (!$this->request->is_ajax())
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('INVALID_REQUEST')], 400);
		}

		if (!check_form_key('advancedhide_appeal'))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FORM_INVALID')], 403);
		}

		$post_id  = $this->request->variable('post_id', 0);
		$block_id = $this->request->variable('block_id', 0);
		$reason   = mb_substr(trim($this->request->variable('reason', '', true)), 0, 500);

		$user_id = (int)$this->user->data['user_id'];
		if ($user_id <= 1)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NOT_AUTHORISED')], 403);
		}

		$now = time();
		$bans_table = $this->table_prefix . 'advancedhide_bans';
		$sql = 'SELECT ban_id, appeal_status FROM ' . $bans_table . '
			WHERE post_id = ' . (int)$post_id . '
				AND block_index = ' . (int)$block_id . '
				AND user_id = ' . (int)$user_id . '
				AND (ban_end = 0 OR ban_end > ' . $now . ')';
		$res = $this->db->sql_query_limit($sql, 1);
		$ban = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$ban)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('NOT_AUTHORISED')], 403);
		}

		if ($ban['appeal_status'] === 'rejected')
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('ADVHIDE_APPEAL_REJECTED')], 403);
		}

		if ($ban['appeal_status'] === 'pending')
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_APPEAL_SUBMITTED')], 200);
		}

		$flood_key = '_advhide_app_' . (int)$user_id;
		if ($this->cache->get($flood_key))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('FLOOD_ERROR')], 429);
		}
		$this->cache->put($flood_key, 1, 15);

		$sql_up = 'UPDATE ' . $bans_table . "
			SET appeal_status = 'pending',
				appeal_reason = '" . $this->db->sql_escape($reason) . "',
				appeal_time = " . $now . '
			WHERE ban_id = ' . (int)$ban['ban_id'];
		$this->db->sql_query($sql_up);

		$reports_table = $this->table_prefix . 'advancedhide_reports';
		$sql_ary = [
			'post_id'       => (int)$post_id,
			'block_index'   => (int)$block_id,
			'reporter_id'   => $user_id,
			'report_time'   => $now,
			'report_reason' => '[APPEAL] ' . $reason,
			'report_status' => 'pending',
			'report_closed' => 0,
		];
		$this->db->sql_query('INSERT INTO ' . $reports_table . ' ' . $this->db->sql_build_array('INSERT', $sql_ary));

		return new JsonResponse(['success' => true, 'message' => $this->language->lang('HIDE_APPEAL_SUBMITTED')]);
	}
}