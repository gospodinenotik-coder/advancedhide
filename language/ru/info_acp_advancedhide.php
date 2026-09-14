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
	'ACP_ADVANCEDHIDE_TITLE'               => 'AdvancedHide BBCode',
	'ACP_ADVANCEDHIDE_SETTINGS'            => 'Настройки AdvancedHide',
	'ACP_ADVANCEDHIDE_EXPLAIN'             => 'Управление параметрами отображения и ограничениями скрытых блоков [hide].',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE'         => 'Видим автору сообщения',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE_EXPLAIN' => 'Разрешить автору поста всегда видеть свои скрытые блоки.',
	'ADVANCEDHIDE_THANKS_TABLE'            => 'Таблица благодарностей',
	'ADVANCEDHIDE_THANKS_TABLE_EXPLAIN'    => 'Имя таблицы в БД для проверки условий thanks (по умолчанию {PREFIX}thanks).',
	'ADVANCEDHIDE_MAX_BLOCKS'              => 'Максимум блоков в посте',
	'ADVANCEDHIDE_MAX_BLOCKS_EXPLAIN'      => 'Максимальное количество блоков [hide] в одном сообщении (по умолчанию 20).',
	'ACP_ADVANCEDHIDE_SAVED'               => 'Настройки AdvancedHide успешно сохранены.',
]);