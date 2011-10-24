<?php
/* Copyright (C) 2001-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2010	   Juanjo Menent	    <jmenent@2byte.es>
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
 *       \file       htdocs/compta/bank/rappro.php
 *       \ingroup    banque
 *       \brief      Page to reconciliate bank transactions
 *       \version    $Id: rappro.php,v 1.68 2011/07/31 22:23:16 eldy Exp $
 */

require("./pre.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/bank.lib.php");
require_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");
require_once(DOL_DOCUMENT_ROOT."/adherents/class/adherent.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/sociales/class/chargesociales.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/paiement/class/paiement.class.php");
require_once(DOL_DOCUMENT_ROOT."/compta/tva/class/tva.class.php");
require_once(DOL_DOCUMENT_ROOT."/fourn/class/paiementfourn.class.php");

$langs->load("banks");
$langs->load("bills");

if (! $user->rights->banque->consolidate) accessforbidden();



/*
 * Actions
 */

if (($user->rights->banque->modifier || $user->rights->banque->consolidate) && $_GET["action"] == 'dvnext')
{
	$ac = new Account($db);
	$ac->datev_next($_GET["rowid"]);
}

if (($user->rights->banque->modifier || $user->rights->banque->consolidate) && $_GET["action"] == 'dvprev')
{
	$ac = new Account($db);
	$ac->datev_previous($_GET["rowid"]);
}

// Conciliation
if ($user->rights->banque->consolidate && $_POST["action"] == 'rappro')
{
	// Definition, nettoyage parametres
    $num_releve=trim($_POST["num_releve"]);

    if ($num_releve)
    {
        $bankline=new AccountLine($db);
        $result=$bankline->fetch($_POST["rowid"]);
        $bankline->num_releve=$_POST["num_releve"];

        $result=$bankline->update_conciliation($user,$_POST["cat"]);
        if ($result < 0) $mesg=$bankline->error;
    }
    else
    {
    	$langs->load("errors");
        $mesg='<div class="error">'.$langs->trans("ErrorPleaseTypeBankTransactionReportName").'</div>';
    }
}

/*
 * Action suppression ecriture
 */
if ($_GET["action"] == 'del')
{
	$accline=new AccountLine($db);
	$accline->fetch($_GET["rowid"]);
	$result=$accline->delete();
    if ($result < 0)
	{
        dol_print_error($db,$accline->error);
    }
}


// Charge categories
$sql = "SELECT rowid, label FROM ".MAIN_DB_PREFIX."bank_categ ORDER BY label";
$resql = $db->query($sql);
$options="";
if ($resql) {
    $var=True;
    $num = $db->num_rows($resql);
    $i = 0;
    while ($i < $num) {
        if ($options == "") { $options = "<option value=\"0\" selected=\"true\">&nbsp;</option>"; }
        $obj = $db->fetch_object($resql);
        $options .= "<option value=\"$obj->rowid\">$obj->label</option>\n"; $i++;
    }
    $db->free($resql);
}


/*
 * View
 */

$form=new Form($db);

llxHeader();

$societestatic=new Societe($db);
$chargestatic=new ChargeSociales($db);
$memberstatic=new Adherent($db);
$paymentstatic=new Paiement($db);
$paymentsupplierstatic=new PaiementFourn($db);
$paymentvatstatic=new TVA($db);

$acct = new Account($db);
$acct->fetch($_GET["account"]);

$now=dol_now();

$sql = "SELECT b.rowid, b.dateo as do, b.datev as dv, b.amount, b.label, b.rappro, b.num_releve, b.num_chq, b.fk_type as type";
$sql.= " FROM ".MAIN_DB_PREFIX."bank as b";
$sql.= " WHERE rappro=0 AND fk_account=".$_GET["account"];
$sql.= " ORDER BY dateo ASC";
$sql.= " LIMIT 1000";	// Limit to avoid page overload

$resql = $db->query($sql);
if ($resql)
{
    $var=True;
    $num = $db->num_rows($resql);

    print_fiche_titre($langs->trans("Reconciliation").': <a href="account.php?account='.$_GET["account"].'">'.$acct->label.'</a>');
    print '<br>';

    if ($mesg) print $mesg."<br>";

    // Show last bank receipts
    $nbmax=5;
    $liste="";
    $sql = "SELECT DISTINCT num_releve FROM ".MAIN_DB_PREFIX."bank";
    $sql.= " WHERE fk_account=".$_GET["account"]." AND num_releve IS NOT NULL";
    $sql.= $db->order("num_releve","DESC");
    $sql.= $db->plimit($nbmax+1);
    print $langs->trans("LastAccountStatements").' : ';
    $resqlr=$db->query($sql);
    if ($resqlr)
    {
        $numr=$db->num_rows($resqlr);
        $i=0;
        while (($i < $numr) && ($i < $nbmax))
        {
            $objr = $db->fetch_object($resqlr);
            $last_releve = $objr->num_releve;
            $i++;
            $liste='<a href="'.DOL_URL_ROOT.'/compta/bank/releve.php?account='.$_GET["account"].'&amp;num='.$objr->num_releve.'">'.$objr->num_releve.'</a> &nbsp; '.$liste;
        }
        if ($numr >= $nbmax) $liste="... &nbsp; ".$liste;
        print $liste;

        if ($numr > 0) print '<br><br>';
        else print '<b>'.$langs->trans("None").'</b><br><br>';
    }
    else
    {
        dol_print_error($db);
    }

    print '<table class="liste" width="100%">';
    print "<tr class=\"liste_titre\">\n";
    print '<td align="center">'.$langs->trans("DateOperationShort").'</td>';
    print '<td align="center">'.$langs->trans("DateValueShort").'</td>';
    print '<td>'.$langs->trans("Type").'</td>';
    print '<td>'.$langs->trans("Description").'</td>';
    print '<td align="right" width="60" nowrap>'.$langs->trans("Debit").'</td>';
    print '<td align="right" width="60" nowrap>'.$langs->trans("Credit").'</td>';
    print '<td align="center" width="40">'.$langs->trans("Action").'</td>';
    print '<td align="center">'.$langs->trans("AccountStatement").'<br>(Ex: YYYYMM)</td>';
    print "</tr>\n";


    $i = 0;
    while ($i < $num)
    {
        $objp = $db->fetch_object($resql);

        $var=!$var;
        print "<tr $bc[$var]>";
        print '<form method="post" action="rappro.php?account='.$_GET["account"].'">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print "<input type=\"hidden\" name=\"action\" value=\"rappro\">";
        print "<input type=\"hidden\" name=\"account\" value=\"".$_GET["account"]."\">";
        print "<input type=\"hidden\" name=\"rowid\" value=\"".$objp->rowid."\">";

        // Date op
        print '<td align="center" nowrap="nowrap">'.dol_print_date($db->jdate($objp->do),"day").'</td>';

        // Date value
		if (! $objp->rappro && ($user->rights->banque->modifier || $user->rights->banque->consolidate))
		{
			print '<td align="center" nowrap="nowrap">';
			print dol_print_date($db->jdate($objp->dv),"day");
			print ' &nbsp; ';
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=dvprev&amp;account='.$_GET["account"].'&amp;rowid='.$objp->rowid.'">';
			print img_edit_remove() . "</a> ";
			print '<a href="'.$_SERVER['PHP_SELF'].'?action=dvnext&amp;account='.$_GET["account"].'&amp;rowid='.$objp->rowid.'">';
			print img_edit_add() ."</a>";
			print '</td>';
		}
		else
		{
			print '<td align="center">';
			print dol_print_date($db->jdate($objp->dv),"day");
			print '</td>';
		}

		// Number
		$label=($langs->trans("PaymentType".$objp->type)!="PaymentType".$objp->type)?$langs->trans("PaymentType".$objp->type):$objp->type;  // $objp->type is a code
		if ($label=='SOLD') $label='';
		print '<td nowrap="nowrap">'.$label.($objp->num_chq?' '.$objp->num_chq:'').'</td>';

		// Description
        print '<td valign="center"><a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$objp->rowid.'&amp;account='.$acct->id.'">';
		$reg=array();
		preg_match('/\((.+)\)/i',$objp->label,$reg);	// Si texte entoure de parentheses on tente recherche de traduction
		if ($reg[1] && $langs->trans($reg[1])!=$reg[1]) print $langs->trans($reg[1]);
		else print $objp->label;
        print '</a>';

        /*
         * Ajout les liens (societe, company...)
         */
        $newline=1;
        $links = $acct->get_url($objp->rowid);
        foreach($links as $key=>$val)
        {
            if (! $newline) print ' - ';
            else print '<br>';
            if ($links[$key]['type']=='payment') {
	            $paymentstatic->id=$links[$key]['url_id'];
	            print ' '.$paymentstatic->getNomUrl(2);
                $newline=0;
            }
            elseif ($links[$key]['type']=='payment_supplier') {
				$paymentsupplierstatic->id=$links[$key]['url_id'];
				$paymentsupplierstatic->ref=$links[$key]['label'];
				print ' '.$paymentsupplierstatic->getNomUrl(1);
                $newline=0;
			}
            elseif ($links[$key]['type']=='company') {
                print '<a href="'.DOL_URL_ROOT.'/comm/fiche.php?socid='.$links[$key]['url_id'].'">';
                print img_object($langs->trans('ShowCustomer'),'company').' ';
                print dol_trunc($links[$key]['label'],24);
                print '</a>';
                $newline=0;
            }
			else if ($links[$key]['type']=='sc') {
				$chargestatic->id=$links[$key]['url_id'];
				$chargestatic->ref=$links[$key]['url_id'];
				$chargestatic->lib=$langs->trans("SocialContribution");
				print ' '.$chargestatic->getNomUrl(1);
			}
			else if ($links[$key]['type']=='payment_sc')
			{
				//print ' - ';
				/*
				print '<a href="'.DOL_URL_ROOT.'/compta/sociales/xxx.php?id='.$links[$key]['url_id'].'">';
				//print img_object($langs->trans('ShowPayment'),'payment').' ';
				print $langs->trans("SocialContributionPayment");
				print '</a>';
				*/
			}
			else if ($links[$key]['type']=='payment_vat')
			{
				$paymentvatstatic->id=$links[$key]['url_id'];
				$paymentvatstatic->ref=$links[$key]['url_id'];
				$paymentvatstatic->ref=$langs->trans("VATPayment");
				print ' '.$paymentvatstatic->getNomUrl(1);
			}
			else if ($links[$key]['type']=='banktransfert') {
				print '<a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$links[$key]['url_id'].'">';
				print img_object($langs->trans('ShowTransaction'),'payment').' ';
				print $langs->trans("TransactionOnTheOtherAccount");
				print '</a>';
			}
			else if ($links[$key]['type']=='member') {
				print '<a href="'.DOL_URL_ROOT.'/adherents/fiche.php?rowid='.$links[$key]['url_id'].'">';
				print img_object($langs->trans('ShowMember'),'user').' ';
				print $links[$key]['label'];
				print '</a>';
			}
			else {
				//print ' - ';
				print '<a href="'.$links[$key]['url'].$links[$key]['url_id'].'">';
				if (preg_match('/^\((.*)\)$/i',$links[$key]['label'],$reg))
				{
					// Label generique car entre parentheses. On l'affiche en le traduisant
					if ($reg[1]=='paiement') $reg[1]='Payment';
					print $langs->trans($reg[1]);
				}
				else
				{
					print $links[$key]['label'];
				}
				print '</a>';
                $newline=0;
            }
        }
        print '</td>';

        if ($objp->amount < 0)
        {
            print "<td align=\"right\" nowrap>".price($objp->amount * -1)."</td><td>&nbsp;</td>\n";
        }
        else
        {
            print "<td>&nbsp;</td><td align=\"right\" nowrap>".price($objp->amount)."</td>\n";
        }

        if ($objp->rappro)
        {
            // If line already reconciliated, we show receipt
            print "<td align=\"center\" nowrap=\"nowrap\"><a href=\"releve.php?num=$objp->num_releve&amp;account=$acct->id\">$objp->num_releve</a></td>";
        }
        else
        {
            // If not already reconciliated
            if ($user->rights->banque->modifier)
            {
                print '<td align="center" width="30" nowrap="nowrap">';

                print '<a href="'.DOL_URL_ROOT.'/compta/bank/ligne.php?rowid='.$objp->rowid.'&amp;account='.$acct->id.'&amp;orig_account='.$acct->id.'">';
                print img_edit();
                print '</a>&nbsp; ';

                if ($db->jdate($objp->do) <= mktime()) {
                    print '<a href="'.DOL_URL_ROOT.'/compta/bank/rappro.php?action=del&amp;rowid='.$objp->rowid.'&amp;account='.$acct->id.'">';
                    print img_delete();
                    print '</a>';
                }
                else {
                    print "&nbsp;";	// On n'empeche la suppression car le raprochement ne pourra se faire qu'apr�s la date pass�e et que l'�criture apparaisse bien sur le compte.
                }
                print "</td>";
            }
            else
            {
                print "<td align=\"center\">&nbsp;</td>";
            }
        }


        // Affiche zone saisie releve + bouton "Rapprocher"
        if ($db->jdate($objp->do) <= $now)
        {
            print '<td align="center" nowrap="nowrap">';
            print '<input class="flat" name="num_releve" type="text" value="'.$objp->num_releve.'" size="8">';
            print ' &nbsp; ';
            print "<input class=\"button\" type=\"submit\" value=\"".$langs->trans("Conciliate")."\">";
            if ($options)
            {
                print "<br><select class=\"flat\" name=\"cat\">$options";
                print "</select>";
            }
            print "</td>";
        }
        else
        {
            print '<td align="left">';
            print $langs->trans("FutureTransaction");
            print '</td>';
        }

        print "</tr>\n";
        print "</form>\n";
        $i++;
    }
    $db->free($resql);

    if ($num != 0)
    {
        print "</table><br>\n";
    }

}
else
{
  dol_print_error($db);
}

$db->close();

llxFooter('$Date: 2011/07/31 22:23:16 $ - $Revision: 1.68 $');
?>
