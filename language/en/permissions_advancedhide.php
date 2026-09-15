<?php
if (!defined('IN_PHPBB')) exit;

if (empty($lang) || !is_array($lang)) $lang = [];

$lang = array_merge($lang, [
	'ACL_M_HIDE_OVERRIDE' => 'Can view hidden content without fulfilling conditions',
	'ACL_U_HIDE_USE'      => 'Can use [hide] BBCode',
	'ACL_U_HIDE_PASS'     => 'Can set passwords on hidden blocks',
	'ACL_F_HIDE_POST'     => 'Can use hidden content in this forum',
	'ACL_M_HIDE_BAN'      => 'Can ban users from hidden content blocks',
]);