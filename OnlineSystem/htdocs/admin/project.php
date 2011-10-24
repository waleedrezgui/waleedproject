<?php
/* Copyright (C) 2010 Regis Houssin  <regis@dolibarr.fr>
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
 *  \file       htdocs/admin/project.php
 *  \ingroup    project
 *  \brief      Page d'administration-configuration du module Projet
 *  \version    $Id: project.php,v 1.14 2011/07/31 22:23:25 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT.'/projet/class/project.class.php');
require_once(DOL_DOCUMENT_ROOT.'/projet/class/task.class.php');

$langs->load("admin");
$langs->load("other");
$langs->load("projects");

if (!$user->admin)
accessforbidden();


/*
 * Actions
 */

if ($_POST["action"] == 'updateMask')
{
	$maskconstproject=$_POST['maskconstproject'];
	$maskproject=$_POST['maskproject'];
	if ($maskconstproject)  dolibarr_set_const($db,$maskconstproject,$maskproject,'chaine',0,'',$conf->entity);
}

if ($_GET["action"] == 'specimen')
{
	$modele=$_GET["module"];

	$project = new Project($db);
	$project->initAsSpecimen();

	// Charge le modele
	$dir = DOL_DOCUMENT_ROOT . "/includes/modules/project/pdf/";
	$file = "pdf_".$modele.".modules.php";
	if (file_exists($dir.$file))
	{
		$classname = "pdf_".$modele;
		require_once($dir.$file);

		$obj = new $classname($db);

		if ($obj->write_file($project,$langs) > 0)
		{
	 	 	header("Location: ".DOL_URL_ROOT."/document.php?modulepart=project&file=SPECIMEN.pdf");
	  		return;
		}
		else
		{
			$mesg='<div class="error">'.$obj->error.'</div>';
			dol_syslog($obj->error, LOG_ERR);
		}
	}
	else
	{
		$mesg='<div class="error">'.$langs->trans("ErrorModuleNotFound").'</div>';
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
}

if ($_GET["action"] == 'set')
{
	$type='project';
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
	$type='project';
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

	if (dolibarr_set_const($db, "PROJECT_ADDON_PDF",$_GET["value"],'chaine',0,'',$conf->entity))
	{
		$conf->global->PROJECT_ADDON_PDF = $_GET["value"];
	}

	// On active le modele
	$type='project';
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

	dolibarr_set_const($db, "PROJECT_ADDON",$_GET["value"],'chaine',0,'',$conf->entity);
}

/*
 * View
 */

$html=new Form($db);

llxHeader();

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("ProjectsSetup"),$linkback,'setup');

print "<br>";


// Project numbering module

$dir = DOL_DOCUMENT_ROOT."/includes/modules/project/";

print_titre($langs->trans("ProjectsNumberingModules"));

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td width="100">'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td>'.$langs->trans("Example").'</td>';
print '<td align="center" width="60">'.$langs->trans("Activated").'</td>';
print '<td align="center" width="16">'.$langs->trans("Info").'</td>';
print "</tr>\n";

clearstatcache();

$handle = opendir($dir);
if (is_resource($handle))
{
	$var=true;

	while (($file = readdir($handle))!==false)
	{
		if (substr($file, 0, 12) == 'mod_project_' && substr($file, dol_strlen($file)-3, 3) == 'php')
		{
			$file = substr($file, 0, dol_strlen($file)-4);

			require_once(DOL_DOCUMENT_ROOT ."/includes/modules/project/".$file.".php");

			$module = new $file;

			if ($module->isEnabled())
			{
				// Show modules according to features level
				if ($module->version == 'development'  && $conf->global->MAIN_FEATURES_LEVEL < 2) continue;
				if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) continue;

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
				if ($conf->global->PROJECT_ADDON == "$file")
				{
					print img_picto($langs->trans("Activated"),'on');
				}
				else
				{
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&amp;value='.$file.'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"),'off').'</a>';
				}
				print '</td>';

				$project=new Project($db);
				$project->initAsSpecimen();

				// Info
				$htmltooltip='';
				$htmltooltip.=''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';
				$nextval=$module->getNextValue($mysoc,$project);
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

				print '</tr>';
			}
		}
	}
	closedir($handle);
}

print '</table><br>';


/*
 * Modeles documents for projects
 */

$dir = DOL_DOCUMENT_ROOT.'/includes/modules/project/pdf/';

print_titre($langs->trans("ProjectsModelModule"));

// Defini tableau def de modele
$type='project';
$def = array();

$sql = "SELECT nom";
$sql.= " FROM ".MAIN_DB_PREFIX."document_model";
$sql.= " WHERE type = '".$type."'";
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
print '  <td width="100">'.$langs->trans("Name")."</td>\n";
print "  <td>".$langs->trans("Description")."</td>\n";
print '<td align="center" width="60">'.$langs->trans("Activated")."</td>\n";
print '<td align="center" width="60">'.$langs->trans("Default")."</td>\n";
print '<td align="center" width="32" colspan="2">'.$langs->trans("Info").'</td>';
print "</tr>\n";

clearstatcache();

$handle=opendir($dir);

$var=true;
if (is_resource($handle))
{
    while (($file = readdir($handle))!==false)
    {
    	if (preg_match('/\.modules\.php$/i',$file) && substr($file,0,4) == 'pdf_')
    	{
    		$name = substr($file, 4, dol_strlen($file) -16);
    		$classname = substr($file, 0, dol_strlen($file) -12);

    		$var=!$var;
    		print "<tr ".$bc[$var].">\n  <td>$name";
    		print "</td>\n  <td>\n";
    		require_once($dir.$file);
    		$module = new $classname($db);
    		print $module->description;
    		print "</td>\n";

    		// Active
    		if (in_array($name, $def))
    		{
    			print "<td align=\"center\">\n";
    			if ($conf->global->PROJECT_ADDON_PDF != "$name")
    			{
    				print '<a href="'.$_SERVER["PHP_SELF"].'?action=del&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'">';
    				print img_picto($langs->trans("Enabled"),'on');
    				print '</a>';
    			}
    			else
    			{
    				print img_picto($langs->trans("Enabled"),'on');
    			}
    			print "</td>";
    		}
    		else
    		{
    			print "<td align=\"center\">\n";
    			print '<a href="'.$_SERVER["PHP_SELF"].'?action=set&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"),'off').'</a>';
    			print "</td>";
    		}

    		// Defaut
    		print "<td align=\"center\">";
    		if ($conf->global->PROJECT_ADDON_PDF == "$name")
    		{
    			print img_picto($langs->trans("Default"),'on');
    		}
    		else
    		{
    			print '<a href="'.$_SERVER["PHP_SELF"].'?action=setdoc&amp;value='.$name.'&amp;scandir='.$module->scandir.'&amp;label='.urlencode($module->name).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"),'off').'</a>';
    		}
    		print '</td>';

    		// Info
    		$htmltooltip =    ''.$langs->trans("Name").': '.$module->name;
    		$htmltooltip.='<br>'.$langs->trans("Type").': '.($module->type?$module->type:$langs->trans("Unknown"));
    		$htmltooltip.='<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
    		$htmltooltip.='<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
    		$htmltooltip.='<br>'.$langs->trans("Logo").': '.yn($module->option_logo,1,1);
    		print '<td align="center">';
    		print $html->textwithpicto('',$htmltooltip,1,0);
    		print '</td>';
    		print '<td align="center">';
    		print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&amp;module='.$name.'">'.img_object($langs->trans("Preview"),'order').'</a>';
    		print '</td>';

    		print "</tr>\n";
    	}
    }
    closedir($handle);
}

print '</table><br/>';

llxFooter('$Date: 2011/07/31 22:23:25 $ - $Revision: 1.14 $');
?>
