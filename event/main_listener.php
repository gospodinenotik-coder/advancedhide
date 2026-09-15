<?php
namespace gospodinenotik\advancedhide\event;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\config\config;
use phpbb\template\template;
use phpbb\request\request_interface;
use phpbb\language\language;
use gospodinenotik\advancedhide\service\auth_service;
use gospodinenotik\advancedhide\service\block_parser;

class main_listener implements EventSubscriberInterface
{
	protected $user;
	protected $auth;
	protected $config;
	protected $template;
	protected $request;
	protected $language;
	protected $auth_service;
	protected $parser;
	protected $phpbb_root_path;
	protected $php_ext;

	public function __construct(
		user $user,
		auth $auth,
		config $config,
		template $template,
		request_interface $request,
		language $language,
		auth_service $auth_service,
		block_parser $parser,
		$phpbb_root_path,
		$php_ext
	) {
		$this->user = $user;
		$this->auth = $auth;
		$this->config = $config;
		$this->template = $template;
		$this->request = $request;
		$this->language = $language;
		$this->auth_service = $auth_service;
		$this->parser = $parser;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup'                         => 'load_language',
			'core.permissions'                        => 'register_permissions',
			'core.page_header'                        => 'assign_common_vars',
			'core.posting_modify_submission_errors'   => 'validate_post_passwords',
			'core.modify_submit_post_data'            => 'canonicalize_post_on_submit',
			'core.modify_text_for_storage_before'     => 'canonicalize_post_on_storage',
			'core.viewtopic_modify_post_row'          => 'process_post_hide_and_attachments',
			'core.posting_modify_quote_text'          => 'protect_quote_and_preview',
			'core.search_modify_post_row'             => 'protect_search',
			'core.feed_modify_post_row'               => 'protect_feed',
		];
	}

	public function load_language($event)
	{
		$this->language->add_lang('common', 'gospodinenotik/advancedhide');
		$this->language->add_lang('permissions_advancedhide', 'gospodinenotik/advancedhide');
	}

	public function register_permissions($event)
	{
		$this->language->add_lang('permissions_advancedhide', 'gospodinenotik/advancedhide');
		$permissions = $event['permissions'];
		$permissions['m_hide_override'] = ['lang' => 'ACL_M_HIDE_OVERRIDE', 'cat' => 'misc'];
		$permissions['m_hide_ban']      = ['lang' => 'ACL_M_HIDE_BAN',      'cat' => 'post_actions'];
		$permissions['u_hide_use']      = ['lang' => 'ACL_U_HIDE_USE',      'cat' => 'post'];
		$permissions['u_hide_pass']     = ['lang' => 'ACL_U_HIDE_PASS',     'cat' => 'post'];
		$permissions['f_hide_post']     = ['lang' => 'ACL_F_HIDE_POST',     'cat' => 'post'];
		$event['permissions'] = $permissions;
	}

	public function assign_common_vars($event)
	{
		// Генерируем изолированные токены без вызова add_form_key, чтобы не затирать S_FORM_TOKEN в posting.php
		$now = time();
		$token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests'])) ? $this->user->session_id : '';
		$form_salt = $this->user->data['user_form_salt'] ?? '';

		$unlock_token = sha1($now . $form_salt . 'advancedhide_unlock' . $token_sid);
		$ban_token    = sha1($now . $form_salt . 'advancedhide_ban' . $token_sid);
		$report_token = sha1($now . $form_salt . 'advancedhide_report' . $token_sid);
		$appeal_token = sha1($now . $form_salt . 'advancedhide_appeal' . $token_sid);

		$modules = ['guest', 'posts', 'days', 'time', 'regdate', 'reply', 'thanks', 'groups', 'users', 'pass', 'not_groups', 'not_users'];
		$vars = [
			'ADVHIDE_CREATION_TIME'       => $now,
			'ADVHIDE_TOKEN_UNLOCK'        => $unlock_token,
			'ADVHIDE_TOKEN_BAN'           => $ban_token,
			'ADVHIDE_TOKEN_REPORT'        => $report_token,
			'ADVHIDE_TOKEN_APPEAL'        => $appeal_token,

			'U_ADVANCEDHIDE_UNLOCK'       => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/unlock'),
			'U_ADVANCEDHIDE_CHECK_UNLOCK' => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/check_unlock'),
			'U_ADVANCEDHIDE_AUDIT'        => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/audit'),
			'U_ADVANCEDHIDE_BAN'          => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/ban'),
			'U_ADVANCEDHIDE_REPORT'       => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/report'),
			'U_ADVANCEDHIDE_APPEAL'       => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/appeal'),
			'S_ADVHIDE_SHOW_BUTTONS'      => (bool)($this->config['advancedhide_show_buttons'] ?? 1),
		];

		foreach ($modules as $m)
		{
			$vars['S_ADVHIDE_MOD_' . strtoupper($m)] = $this->auth_service->is_module_enabled($m);
			$vars['ADVHIDE_ICON_' . strtoupper($m)]  = htmlspecialchars($this->config['advancedhide_icon_' . $m] ?? 'fa-eye-slash', ENT_QUOTES, 'UTF-8');
		}

		$this->template->assign_vars($vars);
	}

	public function validate_post_passwords($event)
	{
		$post_data = $event['post_data'];
		$forum_id  = (int)($event['forum_id'] ?? 0);
		$message   = !empty($post_data['message']) ? $post_data['message'] : $this->request->variable('message', '', true);

		if (empty($message) || stripos($message, '[hide') === false)
		{
			return;
		}

		$clean_message = preg_replace('/\[code(?:=[^\]]*)?\].*?\[\/code\]/is', '', $message);
		$blocks = $this->parser->parse_blocks($clean_message);

		if (!empty($blocks))
		{
			if (!$this->auth->acl_get('u_hide_use') || ($forum_id > 0 && !$this->auth->acl_get('f_hide_post', $forum_id) && !$this->auth->acl_get('a_')))
			{
				$error = $event['error'];
				$error[] = $this->language->lang('HIDE_ERROR_NO_PERMISSION');
				$event['error'] = $error;
				return;
			}
		}

		$pass_count = 0;
		foreach ($blocks as $b)
		{
			if ($b->has_password)
			{
				$pass_count++;
			}
		}

		if ($pass_count > 0 && !$this->auth->acl_get('u_hide_pass') && !$this->auth->acl_get('a_'))
		{
			$error = $event['error'];
			$error[] = $this->language->lang('HIDE_ERROR_NO_PASS_PERMISSION');
			$event['error'] = $error;
			return;
		}

		if ($pass_count > 3)
		{
			$error = $event['error'];
			$error[] = $this->language->lang('HIDE_ERROR_TOO_MANY_PASSWORDS');
			$event['error'] = $error;
		}
	}

	public function canonicalize_post_on_storage($event)
	{
		$text = $event['text'];
		if (stripos($text, '[hide') !== false || stripos($text, '<hide') !== false)
		{
			$event['text'] = $this->parser->canonicalize_and_hash($text);
		}
	}

	public function canonicalize_post_on_submit($event)
	{
		$data = $event['data'];
		if (!empty($data['message']) && (stripos($data['message'], '[hide') !== false || stripos($data['message'], '<hide') !== false))
		{
			$data['message'] = $this->parser->canonicalize_and_hash($data['message']);
			$event['data'] = $data;
		}
	}

	public function process_post_hide_and_attachments($event)
	{
		$post_row = $event['post_row'];
		$row = $event['row'];

		if (empty($row['post_text']) || (stripos($row['post_text'], '[hide') === false && stripos($row['post_text'], '<hide') === false))
		{
			return;
		}

		$blocks = $this->parser->parse_blocks($row['post_text']);
		if (empty($blocks))
		{
			return;
		}

		$context = [
			'forum_id'  => (int)$row['forum_id'],
			'topic_id'  => (int)$row['topic_id'],
			'post_id'   => (int)$row['post_id'],
			'poster_id' => (int)($event['poster_id'] ?? $row['user_id'] ?? $row['poster_id'] ?? 0),
		];

		$now = time();
		$token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests'])) ? $this->user->session_id : '';
		$form_salt = $this->user->data['user_form_salt'] ?? '';
		$unlock_token = sha1($now . $form_salt . 'advancedhide_unlock' . $token_sid);

		$locked_attachment_ids = [];
		$message = $post_row['MESSAGE'];

		$viewer_id = (int)$this->user->data['user_id'];
		$is_author = ($viewer_id > 1 && $viewer_id === $context['poster_id']);
		$is_mod    = ($this->auth->acl_get('m_hide_override', $context['forum_id']) || $this->auth->acl_get('a_'));

		foreach ($blocks as $idx => $block)
		{
			$eval = $this->auth_service->evaluate_block($block, $context);
			$is_unlocked = $this->auth_service->is_block_unlocked($block->block_hash);

			if ($eval['can_view'] || $is_unlocked)
			{
				$badge = '';
				if ($eval['override'] === 'mod')
				{
					$badge = '<span class="hide-override-badge mod">' . htmlspecialchars($this->language->lang('HIDE_OVERRIDE_MOD'), ENT_QUOTES, 'UTF-8') . '</span>';
				}
				elseif ($eval['override'] === 'author')
				{
					$badge = '<span class="hide-override-badge author">' . htmlspecialchars($this->language->lang('HIDE_OVERRIDE_AUTHOR'), ENT_QUOTES, 'UTF-8') . '</span>';
				}

				$replacement = '<div class="advancedhide-box hide-unlocked" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
					'<div class="hide-header"><i class="fa fa-unlock-alt"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_UNLOCKED'), ENT_QUOTES, 'UTF-8') . ' ' . $badge . '</div>' .
					'<div class="hide-content">' . $block->content . '</div>' .
				'</div>';
			}
			else
			{
				// Выделяем скрытые вложения с поддержкой суффикса UID
				if (preg_match_all('/\[attachment=(\d+)(?::[a-zA-Z0-9_-]+)?\]/i', $block->content, $att_matches))
				{
					foreach ($att_matches[1] as $att_idx)
					{
						$locked_attachment_ids[] = (int)$att_idx;
					}
				}

				if (!empty($eval['is_banned']))
				{
					$ban_reason = htmlspecialchars($eval['ban_info']['ban_reason'] ?? '', ENT_QUOTES, 'UTF-8');
					$replacement = '<div class="advancedhide-box hide-locked hide-banned" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
						'<div class="hide-header"><i class="fa fa-ban"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_BANNED'), ENT_QUOTES, 'UTF-8') . '</div>' .
						'<div class="hide-content">' .
							'<p class="hide-ban-reason">' . htmlspecialchars($this->language->lang('HIDE_BANNED_FROM_BLOCK', $ban_reason), ENT_QUOTES, 'UTF-8') . '</p>' .
							'<button type="button" class="button2 advhide-open-appeal-btn" data-banid="' . (int)($eval['ban_info']['ban_id'] ?? 0) . '" data-postid="' . $context['post_id'] . '">' .
								htmlspecialchars($this->language->lang('ADVHIDE_APPEAL_BTN'), ENT_QUOTES, 'UTF-8') .
							'</button>' .
						'</div>' .
					'</div>';
				}
				else
				{
					$failed_html = '';
					if (!empty($eval['failed_conditions']))
					{
						$failed_html = '<ul class="hide-failed-conditions">';
						foreach ($eval['failed_conditions'] as $fc)
						{
							$failed_html .= '<li>' . htmlspecialchars($fc, ENT_QUOTES, 'UTF-8') . '</li>';
						}
						$failed_html .= '</ul>';
					}

					$form_html = '';
					if ($block->has_password)
					{
						$form_html = '<div class="hide-password-form">' .
							'<input type="password" class="hide-pass-input inputbox autowidth" placeholder="' . htmlspecialchars($this->language->lang('HIDE_PROMPT_PASS'), ENT_QUOTES, 'UTF-8') . '" />' .
							'<button type="button" class="button2 hide-unlock-btn" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '">' .
								htmlspecialchars($this->language->lang('HIDE_UNLOCK_BTN'), ENT_QUOTES, 'UTF-8') .
							'</button>' .
							'<div class="hide-captcha-container" style="display:none; margin-top:8px;"></div>' .
						'</div>';
					}

					$audit_btn = '';
					if ($is_author || $is_mod)
					{
						$audit_btn = '<button type="button" class="button2 advhide-btn-audit" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '" style="float:right; margin-top:-2px;">' .
							'<i class="fa fa-shield"></i> ' . htmlspecialchars($this->language->lang('ADVHIDE_AUDIT_TITLE'), ENT_QUOTES, 'UTF-8') .
						'</button>';
					}

					$replacement = '<div class="advancedhide-box hide-locked" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
						'<div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . $audit_btn . '</div>' .
						'<div class="hide-content">' .
							$failed_html .
							$form_html .
							'<input type="hidden" class="hide-token" value="' . $unlock_token . '" />' .
							'<input type="hidden" class="hide-time" value="' . $now . '" />' .
						'</div>' .
					'</div>';
				}
			}

			// Заменяем блок в тексте сообщения
			$pattern = '/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is';
			if (preg_match($pattern, $message, $m, PREG_OFFSET_CAPTURE))
			{
				$message = substr_replace($message, $replacement, $m[0][1], strlen($m[0][0]));
			}
			else
			{
				$bb_pattern = '/\[hide(?:=[^\]]*)?\].*?\[\/hide\]/is';
				if (preg_match($bb_pattern, $message, $m_bb, PREG_OFFSET_CAPTURE))
				{
					$message = substr_replace($message, $replacement, $m_bb[0][1], strlen($m_bb[0][0]));
				}
			}
		}

		$post_row['MESSAGE'] = $message;

		// Защита от утечки вложений (Attachment Leakage Gap)
		if (!empty($locked_attachment_ids) && !empty($post_row['attachment_data']) && is_array($post_row['attachment_data']))
		{
			$filtered_attachments = [];
			foreach ($post_row['attachment_data'] as $att_idx => $att)
			{
				$att_id = (int)($att['ATTACH_ID'] ?? -1);
				if (!in_array($att_idx, $locked_attachment_ids, true) && !in_array($att_id, $locked_attachment_ids, true))
				{
					$filtered_attachments[] = $att;
				}
			}
			$post_row['attachment_data'] = $filtered_attachments;
			$post_row['S_HAS_ATTACHMENTS'] = !empty($filtered_attachments);
		}

		$event['post_row'] = $post_row;
	}

	public function protect_quote_and_preview($event)
	{
		$text = $event['text'];
		if (stripos($text, '[hide') !== false)
		{
			$event['text'] = preg_replace('/\[hide(?:=[^\]]*)?\].*?\[\/hide\]/is', '[hide]' . $this->language->lang('HIDE_PROTECTED_NOTICE') . '[/hide]', $text);
		}
	}

	public function protect_search($event)
	{
		$row = $event['row'];
		if (!empty($row['post_text']) && (stripos($row['post_text'], '[hide') !== false || stripos($row['post_text'], '<hide') !== false))
		{
			$row['post_text'] = preg_replace('/\[hide(?:=[^\]]*)?\].*?\[\/hide\]/is', '[' . $this->language->lang('HIDE_TITLE_LOCKED') . ']', $row['post_text']);
			$row['post_text'] = preg_replace('/<hide[^>]*>.*?<\/hide>/is', '[' . $this->language->lang('HIDE_TITLE_LOCKED') . ']', $row['post_text']);
			$event['row'] = $row;
		}
	}

	public function protect_feed($event)
	{
		$row = $event['row'];
		if (!empty($row['post_text']) && (stripos($row['post_text'], '[hide') !== false || stripos($row['post_text'], '<hide') !== false))
		{
			$row['post_text'] = preg_replace('/\[hide(?:=[^\]]*)?\].*?\[\/hide\]/is', '[' . $this->language->lang('HIDE_TITLE_LOCKED') . ']', $row['post_text']);
			$row['post_text'] = preg_replace('/<hide[^>]*>.*?<\/hide>/is', '[' . $this->language->lang('HIDE_TITLE_LOCKED') . ']', $row['post_text']);
			$event['row'] = $row;
		}
	}
}