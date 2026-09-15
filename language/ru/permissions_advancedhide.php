<?php
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = [];
}

$lang = array_merge($lang, [
	'ACL_M_HIDE_OVERRIDE' => 'Может просматривать скрытый контент без выполнения условий',
]);