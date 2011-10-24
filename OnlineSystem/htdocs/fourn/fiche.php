<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Eric Seigne          <erics@rycks.com>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
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
 *	\file       htdocs/fourn/fiche.php
 *	\ingroup    fournisseur, facture
 *	\brief      Page for supplier third party card (view, edit)
 *	\version	$Id: fiche.php,v 1.140 2011/07/31 23:57:03 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.class.php");
require_once(DOL_DOCUMENT_ROOT."/fourn/class/fournisseur.facture.class.php");
require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");
require_once(DOL_DOCUMENT_ROOT."/lib/company.lib.php");
if ($conf->adherent->enabled) require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent.class.php");

$langs->load('suppliers');
$langs->load('products');
$langs->load('bills');
$langs->load('orders');
$langs->load('companies');
$langs->load('commercial');

// Security check
$socid = isset($_GET["socid"])?$_GET["socid"]:'';
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'societe',$socid,'');


/*
 * Action
 */

if ($_POST['action'] == 'setsupplieraccountancycode')
{
    $societe = new Societe($db);
    $result=$societe->fetch($_POST['socid']);
    $societe->code_compta_fournisseur=$_POST["supplieraccountancycode"];
    $result=$societe->update($societe->id,$user,1,0,1);
    if ($result < 0)
    {
        $mesg=join(',',$societe->errors);
    }
    $POST["action"]="";
    $socid=$_POST["socid"];
}



/*
 * View
 */

$societe = new Fournisseur($db);
$contactstatic = new Contact($db);
$form = new Form($db);

if ( $societe->fetch($socid) )
{
	llxHeader('',$langs->trans('SupplierCard'));

	/*
	 * Affichage onglets
	 */
	$head = societe_prepare_head($societe);

	dol_fiche_head($head, 'supplier', $langs->trans("ThirdParty"),0,'company');


	print '<table width="100%" class="notopnoleftnoright">';
	print '<tr><td valign="top" width="50%" class="notopnoleft">';

	print '<table width="100%" class="border">';
	print '<tr><td width="20%">'.$langs->trans("ThirdPartyName").'</td><td width="80%" colspan="3">';
	$societe->next_prev_filter="te.fournisseur = 1";
	print $form->showrefnav($societe,'socid','',($user->societe_id?0:1),'rowid','nom','','');
	print '</td></tr>';

    if (! empty($conf->global->SOCIETE_USEPREFIX))  // Old not used prefix field
    {
        print '<tr><td>'.$langs->trans('Prefix').'</td><td colspan="3">'.$societe->prefix_comm.'</td></tr>';
    }

	if ($societe->fournisseur)
	{
        print '<tr>';
        print '<td nowrap="nowrap">'.$langs->trans("SupplierCode"). '</td><td colspan="3">';
        print $societe->code_fournisseur;
        if ($societe->check_codefournisseur() <> 0) print ' <font class="error">('.$langs->trans("WrongSupplierCode").')</font>';
        print '</td>';
        print '</tr>';

        print '<tr>';
        print '<td>';
        print $form->editfieldkey("SupplierAccountancyCode",'supplieraccountancycode',$societe->code_compta_fournisseur,'socid',$societe->id,$user->rights->societe->creer);
        print '</td><td colspan="3">';
        print $form->editfieldval("SupplierAccountancyCode",'supplieraccountancycode',$societe->code_compta_fournisseur,'socid',$societe->id,$user->rights->societe->creer);
        print '</td>';
        print '</tr>';
	}

	// Address
	print '<tr><td valign="top">'.$langs->trans("Address").'</td><td colspan="3">';
	dol_print_address($societe->address,'gmap','thirdparty',$societe->id);
	print '</td></tr>';

	// Zip / Town
	print '<tr><td nowrap="nowrap">'.$langs->trans("Zip").' / '.$langs->trans("Town").'</td><td colspan="3">'.$societe->cp.(($societe->cp && $societe->ville)?' / ':'').$societe->ville.'</td>';
	print '</tr>';

	// Country
	print '<tr><td>'.$langs->trans("Country").'</td><td colspan="3">';
	$img=picto_from_langcode($societe->pays_code);
	if ($societe->isInEEC()) print $form->textwithpicto(($img?$img.' ':'').$societe->pays,$langs->trans("CountryIsInEEC"),1,0);
	else print ($img?$img.' ':'').$societe->pays;
	print '</td></tr>';

	// Phone
	print '<tr><td>'.$langs->trans("Phone").'</td><td style="min-width: 25%;">'.dol_print_phone($societe->tel,$societe->pays_code,0,$societe->id,'AC_TEL').'</td>';

	// Fax
	print '<td>'.$langs->trans("Fax").'</td><td style="min-width: 25%;">'.dol_print_phone($societe->fax,$societe->pays_code,0,$societe->id,'AC_FAX').'</td></tr>';

    // EMail
	print '<td>'.$langs->trans('EMail').'</td><td colspan="3">'.dol_print_email($societe->email,0,$societe->id,'AC_EMAIL').'</td></tr>';

	// Web
	print '<tr><td>'.$langs->trans("Web")."</td><td colspan=\"3\">".dol_print_url($societe->url)."</td></tr>";

	// Assujetti a TVA ou pas
	print '<tr>';
	print '<td nowrap="nowrap">'.$langs->trans('VATIsUsed').'</td><td colspan="3">';
	print yn($societe->tva_assuj);
	print '</td>';
	print '</tr>';

	// Local Taxes
	if($mysoc->pays_code=='ES')
	{
		if($mysoc->localtax1_assuj=="1" && $mysoc->localtax2_assuj=="1")
		{
			print '<tr><td nowrap="nowrap">'.$langs->trans('LocalTax1IsUsedES').'</td><td colspan="3">';
			print yn($societe->localtax1_assuj);
			print '</td></tr>';
			print '<tr><td nowrap="nowrap">'.$langs->trans('LocalTax2IsUsedES').'</td><td colspan="3">';
			print yn($societe->localtax2_assuj);
			print '</td></tr>';
		}
		elseif($mysoc->localtax1_assuj=="1")
		{
			print '<tr><td>'.$langs->trans("LocalTax1IsUsedES").'</td><td colspan="3">';
			print yn($societe->localtax1_assuj);
			print '</td></tr>';
		}
		elseif($mysoc->localtax2_assuj=="1")
		{
			print '<tr><td>'.$langs->trans("LocalTax2IsUsedES").'</td><td colspan="3">';
			print yn($societe->localtax2_assuj);
			print '</td></tr>';
		}
	}

    // TVA Intra
    print '<tr><td nowrap>'.$langs->trans('VATIntraVeryShort').'</td><td colspan="3">';
    print $objsoc->tva_intra;
    print '</td></tr>';

    // Module Adherent
    if ($conf->adherent->enabled)
    {
        $langs->load("members");
        $langs->load("users");
        print '<tr><td width="25%" valign="top">'.$langs->trans("LinkedToDolibarrMember").'</td>';
        print '<td colspan="3">';
        $adh=new Adherent($db);
        $result=$adh->fetch('','',$societe->id);
        if ($result > 0)
        {
            $adh->ref=$adh->getFullName($langs);
            print $adh->getNomUrl(1);
        }
        else
        {
            print $langs->trans("UserNotLinkedToMember");
        }
        print '</td>';
        print "</tr>\n";
    }

	print '</table>';

	print '</td><td valign="top" width="50%" class="notopnoleftnoright">';
	$var=true;

	$MAXLIST=5;

	// Lien recap
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td colspan="4"><table width="100%" class="nobordernopadding"><tr><td>'.$langs->trans("Summary").'</td>';
	print '<td align="right"><a href="'.DOL_URL_ROOT.'/fourn/recap-fourn.php?socid='.$societe->id.'">'.$langs->trans("ShowSupplierPreview").'</a></td></tr></table></td>';
	print '</tr>';
	print '</table>';

	/*
	 * List of products
	 */
	if ($conf->product->enabled || $conf->service->enabled)
	{
		$langs->load("products");
		print '<table class="noborder" width="100%">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("ProductsAndServices").'</td><td align="right">';
		print '<a href="'.DOL_URL_ROOT.'/fourn/product/liste.php?fourn_id='.$societe->id.'">'.$langs->trans("All").' ('.$societe->NbProduct().')';
		print '</a></td></tr></table>';
	}


	print '<br>';

	/*
	 * Last orders
	 */
	$orderstatic = new CommandeFournisseur($db);

	if ($user->rights->fournisseur->commande->lire)
	{
		$sql  = "SELECT p.rowid,p.ref, p.date_commande as dc, p.fk_statut";
		$sql.= " FROM ".MAIN_DB_PREFIX."commande_fournisseur as p ";
		$sql.= " WHERE p.fk_soc =".$societe->id;
		$sql.= " ORDER BY p.date_commande DESC";
		$sql.= " ".$db->plimit($MAXLIST);
		$resql=$db->query($sql);
		if ($resql)
		{
			$i = 0 ;
			$num = $db->num_rows($resql);
			print '<table class="noborder" width="100%">';

			if ($num > 0)
			{
    			print '<tr class="liste_titre">';
    			print '<td colspan="3">';
    			print '<table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans("LastOrders",($num<$MAXLIST?"":$MAXLIST)).'</td>';
    			print '<td align="right"><a href="commande/liste.php?socid='.$societe->id.'">'.$langs->trans("AllOrders").' ('.$num.')</td>';
                print '<td width="20px" align="right"><a href="'.DOL_URL_ROOT.'/commande/stats/index.php?mode=supplier&socid='.$societe->id.'">'.img_picto($langs->trans("Statistics"),'stats').'</a></td>';
    			print '</tr></table>';
    			print '</td></tr>';
			}

			while ($i < $num && $i <= $MAXLIST)
			{
				$obj = $db->fetch_object($resql);
				$var=!$var;

				print "<tr $bc[$var]>";
				print '<td><a href="commande/fiche.php?id='.$obj->rowid.'">'.img_object($langs->trans("ShowOrder"),"order")." ".$obj->ref.'</a></td>';
				print '<td align="center" width="80">';
				if ($obj->dc)
				{
					print dol_print_date($db->jdate($obj->dc),'day');
				}
				else
				{
					print "-";
				}
				print '</td>';
				print '<td align="right" nowrap="nowrap">'.$orderstatic->LibStatut($obj->fk_statut,5).'</td>';
				print '</tr>';
				$i++;
			}
			$db->free($resql);
			print "</table>";
		}
		else
		{
			dol_print_error($db);
		}
	}

	/*
	 * Last invoices
	 */
	$MAXLIST=5;

	$langs->load('bills');
	$facturestatic = new FactureFournisseur($db);

	if ($user->rights->fournisseur->facture->lire)
	{
		$sql = 'SELECT f.rowid,f.libelle,f.facnumber,f.fk_statut,f.datef as df,f.total_ttc as amount,f.paye,';
		$sql.= ' SUM(pf.amount) as am';
		$sql.= ' FROM '.MAIN_DB_PREFIX.'facture_fourn as f';
		$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'paiementfourn_facturefourn as pf ON f.rowid=pf.fk_facturefourn';
		$sql.= ' WHERE f.fk_soc = '.$societe->id;
		$sql.= ' GROUP BY f.rowid,f.libelle,f.facnumber,f.fk_statut,f.datef,f.total_ttc,f.paye';
		$sql.= ' ORDER BY f.datef DESC';
		$resql=$db->query($sql);
		if ($resql)
		{
			$i = 0 ;
			$num = $db->num_rows($resql);
			print '<table class="noborder" width="100%">';
			if ($num > 0)
			{
    			print '<tr class="liste_titre">';
    			print '<td colspan="4">';
    			print '<table class="nobordernopadding" width="100%"><tr><td>'.$langs->trans('LastSuppliersBills',($num<=$MAXLIST?"":$MAXLIST)).'</td><td align="right"><a href="facture/index.php?socid='.$societe->id.'">'.$langs->trans('AllBills').' ('.$num.')</td>';
                print '<td width="20px" align="right"><a href="'.DOL_URL_ROOT.'/compta/facture/stats/index.php?mode=supplier&socid='.$societe->id.'">'.img_picto($langs->trans("Statistics"),'stats').'</a></td>';
    			print '</tr></table>';
    			print '</td></tr>';
			}
			while ($i < min($num,$MAXLIST))
			{
				$obj = $db->fetch_object($resql);
				$var=!$var;
				print '<tr '.$bc[$var].'>';
				print '<td>';
				print '<a href="facture/fiche.php?facid='.$obj->rowid.'">';
				print img_object($langs->trans('ShowBill'),'bill').' '.$obj->facnumber.'</a> '.dol_trunc($obj->libelle,14).'</td>';
				print '<td align="center" nowrap="nowrap">'.dol_print_date($db->jdate($obj->df),'day').'</td>';
				print '<td align="right" nowrap="nowrap">'.price($obj->amount).'</td>';
				print '<td align="right" nowrap="nowrap">';
				print $facturestatic->LibStatut($obj->paye,$obj->fk_statut,5,$obj->am);
				print '</td>';
				print '</tr>';
				$i++;
			}
			$db->free($resql);
			print '</table>';
		}
		else
		{
			dol_print_error($db);
		}
	}

	print '</td></tr>';
	print '</table>' . "\n";
	print '</div>';


	/*
	 * Barre d'actions
	 */

	print '<div class="tabsAction">';

	if ($user->rights->fournisseur->commande->creer)
	{
		$langs->load("orders");
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/fourn/commande/fiche.php?action=create&socid='.$societe->id.'">'.$langs->trans("AddOrder").'</a>';
	}

	if ($user->rights->fournisseur->facture->creer)
	{
		$langs->load("bills");
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/fourn/facture/fiche.php?action=create&socid='.$societe->id.'">'.$langs->trans("AddBill").'</a>';
	}

    // Add action
    if ($conf->agenda->enabled && ! empty($conf->global->MAIN_REPEATTASKONEACHTAB))
    {
        if ($user->rights->agenda->myactions->create)
        {
            print '<a class="butAction" href="'.DOL_URL_ROOT.'/comm/action/fiche.php?action=create&socid='.$societe->id.'">'.$langs->trans("AddAction").'</a>';
        }
        else
        {
            print '<a class="butAction" title="'.dol_escape_js($langs->trans("NotAllowed")).'" href="#">'.$langs->trans("AddAction").'</a>';
        }
    }

	/*if ($user->rights->societe->contact->creer)
	{
		print "<a class=\"butAction\" href=\"".DOL_URL_ROOT.'/contact/fiche.php?socid='.$socid."&amp;action=create\">".$langs->trans("AddContact")."</a>";
	}*/

	print '</div>';
	print '<br>';

    if (! empty($conf->global->MAIN_REPEATCONTACTONEACHTAB))
    {
        print '<br>';
        // List of contacts
        show_contacts($conf,$langs,$db,$societe,$_SERVER["PHP_SELF"].'?socid='.$societe->id);
    }

    if (! empty($conf->global->MAIN_REPEATTASKONEACHTAB))
    {
        // List of todo actions
        show_actions_todo($conf,$langs,$db,$societe);

        // List of done actions
        show_actions_done($conf,$langs,$db,$societe);
    }
}
else
{
	dol_print_error($db);
}
$db->close();

llxFooter('$Date: 2011/07/31 23:57:03 $ - $Revision: 1.140 $');
?>
