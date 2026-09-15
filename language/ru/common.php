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
	'HIDE_TITLE_LOCKED'                 => 'Скрытый контент',
	'HIDE_TITLE_UNLOCKED'               => 'Скрытый контент (доступ открыт)',
	'HIDE_OVERRIDE_MOD'                 => 'Модератор',
	'HIDE_OVERRIDE_AUTHOR'              => 'Автор поста',
	'HIDE_CONTENT_PROTECTED'             => '[Скрытый контент недоступен для просмотра]',
	'HIDE_LIMIT_EXCEEDED'               => 'Превышен лимит скрытых блоков в сообщении.',

	'HIDE_COND_GUEST_FAILED'            => 'Контент доступен только зарегистрированным пользователям.',
	'HIDE_COND_POSTS_FAILED'            => 'Требуется минимум %1$d сообщений (у вас %2$d).',
	'HIDE_COND_DAYS_FAILED'             => 'Требуется стаж аккаунта от %1$d дней (у вас %2$d).',
	'HIDE_COND_REGDATE_FAILED'          => 'Доступно только зарегистрированным до %s.',
	'HIDE_COND_TIME_FAILED'             => 'Контент станет доступен после %s (UTC).',
	'HIDE_COND_REPLY_FAILED'            => 'Необходимо ответить в этой теме.',
	'HIDE_COND_THANKS_FAILED'           => 'Необходимо поблагодарить автора этого сообщения.',
	'HIDE_COND_GROUPS_FAILED'           => 'Вы не состоите в разрешенной группе.',
	'HIDE_COND_USERS_FAILED'            => 'Контент скрыт для вашего аккаунта.',
	'HIDE_COND_PASS_REQUIRED'           => 'Для просмотра требуется ввод пароля.',
	'HIDE_COND_UNKNOWN'                 => 'Неизвестное или некорректное условие скрытия.',
	'HIDE_PASS_LIMIT_EXCEEDED'          => 'Превышен лимит паролей в одном сообщении.',

	'HIDE_PASS_PLACEHOLDER'             => 'Введите пароль...',
	'HIDE_PASS_SUBMIT'                  => 'Открыть',
	'HIDE_PASS_INCORRECT'               => 'Неверный пароль.',
	'HIDE_PASS_OK_OTHER_FAILED_GENERIC' => 'Пароль верный, но дополнительные условия доступа не выполнены.',
	'HIDE_RATE_LIMIT_EXCEEDED'          => 'Слишком много попыток. Попробуйте через минуту.',
	'HIDE_BLOCK_NOT_FOUND'              => 'Скрытый блок не найден.',

	'HIDE_TITLE_DISABLED'               => 'Скрытый контент (модуль временно отключен)',
	'HIDE_COND_MODULE_DISABLED'         => 'Модуль скрытия «%s» временно отключен администратором.',
	'HIDE_MODULE_PASS_DISABLED'         => 'Разблокировка паролем временно отключена администратором.',
	'HIDE_CAPTCHA_QA_PLACEHOLDER'       => 'Ответ на защитный вопрос...',
	'HIDE_CAPTCHA_CODE_PLACEHOLDER'     => 'Код с картинки...',

	'ADVHIDE_MOD_GUEST'                 => 'Гости',
	'ADVHIDE_MOD_POSTS'                 => 'Сообщения',
	'ADVHIDE_MOD_DAYS'                  => 'Стаж (дни)',
	'ADVHIDE_MOD_TIME'                  => 'Время',
	'ADVHIDE_MOD_REGDATE'               => 'Дата рег.',
	'ADVHIDE_MOD_REPLY'                 => 'Ответ',
	'ADVHIDE_MOD_THANKS'                => 'Спасибо',
	'ADVHIDE_MOD_GROUPS'                => 'Группы',
	'ADVHIDE_MOD_USERS'                 => 'Пользователи',
	'ADVHIDE_MOD_PASS'                  => 'Пароль',

	'HIDE_PROMPT_POSTS'                 => 'Введите требуемое количество сообщений:',
	'HIDE_PROMPT_DAYS'                  => 'Введите требуемый стаж в днях:',
	'HIDE_PROMPT_TIME'                  => 'Введите дату открытия (ГГГГ-ММ-ДД ЧЧ:ММ:СС):',
	'HIDE_PROMPT_REGDATE'               => 'Введите предельную дату регистрации (ГГГГ-ММ-ДД):',
	'HIDE_PROMPT_GROUPS'                => 'Введите ID групп через запятую:',
	'HIDE_PROMPT_USERS'                 => 'Введите имена пользователей через запятую:',
	'HIDE_PROMPT_PASS'                  => 'Введите пароль для скрытого блока:',

	'ADVHIDE_BTN_GUEST_TIP'             => 'Скрыть контент от гостей (доступно только зарегистрированным)',
	'ADVHIDE_BTN_POSTS_TIP'             => 'Скрыть контент до набора указанного количества сообщений',
	'ADVHIDE_BTN_DAYS_TIP'              => 'Скрыть контент по стажу регистрации (в днях)',
	'ADVHIDE_BTN_TIME_TIP'              => 'Скрыть контент до наступления определенной даты и времени',
	'ADVHIDE_BTN_REGDATE_TIP'           => 'Скрыть контент для пользователей, зарегистрированных позже указанной даты',
	'ADVHIDE_BTN_REPLY_TIP'             => 'Скрыть контент до ответа в данной теме',
	'ADVHIDE_BTN_THANKS_TIP'            => 'Скрыть контент до нажатия кнопки «Спасибо»',
	'ADVHIDE_BTN_GROUPS_TIP'            => 'Скрыть контент для всех, кроме выбранных групп',
	'ADVHIDE_BTN_USERS_TIP'             => 'Скрыть контент для всех, кроме выбранных пользователей',
	'ADVHIDE_BTN_PASS_TIP'              => 'Скрыть контент под пароль с возможностью ввода',

	'HIDE_ERROR_TOO_MANY_PASSWORDS'     => 'Сообщение содержит слишком много парольных блоков скрытия (максимум 3).',
	'CAPTCHA_SERVICE_UNAVAILABLE'       => 'Сервис проверки Captcha временно недоступен. Попробуйте позже.',

	'ADVHIDE_BUILDER_TITLE'             => 'Конструктор скрытого контента [hide]',
	'ADVHIDE_BUILDER_BTN'               => 'Мастер [hide]',
	'ADVHIDE_INSERT_BBCODE'             => 'Вставить BBCode',
	'ADVHIDE_BTN_NOT_GROUPS_TIP'        => 'Скрыть контент для указанных групп',
	'ADVHIDE_BTN_NOT_USERS_TIP'         => 'Скрыть контент для указанных пользователей',
	'HIDE_PROMPT_NOT_GROUPS'            => 'Введите ID исключаемых групп через запятую:',
	'HIDE_PROMPT_NOT_USERS'             => 'Введите имена исключаемых пользователей через запятую:',
	'ADVHIDE_BTN_AUDIT'                 => 'Журнал аудита и банов',
	'ADVHIDE_BTN_REPORT'                => 'Пожаловаться на брутфорс',
	'ADVHIDE_BTN_APPEAL'                => 'Подать апелляцию',
	'ADVHIDE_STATUS_BANNED'             => 'Доступ заблокирован',
	'ADVHIDE_BAN_PERMANENT'             => 'Бессрочно',
	'ADVHIDE_UNBAN_BTN'                 => 'Разблокировать',
	'ADVHIDE_REPORT_CLOSED'             => 'Жалоба закрыта.',
	'ADVHIDE_REPORTS_TITLE'             => 'Жалобы и апелляции',
	'ADVHIDE_BANS_TITLE'                => 'Активные персональные блокировки',
	'ADVHIDE_LOGS_TITLE'                => 'Журнал попыток ввода паролей',
	'ADVHIDE_TH_MASKED_PASS'            => 'Введенный пароль (маскирован)',
	'HIDE_BANNED_WITH_REASON'           => 'Вам заблокирован доступ к этому скрытому блоку. Причина: %s',
	'HIDE_NO_POST_AUTH'                 => 'У вас нет прав на использование тега [hide] в этом сообщении.',
	'HIDE_NO_PASS_AUTH'                 => 'У вас нет прав на установку паролей на скрытые блоки.',
	'ADVHIDE_BUILDER_PREVIEW'           => 'Предпросмотр тега',
	'ADVHIDE_BUILDER_CLOSE'             => 'Закрыть',
]);