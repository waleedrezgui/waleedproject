<?php
/* Copyright (C) 2004      Rodolphe Quiedeville <rodolphe@quiedeville.org>
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
 *   	\file       htdocs/societe/class/client.class.php
 *		\ingroup    societe
 *		\brief      File for class of customers
 *		\version    $Id: client.class.php,v 1.4 2011/07/31 23:22:58 eldy Exp $
 */
include_once(DOL_DOCUMENT_ROOT."/societe/class/societe.class.php");


/**
 *      \class      Client
 *		\brief      Class to manage customers
 */
class Client extends Societe
{
    var $db;


    /**
     *    \brief  Constructeur de la classe
     *    \param  DB     handler acces base de donnees
     *    \param  id     id societe (0 par defaut)
     */
    function Client($DB, $id=0)
    {
        global $config;

        $this->db = $DB;
        $this->id = $id;
        $this->factures = array();

        return 0;
    }

    function read_factures()
    {
        $sql = "SELECT rowid, facnumber";
        $sql .= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql .= " WHERE f.fk_soc = ".$this->id;
        $sql .= " ORDER BY datef DESC";

        $i = 0;
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);

            while ($i < $num )
            {
                $row = $this->db->fetch_row($resql);

                $this->factures[$i][0] = $row[0];
                $this->factures[$i][1] = $row[1];

                $i++;
            }
        }
        return $result;
    }


    /**
     *      \brief      Charge indicateurs this->nb de tableaux de bord
     *      \return     int         <0 si ko, >0 si ok
     */
    function load_state_board()
    {
        global $conf, $user;

        $this->nb=array("customers" => 0,"prospects" => 0);
        $clause = "WHERE";

        $sql = "SELECT count(s.rowid) as nb, s.client";
        $sql.= " FROM ".MAIN_DB_PREFIX."societe as s";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
        {
        	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
        	$sql.= " WHERE sc.fk_user = " .$user->id;
        	$clause = "AND";
        }
        $sql.= " ".$clause." s.client in (1,2,3)";
        $sql.= " AND s.entity = ".$conf->entity;
        $sql.= " GROUP BY s.client";

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                if ($obj->client == 1 || $obj->client == 3) $this->nb["customers"]+=$obj->nb;
                if ($obj->client == 2 || $obj->client == 3) $this->nb["prospects"]+=$obj->nb;
            }
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            $this->error=$this->db->error();
            return -1;
        }

    }

}
?>
