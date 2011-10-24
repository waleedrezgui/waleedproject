<?php
/* Copyright (C) 2001-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Eric Seigne          <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani     <acianfa@free.fr>
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
 *	\file       htdocs/product/price.php
 *	\ingroup    product
 *	\brief      Page to show product prices
 *	\version    $Id: price.php,v 1.111 2011/08/04 21:46:50 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/product.lib.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");

$langs->load("products");
$langs->load("bills");

// Security check
if (isset($_GET["id"]) || isset($_GET["ref"]))
{
	$id = isset($_GET["id"])?$_GET["id"]:(isset($_GET["ref"])?$_GET["ref"]:'');
}
$fieldid = isset($_GET["ref"])?'ref':'rowid';
$socid=$user->societe_id?$user->societe_id:0;
$result=restrictedArea($user,'produit|service',$id,'product','','',$fieldid);


/*
 * Actions
 */

if ($_POST["action"] == 'update_price' && ! $_POST["cancel"] && ($user->rights->produit->creer || $user->rights->service->creer))
{
	$product = new Product($db);

	$result = $product->fetch($_GET["id"]);

	// MultiPrix
	if($conf->global->PRODUIT_MULTIPRICES)
	{
		$newprice='';
		$newprice_min='';
		$newpricebase='';
		$newvat='';

		for($i=1; $i<=$conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++)
		{
			if (isset($_POST["price_".$i]))
			{
				$level=$i;
				$newprice=price2num($_POST["price_".$i],'MU');
				$newprice_min=price2num($_POST["price_min_".$i],'MU');
				$newpricebase=$_POST["multiprices_base_type_".$i];
				$newnpr=(preg_match('/\*/',$_POST["tva_tx_".$i]) ? 1 : 0);
				$newvat=str_replace('*','',$_POST["tva_tx_".$i]);
				break;	// We found submited price
			}
		}
	}
	else
	{
		$level=0;
		$newprice=price2num($_POST["price"],'MU');
		$newprice_min=price2num($_POST["price_min"],'MU');
		$newpricebase=$_POST["price_base_type"];
		$newnpr=(preg_match('/\*/',$_POST["tva_tx"]) ? 1 : 0);
		$newvat=str_replace('*','',$_POST["tva_tx"]);
	}

	if ($product->update_price($product->id, $newprice, $newpricebase, $user, $newvat, $newprice_min, $level, $newnpr) > 0)
	{
		$_GET["action"] = '';
		$mesg = '<div class="ok">'.$langs->trans("RecordSaved").'</div>';
	}
	else
	{
		$_GET["action"] = 'edit_price';
		$mesg = '<div class="error">'.$product->error.'</div>';
	}
}

if ($_GET["action"] == 'delete' && $user->rights->produit->supprimer)
{
	$productstatic = new Product($db);
	$result=$productstatic->log_price_delete($user,$_GET["lineid"]);
	if ($result < 0) $mesg='<div class="error">'.$productstatic->error.'</div>';
}


/*
 * View
 */

$html = new Form($db);

$product = new Product($db);
if ($_GET["ref"]) $result = $product->fetch('',$_GET["ref"]);
if ($_GET["id"])  $result = $product->fetch($_GET["id"]);

llxHeader("","",$langs->trans("CardProduct".$product->type));

$head=product_prepare_head($product, $user);
$titre=$langs->trans("CardProduct".$product->type);
$picto=($product->type==1?'service':'product');
dol_fiche_head($head, 'price', $titre, 0, $picto);


print '<table class="border" width="100%">';

// Ref
print '<tr>';
print '<td width="15%">'.$langs->trans("Ref").'</td><td colspan="2">';
print $html->showrefnav($product,'ref','',1,'ref');
print '</td>';
print '</tr>';

// Label
print '<tr><td>'.$langs->trans("Label").'</td><td>'.$product->libelle.'</td>';

$isphoto=$product->is_photo_available($conf->product->dir_output);

$nblignes=5;
if ($isphoto)
{
	// Photo
	print '<td valign="middle" align="center" width="30%" rowspan="'.$nblignes.'">';
	print $product->show_photos($conf->product->dir_output,1,1,0,0,0,80);
	print '</td>';
}

print '</tr>';

// MultiPrix
if ($conf->global->PRODUIT_MULTIPRICES)
{
	if ($socid)
	{
		$soc = new Societe($db);
		$soc->id = $socid;
		$soc->fetch($socid);

		print '<tr><td>'.$langs->trans("SellingPrice").'</td>';

		if ($product->multiprices_base_type["$soc->price_level"] == 'TTC')
		{
			print '<td>'.price($product->multiprices_ttc["$soc->price_level"]);
		}
		else
		{
			print '<td>'.price($product->multiprices["$soc->price_level"]);
		}

		if ($product->multiprices_base_type["$soc->price_level"])
		{
			print ' '.$langs->trans($product->multiprices_base_type["$soc->price_level"]);
		}
		else
		{
			print ' '.$langs->trans($product->price_base_type);
		}
		print '</td></tr>';

		// Prix mini
		print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
		if ($product->multiprices_base_type["$soc->price_level"] == 'TTC')
		{
			print price($product->multiprices_min_ttc["$soc->price_level"]).' '.$langs->trans($product->multiprices_base_type["$soc->price_level"]);
		}
		else
		{
			print price($product->multiprices_min["$soc->price_level"]).' '.$langs->trans($product->multiprices_base_type["$soc->price_level"]);
		}
		print '</td></tr>';

		// TVA
		print '<tr><td>'.$langs->trans("VATRate").'</td><td>'.vatrate($product->multiprices_tva_tx["$soc->price_level"],true).'</td></tr>';
	}
	else
	{
		for ($i=1; $i<=$conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++)
		{
            // TVA
            if ($i == 1) // We show only price for level 1
            {
                 print '<tr><td>'.$langs->trans("VATRate").'</td><td>'.vatrate($product->multiprices_tva_tx[1],true).'</td></tr>';
            }

            print '<tr><td>'.$langs->trans("SellingPrice").' '.$i.'</td>';

			if ($product->multiprices_base_type["$i"] == 'TTC')
			{
				print '<td>'.price($product->multiprices_ttc["$i"]);
			}
			else
			{
				print '<td>'.price($product->multiprices["$i"]);
			}

			if ($product->multiprices_base_type["$i"])
			{
				print ' '.$langs->trans($product->multiprices_base_type["$i"]);
			}
			else
			{
				print ' '.$langs->trans($product->price_base_type);
			}
			print '</td></tr>';

			// Prix mini
			print '<tr><td>'.$langs->trans("MinPrice").' '.$i.'</td><td>';
			if ($product->multiprices_base_type["$i"] == 'TTC')
			{
				print price($product->multiprices_min_ttc["$i"]).' '.$langs->trans($product->multiprices_base_type["$i"]);
			}
			else
			{
				print price($product->multiprices_min["$i"]).' '.$langs->trans($product->multiprices_base_type["$i"]);
			}
			print '</td></tr>';
		}
	}
}
else
{
    // TVA
    print '<tr><td>'.$langs->trans("VATRate").'</td><td>'.vatrate($product->tva_tx.($product->tva_npr?'*':''),true).'</td></tr>';

    // Price
	print '<tr><td>'.$langs->trans("SellingPrice").'</td><td>';
	if ($product->price_base_type == 'TTC')
	{
		print price($product->price_ttc).' '.$langs->trans($product->price_base_type);
	}
	else
	{
		print price($product->price).' '.$langs->trans($product->price_base_type);
	}
	print '</td></tr>';

	// Price minimum
	print '<tr><td>'.$langs->trans("MinPrice").'</td><td>';
	if ($product->price_base_type == 'TTC')
	{
		print price($product->price_min_ttc).' '.$langs->trans($product->price_base_type);
	}
	else
	{
		print price($product->price_min).' '.$langs->trans($product->price_base_type);
	}
	print '</td></tr>';
}

// Status (to sell)
print '<tr><td>'.$langs->trans("Status").' ('.$langs->trans("Sell").')'.'</td><td>';
print $product->getLibStatut(2,0);
print '</td></tr>';

print "</table>\n";

print "</div>\n";

if ($mesg) print $mesg;


/* ************************************************************************** */
/*                                                                            */
/* Barre d'action                                                             */
/*                                                                            */
/* ************************************************************************** */

if (empty($_GET["action"]) || $_GET["action"]=='delete')
{
	print "\n<div class=\"tabsAction\">\n";

	if ($user->rights->produit->creer || $user->rights->service->creer)
	{
		print '<a class="butAction" href="'.DOL_URL_ROOT.'/product/price.php?action=edit_price&amp;id='.$product->id.'">'.$langs->trans("UpdatePrice").'</a>';
	}

	print "\n</div>\n";
}



/*
 * Edition du prix
 */
if ($_GET["action"] == 'edit_price' && ($user->rights->produit->creer || $user->rights->service->creer))
{
	print_fiche_titre($langs->trans("NewPrice"),'','');

	if (empty($conf->global->PRODUIT_MULTIPRICES))
	{
		print '<form action="price.php?id='.$product->id.'" method="post">';
		print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		print '<input type="hidden" name="action" value="update_price">';
		print '<input type="hidden" name="id" value="'.$product->id.'">';
		print '<table class="border" width="100%">';

        // VAT
        print '<tr><td>'.$langs->trans("VATRate").'</td><td>';
        print $html->load_tva("tva_tx",$product->tva_tx,$mysoc,'',$product->id,$product->tva_npr);
        print '</td></tr>';

		// Price base
		print '<tr><td width="15%">';
		print $langs->trans('PriceBase');
		print '</td>';
		print '<td>';
		print $html->select_PriceBaseType($product->price_base_type, "price_base_type");
		print '</td>';
		print '</tr>';

		// Price
		print '<tr><td width="20%">';
		$text=$langs->trans('SellingPrice');
		print $html->textwithpicto($text,$langs->trans("PrecisionUnitIsLimitedToXDecimals",$conf->global->MAIN_MAX_DECIMALS_UNIT),$direction=1,$usehelpcursor=1);
		print '</td><td>';
		if ($product->price_base_type == 'TTC')
		{
			print '<input name="price" size="10" value="'.price($product->price_ttc).'">';
		}
		else
		{
			print '<input name="price" size="10" value="'.price($product->price).'">';
		}
		print '</td></tr>';

		// Price minimum
		print '<tr><td>' ;
		$text=$langs->trans('MinPrice') ;
		print $html->textwithpicto($text,$langs->trans("PrecisionUnitIsLimitedToXDecimals",$conf->global->MAIN_MAX_DECIMALS_UNIT),$direction=1,$usehelpcursor=1);
		if ($product->price_base_type == 'TTC')
		{
			print '<td><input name="price_min" size="10" value="'.price($product->price_min_ttc).'">';
		}
		else
		{
			print '<td><input name="price_min" size="10" value="'.price($product->price_min).'">';
		}
		print '</td></tr>';

		print '<tr><td colspan="2" align="center"><input type="submit" class="button" value="'.$langs->trans("Save").'">&nbsp;';
		print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'"></td></tr>';
		print '</table>';
		print '</form>';
	}
	else
	{
		for ($i=1; $i<=$conf->global->PRODUIT_MULTIPRICES_LIMIT; $i++)
		{
			print '<form action="price.php?id='.$product->id.'" method="post">';
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
			print '<input type="hidden" name="action" value="update_price">';
			print '<input type="hidden" name="id" value="'.$product->id.'">';
			print '<table class="border" width="100%">';

            // VAT
            if ($i == 1)
            {
                print '<tr><td>'.$langs->trans("VATRate").'</td><td>';
                print $html->load_tva("tva_tx_".$i,$product->multiprices_tva_tx["$i"],$mysoc,'',$product->id);
                print '</td></tr>';
            }
            else
            {    // We always use the vat rate of price level 1 (A vat rate does not depends on customer)
                print '<input type="hidden" name="tva_tx_'.$i.'" value="'.$product->multiprices_tva_tx[1].'">';
            }

			// Selling price
			print '<tr><td width="20%">';
			$text=$langs->trans('SellingPrice').' '.$i;
			print $html->textwithpicto($text,$langs->trans("PrecisionUnitIsLimitedToXDecimals",$conf->global->MAIN_MAX_DECIMALS_UNIT),$direction=1,$usehelpcursor=1);
			print '</td><td>';
			if ($product->multiprices_base_type["$i"] == 'TTC')
			{
				print '<input name="price_'.$i.'" size="10" value="'.price($product->multiprices_ttc["$i"]).'">';
			}
			else
			{
				print '<input name="price_'.$i.'" size="10" value="'.price($product->multiprices["$i"]).'">';
			}
			print $html->select_PriceBaseType($product->multiprices_base_type["$i"], "multiprices_base_type_".$i);
			print '</td></tr>';

            // Min price
			print '<tr><td>';
			$text=$langs->trans('MinPrice').' '.$i;
			print $html->textwithpicto($text,$langs->trans("PrecisionUnitIsLimitedToXDecimals",$conf->global->MAIN_MAX_DECIMALS_UNIT),$direction=1,$usehelpcursor=1);
			if ($product->multiprices_base_type["$i"] == 'TTC')
			{
				print '<td><input name="price_min_'.$i.'" size="10" value="'.price($product->multiprices_min_ttc["$i"]).'">';
			}
			else
			{
				print '<td><input name="price_min_'.$i.'" size="10" value="'.price($product->multiprices_min["$i"]).'">';
			}
			print '</td></tr>';

			print '<tr><td colspan="2" align="center"><input type="submit" class="button" value="'.$langs->trans("Save").'">&nbsp;';
			print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'"></td></tr>';
			print '</table>';
			print '</form>';
		}

	}
}


// Liste des evolutions du prix
$sql = "SELECT p.rowid, p.price, p.price_ttc, p.price_base_type, p.tva_tx, p.recuperableonly,";
$sql.= " p.price_level, p.price_min, p.price_min_ttc,";
$sql.= " p.date_price as dp, u.rowid as user_id, u.login";
$sql.= " FROM ".MAIN_DB_PREFIX."product_price as p,";
$sql.= " ".MAIN_DB_PREFIX."user as u";
$sql.= " WHERE fk_product = ".$product->id;
$sql.= " AND p.fk_user_author = u.rowid";
if ($socid && $conf->global->PRODUIT_MULTIPRICES) $sql.= " AND p.price_level = ".$soc->price_level;
$sql.= " ORDER BY p.date_price DESC, p.price_level ASC";
//$sql .= $db->plimit();

$result = $db->query($sql) ;
if ($result)
{
	$num = $db->num_rows($result);

	if (! $num)
	{
		$db->free($result) ;

		// Il doit au moins y avoir la ligne de prix initial.
		// On l'ajoute donc pour remettre a niveau (pb vieilles versions)
		$product->update_price($product->id, $product->price, 'HT', $user, $newprice_min);

		$result = $db->query($sql) ;
		$num = $db->num_rows($result);
	}

	if ($num > 0)
	{
		print '<br>';

		print '<table class="noborder" width="100%">';

		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("AppliedPricesFrom").'</td>';

		if($conf->global->PRODUIT_MULTIPRICES)
		{
			print '<td>'.$langs->trans("MultiPriceLevelsName").'</td>';
		}

		print '<td align="center">'.$langs->trans("PriceBase").'</td>';
		print '<td align="right">'.$langs->trans("VAT").'</td>';
		print '<td align="right">'.$langs->trans("HT").'</td>';
		print '<td align="right">'.$langs->trans("TTC").'</td>';
		print '<td align="right">'.$langs->trans("MinPrice").' '.$langs->trans("HT").'</td>';
		print '<td align="right">'.$langs->trans("MinPrice").' '.$langs->trans("TTC").'</td>';
		print '<td align="right">'.$langs->trans("ChangedBy").'</td>';
		if ($user->rights->produit->supprimer) print '<td align="right">&nbsp;</td>';
		print '</tr>';

		$var=True;
		$i = 0;
		while ($i < $num)
		{
			$objp = $db->fetch_object($result);
			$var=!$var;
			print "<tr $bc[$var]>";
			// Date
			print "<td>".dol_print_date($db->jdate($objp->dp),"dayhour")."</td>";

			// Price level
			if ($conf->global->PRODUIT_MULTIPRICES)
			{
				print '<td align="center">'.$objp->price_level."</td>";
			}

			print '<td align="center">'.$langs->trans($objp->price_base_type)."</td>";
			print '<td align="right">'.vatrate($objp->tva_tx,true,$objp->recuperableonly)."</td>";
			print '<td align="right">'.price($objp->price)."</td>";
			print '<td align="right">'.price($objp->price_ttc)."</td>";
			print '<td align="right">'.price($objp->price_min).'</td>';
			print '<td align="right">'.price($objp->price_min_ttc).'</td>';

			// User
			print '<td align="right"><a href="'.DOL_URL_ROOT.'/user/fiche.php?id='.$objp->user_id.'">'.img_object($langs->trans("ShowUser"),'user').' '.$objp->login.'</a></td>';

			// Action
			if ($user->rights->produit->supprimer)
			{
				print '<td align="right">';
				if ($i > 0)
				{
					print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&amp;id='.$product->id.'&amp;lineid='.$objp->rowid.'">';
					print img_delete();
					print '</a>';
				}
				else print '&nbsp;';	// Can not delete last price (it's current price)
				print '</td>';
			}

			print "</tr>\n";
			$i++;
		}
		$db->free($result);
		print "</table>";
		print "<br>";
	}
}
else
{
	dol_print_error($db);
}


$db->close();

llxFooter('$Date: 2011/08/04 21:46:50 $ - $Revision: 1.111 $');
?>
