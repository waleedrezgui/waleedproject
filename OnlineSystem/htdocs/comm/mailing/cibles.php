<?PHP
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@uers.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
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
 *       \file       htdocs/comm/mailing/cibles.php
 *       \ingroup    mailing
 *       \brief      Page to define emailing targets
 *       \version    $Id: cibles.php,v 1.76 2011/08/03 00:46:33 eldy Exp $
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/comm/mailing/class/mailing.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/emailing.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/CMailFile.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/functions2.lib.php");

$langs->load("mails");

// Security check
if (! $user->rights->mailing->lire || $user->societe_id > 0)
accessforbidden();


$dirmod=DOL_DOCUMENT_ROOT."/includes/modules/mailings";


$mesg = '';


$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="email";

$search_nom=isset($_GET["search_nom"])?$_GET["search_nom"]:$_POST["search_nom"];
$search_prenom=isset($_GET["search_prenom"])?$_GET["search_prenom"]:$_POST["search_prenom"];
$search_email=isset($_GET["search_email"])?$_GET["search_email"]:$_POST["search_email"];


/*
 * Actions
 */

if ($_GET["action"] == 'add')
{
	$modulename=$_GET["module"];
	$result=0;

	$var=true;
	foreach ($conf->file->dol_document_root as $dirmod)
	{
		$dir=$dirmod."/includes/modules/mailings/";

		if (is_dir($dir))
		{
			// Chargement de la classe
			$file = $dir."/".$modulename.".modules.php";
			$classname = "mailing_".$modulename;

			if (file_exists($file))
			{
				require_once($file);

				// We fill $filtersarray. Using this variable is now deprecated.
				// Kept for backward compatibility.
				$filtersarray=array();
				if (isset($_POST["filter"])) $filtersarray[0]=$_POST["filter"];

				// Add targets into database
				$obj = new $classname($db);
				$result=$obj->add_to_target($_GET["rowid"],$filtersarray);
			}
		}
	}

	if ($result > 0)
	{
		Header("Location: cibles.php?id=".$_GET["rowid"]);
		exit;
	}
	if ($result == 0)
	{
		$mesg='<div class="warning">'.$langs->trans("WarningNoEMailsAdded").'</div>';
	}
	if ($result < 0)
	{
		$mesg='<div class="error">'.$obj->error.'</div>';
	}
	$_REQUEST["id"]=$_GET["rowid"];
}

if ($_GET["action"] == 'clear')
{
	// Chargement de la classe
	$file = $dirmod."/modules_mailings.php";
	$classname = "MailingTargets";
	require_once($file);

	$obj = new $classname($db);
	$obj->clear_target($_GET["rowid"]);

	Header("Location: cibles.php?id=".$_GET["rowid"]);
	exit;
}

if ($_GET["action"] == 'delete')
{
	// Ici, rowid indique le destinataire et id le mailing
	$sql="DELETE FROM ".MAIN_DB_PREFIX."mailing_cibles where rowid=".$_GET["rowid"];
	$resql=$db->query($sql);
	if ($resql)
	{
		$file = $dirmod."/modules_mailings.php";
		$classname = "MailingTargets";
		require_once($file);

		$obj = new $classname($db);
		$obj->update_nb($_REQUEST["id"]);
	}
	else
	{
		dol_print_error($db);
	}
}

if ($_POST["button_removefilter"])
{
	$search_nom='';
	$search_prenom='';
	$search_email='';
}



/*
 * View
 */

llxHeader('',$langs->trans("Mailing"),'EN:Module_EMailing|FR:Module_Mailing|ES:M&oacute;dulo_Mailing');

$html = new Form($db);

$mil = new Mailing($db);

if ($mil->fetch($_REQUEST["id"]) >= 0)
{
	$head = emailing_prepare_head($mil);

	dol_fiche_head($head, 'targets', $langs->trans("Mailing"), 0, 'email');


	print '<table class="border" width="100%">';

	print '<tr><td width="25%">'.$langs->trans("Ref").'</td>';
	print '<td colspan="3">';
	print $html->showrefnav($mil,'id');
	print '</td></tr>';

	print '<tr><td width="25%">'.$langs->trans("MailTitle").'</td><td colspan="3">'.$mil->titre.'</td></tr>';

	print '<tr><td width="25%">'.$langs->trans("MailFrom").'</td><td colspan="3">'.dol_print_email($mil->email_from,0,0,0,0,1).'</td></tr>';

	// Errors to
	print '<tr><td width="25%">'.$langs->trans("MailErrorsTo").'</td><td colspan="3">'.dol_print_email($mil->email_errorsto,0,0,0,0,1);
	print '</td></tr>';

	// Status
	print '<tr><td width="25%">'.$langs->trans("Status").'</td><td colspan="3">'.$mil->getLibStatut(4).'</td></tr>';

	// Nb of distinct emails
	print '<tr><td width="25%">';
	print $langs->trans("TotalNbOfDistinctRecipients");
	print '</td><td colspan="3">';
	$nbemail = ($mil->nbemail?$mil->nbemail:'0');
	if (!empty($conf->global->MAILING_LIMIT_SENDBYWEB) && $conf->global->MAILING_LIMIT_SENDBYWEB < $nbemail)
	{
		$text=$langs->trans('LimitSendingEmailing',$conf->global->MAILING_LIMIT_SENDBYWEB);
		print $html->textwithpicto($nbemail,$text,1,'warning');
	}
	else
	{
		print $nbemail;
	}
	print '</td></tr>';

	print '</table>';

	print "</div>";

	if ($mesg) print "$mesg<br>\n";

	$var=!$var;

	// Show email selectors
	if ($mil->statut == 0)
	{
		print_fiche_titre($langs->trans("ToAddRecipientsChooseHere"),($user->admin?info_admin($langs->trans("YouCanAddYourOwnPredefindedListHere"),1):''),'');

		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td class="liste_titre">'.$langs->trans("RecipientSelectionModules").'</td>';
		print '<td class="liste_titre" align="center">'.$langs->trans("NbOfUniqueEMails").'</td>';
		print '<td class="liste_titre" align="left">'.$langs->trans("Filter").'</td>';
		print '<td class="liste_titre" align="center">&nbsp;</td>';
		print "</tr>\n";

		clearstatcache();

		$var=true;
		foreach ($conf->file->dol_document_root as $dirroot)
		{
			$dir=$dirroot."/includes/modules/mailings/";

			if (is_dir($dir))
			{
				$handle=opendir($dir);
				if (is_resource($handle))
				{
					while (($file = readdir($handle))!==false)
					{
						if (substr($file, 0, 1) <> '.' && substr($file, 0, 3) <> 'CVS')
						{
							if (preg_match("/(.*)\.modules\.php$/i",$file,$reg))
							{
								$modulename=$reg[1];
								if ($modulename == 'example') continue;

								// Chargement de la classe
								$file = $dir.$modulename.".modules.php";
								$classname = "mailing_".$modulename;
								require_once($file);

								$obj = new $classname($db);

								$qualified=1;
								foreach ($obj->require_module as $key)
								{
									if (! $conf->$key->enabled || (! $user->admin && $obj->require_admin))
									{
										$qualified=0;
										//print "Les prerequis d'activation du module mailing ne sont pas respectes. Il ne sera pas actif";
										break;
									}
								}

								// Si le module mailing est qualifie
								if ($qualified)
								{
									$var = !$var;
									print '<tr '.$bc[$var].'>';

									if ($mil->statut == 0)
									{
										print '<form name="'.$modulename.'" action="cibles.php?action=add&rowid='.$mil->id.'&module='.$modulename.'" method="POST" enctype="multipart/form-data">';
										print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
									}

									print '<td>';
									if (! $obj->picto) $obj->picto='generic';
									print img_object($langs->trans("Module").': '.get_class($obj),$obj->picto).' '.$obj->getDesc();
									print '</td>';

									/*
									 print '<td width=\"100\">';
									 print $modulename;
									 print "</td>";
									 */
									$nbofrecipient=$obj->getNbOfRecipients();
									print '<td align="center">';
									if ($nbofrecipient >= 0)
									{
										print $nbofrecipient;
									}
									else
									{
										print $langs->trans("Error").' '.img_error($obj->error);
									}
									print '</td>';

									print '<td align="left">';
									$filter=$obj->formFilter();
									if ($filter) print $filter;
									else print $langs->trans("None");
									print '</td>';

									print '<td align="right">';
									if ($mil->statut == 0)
									{
										print '<input type="submit" class="button" value="'.$langs->trans("Add").'">';
									}
									else
									{
										//print $langs->trans("MailNoChangePossible");
										print "&nbsp;";
									}
									print '</td>';

									if ($mil->statut == 0) print '</form>';

									print "</tr>\n";
								}
							}
						}
					}
					closedir($handle);
				}
			}
		}	// End foreach dir

		print '</table>';
		print '<br>';

		print '<form action="cibles.php?action=clear&rowid='.$mil->id.'" method="POST">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print_titre($langs->trans("ToClearAllRecipientsClickHere"));
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td class="liste_titre" align="right"><input type="submit" class="button" value="'.$langs->trans("TargetsReset").'"></td>';
		print '</tr>';
		print '</table>';
		print '</form>';
		print '<br>';
	}



	// List of selected targets
	print "\n<!-- Liste destinataires selectionnes -->\n";
	print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="id" value="'.$mil->id.'">';

	$sql  = "SELECT mc.rowid, mc.nom, mc.prenom, mc.email, mc.other, mc.statut, mc.date_envoi, mc.source_url, mc.source_id, mc.source_type";
	$sql .= " FROM ".MAIN_DB_PREFIX."mailing_cibles as mc";
	$sql .= " WHERE mc.fk_mailing=".$mil->id;
	if ($search_nom)    $sql.= " AND mc.nom    like '%".$db->escape($search_nom)."%'";
	if ($search_prenom) $sql.= " AND mc.prenom like '%".$db->escape($search_prenom)."%'";
	if ($search_email)  $sql.= " AND mc.email  like '%".$db->escape($search_email)."%'";
	$sql .= $db->order($sortfield,$sortorder);
	$sql .= $db->plimit($conf->liste_limit+1, $offset);

	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);

		$parm = "&amp;id=".$mil->id;
		if ($search_nom)    $parm.= "&amp;search_nom=".urlencode($search_nom);
		if ($search_prenom) $parm.= "&amp;search_prenom=".urlencode($search_prenom);
		if ($search_email)  $parm.= "&amp;search_email=".urlencode($search_email);

		print_barre_liste($langs->trans("MailSelectedRecipients"),$page,$_SERVER["PHP_SELF"],$parm,$sortfield,$sortorder,"",$num,$mil->nbemail,'');

		if ($page)			$parm.= "&amp;page=".$page;
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print_liste_field_titre($langs->trans("EMail"),$_SERVER["PHP_SELF"],"mc.email",$parm,"","",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Lastname"),$_SERVER["PHP_SELF"],"mc.nom",$parm,"","",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Firstname"),$_SERVER["PHP_SELF"],"mc.prenom",$parm,"","",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("OtherInformations"),$_SERVER["PHP_SELF"],"",$parm,"","",$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Source"),$_SERVER["PHP_SELF"],"",$parm,"",'align="center"',$sortfield,$sortorder);

		// Date sendinf
		if ($mil->statut < 2)
		{
			print '<td class="liste_titre">&nbsp;</td>';
		}
		else
		{
			print_liste_field_titre($langs->trans("DateSending"),$_SERVER["PHP_SELF"],"mc.date_envoi",$parm,'','align="center"',$sortfield,$sortorder);
		}

		// Statut
		print_liste_field_titre($langs->trans("Status"),$_SERVER["PHP_SELF"],"mc.statut",$parm,'','align="right"',$sortfield,$sortorder);

		print '</tr>';

		// Ligne des champs de filtres
		print '<tr class="liste_titre">';
		// EMail
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" name="search_email" size="14" value="'.$search_email.'">';
		print '</td>';
		// Name
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" name="search_nom" size="12" value="'.$search_nom.'">';
		print '</td>';
		// Firstname
		print '<td class="liste_titre">';
		print '<input class="flat" type="text" name="search_prenom" size="10" value="'.$search_prenom.'">';
		print '</td>';
		// Other
		print '<td class="liste_titre">';
		print '&nbsp';
		print '</td>';
		// Source
		print '<td class="liste_titre" align="right" colspan="3">';
		print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" name="button_search" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
		print '&nbsp; ';
		print '<input type="image" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
		print '</td>';
		print '</tr>';

		$var = true;
		$i = 0;

		if ($num)
		{
			while ($i < min($num,$conf->liste_limit))
			{
				$obj = $db->fetch_object($resql);
				$var=!$var;

				print "<tr $bc[$var]>";
				print '<td>'.$obj->email.'</td>';
				print '<td>'.$obj->nom.'</td>';
				print '<td>'.$obj->prenom.'</td>';
				print '<td>'.$obj->other.'</td>';
				print '<td align="center">';
                if (empty($obj->source_id) || empty($obj->source_type))
                {
                    print $obj->source_url; // For backward compatibility
                }
                else
                {
                    if ($obj->source_type == 'member')
                    {
                        include_once(DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php');
                        $m=new Adherent($db);
                        $m->id=$obj->source_id;
                        print $m->getNomUrl(2);
                    }
                    else if ($obj->source_type == 'user')
                    {
                        include_once(DOL_DOCUMENT_ROOT.'/user/class/user.class.php');
                        $m=new User($db);
                        $m->id=$obj->source_id;
                        print $m->getNomUrl(2);
                    }
                    else if ($obj->source_type == 'thirdparty')
                    {
                        include_once(DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php');
                        $m=new Societe($db);
                        $m->id=$obj->source_id;
                        print $m->getNomUrl(2);
                    }
                    else
                    {
                        print $obj->source_url;
                    }
                }
				print '</td>';

				// Statut pour l'email destinataire (Attentioon != statut du mailing)
				if ($obj->statut == 0)
				{
					print '<td align="center">&nbsp;</td>';
					print '<td align="right" nowrap="nowrap">'.$langs->trans("MailingStatusNotSent").' <a href="cibles.php?action=delete&rowid='.$obj->rowid.$parm.'">'.img_delete($langs->trans("RemoveRecipient")).'</td>';
				}
				else
				{
					print '<td align="center">'.$obj->date_envoi.'</td>';
					print '<td align="right" nowrap="nowrap">';
					if ($obj->statut==-1) print $langs->trans("MailingStatusError").' '.img_error();
					if ($obj->statut==1) print $langs->trans("MailingStatusSent").' '.img_picto($langs->trans("MailingStatusSent"),'statut6');
					print '</td>';
				}
				print '</tr>';

				$i++;
			}
		}
		else
		{
			print '<tr '.$bc[false].'><td colspan="7">'.$langs->trans("NoTargetYet").'</td></tr>';
		}
		print "</table><br>";

		$db->free($resql);
	}
	else
	{
		dol_print_error($db);
	}

	print '</form>';
	print "\n<!-- Fin liste destinataires selectionnes -->\n";

}


$db->close();

llxFooter('$Date: 2011/08/03 00:46:33 $ - $Revision: 1.76 $');
?>
