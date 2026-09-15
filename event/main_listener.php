<?php
namespace gospodinenotik\advancedhide\event;

if (!defined('IN_PHPBB'))
{
	exit;
}

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\config\config;
use phpbb\user;
use phpbb\auth\auth;
use phpbb\template\template;
use phpbb\request\request_interface;
use phpbb\language\language;
use phpbb\db\driver\driver_interface;
use gospodinenotik\advancedhide\service\block_parser;
use gospodinenotik\advancedhide\service\auth_service;
use s9e\TextFormatter\Configurator\Items\AttributeFilters\RegexpFilter;

class main_listener implements EventSubscriberInterface
{
	protected $config;
	protected $user;
	protected $auth;
	protected $template;
	protected $request;
	protected $language;
	protected $db;
	protected $parser;
	protected $auth_service;
	protected $phpbb_root_path;
	protected $php_ext;

	public function __construct(config $config, user $user, auth $auth, template $template, request_interface $request, language $language, driver_interface $db, block_parser $parser, auth_service $auth_service, $phpbb_root_path, $php_ext)
	{
		$this->config = $config;
		$this->user = $user;
		$this->auth = $auth;
		$this->template = $template;
		$this->request = $request;
		$this->language = $language;
		$this->db = $db;
		$this->parser = $parser;
		$this->auth_service = $auth_service;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.permissions'                          => 'register_permissions',
			'core.text_formatter_s9e_configure_before' => 'configure_bbcode',
			'core.posting_modify_submission_errors'     => 'validate_post_passwords',
			'core.modify_text_for_storage_before'       => 'canonicalize_on_storage',
			'core.modify_submit_post_data'              => 'canonicalize_on_submit',
			'core.viewtopic_modify_post_row'            => 'process_post_hide',
			'core.posting_modify_template_vars'         => 'protect_quote_and_preview',
			'core.search_modify_post_row'               => 'protect_search',
			'core.feed_modify_feed_row'                 => 'protect_feed',
			'core.user_setup'                           => 'load_language',
			'core.page_header'                          => 'assign_common_vars',
		];
	}

	public function load_language($event)
	{
		$this->language->add_lang('common', 'gospodinenotik/advancedhide');
		$this->language->add_lang('permissions_advancedhide', 'gospodinenotik/advancedhide');
	}

	public function assign_common_vars($event)
	{
		add_form_key('advancedhide_unlock');
		add_form_key('advancedhide_ban');
		add_form_key('advancedhide_report');
		add_form_key('advancedhide_appeal');

		$modules = ['guest', 'posts', 'days', 'time', 'regdate', 'reply', 'thanks', 'groups', 'users', 'not_groups', 'not_users', 'pass'];
		$now = time();
		$token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests'])) ? $this->user->session_id : '';

		$vars = [
			'U_ADVANCEDHIDE_UNLOCK'       => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/unlock'),
			'U_ADVANCEDHIDE_CHECK_UNLOCK' => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/check_unlock'),
			'U_ADVANCEDHIDE_AUDIT'        => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/audit'),
			'U_ADVANCEDHIDE_BAN'      => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/ban'),
			'U_ADVANCEDHIDE_REPORT'   => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/report'),
			'U_ADVANCEDHIDE_APPEAL'   => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/appeal'),
			'S_ADVHIDE_SHOW_BUTTONS'  => (bool)($this->config['advancedhide_show_buttons'] ?? 1),
			'S_ADVHIDE_CAN_USE'       => (bool)$this->auth->acl_get('u_hide_use'),
			'S_ADVHIDE_CAN_PASS'      => (bool)$this->auth->acl_get('u_hide_pass'),
			'ADVHIDE_TOKEN_UNLOCK'    => sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_unlock' . $token_sid),
			'ADVHIDE_TOKEN_AUDIT'     => sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_audit' . $token_sid),
			'ADVHIDE_TOKEN_BAN'       => sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_ban' . $token_sid),
			'ADVHIDE_TOKEN_REPORT'    => sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_report' . $token_sid),
			'ADVHIDE_TOKEN_APPEAL'    => sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_appeal' . $token_sid),
			'ADVHIDE_TOKEN_TIME'      => (int)$now,
		];

		foreach ($modules as $m)
		{
			$vars['S_ADVHIDE_MOD_' . strtoupper($m)]  = $this->auth_service->is_module_enabled($m);
			$vars['ADVHIDE_ICON_' . strtoupper($m)]   = htmlspecialchars($this->config['advancedhide_icon_' . $m] ?? 'fa-eye-slash', ENT_QUOTES, 'UTF-8');
		}

		$this->template->assign_vars($vars);
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

	public function configure_bbcode($event)
	{
		$configurator = $event['configurator'];
		if (isset($configurator->tags['HIDE']))
		{
			return;
		}

		$tag = $configurator->tags->add('HIDE');
		$tag->attributes->add('hide')->defaultValue = 'guest';

		$filter = new RegexpFilter('/^[a-zA-Z0-9_,;:\/\$\.\-\+ ]*$/D');
		$tag->attributes['hide']->filterChain->append($filter);
		$tag->nestingLimit = 1;

		$tag->template = '<hide cond="{@hide}"><xsl:apply-templates/></hide>';

		$configurator->BBCodes->addCustom(
			'[hide={TEXT1?}]{TEXT2}[/hide]',
			'<hide cond="{@hide}"><xsl:apply-templates/></hide>'
		);
	}

	/**
	 * Валидация прав доступа и количества парольных блоков при сохранении поста
	 */
	public function validate_post_passwords($event)
	{
		$post_data = $event['post_data'];
		$message = !empty($post_data['message']) ? $post_data['message'] : $this->request->variable('message', '', true);

		if (empty($message) || stripos($message, '[hide') === false)
		{
			return;
		}

		$forum_id = (int)($post_data['forum_id'] ?? $this->request->variable('f', 0));
		$error = $event['error'];

		// Проверка права на использование [hide]
		if (!$this->auth->acl_get('u_hide_use') || ($forum_id > 0 && !$this->auth->acl_get('f_hide_post', $forum_id) && !$this->auth->acl_get('a_')))
		{
			$error[] = $this->language->lang('HIDE_NO_POST_AUTH');
			$event['error'] = $error;
			return;
		}

		// Исключаем примеры BBCode внутри [code], чтобы они не засчитывались в лимит
		$clean_message = preg_replace('/\[code(?:=[^\]]*)?\].*?\[\/code\]/is', '', $message);
		$blocks = $this->parser->parse_blocks($clean_message);
		$pass_count = 0;
		foreach ($blocks as $b)
		{
			if ($b->has_password)
			{
				$pass_count++;
			}
		}

		// Проверка права на установку паролей
		if ($pass_count > 0 && !$this->auth->acl_get('u_hide_pass'))
		{
			$error[] = $this->language->lang('HIDE_NO_PASS_AUTH');
		}

		if ($pass_count > 3)
		{
			$error[] = $this->language->lang('HIDE_ERROR_TOO_MANY_PASSWORDS');
		}

		$event['error'] = $error;
	}

	public function canonicalize_on_storage($event)
	{
		$text = $event['text'];
		$canonical = $this->parser->canonicalize_and_hash($text);
		if ($canonical !== $text)
		{
			$event['text'] = $canonical;
		}
	}

	public function canonicalize_on_submit($event)
	{
		$data = $event['data'];
		if (!empty($data['message']))
		{
			$canonical = $this->parser->canonicalize_and_hash($data['message']);
			if ($canonical !== $data['message'])
			{
				$data['message'] = $canonical;
				$data['message_md5'] = md5($canonical);
				$event['data'] = $data;
			}
		}
	}

	public function process_post_hide($event)
	{
		$post_row = $event['post_row'];
		$row      = $event['row'];
		$text     = $post_row['MESSAGE'];

		if (stripos($text, '<hide') === false)
		{
			return;
		}

		$blocks = $this->parser->parse_blocks($row['post_text']);

		if (empty($blocks))
		{
			$post_row['MESSAGE'] = preg_replace(
				'/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is',
				'<div class="advancedhide-box hide-locked"><div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . '</div></div>',
				$text
			);
			$event['post_row'] = $post_row;
			return;
		}

		$forum_id  = (int)$row['forum_id'];
		$poster_id = (int)($event['poster_id'] ?? $row['user_id'] ?? $row['poster_id'] ?? 0);
		$viewer_id = (int)$this->user->data['user_id'];
		$is_mod    = $this->auth->acl_get('m_hide_override', $forum_id) || $this->auth->acl_get('m_hide_ban', $forum_id);
		$is_author = ($poster_id > 0 && $viewer_id === $poster_id);

		$context = [
			'forum_id'  => $forum_id,
			'topic_id'  => (int)$row['topic_id'],
			'post_id'   => (int)$row['post_id'],
			'poster_id' => $poster_id,
		];

		$now = time();
		$token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests'])) ? $this->user->session_id : '';
		$form_token = sha1($now . $this->user->data['user_form_salt'] . 'advancedhide_unlock' . $token_sid);

		$locked_attachment_ids = [];
		$locked_attachment_names = [];
		$idx = 0;
		$processed = preg_replace_callback('/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is', function($m) use ($context, $blocks, &$idx, $form_token, $now, $is_mod, $is_author, $viewer_id, &$locked_attachment_ids, &$locked_attachment_names) {
			$idx++;

			if (!isset($blocks[$idx - 1]))
			{
				return '<div class="advancedhide-box hide-locked"><div class="hide-header"><i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($this->language->lang('HIDE_LIMIT_EXCEEDED'), ENT_QUOTES, 'UTF-8') . '</div></div>';
			}

			$block = $blocks[$idx - 1];
			$eval = $this->auth_service->evaluate_block($block, $context);

			$audit_btn = '';
			if ($is_mod || $is_author)
			{
				$audit_btn = ' <button type="button" class="advhide-btn-icon advhide-btn-audit" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '" title="' . htmlspecialchars($this->language->lang('ADVHIDE_BTN_AUDIT'), ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-shield"></i></button>';
			}

			if ($eval['can_view'])
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

				return '<div class="advancedhide-box hide-unlocked" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
					'<div class="hide-header"><i class="fa fa-unlock-alt"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_UNLOCKED'), ENT_QUOTES, 'UTF-8') . ' ' . $badge . $audit_btn . '</div>' .
					'<div class="hide-content">' . $m[2] . '</div>' .
				'</div>';
			}

			// Блок заблокирован - защищаем вложения
			if (preg_match_all('/\[attachment=(\d+)(?::[^\]]*)?\](.*?)(?:\[\/attachment(?::[^\]]*)?\])?/is', $block->content, $att_m))
			{
				foreach ($att_m[1] as $aid)
				{
					$locked_attachment_ids[] = (int)$aid;
				}
				foreach ($att_m[2] as $aname)
				{
					$tname = trim(strip_tags($aname));
					if ($tname !== '')
					{
						$locked_attachment_names[] = strtolower($tname);
					}
				}
			}

			if (preg_match_all('#<!-- ia(\d+) -->([^<]+)<!-- ia\1 -->#i', $m[2], $ia_m))
			{
				foreach ($ia_m[1] as $aid)
				{
					$locked_attachment_ids[] = (int)$aid;
				}
				foreach ($ia_m[2] as $aname)
				{
					$tname = trim(strip_tags($aname));
					if ($tname !== '')
					{
						$locked_attachment_names[] = strtolower($tname);
					}
				}
			}

			if (preg_match_all('/\b([\w\.\-]+\.(?:zip|rar|7z|tar|gz|pdf|txt|docx?|xlsx?|png|jpe?g|gif))\b/i', $block->content . ' ' . $m[2], $fn_m))
			{
				foreach ($fn_m[1] as $fn)
				{
					$locked_attachment_names[] = strtolower(trim($fn));
				}
			}

			if (!empty($eval['has_disabled_module']))
			{
				$reasons_html = '<ul class="hide-reasons">';
				foreach ($eval['failed_conditions'] as $fc)
				{
					$reasons_html .= '<li>' . htmlspecialchars($fc, ENT_QUOTES, 'UTF-8') . '</li>';
				}
				$reasons_html .= '</ul>';

				return '<div class="advancedhide-box hide-locked hide-disabled" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
					'<div class="hide-header"><i class="fa fa-pause-circle"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_DISABLED'), ENT_QUOTES, 'UTF-8') . $audit_btn . '</div>' .
					'<div class="hide-body">' . $reasons_html . '</div>' .
				'</div>';
			}

			$reasons_html = '';
			if (!empty($eval['failed_conditions']))
			{
				$reasons_html = '<ul class="hide-reasons">';
				foreach ($eval['failed_conditions'] as $fc)
				{
					$reasons_html .= '<li>' . htmlspecialchars($fc, ENT_QUOTES, 'UTF-8') . '</li>';
				}
				$reasons_html .= '</ul>';
			}

			// Если пользователь персонально забанен на этот блок
			if (!empty($eval['is_banned']))
			{
				$appeal_btn = ($viewer_id > 1) ? '<div class="advhide-action-bar"><button type="button" class="button2 advhide-btn-appeal" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '"><i class="fa fa-envelope-o"></i> ' . htmlspecialchars($this->language->lang('ADVHIDE_APPEAL_BTN'), ENT_QUOTES, 'UTF-8') . '</button></div>' : '';

				return '<div class="advancedhide-box hide-locked hide-banned" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
					'<div class="hide-header"><i class="fa fa-ban"></i> ' . htmlspecialchars($this->language->lang('ADVHIDE_STATUS_BANNED'), ENT_QUOTES, 'UTF-8') . $audit_btn . '</div>' .
					'<div class="hide-body">' . $reasons_html . $appeal_btn . '</div>' .
				'</div>';
			}

			$pass_form = '';
			if ($block->has_password && $this->auth_service->is_module_enabled('pass'))
			{
				$captcha_html = $this->auth_service->generate_captcha_html();
				$report_btn = ($viewer_id > 1) ? ' <button type="button" class="advhide-btn-icon advhide-btn-report" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '" title="' . htmlspecialchars($this->language->lang('ADVHIDE_BTN_REPORT'), ENT_QUOTES, 'UTF-8') . '"><i class="fa fa-flag"></i></button>' : '';

				$pass_form = '<div class="hide-pass-form" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '">' .
					'<input type="hidden" class="hide-token" name="form_token" value="' . htmlspecialchars($form_token, ENT_QUOTES, 'UTF-8') . '" />' .
					'<input type="hidden" class="hide-creation-time" name="creation_time" value="' . (int)$now . '" />' .
					'<div class="hide-pass-row">' .
						'<input type="password" class="inputbox autowidth hide-pass-input" placeholder="' . htmlspecialchars($this->language->lang('HIDE_PASS_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '" /> ' .
						'<button type="button" class="button2 hide-pass-submit">' . htmlspecialchars($this->language->lang('HIDE_PASS_SUBMIT'), ENT_QUOTES, 'UTF-8') . '</button>' .
						$report_btn .
					'</div>' .
					'<div class="hide-captcha-slot">' . $captcha_html . '</div>' .
					'<span class="hide-pass-msg"></span>' .
				'</div>';
			}

			return '<div class="advancedhide-box hide-locked" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
				'<div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . $audit_btn . '</div>' .
				'<div class="hide-body">' . $reasons_html . $pass_form . '</div>' .
			'</div>';
		}, $text);

		// Устранение утечки вложений из закрытых блоков
		$attachments = isset($event['attachments']) && is_array($event['attachments']) ? $event['attachments'] : [];
		$post_id = (int)$row['post_id'];

		if ((!empty($locked_attachment_ids) || !empty($locked_attachment_names)) && !empty($attachments[$post_id]) && is_array($attachments[$post_id]))
		{
			foreach ($attachments[$post_id] as $k => $att)
			{
				$should_unset = false;
				if (in_array($k, $locked_attachment_ids, true))
				{
					$should_unset = true;
				}
				elseif (is_array($att))
				{
					if (isset($att['attach_id']) && in_array((int)$att['attach_id'], $locked_attachment_ids, true))
					{
						$should_unset = true;
					}
					elseif (isset($att['real_filename']) && in_array(strtolower($att['real_filename']), $locked_attachment_names, true))
					{
						$should_unset = true;
					}
				}
				elseif (is_string($att))
				{
					$att_lower = strtolower($att);
					foreach ($locked_attachment_names as $fname)
					{
						if (strpos($att_lower, $fname) !== false)
						{
							$should_unset = true;
							break;
						}
					}
					if (!$should_unset)
					{
						foreach ($locked_attachment_ids as $aid)
						{
							if (strpos($att, 'id=' . $aid) !== false)
							{
								$should_unset = true;
								break;
							}
						}
					}
				}

				if ($should_unset)
				{
					unset($attachments[$post_id][$k]);
				}
			}

			if (empty($attachments[$post_id]))
			{
				$post_row['S_HAS_ATTACHMENTS'] = false;
				$post_row['S_MULTIPLE_ATTACHMENTS'] = false;
			}
			else
			{
				$post_row['S_HAS_ATTACHMENTS'] = true;
				$post_row['S_MULTIPLE_ATTACHMENTS'] = (count($attachments[$post_id]) > 1);
			}

			$event['attachments'] = $attachments;
		}

		if (!empty($locked_attachment_ids) && isset($post_row['ATTACHMENTS']) && is_array($post_row['ATTACHMENTS']))
		{
			foreach ($post_row['ATTACHMENTS'] as $k => $att)
			{
				if (is_array($att) && isset($att['ATTACH_ID']) && in_array((int)$att['ATTACH_ID'], $locked_attachment_ids, true))
				{
					unset($post_row['ATTACHMENTS'][$k]);
				}
			}
			if (empty($post_row['ATTACHMENTS']))
			{
				$post_row['S_HAS_ATTACHMENTS'] = false;
				$post_row['S_MULTIPLE_ATTACHMENTS'] = false;
			}
		}

		$post_row['MESSAGE'] = $processed;
		$event['post_row'] = $post_row;
	}

	public function protect_quote_and_preview($event)
	{
		$mode = isset($event['mode']) ? (string)$event['mode'] : '';
		$page_data = $event['page_data'];

		if ($mode === 'quote' && !empty($page_data['MESSAGE']))
		{
			$post_data = isset($event['post_data']) && is_array($event['post_data']) ? $event['post_data'] : [];
			$poster_id = (int)($post_data['poster_id'] ?? 0);
			$viewer_id = (int)$this->user->data['user_id'];

			if ($poster_id !== $viewer_id)
			{
				$page_data['MESSAGE'] = preg_replace(
					'/(?:\[hide(=[^\]]*)?\](.*?)\[\/hide\]|<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>)/is',
					'[hide]' . $this->language->lang('HIDE_CONTENT_PROTECTED') . '[/hide]',
					$page_data['MESSAGE']
				);
				$event['page_data'] = $page_data;
			}
		}

		if (!empty($event['preview']))
		{
			$preview_text = '';
			if (!empty($page_data['PREVIEW_MESSAGE']))
			{
				$preview_text = $page_data['PREVIEW_MESSAGE'];
			}
			elseif (method_exists($this->template, 'retrieve_var'))
			{
				$preview_text = (string)$this->template->retrieve_var('PREVIEW_MESSAGE');
			}

			if ($preview_text !== '' && stripos($preview_text, '<hide') !== false)
			{
				$blocks = $this->parser->parse_blocks($preview_text);
				$context = [
					'forum_id'  => (int)($event['forum_id'] ?? 0),
					'topic_id'  => (int)($event['topic_id'] ?? 0),
					'post_id'   => 0,
					'poster_id' => 0,
				];

				$idx = 0;
				$processed_preview = preg_replace_callback(
					'/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is',
					function($m) use ($context, $blocks, &$idx) {
						$idx++;
						if (!isset($blocks[$idx - 1]))
						{
							return '<div class="advancedhide-box hide-locked"><div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . '</div></div>';
						}

						$eval = $this->auth_service->evaluate_block($blocks[$idx - 1], $context);
						if ($eval['can_view'])
						{
							return '<div class="advancedhide-box hide-unlocked"><div class="hide-header"><i class="fa fa-unlock-alt"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_UNLOCKED'), ENT_QUOTES, 'UTF-8') . '</div><div class="hide-content">' . $m[2] . '</div></div>';
						}

						$reasons_html = '';
						if (!empty($eval['failed_conditions']))
						{
							$reasons_html = '<ul class="hide-reasons">';
							foreach ($eval['failed_conditions'] as $fc)
							{
								$reasons_html .= '<li>' . htmlspecialchars($fc, ENT_QUOTES, 'UTF-8') . '</li>';
							}
							$reasons_html .= '</ul>';
						}

						return '<div class="advancedhide-box hide-locked"><div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . '</div><div class="hide-body">' . $reasons_html . '</div></div>';
					},
					$preview_text
				);

				$this->template->assign_var('PREVIEW_MESSAGE', $processed_preview);
				$page_data['PREVIEW_MESSAGE'] = $processed_preview;
				$event['page_data'] = $page_data;
			}
		}
	}

	public function protect_search($event)
	{
		$row = $event['row'];
		if (empty($row['post_text']))
		{
			return;
		}

		$raw_text = $row['post_text'];
		if (stripos($raw_text, '[hide') === false && stripos($raw_text, '<hide') === false)
		{
			return;
		}

		$context = [
			'forum_id'  => (int)($row['forum_id'] ?? 0),
			'topic_id'  => (int)($row['topic_id'] ?? 0),
			'post_id'   => (int)$row['post_id'],
			'poster_id' => (int)($row['poster_id'] ?? 0),
		];

		$blocks = $this->parser->parse_blocks($raw_text);
		if (empty($blocks))
		{
			$row['post_text'] = preg_replace(
				'/(?:\[hide(=[^\]]*)?\](.*?)\[\/hide\]|<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>)/is',
				htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8'),
				$raw_text
			);
			$event['row'] = $row;
			return;
		}

		$idx = 0;
		$row['post_text'] = preg_replace_callback(
			'/(?:\[hide(=[^\]]*)?\](.*?)\[\/hide\]|<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>)/is',
			function ($m) use ($context, $blocks, &$idx) {
				$idx++;
				if (!isset($blocks[$idx - 1]))
				{
					return htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8');
				}

				$eval = $this->auth_service->evaluate_block($blocks[$idx - 1], $context);
				if (!$eval['can_view'])
				{
					return htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8');
				}

				return isset($m[2]) && $m[2] !== '' ? $m[2] : ($m[4] ?? '');
			},
			$raw_text
		);

		$event['row'] = $row;
	}

	public function protect_feed($event)
	{
		$feed = $event['feed'];
		$row = $event['row'];

		$text_key = $feed->get('text');
		if ($text_key === null || empty($row[$text_key]) || empty($row['post_id']))
		{
			return;
		}

		$raw_text = $row[$text_key];
		if (stripos($raw_text, '[hide') === false && stripos($raw_text, '<hide') === false)
		{
			return;
		}

		$context = [
			'forum_id'  => (int)($row['forum_id'] ?? 0),
			'topic_id'  => (int)($row['topic_id'] ?? 0),
			'post_id'   => (int)$row['post_id'],
			'poster_id' => (int)($row['poster_id'] ?? 0),
		];

		$blocks = $this->parser->parse_blocks($raw_text);
		if (empty($blocks))
		{
			$row[$text_key] = preg_replace(
				'/(?:\[hide(=[^\]]*)?\](.*?)\[\/hide\]|<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>)/is',
				htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8'),
				$raw_text
			);
			$event['row'] = $row;
			return;
		}

		$idx = 0;
		$row[$text_key] = preg_replace_callback(
			'/(?:\[hide(=[^\]]*)?\](.*?)\[\/hide\]|<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>)/is',
			function ($m) use ($context, $blocks, &$idx) {
				$idx++;
				if (!isset($blocks[$idx - 1]))
				{
					return htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8');
				}

				$eval = $this->auth_service->evaluate_block($blocks[$idx - 1], $context);
				if (!$eval['can_view'])
				{
					return htmlspecialchars($this->language->lang('HIDE_CONTENT_PROTECTED'), ENT_QUOTES, 'UTF-8');
				}

				return isset($m[2]) && $m[2] !== '' ? $m[2] : ($m[4] ?? '');
			},
			$raw_text
		);

		$event['row'] = $row;
	}
}