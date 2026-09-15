# AdvancedHide Content BBCode for phpBB 3.3.17

Расширение для скрытия содержимого сообщений на форумах phpBB 3.3.x с поддержкой многофакторных условий, паролей, встроенной Captcha, аудита взломов и модераторских ограничений.

## Требования
* phpBB >= 3.3.17, < 3.4.0
* PHP >= 7.2, < 8.5

## Синтаксис тегов
* `[hide]текст[/hide]` — скрытие от гостей
* `[hide=posts,20]текст[/hide]` — минимум сообщений
* `[hide=days,30]текст[/hide]` — стаж в днях
* `[hide=groups,2,5]текст[/hide]` — разрешённые группы
* `[hide=not_groups,6]текст[/hide]` — исключённые группы
* `[hide=users,admin,moderator]текст[/hide]` — разрешённые пользователи
* `[hide=not_users,spammer]текст[/hide]` — исключённые пользователи
* `[hide=pass,SecretPass123]текст[/hide]` — защита паролем с Captcha и Rate Limiting