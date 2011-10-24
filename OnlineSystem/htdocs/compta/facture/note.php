<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *      \file       htdocs/compta/facture/note.php
 *      \ingroup    facture
 *      \brief      Fiche de notes sur une facture
 *		\version    $Id: note.php,v 1.58 2011/07/31 22:23:13 eldy Exp $
*/

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/compta/facture/class/facture.class.php");
require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');
require_once(DOL_DOCUMENT_ROOT.'/lib/invoice.lib.php');

$socid=isset($_GET["socid"])?$_GET["socid"]:isset($_POST["socid"])?$_POST["socid"]:"";

if (!$user->rights->facture->lire)
  accessforbidden();

$langs->load("companies");
$langs->load("bills");

// Security check
if ($user->societe_id > 0)
{
  unset($_GET["action"]);
  $socid = $user->societe_id;
}


$fac = new Facture($db);
$fac->fetch($_GET["facid"]);


/******************************************************************************/
/*                     Actions                                                */
/******************************************************************************/

if ($_POST["action"] == 'update_public' && $user->rights->facture->creer)
{
	$db->begin();

	$res=$fac->update_note_public($_POST["note_public"],$user);
	if ($res < 0)
	{
		$mesg='<div class="error">'.$fac->error.'</div>';
		$db->rollback();
	}
	else
	{
		$db->commit();
	}
}

if ($_POST["action"] == 'update' && $user->rights->facture->creer)
{
	$db->begin();

	$res=$fac->update_note($_POST["note"],$user);
	if ($res < 0)
	{
		$mesg='<div class="error">'.$fac->error.'</div>';
		$db->rollback();
	}
	else
	{
		$db->commit();
	}
}



/******************************************************************************/
/* Affichage fiche                                                            */
/******************************************************************************/

llxHeader();

$html = new Form($db);

$id = $_GET['facid'];
$ref= $_GET['ref'];
if ($id > 0 || ! empty($ref))
{
	$fac = new Facture($db);
	$fac->fetch($id,$ref);

	$soc = new Societe($db, $fac->socid);
    $soc->fetch($fac->socid);

    $head = facture_prepare_head($fac);
    dol_fiche_head($head, 'note', $langs->trans("InvoiceCustomer"), 0, 'bill');


    print '<table class="border" width="100%">';

	// Ref
	print '<tr><td width="20%">'.$langs->trans('Ref').'</td>';
	print '<td colspan="3">';
	$morehtmlref='';
	$discount=new DiscountAbsolute($db);
	$result=$discount->fetch(0,$fac->id);
	if ($result > 0)
	{
		$morehtmlref=' ('.$langs->trans("CreditNoteConvertedIntoDiscount",$discount->getNomUrl(1,'discount')).')';
	}
	if ($result < 0)
	{
		dol_print_error('',$discount->error);
	}
	print $html->showrefnav($fac,'ref','',1,'facnumber','ref',$morehtmlref);
	print '</td></tr>';

    // Company
    print '<tr><td>'.$langs->trans("Company").'</td>';
    print '<td colspan="3">'.$soc->getNomUrl(1,'compta').'</td>';

	// Note publique
    print '<tr><td valign="top">'.$langs->trans("NotePublic").' :</td>';
	print '<td valign="top" colspan="3">';
    if ($_GET["action"] == 'edit')
    {
        print '<form method="post" action="note.php?facid='.$fac->id.'">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="update_public">';
        print '<textarea name="note_public" cols="80" rows="8">'.$fac->note_public."</textarea><br>";
        print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
        print '</form>';
    }
    else
    {
	    print ($fac->note_public?nl2br($fac->note_public):"&nbsp;");
    }
	print "</td></tr>";

	// Note priv�e
	if (! $user->societe_id)
	{
	    print '<tr><td valign="top">'.$langs->trans("NotePrivate").' :</td>';
		print '<td valign="top" colspan="3">';
	    if ($_GET["action"] == 'edit')
	    {
	        print '<form method="post" action="note.php?facid='.$fac->id.'">';
	        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	        print '<input type="hidden" name="action" value="update">';
	        print '<textarea name="note" cols="80" rows="8">'.$fac->note."</textarea><br>";
	        print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
	        print '</form>';
	    }
		else
		{
		    print ($fac->note?nl2br($fac->note):"&nbsp;");
		}
		print "</td></tr>";
	}

    print "</table>";


    /*
    * Actions
    */
    print '</div>';
    print '<div class="tabsAction">';

    if ($user->rights->facture->creer && $_GET["action"] <> 'edit')
    {
        print "<a class=\"butAction\" href=\"note.php?facid=$fac->id&amp;action=edit\">".$langs->trans('Modify')."</a>";
    }

    print "</div>";


}

$db->close();

llxFooter('$Date: 2011/07/31 22:23:13 $ - $Revision: 1.58 $');
?>
