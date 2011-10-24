<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Simon Tosser         <simon@kornog-computing.com>
 * Copyright (C) 2005-2006 Regis Houssin        <regis@dolibarr.fr>
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
 *	\file       	htdocs/product/stock/user.php
 *	\ingroup    	stock
 *	\brief      	Page to link dolibarr users with warehouses
 *	\version    	$Id: user.php,v 1.16 2011/07/31 23:24:01 eldy Exp $
 * 	\deprecated		This page is never called. Feature will never be developped.
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/stock.lib.php");


$langs->load("products");
$langs->load("stocks");
$langs->load("companies");

$sortfield = isset($_GET["sortfield"])?$_GET["sortfield"]:$_POST["sortfield"];
$sortorder = isset($_GET["sortorder"])?$_GET["sortorder"]:$_POST["sortorder"];
if (! $sortfield) $sortfield="p.ref";
if (! $sortorder) $sortorder="DESC";

$mesg = '';


/*
 * Actions
 */





/*
 * View
 */

$help_url='EN:Module_Stocks_En|FR:Module_Stock|ES:M&oacute;dulo_Stocks';
llxHeader("",$langs->trans("WarehouseCard"),$help_url);

$form=new Form($db);


if ($_GET["id"])
{
	if ($mesg) print $mesg;

	$entrepot = new Entrepot($db);
	$result = $entrepot->fetch($_GET["id"]);
	if ($result < 0)
	{
		dol_print_error($db);
	}

	/*
	 * Affichage fiche
	 */
	if ($_GET["action"] <> 'edit' && $_GET["action"] <> 're-edit')
	{

		$head = stock_prepare_head($entrepot);

		dol_fiche_head($head, 'user', $langs->trans("Warehouse"), 0, 'stock');


		print '<table class="border" width="100%">';

		// Ref
		print '<tr><td width="25%">'.$langs->trans("Ref").'</td><td colspan="3">'.$entrepot->libelle.'</td>';

		print '<tr><td>'.$langs->trans("LocationSummary").'</td><td colspan="3">'.$entrepot->lieu.'</td></tr>';

		// Description
		print '<tr><td valign="top">'.$langs->trans("Description").'</td><td colspan="3">'.nl2br($entrepot->description).'</td></tr>';

		print '<tr><td>'.$langs->trans('Address').'</td><td colspan="3">';
		print $entrepot->address;
		print '</td></tr>';

		print '<tr><td width="25%">'.$langs->trans('Zip').'</td><td width="25%">'.$entrepot->cp.'</td>';
		print '<td width="25%">'.$langs->trans('Town').'</td><td width="25%">'.$entrepot->ville.'</td></tr>';

		print '<tr><td>'.$langs->trans('Country').'</td><td colspan="3">';
		print $entrepot->pays;
		print '</td></tr>';

		// Statut
		print '<tr><td>'.$langs->trans("Status").'</td><td colspan="3">'.$entrepot->getLibStatut(4).'</td></tr>';

		print "</table>";

		print '</div>';


		/* ************************************************************************** */
		/*                                                                            */
		/* Barre d'action                                                             */
		/*                                                                            */
		/* ************************************************************************** */

		print "<div class=\"tabsAction\">\n";

		print "</div>";


		/* ************************************************************************** */
		/*                                                                            */
		/* Affichage des utilisateurs de l'entrepot                                   */
		/*                                                                            */
		/* ************************************************************************** */
		print '<br>';

		print '<table class="noborder" width="100%">';
		print "<tr class=\"liste_titre\">";
		print_liste_field_titre($langs->trans("User"),"",    "p.ref","&amp;id=".$_GET['id'],"",'align="left"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Label"),"", "p.label","&amp;id=".$_GET['id'],"",'align="center"',$sortfield,$sortorder);
		print_liste_field_titre($langs->trans("Units"),"", "ps.reel","&amp;id=".$_GET['id'],"",'align="center"',$sortfield,$sortorder);
		print "</tr>";
		$sql = "SELECT u.rowid as rowid, u.name, u.firstname, ue.send, ue.consult ";
		$sql .= " FROM ".MAIN_DB_PREFIX."user_entrepot as ue, ".MAIN_DB_PREFIX."user as u ";

		$sql .= " WHERE ue.fk_user = u.rowid ";
		$sql .= " AND ue.fk_entrepot = ".$entrepot->id;

		$resql = $db->query($sql) ;
		if ($resql)
		{
			$num = $db->num_rows($resql);
			$i = 0;
			$var=True;
			while ($i < $num)
			{
		  $objp = $db->fetch_object($resql);

		  $var=!$var;

		  print "<tr $bc[$var]>";
		  print "<td><a href=\"../user.php?id=$objp->rowid\">";
		  print img_object($langs->trans("ShowUser"),"user").' '.$objp->firstname. ' '.$objp->name;
		  print "</a></td>";
		  print '<td align="center">'.$objp->consult.'</td>';
		  print '<td align="center">'.$objp->send.'</td>';
		  print "</tr>";
		  $i++;
			}
			$db->free($resql);
		}
		else
		{
			dol_print_error($db);
		}
		print "</table>\n";
	}
}

$db->close();

llxFooter('$Date: 2011/07/31 23:24:01 $ - $Revision: 1.16 $');
?>
