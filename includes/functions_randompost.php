<?php
//-----------------------------------------------------------------------------
// $RCSFile: functions_network.php $ $Revision: 1.50 $
// $Date: 2009/07/01 19:49:46 $
//-----------------------------------------------------------------------------

function can_viewpost($postid)//$postinfo,$threadinfo,$foruminfo)
{
	global $vbulletin; 
	
	$postinfo = fetch_postinfo($postid);
	$threadinfo = fetch_threadinfo($postinfo['threadid']);
	$foruminfo = fetch_foruminfo($threadinfo['forumid']);
	
	if (!$postinfo['postid'])
	{	
		return false;
	}	
	
	if ((!$postinfo['visible'] OR $postinfo ['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{
		return false;
	}
	
	if ((!$threadinfo['visible'] OR $threadinfo['isdeleted']) AND !can_moderate($threadinfo['forumid']))
	{	
		return false;
	}
	
	$forumperms = fetch_permissions($threadinfo['forumid']);
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canview']) OR !($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewthreads']))
	{
		return false;
	}
	
	if (!($forumperms & $vbulletin->bf_ugp_forumpermissions['canviewothers']) AND ($threadinfo['postuserid'] != $vbulletin->userinfo['userid'] OR $vbulletin->userinfo['userid'] == 0))
	{
		return false;
	}		
	
	// check if there is a forum password and if so, ensure the user has it set
	if (!verify_forum_password($foruminfo['forumid'], $foruminfo['password'],false))
	{
		return false;
	}
		
	return true;
}

?>