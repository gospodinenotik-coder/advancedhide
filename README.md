# AdvancedHide

Расширение для phpBB 3.3.x, предоставляющее расширенные возможности скрытия контента с гибкой системой условий доступа.

## Возможности

- **Гибкие условия доступа**: скрывайте контент по количеству сообщений, стажу регистрации, дате, факту ответа в теме, благодарностям, группам, пользователям или паролю
- **Комбинирование условий**: до 5 условий на блок (например: `posts,10;reply;pass,secret`)
- **Защита от утечек**: контент защищён в поиске, RSS-лентах, цитировании и предпросмотре
- **Безопасность**: rate limiting, CSRF-защита, аудит действий, emergency kill-switch
- **Интеграция с правами phpBB**: модераторы с правом `m_hide_override` видят весь контент

## Установка

1. Скопируйте файлы расширения в `ext/vendor/advancedhide/`
2. Включите расширение в ACP: Customise → Manage extensions → Enable
3. Настройте параметры в ACP: Extensions → AdvancedHide Settings

## BBCode синтаксис

```
[hide=условие]скрытый контент[/hide]
```

### Примеры условий

- `[hide=guest]` — только для гостей
- `[hide=posts,100]` — пользователям со 100+ сообщениями
- `[hide=days,30]` — пользователям старше 30 дней
- `[hide=reply]` — ответившим в теме
- `[hide=thanks]` — поблагодарившим автора поста
- `[hide=groups,1,2]` — участникам групп 1 или 2
- `[hide=users,admin]` — пользователю admin
- `[hide=pass,mypassword]` — знающим пароль
- `[hide=posts,50;reply]` — комбинация условий (И)

## Безопасность

### Реализованные механизмы защиты

| Угроза | Мера защиты | Статус |
|--------|-------------|--------|
| Утечка через поиск | Фильтрация `post_text` в `protect_search()` | ✅ Защищено |
| Race condition в rate limiter | Атомарные SQL-транзакции с `FOR UPDATE` | ✅ Защищено |
| SQL-ошибки при удалении постов | Очистка `audit_log` по `post_id` | ✅ Защищено |
| Orphan записи при preview | Проверка режима сохранения поста | ✅ Защищено |
| Неправильная привязка паролей | Индивидуальная обработка каждого блока | ✅ Защищено |
| Слабая проверка доступа | Полная интеграция с `content.visibility` phpBB | ✅ Защищено |
| DoS через XML parsing | Лимиты на размер текста и количество блоков | ✅ Защищено |
| Brute-force паролей | Rate limiting + Captcha | ✅ Защищено |
| CSRF атаки | Form token validation | ✅ Защищено |

### Emergency Kill-Switch

В экстренной ситуации включите режим полной блокировки:

```sql
UPDATE phpbb_config SET config_value = 1 WHERE config_name = 'advancedhide_emergency_shutdown';
```

Все скрытые блоки станут недоступны (fail-closed).

### Журнал аудита безопасности

Все попытки разблокировки логируются в таблицу `phpbb_advancedhide_audit_log`:

- `unlock_attempt` — попытка ввода пароля
- `unlock_success` — успешная разблокировка
- `rate_limited` — превышение лимита запросов

## Конфигурация

### Параметры rate limiting

- `advancedhide_rl_minute_limit` — максимум запросов в минуту (по умолчанию: 5)
- `advancedhide_rl_day_limit` — максимум запросов в день (по умолчанию: 30)

### Опции модулей

Каждый тип условия можно включить/отключить:

- `advancedhide_mod_guest` — условие guest
- `advancedhide_mod_posts` — условие posts
- `advancedhide_mod_days` — условие days
- `advancedhide_mod_time` — условие time
- `advancedhide_mod_regdate` — условие regdate
- `advancedhide_mod_reply` — условие reply
- `advancedhide_mod_thanks` — условие thanks
- `advancedhide_mod_groups` — условие groups
- `advancedhide_mod_users` — условие users
- `advancedhide_mod_pass` — условие pass

### Иконки

Настройте иконки для каждого типа условия (FontAwesome):

- `advancedhide_icon_guest` — по умолчанию: `fa-eye-slash`
- `advancedhide_icon_posts` — по умолчанию: `fa-comments`
- и т.д.

## Требования

- phpBB 3.3.x
- PHP 7.4+
- MySQL 5.6+ / PostgreSQL 9.6+ / SQLite 3.8+

## Структура базы данных

### Таблица `advancedhide_rl`

Rate limiting для защиты от brute-force:

- `rl_key` — уникальный ключ (user/session/IP/block)
- `rl_window` — временное окно (минута/день)
- `rl_count` — счётчик запросов

**Индексы:**
- PRIMARY KEY (`rl_key`)
- INDEX (`rl_window`)
- INDEX (`rl_key`, `rl_window`) — составной индекс для оптимизации

### Таблица `advancedhide_audit_log`

Журнал аудита безопасности:

- `log_id` — ID записи
- `log_time` — время события
- `user_id` — ID пользователя
- `user_ip` — IP-адрес
- `action_type` — тип действия
- `post_id` — ID поста
- `block_index` — индекс блока
- `result` — результат (success/failure/rate_limited)
- `details` — дополнительные данные (JSON)

## Лицензия

MIT License

## Авторы

Разработано для сообщества phpBB.