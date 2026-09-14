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

	'ADVANCEDHIDE_SHOW_BUTTONS'         => 'Отображать кнопки в редакторе',
	'ADVANCEDHIDE_SHOW_BUTTONS_EXPLAIN' => 'Показывать панель кнопок [hide] над полем ввода сообщения.',
	'ADVANCEDHIDE_SECURITY_LEGEND'      => 'Безопасность и защита от подбора',
	'ADVANCEDHIDE_ENABLE_CAPTCHA'       => 'Включить системную Captcha',
	'ADVANCEDHIDE_ENABLE_CAPTCHA_EXPLAIN' => 'Использовать системный плагин Captcha (настроенный в ACP -> Противодействие спам-ботам) при попытке разблокировки паролем.',
	'ADVANCEDHIDE_RL_MINUTE'            => 'Лимит попыток в минуту',
	'ADVANCEDHIDE_RL_MINUTE_EXPLAIN'    => 'Максимум попыток ввода пароля к одному блоку за 1 минуту.',
	'ADVANCEDHIDE_RL_DAY'               => 'Лимит попыток в сутки',
	'ADVANCEDHIDE_RL_DAY_EXPLAIN'       => 'Максимум попыток ввода пароля к одному блоку за 24 часа.',
	'ADVANCEDHIDE_MODULES_LEGEND'       => 'Управление модулями скрытия и иконками кнопок',
	'ADVANCEDHIDE_MODULES_EXPLAIN'      => 'Отключенные модули скрываются из редактора сообщений. При обнаружении отключенного модуля на форуме отображается заглушка, а контент блокируется.',
	'ADVANCEDHIDE_TH_MODULE'            => 'Модуль',
	'ADVANCEDHIDE_TH_STATUS'            => 'Статус',
	'ADVANCEDHIDE_TH_ICON'              => 'Иконка FontAwesome',
	'ADVANCEDHIDE_TH_SETTINGS'          => 'Параметры модуля',
]);