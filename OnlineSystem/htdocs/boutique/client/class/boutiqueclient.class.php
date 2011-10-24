<?php
/* Copyright (C) 2003 Rodolphe Quiedeville <rodolphe@quiedeville.org>
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
 * $Id: boutiqueclient.class.php,v 1.2 2011/08/03 00:45:46 eldy Exp $
 *
 */

/**
 *      \file       htdocs/boutique/client/class/boutiqueclient.class.php
 *      \brief      Classe permettant de gerer des clients de la boutique online
 *      \author	    Rodolphe Quiedeville
 *      \version    $Revision: 1.2 $
 */

/**
 *      \class      BoutiqueClient
 *      \brief      Classe permettant de gerer des clients de la boutique online
 */
class BoutiqueClient
{
    var $db ;

    var $id ;
    var $nom;

    function BoutiqueClient($DB, $id=0)
    {
        $this->db = $DB;
        $this->id = $id ;
    }

    /**
     *      \brief      Fonction permettant de recuperer les informations d'un clients de la boutique
     *      \param      id			Id du client
     */
    function fetch ($id)
    {
		global $conf;

        $sql = "SELECT customers_id, customers_lastname, customers_firstname FROM ".$conf->global->OSC_DB_NAME.".".$conf->global->OSC_DB_TABLE_PREFIX."customers WHERE customers_id = ".$id;

        $resql = $this->db->query($sql) ;
        if ( $resql )
        {
            $result = $this->db->fetch_array($resql);

            $this->id      = $result["customers_id"];
            $this->name    = $result["customers_firstname"] . " " . $result["customers_lastname"];

            $this->db->free($resql);
        	return 1;
        }
        else
        {
            print $this->db->error();
            return -1;
        }
    }

}
?>
