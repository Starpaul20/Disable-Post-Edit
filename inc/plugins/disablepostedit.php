<?php
/**
 * Disable Post Edit
 * Copyright 2017 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Neat trick for caching our custom template(s)
if(THIS_SCRIPT == 'editpost.php')
{
	global $templatelist;
	if(isset($templatelist))
	{
		$templatelist .= ',';
	}
	$templatelist .= 'editpost_disableedit';
}

// Tell MyBB when to run the hooks
$plugins->add_hook("datahandler_post_update", "disablepostedit_run");
$plugins->add_hook("editpost_start", "disablepostedit_editpost");
$plugins->add_hook("postbit", "disablepostedit_postbit");
$plugins->add_hook("xmlhttp_edit_post_end", "disablepostedit_xmlhttp");

// The information that shows up on the plugin manager
function disablepostedit_info()
{
	global $lang;
	$lang->load("disablepostedit", true);

	return array(
		"name"				=> $lang->disablepostedit_info_name,
		"description"		=> $lang->disablepostedit_info_desc,
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"compatibility"		=> "18*"
	);
}

// This function runs when the plugin is installed.
function disablepostedit_install()
{
	global $db;
	disablepostedit_uninstall();

	switch($db->type)
	{
		case "pgsql":
			$db->add_column("posts", "disableedit", "smallint NOT NULL default '0'");
			break;
		default:
			$db->add_column("posts", "disableedit", "tinyint(1) NOT NULL default '0'");
			break;
	}
}

// Checks to make sure plugin is installed
function disablepostedit_is_installed()
{
	global $db;
	if($db->field_exists("disableedit", "posts"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function disablepostedit_uninstall()
{
	global $db;

	if($db->field_exists("disableedit", "posts"))
	{
		$db->drop_column("posts", "disableedit");
	}
}

// This function runs when the plugin is activated.
function disablepostedit_activate()
{
	global $db;

	// Insert templates
	$insert_array = array(
		'title'		=> 'editpost_disableedit',
		'template'	=> $db->escape_string('<tr>
	<td class="trow2"><strong>{$lang->disable_edit}</strong></td>
	<td class="trow2"><span class="smalltext">
		<label><input type="checkbox" class="checkbox" name="disableedit" value="1" tabindex="8" {$disableeditedby} /> {$lang->disable_edited_by}</label></span>
	</td>
</tr>'),
		'sid'		=> '-1',
		'version'	=> '',
		'dateline'	=> TIME_NOW
	);
	$db->insert_query("templates", $insert_array);

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$pollbox}')."#i", '{$pollbox}{$disableedit}');
	find_replace_templatesets("showthread_inlinemoderation_standard", "#".preg_quote('{$inlinemodapprove}')."#i", '{$inlinemodapprove}{$inlinedisableedit}');
}

// This function runs when the plugin is deactivated.
function disablepostedit_deactivate()
{
	global $db;
	$db->delete_query("templates", "title IN('editpost_disableedit')");

	include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("editpost", "#".preg_quote('{$disableedit}')."#i", '', 0);
	find_replace_templatesets("showthread_inlinemoderation_standard", "#".preg_quote('{$inlinedisableedit}')."#i", '', 0);
}

// Update 'Disable edit' input from edit page
function disablepostedit_run()
{
	global $db, $mybb, $post;
	$edit = get_post($post['pid']);

	if(is_moderator($edit['fid'], "caneditposts"))
	{
		$disable_edit = array(
			"disableedit" => $mybb->get_input('disableedit', MyBB::INPUT_INT)
		);
		$db->update_query("posts", $disable_edit, "pid='{$edit['pid']}'");
	}
}

// Edit post page functions
function disablepostedit_editpost()
{
	global $mybb, $templates, $lang, $disableedit;
	$lang->load("disablepostedit");

	$pid = $mybb->get_input('pid', MyBB::INPUT_INT);
	$edit = get_post($pid);

	if($edit['disableedit'] == 1 && !is_moderator($edit['fid'], "caneditposts"))
	{
		error($lang->error_editing_disabled);
	}

	if(is_moderator($edit['fid'], "caneditposts"))
	{
		if($edit['disableedit'] == 1)
		{
			$disableeditedby = "checked=\"checked\"";
		}
		else
		{
			$disableeditedby = '';
		}
		eval("\$disableedit = \"".$templates->get("editpost_disableedit")."\";");
	}
}

// Hide edit button if disabled
function disablepostedit_postbit($post)
{
	if($post['disableedit'] == 1 && !is_moderator($post['fid'], "caneditposts"))
	{
		$post['button_edit'] = '';
		$post['button_quickdelete'] = '';
	}

	return $post;
}

// Error if quick editing is used
function disablepostedit_xmlhttp()
{
	global $post, $lang, $forum;
	$lang->load("disablepostedit");

	if($post['disableedit'] == 1 && !is_moderator($forum['fid'], "caneditposts"))
	{
		xmlhttp_error($lang->error_editing_disabled);
	}
}
