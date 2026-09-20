<?php
if (!defined('IN_PHPBB')) exit;

if (empty($lang) || !is_array($lang)) $lang = [];

$lang = array_merge($lang, [
	'ACP_ADVANCEDHIDE_TITLE'        => 'AdvancedHide BBCode',
	'ACP_ADVANCEDHIDE_SETTINGS'     => 'AdvancedHide Settings',
	'ACP_ADVANCEDHIDE_EXPLAIN'      => 'Manage parameters and restrictions for [hide] BBCode blocks.',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE'  => 'Visible to post author',
	'ADVANCEDHIDE_AUTHOR_OVERRIDE_EXPLAIN' => 'Allow the post author to always see their own hidden blocks.',
	'ADVANCEDHIDE_THANKS_TABLE'     => 'Thanks table name',
	'ADVANCEDHIDE_THANKS_TABLE_EXPLAIN'    => 'Database table name used to check thanks conditions (default: {PREFIX}thanks).',
	'ADVANCEDHIDE_MAX_BLOCKS'       => 'Max blocks per post',
	'ADVANCEDHIDE_MAX_BLOCKS_EXPLAIN'      => 'Maximum number of [hide] blocks in a single post (default: 20).',
	'ACP_ADVANCEDHIDE_SAVED'        => 'AdvancedHide settings saved successfully.',

	'ADVANCEDHIDE_SHOW_BUTTONS'         => 'Show buttons in editor',
	'ADVANCEDHIDE_SHOW_BUTTONS_EXPLAIN' => 'Display [hide] buttons toolbar above the message input field.',
	'ADVANCEDHIDE_SECURITY_LEGEND'      => 'Security & Brute-Force Protection',
	'ADVANCEDHIDE_ENABLE_CAPTCHA'       => 'Enable system Captcha',
	'ADVANCEDHIDE_ENABLE_CAPTCHA_EXPLAIN' => 'Use board default Captcha plugin (configured in ACP -> Spambot countermeasures) during password unlock attempts. <a href="%s">Configure Captcha</a>',
	'ADVANCEDHIDE_RL_MINUTE'            => 'Attempts limit per minute',
	'ADVANCEDHIDE_RL_MINUTE_EXPLAIN'    => 'Maximum password attempts per block within 1 minute.',
	'ADVANCEDHIDE_RL_DAY'               => 'Attempts limit per day',
	'ADVANCEDHIDE_RL_DAY_EXPLAIN'       => 'Maximum password attempts per block within 24 hours.',
	'ADVANCEDHIDE_MODULES_LEGEND'       => 'Hide Modules & Button Icons Management',
	'ADVANCEDHIDE_MODULES_EXPLAIN'      => 'Disabled modules are hidden from editor. On forum, disabled modules show placeholder and keep content locked.',
	'ADVANCEDHIDE_TH_MODULE'            => 'Module',
	'ADVANCEDHIDE_TH_STATUS'            => 'Status',
	'ADVANCEDHIDE_TH_ICON'              => 'FontAwesome Icon',
	'ADVANCEDHIDE_TH_SETTINGS'          => 'Module Settings',
	'ADVANCEDHIDE_CAPTCHA_LINK'         => 'Configure Captcha',
	'ADVANCEDHIDE_PERMISSIONS_LINK'     => 'Configure Permissions',
]);