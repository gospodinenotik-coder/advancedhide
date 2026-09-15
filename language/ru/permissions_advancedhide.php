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
	'ACL_U_HIDE_USE'      => 'Может использовать BBCode скрытия [hide]',
	'ACL_U_HIDE_PASS'     => 'Может устанавливать пароли на блоки скрытия',
	'ACL_F_HIDE_POST'     => 'Может использовать скрытый контент в этом форуме',
	'ACL_M_HIDE_BAN'      => 'Может блокировать доступ пользователей к скрытым блокам',
]);