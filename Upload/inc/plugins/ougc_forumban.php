<?php

/***************************************************************************
 *
 *   OUGC Forum Ban plugin (/inc/plugins/ougc_forumban.php)
 *	 Author: Omar Gonzalez
 *   Copyright: © 2015 Omar Gonzalez
 *   
 *   Website: http://omarg.me
 *
 *   Allow moderators to ban users from specific forums.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Plugin API
function ougc_forumban_info()
{
	global $lang, $forumban;
	$forumban->load_language();

	return array(
		'name'			=> 'OUGC Forum Ban',
		'description'	=> $lang->setting_group_ougc_forumban_desc,
		'website'		=> 'http://omarg.me',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'http://omarg.me',
		'version'		=> '1.8',
		'versioncode'	=> 1800,
		'compatibility'	=> '18*',
		'pl'			=> array(
			'version'	=> 12,
			'url'		=> 'http://mods.mybb.com/view/pluginlibrary'
		)
	);
}

// _activate() routine
function ougc_forumban_activate()
{
	global $PL/*, $lang*/, $cache;
	ougc_forumban_lang_load();
	ougc_forumban_deactivate();

	// Add settings group
	$PL->settings('ougc_forumban', $lang->setting_group_ougc_forumban, $lang->setting_group_ougc_forumban_desc, array(
		'groups'	=> array(
		   'title'			=> $lang->setting_ougc_forumban_groups,
		   'description'	=> $lang->setting_ougc_forumban_groups_desc,
		   'optionscode'	=> 'groupselect',
			'value'			=>	'1,2,5,7',
		)
	));

	// Add template group
	$PL->templates('ougcforumban', '<lang:setting_group_ougc_forumban>', array(
		''	=> '{$br_postbit}<img src="{$image}" alt="{$usertitle}" title="{$usertitle}" />{$br_profile}'
	));

	// Modify templates
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'groupimage\']}').'#', '{$post[\'groupimage\']}{$post[\'ougc_forumban\']}');
	find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'groupimage\']}').'#', '{$post[\'groupimage\']}{$post[\'ougc_forumban\']}');
	find_replace_templatesets('member_profile', '#'.preg_quote('{$groupimage}').'#', '{$groupimage}{$memprofile[\'ougc_forumban\']}');

	// Insert/update version into cache
	$plugins = $cache->read('ougc_plugins');
	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_forumban_info();

	if(!isset($plugins['forumban']))
	{
		$plugins['forumban'] = $info['versioncode'];
	}

	/*~*~* RUN UPDATES START *~*~*/

	/*~*~* RUN UPDATES END *~*~*/

	$plugins['forumban'] = $info['versioncode'];
	$cache->update('ougc_plugins', $plugins);
}

// _deactivate() routine
function ougc_forumban_deactivate()
{
	ougc_forumban_pl_check();

	// Revert template edits
	require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
	find_replace_templatesets('postbit', '#'.preg_quote('{$post[\'ougc_forumban\']}').'#', '', 0);
	find_replace_templatesets('postbit_classic', '#'.preg_quote('{$post[\'ougc_forumban\']}').'#', '', 0);
	find_replace_templatesets('member_profile', '#'.preg_quote('{$memprofile[\'ougc_forumban\']}').'#', '', 0);
}

// _insstall() routine
function ougc_forumban_install()
{
	global $db;

	// Create our table(s)
	if(!$db->table_exists('ougc_forumban'))
	{
		$db->write_query("CREATE TABLE `".TABLE_PREFIX."ougc_forumban` (
				`bid` int UNSIGNED NOT NULL AUTO_INCREMENT,
				`uid` int NOT NULL DEFAULT '0',
				`fid` int NOT NULL DEFAULT '0',
				`parentlist` text NOT NULL,
				`active` tinyint(1) NOT NULL DEFAULT '1',
				`dateline` int(10) NOT NULL DEFAULT '0',
				PRIMARY KEY (`bid`)
			) ENGINE=MyISAM{$db->build_create_table_collation()};"
		);
	}
}

// _is_installed() routine
function ougc_forumban_is_installed()
{
	global $db;

	return $db->table_exists('ougc_forumban');
}

// _uninstall() routine
function ougc_forumban_uninstall()
{
	global $PL, $cache;
	$PL or require_once PLUGINLIBRARY;

	$db->drop_table('ougc_forumban');
	$PL->templates_delete('ougcforumban');

	// Delete version from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['forumban']))
	{
		unset($plugins['forumban']);
	}

	if(!empty($plugins))
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$cache->delete('ougc_plugins');
	}
}

class OUGC_ForumBan
{
	function __construct()
	{
		global $plugins;

		// Run/Add Hooks
		if(defined('IN_ADMINCP'))
		{
		}
		else
		{
			$plugins->add_hook('global_intermediate', array($this, 'hook_global_intermediate'), -9999);
			$plugins->add_hook('build_forumbits_forum', array($this, 'hook_build_forumbits_forum'), -9999);
			$plugins->add_hook('announcements_start', array($this, 'hook_alternate'), -9999);
			$plugins->add_hook('attachment_start', array($this, 'hook_alternate'), -9999);
			$plugins->add_hook('report_start', array($this, 'hook_alternate'), -9999);
			$plugins->add_hook('report_do_report_start', array($this, 'hook_alternate'), -9999);
			$plugins->add_hook('archive_start', array($this, 'hook_alternate'), -9999);
		}
	}

	function load_language()
	{
		global $lang;

		isset($lang->setting_group_ougc_forumban) or $lang->load('ougc_forumban');
	}

	function is_banned($fid, $parentlist)
	{
		global $mybb;

		$this->is_banned = false;

		if(!empty($mybb->user['ougc_forumban']))
		{
			$forum_list = $parentlist.','.$fid;

			$banned_from = array_map('intval', explode(',', $mybb->user['ougc_forumban']));
			foreach($banned_from as $fid)
			{
				if(strpos(','.$forum_list.',', ','.$fid.',') !== false)
				{
					$this->is_banned = true;
					break;
				}
			}
		}

		return $this->is_banned;
	}

	function hook_global_intermediate()
	{
		global $load_from_forum, $style, $forum_cache, $mybb, $plugins;
		$forum_cache or cache_forums();

		/* DEBUG */ $mybb->user['ougc_forumban'] = '2,77';

		if(empty($load_from_forum) || empty($style) || empty($forum_cache[$style['fid']]) || empty($mybb->user['ougc_forumban']))
		{
			return;
		}

		if($this->is_banned($forum_cache[$style['fid']]['fid'], $forum_cache[$style['fid']]['parentlist']))
		{
			$plugins->add_hook('global_end', 'error_no_permission');
		}
	}

	function hook_alternate()
	{
		global $mybb, $plugins, $db, $forum, $thread, $announcement, $action, $attachment;

		/* DEBUG */ $mybb->user['ougc_forumban'] = '2,77';

		if(empty($mybb->user['ougc_forumban']))
		{
			return;
		}

		$func = 'error_no_permission';
		switch($plugins->current_hook)
		{
			case 'announcements_start':
				if($announcement['fid'] > 0)
				{
					$forum = get_forum($announcement['fid']);
				}
				break;
			case 'attachment_start':
				if($attachment['pid'] || $attachment['uid'] != $mybb->user['uid'])
				{
					$post = get_post($attachment['pid']);
					$thread = get_thread($post['tid']);
					$forum = get_forum($thread['fid']);
				}
				break;
			case 'archive_start':
				$func = 'archive_error_no_permission';
				switch($action)
				{
					case 'announcement':
						if($announcement['fid'] != -1)
						{
							$forum = get_forum($announcement['fid']);
						}
						break;
					case 'thread':
						$forum = get_forum($thread['fid']);
						break;
				}
				break;
		}

		if(!empty($forum['fid']) && $this->is_banned($forum['fid'], $forum['parentlist']))
		{
			$func();
		}
	}

	function hook_build_forumbits_forum(&$forum)
	{
		global $mybb;

		/* DEBUG */ $mybb->user['ougc_forumban'] = '2,77';

		if(!empty($mybb->user['ougc_forumban']) && $this->is_banned($forum['fid'], $forum['parentlist']))
		{
			$forum['lastpost'] = 0;
		}
	}
}

$GLOBALS['forumban'] = new OUGC_ForumBan;