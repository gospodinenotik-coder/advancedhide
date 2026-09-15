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
	'ACP_ADVANCEDHIDE_EXPLAIN'             => 'Управление параметрами и ограничениями блоков [hide].',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE'         => 'Видим автору сообщения',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE_EXPLAIN' => 'Разрешить автору темы всегда видеть свои скрытые блоки.',
	'ADVANCEDHIDE_THANKS_TABLE'            => 'Имя таблицы благодарностей',
	'ADVANCEDHIDE_THANKS_TABLE_EXPLAIN'    => 'Имя таблицы в базе данных для проверки условия thanks (по умолчанию: {PREFIX}thanks).',
	'ADVANCEDHIDE_MAX_BLOCKS'              => 'Максимум блоков в посте',
	'ADVANCEDHIDE_MAX_BLOCKS_EXPLAIN'      => 'Максимальное число блоков [hide] в одном сообщении (по умолчанию: 20).',
	'ACP_ADVANCEDHIDE_SAVED'               => 'Настройки AdvancedHide успешно сохранены.',
	'ADVANCEDHIDE_SHOW_BUTTONS'         => 'Отображать кнопки в редакторе',
	'ADVANCEDHIDE_SHOW_BUTTONS_EXPLAIN' => 'Показывать панель кнопок [hide] над полем ввода сообщения.',
	'ADVANCEDHIDE_SECURITY_LEGEND'      => 'Безопасность и защита от подбора',
	'ADVANCEDHIDE_ENABLE_CAPTCHA'       => 'Включить системную Captcha',
	'ADVANCEDHIDE_ENABLE_CAPTCHA_EXPLAIN' => 'Использовать стандартный плагин капчи форума при вводе пароля.',
	'ADVANCEDHIDE_RL_MINUTE'            => 'Лимит попыток в минуту',
	'ADVANCEDHIDE_RL_MINUTE_EXPLAIN'    => 'Максимум попыток ввода пароля к блоку за 1 минуту.',
	'ADVANCEDHIDE_RL_DAY'               => 'Лимит попыток в сутки',
	'ADVANCEDHIDE_RL_DAY_EXPLAIN'       => 'Максимум попыток ввода пароля к блоку за 24 часа.',
	'ADVANCEDHIDE_MODULES_LEGEND'       => 'Управление модулями и иконками кнопок',
	'ADVANCEDHIDE_MODULES_EXPLAIN'      => 'Отключенные модули скрываются из редактора и показывают заглушку на форуме.',
	'ADVANCEDHIDE_TH_MODULE'            => 'Модуль',
	'ADVANCEDHIDE_TH_STATUS'            => 'Статус',
	'ADVANCEDHIDE_TH_ICON'              => 'Иконка FontAwesome',
<<<<<<< HEAD
	'ADVANCEDHIDE_TH_SETTINGS'          => 'Настройки модуля',
=======
	'ADVANCEDHIDE_TH_SETTINGS'          => 'Параметры модуля',
>>>>>>> 5af1a80df62317c47a5d41f7c158692e36f7ba22
]);