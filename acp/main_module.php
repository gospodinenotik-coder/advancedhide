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
		$this->tpl_name = 'acp_advancedhide';
		$this->page_title = $language->lang('ACP_ADVANCEDHIDE_TITLE');

		if ($request->is_set_post('submit')) {
			if (!check_form_key('acp_advancedhide')) {
				trigger_error($language->lang('FORM_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
			}

			$author_override = $request->variable('advancedhide_author_override', 1);
			$thanks_table    = $request->variable('advancedhide_thanks_table', '');
			$max_blocks      = $request->variable('advancedhide_max_blocks', 20);

			$config->set('advancedhide_author_override', $author_override);
			$config->set('advancedhide_thanks_table', $thanks_table);
			$config->set('advancedhide_max_blocks', $max_blocks);

			trigger_error($language->lang('ACP_ADVANCEDHIDE_SAVED') . adm_back_link($this->u_action));
		}

		add_form_key('acp_advancedhide');

		$template->assign_vars([
			'AUTHOR_OVERRIDE' => $config['advancedhide_author_override'],
			'THANKS_TABLE'    => $config['advancedhide_thanks_table'],
			'MAX_BLOCKS'      => $config['advancedhide_max_blocks'],
			'U_ACTION'        => $this->u_action,
		]);
	}
}