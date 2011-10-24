<?php
/* Copyright (C) 2003-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
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
 *      \file       htdocs/core/class/notify.class.php
 *      \ingroup    notification
 *      \brief      File of class to manage notifications
 *      \version    $Id: notify.class.php,v 1.13 2011/07/31 23:45:14 eldy Exp $
 */
require_once(DOL_DOCUMENT_ROOT ."/lib/CMailFile.class.php");


/**
 *      \class      Notify
 *      \brief      Classe de gestion des notifications
 */
class Notify
{
    var $id;
    var $db;
    var $error;

    var $author;
    var $ref;
    var $date;
    var $duree;
    var $note;
    var $fk_project;

	// Les codes actions sont definis dans la table llx_notify_def
	// \todo utiliser des codes texte plutot que numerique
	// 1 = Validation fiche inter
	// 2 = Validation facture

    /**
     *    \brief      Constructeur
     *    \param      DB      Handler acces base
     */
    function Notify($DB)
    {
        $this->db = $DB ;
    }


    /**
     *    	\brief      Renvoie le message signalant les notifications qui auront lieu sur
     *					un evenement pour affichage dans texte de confirmation evenement.
     * 		\param		action		Id of action in llx_c_action_trigger
     * 		\param		socid		Id of third party
     *		\return		string		Message
     */
	function confirmMessage($action,$socid)
	{
		global $langs;
		$langs->load("mails");

		$nb=$this->countDefinedNotifications($action,$socid);
		if ($nb <= 0) $texte=img_object($langs->trans("Notifications"),'email').' '.$langs->trans("NoNotificationsWillBeSent");
		if ($nb == 1) $texte=img_object($langs->trans("Notifications"),'email').' '.$langs->trans("ANotificationsWillBeSent");
		if ($nb >= 2) $texte=img_object($langs->trans("Notifications"),'email').' '.$langs->trans("SomeNotificationsWillBeSent",$nb);
		return $texte;
	}

    /**
     *    	\brief      Return number of notifications activated for action code and third party
     * 		\param		action		Code of action in llx_c_action_trigger (new usage) or Id of action in llx_c_action_trigger (old usage)
     * 		\param		socid		Id of third party
     * 		\return		int			<0 si ko, sinon nombre de notifications definies
     */
	function countDefinedNotifications($action,$socid)
	{
		global $conf;

        $num=-1;

        $sql = "SELECT n.rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."notify_def as n,";
        $sql.= " ".MAIN_DB_PREFIX."socpeople as c,";
        $sql.= " ".MAIN_DB_PREFIX."c_action_trigger as a,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE n.fk_contact = c.rowid";
        $sql.= " AND a.rowid = n.fk_action";
        $sql.= " AND n.fk_soc = s.rowid";
        if (is_numeric($action)) $sql.= " AND n.fk_action = ".$action;	// Old usage
        else $sql.= " AND a.code = '".$action."'";	// New usage
        $sql.= " AND a.entity = ".$conf->entity;
        $sql.= " AND s.rowid = ".$socid;

		dol_syslog("Notify.class::countDefinedNotifications $action, $socid");

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
		}
		else
		{
			$this->error=$this->db->error.' sql='.$sql;
			return -1;
		}

		return $num;
	}

    /**
     *    	\brief      Check if notification are active for couple action/company.
     * 					If yes, send mail and save trace into llx_notify.
     * 		\param		action		Code of action in llx_c_action_trigger (new usage) or Id of action in llx_c_action_trigger (old usage)
     * 		\param		socid		Id of third party
     * 		\param		texte		Message to send
     * 		\param		objet_type	Type of object the notification deals on (facture, order, propal, order_supplier...). Just for log in llx_notify.
     * 		\param		objet_id	Id of object the notification deals on
     * 		\param		file		Attach a file
     *		\return		int			<0 if KO, or number of changes if OK
     */
    function send($action, $socid, $texte, $objet_type, $objet_id, $file="")
    {
        global $conf,$langs,$mysoc,$dolibarr_main_url_root;

        $langs->load("other");

		dol_syslog("Notify::send action=$action, socid=$socid, texte=$texte, objet_type=$objet_type, objet_id=$objet_id, file=$file");

		$sql = "SELECT s.nom, c.email, c.rowid as cid, c.name, c.firstname,";
		$sql.= " a.rowid as adid, a.label, a.code, n.rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."socpeople as c,";
        $sql.= " ".MAIN_DB_PREFIX."c_action_trigger as a,";
        $sql.= " ".MAIN_DB_PREFIX."notify_def as n,";
        $sql.= " ".MAIN_DB_PREFIX."societe as s";
        $sql.= " WHERE n.fk_contact = c.rowid AND a.rowid = n.fk_action";
        $sql.= " AND n.fk_soc = s.rowid";
        if (is_numeric($action)) $sql.= " AND n.fk_action = ".$action;	// Old usage
        else $sql.= " AND a.code = '".$action."'";	// New usage
        $sql .= " AND s.rowid = ".$socid;

		dol_syslog("Notify::send sql=".$sql);
        $result = $this->db->query($sql);
        if ($result)
        {
            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num)	// For each notification couple defined (third party/actioncode)
            {
                $obj = $this->db->fetch_object($result);

                $sendto = $obj->firstname . " " . $obj->name . " <".$obj->email.">";
				$actiondefid = $obj->adid;

                if (dol_strlen($sendto))
                {
                	include_once(DOL_DOCUMENT_ROOT.'/lib/files.lib.php');
                	$application=($conf->global->MAIN_APPLICATION_TITLE?$conf->global->MAIN_APPLICATION_TITLE:'Dolibarr ERP/CRM');

                	$subject = '['.$application.'] '.$langs->transnoentitiesnoconv("DolibarrNotification");

                	$message = $langs->transnoentities("YouReceiveMailBecauseOfNotification",$application,$mysoc->name)."\n";
                	$message.= $langs->transnoentities("YouReceiveMailBecauseOfNotification2",$application,$mysoc->name)."\n";
                	$message.= "\n";
                    $message.= $texte;
                    // Add link
                    switch($objet_type)
                    {
                    	case 'ficheinter':
						    $link=DOL_URL_ROOT.'/fichinter/fiche.php?id='.$objet_id;
    						break;
                    	case 'propal':
						    $link=DOL_URL_ROOT.'/comm/propal.php?id='.$objet_id;
    						break;
    					case 'facture':
						    $link=DOL_URL_ROOT.'/compta/facture.php?facid='.$objet_id;
    						break;
                    	case 'order':
						    $link=DOL_URL_ROOT.'/commande/fiche.php?facid='.$objet_id;
    						break;
    					case 'order_supplier':
						    $link=DOL_URL_ROOT.'/fourn/commande/fiche.php?facid='.$objet_id;
    						break;
                    }
                    $urlwithouturlroot=preg_replace('/'.preg_quote(DOL_URL_ROOT,'/').'$/i','',$dolibarr_main_url_root);
                    if ($link) $message.="\n".$urlwithouturlroot.$link;

                    $filename = basename($file);

                    $mimefile=dol_mimetype($file);

                    $msgishtml=0;

                    $replyto = $conf->notification->email_from;

                    $mailfile = new CMailFile($subject,
	                    $sendto,
	                    $replyto,
	                    $message,
	                    array($file),
	                    array($mimefile),
	                    array($filename[sizeof($filename)-1]),
	                    '', '', 0, $msgishtml
	                    );

                    if ( $mailfile->sendfile() )
                    {
                        $sendto = htmlentities($sendto);

                        $sql = "INSERT INTO ".MAIN_DB_PREFIX."notify (daten, fk_action, fk_contact, objet_type, objet_id, email)";
                        $sql.= " VALUES (".$this->db->idate(mktime()).", ".$actiondefid." ,".$obj->cid." , '".$objet_type."', ".$objet_id.", '".$this->db->escape($obj->email)."')";
                        dol_syslog("Notify::send sql=".$sql);
                        if (! $this->db->query($sql) )
                        {
                            dol_print_error($this->db);
                        }
                    }
                    else
                    {
                        $this->error=$mailfile->error;
                        //dol_syslog("Notify::send ".$this->error, LOG_ERR);
                    }
                }
                $i++;
            }
            return $i;
        }
        else
        {
            $this->error=$this->db->error();
            return -1;
        }

    }

}

?>
