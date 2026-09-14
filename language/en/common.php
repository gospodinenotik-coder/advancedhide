<?php
if (!defined('IN_PHPBB')) exit;

if (empty($lang) || !is_array($lang)) $lang = [];

$lang = array_merge($lang, [
	'HIDE_TITLE_LOCKED'              => 'Hidden Content',
	'HIDE_TITLE_UNLOCKED'            => 'Hidden Content (Unlocked)',
	'HIDE_OVERRIDE_MOD'              => 'Moderator',
	'HIDE_OVERRIDE_AUTHOR'           => 'Post Author',
	'HIDE_CONTENT_PROTECTED'          => '[Hidden content protected]',
	'HIDE_LIMIT_EXCEEDED'            => 'Maximum hidden blocks limit exceeded.',

	'HIDE_COND_GUEST_FAILED'         => 'Content available to registered users only.',
	'HIDE_COND_POSTS_FAILED'         => 'Requires at least %1$d posts (you have %2$d).',
	'HIDE_COND_DAYS_FAILED'          => 'Requires account age of at least %1$d days (you have %2$d).',
	'HIDE_COND_REGDATE_FAILED'       => 'Available only for accounts registered before %s.',
	'HIDE_COND_TIME_FAILED'          => 'Content will unlock after %s (UTC).',
	'HIDE_COND_REPLY_FAILED'         => 'You must reply to this topic to unlock.',
	'HIDE_COND_THANKS_FAILED'        => 'You must thank the author of this post to unlock.',
	'HIDE_COND_GROUPS_FAILED'        => 'You belong to none of the allowed groups.',
	'HIDE_COND_USERS_FAILED'         => 'Content is hidden for your account.',
	'HIDE_COND_PASS_REQUIRED'        => 'Password required to unlock.',
	'HIDE_COND_UNKNOWN'              => 'Invalid or unknown hide condition.',
	'HIDE_PASS_LIMIT_EXCEEDED'       => 'Password limit exceeded per post.',

	'HIDE_PASS_PLACEHOLDER'          => 'Enter password...',
	'HIDE_PASS_SUBMIT'               => 'Unlock',
	'HIDE_PASS_INCORRECT'            => 'Incorrect password.',
	'HIDE_PASS_OK_OTHER_FAILED_GENERIC' => 'Password correct, but additional access conditions were not met.',
	'HIDE_RATE_LIMIT_EXCEEDED'       => 'Too many attempts. Please try again in 1 minute.',
	'HIDE_BLOCK_NOT_FOUND'           => 'Hidden block not found.',

	'HIDE_TITLE_DISABLED'               => 'Hidden Content (Module Temporarily Disabled)',
	'HIDE_COND_MODULE_DISABLED'         => 'The "%s" hide module is temporarily disabled by administrator.',
	'HIDE_MODULE_PASS_DISABLED'         => 'Password unlock is temporarily disabled by administrator.',
	'HIDE_CAPTCHA_QA_PLACEHOLDER'       => 'Answer to security question...',
	'HIDE_CAPTCHA_CODE_PLACEHOLDER'     => 'Confirmation code...',

	'ADVHIDE_MOD_GUEST'                 => 'Guests',
	'ADVHIDE_MOD_POSTS'                 => 'Posts',
	'ADVHIDE_MOD_DAYS'                  => 'Account age',
	'ADVHIDE_MOD_TIME'                  => 'Time unlock',
	'ADVHIDE_MOD_REGDATE'               => 'Reg. date',
	'ADVHIDE_MOD_REPLY'                 => 'Reply',
	'ADVHIDE_MOD_THANKS'                => 'Thanks',
	'ADVHIDE_MOD_GROUPS'                => 'Groups',
	'ADVHIDE_MOD_USERS'                 => 'Users',
	'ADVHIDE_MOD_PASS'                  => 'Password',

	'HIDE_PROMPT_POSTS'                 => 'Enter required post count:',
	'HIDE_PROMPT_DAYS'                  => 'Enter required account age in days:',
	'HIDE_PROMPT_TIME'                  => 'Enter unlock datetime (YYYY-MM-DD HH:MM:SS):',
	'HIDE_PROMPT_REGDATE'               => 'Enter cutoff registration date (YYYY-MM-DD):',
	'HIDE_PROMPT_GROUPS'                => 'Enter comma-separated group IDs:',
	'HIDE_PROMPT_USERS'                 => 'Enter comma-separated usernames:',
	'HIDE_PROMPT_PASS'                  => 'Enter password for hidden block:',
]);