<?php
namespace vendor\advancedhide\acp;

if (!defined('IN_PHPBB'))
{
	exit;
}

class main_module
{
	public $u_action;
	public $tpl_name;
	public $page_title;

	public function main($id, $mode)
	{
		global $config, $request, $template, $user, $language;

		$language->add_lang('info_acp_advancedhide', 'vendor/advancedhide');
		$language->add_lang('common', 'vendor/advancedhide');

		$this->tpl_name = 'acp_advancedhide';
		$this->page_title = $language->lang('ACP_ADVANCEDHIDE_TITLE');

		$modules = ['guest', 'posts', 'days', 'time', 'regdate', 'reply', 'thanks', 'groups', 'users', 'pass'];

		if ($request->is_set_post('submit'))
		{
			if (!check_form_key('acp_advancedhide'))
			{
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$author_override = $request->variable('advancedhide_author_override', 1);
			$thanks_table    = preg_replace('/[^a-zA-Z0-9_]/', '', $request->variable('advancedhide_thanks_table', ''));
			$max_blocks      = max(1, min(100, $request->variable('advancedhide_max_blocks', 20)));
			$show_buttons    = $request->variable('advancedhide_show_buttons', 1);
			$enable_captcha  = $request->variable('advancedhide_enable_captcha', 0);
			$rl_minute       = max(1, min(60, $request->variable('advancedhide_rl_minute_limit', 5)));
			$rl_day          = max(1, min(1000, $request->variable('advancedhide_rl_day_limit', 30)));

			$config->set('advancedhide_author_override', $author_override);
			$config->set('advancedhide_thanks_table', $thanks_table);
			$config->set('advancedhide_max_blocks', $max_blocks);
			$config->set('advancedhide_show_buttons', $show_buttons);
			$config->set('advancedhide_enable_captcha', $enable_captcha);
			$config->set('advancedhide_rl_minute_limit', $rl_minute);
			$config->set('advancedhide_rl_day_limit', $rl_day);

			foreach ($modules as $m)
			{
				$mod_enabled = $request->variable('advancedhide_mod_' . $m, 0);
				$raw_icon    = $request->variable('advancedhide_icon_' . $m, '');
				$clean_icon  = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $raw_icon);

				$config->set('advancedhide_mod_' . $m, $mod_enabled);
				$config->set('advancedhide_icon_' . $m, $clean_icon ?: 'fa-eye-slash');
			}

			trigger_error($language->lang('ACP_ADVANCEDHIDE_SAVED') . adm_back_link($this->u_action));
		}

		add_form_key('acp_advancedhide');

		$template_vars = [
			'AUTHOR_OVERRIDE'   => (int)$config['advancedhide_author_override'],
			'THANKS_TABLE'      => $config['advancedhide_thanks_table'],
			'MAX_BLOCKS'        => (int)$config['advancedhide_max_blocks'],
			'SHOW_BUTTONS'      => (int)($config['advancedhide_show_buttons'] ?? 1),
			'ENABLE_CAPTCHA'    => (int)($config['advancedhide_enable_captcha'] ?? 0),
			'RL_MINUTE_LIMIT'   => (int)($config['advancedhide_rl_minute_limit'] ?? 5),
			'RL_DAY_LIMIT'      => (int)($config['advancedhide_rl_day_limit'] ?? 30),
			'U_ACTION'          => $this->u_action,
		];

		foreach ($modules as $m)
		{
			$template_vars['MOD_' . strtoupper($m)]  = (int)($config['advancedhide_mod_' . $m] ?? 1);
			$template_vars['ICON_' . strtoupper($m)] = $config['advancedhide_icon_' . $m] ?? 'fa-eye-slash';
		}

		$template->assign_vars($template_vars);
	}
}