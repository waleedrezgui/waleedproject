<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2005 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *
 * $Id: index.php,v 1.12 2011/08/08 15:28:01 eldy Exp $
 */

/**
        \file       htdocs/compta/param/comptes/index.php
        \ingroup    compta
		\brief      Page acceuil zone parametrages
		\version    $Revision: 1.12 $
*/

require("../../../main.inc.php");

$langs->load("compta");
$langs->load("bills");

/*
 * S�curit� acc�s client
 */
if ($user->societe_id > 0)
{
  $action = '';
  $socid = $user->societe_id;
}

llxHeader("","Accueil Compta");

/*
 * Affichage page
 *
 */
print_fiche_titre($langs->trans("AccountancySetup"));

print '<table border="0" width="100%" class="notopnoleftnoright">';

print '<tr><td valign="top" width="30%" class="notopnoleft">';

/*
 * Zone recherche facture
 */
print '<form method="post" action="facture.php">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';

print '<table class="noborder" width="100%">';
print "<tr class=\"liste_titre\">";
print '<td colspan="3">'.$langs->trans("SearchABill").'</td></tr>';
print "<tr $bc[0]>";
print '<td>'.$langs->trans("Ref").':</td><td><input type="text" class="flat" size="18" name="sf_ref"></td><td><input type="submit" value="'.$langs->trans("Search").'" class="button"></td></tr>';
print "</table></form><br>";



print '</td><td valign="top" width="70%" class="notopnoleftnoright">';



print '</td></tr>';

print '</table>';

$db->close();

llxFooter('$Date: 2011/08/08 15:28:01 $ - $Revision: 1.12 $');
?>
