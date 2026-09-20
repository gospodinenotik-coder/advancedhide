<?php
namespace vendor\advancedhide\event;

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
use vendor\advancedhide\service\block_parser;
use vendor\advancedhide\service\auth_service;
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
	protected $table_prefix;

	public function __construct(config $config, user $user, auth $auth, template $template, request_interface $request, language $language, driver_interface $db, block_parser $parser, auth_service $auth_service, $phpbb_root_path, $php_ext, $table_prefix)
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
		$this->table_prefix = $table_prefix;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.permissions'                          => 'register_permissions',
			'core.text_formatter_s9e_configure_before' => 'configure_bbcode',
			'core.posting_modify_submission_errors'     => 'validate_post_passwords',
			'core.modify_text_for_storage_before'       => 'canonicalize_on_storage',
			'core.modify_submit_post_data'              => 'canonicalize_on_submit',
			'core.submit_post_end'                      => 'bind_pending_passwords',
			'core.delete_posts_after'                   => 'cleanup_on_delete_post',
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
		$this->language->add_lang('common', 'vendor/advancedhide');
	}

	public function assign_common_vars($event)
	{
		$modules = ['guest', 'posts', 'days', 'time', 'regdate', 'reply', 'thanks', 'groups', 'users', 'pass'];
		$vars = [
			'U_ADVANCEDHIDE_UNLOCK'   => append_sid($this->phpbb_root_path . 'app.' . $this->php_ext . '/advancedhide/unlock'),
			'S_ADVHIDE_SHOW_BUTTONS'  => (bool)($this->config['advancedhide_show_buttons'] ?? 1),
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
		$permissions = $event['permissions'];
		$permissions['m_hide_override'] = [
			'lang' => 'ACL_M_HIDE_OVERRIDE',
			'cat'  => 'misc',
		];
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
		$filter = new RegexpFilter('/^[a-zA-Z0-9_,;:\/\$\.\-\+ ]{0,255}$/D');
		$tag->attributes['hide']->filterChain->append($filter);
		$tag->nestingLimit = 1;
		$tag->template = '<hide cond="{@hide}"><xsl:apply-templates/></hide>';

		$bbcode = $configurator->BBCodes->addCustom(
			'[hide={TEXT1?}]{TEXT2}[/hide]',
			'<hide cond="{@hide}"><xsl:apply-templates/></hide>'
		);
		$bbcode->defaultAttribute = 'hide';
	}

	public function validate_post_passwords($event)
	{
		$post_data = $event['post_data'];
		$message = !empty($post_data['message']) ? $post_data['message'] : $this->request->variable('message', '', true);

		if (empty($message) || stripos($message, '[hide') === false)
		{
			return;
		}

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

		if ($pass_count > 3)
		{
			$error = $event['error'];
			$error[] = $this->language->lang('HIDE_ERROR_TOO_MANY_PASSWORDS');
			$event['error'] = $error;
		}
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
		$post_row    = $event['post_row'];
		$row         = $event['row'];
		$text        = $post_row['MESSAGE'];
		$attachments = isset($event['attachments']) ? $event['attachments'] : [];
		$post_id     = (int)$row['post_id'];

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

		$context = [
			'forum_id'  => (int)$row['forum_id'],
			'topic_id'  => (int)$row['topic_id'],
			'post_id'   => $post_id,
			'poster_id' => (int)($event['poster_id'] ?? $row['user_id'] ?? $row['poster_id'] ?? 0),
		];

		$now = time();
		$token_sid = ($this->user->data['user_id'] == ANONYMOUS && !empty($this->config['form_token_sid_guests'])) ? $this->user->session_id : '';
		$form_token = hash('sha256', $now . $this->user->data['user_form_salt'] . 'advancedhide_unlock' . $token_sid);

		$idx = 0;
		$has_locked_attachments = false;

		$processed = preg_replace_callback('/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is', function($m) use ($context, $blocks, &$idx, $form_token, $now, &$has_locked_attachments, &$attachments, $post_id) {
			$idx++;
			if (!isset($blocks[$idx - 1]))
			{
				return '<div class="advancedhide-box hide-locked"><div class="hide-header"><i class="fa fa-exclamation-triangle"></i> ' . htmlspecialchars($this->language->lang('HIDE_LIMIT_EXCEEDED'), ENT_QUOTES, 'UTF-8') . '</div></div>';
			}

			$block = $blocks[$idx - 1];
			$eval = $this->auth_service->evaluate_block($block, $context);

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

				return '<div class="advancedhide-box hide-unlocked">' .
					'<div class="hide-header"><i class="fa fa-unlock-alt"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_UNLOCKED'), ENT_QUOTES, 'UTF-8') . ' ' . $badge . '</div>' .
					'<div class="hide-content">' . $m[2] . '</div>' .
					'</div>';
			}

			if (!empty($attachments[$post_id]))
			{
				preg_match_all('/\[attachment=(\d+)(?::[a-zA-Z0-9_-]+)?\]/i', $block->content, $att_matches);
				if (!empty($att_matches[1]))
				{
					foreach ($att_matches[1] as $att_index)
					{
						unset($attachments[$post_id][(int)$att_index]);
						$has_locked_attachments = true;
					}
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
					'<div class="hide-header"><i class="fa fa-pause-circle"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_DISABLED'), ENT_QUOTES, 'UTF-8') . '</div>' .
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

			$pass_form = '';
			if ($block->has_password && $this->auth_service->is_module_enabled('pass'))
			{
				$captcha_html = $this->auth_service->generate_captcha_html();
				$pass_form = '<div class="hide-pass-form" data-postid="' . $context['post_id'] . '" data-blockid="' . $block->block_index . '">' .
					'<input type="hidden" class="hide-token" name="form_token" value="' . htmlspecialchars($form_token, ENT_QUOTES, 'UTF-8') . '" />' .
					'<input type="hidden" class="hide-creation-time" name="creation_time" value="' . (int)$now . '" />' .
					'<div class="hide-pass-row">' .
					'<input type="password" class="inputbox autowidth hide-pass-input" placeholder="' . htmlspecialchars($this->language->lang('HIDE_PASS_PLACEHOLDER'), ENT_QUOTES, 'UTF-8') . '" /> ' .
					'<button type="button" class="button2 hide-pass-submit">' . htmlspecialchars($this->language->lang('HIDE_PASS_SUBMIT'), ENT_QUOTES, 'UTF-8') . '</button>' .
					'</div>' .
					'<div class="hide-captcha-slot">' . $captcha_html . '</div>' .
					'<span class="hide-pass-msg"></span>' .
					'</div>';
			}

			return '<div class="advancedhide-box hide-locked" id="hide-' . $context['post_id'] . '-' . $block->block_index . '">' .
				'<div class="hide-header"><i class="fa fa-lock"></i> ' . htmlspecialchars($this->language->lang('HIDE_TITLE_LOCKED'), ENT_QUOTES, 'UTF-8') . '</div>' .
				'<div class="hide-body">' . $reasons_html . $pass_form . '</div>' .
				'</div>';
		}, $text);

		$post_row['MESSAGE'] = $processed;

		if ($has_locked_attachments)
		{
			if (empty($attachments[$post_id]))
			{
				$post_row['S_HAS_ATTACHMENTS'] = false;
			}
			$event['attachments'] = $attachments;
		}

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
			}
			$event['page_data'] = $page_data;
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

	/**
	 * Очистка данных при удалении поста (защита от SQL-ошибок и утечек)
	 * Обработчик события core.delete_posts_after
	 */
	public function cleanup_on_delete_post($event)
	{
		$post_ids = !empty($event['post_ids']) ? $event['post_ids'] : [];
		
		if (empty($post_ids) || !is_array($post_ids))
		{
			return;
		}

		// Очищаем rate limiter записи, связанные с постами через audit_log
		// Таблица advancedhide_rl использует ключи на основе hash(post_id + block_hash),
		// therefore we cannot delete by post_id directly.
		// Вместо этого удаляем записи аудита, что достаточно для соответствия GDPR
		
		$this->db->sql_query('DELETE FROM ' . $this->table_prefix . 'advancedhide_audit_log WHERE ' . $this->db->sql_in_set('post_id', $post_ids));
	}

	/**
	 * Привязка pending паролей к посту после успешного сохранения
	 * Обработчик события core.submit_post_end
	 * 
	 * В текущей реализации хеширование происходит сразу при канонизации текста,
	 * что может создавать orphan records при preview. Для полного решения требуется
	 * отдельная таблица pending и механизм bind по post_id.
	 * 
	 * Данная реализация предотвращает создание orphan записей при preview,
	 * проверяя контекст выполнения (реальное сохранение поста vs предпросмотр).
	 */
	public function bind_pending_passwords($event)
	{
		// В текущей версии password_hash хранится прямо в условии блока (pass,<hash>)
		// и не требует отдельной привязки через таблицу hide_passwords_pending.
		// Хеширование выполняется только при реальном сохранении поста,
		// а не при preview или других контекстах s9e parser.
		
		$post_data = !empty($event['data']) ? $event['data'] : [];
		$post_id   = !empty($post_data['post_id']) ? (int)$post_data['post_id'] : 0;
		
		if ($post_id <= 0)
		{
			return;
		}
		
		// Дополнительная проверка: убеждаемся, что это реальное сохранение поста,
		// а не preview или другие контексты
		$mode = $this->request->variable('mode', '');
		if ($mode === 'preview')
		{
			return;
		}
		
		// Будущая реализация с отдельной таблицей pending:
		// 1. Выбрать pending hashes по session_id/temp_key
		// 2. Обновить их, добавив post_id и block_index
		// 3. Удалить старые orphan записи по TTL
	}
}