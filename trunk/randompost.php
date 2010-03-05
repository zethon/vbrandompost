<?php
// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('THIS_SCRIPT', 'randompost');

// ################### PRE-CACHE TEMPLATES AND DATA ######################
// get special phrase groups
$phrasegroups = array(
	'showthread',
	'postbit',
	'reputationlevel'
);

// get special data templates from the datastore
$specialtemplates = array(
	'smiliecache',
	'bbcodecache',
);

// pre-cache templates used by all actions
$globaltemplates = array(
	'im_aim',
	'im_icq',
	'im_msn',
	'im_yahoo',
	'im_skype',
	'postbit',
	'postbit_wrapper',
	'postbit_attachment',
	'postbit_attachmentimage',
	'postbit_attachmentthumbnail',
	'postbit_attachmentmoderated',
	'postbit_editedby',
	'postbit_ip',
	'postbit_onlinestatus',
	'postbit_reputation',
	'bbcode_code',
	'bbcode_html',
	'bbcode_php',
	'bbcode_quote',
	'SHOWTHREAD_SHOWPOST'
);

// pre-cache templates used by specific actions
$actiontemplates = array();

// ######################### REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/functions_bigthree.php');
require_once(DIR . '/includes/class_postbit.php');
require_once(DIR . '/includes/functions_randompost.php');

$allowedgroups = explode(',',$vbulletin->options['randompost_groups']);
if (!is_member_of($vbulletin->userinfo,$allowedgroups) && $allowedgroups[0] != 0)
{
	print_no_permission();	
}

$navbits = array('randompost.php' . $vbulletin->session->vars['sessionurl_q'] => $vbphrase['randompost']);
$navbits[""] = $vbphrase['viewrandompost'];
$navbits = construct_navbits($navbits);
eval('$navbar = "' . fetch_template('navbar') . '";');
$custompagetitle = $vbphrase['viewrandompost'];

if ($vbulletin->options['randompost_forums'])
{
	$forumids = $vbulletin->options['randompost_forums'];
	$forumclause = "
		LEFT JOIN " . TABLE_PREFIX . "thread AS thread ON (post.threadid = thread.threadid)
		LEFT JOIN " . TABLE_PREFIX . "forum AS forum ON (thread.forumid = forum.forumid)
		WHERE (forum.forumid IN ($forumids))
	";
}
	
# get the row count 
$rowcount = $db->query_first("
						SELECT COUNT(postid) AS count
						FROM " . TABLE_PREFIX . "post AS post
						$forumclause
					");
					
$foundpost = false;
$trycount = 0;

if ($vbulletin->GPC['postid'] && can_viewpost($vbulletin->GPC['postid']))
{
	$postid = $vbulletin->GPC['postid'];
	$foundpost = true;
}
else
{
	while (!$foundpost && $trycount < 10)
	{
		$rownumber = rand(1,$rowcount['count']);
		 
		$postid = $db->query_first("
						SELECT postid
						FROM " . TABLE_PREFIX . "post AS post
						$forumclause
						LIMIT $rownumber,1;
						");
		$postid = $postid['postid'];					
		
		if (can_viewpost($postid))
		{
			$foundpost = true;	
		}
		
		$trycount++;
	}
	
	if ($vbulletin->GPC['postid'])
	{
		header("Location:". $vbulletin->options[bburl] ."/randompostphp?postid=$postid");
	}
}

if ($foundpost)
{
	$postinfo = fetch_postinfo($postid);
	$threadinfo = fetch_threadinfo($postinfo['threadid']);
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);	
	
	$post = $db->query_first_slave("
		SELECT
			post.*, post.username AS postusername, post.ipaddress AS ip, IF(post.visible = 2, 1, 0) AS isdeleted,
			user.*, userfield.*, usertextfield.*,
			" . iif($foruminfo['allowicons'], 'icon.title as icontitle, icon.iconpath,') . "
			IF(displaygroupid=0, user.usergroupid, displaygroupid) AS displaygroupid, infractiongroupid
			" . iif($vbulletin->options['avatarenabled'], ',avatar.avatarpath, NOT ISNULL(customavatar.userid) AS hascustomavatar, customavatar.dateline AS avatardateline,customavatar.width AS avwidth,customavatar.height AS avheight') . "
			,editlog.userid AS edit_userid, editlog.username AS edit_username, editlog.dateline AS edit_dateline, editlog.reason AS edit_reason,
			postparsed.pagetext_html, postparsed.hasimages,
			sigparsed.signatureparsed, sigparsed.hasimages AS sighasimages,
			sigpic.userid AS sigpic, sigpic.dateline AS sigpicdateline, sigpic.width AS sigpicwidth, sigpic.height AS sigpicheight
			" . iif(!($permissions['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['canseehiddencustomfields']), $vbulletin->profilefield['hidden']) . "
		FROM " . TABLE_PREFIX . "post AS post
		LEFT JOIN " . TABLE_PREFIX . "user AS user ON(user.userid = post.userid)
		LEFT JOIN " . TABLE_PREFIX . "userfield AS userfield ON(userfield.userid = user.userid)
		LEFT JOIN " . TABLE_PREFIX . "usertextfield AS usertextfield ON(usertextfield.userid = user.userid)
		" . iif($foruminfo['allowicons'], "LEFT JOIN " . TABLE_PREFIX . "icon AS icon ON(icon.iconid = post.iconid)") . "
		" . iif($vbulletin->options['avatarenabled'], "LEFT JOIN " . TABLE_PREFIX . "avatar AS avatar ON(avatar.avatarid = user.avatarid) LEFT JOIN " . TABLE_PREFIX . "customavatar AS customavatar ON(customavatar.userid = user.userid)") . "
		LEFT JOIN " . TABLE_PREFIX . "editlog AS editlog ON(editlog.postid = post.postid)
		LEFT JOIN " . TABLE_PREFIX . "postparsed AS postparsed ON(postparsed.postid = post.postid AND postparsed.styleid = " . intval(STYLEID) . " AND postparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigparsed AS sigparsed ON(sigparsed.userid = user.userid AND sigparsed.styleid = " . intval(STYLEID) . " AND sigparsed.languageid = " . intval(LANGUAGEID) . ")
		LEFT JOIN " . TABLE_PREFIX . "sigpic AS sigpic ON(sigpic.userid = post.userid)
		WHERE post.postid = $postid
	");		
	
	// check for attachments
	if ($post['attach'])
	{
		$attachments = $db->query_read_slave("
			SELECT dateline, thumbnail_dateline, filename, filesize, visible, attachmentid, counter,
				postid, IF(thumbnail_filesize > 0, 1, 0) AS hasthumbnail, thumbnail_filesize,
				attachmenttype.thumbnail AS build_thumbnail, attachmenttype.newwindow
			FROM " . TABLE_PREFIX . "attachment
			LEFT JOIN " . TABLE_PREFIX . "attachmenttype AS attachmenttype USING (extension)
			WHERE postid = $postid
			ORDER BY attachmentid
		");
		while ($attachment = $db->fetch_array($attachments))
		{
			if (!$attachment['build_thumbnail'])
			{
				$attachment['hasthumbnail'] = false;
			}
			$post['attachments']["$attachment[attachmentid]"] = $attachment;
		}
	}
	
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['cangetattachment']))
	{
		$vbulletin->options['viewattachedimages'] = 0;
		$vbulletin->options['attachthumbs'] = 0;
	}
	
	$show['inlinemod'] = false;
	
	$saveparsed = ''; // inialise
	
	$show['spacer'] = false;
	
	$post['postcount'] =& $vbulletin->GPC['postcount'];
	
	$postbit_factory =& new vB_Postbit_Factory();
	$postbit_factory->registry =& $vbulletin;
	$postbit_factory->forum =& $foruminfo;
	$postbit_factory->thread =& $threadinfo;
	$postbit_factory->cache = array();
	$postbit_factory->bbcode_parser =& new vB_BbCodeParser($vbulletin, fetch_tag_list());

	$postbit_obj =& $postbit_factory->fetch_postbit('post');
	$postbit_obj->highlight =& $replacewords;
	$postbit_obj->cachable = (!$post['pagetext_html'] AND $vbulletin->options['cachemaxage'] > 0 AND (TIMENOW - ($vbulletin->options['cachemaxage'] * 60 * 60 * 24)) <= $threadinfo['lastpost']);
	$postbits = $postbit_obj->construct_postbit($post);
	
	$randomrow = rand(1,$rowcount['count']);
	$randomid = $db->query_first("
					SELECT postid
					FROM " . TABLE_PREFIX . "post
					$forumclause
					LIMIT $randomrow,1;
					");	
	
	$randomid = $randomid['postid'];
	
	eval('print_output("' . fetch_template('randompost') . '");');	
}
else
{
	// couldn't find a random post to display
	eval(standard_error(fetch_error('could_not_find_random_post', $vbulletin->options['bburl'])));	
}

?>