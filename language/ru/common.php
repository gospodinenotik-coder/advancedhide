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
]);