<?php
/* Copyright (C) 2003-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *		\file       htdocs/includes/boxes/box_commandes.php
 *		\ingroup    commande
 *		\brief      Module de generation de l'affichage de la box commandes
 *		\version	$Id: box_commandes.php,v 1.33 2011/07/31 23:29:10 eldy Exp $
 */

include_once(DOL_DOCUMENT_ROOT."/includes/boxes/modules_boxes.php");


class box_commandes extends ModeleBoxes {

    var $boxcode="lastcustomerorders";
    var $boximg="object_order";
    var $boxlabel;
    var $depends = array("commande");

	var $db;
	var $param;

    var $info_box_head = array();
    var $info_box_contents = array();

    /**
     *      \brief      Constructeur de la classe
     */
    function box_commandes()
    {
        global $langs;
        $langs->load("boxes");

        $this->boxlabel=$langs->trans("BoxLastCustomerOrders");
    }

    /**
     *      \brief      Charge les donnees en memoire pour affichage ulterieur
     *      \param      $max        Nombre maximum d'enregistrements a charger
     */
    function loadBox($max=5)
    {
        global $user, $langs, $db, $conf;

		$this->max=$max;

		include_once(DOL_DOCUMENT_ROOT."/commande/class/commande.class.php");
        $commandestatic=new Commande($db);

        $this->info_box_head = array('text' => $langs->trans("BoxTitleLastCustomerOrders",$max));

        if ($user->rights->commande->lire)
        {

            $sql = "SELECT s.nom, s.rowid as socid,";
            $sql.= " p.ref, p.tms, p.rowid,";
            $sql.= " p.fk_statut, p.facture";
            $sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
            $sql.= ", ".MAIN_DB_PREFIX."commande as p";
            if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= ", ".MAIN_DB_PREFIX."societe_commerciaux as sc";
            $sql.= " WHERE p.fk_soc = s.rowid";
            $sql.= " AND s.entity = ".$conf->entity;
            if (!$user->rights->societe->client->voir && !$user->societe_id) $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " .$user->id;
            if ($user->societe_id) $sql.= " AND s.rowid = ".$user->societe_id;
            $sql.= " ORDER BY p.date_commande DESC, p.ref DESC ";
            $sql.= $db->plimit($max, 0);

            $result = $db->query($sql);

            if ($result)
            {
                $num = $db->num_rows($result);

                $i = 0;

                while ($i < $num)
                {
                    $objp = $db->fetch_object($result);
					$datem=$db->jdate($objp->tms);

                    $this->info_box_contents[$i][0] = array('td' => 'align="left" width="16"',
                    'logo' => $this->boximg,
                    'url' => DOL_URL_ROOT."/commande/fiche.php?id=".$objp->rowid);

                    $this->info_box_contents[$i][1] = array('td' => 'align="left"',
                    'text' => $objp->ref,
                    'url' => DOL_URL_ROOT."/commande/fiche.php?id=".$objp->rowid);

					$this->info_box_contents[$i][2] = array('td' => 'align="left" width="16"',
                    'logo' => 'company',
                    'url' => DOL_URL_ROOT."/comm/fiche.php?socid=".$objp->socid);

					$this->info_box_contents[$i][3] = array('td' => 'align="left"',
                    'text' => $objp->nom,
                    'url' => DOL_URL_ROOT."/comm/fiche.php?socid=".$objp->socid);

                    $this->info_box_contents[$i][4] = array('td' => 'align="right"',
                    'text' => dol_print_date($datem,'day'),
                    );

                    $this->info_box_contents[$i][5] = array('td' => 'align="right" width="18"',
                    'text' => $commandestatic->LibStatut($objp->fk_statut,$objp->facture,3));

                    $i++;
                }

                if ($num==0) $this->info_box_contents[$i][0] = array('td' => 'align="center"','text'=>$langs->trans("NoRecordedOrders"));
            }
            else {
                $this->info_box_contents[0][0] = array(	'td' => 'align="left"',
    	        										'maxlength'=>500,
	            										'text' => ($db->error().' sql='.$sql));
            }
        }
        else {
            $this->info_box_contents[0][0] = array('align' => 'left',
            'text' => $langs->trans("ReadPermissionNotAllowed"));
        }
    }

    function showBox($head = null, $contents = null)
    {
        parent::showBox($this->info_box_head, $this->info_box_contents);
    }

}

?>
