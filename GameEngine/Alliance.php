<?php

#################################################################################
##              -= YOU MAY NOT REMOVE OR CHANGE THIS NOTICE =-                 ##
## --------------------------------------------------------------------------- ##
##  Project:       TravianZ                                                    ##
##  Version:       22.06.2015                    			       ## 
##  Filename       Alliance.php                                                ##
##  Developed by:  Mr.php , Advocaite , brainiacX , yi12345 , Shadow , ronix   ## 
##  Fixed by:      Shadow - STARVATION , HERO FIXED COMPL.  		       ##
##  Fixed by:      InCube - double troops				       ##
##  License:       TravianZ Project                                            ##
##  Copyright:     TravianZ (c) 2010-2015. All rights reserved.                ##
##  URLs:          http://travian.shadowss.ro                		       ##
##  Source code:   https://github.com/Shadowss/TravianZ		               ## 
##                                                                             ##
#################################################################################

use App\Entity\User;

global $autoprefix;

// even with autoloader created, we can't use it here yet, as it's not been created
// ... so, let's see where it is and include it
$autoloader_found = false;
// go max 5 levels up - we don't have folders that go deeper than that
$autoprefix = '';
for ($i = 0; $i < 5; $i++) {
    $autoprefix = str_repeat('../', $i);
    if (file_exists($autoprefix.'autoloader.php')) {
        $autoloader_found = true;
        include_once $autoprefix.'autoloader.php';
        break;
    }
}

if (!$autoloader_found) {
    die('Could not find autoloading class.');
}

class Alliance {

		public $gotInvite = false;
		public $inviteArray = array();
		public $allianceArray = array();
		public $userPermArray = array();

		public function procAlliance($get) {
			global $session, $database;

			if($session->alliance != 0) {
				$this->allianceArray = $database->getAlliance($session->alliance);
				// Permissions Array
				// [id] => id [uid] => uid [alliance] => alliance [opt1] => X [opt2] => X [opt3] => X [opt4] => X [opt5] => X [opt6] => X [opt7] => X [opt8] => X
				$this->userPermArray = $database->getAlliPermissions($session->uid, $session->alliance);
			} else {
				$this->inviteArray = $database->getInvitation($session->uid);
				$this->gotInvite = count($this->inviteArray) == 0 ? false : true;
			}
			if(isset($get['a'])) {
				switch($get['a']) {
					case 2:
						$this->rejectInvite($get);
						break;
					case 3:
						$this->acceptInvite($get);
						break;
					default:
						break;
				}
			}
			if(isset($get['o'])) {
				switch($get['o']) {
					case 4:
						$this->delInvite($get);
						break;
					default:
						break;
				}
			}
		}

		/**
		 * Redirects to the option menu if some errors were generated
		 */
		
		public function redirect()
		{
		    header("location: allianz.php?s=5");
		    exit;
		}
		
		public function procAlliForm($post) {
			if(isset($post['ft'])) {
				switch($post['ft']) {
					case "ali1":
						$this->createAlliance($post);
						break;
				}

			}
			if(isset($post['dipl']) && isset($post['a_name'])) {
				$this->changediplomacy($post);
			}

			if(isset($post['s'])) {
				if(isset($post['o'])) {
					switch($post['o']) {
						case 1:
							if(isset($_POST['a'])) {
								$this->changeUserPermissions($post);
							}
							break;
						case 2:
							if(isset($_POST['a_user'])) {
								$this->kickAlliUser($post);
							}
							break;
						case 4:
							if(isset($_POST['a']) && $_POST['a'] == 4) {
								$this->sendInvite($post);
							}
							break;
						case 3:
							$this->updateAlliProfile($post);
							break;
						case 11:
							$this->quitally($post);
							break;
						case 100:
							$this->changeAliName($post);
							break;
					}
				}
			}
		}

		/*****************************************
		Function to process of sending invitations
		*****************************************/
		public function sendInvite($post) {
			global $form, $database, $session;
			if($session->access != BANNED){
			$UserData = $database->getUserArray(stripslashes($post['a_name']), 0);
			if($this->userPermArray['opt4'] == 0) {
				$form->addError("perm", NO_PERMISSION);
			}elseif(!isset($post['a_name']) || $post['a_name'] == "") {
				$form->addError("name1", NAME_EMPTY);
			}elseif(!User::exists($database, $post['a_name'])) {
				$form->addError("name2", NAME_NO_EXIST."".stripslashes(stripslashes($post['a_name'])));
			}elseif($UserData['id'] == $session->uid) {
				$form->addError("name3", SAME_NAME);
			}elseif($database->getInvitation2($UserData['id'],$session->alliance)) {
				$form->addError("name4", $post['a_name'].ALREADY_INVITED);
			}elseif($UserData['alliance'] == $session->alliance) {
				$form->addError("name5", $post['a_name'].ALREADY_IN_ALLY);
			}else{
				// Obtenemos la informacion necesaria
				$aid = $session->alliance;
				// Insertamos invitacion
				$database->sendInvitation($UserData['id'], $aid, $session->uid);
				// Log the notice
				$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid=' . $session->uid . '">' . addslashes($session->username) . '</a> has invited  <a href="spieler.php?uid=' . $UserData['id'] . '">' . addslashes($UserData['username']) . '</a> into the alliance.');
				// send invitation via in-game messages
				$database->sendMessage(
				    $UserData['id'],
				    4,
				    'Alliance invitation.',
				    $database->escape("Hi, ".$UserData['username']."!\n\nThis is to inform you that you have been invited to join an alliance. To accept this invitation, please visit your Embassy.\n\nYours sincerely,\n<i>Server Robot :)</i>"),
				    0,
				    0,
				    0,
				    0,
				    0,
				    true);
			}
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to reject an invitation
		*****************************************/
		private function rejectInvite($get) {
			global $database, $session;
			if($session->access != BANNED){
			foreach($this->inviteArray as $invite) {
			    if($invite['id'] == $get['d'] && $invite['uid'] == $session->uid) {
					$database->removeInvitation($get['d']);
					$database->insertAlliNotice($invite['alliance'], '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has rejected the invitation.');
				}
			}
			    header("Location: build.php?gid=18");
			    exit;
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to del an invitation
		*****************************************/
		private function delInvite($get) {
			global $database, $session;
			if($session->access != BANNED){
			$inviteArray = $database->getAliInvitations($session->alliance);
			foreach($inviteArray as $invite) {
			    if($invite['id'] == $get['d'] && $invite['alliance'] == $session->alliance && $this->userPermArray['opt4'] == 1) {
				    $invitename = $database->getUserArray($invite['uid'], 1);
					$database->removeInvitation($get['d']);
					$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has deleted the invitation for <a href="spieler.php?uid='.$invitename['id'].'">'.addslashes($invitename['username']).'</a>.');
				}
			}
			    header("Location: allianz.php?delinvite");
			    exit;
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to accept an invitation
		*****************************************/
		private function acceptInvite($get) {
			global $form, $database, $session;

			if ($session->access != BANNED) {
    			foreach ($this->inviteArray as $invite) {
        			if ($session->alliance == 0) {
        				if ($invite['id'] == $get['d'] && $invite['uid'] == $session->uid) {
            				$memberlist = $database->getAllMember($invite['alliance']);
            				$alliance_info = $database->getAlliance($invite['alliance']);
            				if (count($memberlist) < $alliance_info['max']) {
            					$database->removeInvitation($get['d']);
            					$database->updateUserField($invite['uid'], "alliance", $invite['alliance'], 1);
            					$database->createAlliPermissions($invite['uid'], $invite['alliance'], '', 0, 0, 0, 0, 0, 0, 0, 0);
            					// Log the notice
            					$database->insertAlliNotice($invite['alliance'], '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has joined the alliance.');
            				} else {
            				    $accept_error = 1;
            				    $max = $alliance_info['max'];
            				}
        				}
        			}
    			}

    			if($accept_error == 1){
    			    $form->addError("ally_accept", "The alliance can contain only ".$max." members at this moment.");
    			}else{
    			    header("Location: build.php?gid=18");
    			    exit;
    			}
			} else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to create an alliance
		*****************************************/
		private function createAlliance($post) {
			global $form, $database, $session, $bid18, $building;
			if($session->access != BANNED){
			if(!isset($post['ally1']) || $post['ally1'] == "") {
				$form->addError("ally1", ATAG_EMPTY);
			}
			if(!isset($post['ally2']) || $post['ally2'] == "") {
				$form->addError("ally2", ANAME_EMPTY);
			}
			if($database->aExist($post['ally1'], "tag")) {
				$form->addError("ally1", ATAG_EXIST);
			}
			if($database->aExist($post['ally2'], "name")) {
				$form->addError("ally2", ANAME_EXIST);
			}
			if($session->alliance != 0){
			    $form->addError("ally3", ALREADY_ALLY_MEMBER);
			}
			if($building->getTypeLevel(18) < 3){
			    $form->addError("ally4", ALLY_TOO_LOW);
			}
			if($form->returnErrors() != 0) {
				$_SESSION['errorarray'] = $form->getErrors();
				$_SESSION['valuearray'] = $post;
				if($building->getTypeLevel(18) > 0) header("Location: build.php?gid=18");
				else header("Location: dorf2.php");
				exit;
			} else {
				$max = $bid18[$building->getTypeLevel(18)]['attri'];
				$aid = $database->createAlliance($post['ally1'], $post['ally2'], $session->uid, $max);
				$database->updateUserField($session->uid, "alliance", $aid, 1);
				$database->procAllyPop($aid);
				// Asign Permissions
				$database->createAlliPermissions($session->uid, $aid, 'Alliance founder', '1', '1', '1', '1', '1', '1', '1', '1');
				// log the notice
				$database->insertAlliNotice($aid, 'The alliance has been founded by <a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a>.');
				header("Location: build.php?gid=18");
				exit;
			}
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to change the alliance name
		*****************************************/
		private function changeAliName($get) {
			global $form, $database, $session;
			if($session->access == BANNED) {
			    header("Location: banned.php");
			    exit;
			}
			
			if(!isset($get['ally1']) || $get['ally1'] == "") $form->addError("ally1", ATAG_EMPTY);
			
			if(!isset($get['ally2']) || $get['ally2'] == "") $form->addError("ally2", ANAME_EMPTY);
			
			if($database->aExist($get['ally1'], "tag")) $form->addError("ally1", ATAG_EXIST);
			
			if($database->aExist($get['ally2'], "name")) $form->addError("ally2", ANAME_EXIST);
			
			if($this->userPermArray['opt3'] == 0) $form->addError("perm", NO_PERMISSION);
			
			if($form->returnErrors() == 0) {
				$database->setAlliName($session->alliance, $get['ally2'], $get['ally1']);
				// log the notice
				$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has changed the alliance name.');
			}
		}

		/*****************************************
		Function to create/change the alliance description
		*****************************************/
		private function updateAlliProfile($post) {
			global $database, $session, $form;
			if($session->access != BANNED){
			if($this->userPermArray['opt3'] == 0) {
				$form->addError("perm", NO_PERMISSION);
			}
			if($form->returnErrors() != 0) {
				$_SESSION['errorarray'] = $form->getErrors();
				$_SESSION['valuearray'] = $post;
			} else {
				$database->submitAlliProfile($session->alliance, $post['be2'], $post['be1']);
				// log the notice
				$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has changed the alliance description.');
			}
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		/*****************************************
		Function to change the user permissions
		*****************************************/
		private function changeUserPermissions($post)
		{
			global $database, $session, $form;
			if($session->access == BANNED)
			{
			    header("Location: banned.php");
			    exit;
			}
			
			if($this->userPermArray['opt1'] == 0) $form->addError("perm", NO_PERMISSION);	
			elseif($database->getUserField($post['a_user'], "alliance", 0) != $session->alliance) $form->addError("perm", USER_NOT_IN_YOUR_ALLY);
			elseif($post['a_user'] == $session->uid) $form->addError("perm", CANT_EDIT_YOUR_PERMISSIONS);
			else 
			{
			    $database->updateAlliPermissions($post['a_user'], $session->alliance, $post['a_titel'], $post['e1'], $post['e2'], $post['e3'], $post['e4'], $post['e5'], $post['e6'], $post['e7']);
				// log the notice
			    $database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a> has changed permissions of <a href="spieler.php?uid='.$post['a_user'].'">'.addslashes($database->getUserField($post['a_user'], "username", 0)).'</a>.');
			    $form->addError("perm", ALLY_PERMISSIONS_UPDATED);
			}
			
			if($form->returnErrors() > 0) 
			{
			    $_SESSION['errorarray'] = $form->getErrors();
			    $_SESSION['valuearray'] = $post;
			    header("Location: allianz.php?s=5");
			    exit;
			}
		}
		/*****************************************
		Function to kick a user from alliance
		*****************************************/
		private function kickAlliUser($post) {
			global $database, $session, $form;

			if ($session->access != BANNED) {
				$UserData = $database->getUserArray($post['a_user'], 1);
				if($this->userPermArray['opt2'] == 0) {
					$form->addError("perm", NO_PERMISSION);
				} else if($database->getUserField($post['a_user'], "alliance", 0) != $session->alliance){
				    $form->addError("perm", USER_NOT_IN_YOUR_ALLY);
				} else if($UserData['id'] != $session->uid){
					$database->updateUserField($post['a_user'], 'alliance', 0, 1);
					$database->deleteAlliPermissions($post['a_user']);
					$database->deleteAlliance($session->alliance);
					// log the notice
					$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid='.$UserData['id'].'">'.($kickedUsername = addslashes($database->getUserField($post['a_user'], "username", 0))).'</a> has been expelled from the alliance by <a href="spieler.php?uid='.$session->uid.'">'.addslashes($session->username).'</a>.');
				    if($session->alliance && $database->isAllianceOwner($UserData['id']) == $session->alliance){
						$newowner = $database->getAllMember2($session->alliance);
						$newleader = $newowner['id'];
						$q = "UPDATE " . TB_PREFIX . "alidata set leader = ".(int) $newleader." where id = ".(int) $session->alliance."";
						$database->query($q);
						$database->updateAlliPermissions($newleader, 1, 1, 1, 1, 1, 1, 1, 1, 1);
						Automation::updateMax($newleader);						
					}
					$form->addError("perm", $kickedUsername.ALLY_USER_KICKED);
				}
			} else {
			    header("Location: banned.php");
			    exit;
			}
		}
		/*****************************************
		Function to set forum link
		*****************************************/
		public function setForumLink($post) {
			global $database, $session, $form;
			if($session->access == BANNED)
			{
			    header("Location: banned.php");
			    exit;
			}
			
			if($this->userPermArray['opt5'] == 0) $form->addError("perm", NO_PERMISSION);
			else
			{
			    $database->setAlliForumdblink($session->alliance, $post['f_link']);
			    $form->addError("perm", ALLY_FORUM_LINK_UPDATED);
			}
		}
		/*****************************************
		Function to vote on forum survey
		*****************************************/
		public function Vote($post) {
			global $database, $session;
			if($session->access != BANNED){
			if($database->checkSurvey($post['tid']) && !$database->checkVote($post['tid'], $session->uid)){
			$survey = $database->getSurvey($post['tid']);
			$text = ''.$survey['voted'].','.$session->uid.',';
			$database->Vote($post['tid'], $post['vote'], $text);
			}
			    header("Location: allianz.php?s=2&fid2=".$post['fid2']."&pid=".$post['pid']."&tid=".$post['tid']);
			    exit;
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}
		/*****************************************
		Function to quit from alliance
		*****************************************/
		private function quitally($post) {
			global $database, $session, $form;
			if($session->access != BANNED){
			if(!isset($post['pw']) || $post['pw'] == "") {
				$form->addError("pw", PW_EMPTY);
			} elseif(!password_verify($post['pw'], $session->userinfo['password'])) {
			    $form->addError("pw", LOGIN_PW_ERROR);
			} else {
			    // check whether this is not the founder leaving and if he is, see whether
			    // his replacement has been selected
			    if (
			        $session->alliance &&
			        $database->isAllianceOwner($session->uid) == $session->alliance &&
			        $database->countAllianceMembers($session->alliance) > 1
			    ) {
				    // check that we have a valid new founder
				    if (!isset($post['new_founder'])) {
				        $form->addError("founder", 'Founder was not selected.');
				        return;
				    } else {
				        $post['new_founder'] = (int) $post['new_founder'];
				    }

    				$members = $database->getAllMember($session->alliance);
    				$validMemberFound = false;

    				foreach ($members as $member) {
    				    if ($member['id'] == $post['new_founder']) {
    				        $validMemberFound = true;
    				        break;
    				    }
    				}

    				if (!$validMemberFound || $post['new_founder'] == $session->uid) {
    				    $form->addError("founder", 'Invalid founder.');
    				    return;
    				}

    				$newleader = (int) $post['new_founder'];
    				$q = "UPDATE " . TB_PREFIX . "alidata set leader = ".$newleader." where id = ".(int) $session->alliance;
    				$_SESSION['alliance_user'] = 0;
    				$database->query($q);
    				$database->createAlliPermissions($newleader, $session->alliance, 'Alliance Leader', 1, 1, 1, 1, 1, 1, 1, 1);
    				Automation::updateMax($newleader);

    				// send the new founder an in-game message, notifying them of their election
    				$database->sendMessage(
    				    $newleader,
    				    4,
    				    'You are now leader of your alliance',
    				    "Hi!\n\nThis is to inform you that the former leader of your alliance - <a href=\"".rtrim(SERVER, '/')."/spieler.php?uid=".(int) $session->uid."\">".$database->escape($session->username)."</a>, has decided to quit and elected you as his replacement. You now gain full access, administration and responsibilities to your alliance.\n\nGood luck!\n\nYours sincerely,\n<i>Server Robot :)</i>",
    				    0,
    				    0,
    				    0,
    				    0,
    				    0,
    				    true);
				}

				$database->updateUserField($session->uid, 'alliance', 0, 1);
				$database->deleteAlliPermissions($session->uid);
				// log the notice
				$database->deleteAlliance($session->alliance);
				$database->insertAlliNotice($session->alliance, '<a href="spieler.php?uid=' . $session->uid . '">' . addslashes($session->username) . '</a> has quit the alliance.');
				header("Location: spieler.php?uid=".$session->uid);
				exit;
			}
			}else{
			    header("Location: banned.php");
			    exit;
			}
		}

		private function changediplomacy($post) {
			global $database, $session, $form;
			if($session->access == BANNED) {			    
			    header("Location: banned.php");
			    exit;
			}
			if($this->userPermArray['opt6'] == 1){
			    if(!empty($post['a_name']) || !empty($post['dipl'])){
			        $aName = $post['a_name'];
			        $aType = (int)intval($post['dipl']);
			        if($database->aExist($aName, "tag")) {
			            $allianceID = $database->getAllianceID($aName);
			            if($allianceID != $session->alliance) {
			                if($aType >= 1 and $aType <= 3) {
			                    if(!$database->diplomacyInviteCheck2($session->alliance, $allianceID)) {
			                        if($database->diplomacyCheckLimits($session->alliance, $aType)){
			                            $database->diplomacyInviteAdd($session->alliance, $allianceID, $aType);
			                            if($aType == 1){
			                                $notice = OFFERED_CONFED_TO;
			                            }else if($aType == 2){
			                                $notice = OFFERED_NON_AGGRESION_PACT_TO;
			                            }else if($aType == 3){
			                                $notice = DECLARED_WAR_ON;
			                            }
			                            $database->insertAlliNotice($session->alliance, '<a href="allianz.php?aid='.$session->alliance.'">'.$database->getAllianceName($session->alliance).'</a> '.$notice.' <a href="allianz.php?aid='.$allianceID.'">'.$aName.'</a>.');
			                            $database->insertAlliNotice($allianceID, '<a href="allianz.php?aid='.$session->alliance.'">'.$database->getAllianceName($session->alliance).'</a> '.$notice.' <a href="allianz.php?aid='.$allianceID.'">'.$aName.'</a>.');
			                            $form->addError("name", INVITE_SENT);
			                            
			                        }
			                        else $form->addError("name", ALLY_TOO_MUCH_PACTS);
			                    } 
			                    else $form->addError("name", INVITE_ALREADY_SENT);	                    
			                } 
			                else $form->addError("name", WRONG_DIPLOMACY);		                    		                
			            }
			            else $form->addError("name", CANNOT_INVITE_SAME_ALLY);			                
			        } 
			        else $form->addError("name", ALLY_DOESNT_EXISTS);
			    }
			    else $form->addError("name", NAME_OR_DIPL_EMPTY);
			}
			else $form->addError("name", NO_PERMISSION);
		}
}

$alliance = new Alliance;

?>
