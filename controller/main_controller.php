<?php
namespace vendor\advancedhide\controller;

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
use vendor\advancedhide\service\block_parser;
use vendor\advancedhide\service\auth_service;

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

	public function __construct(config $config, user $user, auth $auth, driver_interface $db, request_interface $request, language $language, cache_interface $cache, block_parser $parser, auth_service $auth_service, captcha_factory $captcha_factory, $phpbb_root_path, $php_ext, $table_prefix)
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
		$this->table_prefix = $table_prefix;
	}

	public function unlock()
	{
		global $log;
		
		// Emergency Kill-Switch Check (Fail-Closed)
		if (!empty($this->config['advancedhide_emergency_shutdown']))
		{
			return new JsonResponse([
				'success' => false, 
				'message' => $this->language->lang('ADVHIDE_EMERGENCY_SHUTDOWN')
			], 503);
		}
	
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
			WHERE p.post_id = :post_id';
		$res = $this->db->sql_query($sql, ['post_id' => $post_id]);
		$post = $this->db->sql_fetchrow($res);
		$this->db->sql_freeresult($res);

		if (!$post)
		{
			// Аудит: попытка доступа к несуществующему блоку (возможная атака перебором)
			$this->audit_log('unlock_attempt', 0, 0, 'failure', json_encode(['post_id' => $post_id, 'reason' => 'not_found']));
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_BLOCK_NOT_FOUND')], 404);
		}

		$forum_id = (int)$post['forum_id'];
		$can_approve = $this->auth->acl_get('m_approve', $forum_id);
		if (!$this->auth->acl_get('f_read', $forum_id) || ($post['post_visibility'] != ITEM_APPROVED && !$can_approve) || ($post['topic_visibility'] != ITEM_APPROVED && !$can_approve))
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('SORRY_AUTH_READ')], 403);
		}

		$sql_f = 'SELECT forum_password FROM ' . FORUMS_TABLE . ' WHERE forum_id = ' . (int)$forum_id;
		$res_f = $this->db->sql_query($sql_f);
		$forum_data = $this->db->sql_fetchrow($res_f);
		$this->db->sql_freeresult($res_f);

		if (!empty($forum_data['forum_password']))
		{
			$session_passwords = !empty($this->user->data['session_forum_passwords']) ? json_decode($this->user->data['session_forum_passwords'], true) : [];
			if (!is_array($session_passwords))
			{
				$session_passwords = [];
			}
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
		$session_id = !empty($this->user->session_id) ? $this->user->session_id : $this->user->ip;
		$user_token = ($user_id > 1) ? 'u_' . $user_id : 's_' . $session_id;
		$user_identity = hash('sha256', $user_token . '|' . $post_id . '|' . $block->block_hash);
		$user_ip = $this->user->ip;

		$reservation = $this->auth_service->acquire_rate_limit($user_identity, $user_ip, $post_id, $block->block_hash);
		if ($reservation === false)
		{
			return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_RATE_LIMIT_EXCEEDED')], 429);
		}

		// Captcha Verification (Fail-Closed)
		// Валидация штатной Captcha ядра phpBB (Fail-Closed)
		if (!empty($this->config['advancedhide_enable_captcha']))
		{
			try
			{
				$plugin_name = $this->config['captcha_plugin'] ?? '';
				if (empty($plugin_name))
				{
					$this->auth_service->refund_rate_limit($reservation);
					return new JsonResponse(['success' => false, 'message' => $this->language->lang('CAPTCHA_SERVICE_UNAVAILABLE')], 503);
				}

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
				$this->auth_service->refund_rate_limit($reservation);
				return new JsonResponse([
					'success' => false,
					'message' => $this->language->lang('HIDE_PASS_OK_OTHER_FAILED_GENERIC'),
				], 403);
			}

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
				'<div class="advancedhide-header">' . $this->language->lang('HIDE_UNLOCKED_HEADER') . '</div>' .
				'<div class="advancedhide-content">' . $rendered_inner . '</div>' .
				'</div>';

			// Аудит: успешная разблокировка
			$this->audit_log('unlock_success', $post_id, $block->block_index, 'success', json_encode(['user_id' => $user_id]));

			return new JsonResponse(['success' => true, 'html' => $html]);
		}

		// Пароль неверный - возврат лимита
		$this->auth_service->refund_rate_limit($reservation);
		
		// Аудит: неудачная попытка разблокировки
		$this->audit_log('unlock_attempt', $post_id, $block->block_index, 'failure', json_encode(['reason' => 'wrong_password']));

		return new JsonResponse(['success' => false, 'message' => $this->language->lang('HIDE_WRONG_PASSWORD')], 403);
	}

	/**
	 * Запись в журнал аудита безопасности (Sorokin Action Journal)
	 */
	protected function audit_log($action_type, $post_id, $block_index, $result, $details = '')
	{
		global $log;

		$sql_ary = [
			'log_time'     => time(),
			'user_id'      => (int) $this->user->data['user_id'],
			'user_ip'      => $this->user->ip,
			'action_type'  => substr($action_type, 0, 50),
			'post_id'      => (int) $post_id,
			'block_index'  => (int) $block_index,
			'result'       => substr($result, 0, 20),
			'details'      => substr($details, 0, 65535),
		];

		// Вставка в таблицу аудита
		$this->db->sql_query('INSERT INTO ' . $this->table_prefix . 'advancedhide_audit_log ' . $this->db->sql_build_array('INSERT', $sql_ary));

		// Дублирование в системный лог phpBB для интеграции с ACP
		if (isset($log))
		{
			$log_action = 'ADVHIDE_' . strtoupper($action_type);
			$log->add('admin', (int) $this->user->data['user_id'], $this->user->ip, $log_action, false, [], 0, 0);
		}
	}
}
