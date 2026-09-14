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
]);