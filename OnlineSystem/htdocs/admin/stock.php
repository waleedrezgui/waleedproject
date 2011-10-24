<?php
/* Copyright (C) 2006      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2008-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
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
 *	\file       htdocs/admin/stock.php
 *	\ingroup    stock
 *	\brief      Page d'administration/configuration du module gestion de stock
 *	\version    $Id: stock.php,v 1.24 2011/07/31 22:23:22 eldy Exp $
 */
require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");

$langs->load("admin");
$langs->load("stocks");

// Securit check
if (!$user->admin)
accessforbidden();


/*
 * Actions
 */

if ($_POST["action"] == 'STOCK_USERSTOCK_AUTOCREATE')
{
	dolibarr_set_const($db, "STOCK_USERSTOCK_AUTOCREATE", $_POST["STOCK_USERSTOCK_AUTOCREATE"],'chaine',0,'',$conf->entity);
	Header("Location: stock.php");
	exit;
}
// Mode of stock decrease
if ($_POST["action"] == 'STOCK_CALCULATE_ON_BILL'
|| $_POST["action"] == 'STOCK_CALCULATE_ON_VALIDATE_ORDER'
|| $_POST["action"] == 'STOCK_CALCULATE_ON_SHIPMENT')
{
	$count=0;
	$db->begin();
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_BILL", '','chaine',0,'',$conf->entity);
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_VALIDATE_ORDER", '','chaine',0,'',$conf->entity);
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SHIPMENT", '','chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_BILL')           $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_BILL", $_POST["STOCK_CALCULATE_ON_BILL"],'chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_VALIDATE_ORDER') $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_VALIDATE_ORDER", $_POST["STOCK_CALCULATE_ON_VALIDATE_ORDER"],'chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_SHIPMENT')       $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SHIPMENT", $_POST["STOCK_CALCULATE_ON_SHIPMENT"],'chaine',0,'',$conf->entity);
	if ($count == 4)
	{
		$db->commit();
		Header("Location: stock.php");
		exit;
	}
	else
	{
		$db->rollback();
		dol_print_error("Error in some requests", LOG_ERR);
	}
}
// Mode of stock increase
if ($_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_BILL'
|| $_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER'
|| $_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER')
{
	$count=0;
	$db->begin();
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_BILL", '','chaine',0,'',$conf->entity);
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER", '','chaine',0,'',$conf->entity);
	$count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER", '','chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_BILL')           $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_BILL", $_POST["STOCK_CALCULATE_ON_SUPPLIER_BILL"],'chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER') $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER", $_POST["STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER"],'chaine',0,'',$conf->entity);
	if ($_POST["action"] == 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER') $count+=dolibarr_set_const($db, "STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER", $_POST["STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER"],'chaine',0,'',$conf->entity);
	if ($count == 4)
	{
		$db->commit();
		Header("Location: stock.php");
		exit;
	}
	else
	{
		$db->rollback();
		dol_print_error("Error in some requests", LOG_ERR);
	}
}



/*
 * View
 */

llxHeader('',$langs->trans("StockSetup"));

$linkback='<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print_fiche_titre($langs->trans("StockSetup"),$linkback,'setup');
print '<br>';

$html=new Form($db);
$var=true;
print '<table class="noborder" width="100%">';

print '<tr class="liste_titre">';
print "  <td>".$langs->trans("Parameters")."</td>\n";
print "  <td align=\"right\" width=\"160\">".$langs->trans("Value")."</td>\n";
print '</tr>'."\n";

/*
 * Formulaire parametres divers
 */

$var=!$var;

print "<tr ".$bc[$var].">";
print '<td width="60%">'.$langs->trans("UserWarehouseAutoCreate").'</td>';

print '<td width="160" align="right">';
print "<form method=\"post\" action=\"stock.php\">";
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print "<input type=\"hidden\" name=\"action\" value=\"STOCK_USERSTOCK_AUTOCREATE\">";
print $html->selectyesno("STOCK_USERSTOCK_AUTOCREATE",$conf->global->STOCK_USERSTOCK_AUTOCREATE,1);
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print '</form>';
print "</td>\n";
print "</tr>\n";


// Title rule for stock decrease
print '<tr class="liste_titre">';
print "  <td>".$langs->trans("RuleForStockManagementDecrease")."</td>\n";
print "  <td align=\"right\" width=\"160\">".$langs->trans("Value")."</td>\n";
print '</tr>'."\n";
$var=true;

if ($conf->facture->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("DeStockOnBill").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_BILL\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_BILL",$conf->global->STOCK_CALCULATE_ON_BILL,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}

if ($conf->commande->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("DeStockOnValidateOrder").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_VALIDATE_ORDER\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_VALIDATE_ORDER",$conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}

if ($conf->expedition->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("DeStockOnShipment").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_SHIPMENT\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_SHIPMENT",$conf->global->STOCK_CALCULATE_ON_SHIPMENT,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}


// Title rule for stock increase
print '<tr class="liste_titre">';
print "  <td>".$langs->trans("RuleForStockManagementIncrease")."</td>\n";
print "  <td align=\"right\" width=\"160\">".$langs->trans("Value")."</td>\n";
print '</tr>'."\n";
$var=true;

if ($conf->fournisseur->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("ReStockOnBill").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_SUPPLIER_BILL\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_SUPPLIER_BILL",$conf->global->STOCK_CALCULATE_ON_SUPPLIER_BILL,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}

if ($conf->fournisseur->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("ReStockOnValidateOrder").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER",$conf->global->STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}
if ($conf->fournisseur->enabled)
{
	$var=!$var;
	print "<tr ".$bc[$var].">";
	print '<td width="60%">'.$langs->trans("ReStockOnDispatchOrder").'</td>';
	print '<td width="160" align="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print "<input type=\"hidden\" name=\"action\" value=\"STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER\">";
	print $html->selectyesno("STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER",$conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print "</form>\n</td>\n</tr>\n";
}


print '</table>';
$db->close();

llxFooter('$Date: 2011/07/31 22:23:22 $ - $Revision: 1.24 $');
?>
