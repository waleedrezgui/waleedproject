<?php
/* Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/sociales/charges.php
 *		\ingroup    tax
 *		\brief      Social contribution car page
 *		\version    $Id: charges.php,v 1.66 2011/08/05 21:11:50 eldy Exp $
 */

require('../../main.inc.php');
require(DOL_DOCUMENT_ROOT."/compta/sociales/class/chargesociales.class.php");

$langs->load("compta");
$langs->load("bills");

$chid=GETPOST("id");

// Security check
$socid = GETPOST("socid");
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'tax', '', '', 'charges');





/* *************************************************************************** */
/*                                                                             */
/* Actions                                                                     */
/*                                                                             */
/* *************************************************************************** */

/*
 * 	Classer paye
 */
if ($_REQUEST["action"] == 'confirm_paid' && $_REQUEST["confirm"] == 'yes')
{
	$chargesociales = new ChargeSociales($db);
	$chargesociales->fetch($chid);
	$result = $chargesociales->set_paid($user);
}

/*
 *	Suppression d'une charge sociale
 */
if ($_REQUEST["action"] == 'confirm_delete' && $_REQUEST["confirm"] == 'yes')
{
	$chargesociales=new ChargeSociales($db);
	$chargesociales->fetch($chid);
	$result=$chargesociales->delete($user);
	if ($result > 0)
	{
		Header("Location: index.php");
		exit;
	}
	else
	{
		$mesg='<div class="error">'.$chargesociales->error.'</div>';
	}
}


/*
 * Ajout d'une charge sociale
 */

if ($_POST["action"] == 'add' && $user->rights->tax->charges->creer)
{
	$dateech=@dol_mktime($_POST["echhour"],$_POST["echmin"],$_POST["echsec"],$_POST["echmonth"],$_POST["echday"],$_POST["echyear"]);
	$dateperiod=@dol_mktime($_POST["periodhour"],$_POST["periodmin"],$_POST["periodsec"],$_POST["periodmonth"],$_POST["periodday"],$_POST["periodyear"]);
	if (! $dateech)
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("DateDue")).'</div>';
		$_GET["action"] = 'create';
	}
	elseif (! $dateperiod)
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("Period")).'</div>';
		$_GET["action"] = 'create';
	}
	elseif (! $_POST["actioncode"] > 0)
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("Type")).'</div>';
		$_GET["action"] = 'create';
	}
	elseif (! $_POST["amount"])
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("Amount")).'</div>';
		$_GET["action"] = 'create';
	}
	else
	{
		$chargesociales=new ChargeSociales($db);

		$chargesociales->type=$_POST["actioncode"];
		$chargesociales->lib=$_POST["label"];
		$chargesociales->date_ech=$dateech;
		$chargesociales->periode=$dateperiod;
		$chargesociales->amount=$_POST["amount"];

		$chid=$chargesociales->create($user);
		if ($chid > 0)
		{
			//$mesg='<div class="ok">'.$langs->trans("SocialContributionAdded").'</div>';
		}
		else
		{
			$mesg='<div class="error">'.$chargesociales->error.'</div>';
		}
	}
}


if ($_GET["action"] == 'update' && ! $_POST["cancel"] && $user->rights->tax->charges->creer)
{
	$dateech=dol_mktime($_POST["echhour"],$_POST["echmin"],$_POST["echsec"],$_POST["echmonth"],$_POST["echday"],$_POST["echyear"]);
	$dateperiod=dol_mktime($_POST["periodhour"],$_POST["periodmin"],$_POST["periodsec"],$_POST["periodmonth"],$_POST["periodday"],$_POST["periodyear"]);
	if (! $dateech)
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("DateDue")).'</div>';
		$_GET["action"] = 'edit';
	}
	elseif (! $dateperiod)
	{
		$mesg='<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->transnoentities("Period")).'</div>';
		$_GET["action"] = 'edit';
	}
	else
	{
		$chargesociales=new ChargeSociales($db);
		$result=$chargesociales->fetch($_GET["id"]);

		$chargesociales->lib=$_POST["label"];
		$chargesociales->date_ech=$dateech;
		$chargesociales->periode=$dateperiod;

		$result=$chargesociales->update($user);
		if ($result > 0)
		{
			//$mesg='<div class="ok">'.$langs->trans("SocialContributionAdded").'</div>';
		}
		else
		{
			$mesg='<div class="error">'.$chargesociales->error.'</div>';
		}
	}
}



/*
 * View
 */

llxHeader('',$langs->trans("SocialContribution"));

$html = new Form($db);

// Mode creation
if ($_GET["action"] == 'create')
{
	print_fiche_titre($langs->trans("NewSocialContribution"));
	print "<br>\n";

	if ($mesg) print $mesg.'<br>';

    $var=false;

    print '<form name="charge" method="post" action="'.$_SERVER["PHP_SELF"].'">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="action" value="add">';

	print "<table class=\"noborder\" width=\"100%\">";
    print "<tr class=\"liste_titre\">";
    print '<td>';
    print '&nbsp;';
    print '</td><td align="left">';
    print $langs->trans("Label");
    print '</td><td align="left">';
    print $langs->trans("Type");
    print '</td><td align="center">';
    print $langs->trans("PeriodEndDate");
    print '</td><td align="right">';
    print $langs->trans("Amount");
    print '</td><td align="center">';
    print $langs->trans("DateDue");
    print '</td><td align="left">';
    print '&nbsp;';
    print '</td>';
    print "</tr>\n";

    print '<tr '.$bc[$var].' valign="top">';

    print '<td>&nbsp;</td>';

    // Label
    print '<td align="left"><input type="text" size="34" name="label" class="flat"></td>';

	// Type
    print '<td align="left">';
    $html->select_type_socialcontrib(isset($_POST["actioncode"])?$_POST["actioncode"]:'','actioncode',1);
    print '</td>';

	// Date end period
	print '<td align="center">';
    print $html->select_date(! empty($dateperiod)?$dateperiod:'-1', 'period', 0, 0, 0, 'charge', 1);
	print '</td>';

    print '<td align="right"><input type="text" size="6" name="amount" class="flat"></td>';

    print '<td align="center">';
    print $html->select_date(! empty($dateech)?$dateech:'-1', 'ech', 0, 0, 0, 'charge', 1);
	print '</td>';

    print '<td align="center"><input type="submit" class="button" value="'.$langs->trans("Add").'"></td>';
    print '</tr>';

    print '</table>';

    print '</form>';
}

/* *************************************************************************** */
/*                                                                             */
/* Mode fiche                                                                  */
/*                                                                             */
/* *************************************************************************** */
if ($chid > 0)
{
	$cha = new ChargeSociales($db);
    $result=$cha->fetch($chid);

	if ($result > 0)
	{
		dol_htmloutput_mesg($mesg);

		$h = 0;
		$head[$h][0] = DOL_URL_ROOT.'/compta/sociales/charges.php?id='.$cha->id;
		$head[$h][1] = $langs->trans('Card');
		$head[$h][2] = 'card';
		$h++;

		dol_fiche_head($head, 'card', $langs->trans("SocialContribution"),0,'bill');

		/*
		* Confirmation de la suppression de la charge
		*
		*/
		if ($_GET["action"] == 'paid')
		{
			$text=$langs->trans('ConfirmPaySocialContribution');
			print $html->formconfirm($_SERVER["PHP_SELF"]."?id=".$cha->id,$langs->trans('PaySocialContribution'),$text,"confirm_paid",'','',2);
		}

		if ($_GET['action'] == 'delete')
		{
			$text=$langs->trans('ConfirmDeleteSocialContribution');
			print $html->formconfirm($_SERVER['PHP_SELF'].'?id='.$cha->id,$langs->trans('DeleteSocialContribution'),$text,'confirm_delete','','',2);
		}

		if ($_GET['action'] == 'edit')
		{
			print "<form name=\"charge\" action=\"charges.php?id=$cha->id&amp;action=update\" method=\"post\">";
			print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
		}

		print '<table class="border" width="100%">';

		// Ref
		print "<tr><td>".$langs->trans("Ref").'</td><td colspan="2">';
		print $html->showrefnav($cha,'id');
		print "</td></tr>";

		// Label
		if ($_GET['action'] == 'edit')
		{
			print '<tr><td>'.$langs->trans("Label").'</td><td colspan="2">';
			print '<input type="text" name="label" size="40" value="'.$cha->lib.'">';
			print '</td></tr>';
		}
		else
		{
			print '<tr><td>'.$langs->trans("Label").'</td><td colspan="2">'.$cha->lib.'</td></tr>';
		}

		// Type
		print "<tr><td>".$langs->trans("Type")."</td><td>".$cha->type_libelle."</td><td>".$langs->trans("Payments")."</td></tr>";

		// Period end date
		print "<tr><td>".$langs->trans("PeriodEndDate")."</td>";
		print "<td>";
		if ($_GET['action'] == 'edit')
		{
			print $html->select_date($cha->periode, 'period', 0, 0, 0, 'charge', 1);
		}
		else
		{
			print dol_print_date($cha->periode,"day");
		}
		print "</td>";

		$rowspan=5;
		print '<td rowspan="'.$rowspan.'" valign="top">';

		/*
		* Paiements
		*/
		$sql = "SELECT p.rowid, p.num_paiement, datep as dp, p.amount,";
		$sql.= "c.libelle as paiement_type";
		$sql.= " FROM ".MAIN_DB_PREFIX."paiementcharge as p";
		$sql.= ", ".MAIN_DB_PREFIX."c_paiement as c ";
		$sql.= ", ".MAIN_DB_PREFIX."chargesociales as s";
		$sql.= " WHERE p.fk_charge = ".$chid;
		$sql.= " AND p.fk_charge = s.rowid";
		$sql.= " AND s.entity = ".$conf->entity;
		$sql.= " AND p.fk_typepaiement = c.id";
		$sql.= " ORDER BY dp DESC";

		//print $sql;
		$resql = $db->query($sql);
		if ($resql)
		{
			$num = $db->num_rows($resql);
			$i = 0; $total = 0;
			echo '<table class="nobordernopadding" width="100%">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("Date").'</td><td>'.$langs->trans("Type").'</td>';
			print '<td align="right">'.$langs->trans("Amount").'</td><td>&nbsp;</td></tr>';

			$var=True;
			while ($i < $num)
			{
				$objp = $db->fetch_object($resql);
				$var=!$var;
				print "<tr ".$bc[$var]."><td>";
				print '<a href="'.DOL_URL_ROOT.'/compta/payment_sc/fiche.php?id='.$objp->rowid.'">'.img_object($langs->trans("Payment"),"payment").'</a> ';
				print dol_print_date($db->jdate($objp->dp),'day')."</td>\n";
				print "<td>".$objp->paiement_type.' '.$objp->num_paiement."</td>\n";
				print '<td align="right">'.price($objp->amount)."</td><td>&nbsp;".$langs->trans("Currency".$conf->monnaie)."</td>\n";
				print "</tr>";
				$totalpaye += $objp->amount;
				$i++;
			}

			if ($cha->paye == 0)
			{
				print "<tr><td colspan=\"2\" align=\"right\">".$langs->trans("AlreadyPaid")." :</td><td align=\"right\"><b>".price($totalpaye)."</b></td><td>&nbsp;".$langs->trans("Currency".$conf->monnaie)."</td></tr>\n";
				print "<tr><td colspan=\"2\" align=\"right\">".$langs->trans("AmountExpected")." :</td><td align=\"right\" bgcolor=\"#d0d0d0\">".price($cha->amount)."</td><td bgcolor=\"#d0d0d0\">&nbsp;".$langs->trans("Currency".$conf->monnaie)."</td></tr>\n";

				$resteapayer = $cha->amount - $totalpaye;

				print "<tr><td colspan=\"2\" align=\"right\">".$langs->trans("RemainderToPay")." :</td>";
				print "<td align=\"right\" bgcolor=\"#f0f0f0\"><b>".price($resteapayer)."</b></td><td bgcolor=\"#f0f0f0\">&nbsp;".$langs->trans("Currency".$conf->monnaie)."</td></tr>\n";
			}
			print "</table>";
			$db->free($resql);
		}
		else
		{
			dol_print_error($db);
		}
		print "</td>";

		print "</tr>";

		// Due date
		if ($_GET['action'] == 'edit')
		{
			print '<tr><td>'.$langs->trans("DateDue")."</td><td>";
			print $html->select_date($cha->date_ech, 'ech', 0, 0, 0, 'charge', 1);
			print "</td></tr>";
		}
		else {
			print "<tr><td>".$langs->trans("DateDue")."</td><td>".dol_print_date($cha->date_ech,'day')."</td></tr>";
		}

		// Amount
		print '<tr><td>'.$langs->trans("AmountTTC").'</td><td>'.price($cha->amount).'</td></tr>';

		// Status
		print '<tr><td>'.$langs->trans("Status").'</td><td>'.$cha->getLibStatut(4).'</td></tr>';

		print '<tr><td colspan="2">&nbsp;</td></tr>';

		if ($_GET['action'] == 'edit')
		{
			print '<tr><td colspan="3" align="center">';
			print '<input type="submit" class="button" name="save" value="'.$langs->trans("Save").'">';
			print ' &nbsp; ';
			print '<input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'">';
			print '</td></tr>';
		}

		print '</table>';

		if ($_GET['action'] == 'edit') print "</form>\n";

		print '</div>';


		/*
		*   Boutons actions
		*/
		if ($_GET["action"] != 'edit')
		{
			print "<div class=\"tabsAction\">\n";

			// Edit
			if ($user->rights->tax->charges->creer)
			{
				print "<a class=\"butAction\" href=\"".DOL_URL_ROOT."/compta/sociales/charges.php?id=$cha->id&amp;action=edit\">".$langs->trans("Modify")."</a>";
			}

			// Emettre paiement
			if ($cha->paye == 0 && ((price2num($cha->amount) < 0 && round($resteapayer) < 0) || (price2num($cha->amount) > 0 && round($resteapayer) > 0)) && $user->rights->tax->charges->creer)
			{
				print "<a class=\"butAction\" href=\"".DOL_URL_ROOT."/compta/paiement_charge.php?id=$cha->id&amp;action=create\">".$langs->trans("DoPayment")."</a>";
			}

			// Classify 'paid'
			if ($cha->paye == 0 && round($resteapayer) <=0 && $user->rights->tax->charges->creer)
			{
				print "<a class=\"butAction\" href=\"".DOL_URL_ROOT."/compta/sociales/charges.php?id=$cha->id&amp;action=paid\">".$langs->trans("ClassifyPaid")."</a>";
			}

			// Delete
			if ($user->rights->tax->charges->supprimer)
			{
				print "<a class=\"butActionDelete\" href=\"".DOL_URL_ROOT."/compta/sociales/charges.php?id=$cha->id&amp;action=delete\">".$langs->trans("Delete")."</a>";
			}

			print "</div>";
		}
	}
	else
	{
		/* Charge non trouv� */
		dol_print_error('',$cha->error);
	}
}

$db->close();

llxFooter('$Date: 2011/08/05 21:11:50 $ - $Revision: 1.66 $');
?>
