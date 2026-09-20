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
	'HIDE_TITLE_LOCKED'                 => 'Hidden Content',
	'HIDE_TITLE_UNLOCKED'               => 'Hidden Content (Unlocked)',
	'HIDE_OVERRIDE_MOD'                 => 'Moderator',
	'HIDE_OVERRIDE_AUTHOR'              => 'Post Author',
	'HIDE_CONTENT_PROTECTED'             => '[Hidden content protected]',
	'HIDE_LIMIT_EXCEEDED'               => 'Maximum hidden blocks limit exceeded.',
	'HIDE_TITLE_DISABLED'               => 'Hidden Content (Module Temporarily Disabled)',
	'HIDE_COND_MODULE_DISABLED'         => 'The "%s" hide module is temporarily disabled by administrator.',
	'HIDE_MODULE_PASS_DISABLED'         => 'Password unlock is temporarily disabled by administrator.',
	'HIDE_BLOCK_NOT_FOUND'              => 'Hidden block not found.',

	'HIDE_COND_GUEST_FAILED'            => 'Content available to registered users only.',
	'HIDE_COND_POSTS_FAILED'            => 'Requires at least %1$d posts (you have %2$d).',
	'HIDE_COND_DAYS_FAILED'             => 'Requires account age of at least %1$d days (you have %2$d).',
	'HIDE_COND_REGDATE_FAILED'          => 'Available only for accounts registered before %s.',
	'HIDE_COND_TIME_FAILED'             => 'Content will unlock after %s (UTC).',
	'HIDE_COND_REPLY_FAILED'            => 'You must reply to this topic to unlock.',
	'HIDE_COND_THANKS_FAILED'           => 'You must thank the author of this post to unlock.',
	'HIDE_COND_GROUPS_FAILED'           => 'You belong to none of the allowed groups.',
	'HIDE_COND_USERS_FAILED'            => 'Content is hidden for your account.',
	'HIDE_COND_PASS_REQUIRED'           => 'Password required to unlock.',
	'HIDE_COND_UNKNOWN'                 => 'Invalid or unknown hide condition.',
	'HIDE_PASS_LIMIT_EXCEEDED'          => 'Password limit exceeded per post.',

	'HIDE_PASS_PLACEHOLDER'             => 'Enter password...',
	'HIDE_PASS_SUBMIT'                  => 'Unlock',
	'HIDE_PASS_INCORRECT'               => 'Incorrect password.',
	'HIDE_PASS_OK_OTHER_FAILED_GENERIC' => 'Password correct, but additional access conditions were not met.',
	'HIDE_RATE_LIMIT_EXCEEDED'          => 'Too many attempts. Please try again in 1 minute.',
	'HIDE_CAPTCHA_QA_PLACEHOLDER'       => 'Answer to security question...',
	'HIDE_CAPTCHA_CODE_PLACEHOLDER'     => 'Confirmation code...',
	'HIDE_ERROR_TOO_MANY_PASSWORDS'     => 'Message contains too many password hide blocks (maximum 3 allowed).',
	'CAPTCHA_SERVICE_UNAVAILABLE'       => 'Captcha verification service is temporarily unavailable. Please try again later.',

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

	'ADVHIDE_BUILDER_BTN'               => 'Hide Content',
	'ADVHIDE_BUILDER_TITLE'             => 'AdvancedHide BBCode Builder',
	'ADVHIDE_MODAL_TITLE'               => 'AdvancedHide BBCode Constructor',
	'ADVHIDE_MODAL_SUBTITLE'            => 'Select or combine multiple access criteria to protect your content.',
	'ADVHIDE_MODAL_PREVIEW'             => 'Final BBCode:',
	'ADVHIDE_MODAL_INSERT'              => 'Insert into message',
	'ADVHIDE_MODAL_CANCEL'              => 'Cancel',
	'ADVHIDE_MODAL_RESET'               => 'Reset',
	'ADVHIDE_DEFAULT_CONTENT'           => 'Hidden content goes here...',

	'ADVHIDE_FIELD_GUEST'               => 'Registered users only (hide from guests)',
	'ADVHIDE_FIELD_REPLY'               => 'Require reply to this topic',
	'ADVHIDE_FIELD_THANKS'              => 'Require post thanks',
	'ADVHIDE_FIELD_POSTS'               => 'Minimum post count:',
	'ADVHIDE_FIELD_DAYS'                => 'Minimum membership age (days):',
	'ADVHIDE_FIELD_REGDATE'             => 'Registered strictly before date:',
	'ADVHIDE_FIELD_TIME'                => 'Automatic unlock date/time (UTC):',
	'ADVHIDE_FIELD_GROUPS'              => 'Allowed group IDs (comma-separated):',
	'ADVHIDE_FIELD_USERS'               => 'Allowed usernames (comma-separated):',
	'ADVHIDE_FIELD_PASS'                => 'Protect with password:',

        // GDPR / Privacy
        'PRIVACY_ADVHIDE_USAGE'             => 'AdvancedHide Usage',
        'PRIVACY_ADVHIDE_USAGE_DESC'        => 'User has interacted with AdvancedHide content protection system (viewed/attempted to unlock hidden content).',
        
        // Emergency Kill-Switch
        'ADVHIDE_EMERGENCY_SHUTDOWN'        => 'Emergency Shutdown Mode Active',
        'ADVHIDE_EMERGENCY_DESC'            => 'All hidden content is currently inaccessible due to emergency shutdown triggered by administrator.',
]);
