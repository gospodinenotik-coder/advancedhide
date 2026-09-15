<?php
if (!defined('IN_PHPBB')) exit;

if (empty($lang) || !is_array($lang)) $lang = [];

$lang = array_merge($lang, [
	'ACL_M_HIDE_OVERRIDE' => 'Can view hidden content without fulfilling conditions',
]);