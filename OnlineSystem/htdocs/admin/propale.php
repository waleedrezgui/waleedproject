<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville        <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur         <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio         <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier              <benoit.mortier@opensides.be>
 * Copyright (C) 2004      Eric Seigne                 <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2011 Regis Houssin               <regis@dolibarr.fr>
 * Copyright (C) 2008      Raphael Bertrand (Resultic) <raphael.bertrand@resultic.fr>
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
 *	    \file       htdocs/admin/propale.php
 *		\ingroup    propale
 *		\brief      Page d'administration/configuration du module Propale
 *		\version    $Id: propale.php,v 1.108 2011/07/31 22:23:25 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/comm/propal/class/propal.class.php");

$langs->load("admin");
$langs->load("bills");
$langs->load("propal");
$langs->load("other");

if (!$user->admin)
accessforbidden();


/*
 * Actions
 */

if ($_POST["action"] == 'updateMask')
{
	$maskconstpropal=$_POST['maskconstpropal'];
	$maskpropal=$_POST['maskpropal'];
	if ($maskconstpropal)  dolibarr_set_const($db,$maskconstpropal,$maskpropal,'chaine',0,'',$conf->entity);
}

if ($_GET["action"] == 'specimen')
{
	$modele=$_GET["module"];

	$propal = new Propal($db);
	$propal->initAsSpecimen();

	// Charge le modele
	$dir = "/includes/modules/propale/";
	$file = "pdf_propale_".$modele.".modules.php";
	$file = dol_buildpath($dir.$file);
	if (file_exists($file))
	{
		$classname = "pdf_propale_".$modele;
		require_once($file);

		$module = new $classname($db);

		if ($module->write_file($propal,$langs) > 0)
		{
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=propal&file=SPECIMEN.pdf");
			return;
		}
		else
		{
			$mesg='<div class="error">'.$module->error.'</div>';
			dol_syslog($module->error, LOG_ERR);
		}
	}
	else
	{
		$mesg='<div class="error">'.$langs->trans("ErrorModuleNotFound").'</div>';
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
}

if ($_POST["action"] == 'set_PROPALE_DRAFT_WATERMARK')
{
	dolibarr_set_const($db, "PROPALE_DRAFT_WATERMARK",trim($_POST["PROPALE_DRAFT_WATERMARK"]),'chaine',0,'',$conf->entity);
}

if ($_POST["action"] == 'set_PROPALE_FREE_TEXT')
{
	dolibarr_set_const($db, "PROPALE_FREE_TEXT",$_POST["PROPALE_FREE_TEXT"],'chaine',0,'',$conf->entity);
}

if ($_POST["action"] == 'setdefaultduration')
{
	dolibarr_set_const($db, "PROPALE_VALIDITY_DURATION",$_POST["value"],'chaine',0,'',$conf->entity);
}

if ($_POST["action"] == 'setclassifiedinvoiced')
{
	dolibarr_set_const($db, "PROPALE_CLASSIFIED_INVOICED_WITH_ORDER",$_POST["value"],'chaine',0,'',$conf->entity);
}

if ($_POST["action"] == 'setusecustomercontactasrecipient')
{
	dolibarr_set_const($db, "PROPALE_USE_CUSTOMER_CONTACT_AS_RECIPIENT",$_POST["value"],'chaine',0,'',$conf->entity);
}




if ($_GET["action"] == 'set')
{
	$type='propal';
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity, libelle, description)";
    $sql.= " VALUES ('".$db->escape($_GET["value"])."','".$type."',".$conf->entity.", ";
    $sql.= ($_GET["label"]?"'".$db->escape($_GET["label"])."'":'null').", ";
    $sql.= (! empty($_GET["scandir"])?"'".$db->escape($_GET["scandir"])."'":"null");
    $sql.= ")";
	if ($db->query($sql))
	{

	}
}
if ($_GET["action"] == 'del')
{
	$type='propal';
	$sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model";
	$sql.= " WHERE nom = '".$_GET["value"]."'";
	$sql.= " AND type = '".$type."'";
	$sql.= " AND entity = ".$conf->entity;
	if ($db->query($sql))
	{

	}
}

if ($_GET["action"] == 'setdoc')
{
	$db->begin();

	if (dolibarr_set_const($db, "PROPALE_ADDON_PDF",$_GET["value"],'chaine',0,'',$conf->entity))
	{
		$conf->global->PROPALE_ADDON_PDF = $_GET["value"];
	}

	// On active le modele
	$type='propal';
	$sql_del = "DELETE FROM ".MAIN_DB_PREFIX."document_model";
	$sql_del.= " WHERE nom = '".$db->escape($_GET["value"])."'";
	$sql_del.= " AND type = '".$type."'";
	$sql_del.= " AND entity = ".$conf->entity;
	$result1=$db->query($sql_del);

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity, libelle, description)";
    $sql.= " VALUES ('".$db->escape($_GET["value"])."', '".$type."', ".$conf->entity.", ";
    $sql.= ($_GET["label"]?"'".$db->escape($_GET["label"])."'":'null').", ";
    $sql.= (! empty($_GET["scandir"])?"'".$db->escape($_GET["scandir"])."'":"null");
    $sql.= ")";
	$result2=$db->query($sql);
	if ($result1 && $result2)
	{
		$db->commit();
	}
	else
	{
		$db->rollback();
	}
}

if ($_GET["action"] == 'setmod')
{
	// TODO Verifier si module numerotation choisi peut etre active
	// par appel methode canBeActivated

	dolibarr_set_const($db, "PROPALE_ADDON",$_GET["value"],'chaine',0,'',$conf->entity);
}


/*
 * Affiche page
 */

llxHeader('',$langs->trans("PropalSetup"));

$html=new Form($db);

if ($mesg) print $mesg;

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("PropalSetup"),$linkback,'setup');

/*
 *  Module numerotation
 */
print "<br>";
print_titre($langs->trans("ProposalsNumberingModules"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Name")."</td>\n";
print '<td>'.$langs->trans("Description")."</td>\n";
print '<td nowrap>'.$langs->trans("Example")."</td>\n";
print '<td align="center" width="60">'.$langs->trans("Status").'</td>';
print '<td align="center" width="16">'.$langs->trans("Infos").'</td>';
print '</tr>'."\n";

clearstatcache();

foreach ($conf->file->dol_document_root as $dirroot)
{
	$dir = $dirroot . "/includes/modules/propale/";

	if (is_dir($dir))
	{
		$handle = opendir($dir);
		if (is_resource($handle))
		{
			$var=true;

			while (($file = readdir($handle))!==false)
			{
				if (substr($file, 0, 12) == 'mod_propale_' && substr($file, dol_strlen($file)-3, 3) == 'php')
				{
					$file = substr($file, 0, dol_strlen($file)-4);

					require_once($dir.$file.".php");

					$module = new $file;

					// Show modules according to features level
					if ($module->version == 'development'  && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
					if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

					if ($module->isEnabled())
					{
						$var=!$var;
						print '<tr '.$bc[$var].'><td>'.$module->nom."</td><td>\n";
						print $module->info();
						print '</td>';

                        // Show example of numbering module
                        print '<td nowrap="nowrap">';
                        $tmp=$module->getExample();
                        if (preg_match('/^Error/',$tmp)) print $langs->trans($tmp);
                        else print $tmp;
                        print '</td>'."\n";

						print '<td align="center">';
						if ($conf->global->PROPALE_ADDON == "$file")
						{
							print img_picto($langs->trans("Activated"),'on');
						}
						else
						{
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&amp;value='.$file.'">';
							print img_picto($langs->trans("Disabled"),'off');
							print '</a>';
						}
						print '</td>';

						$propale=new Propal($db);
						$propale->initAsSpecimen();

						// Info
						$htmltooltip='';
						$htmltooltip.=''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
						$facture->type=0;
						$nextval=$module->getNextValue($mysoc,$propale);
						if ("$nextval" != $langs->trans("NotAvailable"))	// Keep " on nextval
						{
							$htmltooltip.=''.$langs->trans("NextValue").': ';
							if ($nextval)
							{
								$htmltooltip.=$nextval.'<br>';
							}
							else
							{
								$htmltooltip.=$langs->trans($module->error).'<br>';
							}
						}

						print '<td align="center">';
						print $html->textwithpicto('',$htmltooltip,1,0);
						print '</td>';

						print "</tr>\n";
					}
				}
			}
			closedir($handle);
		}
	}
}
print "</table><br>\n";


/*
 * Modeles de documents
 */

print_titre($langs->trans("ProposalsPDFModules"));

// Defini tableau def de modele propal
$def = array();
$sql = "SELECT nom";
$sql.= " FROM ".MAIN_DB_PREFIX."document_model";
$sql.= " WHERE type = 'propal'";
$sql.= " AND entity = ".$conf->entity;
$resql=$db->query($sql);
if ($resql)
{
	$i = 0;
	$num_rows=$db->num_rows($resql);
	while ($i < $num_rows)
	{
		$array = $db->fetch_array($resql);
		array_push($def, $array[0]);
		$i++;
	}
}
else
{
	dol_print_error($db);
}


print "<table class=\"noborder\" width=\"100%\">\n";
print "<tr class=\"liste_titre\">\n";
print "  <td width=\"140\">".$langs->trans("Name")."</td>\n";
print "  <td>".$langs->trans("Description")."</td>\n";
print '<td align="center" width="40">'.$langs->trans("Status")."</td>\n";
print '<td align="center" width="40">'.$langs->trans("Default")."</td>\n";
print '<td align="center" width="40">'.$langs->trans("Infos").'</td>';
print '<td align="center" width="40">'.$langs->trans("Preview").'</td>';
print "</tr>\n";

clearstatcache();

foreach ($conf->file->dol_document_root as $dirroot)
{
	$dir = $dirroot . "/includes/modules/propale/";

	if (is_dir($dir))
	{
		$var=true;

		$handle=opendir($dir);
		if (is_resource($handle))
		{
			while (($file = readdir($handle))!==false)
			{
				if (substr($file, dol_strlen($file) -12) == '.modules.php' && substr($file,0,12) == 'pdf_propale_')
				{
					$name = substr($file, 12, dol_strlen($file) - 24);
					$classname = substr($file, 0, dol_strlen($file) -12);

					$var=!$var;
					print "<tr ".$bc[$var].">\n  <td>";
					print $name;
					print "</td>\n  <td>\n";
					require_once($dir.$file);
					$module = new $classname($db);
					print $module->description;
					print '</td>';

					// Activate
					print '<td align="center">'."\n";
					if (in_array($name, $def))
					{
						if ($conf->global->PROPALE_ADDON_PDF != "$name")
						{
							print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'">';
							print img_picto($langs->trans("Activated"),'on');
							print '</a>';
						}
						else
						{
							print img_picto($langs->trans("Activated"),'on');
						}
					}
					else
					{
						print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'">';
						print img_picto($langs->trans("Disabled"),'off');
						print '</a>';
					}
					print "</td>";

					// Default
					print '<td align="center">';
					if ($conf->global->PROPALE_ADDON_PDF == "$name")
					{
						print img_picto($langs->trans("Yes"),'on');
					}
					else
					{
						print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'">';
						print img_picto($langs->trans("No"),'off');
						print '</a>';
					}
					print '</td>';

					// Info
					$htmltooltip =    ''.$langs->trans("Name").': '.$module->name;
					$htmltooltip.='<br>'.$langs->trans("Type").': '.($module->type?$module->type:$langs->trans("Unknown"));
					$htmltooltip.='<br>'.$langs->trans("Height").'/'.$langs->trans("Width").': '.$module->page_hauteur.'/'.$module->page_largeur;
					$htmltooltip.='<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
					$htmltooltip.='<br>'.$langs->trans("Logo").': '.yn($module->option_logo,1,1);
					$htmltooltip.='<br>'.$langs->trans("PaymentMode").': '.yn($module->option_modereg,1,1);
					$htmltooltip.='<br>'.$langs->trans("PaymentConditions").': '.yn($module->option_condreg,1,1);
					$htmltooltip.='<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang,1,1);
					//$htmltooltip.='<br>'.$langs->trans("Escompte").': '.yn($module->option_escompte,1,1);
					//$htmltooltip.='<br>'.$langs->trans("CreditNote").': '.yn($module->option_credit_note,1,1);
					$htmltooltip.='<br>'.$langs->trans("WatermarkOnDraftProposal").': '.yn($module->option_draft_watermark,1,1);

					print '<td align="center">';
					print $html->textwithpicto('',$htmltooltip,1,0);
					print '</td>';
					print '<td align="center">';
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"),'propal').'</a>';
					print '</td>';

					print "</tr>\n";
				}
			}
			closedir($handle);
		}
	}
}


print '</table>';
print '<br>';


/*
 * Other options
 *
 */
print_titre($langs->trans("OtherOptions"));

$var=true;
print "<table class=\"noborder\" width=\"100%\">";
print "<tr class=\"liste_titre\">";
print "<td>".$langs->trans("Parameter")."</td>\n";
print '<td width="60" align="center">'.$langs->trans("Value")."</td>\n";
print "<td>&nbsp;</td>\n";
print "</tr>";

$var=!$var;
print "<form method=\"post\" action=\"".$_SERVER["PHP_SELF"]."\">";
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print "<input type=\"hidden\" name=\"action\" value=\"setdefaultduration\">";
print "<tr ".$bc[$var].">";
print '<td>'.$langs->trans("DefaultProposalDurationValidity").'</td>';
print '<td width="60" align="center">'."<input size=\"3\" class=\"flat\" type=\"text\" name=\"value\" value=\"".$conf->global->PROPALE_VALIDITY_DURATION."\"></td>";
print '<td align="right"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
print '</tr>';
print '</form>';

/*
$var=! $var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="setusecustomercontactasrecipient">';
print '<tr '.$bc[$var].'><td>';
print $langs->trans("UseCustomerContactAsPropalRecipientIfExist");
print '</td><td width="60" align="center">';
print $html->selectyesno("value",$conf->global->PROPALE_USE_CUSTOMER_CONTACT_AS_RECIPIENT,1);
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print "</td></tr>\n";
print '</form>';
*/

if ($conf->commande->enabled)
{
	$var=!$var;
	print "<form method=\"post\" action=\"".$_SERVER["PHP_SELF"]."\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"setclassifiedinvoiced\">";
	print "<tr ".$bc[$var].">";
	print '<td>'.$langs->trans("ClassifiedInvoicedWithOrder").'</td>';
	print '<td width="60" align="center">';
	print $html->selectyesno('value',$conf->global->PROPALE_CLASSIFIED_INVOICED_WITH_ORDER,1);
	print "</td>";
	print '<td align="right"><input type="submit" class="button" value="'.$langs->trans("Modify").'"></td>';
	print '</tr>';
	print '</form>';
}

$var=! $var;
print '<form action="'.$_SERVER["PHP_SELF"].'" method="post">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="set_PROPALE_FREE_TEXT">';
print '<tr '.$bc[$var].'><td colspan="2">';
print $langs->trans("FreeLegalTextOnProposal").' ('.$langs->trans("AddCRIfTooLong").')<br>';
print '<textarea name="PROPALE_FREE_TEXT" class="flat" cols="120">'.$conf->global->PROPALE_FREE_TEXT.'</textarea>';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print "</td></tr>\n";
print '</form>';

$var=!$var;
print "<form method=\"post\" action=\"".$_SERVER["PHP_SELF"]."\">";
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print "<input type=\"hidden\" name=\"action\" value=\"set_PROPALE_DRAFT_WATERMARK\">";
print '<tr '.$bc[$var].'><td colspan="2">';
print $langs->trans("WatermarkOnDraftProposal").'<br>';
print '<input size="50" class="flat" type="text" name="PROPALE_DRAFT_WATERMARK" value="'.$conf->global->PROPALE_DRAFT_WATERMARK.'">';
print '</td><td align="right">';
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print "</td></tr>\n";
print '</form>';

print '</table>';



/*
 *  Directory
 */
print '<br>';
print_titre($langs->trans("PathToDocuments"));

print "<table class=\"noborder\" width=\"100%\">\n";
print "<tr class=\"liste_titre\">\n";
print "  <td>".$langs->trans("Name")."</td>\n";
print "  <td>".$langs->trans("Value")."</td>\n";
print "</tr>\n";
print "<tr ".$bc[false].">\n  <td width=\"140\">".$langs->trans("PathDirectory")."</td>\n  <td>".$conf->propale->dir_output."</td>\n</tr>\n";
print "</table>\n<br>";



$db->close();

llxFooter('$Date: 2011/07/31 22:23:25 $ - $Revision: 1.108 $');
?>
