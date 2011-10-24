<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2009 Regis Houssin         <regis@dolibarr.fr>
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
 *       \file       htdocs/fourn/facture/document.php
 *       \ingroup    facture, fournisseur
 *       \brief      Page de gestion des documents attachees a une facture fournisseur
 *       \version    $Id: document.php,v 1.46 2011/07/31 23:57:01 eldy Exp $
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php');
require_once(DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php');
require_once(DOL_DOCUMENT_ROOT.'/lib/fourn.lib.php');
require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");

$langs->load('bills');
$langs->load('other');
$langs->load("companies");

$facid = isset($_GET["facid"])?$_GET["facid"]:'';
$action=empty($_GET['action']) ? (empty($_POST['action']) ? '' : $_POST['action']) : $_GET['action'];

// Security check
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'fournisseur', $facid, 'facture_fourn', 'facture');

// Get parameters
$sortfield = GETPOST("sortfield",'alpha');
$sortorder = GETPOST("sortorder",'alpha');
$page = GETPOST("page",'int');
if ($page == -1) { $page = 0; }
$offset = $conf->liste_limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="name";



/*
 * Actions
 */

// Envoi fichier
if ($_POST['sendit'] && ! empty($conf->global->MAIN_UPLOAD_DOC))
{
	require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

	$facture = new FactureFournisseur($db);
	if ($facture->fetch($facid))
	{
	    $ref=dol_sanitizeFileName($facture->ref);
		$upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($facture->id,2).$ref;

		if (create_exdir($upload_dir) >= 0)
		{
			$resupload=dol_move_uploaded_file($_FILES['userfile']['tmp_name'], $upload_dir . "/" . $_FILES['userfile']['name'],0,0,$_FILES['userfile']['error']);
			if (is_numeric($resupload) && $resupload > 0)
			{
				$mesg = '<div class="ok">'.$langs->trans("FileTransferComplete").'</div>';
			}
			else
			{
				$langs->load("errors");
				if ($resupload < 0)	// Unknown error
				{
					$mesg = '<div class="error">'.$langs->trans("ErrorFileNotUploaded").'</div>';
				}
				else if (preg_match('/ErrorFileIsInfectedWithAVirus/',$resupload))	// Files infected by a virus
				{
					$mesg = '<div class="error">'.$langs->trans("ErrorFileIsInfectedWithAVirus").'</div>';
				}
				else	// Known error
				{
					$mesg = '<div class="error">'.$langs->trans($resupload).'</div>';
				}
			}
		}
	}
}

// Delete
if ($action=='delete')
{
	$facid=$_GET['id'];

	$facture = new FactureFournisseur($db);
	if ($facture->fetch($facid))
	{
        $ref=dol_sanitizeFileName($facture->ref);
        $upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($facture->id,2).$ref;

		$file = $upload_dir . '/' . $_GET['urlfile'];	// Do not use urldecode here ($_GET and $_REQUEST are already decoded by PHP).
		dol_delete_file($file);
		$mesg = '<div class="ok">'.$langs->trans('FileWasRemoved').'</div>';
	}
}


/*
 * Affichage
 */

$html = new Form($db);

llxHeader();

if ($facid > 0)
{
	$facture = new FactureFournisseur($db);
	if ($facture->fetch($facid))
	{
		$facture->fetch_thirdparty();

        $ref=dol_sanitizeFileName($facture->ref);
        $upload_dir = $conf->fournisseur->facture->dir_output.'/'.get_exdir($facture->id,2).$ref;

		$head = facturefourn_prepare_head($facture);
		dol_fiche_head($head, 'documents', $langs->trans('SupplierInvoice'), 0, 'bill');


		// Construit liste des fichiers
		$filearray=dol_dir_list($upload_dir,"files",0,'','\.meta$',$sortfield,(strtolower($sortorder)=='desc'?SORT_DESC:SORT_ASC),1);
		$totalsize=0;
		foreach($filearray as $key => $file)
		{
			$totalsize+=$file['size'];
		}


		print '<table class="border"width="100%">';

		// Ref
		print '<tr><td width="30%" nowrap="nowrap">'.$langs->trans("Ref").'</td><td colspan="3">';
		print $html->showrefnav($facture,'facid','',1,'rowid','ref',$morehtmlref);
		print '</td>';
		print "</tr>\n";

		// Ref supplier
		print '<tr><td nowrap="nowrap">'.$langs->trans("RefSupplier").'</td><td colspan="3">'.$facture->ref_supplier.'</td>';
		print "</tr>\n";

		// Societe
		print '<tr><td>'.$langs->trans('Supplier').'</td><td colspan="3">'.$facture->thirdparty->getNomUrl(1).'</td></tr>';

		print '<tr><td>'.$langs->trans('NbOfAttachedFiles').'</td><td colspan="3">'.sizeof($filearray).'</td></tr>';

		print '<tr><td>'.$langs->trans('TotalSizeOfAttachedFiles').'</td><td colspan="3">'.$totalsize.' '.$langs->trans('bytes').'</td></tr>';

		print '</table>';
		print '</div>';

		if ($mesg) { print $mesg.'<br>'; }


		// Affiche formulaire upload
		$formfile=new FormFile($db);
		$formfile->form_attach_new_file(DOL_URL_ROOT.'/fourn/facture/document.php?facid='.$facture->id,'',0,0,$user->rights->fournisseur->facture->creer);


		// List of document
		$param='&facid='.$facture->id;
		$formfile->list_of_documents($filearray,$facture,'facture_fournisseur',$param,0,get_exdir($facture->id,2,0).$facture->id.'/');

	}
	else
	{
		print 'facid='.$facid.'<br>';
		dol_print_error($db);
	}
}
else
{
	print $langs->trans('UnkownError');
}

$db->close();

llxFooter('$Date: 2011/07/31 23:57:01 $ - $Revision: 1.46 $');
?>
