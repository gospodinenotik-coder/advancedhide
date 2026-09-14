<?php
namespace vendor\advancedhide\acp;

if (!defined('IN_PHPBB'))
{
	exit;
}

class main_info
{
	public function module()
	{
		return [
			'filename' => '\vendor\advancedhide\acp\main_module',
			'title'    => 'ACP_ADVANCEDHIDE_TITLE',
			'modes'    => [
				'settings' => [
					'title' => 'ACP_ADVANCEDHIDE_SETTINGS',
					'auth'  => 'ext_vendor/advancedhide && acl_a_board',
					'cat'   => ['ACP_ADVANCEDHIDE_TITLE'],
				],
			],
		];
	}
}