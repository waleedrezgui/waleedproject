<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2002-2003 Jean-Louis Bergamo   <jlb@j1b.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis@dolibarr.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/adherents/fiche.php
 *       \ingroup    member
 *       \brief      Page of member
 *       \version    $Id: fiche.php,v 1.238 2011/07/31 22:23:27 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/member.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/images.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/functions2.lib.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent_type.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/extrafields.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/cotisation.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/bank/class/account.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formcompany.class.php");

$langs->load("companies");
$langs->load("bills");
$langs->load("members");
$langs->load("users");

// Security check
if (! $user->rights->adherent->lire) accessforbidden();

$adh = new Adherent($db);
$extrafields = new ExtraFields($db);

$errmsg=''; $errmsgs=array();

$action=GETPOST("action");
$rowid=GETPOST("rowid");
$typeid=GETPOST("typeid");

if ($rowid)
{
	// Load member
	$result = $adh->fetch($rowid);

	// Define variables to know what current user can do on users
	$canadduser=($user->admin || $user->rights->user->user->creer);
	// Define variables to know what current user can do on properties of user linked to edited member
	if ($adh->user_id)
	{
		// $user est le user qui edite, $adh->user_id est l'id de l'utilisateur lies au membre edite
		$caneditfielduser=( (($user->id == $adh->user_id) && $user->rights->user->self->creer)
		|| (($user->id != $adh->user_id) && $user->rights->user->user->creer) );
		$caneditpassworduser=( (($user->id == $adh->user_id) && $user->rights->user->self->password)
		|| (($user->id != $adh->user_id) && $user->rights->user->user->password) );
	}
}

// Define variables to know what current user can do on members
$canaddmember=$user->rights->adherent->creer;
// Define variables to know what current user can do on properties of a member
if ($rowid)
{
	$caneditfieldmember=$user->rights->adherent->creer;
}



/*
 * 	Actions
 */

if ($_POST['action'] == 'setuserid' && ($user->rights->user->self->creer || $user->rights->user->user->creer))
{
	$error=0;
	if (empty($user->rights->user->user->creer))	// If can edit only itself user, we can link to itself only
	{
		if ($_POST["userid"] != $user->id && $_POST["userid"] != $adh->user_id)
		{
			$error++;
			$mesg='<div class="error">'.$langs->trans("ErrorUserPermissionAllowsToLinksToItselfOnly").'</div>';
		}
	}

	if (! $error)
	{
		if ($_POST["userid"] != $adh->user_id)	// If link differs from currently in database
		{
			$result=$adh->setUserId($_POST["userid"]);
			if ($result < 0) dol_print_error($adh->db,$adh->error);
			$_POST['action']='';
			$action='';
		}
	}
}
if ($_POST['action'] == 'setsocid')
{
	$error=0;
	if (! $error)
	{
		if ($_POST["socid"] != $adh->fk_soc)	// If link differs from currently in database
		{
			$sql ="SELECT rowid FROM ".MAIN_DB_PREFIX."adherent";
			$sql.=" WHERE fk_soc = '".$_POST["socid"]."'";
			$sql.=" AND entity = ".$conf->entity;
			$resql = $db->query($sql);
			if ($resql)
			{
				$obj = $db->fetch_object($resql);
				if ($obj && $obj->rowid > 0)
				{
					$othermember=new Adherent($db);
					$othermember->fetch($obj->rowid);
					$thirdparty=new Societe($db);
					$thirdparty->fetch($_POST["socid"]);
					$error++;
					$errmsg='<div class="error">'.$langs->trans("ErrorMemberIsAlreadyLinkedToThisThirdParty",$othermember->getFullName($langs),$othermember->login,$thirdparty->nom).'</div>';
				}
			}

			if (! $error)
			{
				$result=$adh->setThirdPartyId($_POST["socid"]);
				if ($result < 0) dol_print_error($adh->db,$adh->error);
				$_POST['action']='';
				$action='';
			}
		}
	}
}

// Create user from a member
if ($_POST["action"] == 'confirm_create_user' && $_POST["confirm"] == 'yes' && $user->rights->user->user->creer)
{
	if ($result > 0)
	{
		// Creation user
		$nuser = new User($db);
		$result=$nuser->create_from_member($adh,$_POST["login"]);

		if ($result < 0)
		{
			$langs->load("errors");
			$errmsg=$langs->trans($nuser->error);
		}
	}
	else
	{
		$errmsg=$adh->error;
	}
}

// Create third party from a member
if ($_POST["action"] == 'confirm_create_thirdparty' && $_POST["confirm"] == 'yes' && $user->rights->societe->creer)
{
	if ($result > 0)
	{
		// Creation user
		$company = new Societe($db);
		$result=$company->create_from_member($adh,$_POST["companyname"]);

		if ($result < 0)
		{
			$langs->load("errors");
			$errmsg=$langs->trans($company->error);
			$errmsgs=$company->errors;
		}
	}
	else
	{
		$errmsg=$adh->error;
	}
}

if ($_REQUEST["action"] == 'confirm_sendinfo' && $_REQUEST["confirm"] == 'yes')
{
	if ($adh->email)
	{
		$result=$adh->send_an_email($langs->transnoentitiesnoconv("ThisIsContentOfYourCard")."\n\n%INFOS%\n\n",$langs->transnoentitiesnoconv("CardContent"));
		$mesg=$langs->trans("CardSent");
	}
}

if ($_REQUEST["action"] == 'update' && ! $_POST["cancel"] && $user->rights->adherent->creer)
{
	require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

	$datenaiss='';
	if (isset($_POST["naissday"]) && $_POST["naissday"]
		&& isset($_POST["naissmonth"]) && $_POST["naissmonth"]
		&& isset($_POST["naissyear"]) && $_POST["naissyear"])
	{
		$datenaiss=dol_mktime(12, 0, 0, $_POST["naissmonth"], $_POST["naissday"], $_POST["naissyear"]);
	}

	// Create new object
	if ($result > 0)
	{
		$adh->oldcopy=dol_clone($adh);

		// Change values
		$adh->civilite_id = trim($_POST["civilite_id"]);
		$adh->prenom      = trim($_POST["prenom"]);
		$adh->nom         = trim($_POST["nom"]);
		$adh->login       = trim($_POST["login"]);
		$adh->pass        = trim($_POST["pass"]);

		$adh->societe     = trim($_POST["societe"]);
        $adh->adresse     = trim($_POST["address"]);    // deprecated
		$adh->address     = trim($_POST["address"]);
        $adh->cp          = trim($_POST["zipcode"]);    // deprecated
		$adh->zip         = trim($_POST["zipcode"]);
        $adh->ville       = trim($_POST["town"]);       // deprecated
        $adh->town        = trim($_POST["town"]);

		$adh->fk_departement = $_POST["departement_id"];
		$adh->pays_id        = $_POST["pays_id"];

		$adh->phone       = trim($_POST["phone"]);
		$adh->phone_perso = trim($_POST["phone_perso"]);
		$adh->phone_mobile= trim($_POST["phone_mobile"]);
		$adh->email       = trim($_POST["email"]);
		$adh->naiss       = $datenaiss;

		$adh->typeid      = $_POST["typeid"];
		$adh->note        = trim($_POST["comment"]);
		$adh->morphy      = $_POST["morphy"];

		$adh->amount      = $_POST["amount"];

        if (GETPOST('deletephoto')) $adh->photo='';
		elseif (! empty($_FILES['photo']['name'])) $adh->photo  = dol_sanitizeFileName($_FILES['photo']['name']);

		// Get status and public property
		$adh->statut      = $_POST["statut"];
		$adh->public      = $_POST["public"];

		// Get extra fields
		foreach($_POST as $key => $value)
		{
			if (preg_match("/^options_/",$key))
			{
				$adh->array_options[$key]=$_POST[$key];
			}
		}

		// Check if we need to also synchronize user information
		$nosyncuser=0;
		if ($adh->user_id)	// If linked to a user
		{
			if ($user->id != $adh->user_id && empty($user->rights->user->user->creer)) $nosyncuser=1;		// Disable synchronizing
		}

		// Check if we need to also synchronize password information
		$nosyncuserpass=0;
		if ($adh->user_id)	// If linked to a user
		{
			if ($user->id != $adh->user_id && empty($user->rights->user->user->password)) $nosyncuserpass=1;	// Disable synchronizing
		}

		$result=$adh->update($user,0,$nosyncuser,$nosyncuserpass);
		if ($result >= 0 && ! sizeof($adh->errors))
		{
            $dir= $conf->adherent->dir_output . '/' . get_exdir($adh->id,2,0,1).'/photos';
		    $file_OK = is_uploaded_file($_FILES['photo']['tmp_name']);
            if ($file_OK)
            {
    		    if (GETPOST('deletephoto'))
                {
                    $fileimg=$conf->adherent->dir_output.'/'.get_exdir($adh->id,2,0,1).'/photos/'.$adh->photo;
                    $dirthumbs=$conf->adherent->dir_output.'/'.get_exdir($adh->id,2,0,1).'/photos/thumbs';
                    dol_delete_file($fileimg);
                    dol_delete_dir_recursive($dirthumbs);
                }

    		    if (image_format_supported($_FILES['photo']['name']) > 0)
    			{
    				dol_mkdir($dir);

    				if (@is_dir($dir))
    				{
    					$newfile=$dir.'/'.dol_sanitizeFileName($_FILES['photo']['name']);
    					if (! dol_move_uploaded_file($_FILES['photo']['tmp_name'],$newfile,1,0,$_FILES['photo']['error']) > 0)
    					{
    						$message .= '<div class="error">'.$langs->trans("ErrorFailedToSaveFile").'</div>';
    					}
    					else
    					{
    						// Create small thumbs for company (Ratio is near 16/9)
    						// Used on logon for example
    						$imgThumbSmall = vignette($newfile, $maxwidthsmall, $maxheightsmall, '_small', $quality);

    						// Create mini thumbs for company (Ratio is near 16/9)
    						// Used on menu or for setup page for example
    						$imgThumbMini = vignette($newfile, $maxwidthmini, $maxheightmini, '_mini', $quality);
    					}
    				}
    			}
    			else
    			{
                    $errmsgs[] = "ErrorBadImageFormat";
    			}
            }

			$_GET["rowid"]=$adh->id;
			$_REQUEST["action"]='';
		}
		else
		{
            if ($adh->error) $errmsg=$adh->error;
            else $errmsgs=$adh->errors;
			$action='';
		}
	}
}

if ($_POST["action"] == 'add' && $user->rights->adherent->creer)
{
	$datenaiss='';
	if (isset($_POST["naissday"]) && $_POST["naissday"]
		&& isset($_POST["naissmonth"]) && $_POST["naissmonth"]
		&& isset($_POST["naissyear"]) && $_POST["naissyear"])
	{
		$datenaiss=dol_mktime(12, 0, 0, $_POST["naissmonth"], $_POST["naissday"], $_POST["naissyear"]);
	}
	$datecotisation='';
	if (isset($_POST["reday"]) && isset($_POST["remonth"]) && isset($_POST["reyear"]))
    {
		$datecotisation=dol_mktime(12, 0 , 0, $_POST["remonth"], $_POST["reday"], $_POST["reyear"]);
	}

    $typeid=$_POST["typeid"];
	$civilite_id=$_POST["civilite_id"];
    $nom=$_POST["nom"];
    $prenom=$_POST["prenom"];
    $societe=$_POST["societe"];
    $address=$_POST["address"];
    $zip=$_POST["zipcode"];
    $town=$_POST["town"];
	$departement_id=$_POST["departement_id"];
    $pays_id=$_POST["pays_id"];

    $phone=$_POST["phone"];
    $phone_perso=$_POST["phone_perso"];
    $phone_mobile=$_POST["phone_mobile"];
    $email=$_POST["member_email"];
    $login=$_POST["member_login"];
    $pass=$_POST["password"];
    $photo=$_POST["photo"];
    $comment=$_POST["comment"];
    $morphy=$_POST["morphy"];
    $cotisation=$_POST["cotisation"];
    $public=$_POST["public"];

    $userid=$_POST["userid"];
    $socid=$_POST["socid"];

    $adh->civilite_id = $civilite_id;
    $adh->prenom      = $prenom;
    $adh->nom         = $nom;
    $adh->societe     = $societe;
    $adh->adresse     = $address; // deprecated
    $adh->address     = $address;
    $adh->cp          = $zip;     // deprecated
    $adh->zip         = $zip;
    $adh->ville       = $town;    // deprecated
    $adh->town        = $town;
    $adh->fk_departement = $departement_id;
    $adh->pays_id     = $pays_id;
    $adh->phone       = $phone;
    $adh->phone_perso = $phone_perso;
    $adh->phone_mobile= $phone_mobile;
    $adh->email       = $email;
    $adh->login       = $login;
    $adh->pass        = $pass;
    $adh->naiss       = $datenaiss;
    $adh->photo       = $photo;
    $adh->typeid      = $typeid;
    $adh->note        = $comment;
    $adh->morphy      = $morphy;
    $adh->user_id     = $userid;
    $adh->fk_soc      = $socid;
    $adh->public      = $public;

    // Get extra fields
    foreach($_POST as $key => $value)
    {
        if (preg_match("/^options_/",$key))
        {
            $adh->array_options[$key]=$_POST[$key];
        }
    }

    // Check parameters
    if (empty($morphy) || $morphy == "-1") {
    	$error++;
        $errmsg .= $langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Nature"))."<br>\n";
    }
    // Test si le login existe deja
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
        if (empty($login)) {
            $error++;
            $errmsg .= $langs->trans("ErrorFieldRequired",$langs->trans("Login"))."<br>\n";
        }
        else {
            $sql = "SELECT login FROM ".MAIN_DB_PREFIX."adherent WHERE login='".$db->escape($login)."'";
            $result = $db->query($sql);
            if ($result) {
                $num = $db->num_rows($result);
            }
            if ($num) {
                $error++;
                $langs->load("errors");
                $errmsg .= $langs->trans("ErrorLoginAlreadyExists",$login)."<br>\n";
            }
        }
        if (empty($pass)) {
            $error++;
            $errmsg .= $langs->trans("ErrorFieldRequired",$langs->transnoentities("Password"))."<br>\n";
        }
    }
    if (empty($nom)) {
        $error++;
        $langs->load("errors");
        $errmsg .= $langs->trans("ErrorFieldRequired",$langs->transnoentities("Lastname"))."<br>\n";
    }
	if ($morphy != 'mor' && (!isset($prenom) || $prenom=='')) {
		$error++;
        $langs->load("errors");
		$errmsg .= $langs->trans("ErrorFieldRequired",$langs->transnoentities("Firstname"))."<br>\n";
    }
    if (! ($typeid > 0)) {	// Keep () before !
        $error++;
        $errmsg .= $langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Type"))."<br>\n";
    }
    if ($conf->global->ADHERENT_MAIL_REQUIRED && ! isValidEMail($email)) {
        $error++;
        $langs->load("errors");
        $errmsg .= $langs->trans("ErrorBadEMail",$email)."<br>\n";
    }
    $public=0;
    if (isset($public)) $public=1;

    if (! $error)
    {
		$db->begin();

		// Email a peu pres correct et le login n'existe pas
        $result=$adh->create($user);
		if ($result > 0)
        {
			$db->commit();
			$rowid=$adh->id;
			$action='';
        }
        else
		{
			$db->rollback();

			if ($adh->error) $errmsg=$adh->error;
			else $errmsgs=$adh->errors;

			$action = 'create';
        }
    }
    else {
        $action = 'create';
    }
}

if ($user->rights->adherent->supprimer && $_REQUEST["action"] == 'confirm_delete' && $_REQUEST["confirm"] == 'yes')
{
    $result=$adh->delete($rowid);
    if ($result > 0)
    {
    	Header("Location: liste.php");
    	exit;
    }
    else
    {
    	$errmesg=$adh->error;
    }
}

if ($user->rights->adherent->creer && $_POST["action"] == 'confirm_valid' && $_POST["confirm"] == 'yes')
{
    $result=$adh->validate($user);

    $adht = new AdherentType($db);
    $adht->fetch($adh->typeid);

	if ($result >= 0 && ! sizeof($adh->errors))
	{
        // Send confirmation Email (selon param du type adherent sinon generique)
		if ($adh->email && $_POST["send_mail"])
		{
			$result=$adh->send_an_email($adht->getMailOnValid(),$conf->global->ADHERENT_MAIL_VALID_SUBJECT,array(),array(),array(),"","",0,2);
			if ($result < 0)
			{
				$errmsg.=$adh->error;
			}
		}

	    // Rajoute l'utilisateur dans les divers abonnements (mailman, spip, etc...)
	    if ($adh->add_to_abo() < 0)
	    {
	        // error
	        $errmsg.= $langs->trans("FaildToAddToMailmanList").': '.$adh->error."<br>\n";
	    }
	}
	else
	{
        if ($adh->error) $errmsg=$adh->error;
        else $errmsgs=$adh->errors;
		$action='';
	}
}

if ($user->rights->adherent->supprimer && $_POST["action"] == 'confirm_resign' && $_POST["confirm"] == 'yes')
{
    $adht = new AdherentType($db);
    $adht->fetch($adh->typeid);

    $result=$adh->resiliate($user);

    if ($result >= 0 && ! sizeof($adh->errors))
	{
	    if ($adh->email && $_POST["send_mail"])
		{
			$result=$adh->send_an_email($adht->getMailOnResiliate(),$conf->global->ADHERENT_MAIL_RESIL_SUBJECT,array(),array(),array(),"","",0,-1);
		}
		if ($result < 0)
		{
			$errmsg.=$adh->error;
		}

	    // supprime l'utilisateur des divers abonnements ..
	    if ($adh->del_to_abo() < 0)
	    {
	        // error
	        $errmsg.=$langs->trans("FaildToRemoveFromMailmanList").': '.$adh->error."<br>\n";
	    }
	}
	else
	{
		if ($adh->error) $errmsg=$adh->error;
		else $errmsgs=$adh->errors;
		$action='';
	}
}

if ($user->rights->adherent->supprimer && $_POST["action"] == 'confirm_del_spip' && $_POST["confirm"] == 'yes')
{
	if (! sizeof($adh->errors))
	{
	    if(!$adh->del_to_spip()){
	        $errmsg.="Echec de la suppression de l'utilisateur dans spip: ".$adh->error."<BR>\n";
	    }
	}
}

if ($user->rights->adherent->creer && $_POST["action"] == 'confirm_add_spip' && $_POST["confirm"] == 'yes')
{
	if (! sizeof($adh->errors))
	{
	    if (!$adh->add_to_spip())
	    {
	        $errmsg.="Echec du rajout de l'utilisateur dans spip: ".$adh->error."<BR>\n";
	    }
	}
}



/*
 * View
 */

// fetch optionals attributes and labels
$extralabels=$extrafields->fetch_name_optionals_label('member');

$help_url='EN:Module_Foundations|FR:Module_Adh&eacute;rents|ES:M&oacute;dulo_Miembros';
llxHeader('',$langs->trans("Member"),$help_url);

$html = new Form($db);
$htmlcompany = new FormCompany($db);

$countrynotdefined=$langs->trans("ErrorSetACountryFirst").' ('.$langs->trans("SeeAbove").')';

if ($action == 'create')
{
    /* ************************************************************************** */
    /*                                                                            */
    /* Fiche creation                                                             */
    /*                                                                            */
    /* ************************************************************************** */
    $adh->fk_departement = $_POST["departement_id"];

    // We set pays_id, pays_code and label for the selected country
    $adh->pays_id=$_POST["pays_id"]?$_POST["pays_id"]:$mysoc->pays_id;
    if ($adh->pays_id)
    {
        $sql = "SELECT rowid, code, libelle";
        $sql.= " FROM ".MAIN_DB_PREFIX."c_pays";
        $sql.= " WHERE rowid = ".$adh->pays_id;
        $resql=$db->query($sql);
        if ($resql)
        {
            $obj = $db->fetch_object($resql);
        }
        else
        {
            dol_print_error($db);
        }
        $adh->pays_id=$obj->rowid;
        $adh->pays_code=$obj->code;
        $adh->pays=$obj->libelle;
    }

    $adht = new AdherentType($db);

    print_fiche_titre($langs->trans("NewMember"));

    dol_htmloutput_mesg($errmsg,$errmsgs,'error');
    dol_htmloutput_mesg($mesg,$mesgs);

    if ($conf->use_javascript_ajax)
    {
        print "\n".'<script type="text/javascript" language="javascript">';
        print 'jQuery(document).ready(function () {
                    jQuery("#selectpays_id").change(function() {
                        document.formsoc.action.value="create";
                        document.formsoc.submit();
                    });
               })';
        print '</script>'."\n";
    }

    print '<form name="formsoc" action="'.$_SERVER["PHP_SELF"].'" method="post" enctype="multipart/form-data">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="add">';

    print '<table class="border" width="100%">';

    // Login
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
        print '<tr><td><span class="fieldrequired">'.$langs->trans("Login").' / '.$langs->trans("Id").'</span></td><td><input type="text" name="member_login" size="40" value="'.(isset($_POST["member_login"])?$_POST["member_login"]:$adh->login).'"></td></tr>';
    }

    // Moral-Physique
    $morphys["phy"] = $langs->trans("Physical");
    $morphys["mor"] = $langs->trans("Moral");
    print '<tr><td><span class="fieldrequired">'.$langs->trans("Nature")."</span></td><td>\n";
    print $html->selectarray("morphy", $morphys, isset($_POST["morphy"])?$_POST["morphy"]:$adh->morphy, 1);
    print "</td>\n";

    // Type
    print '<tr><td><span class="fieldrequired">'.$langs->trans("MemberType").'</span></td><td>';
    $listetype=$adht->liste_array();
    if (sizeof($listetype))
    {
        print $html->selectarray("typeid", $listetype, isset($_POST["typeid"])?$_POST["typeid"]:$typeid, 1);
    } else {
        print '<font class="error">'.$langs->trans("NoTypeDefinedGoToSetup").'</font>';
    }
    print "</td>\n";

    // Company
    print '<tr><td>'.$langs->trans("Company").'</td><td><input type="text" name="societe" size="40" value="'.(isset($_POST["societe"])?$_POST["societe"]:$adh->societe).'"></td></tr>';

    // Civility
    print '<tr><td>'.$langs->trans("UserTitle").'</td><td>';
    print $htmlcompany->select_civilite(isset($_POST["civilite_id"])?$_POST["civilite_id"]:$adh->civilite_id,'civilite_id').'</td>';
    print '</tr>';

    // Lastname
    print '<tr><td><span class="fieldrequired">'.$langs->trans("Lastname").'</span></td><td><input type="text" name="nom" value="'.(isset($_POST["nom"])?$_POST["nom"]:$adh->nom).'" size="40"></td>';
    print '</tr>';

    // Firstname
    print '<tr><td>'.$langs->trans("Firstname").'</td><td><input type="text" name="prenom" size="40" value="'.(isset($_POST["prenom"])?$_POST["prenom"]:$adh->prenom).'"></td>';
    print '</tr>';

    // Password
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
    	include_once(DOL_DOCUMENT_ROOT.'/lib/security.lib.php');
	    $generated_password=getRandomPassword('');
        print '<tr><td><span class="fieldrequired">'.$langs->trans("Password").'</span></td><td>';
        print '<input size="30" maxsize="32" type="text" name="password" value="'.$generated_password.'">';
        print '</td></tr>';
    }

    // Address
    print '<tr><td valign="top">'.$langs->trans("Address").'</td><td>';
    print '<textarea name="address" wrap="soft" cols="40" rows="2">'.(isset($_POST["address"])?$_POST["address"]:$adh->address).'</textarea>';
    print '</td></tr>';

    // Zip / Town
    print '<tr><td>'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td>';
    print $htmlcompany->select_ziptown((isset($_POST["zipcode"])?$_POST["zipcode"]:$adh->zip),'zipcode',array('town','selectpays_id','departement_id'),6);
    print ' ';
    print $htmlcompany->select_ziptown((isset($_POST["town"])?$_POST["town"]:$adh->town),'town',array('zipcode','selectpays_id','departement_id'));
    print '</td></tr>';

    // Country
    $adh->pays_id=$adh->pays_id?$adh->pays_id:$mysoc->pays_id;
    print '<tr><td width="25%">'.$langs->trans('Country').'</td><td>';
    $html->select_pays(isset($_POST["pays_id"])?$_POST["pays_id"]:$adh->pays_id,'pays_id');
    if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
    print '</td></tr>';

    // State
    if (empty($conf->global->MEMBER_DISABLE_STATE))
    {
        print '<tr><td>'.$langs->trans('State').'</td><td>';
        if ($adh->pays_id)
        {
            $htmlcompany->select_departement(isset($_POST["departement_id"])?$_POST["departement_id"]:$adh->fk_departement,$adh->pays_code);
        }
        else
        {
            print $countrynotdefined;
        }
        print '</td></tr>';
    }

    // Tel pro
    print '<tr><td>'.$langs->trans("PhonePro").'</td><td><input type="text" name="phone" size="20" value="'.(isset($_POST["phone"])?$_POST["phone"]:$adh->phone).'"></td></tr>';

    // Tel perso
    print '<tr><td>'.$langs->trans("PhonePerso").'</td><td><input type="text" name="phone_perso" size="20" value="'.(isset($_POST["phone_perso"])?$_POST["phone_perso"]:$adh->phone_perso).'"></td></tr>';

    // Tel mobile
    print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td><input type="text" name="phone_mobile" size="20" value="'.(isset($_POST["phone_mobile"])?$_POST["phone_mobile"]:$adh->phone_mobile).'"></td></tr>';

    // EMail
    print '<tr><td>'.($conf->global->ADHERENT_MAIL_REQUIRED?'<span class="fieldrequired">':'').$langs->trans("EMail").($conf->global->ADHERENT_MAIL_REQUIRED?'</span>':'').'</td><td><input type="text" name="member_email" size="40" value="'.(isset($_POST["member_email"])?$_POST["member_email"]:$adh->email).'"></td></tr>';

    // Birthday
    print "<tr><td>".$langs->trans("Birthday")."</td><td>\n";
    $html->select_date(($adh->naiss ? $adh->naiss : -1),'naiss','','',1,'formsoc');
    print "</td></tr>\n";

    // Profil public
    print "<tr><td>".$langs->trans("Public")."</td><td>\n";
    print $html->selectyesno("public",$adh->public,1);
    print "</td></tr>\n";

    // Attribut optionnels
    foreach($extrafields->attribute_label as $key=>$label)
    {
        $value=(isset($_POST["options_".$key])?$_POST["options_".$key]:'');
        print "<tr><td>".$label.'</td><td>';
        print $extrafields->showInputField($key,$value);
        print '</td></tr>'."\n";
    }

/*
    // Third party Dolibarr
    if ($conf->societe->enabled)
    {
        print '<tr><td>'.$langs->trans("LinkedToDolibarrThirdParty").'</td><td class="valeur">';
        print $html->select_societes($adh->fk_soc,'socid','',1);
        print '</td></tr>';
    }

    // Login Dolibarr
    print '<tr><td>'.$langs->trans("LinkedToDolibarrUser").'</td><td class="valeur">';
    print $html->select_users($adh->user_id,'userid',1);
    print '</td></tr>';
*/
    print "</table>\n";
    print '<br>';

    print '<center><input type="submit" class="button" value="'.$langs->trans("AddMember").'"></center>';

    print "</form>\n";

}

if ($action == 'edit')
{
	/********************************************
	 *
	 * Fiche en mode edition
	 *
	 ********************************************/

	$adh = new Adherent($db);
    $res=$adh->fetch($rowid);
    if ($res < 0) { dol_print_error($db,$adh->error); exit; }
    $res=$adh->fetch_optionals($rowid,$extralabels);
    if ($res < 0) { dol_print_error($db); exit; }

	$adht = new AdherentType($db);
    $adht->fetch($adh->typeid);

	// We set pays_id, and pays_code label of the chosen country
	if (isset($_POST["pays"]) || $adh->pays_id)
	{
		$sql = "SELECT rowid, code, libelle from ".MAIN_DB_PREFIX."c_pays where rowid = ".(isset($_POST["pays"])?$_POST["pays"]:$adh->pays_id);
		$resql=$db->query($sql);
		if ($resql)
		{
			$obj = $db->fetch_object($resql);
		}
		else
		{
			dol_print_error($db);
		}
		$adh->pays_id=$obj->rowid;
		$adh->pays_code=$obj->code;
		$adh->pays=$langs->trans("Country".$obj->code)?$langs->trans("Country".$obj->code):$obj->libelle;
	}

	$head = member_prepare_head($adh);

	dol_fiche_head($head, 'general', $langs->trans("Member"), 0, 'user');

	dol_htmloutput_errors($errmsg,$errmsgs);

	if ($mesg) print '<div class="ok">'.$mesg.'</div>';

	if ($conf->use_javascript_ajax)
	{
        print "\n".'<script type="text/javascript" language="javascript">';
        print 'jQuery(document).ready(function () {
                    jQuery("#selectpays").change(function() {
	               	    document.formsoc.action.value="edit";
                        document.formsoc.submit();
                    });
               })';
        print '</script>'."\n";
	}

	$rowspan=17;
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED)) $rowspan+=1;
	$rowspan+=sizeof($extrafields->attribute_label);
	if ($conf->societe->enabled) $rowspan++;

	print '<form name="formsoc" action="'.$_SERVER["PHP_SELF"].'" method="post" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"update\">";
	print "<input type=\"hidden\" name=\"rowid\" value=\"$rowid\">";
	print "<input type=\"hidden\" name=\"statut\" value=\"".$adh->statut."\">";

	print '<table class="border" width="100%">';

    // Ref
    print '<tr><td>'.$langs->trans("Ref").'</td><td class="valeur" colspan="2">'.$adh->id.'</td></tr>';

    // Login
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
        print '<tr><td><span class="fieldrequired">'.$langs->trans("Login").' / '.$langs->trans("Id").'</span></td><td colspan="2"><input type="text" name="login" size="30" value="'.(isset($_POST["login"])?$_POST["login"]:$adh->login).'"></td></tr>';
    }

    // Physique-Moral
	$morphys["phy"] = $langs->trans("Physical");
	$morphys["mor"] = $langs->trans("Morale");
	print '<tr><td><span class="fieldrequired">'.$langs->trans("Nature").'</span></td><td>';
	print $html->selectarray("morphy",  $morphys, isset($_POST["morphy"])?$_POST["morphy"]:$adh->morphy);
	print "</td>";
    // Photo
    print '<td align="center" valign="middle" width="25%" rowspan="'.$rowspan.'">';
    print $html->showphoto('memberphoto',$adh)."\n";
    if ($caneditfieldmember)
    {
        if ($adh->photo) print "<br>\n";
        print '<table class="nobordernopadding">';
        if ($adh->photo) print '<tr><td align="center"><input type="checkbox" class="flat" name="deletephoto" id="photodelete"> '.$langs->trans("Delete").'<br><br></td></tr>';
        print '<tr><td>'.$langs->trans("PhotoFile").'</td></tr>';
        print '<tr><td><input type="file" class="flat" name="photo" id="photoinput"></td></tr>';
        print '</table>';
    }
    print '</td>';

    // Type
    print '<tr><td><span class="fieldrequired">'.$langs->trans("Type").'</span></td><td>';
    if ($user->rights->adherent->creer)
    {
        print $html->selectarray("typeid",  $adht->liste_array(), (isset($_POST["typeid"])?$_POST["typeid"]:$adh->typeid));
    }
    else
    {
        print $adht->getNomUrl(1);
        print '<input type="hidden" name="typeid" value="'.$adh->typeid.'">';
    }
    print "</td></tr>";

	// Company
	print '<tr><td>'.$langs->trans("Company").'</td><td><input type="text" name="societe" size="40" value="'.(isset($_POST["societe"])?$_POST["societe"]:$adh->societe).'"></td></tr>';

	// Civilite
	print '<tr><td width="20%">'.$langs->trans("UserTitle").'</td><td width="35%">';
	print $htmlcompany->select_civilite(isset($_POST["civilite_id"])?$_POST["civilite_id"]:$adh->civilite_id)."\n";
	print '</td>';
	print '</tr>';

	// Name
	print '<tr><td><span class="fieldrequired">'.$langs->trans("Lastname").'</span></td><td><input type="text" name="nom" size="40" value="'.(isset($_POST["nom"])?$_POST["nom"]:$adh->nom).'"></td>';
	print '</tr>';

	// Firstname
	print '<tr><td width="20%">'.$langs->trans("Firstname").'</td><td><input type="text" name="prenom" size="40" value="'.(isset($_POST["prenom"])?$_POST["prenom"]:$adh->prenom).'"></td>';
	print '</tr>';

	// Password
	if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
	{
	    print '<tr><td><span class="fieldrequired">'.$langs->trans("Password").'</span></td><td><input type="password" name="pass" size="30" value="'.(isset($_POST["pass"])?$_POST["pass"]:$adh->pass).'"></td></tr>';
	}

	// Address
	print '<tr><td>'.$langs->trans("Address").'</td><td>';
	print '<textarea name="address" wrap="soft" cols="40" rows="2">'.(isset($_POST["address"])?$_POST["address"]:$adh->address).'</textarea>';
	print '</td></tr>';

    // Zip / Town
    print '<tr><td>'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td>';
    print $htmlcompany->select_ziptown((isset($_POST["zipcode"])?$_POST["zipcode"]:$adh->zip),'zipcode',array('town','selectpays_id','departement_id'),6);
    print ' ';
    print $htmlcompany->select_ziptown((isset($_POST["town"])?$_POST["town"]:$adh->town),'town',array('zipcode','selectpays_id','departement_id'));
    print '</td></tr>';

    // Country
    //$adh->pays_id=$adh->pays_id?$adh->pays_id:$mysoc->pays_id;    // In edit mode we don't force to company country if not defined
    print '<tr><td width="25%">'.$langs->trans('Country').'</td><td>';
    $html->select_pays(isset($_POST["pays_id"])?$_POST["pays_id"]:$adh->pays_id,'pays_id');
    if ($user->admin) print info_admin($langs->trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
    print '</td></tr>';

    // State
    if (empty($conf->global->MEMBER_DISABLE_STATE))
    {
    	print '<tr><td>'.$langs->trans('State').'</td><td>';
    	$htmlcompany->select_departement($adh->fk_departement,$adh->pays_code);
    	print '</td></tr>';
    }

	// Tel
	print '<tr><td>'.$langs->trans("PhonePro").'</td><td><input type="text" name="phone" size="20" value="'.(isset($_POST["phone"])?$_POST["phone"]:$adh->phone).'"></td></tr>';

	// Tel perso
	print '<tr><td>'.$langs->trans("PhonePerso").'</td><td><input type="text" name="phone_perso" size="20" value="'.(isset($_POST["phone_perso"])?$_POST["phone_perso"]:$adh->phone_perso).'"></td></tr>';

	// Tel mobile
	print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td><input type="text" name="phone_mobile" size="20" value="'.(isset($_POST["phone_mobile"])?$_POST["phone_mobile"]:$adh->phone_mobile).'"></td></tr>';

	// EMail
	print '<tr><td>'.($conf->global->ADHERENT_MAIL_REQUIRED?'<span class="fieldrequired">':'').$langs->trans("EMail").($conf->global->ADHERENT_MAIL_REQUIRED?'</span>':'').'</td><td><input type="text" name="email" size="40" value="'.(isset($_POST["email"])?$_POST["email"]:$adh->email).'"></td></tr>';

	// Date naissance
    print "<tr><td>".$langs->trans("Birthday")."</td><td>\n";
    $html->select_date(($adh->naiss ? $adh->naiss : -1),'naiss','','',1,'formsoc');
    print "</td></tr>\n";

	// Profil public
    print "<tr><td>".$langs->trans("Public")."</td><td>\n";
    print $html->selectyesno("public",(isset($_POST["public"])?$_POST["public"]:$adh->public),1);
    print "</td></tr>\n";

	// Other attributes
	foreach($extrafields->attribute_label as $key=>$label)
	{
	    $value=(isset($_POST["options_$key"])?$_POST["options_$key"]:$adh->array_options["options_$key"]);
		print "<tr><td>".$label."</td><td>";
        print $extrafields->showInputField($key,$value);
		print "</td></tr>\n";
	}

	// Third party Dolibarr
    if ($conf->societe->enabled)
    {
    	print '<tr><td>'.$langs->trans("LinkedToDolibarrThirdParty").'</td><td class="valeur">';
    	if ($adh->fk_soc)
	    {
	    	$company=new Societe($db);
	    	$result=$company->fetch($adh->fk_soc);
	    	print $company->getNomUrl(1);
	    }
	    else
	    {
	    	print $langs->trans("NoThirdPartyAssociatedToMember");
	    }
	    print '</td></tr>';
    }

    // Login Dolibarr
	print '<tr><td>'.$langs->trans("LinkedToDolibarrUser").'</td><td class="valeur">';
	if ($adh->user_id)
	{
		print $html->form_users($_SERVER['PHP_SELF'].'?rowid='.$adh->id,$adh->user_id,'none');
	}
	else print $langs->trans("NoDolibarrAccess");
	print '</td></tr>';

	print '<tr><td colspan="3" align="center">';
	print '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; &nbsp; &nbsp; ';
	print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
	print '</td></tr>';

	print '</table>';

	print '</form>';

	print '</div>';
}

if ($rowid && $action != 'edit')
{
	/* ************************************************************************** */
	/*                                                                            */
	/* Mode affichage                                                             */
	/*                                                                            */
	/* ************************************************************************** */

    $adh = new Adherent($db);
    $res=$adh->fetch($rowid);
    if ($res < 0) { dol_print_error($db,$adh->error); exit; }
    $res=$adh->fetch_optionals($rowid,$extralabels);
	if ($res < 0) { dol_print_error($db); exit; }

    $adht = new AdherentType($db);
    $res=$adht->fetch($adh->typeid);
	if ($res < 0) { dol_print_error($db); exit; }


	/*
	 * Affichage onglets
	 */
	$head = member_prepare_head($adh);

	dol_fiche_head($head, 'general', $langs->trans("Member"), 0, 'user');

	dol_htmloutput_errors($errmsg,$errmsgs);

	// Confirm create user
	if ($_GET["action"] == 'create_user')
	{
		$login=$adh->login;
		if (empty($login))
		{
			// Full firstname and name separated with a dot : firstname.name
			include_once(DOL_DOCUMENT_ROOT.'/lib/functions2.lib.php');
			$login=dol_buildlogin($adh->nom,$adh->prenom);
		}
		if (empty($login)) $login=strtolower(substr($adh->prenom, 0, 4)) . strtolower(substr($adh->nom, 0, 4));

		// Create a form array
		$formquestion=array(
		array('label' => $langs->trans("LoginToCreate"), 'type' => 'text', 'name' => 'login', 'value' => $login)
		);
        $text=$langs->trans("ConfirmCreateLogin").'<br>';
        if ($conf->societe->enabled)
        {
            if ($adh->fk_soc > 0) $text.=$langs->trans("UserWillBeExternalUser");
            else $text.=$langs->trans("UserWillBeInternalUser");
        }
		$ret=$html->form_confirm($_SERVER["PHP_SELF"]."?rowid=".$adh->id,$langs->trans("CreateDolibarrLogin"),$text,"confirm_create_user",$formquestion,'yes');
		if ($ret == 'html') print '<br>';
	}

	// Confirm create third party
	if ($_GET["action"] == 'create_thirdparty')
	{
		$name = $adh->getFullName($langs);
		if (! empty($name))
		{
			if ($adh->societe) $name.=' ('.$adh->societe.')';
		}
		else
		{
			$name=$adh->societe;
		}

		// Create a form array
		$formquestion=array(
		array('label' => $langs->trans("NameToCreate"), 'type' => 'text', 'name' => 'companyname', 'value' => $name));

		$ret=$html->form_confirm($_SERVER["PHP_SELF"]."?rowid=".$adh->id,$langs->trans("CreateDolibarrThirdParty"),$langs->trans("ConfirmCreateThirdParty"),"confirm_create_thirdparty",$formquestion,1);
		if ($ret == 'html') print '<br>';
	}

    // Confirm validate member
    if ($action == 'valid')
    {
		$langs->load("mails");

        $adht = new AdherentType($db);
        $adht->fetch($adh->typeid);

        $subjecttosend=$adh->makeSubstitution($conf->global->ADHERENT_MAIL_VALID_SUBJECT);
        $texttosend=$adh->makeSubstitution($adht->getMailOnValid());

        $tmp=$langs->trans("SendAnEMailToMember");
        $tmp.=' ('.$langs->trans("MailFrom").': <b>'.$conf->global->ADHERENT_MAIL_FROM.'</b>, ';
        $tmp.=$langs->trans("MailRecipient").': <b>'.$adh->email.'</b>)';
        $helpcontent='';
        $helpcontent.='<b>'.$langs->trans("MailFrom").'</b>: '.$conf->global->ADHERENT_MAIL_FROM.'<br>'."\n";
        $helpcontent.='<b>'.$langs->trans("MailRecipient").'</b>: '.$adh->email.'<br>'."\n";
		$helpcontent.='<b>'.$langs->trans("Subject").'</b>:<br>'."\n";
        $helpcontent.=$subjecttosend."\n";
        $helpcontent.="<br>";
        $helpcontent.='<b>'.$langs->trans("Content").'</b>:<br>';
        $helpcontent.=dol_htmlentitiesbr($texttosend)."\n";
		$label=$html->textwithpicto($tmp,$helpcontent,1,'help');

        // Cree un tableau formulaire
        $formquestion=array();
		if ($adh->email) $formquestion[0]=array('type' => 'checkbox', 'name' => 'send_mail', 'label' => $label,  'value' => ($conf->global->ADHERENT_DEFAULT_SENDINFOBYMAIL?true:false));
        $ret=$html->form_confirm("fiche.php?rowid=$rowid",$langs->trans("ValidateMember"),$langs->trans("ConfirmValidateMember"),"confirm_valid",$formquestion,1);
        if ($ret == 'html') print '<br>';
    }

    // Confirm send card by mail
    if ($action == 'sendinfo')
    {
        $ret=$html->form_confirm("fiche.php?rowid=$rowid",$langs->trans("SendCardByMail"),$langs->trans("ConfirmSendCardByMail",$adh->email),"confirm_sendinfo",'',0,1);
        if ($ret == 'html') print '<br>';
    }

    // Confirm resiliate
    if ($action == 'resign')
    {
		$langs->load("mails");

        $adht = new AdherentType($db);
        $adht->fetch($adh->typeid);

        $subjecttosend=$adh->makeSubstitution($conf->global->ADHERENT_MAIL_RESIL_SUBJECT);
        $texttosend=$adh->makeSubstitution($adht->getMailOnResiliate());

        $tmp=$langs->trans("SendAnEMailToMember");
        $tmp.=' ('.$langs->trans("MailFrom").': <b>'.$conf->global->ADHERENT_MAIL_FROM.'</b>, ';
        $tmp.=$langs->trans("MailRecipient").': <b>'.$adh->email.'</b>)';
        $helpcontent='';
        $helpcontent.='<b>'.$langs->trans("MailFrom").'</b>: '.$conf->global->ADHERENT_MAIL_FROM.'<br>'."\n";
        $helpcontent.='<b>'.$langs->trans("MailRecipient").'</b>: '.$adh->email.'<br>'."\n";
        $helpcontent.='<b>'.$langs->trans("Subject").'</b>:<br>'."\n";
        $helpcontent.=$subjecttosend."\n";
        $helpcontent.="<br>";
        $helpcontent.='<b>'.$langs->trans("Content").'</b>:<br>';
        $helpcontent.=dol_htmlentitiesbr($texttosend)."\n";
        $label=$html->textwithpicto($tmp,$helpcontent,1,'help');

        // Cree un tableau formulaire
		$formquestion=array();
		if ($adh->email) $formquestion[0]=array('type' => 'checkbox', 'name' => 'send_mail', 'label' => $label, 'value' => ($conf->global->ADHERENT_DEFAULT_SENDINFOBYMAIL?'true':'false'));
		$ret=$html->form_confirm("fiche.php?rowid=$rowid",$langs->trans("ResiliateMember"),$langs->trans("ConfirmResiliateMember"),"confirm_resign",$formquestion);
        if ($ret == 'html') print '<br>';
    }

	// Confirm remove member
    if ($action == 'delete')
    {
        $ret=$html->form_confirm("fiche.php?rowid=$rowid",$langs->trans("DeleteMember"),$langs->trans("ConfirmDeleteMember"),"confirm_delete",'',0,1);
        if ($ret == 'html') print '<br>';
    }

    /*
    * Confirm add in spip
    */
    if ($action == 'add_spip')
    {
        $ret=$html->form_confirm("fiche.php?rowid=$rowid","Ajouter dans spip","Etes-vous sur de vouloir ajouter cet adherent dans spip ? (serveur : ".ADHERENT_SPIP_SERVEUR.")","confirm_add_spip");
        if ($ret == 'html') print '<br>';
    }

    /*
    * Confirm removed from spip
    */
    if ($action == 'del_spip')
    {
        $ret=$html->form_confirm("fiche.php?rowid=$rowid","Supprimer dans spip","Etes-vous sur de vouloir effacer cet adherent dans spip ? (serveur : ".ADHERENT_SPIP_SERVEUR.")","confirm_del_spip");
        if ($ret == 'html') print '<br>';
    }

    $rowspan=19+sizeof($extrafields->attribute_label);
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED)) $rowspan+=1;
    if ($conf->societe->enabled) $rowspan++;

    print '<table class="border" width="100%">';

    // Ref
    print '<tr><td width="20%">'.$langs->trans("Ref").'</td>';
	print '<td class="valeur" colspan="2">';
	print $html->showrefnav($adh,'rowid');
	print '</td></tr>';

    $showphoto='<td rowspan="'.$rowspan.'" align="center" valign="middle" width="25%">';
    $showphoto.=$html->showphoto('memberphoto',$adh);
    $showphoto.='</td>';

    // Login
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
        print '<tr><td>'.$langs->trans("Login").' / '.$langs->trans("Id").'</td><td class="valeur">'.$adh->login.'&nbsp;</td>';
        print $showphoto; $showphoto='';
        print '</tr>';
    }

	// Morphy
    print '<tr><td>'.$langs->trans("Nature").'</td><td class="valeur" >'.$adh->getmorphylib().'</td>';
    print $showphoto; $showphoto='';
    print '</tr>';

    // Type
    print '<tr><td>'.$langs->trans("Type").'</td><td class="valeur">'.$adht->getNomUrl(1)."</td></tr>\n";

    // Company
    print '<tr><td>'.$langs->trans("Company").'</td><td class="valeur">'.$adh->societe.'</td></tr>';

	// Civility
    print '<tr><td>'.$langs->trans("UserTitle").'</td><td class="valeur">'.$adh->getCivilityLabel().'&nbsp;</td>';
	print '</tr>';

    // Name
    print '<tr><td>'.$langs->trans("Lastname").'</td><td class="valeur">'.$adh->nom.'&nbsp;</td>';
	print '</tr>';

    // Firstname
    print '<tr><td>'.$langs->trans("Firstname").'</td><td class="valeur">'.$adh->prenom.'&nbsp;</td></tr>';

	// Password
    if (empty($conf->global->ADHERENT_LOGIN_NOT_REQUIRED))
    {
        print '<tr><td>'.$langs->trans("Password").'</td><td>'.preg_replace('/./i','*',$adh->pass).'</td></tr>';
    }

    // Address
    print '<tr><td>'.$langs->trans("Address").'</td><td class="valeur">';
    dol_print_address($adh->address,'gmap','member',$adh->id);
    print '</td></tr>';

    // Zip / Town
    print '<tr><td nowrap="nowrap">'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td class="valeur">'.$adh->zip.(($adh->zip && $adh->town)?' / ':'').$adh->town.'</td></tr>';

	// Country
    print '<tr><td>'.$langs->trans("Country").'</td><td class="valeur">';
	$img=picto_from_langcode($adh->pays_code);
	if ($img) print $img.' ';
    print getCountry($adh->pays_code);
    print '</td></tr>';

	// State
	print '<tr><td>'.$langs->trans('State').'</td><td class="valeur">'.$adh->departement.'</td>';

    // Tel pro.
    print '<tr><td>'.$langs->trans("PhonePro").'</td><td class="valeur">'.dol_print_phone($adh->phone,$adh->pays_code,0,$adh->fk_soc,1).'</td></tr>';

    // Tel perso
    print '<tr><td>'.$langs->trans("PhonePerso").'</td><td class="valeur">'.dol_print_phone($adh->phone_perso,$adh->pays_code,0,$adh->fk_soc,1).'</td></tr>';

    // Tel mobile
    print '<tr><td>'.$langs->trans("PhoneMobile").'</td><td class="valeur">'.dol_print_phone($adh->phone_mobile,$adh->pays_code,0,$adh->fk_soc,1).'</td></tr>';

    // EMail
    print '<tr><td>'.$langs->trans("EMail").'</td><td class="valeur">'.dol_print_email($adh->email,0,$adh->fk_soc,1).'</td></tr>';

	// Date naissance
    print '<tr><td>'.$langs->trans("Birthday").'</td><td class="valeur">'.dol_print_date($adh->naiss,'day').'</td></tr>';

    // Public
    print '<tr><td>'.$langs->trans("Public").'</td><td class="valeur">'.yn($adh->public).'</td></tr>';

    // Status
    print '<tr><td>'.$langs->trans("Status").'</td><td class="valeur">'.$adh->getLibStatut(4).'</td></tr>';

    // Other attributes
    foreach($extrafields->attribute_label as $key=>$label)
    {
        $value=$adh->array_options["options_$key"];
        print "<tr><td>".$label."</td><td>";
        print $extrafields->showOutputField($key,$value);
        print "</td></tr>\n";
    }

	// Third party Dolibarr
    if ($conf->societe->enabled)
    {
	    print '<tr><td>';
		print '<table class="nobordernopadding" width="100%"><tr><td>';
	    print $langs->trans("LinkedToDolibarrThirdParty");
	    print '</td>';
		if ($_GET['action'] != 'editthirdparty' && $user->rights->adherent->creer) print '<td align="right"><a href="'.$_SERVER["PHP_SELF"].'?action=editthirdparty&amp;rowid='.$adh->id.'">'.img_edit($langs->trans('SetLinkToThirdParty'),1).'</a></td>';
		print '</tr></table>';
	    print '</td><td class="valeur">';
		if ($_GET['action'] == 'editthirdparty')
		{
			$htmlname='socid';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" name="form'.$htmlname.'">';
            print '<input type="hidden" name="rowid" value="'.$adh->id.'">';
			print '<input type="hidden" name="action" value="set'.$htmlname.'">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
			print '<tr><td>';
			print $html->select_societes($adh->fk_soc,'socid','',1);
			print '</td>';
			print '<td align="left"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
			print '</tr></table></form>';
		}
		else
		{
			if ($adh->fk_soc)
		    {
		    	$company=new Societe($db);
		    	$result=$company->fetch($adh->fk_soc);
		    	print $company->getNomUrl(1);
		    }
		    else
		    {
		    	print $langs->trans("NoThirdPartyAssociatedToMember");
		    }
		}
	    print '</td></tr>';
    }

	// Login Dolibarr
	print '<tr><td>';
	print '<table class="nobordernopadding" width="100%"><tr><td>';
	print $langs->trans("LinkedToDolibarrUser");
	print '</td>';
	if ($_GET['action'] != 'editlogin' && $user->rights->adherent->creer)
	{
	    print '<td align="right">';
	    if ($user->rights->user->user->creer)
	    {
	        print '<a href="'.$_SERVER["PHP_SELF"].'?action=editlogin&amp;rowid='.$adh->id.'">'.img_edit($langs->trans('SetLinkToUser'),1).'</a>';
	    }
	    print '</td>';
	}
	print '</tr></table>';
	print '</td><td class="valeur">';
	if ($_GET['action'] == 'editlogin')
	{
		print $html->form_users($_SERVER['PHP_SELF'].'?rowid='.$adh->id,$adh->user_id,'userid','');
	}
	else
	{
		if ($adh->user_id)
		{
			print $html->form_users($_SERVER['PHP_SELF'].'?rowid='.$adh->id,$adh->user_id,'none');
		}
		else print $langs->trans("NoDolibarrAccess");
	}
	print '</td></tr>';

    print "</table>\n";

    print "</div>\n";


    /*
     * Barre d'actions
     *
     */
    print '<div class="tabsAction">';

    if ($action != 'valid' && $action != 'editlogin' && $action != 'editthirdparty')
    {
	    // Modify
		if ($user->rights->adherent->creer)
		{
			print "<a class=\"butAction\" href=\"fiche.php?rowid=$rowid&action=edit\">".$langs->trans("Modify")."</a>";
	    }
		else
		{
			print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("Modify")."</font>";
		}

		// Valider
		if ($adh->statut == -1)
		{
			if ($user->rights->adherent->creer)
			{
				print "<a class=\"butAction\" href=\"fiche.php?rowid=$rowid&action=valid\">".$langs->trans("Validate")."</a>\n";
			}
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("Validate")."</font>";
			}
		}

		// Reactiver
		if ($adh->statut == 0)
		{
			if ($user->rights->adherent->creer)
			{
		        print "<a class=\"butAction\" href=\"fiche.php?rowid=$rowid&action=valid\">".$langs->trans("Reenable")."</a>\n";
		    }
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("Reenable")."</font>";
			}
		}

		// Envoi fiche par mail
		if ($adh->statut >= 1)
		{
			if ($user->rights->adherent->creer)
			{
				if ($adh->email) print "<a class=\"butAction\" href=\"fiche.php?rowid=$adh->id&action=sendinfo\">".$langs->trans("SendCardByMail")."</a>\n";
				else print "<a class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NoEMail"))."\">".$langs->trans("SendCardByMail")."</a>\n";
		    }
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("SendCardByMail")."</font>";
			}
		}

		// Resilier
		if ($adh->statut >= 1)
		{
			if ($user->rights->adherent->supprimer)
			{
		        print "<a class=\"butAction\" href=\"fiche.php?rowid=$rowid&action=resign\">".$langs->trans("Resiliate")."</a>\n";
		    }
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("Resiliate")."</font>";
			}
		}

		// Create third party
		if ($conf->societe->enabled && ! $adh->fk_soc)
		{
			if ($user->rights->societe->creer)
			{
				if ($adh->statut != -1) print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?rowid='.$adh->id.'&amp;action=create_thirdparty">'.$langs->trans("CreateDolibarrThirdParty").'</a>';
				else print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("ValidateBefore")).'">'.$langs->trans("CreateDolibarrThirdParty").'</a>';
			}
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("CreateDolibarrThirdParty")."</font>";
			}
		}

		// Create user
		if (! $user->societe_id && ! $adh->user_id)
		{
			if ($user->rights->user->user->creer)
			{
				if ($adh->statut != -1) print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?rowid='.$adh->id.'&amp;action=create_user">'.$langs->trans("CreateDolibarrLogin").'</a>';
				else print '<a class="butActionRefused" href="#" title="'.dol_escape_htmltag($langs->trans("ValidateBefore")).'">'.$langs->trans("CreateDolibarrLogin").'</a>';
			}
			else
			{
				print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("CreateDolibarrLogin")."</font>";
			}
		}

		// Delete
	    if ($user->rights->adherent->supprimer)
	    {
	        print "<a class=\"butActionDelete\" href=\"fiche.php?rowid=$adh->id&action=delete\">".$langs->trans("Delete")."</a>\n";
	    }
		else
		{
			print "<font class=\"butActionRefused\" href=\"#\" title=\"".dol_escape_htmltag($langs->trans("NotEnoughPermissions"))."\">".$langs->trans("Delete")."</font>";
		}

	    // Action SPIP
	    if ($conf->global->ADHERENT_USE_SPIP)
	    {
	        $isinspip=$adh->is_in_spip();
	        if ($isinspip == 1)
	        {
	            print "<a class=\"butAction\" href=\"fiche.php?rowid=$adh->id&action=del_spip\">".$langs->trans("DeleteIntoSpip")."</a>\n";
	        }
	        if ($isinspip == 0)
	        {
	            print "<a class=\"butAction\" href=\"fiche.php?rowid=$adh->id&action=add_spip\">".$langs->trans("AddIntoSpip")."</a>\n";
	        }
	        if ($isinspip == -1) {
	            print '<br><font class="error">Failed to connect to SPIP: '.$adh->error.'</font>';
	        }
	    }

    }

    print '</div>';
    print "<br>\n";

}


$db->close();

llxFooter('$Date: 2011/07/31 22:23:27 $ - $Revision: 1.238 $');
?>
