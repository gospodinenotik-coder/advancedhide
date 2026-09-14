<?php
namespace vendor\advancedhide\service;

if (!defined('IN_PHPBB'))
{
	exit;
}

use phpbb\config\config;

class hide_block
{
	public $block_index;
	public $cond_str;
	public $content;
	public $condition_hash;
	public $content_hash;
	public $block_hash;
	public $normalized_conditions = [];
	public $has_password = false;
	public $password_hash = '';

	public function __construct($index, $cond_str, $content)
	{
		$this->block_index = (int)$index;
		$this->cond_str    = substr(trim($cond_str), 0, 255);
		$this->content     = $content;

		$this->condition_hash = hash('sha256', $this->cond_str);
		$this->content_hash   = hash('sha256', $content);
		$this->block_hash     = hash('sha256', $this->block_index . '_' . $this->condition_hash . '_' . $this->content_hash);

		$this->parse_conditions();
	}

	protected function parse_conditions()
	{
		$raw_conds = ($this->cond_str === '' || strtolower($this->cond_str) === 'guest') ? ['guest'] : explode(';', $this->cond_str);
		if (count($raw_conds) > 5)
		{
			$raw_conds = array_slice($raw_conds, 0, 5);
		}

		foreach ($raw_conds as $rc)
		{
			$sub = explode(',', trim($rc));
			$type = strtolower(trim($sub[0] ?? ''));
			$args = array_map('trim', array_slice($sub, 1));

			if ($type === 'pass')
			{
				$this->has_password = true;
				$this->password_hash = implode(',', $args);
			}

			$this->normalized_conditions[] = [
				'type' => $type,
				'args' => $args
			];
		}
	}
}

class block_parser
{
	protected $config;
	protected $phpbb_root_path;
	protected $php_ext;

	public function __construct(config $config, $phpbb_root_path, $php_ext)
	{
		$this->config = $config;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
	}

	public function parse_blocks($text)
	{
		if (empty($text) || strlen($text) > 500000)
		{
			return [];
		}

		$is_xml    = (stripos($text, '<hide') !== false);
		$is_bbcode = (stripos($text, '[hide') !== false);

		if (!$is_xml && !$is_bbcode)
		{
			return [];
		}

		$max_blocks = (int)($this->config['advancedhide_max_blocks'] ?: 20);
		$lower_text = strtolower($text);
		$opener_count = substr_count($lower_text, '[hide') + substr_count($lower_text, '<hide');
		if ($opener_count > $max_blocks * 3)
		{
			return [];
		}

		// Захватывает как теги с атрибутами (<HIDE hide="...">, <hide cond="...">), так и тег по умолчанию (<HIDE>)
		if ($is_xml)
		{
			$pattern = '/<(?:hide)(?:\s+[^>]*(?:cond|hide)="([^"]*)")?[^>]*>(.*?)<\/(?:hide)>/is';
		}
		else
		{
			$pattern = '/\[hide(=[^\]]*)?\](.*?)\[\/hide\]/is';
		}

		$ok = preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
		if ($ok === false || $ok === 0)
		{
			return [];
		}

		$blocks = [];
		$counter = 0;
		foreach ($matches as $m)
		{
			$counter++;
			if ($counter > $max_blocks)
			{
				break;
			}

			if ($is_xml)
			{
				$cond_str = !empty($m[1]) ? $m[1] : 'guest';
				$content  = $m[2] ?? '';
			}
			else
			{
				$cond_str = isset($m[1]) ? ltrim($m[1], '=') : '';
				$content  = $m[2] ?? '';
			}

			$blocks[] = new hide_block($counter, $cond_str, $content);
		}

		return $blocks;
	}

	public function canonicalize_cond_string($cond_str, array &$pass_cache = [])
	{
		$cond_str = substr(trim($cond_str), 0, 255);
		if ($cond_str === '' || strtolower($cond_str) === 'guest')
		{
			return 'guest';
		}

		$parts = explode(';', $cond_str);
		if (count($parts) > 5)
		{
			$parts = array_slice($parts, 0, 5);
		}

		$new_parts = [];
		$pass_counter = 0;

		foreach ($parts as $part)
		{
			$sub = explode(',', trim($part));
			$type = strtolower(trim($sub[0] ?? ''));

			if ($type === 'users' && count($sub) > 1)
			{
				$uids = [];
				$usernames = [];
				foreach (array_slice($sub, 1) as $u)
				{
					$u = trim($u);
					if (is_numeric($u) && (int)$u > 0)
					{
						$uids[] = (int)$u;
					}
					elseif ($u !== '')
					{
						$usernames[] = $u;
					}
				}

				if (!empty($usernames))
				{
					if (!function_exists('user_get_id_name'))
					{
						include_once($this->phpbb_root_path . 'includes/functions_user.' . $this->php_ext);
					}
					$found_uids = [];
					user_get_id_name($found_uids, $usernames);
					if (!empty($found_uids))
					{
						foreach ($found_uids as $uid)
						{
							if ((int)$uid > 0)
							{
								$uids[] = (int)$uid;
							}
						}
					}
				}
				$uids = array_unique(array_filter($uids));
				$new_parts[] = 'users,' . (!empty($uids) ? implode(',', $uids) : '0');
			}
			elseif ($type === 'pass')
			{
				$pass_counter++;
				if ($pass_counter > 2)
				{
					$new_parts[] = 'pass_limit_exceeded';
					continue;
				}

				$plain_pass = trim(implode(',', array_slice($sub, 1)));
				if ($plain_pass === '')
				{
					$new_parts[] = 'pass,';
					continue;
				}

				if (isset($pass_cache[$plain_pass]))
				{
					$new_parts[] = 'pass,' . $pass_cache[$plain_pass];
					continue;
				}

				$info = password_get_info($plain_pass);
				if ($info['algoName'] === 'unknown')
				{
					$hashed = password_hash($plain_pass, PASSWORD_DEFAULT);
					$pass_cache[$plain_pass] = $hashed;
					$new_parts[] = 'pass,' . $hashed;
				}
				else
				{
					$new_parts[] = 'pass,' . $plain_pass;
				}
			}
			else
			{
				$new_parts[] = trim($part);
			}
		}

		return implode(';', $new_parts);
	}

	public function canonicalize_and_hash($text)
	{
		if (empty($text) || strlen($text) > 500000)
		{
			return $text;
		}

		if (stripos($text, '[hide') === false && stripos($text, '<hide') === false)
		{
			return $text;
		}

		$pass_cache = [];

		// 1. Хэширование и разрешение ников в BBCode: [hide=...]
		$text = preg_replace_callback('/\[hide(=[^\]]*)?\]/i', function($matches) use (&$pass_cache) {
			$raw_param = isset($matches[1]) ? ltrim($matches[1], '=') : '';
			$new_cond_str = $this->canonicalize_cond_string($raw_param, $pass_cache);
			return '[hide=' . $new_cond_str . ']';
		}, $text);

		// 2. Хэширование и разрешение ников в s9e XML: <HIDE hide="..."> и <hide cond="...">
		$text = preg_replace_callback('/(<(?:hide)\s+[^>]*(?:cond|hide)=")([^"]*)(")/i', function($matches) use (&$pass_cache) {
			$new_cond_str = $this->canonicalize_cond_string($matches[2], $pass_cache);
			return $matches[1] . $new_cond_str . $matches[3];
		}, $text);

		return $text;
	}
}