<?php
namespace gospodinenotik\advancedhide\acp;

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
		global $config, $request, $template, $user, $language, $db, $table_prefix, $phpbb_container, $phpbb_root_path, $php_ext;

		$language->add_lang('info_acp_advancedhide', 'gospodinenotik/advancedhide');
		$language->add_lang('common', 'gospodinenotik/advancedhide');

		$modules = ['guest', 'posts', 'days', 'time', 'regdate', 'reply', 'thanks', 'groups', 'users', 'not_groups', 'not_users', 'pass'];

		if ($mode === 'audit')
		{
			$this->tpl_name = 'acp_advancedhide_audit';
			$this->page_title = $language->lang('ACP_ADVANCEDHIDE_AUDIT');

			$action = $request->variable('action', '');
			$ban_id = $request->variable('ban_id', 0);
			$report_id = $request->variable('report_id', 0);

			if ($action === 'prune' && check_link_hash($request->variable('hash', ''), 'prune_advhide_logs'))
			{
				$threshold = time() - (30 * 86400);
				$sql = 'DELETE FROM ' . $table_prefix . 'advancedhide_logs WHERE attempt_time < ' . (int)$threshold;
				$db->sql_query($sql);
				trigger_error($language->lang('ADVHIDE_PRUNE_SUCCESS') . adm_back_link($this->u_action));
			}

			if ($action === 'unban' && $ban_id > 0 && (check_link_hash($request->variable('hash', ''), 'unban_advhide_' . $ban_id) || check_link_hash($request->variable('hash', ''), 'advhide_appeal' . $ban_id)))
			{
				$sql = 'DELETE FROM ' . $table_prefix . 'advancedhide_bans WHERE ban_id = ' . (int)$ban_id;
				$db->sql_query($sql);
				trigger_error($language->lang('ADVHIDE_UNBAN_SUCCESS') . adm_back_link($this->u_action));
			}

			if ($action === 'reject_appeal' && $ban_id > 0 && (check_link_hash($request->variable('hash', ''), 'reject_advhide_' . $ban_id) || check_link_hash($request->variable('hash', ''), 'advhide_appeal' . $ban_id)))
			{
				$sql = "UPDATE " . $table_prefix . "advancedhide_bans SET appeal_status = 'rejected' WHERE ban_id = " . (int)$ban_id;
				$db->sql_query($sql);
				trigger_error($language->lang('ADVHIDE_APPEAL_REJECTED') . adm_back_link($this->u_action));
			}

			if ($action === 'close_report' && $report_id > 0 && check_link_hash($request->variable('hash', ''), 'close_advhide_rep_' . $report_id))
			{
				$sql = 'UPDATE ' . $table_prefix . 'advancedhide_reports SET report_closed = 1 WHERE report_id = ' . (int)$report_id;
				$db->sql_query($sql);
				trigger_error($language->lang('ADVHIDE_REPORT_CLOSED') . adm_back_link($this->u_action));
			}

			// Параметры фильтрации и пагинации
			$start = $request->variable('start', 0);
			$limit = 25;
			$status_filter = $request->variable('status_filter', $request->variable('filter_status', ''));
			$sql_where = '';

			if ($status_filter !== '')
			{
				$sql_where = ' WHERE l.status = \'' . $db->sql_escape($status_filter) . '\'';
			}

			// Подсчет общего количества записей
			$sql_count = 'SELECT COUNT(l.log_id) AS total_logs FROM ' . $table_prefix . 'advancedhide_logs l' . $sql_where;
			$res_count = $db->sql_query($sql_count);
			$total_logs = (int)$db->sql_fetchfield('total_logs');
			$db->sql_freeresult($res_count);

			$sql = 'SELECT l.*, u.username, u.user_colour FROM ' . $table_prefix . 'advancedhide_logs l
				LEFT JOIN ' . USERS_TABLE . ' u ON (l.user_id = u.user_id)
				' . $sql_where . '
				ORDER BY l.attempt_time DESC';
			$res = $db->sql_query_limit($sql, $limit, $start);

			while ($row = $db->sql_fetchrow($res))
			{
				$status_key = 'ADVHIDE_AUDIT_' . strtoupper($row['status']);
				$status_text = $language->is_set($status_key) ? $language->lang($status_key) : $row['status'];

				$details_formatted = '';
				if (!empty($row['details_json']))
				{
					$ts_ary = json_decode($row['details_json'], true);
					if (is_array($ts_ary))
					{
						$formatted_list = array_map(function($t) use ($user) {
							if (is_array($t))
							{
								$time_str = !empty($t['time']) ? $user->format_date((int)$t['time']) : '';
								$ip_str   = !empty($t['ip']) ? htmlspecialchars($t['ip'], ENT_QUOTES, 'UTF-8') : '';
								$pass_str = !empty($t['pass']) ? htmlspecialchars($t['pass'], ENT_QUOTES, 'UTF-8') : '';
								return trim("{$time_str} | IP: {$ip_str} | {$pass_str}", ' |');
							}
							return is_numeric($t) ? $user->format_date((int)$t) : (string)$t;
						}, $ts_ary);
						$details_formatted = implode('<br />', $formatted_list);
					}
				}

				$template->assign_block_vars('logs', [
					'LOG_ID'            => (int)$row['log_id'],
					'POST_ID'           => (int)$row['post_id'],
					'BLOCK_INDEX'       => (int)$row['block_index'],
					'USERNAME'          => !empty($row['username']) ? get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']) : $language->lang('GUEST'),
					'USER_IP'           => $row['user_ip'],
					'TIME'              => $user->format_date($row['attempt_time']),
					'STATUS'            => $status_text,
					'STATUS_RAW'        => $row['status'],
					'ATTEMPT_COUNT'     => (int)$row['attempt_count'],
					'AGGREGATED_COUNT'  => (int)($row['aggregated_count'] ?? $row['attempt_count'] ?? 1),
					'DETAILS_JSON'      => !empty($row['details_json']) ? $row['details_json'] : '',
					'DETAILS_FORMATTED' => $details_formatted,
					'MASKED_PASS'       => $row['masked_pass'],
					'PASSWORD_USED'     => !empty($row['password_used']) ? $row['password_used'] : $row['masked_pass'],
					'U_POST'            => append_sid("{$phpbb_root_path}viewtopic.$php_ext", 'p=' . $row['post_id'] . '#p' . $row['post_id']),
				]);
			}
			$db->sql_freeresult($res);

			// Построение пагинатора phpBB
			if ($phpbb_container && $phpbb_container->has('pagination'))
			{
				$pagination = $phpbb_container->get('pagination');
				$base_url = $this->u_action . '&amp;action=audit' . ($status_filter !== '' ? '&amp;status_filter=' . urlencode($status_filter) : '');
				$pagination->generate_template_pagination($base_url, 'pagination', 'start', $total_logs, $limit, $start);
			}

			$template->assign_vars([
				'S_STATUS_FILTER' => $status_filter,
				'FILTER_STATUS'   => $status_filter,
				'TOTAL_LOGS'      => $total_logs,
				'U_ACTION_FILTER' => $this->u_action . '&amp;action=audit',
			]);

			// Запрос банов
			$sql_b = 'SELECT b.*, u.username, u.user_colour, mb.username AS mod_username, mb.user_colour AS mod_colour
				FROM ' . $table_prefix . 'advancedhide_bans b
				LEFT JOIN ' . USERS_TABLE . ' u ON (b.user_id = u.user_id)
				LEFT JOIN ' . USERS_TABLE . ' mb ON (b.banned_by = mb.user_id)
				ORDER BY b.ban_start DESC';
			$res_b = $db->sql_query($sql_b);

			while ($row_b = $db->sql_fetchrow($res_b))
			{
				$template->assign_block_vars('bans', [
					'BAN_ID'        => (int)$row_b['ban_id'],
					'POST_ID'       => (int)$row_b['post_id'],
					'BLOCK_INDEX'   => (int)$row_b['block_index'],
					'USERNAME'      => get_username_string('full', $row_b['user_id'], $row_b['username'], $row_b['user_colour']),
					'MOD_USERNAME'  => get_username_string('full', $row_b['banned_by'], $row_b['mod_username'], $row_b['mod_colour']),
					'BAN_START'     => $user->format_date($row_b['ban_start']),
					'BAN_END'       => $row_b['ban_end'] > 0 ? $user->format_date($row_b['ban_end']) : $language->lang('ADVHIDE_BAN_PERMANENT'),
					'REASON'        => $row_b['ban_reason'],
					'APPEAL_STATUS' => $row_b['appeal_status'] ?? '',
					'APPEAL_REASON' => $row_b['appeal_reason'] ?? '',
					'APPEAL_TIME'   => !empty($row_b['appeal_time']) ? $user->format_date($row_b['appeal_time']) : '',
					'U_UNBAN'       => $this->u_action . '&amp;action=unban&amp;ban_id=' . $row_b['ban_id'] . '&amp;hash=' . generate_link_hash('unban_advhide_' . $row_b['ban_id']),
					'U_REJECT'      => $this->u_action . '&amp;action=reject_appeal&amp;ban_id=' . $row_b['ban_id'] . '&amp;hash=' . generate_link_hash('reject_advhide_' . $row_b['ban_id']),
				]);
			}
			$db->sql_freeresult($res_b);

			// Запрос жалоб и апелляций
			$sql_r = 'SELECT r.*, u.username, u.user_colour
				FROM ' . $table_prefix . 'advancedhide_reports r
				LEFT JOIN ' . USERS_TABLE . ' u ON (r.reporter_id = u.user_id)
				WHERE r.report_closed = 0
				ORDER BY r.report_time DESC';
			$res_r = $db->sql_query($sql_r);

			while ($row_r = $db->sql_fetchrow($res_r))
			{
				$template->assign_block_vars('reports', [
					'REPORT_ID'   => (int)$row_r['report_id'],
					'POST_ID'     => (int)$row_r['post_id'],
					'BLOCK_INDEX' => (int)$row_r['block_index'],
					'REPORTER'    => get_username_string('full', $row_r['reporter_id'], $row_r['username'], $row_r['user_colour']),
					'TIME'        => $user->format_date($row_r['report_time']),
					'REASON'      => $row_r['report_reason'],
					'U_CLOSE'     => $this->u_action . '&amp;action=close_report&amp;report_id=' . $row_r['report_id'] . '&amp;hash=' . generate_link_hash('close_advhide_rep_' . $row_r['report_id']),
				]);
			}
			$db->sql_freeresult($res_r);

			$template->assign_vars([
				'U_ACTION'        => $this->u_action,
				'U_PRUNE'         => $this->u_action . '&amp;action=prune&amp;hash=' . generate_link_hash('prune_advhide_logs'),
				'FILTER_STATUS'   => $filter_status,
			]);

			return;
		}

		$this->tpl_name = 'acp_advancedhide';
		$this->page_title = $language->lang('ACP_ADVANCEDHIDE_TITLE');

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

			// Сохранение активности модулей и санитизированных иконок
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