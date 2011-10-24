<?php
/* Copyright (C) 2001-2004 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Eric Seigne          <erics@rycks.com>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis@dolibarr.fr>
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
 *	    \file       htdocs/contact/index.php
 *      \ingroup    societe
 *		\brief      Page to list all contacts
 *		\version    $Id: index.php,v 1.106 2011/07/31 23:54:12 eldy Exp $
 */

require("../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/contact/class/contact.class.php");

$langs->load("companies");
$langs->load("suppliers");

// Security check
$contactid = isset($_GET["id"])?$_GET["id"]:'';
if ($user->societe_id) $socid=$user->societe_id;
$result = restrictedArea($user, 'contact', $contactid,'');

$search_nom=GETPOST("search_nom");
$search_prenom=GETPOST("search_prenom");
$search_societe=GETPOST("search_societe");
$search_poste=GETPOST("search_poste");
$search_phone=GETPOST("search_phone");
$search_phoneper=GETPOST("search_phoneper");
$search_phonepro=GETPOST("search_phonepro");
$search_phonemob=GETPOST("search_phonemob");
$search_fax=GETPOST("search_fax");
$search_email=GETPOST("search_email");
$search_priv=GETPOST("search_priv");

$type=GETPOST("type");
$view=GETPOST("view");

$sall=GETPOST("contactname");
$sortfield = GETPOST("sortfield");
$sortorder = GETPOST("sortorder");
$page = GETPOST("page");

if (! $sortorder) $sortorder="ASC";
if (! $sortfield) $sortfield="p.name";
if ($page < 0) { $page = 0 ; }
$limit = $conf->liste_limit;
$offset = $limit * $page ;

$langs->load("companies");
$titre=$langs->trans("ListOfContacts");
if ($type == "c")
{
	$titre=$langs->trans("ListOfContacts").'  ('.$langs->trans("ThirdPartyCustomers").')';
	$urlfiche="fiche.php";
}
if ($type == "p")
{
	$titre=$langs->trans("ListOfContacts").'  ('.$langs->trans("ThirdPartyProspects").')';
	$urlfiche="prospect/fiche.php";
}
if ($type == "f") {
	$titre=$langs->trans("ListOfContacts").' ('.$langs->trans("ThirdPartySuppliers").')';
	$urlfiche="fiche.php";
}
if ($type == "o") {
	$titre=$langs->trans("ListOfContacts").' ('.$langs->trans("OthersNotLinkedToThirdParty").')';
	$urlfiche="";
}
if ($view == 'phone')  { $text=" (Vue Telephones)"; }
if ($view == 'mail')   { $text=" (Vue EMail)"; }
if ($view == 'recent') { $text=" (Recents)"; }
$titre = $titre." $text";

if ($_POST["button_removefilter"])
{
    $search_nom="";
    $search_prenom="";
    $search_societe="";
    $search_poste="";
    $search_phone="";
    $search_phoneper="";
    $search_phonepro="";
    $search_phonemob="";
    $search_fax="";
    $search_email="";
    $search_priv="";
    $sall="";
}
if ($search_priv < 0) $search_priv='';



/*
 * View
 */

llxHeader('',$langs->trans("ContactsAddresses"),'EN:Module_Third_Parties|FR:Module_Tiers|ES:M&oacute;dulo_Empresas');

$form=new Form($db);

$sql = "SELECT s.rowid as socid, s.nom,";
$sql.= " p.rowid as cidp, p.name, p.firstname, p.poste, p.email,";
$sql.= " p.phone, p.phone_mobile, p.fax, p.fk_pays, p.priv,";
$sql.= " p.tms,";
$sql.= " cp.code as pays_code";
$sql.= " FROM ".MAIN_DB_PREFIX."socpeople as p";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_pays as cp ON cp.rowid = p.fk_pays";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON s.rowid = p.fk_soc";
if (!$user->rights->societe->client->voir && !$socid) $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
$sql.= " WHERE p.entity = ".$conf->entity;
if (!$user->rights->societe->client->voir && !$socid) //restriction
{
	$sql .= " AND (sc.fk_user = " .$user->id." OR p.fk_soc IS NULL)";
}
if ($_GET["userid"])    // propre au commercial
{
    $sql .= " AND p.fk_user_creat=".$_GET["userid"];
}

// Filter to exclude not owned private contacts
if ($search_priv != '0' && $search_priv != '1')
{
	$sql .= " AND (p.priv='0' OR (p.priv='1' AND p.fk_user_creat=".$user->id."))";
}
else
{
	if ($search_priv == '0') $sql .= " AND p.priv='0'";
	if ($search_priv == '1') $sql .= " AND (p.priv='1' AND p.fk_user_creat=".$user->id.")";
}

if ($search_nom)        // filtre sur le nom
{
    $sql .= " AND p.name like '%".$db->escape($search_nom)."%'";
}
if ($search_prenom)     // filtre sur le prenom
{
    $sql .= " AND p.firstname like '%".$db->escape($search_prenom)."%'";
}
if ($search_societe)    // filtre sur la societe
{
    $sql .= " AND s.nom like '%".$db->escape($search_societe)."%'";
}
if (strlen($search_poste))    // filtre sur la societe
{
    $sql .= " AND p.poste like '%".$db->escape($search_poste)."%'";
}
if (strlen($search_phone))
{
    $sql .= " AND (p.phone like '%".$db->escape($search_phone)."%' OR p.phone_perso like '%".$db->escape($search_phone)."%' OR p.phone_mobile like '%".$db->escape($search_phone)."%')";
}
if (strlen($search_phoneper))
{
    $sql .= " AND p.phone like '%".$db->escape($search_phoneper)."%'";
}
if (strlen($search_phonepro))
{
    $sql .= " AND p.phone_perso like '%".$db->escape($search_phonepro)."%'";
}
if (strlen($search_phonemob))
{
    $sql .= " AND p.phone_mobile like '%".$db->escape($search_phonemob)."%'";
}
if (strlen($search_fax))
{
    $sql .= " AND p.fax like '%".$db->escape($search_fax)."%'";
}
if (strlen($search_email))      // filtre sur l'email
{
    $sql .= " AND p.email like '%".$db->escape($search_email)."%'";
}
if ($type == "o")        // filtre sur type
{
    $sql .= " AND p.fk_soc IS NULL";
}
if ($type == "f")        // filtre sur type
{
    $sql .= " AND fournisseur = 1";
}
if ($type == "c")        // filtre sur type
{
    $sql .= " AND client IN (1, 3)";
}
if ($type == "p")        // filtre sur type
{
    $sql .= " AND client IN (2, 3)";
}
if ($sall)
{
    $sql .= " AND (p.name like '%".$db->escape($sall)."%' OR p.firstname like '%".$db->escape($sall)."%' OR p.email like '%".$db->escape($sall)."%') ";
}
if ($socid)
{
    $sql .= " AND s.rowid = ".$socid;
}
// Count total nb of records
$nbtotalofrecords = 0;
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST))
{
    $result = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($result);
}
// Add order and limit
if($view == "recent")
{
    $sql.= " ORDER BY p.datec DESC ";
	$sql.= " ".$db->plimit($conf->liste_limit+1, $offset);
}
else
{
    $sql.= " ORDER BY $sortfield $sortorder ";
	$sql.= " ".$db->plimit($conf->liste_limit+1, $offset);
}

//print $sql;
dol_syslog("contact/index.php sql=".$sql);
$result = $db->query($sql);
if ($result)
{
	$contactstatic=new Contact($db);

    $begin=$_GET["begin"];
    $param ='&begin='.urlencode($begin).'&view='.urlencode($view).'&userid='.urlencode($_GET["userid"]).'&contactname='.urlencode($sall);
    $param.='&type='.urlencode($type).'&view='.urlencode($view).'&search_nom='.urlencode($search_nom).'&search_prenom='.urlencode($search_prenom).'&search_societe='.urlencode($search_societe).'&search_email='.urlencode($search_email);
	if ($search_priv == '0' || $search_priv == '1') $param.="&search_priv=".urlencode($search_priv);

	$num = $db->num_rows($result);
    $i = 0;

    print_barre_liste($titre ,$page, "index.php", $param, $sortfield, $sortorder,'',$num,$nbtotalofrecords);

    print '<form method="post" action="index.php">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="view" value="'.$view.'">';
    print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
    print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';

    if ($sall)
    {
        print $langs->trans("Filter")." (".$langs->trans("Lastname").", ".$langs->trans("Firstname")." ".$langs->trans("or")." ".$langs->trans("EMail")."): ".$sall;
    }

    print '<table class="liste" width="100%">';

    // Ligne des titres
    print '<tr class="liste_titre">';
    print_liste_field_titre($langs->trans("Lastname"),"index.php","p.name", $begin, $param, '', $sortfield,$sortorder);
    print_liste_field_titre($langs->trans("Firstname"),"index.php","p.firstname", $begin, $param, '', $sortfield,$sortorder);
    print_liste_field_titre($langs->trans("PostOrFunction"),"index.php","p.poste", $begin, $param, '', $sortfield,$sortorder);
    if (empty($conf->global->SOCIETE_DISABLE_CONTACTS)) print_liste_field_titre($langs->trans("Company"),"index.php","s.nom", $begin, $param, '', $sortfield,$sortorder);
    if ($view == 'phone')
    {
        print_liste_field_titre($langs->trans("Phone"),"index.php","p.phone", $begin, $param, '', $sortfield,$sortorder);
        print_liste_field_titre($langs->trans("Mobile"),"index.php","p.phone_mob", $begin, $param, '', $sortfield,$sortorder);
        print_liste_field_titre($langs->trans("Fax"),"index.php","p.fax", $begin, $param, '', $sortfield,$sortorder);
    }
    else
    {
        print_liste_field_titre($langs->trans("Phone"),"index.php","p.phone", $begin, $param, '', $sortfield,$sortorder);
        print_liste_field_titre($langs->trans("EMail"),"index.php","p.email", $begin, $param, '', $sortfield,$sortorder);
    }
    print_liste_field_titre($langs->trans("DateModificationShort"),"index.php","p.tms", $begin, $param, 'align="center"', $sortfield,$sortorder);
    print_liste_field_titre($langs->trans("ContactVisibility"),"index.php","p.priv", $begin, $param, 'align="center"', $sortfield,$sortorder);
    print '<td class="liste_titre">&nbsp;</td>';
    print "</tr>\n";

    // Ligne des champs de filtres
    print '<tr class="liste_titre">';
    print '<td class="liste_titre">';
    print '<input class="flat" type="text" name="search_nom" size="9" value="'.$search_nom.'">';
    print '</td>';
    print '<td class="liste_titre">';
    print '<input class="flat" type="text" name="search_prenom" size="9" value="'.$search_prenom.'">';
    print '</td>';
    print '<td class="liste_titre">';
    print '<input class="flat" type="text" name="search_poste" size="9" value="'.$search_poste.'">';
    print '</td>';
    if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
    {
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_societe" size="9" value="'.$search_societe.'">';
        print '</td>';
    }
    if ($view == 'phone')
    {
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_phonepro" size="9" value="'.$search_phonepro.'">';
        print '</td>';
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_phonemob" size="9" value="'.$search_phonemob.'">';
        print '</td>';
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_fax" size="9" value="'.$search_fax.'">';
        print '</td>';
    }
    else
    {
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_phone" size="9" value="'.$search_phone.'">';
        print '</td>';
        print '<td class="liste_titre">';
        print '<input class="flat" type="text" name="search_email" size="9" value="'.$search_email.'">';
        print '</td>';
    }
	print '<td class="liste_titre">&nbsp;</td>';
	print '<td class="liste_titre" align="center">';
	$selectarray=array('0'=>$langs->trans("ContactPublic"),'1'=>$langs->trans("ContactPrivate"));
	print $form->selectarray('search_priv',$selectarray,$search_priv,1);
	print '</td>';
    print '<td class="liste_titre" align="right">';
    print '<input type="image" value="button_search" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/search.png" name="button_search" value="'.dol_escape_htmltag($langs->trans("Search")).'" title="'.dol_escape_htmltag($langs->trans("Search")).'">';
    print '&nbsp; ';
    print '<input type="image" value="button_removefilter" class="liste_titre" src="'.DOL_URL_ROOT.'/theme/'.$conf->theme.'/img/searchclear.png" name="button_removefilter" value="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'" title="'.dol_escape_htmltag($langs->trans("RemoveFilter")).'">';
    print '</td>';
    print '</tr>';

    $var=True;
    while ($i < min($num,$limit))
    {
        $obj = $db->fetch_object($result);

        $var=!$var;

        print "<tr $bc[$var]>";

		// Name
		print '<td valign="middle">';
		$contactstatic->name=$obj->name;
		$contactstatic->firstname='';
		$contactstatic->id=$obj->cidp;
		print $contactstatic->getNomUrl(1,'',20);
		print '</td>';

		// Firstname
        print '<td>'.dol_trunc($obj->firstname,20).'</td>';

		// Function
        print '<td>'.dol_trunc($obj->poste,20).'</td>';

        // Company
        if (empty($conf->global->SOCIETE_DISABLE_CONTACTS))
        {
    		print '<td>';
            if ($obj->socid)
            {
                print '<a href="'.DOL_URL_ROOT.'/comm/fiche.php?socid='.$obj->socid.'">';
                print img_object($langs->trans("ShowCompany"),"company").' '.dol_trunc($obj->nom,20).'</a>';
            }
            else
            {
                print '&nbsp;';
            }
            print '</td>';
        }

        if ($view == 'phone')
        {
            // Phone
            print '<td>'.dol_print_phone($obj->phone,$obj->pays_code,$obj->cidp,$obj->socid,'AC_TEL').'</td>';
            // Phone mobile
            print '<td>'.dol_print_phone($obj->phone_mobile,$obj->pays_code,$obj->cidp,$obj->socid,'AC_TEL').'</td>';
            // Fax
            print '<td>'.dol_print_phone($obj->fax,$obj->pays_code,$obj->cidp,$obj->socid,'AC_TEL').'</td>';
        }
        else
        {
            // Phone
            print '<td>'.dol_print_phone($obj->phone,$obj->pays_code,$obj->cidp,$obj->socid,'AC_TEL').'</td>';
            // EMail
            print '<td>'.dol_print_email($obj->email,$obj->cidp,$obj->socid,'AC_EMAIL',18).'</td>';
        }

		// Date
		print '<td align="center">'.dol_print_date($db->jdate($obj->tms),"day").'</td>';

		// Private/Public
		print '<td align="center">'.$contactstatic->LibPubPriv($obj->priv).'</td>';

		// Links Add action and Export vcard
        print '<td align="right">';
        print '<a href="'.DOL_URL_ROOT.'/comm/action/fiche.php?action=create&amp;backtopage=1&amp;contactid='.$obj->cidp.'&amp;socid='.$obj->socid.'">'.img_object($langs->trans("AddAction"),"action").'</a>';
        print ' &nbsp; ';
        print '<a href="'.DOL_URL_ROOT.'/contact/vcard.php?id='.$obj->cidp.'">';
        print img_picto($langs->trans("VCard"),'vcard.png').' ';
        print '</a></td>';

        print "</tr>\n";
        $i++;
    }

    print "</table>";

    print '</form>';

    if ($num > $limit) print_barre_liste('' ,$page, "index.php", '&amp;begin='.$begin.'&amp;view='.$view.'&amp;userid='.$_GET["userid"], $sortfield, $sortorder,'',$num,$nbtotalofrecords, '');

    $db->free($result);
}
else
{
    dol_print_error($db);
}

print '<br>';

$db->close();

llxFooter('$Date: 2011/07/31 23:54:12 $ - $Revision: 1.106 $');
?>
