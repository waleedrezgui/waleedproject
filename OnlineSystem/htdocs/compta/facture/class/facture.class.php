<?php
/* Copyright (C) 2002-2007 Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio   <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier        <benoit.mortier@opensides.be>
 * Copyright (C) 2005      Marc Barilley / Ocebo <marc@ocebo.com>
 * Copyright (C) 2005-2011 Regis Houssin         <regis@dolibarr.fr>
 * Copyright (C) 2006      Andre Cianfarani      <acianfa@free.fr>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2010-2011 Juanjo Menent         <jmenent@2byte.es>
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
 *	\file       htdocs/compta/facture/class/facture.class.php
 *	\ingroup    facture
 *	\brief      Fichier de la classe des factures clients
 *	\version    $Id: facture.class.php,v 1.124 2011/08/03 00:46:25 eldy Exp $
 */

require_once(DOL_DOCUMENT_ROOT ."/core/class/commonobject.class.php");
require_once(DOL_DOCUMENT_ROOT ."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT ."/societe/class/client.class.php");


/**
 *	\class      Facture
 *	\brief      Classe permettant la gestion des factures clients
 */
class Facture extends CommonObject
{
    var $db;
    var $error;
    var $errors=array();
    var $element='facture';
    var $table_element='facture';
    var $table_element_line = 'facturedet';
    var $fk_element = 'fk_facture';
    var $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

    var $id;
    //! Id client
    var $socid;
    //! Objet societe client (to load with fetch_client method)
    var $client;
    var $author;
    var $fk_user_author;
    var $fk_user_valid;
    //! Invoice date
    var $date;				// Invoice date
    var $date_creation;		// Creation date
    var $date_validation;	// Validation date
    var $datem;
    var $ref;
    var $ref_client;
    var $ref_ext;
    var $ref_int;
    //! 0=Standard invoice, 1=Replacement invoice, 2=Credit note invoice, 3=Deposit invoice, 4=Proforma invoice
    var $type;
    var $amount;
    var $remise;
    var $total_ht;
    var $total_tva;
    var $total_ttc;
    var $note;
    var $note_public;
    //! 0=draft,
    //! 1=validated (need to be paid),
    //! 2=classified paid partially (close_code='discount_vat','badcustomer') or completely (close_code=null),
    //! 3=classified abandoned and no payment done (close_code='badcustomer','abandon' ou 'replaced')
    var $statut;
    //! Fermeture apres paiement partiel: discount_vat, badcustomer, abandon
    //! Fermeture alors que aucun paiement: replaced (si remplace), abandon
    var $close_code;
    //! Commentaire si mis a paye sans paiement complet
    var $close_note;
    //! 1 if invoice paid COMPLETELY, 0 otherwise (do not use it anymore, use statut and close_code
    var $paye;
    //! id of source invoice if replacement invoice or credit note
    var $fk_facture_source;
    var $origin;
    var $origin_id;
    var $fk_project;
    var $date_lim_reglement;
    var $cond_reglement_id;			// Id in llx_c_paiement
    var $cond_reglement_code;		// Code in llx_c_paiement
    var $mode_reglement_id;			// Id in llx_c_paiement
    var $mode_reglement_code;		// Code in llx_c_paiement
    var $modelpdf;
    var $products=array();	// TODO deprecated
    var $lines=array();
    var $line;
    //! Pour board
    var $nbtodo;
    var $nbtodolate;
    var $specimen;
    //! Numero d'erreur de 512 a 1023
    var $errno = 0;

    /**
     *	\brief  Constructeur de la classe
     *	\param  DB         	handler acces base de donnees
     *	\param  socid		id societe ('' par defaut)
     *	\param  facid      	id facture ('' par defaut)
     */
    function Facture($DB, $socid='', $facid='')
    {
        $this->db = $DB;

        $this->id = $facid;
        $this->socid = $socid;

        $this->amount = 0;
        $this->remise = 0;
        $this->remise_percent = 0;
        $this->total_ht = 0;
        $this->total_tva = 0;
        $this->total_ttc = 0;
        $this->propalid = 0;
        $this->fk_project = 0;
        $this->remise_exceptionnelle = 0;
    }

    /**
     *	Create invoice in database
     *  Note: this->ref can be set or empty. If empty, we will use "(PROV)"
     *	@param     	user       		Object user that create
     *	@param      notrigger		1=Does not execute triggers, 0 otherwise
     * 	@param		forceduedate	1=Do not recalculate due date from payment condition but force it with value
     *	@return		int				<0 if KO, >0 if OK
     */
    function create($user,$notrigger=0,$forceduedate=0)
    {
        global $langs,$conf,$mysoc;
        $error=0;

        // Clean parameters
        if (! $this->type) $this->type = 0;
        $this->ref_client=trim($this->ref_client);
        $this->note=trim($this->note);
        $this->note_public=trim($this->note_public);
        if (! $this->remise) $this->remise = 0;
        if (! $this->cond_reglement_id) $this->cond_reglement_id = 0;
        if (! $this->mode_reglement_id) $this->mode_reglement_id = 0;
        $this->brouillon = 1;

        dol_syslog("Facture::Create user=".$user->id);

        // Check parameters
        if (empty($this->date) || empty($user->id))
        {
            $this->error="ErrorBadParameter";
            dol_syslog("Facture::create Try to create an invoice with an empty parameter (user, date, ...)", LOG_ERR);
            return -3;
        }
        $soc = new Societe($this->db);
        $result=$soc->fetch($this->socid);
        if ($result < 0)
        {
            $this->error="Failed to fetch company";
            dol_syslog("Facture::create ".$this->error, LOG_ERR);
            return -2;
        }

        $now=dol_now();

        $this->db->begin();

        // Create invoice from a predefined invoice
        if ($this->fac_rec > 0)
        {
            require_once(DOL_DOCUMENT_ROOT.'/compta/facture/class/facture-rec.class.php');
            $_facrec = new FactureRec($this->db);
            $result=$_facrec->fetch($this->fac_rec);

            $this->fk_project        = $_facrec->fk_project;
            $this->cond_reglement    = $_facrec->cond_reglement_id;
            $this->cond_reglement_id = $_facrec->cond_reglement_id;
            $this->mode_reglement    = $_facrec->mode_reglement_id;
            $this->mode_reglement_id = $_facrec->mode_reglement_id;
            $this->amount            = $_facrec->amount;
            $this->remise_absolue    = $_facrec->remise_absolue;
            $this->remise_percent    = $_facrec->remise_percent;
            $this->remise		     = $_facrec->remise;

            // Clean parametres
            if (! $this->type) $this->type = 0;
            $this->ref_client=trim($this->ref_client);
            $this->note=trim($this->note);
            $this->note_public=trim($this->note_public);
            if (! $this->remise) $this->remise = 0;
            if (! $this->mode_reglement_id) $this->mode_reglement_id = 0;
            $this->brouillon = 1;
        }

        // Define due date if not already defined
        $datelim=(empty($forceduedate)?$this->calculate_date_lim_reglement():$forceduedate);

        // Insert into database
        $socid  = $this->socid;
        $amount = $this->amount;
        $remise = $this->remise;

        $totalht = ($amount - $remise);

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."facture (";
        $sql.= " facnumber";
        $sql.= ", entity";
        $sql.= ", type";
        $sql.= ", fk_soc";
        $sql.= ", datec";
        $sql.= ", amount";
        $sql.= ", remise_absolue";
        $sql.= ", remise_percent";
        $sql.= ", datef";
        $sql.= ", note";
        $sql.= ", note_public";
        $sql.= ", ref_client, ref_int";
        $sql.= ", fk_facture_source, fk_user_author, fk_projet";
        $sql.= ", fk_cond_reglement, fk_mode_reglement, date_lim_reglement, model_pdf";
        $sql.= ")";
        $sql.= " VALUES (";
        $sql.= "'(PROV)'";
        $sql.= ", ".$conf->entity;
        $sql.= ", '".$this->type."'";
        $sql.= ", '".$socid."'";
        $sql.= ", '".$this->db->idate($now)."'";
        $sql.= ", '".$totalht."'";
        $sql.= ",".($this->remise_absolue>0?$this->remise_absolue:'NULL');
        $sql.= ",".($this->remise_percent>0?$this->remise_percent:'NULL');
        $sql.= ", '".$this->db->idate($this->date)."'";
        $sql.= ",".($this->note?"'".$this->db->escape($this->note)."'":"null");
        $sql.= ",".($this->note_public?"'".$this->db->escape($this->note_public)."'":"null");
        $sql.= ",".($this->ref_client?"'".$this->db->escape($this->ref_client)."'":"null");
        $sql.= ",".($this->ref_int?"'".$this->db->escape($this->ref_int)."'":"null");
        $sql.= ",".($this->fk_facture_source?"'".$this->db->escape($this->fk_facture_source)."'":"null");
        $sql.= ",".($user->id > 0 ? "'".$user->id."'":"null");
        $sql.= ",".($this->fk_project?$this->fk_project:"null");
        $sql.= ','.$this->cond_reglement_id;
        $sql.= ",".$this->mode_reglement_id;
        $sql.= ", '".$this->db->idate($datelim)."', '".$this->modelpdf."')";

        dol_syslog("Facture::Create sql=".$sql);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.'facture');

            // Update ref with new one
            $this->ref='(PROV'.$this->id.')';
            $sql = 'UPDATE '.MAIN_DB_PREFIX."facture SET facnumber='".$this->ref."' WHERE rowid=".$this->id;

            dol_syslog("Facture::create sql=".$sql);
            $resql=$this->db->query($sql);
            if (! $resql) $error++;

            // Add object linked
            if (! $error && $this->id && $this->origin && $this->origin_id)
            {
                $ret = $this->add_object_linked();
                if (! $ret)
                {
                    dol_print_error($this->db);
                    $error++;
                }
            }

            /*
             *  Insert lines of invoices into database
             */
            if (sizeof($this->lines) && is_object($this->lines[0]))
            {
            	$fk_parent_line = 0;

                dol_syslog("There is ".sizeof($this->lines)." lines that are invoice lines objects");
                foreach ($this->lines as $i => $val)
                {
                    $newinvoiceline=new FactureLigne($this->db);
                    $newinvoiceline=$this->lines[$i];
                    $newinvoiceline->fk_facture=$this->id;
                    if ($result >= 0 && ($newinvoiceline->info_bits & 0x01) == 0)	// We keep only lines with first bit = 0
                    {
                    	// Reset fk_parent_line for no child products and special product
						if (($newinvoiceline->product_type != 9 && empty($newinvoiceline->fk_parent_line)) || $newinvoiceline->product_type == 9) {
							$fk_parent_line = 0;
						}

                    	$newinvoiceline->fk_parent_line=$fk_parent_line;
						$result=$newinvoiceline->insert();

                    	// Defined the new fk_parent_line
						if ($result > 0 && $newinvoiceline->product_type == 9) {
							$fk_parent_line = $result;
						}
                    }
                    if ($result < 0)
                    {
                        $this->error=$newinvoiceline->error;
                        $error++;
                        break;
                    }
                }
            }
            else
            {
            	$fk_parent_line = 0;

                dol_syslog("There is ".sizeof($this->lines)." lines that are array lines");
                foreach ($this->lines as $i => $val)
                {
                    if (($this->lines[$i]->info_bits & 0x01) == 0)	// We keep only lines with first bit = 0
                    {
	                    // Reset fk_parent_line for no child products and special product
						if (($this->lines[$i]->product_type != 9 && empty($this->lines[$i]->fk_parent_line)) || $this->lines[$i]->product_type == 9) {
							$fk_parent_line = 0;
						}

                        $result = $this->addline(
                        $this->id,
                        $this->lines[$i]->desc,
                        $this->lines[$i]->subprice,
                        $this->lines[$i]->qty,
                        $this->lines[$i]->tva_tx,
                        $this->lines[$i]->localtax1_tx,
                        $this->lines[$i]->localtax2_tx,
                        $this->lines[$i]->fk_product,
                        $this->lines[$i]->remise_percent,
                        $this->lines[$i]->date_start,
                        $this->lines[$i]->date_end,
                        $this->lines[$i]->fk_code_ventilation,
                        $this->lines[$i]->info_bits,
                        $this->lines[$i]->fk_remise_except,
    					'HT',
                        0,
                        $this->lines[$i]->product_type,
                        $this->lines[$i]->rang,
                        $this->lines[$i]->special_code,
                        '',
                        0,
                        $fk_parent_line
                        );
                        if ($result < 0)
                        {
                            $this->error=$this->db->lasterror();
                            dol_print_error($this->db);
                            $this->db->rollback();
                            return -1;
                        }

	                    // Defined the new fk_parent_line
						if ($result > 0 && $this->lines[$i]->product_type == 9) {
							$fk_parent_line = $result;
						}
                    }
                }
            }

            /*
             * Insert lines of predefined invoices
             */
            if (! $error && $this->fac_rec > 0)
            {
                foreach ($_facrec->lines as $i => $val)
                {
                    if ($_facrec->lines[$i]->fk_product)
                    {
                        $prod = new Product($this->db, $_facrec->lines[$i]->fk_product);
                        $res=$prod->fetch($_facrec->lines[$i]->fk_product);
                    }
                    $tva_tx = get_default_tva($mysoc,$soc,$prod->id);
                    $localtax1_tx=get_localtax($tva_tx,1,$soc);
                    $localtax2_tx=get_localtax($tva_tx,2,$soc);

                    $result_insert = $this->addline(
                    $this->id,
                    $_facrec->lines[$i]->desc,
                    $_facrec->lines[$i]->subprice,
                    $_facrec->lines[$i]->qty,
                    $tva_tx,
                    $localtax1_tx,
                    $localtax2_tx,
                    $_facrec->lines[$i]->fk_product,
                    $_facrec->lines[$i]->remise_percent,
					'','',0,0,'','HT',0,
                    $_facrec->lines[$i]->product_type,
                    $_facrec->lines[$i]->rang,
                    $_facrec->lines[$i]->special_code
                    );

                    if ( $result_insert < 0)
                    {
                        $error++;
                        $this->error=$this->db->error();
                        break;
                    }
                }
            }

            if (! $error)
            {
                $result=$this->update_price(1);
                if ($result > 0)
                {
                    // Appel des triggers
                    include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                    $interface=new Interfaces($this->db);
                    $result=$interface->run_triggers('BILL_CREATE',$this,$user,$langs,$conf);
                    if ($result < 0) { $error++; $this->errors=$interface->errors; }
                    // Fin appel triggers

                    if (! $error)
                    {
                        $this->db->commit();
                        return $this->id;
                    }
                    else
                    {
                        $this->db->rollback();
                        return -4;
                    }
                }
                else
                {
                    $this->error=$langs->trans('FailedToUpdatePrice');
                    $this->db->rollback();
                    return -3;
                }
            }
            else
            {
                dol_syslog("Facture::create error ".$this->error, LOG_ERR);
                $this->db->rollback();
                return -2;
            }
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("Facture::create error ".$this->error." sql=".$sql, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }


    /**
     *	Create a new invoice in database from current invoice
     *	@param      user    		Object user that ask creation
     *	@param		invertdetail	Reverse sign of amounts for lines
     *	@return		int				<0 if KO, >0 if OK
     */
    function createFromCurrent($user,$invertdetail=0)
    {
        // Charge facture source
        $facture=new Facture($this->db);

        $facture->fk_facture_source = $this->fk_facture_source;
        $facture->type 			    = $this->type;
        $facture->socid 		    = $this->socid;
        $facture->date              = $this->date;
        $facture->note_public       = $this->note_public;
        $facture->note              = $this->note;
        $facture->ref_client        = $this->ref_client;
        $facture->modelpdf          = $this->modelpdf;
        $facture->fk_project        = $this->fk_project;
        $facture->cond_reglement_id = $this->cond_reglement_id;
        $facture->mode_reglement_id = $this->mode_reglement_id;
        $facture->amount            = $this->amount;
        $facture->remise_absolue    = $this->remise_absolue;
        $facture->remise_percent    = $this->remise_percent;

        $facture->lines		    	= $this->lines;	// Tableau des lignes de factures
        $facture->products		    = $this->lines;	// Tant que products encore utilise

        // Loop on each line of new invoice
        foreach($facture->lines as $i => $line)
        {
            if ($invertdetail)
            {
                $facture->lines[$i]->subprice  = -$facture->lines[$i]->subprice;
                $facture->lines[$i]->price     = -$facture->lines[$i]->price;
                $facture->lines[$i]->total_ht  = -$facture->lines[$i]->total_ht;
                $facture->lines[$i]->total_tva = -$facture->lines[$i]->total_tva;
                $facture->lines[$i]->total_localtax1 = -$facture->lines[$i]->total_localtax1;
                $facture->lines[$i]->total_localtax2 = -$facture->lines[$i]->total_localtax2;
                $facture->lines[$i]->total_ttc = -$facture->lines[$i]->total_ttc;
            }
        }

        dol_syslog("Facture::createFromCurrent invertdetail=".$invertdetail." socid=".$this->socid." nboflines=".sizeof($facture->lines));

        $facid = $facture->create($user);
        if ($facid <= 0)
        {
            $this->error=$facture->error;
            $this->errors=$facture->errors;
        }

        return $facid;
    }


    /**
     *		Load an object from its id and create a new one in database
     *		@param      fromid     		Id of object to clone
     *		@param		invertdetail	Reverse sign of amounts for lines
     * 	 	@return		int				New id of clone
     */
    function createFromClone($fromid,$invertdetail=0)
    {
        global $conf,$user,$langs;

        $error=0;

        // Load source object
        $objFrom=new Facture($this->db);
        $objFrom->fetch($fromid);

        // Load new object
        $object=new Facture($this->db);
        $object->fetch($fromid);

        // Instantiate hooks of thirdparty module
        if (is_array($conf->hooks_modules) && ! empty($conf->hooks_modules))
        {
            $object->callHooks('invoicecard');
        }

        $this->db->begin();

        $object->id=0;
        $object->statut=0;

        // Clear fields
        $object->user_author        = $user->id;
        $object->user_valid         = '';
        $object->fk_facture_source  = 0;
        $object->date_creation      = '';
        $object->date_validation    = '';
        $object->ref_client         = '';
        $object->close_code         = '';
        $object->close_note         = '';
        $object->products = $object->lines;	// Tant que products encore utilise

        // Loop on each line of new invoice
        foreach($object->lines as $i => $line)
        {
            if (($object->lines[$i]->info_bits & 0x02) == 0x02)	// We do not clone line of discounts
            {
                unset($object->lines[$i]);
                unset($object->products[$i]);	// Tant que products encore utilise
            }
        }

        // Create clone
        $result=$object->create($user);

        // Other options
        if ($result < 0)
        {
            $this->error=$object->error;
            $error++;
        }

        if (! $error)
        {
            // Hook for external modules
            if (! empty($object->hooks))
            {
            	foreach($object->hooks as $hook)
            	{
            		if (! empty($hook['modules']))
            		{
            			foreach($hook['modules'] as $module)
            			{
            				if (method_exists($module,'createfrom'))
            				{
            					$result = $module->createfrom($objFrom,$result,$object->element);
            					if ($result < 0) $error++;
            				}
            			}
            		}
            	}
            }

            // Appel des triggers
            include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('BILL_CLONE',$object,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }

        // End
        if (! $error)
        {
            $this->db->commit();
            return $object->id;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *      Load an object from an order and create a new invoice into database
     *      @param      object          Object source
     *      @return     int             <0 if KO, 0 if nothing done, 1 if OK
     */
    function createFromOrder($object)
    {
        global $conf,$user,$langs;

        $error=0;

        // Closed order
        $this->date = dol_now();
        $this->source = 0;

        for ($i = 0 ; $i < sizeof($object->lines) ; $i++)
        {
            $line = new FactureLigne($this->db);

            $line->libelle           = $object->lines[$i]->libelle;
            $line->desc              = $object->lines[$i]->desc;
            $line->price             = $object->lines[$i]->price;
            $line->subprice          = $object->lines[$i]->subprice;
            $line->tva_tx            = $object->lines[$i]->tva_tx;
            $line->localtax1_tx      = $object->lines[$i]->localtax1_tx;
            $line->localtax2_tx      = $object->lines[$i]->localtax2_tx;
            $line->qty               = $object->lines[$i]->qty;
            $line->fk_remise_except  = $object->lines[$i]->fk_remise_except;
            $line->remise_percent    = $object->lines[$i]->remise_percent;
            $line->fk_product        = $object->lines[$i]->fk_product;
            $line->info_bits         = $object->lines[$i]->info_bits;
            $line->product_type      = $object->lines[$i]->product_type;
            $line->rang              = $object->lines[$i]->rang;
            $line->special_code      = $object->lines[$i]->special_code;
            $line->fk_parent_line    = $object->lines[$i]->fk_parent_line;

            $this->lines[$i] = $line;
        }

        $this->socid                = $object->socid;
        $this->fk_project           = $object->fk_project;
        $this->cond_reglement_id    = $object->cond_reglement_id;
        $this->mode_reglement_id    = $object->mode_reglement_id;
        $this->availability_id      = $object->availability_id;
        $this->demand_reason_id     = $object->demand_reason_id;
        $this->date_livraison       = $object->date_livraison;
        $this->fk_delivery_address  = $object->fk_delivery_address;
        $this->contact_id           = $object->contactid;
        $this->ref_client           = $object->ref_client;
        $this->note                 = $object->note;
        $this->note_public          = $object->note_public;

        $this->origin      = $object->element;
        $this->origin_id   = $object->id;

        $ret = $this->create($user);

        if ($ret > 0)
        {
        	// Hook for external modules
            if (! empty($object->hooks))
            {
            	foreach($object->hooks as $hook)
            	{
            		if (! empty($hook['modules']))
            		{
            			foreach($hook['modules'] as $module)
            			{
            				if (method_exists($module,'createfrom'))
            				{
            					$result = $module->createfrom($objFrom,$result,$object->element);
            					if ($result < 0) $error++;
            				}
            			}
            		}
            	}
            }

            if (! $error)
            {
                return 1;
            }
            else return -1;
        }
        else return -1;
    }

    /**
     *      Return clicable link of object (with eventually picto)
     *      @param      withpicto       Add picto into link
     *      @param      option          Where point the link
     *      @param      max             Maxlength of ref
     *      @return     string          String with URL
     */
    function getNomUrl($withpicto=0,$option='',$max=0,$short=0)
    {
        global $langs;

        $result='';

        if ($option == 'withdraw') $url = DOL_URL_ROOT.'/compta/facture/prelevement.php?facid='.$this->id;
        else $url = DOL_URL_ROOT.'/compta/facture.php?facid='.$this->id;

        if ($short) return $url;

        $linkstart='<a href="'.$url.'">';
        $linkend='</a>';

        $picto='bill';
        if ($this->type == 1) $picto.='r';	// Replacement invoice
        if ($this->type == 2) $picto.='a';	// Credit note
        if ($this->type == 3) $picto.='d';	// Deposit invoice

        $label=$langs->trans("ShowInvoice").': '.$this->ref;
        if ($this->type == 1) $label=$langs->trans("ShowInvoiceReplace").': '.$this->ref;
        if ($this->type == 2) $label=$langs->trans("ShowInvoiceAvoir").': '.$this->ref;
        if ($this->type == 3) $label=$langs->trans("ShowInvoiceDeposit").': '.$this->ref;

        if ($withpicto) $result.=($linkstart.img_object($label,$picto).$linkend);
        if ($withpicto && $withpicto != 2) $result.=' ';
        if ($withpicto != 2) $result.=$linkstart.($max?dol_trunc($this->ref,$max):$this->ref).$linkend;
        return $result;
    }


    /**
     *	Get object and lines from database
     *	@param      rowid       Id of object to load
     * 	@param		ref			Reference of invoice
     * 	@param		ref_ext		External reference of invoice
     * 	@param		ref_int		Internal reference of other object
     *	@return     int         >0 if OK, <0 if KO
     */
    function fetch($rowid, $ref='', $ref_ext='', $ref_int='')
    {
        global $conf;

        if (empty($rowid) && empty($ref) && empty($ref_ext) && empty($ref_int)) return -1;

        $sql = 'SELECT f.rowid,f.facnumber,f.ref_client,f.ref_ext,f.ref_int,f.type,f.fk_soc,f.amount,f.tva, f.localtax1, f.localtax2, f.total,f.total_ttc,f.remise_percent,f.remise_absolue,f.remise';
        $sql.= ', f.datef as df';
        $sql.= ', f.date_lim_reglement as dlr';
        $sql.= ', f.datec as datec';
        $sql.= ', f.date_valid as datev';
        $sql.= ', f.tms as datem';
        $sql.= ', f.note, f.note_public, f.fk_statut, f.paye, f.close_code, f.close_note, f.fk_user_author, f.fk_user_valid, f.model_pdf';
        $sql.= ', f.fk_facture_source';
        $sql.= ', f.fk_mode_reglement, f.fk_cond_reglement, f.fk_projet';
        $sql.= ', p.code as mode_reglement_code, p.libelle as mode_reglement_libelle';
        $sql.= ', c.code as cond_reglement_code, c.libelle as cond_reglement_libelle, c.libelle_facture as cond_reglement_libelle_doc';
        $sql.= ', el.fk_source';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'facture as f';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_payment_term as c ON f.fk_cond_reglement = c.rowid';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'c_paiement as p ON f.fk_mode_reglement = p.id';
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."element_element as el ON el.fk_target = f.rowid AND el.targettype = '".$this->element."'";
        $sql.= ' WHERE f.entity = '.$conf->entity;
        if ($rowid)   $sql.= " AND f.rowid=".$rowid;
        if ($ref)     $sql.= " AND f.facnumber='".$this->db->escape($ref)."'";
        if ($ref_ext) $sql.= " AND f.ref_ext='".$this->db->escape($ref_ext)."'";
        if ($ref_int) $sql.= " AND f.ref_int='".$this->db->escape($ref_int)."'";

        dol_syslog("Facture::Fetch sql=".$sql, LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);

                $this->id                     = $obj->rowid;
                $this->ref                    = $obj->facnumber;
                $this->ref_client             = $obj->ref_client;
                $this->ref_ext				  = $obj->ref_ext;
                $this->ref_int				  = $obj->ref_int;
                $this->type                   = $obj->type;
                $this->date                   = $this->db->jdate($obj->df);
                $this->date_creation          = $this->db->jdate($obj->datec);
                $this->date_validation        = $this->db->jdate($obj->datev);
                $this->datem                  = $this->db->jdate($obj->datem);
                $this->amount                 = $obj->amount;
                $this->remise_percent         = $obj->remise_percent;
                $this->remise_absolue         = $obj->remise_absolue;
                $this->remise                 = $obj->remise;
                $this->total_ht               = $obj->total;
                $this->total_tva              = $obj->tva;
                $this->total_localtax1		  = $obj->localtax1;
                $this->total_localtax2		  = $obj->localtax2;
                $this->total_ttc              = $obj->total_ttc;
                $this->paye                   = $obj->paye;
                $this->close_code             = $obj->close_code;
                $this->close_note             = $obj->close_note;
                $this->socid                  = $obj->fk_soc;
                $this->statut                 = $obj->fk_statut;
                $this->date_lim_reglement     = $this->db->jdate($obj->dlr);
                $this->mode_reglement_id      = $obj->fk_mode_reglement;
                $this->mode_reglement_code    = $obj->mode_reglement_code;
                $this->mode_reglement         = $obj->mode_reglement_libelle;
                $this->cond_reglement_id      = $obj->fk_cond_reglement;
                $this->cond_reglement_code    = $obj->cond_reglement_code;
                $this->cond_reglement         = $obj->cond_reglement_libelle;
                $this->cond_reglement_doc     = $obj->cond_reglement_libelle_doc;
                $this->fk_project             = $obj->fk_projet;
                $this->fk_facture_source      = $obj->fk_facture_source;
                $this->note                   = $obj->note;
                $this->note_public            = $obj->note_public;
                $this->user_author            = $obj->fk_user_author;
                $this->user_valid             = $obj->fk_user_valid;
                $this->modelpdf               = $obj->model_pdf;

                $this->commande_id            = $obj->fk_commande;

                if ($this->commande_id)
                {
                    $sql = "SELECT ref";
                    $sql.= " FROM ".MAIN_DB_PREFIX."commande";
                    $sql.= " WHERE rowid = ".$this->commande_id;

                    $resqlcomm = $this->db->query($sql);

                    if ($resqlcomm)
                    {
                        $objc = $this->db->fetch_object($resqlcomm);
                        $this->commande_ref = $objc->ref;
                        $this->db->free($resqlcomm);
                    }
                }

                if ($this->statut == 0)	$this->brouillon = 1;

                /*
                 * Lines
                 */

                $this->lines  = array();

                $result=$this->fetch_lines();
                if ($result < 0)
                {
                    $this->error=$this->db->error();
                    dol_syslog('Facture::Fetch Error '.$this->error, LOG_ERR);
                    return -3;
                }
                return 1;
            }
            else
            {
                $this->error='Bill with id '.$rowid.' or ref '.$ref.' not found sql='.$sql;
                dol_syslog('Facture::Fetch Error '.$this->error, LOG_ERR);
                return -2;
            }
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog('Facture::Fetch Error '.$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *	Recupere les lignes de factures dans this->lines
     *	@return     int         1 if OK, < 0 if KO
     */
    function fetch_lines()
    {
        $sql = 'SELECT l.rowid, l.fk_product, l.fk_parent_line, l.description, l.product_type, l.price, l.qty, l.tva_tx, ';
        $sql.= ' l.localtax1_tx, l.localtax2_tx, l.remise, l.remise_percent, l.fk_remise_except, l.subprice,';
        $sql.= ' l.rang, l.special_code,';
        $sql.= ' l.date_start as date_start, l.date_end as date_end,';
        $sql.= ' l.info_bits, l.total_ht, l.total_tva, l.total_localtax1, l.total_localtax2, l.total_ttc, l.fk_code_ventilation, l.fk_export_compta,';
        $sql.= ' p.ref as product_ref, p.fk_product_type as fk_product_type, p.label as label, p.description as product_desc';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'facturedet as l';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON l.fk_product = p.rowid';
        $sql.= ' WHERE l.fk_facture = '.$this->id;
        $sql.= ' ORDER BY l.rang';

        dol_syslog('Facture::fetch_lines sql='.$sql, LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result)
        {
            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num)
            {
                $objp = $this->db->fetch_object($result);
                $line = new FactureLigne($this->db);

                $line->rowid	        = $objp->rowid;
                $line->desc             = $objp->description;     // Description line
                $line->product_type     = $objp->product_type;	// Type of line
                $line->product_ref      = $objp->product_ref;     // Ref product
                $line->libelle          = $objp->label;           // Label product
                $line->product_desc     = $objp->product_desc;    // Description product
                $line->fk_product_type  = $objp->fk_product_type;	// Type of product
                $line->qty              = $objp->qty;
                $line->subprice         = $objp->subprice;
                $line->tva_tx           = $objp->tva_tx;
                $line->localtax1_tx     = $objp->localtax1_tx;
                $line->localtax2_tx     = $objp->localtax2_tx;
                $line->remise_percent   = $objp->remise_percent;
                $line->fk_remise_except = $objp->fk_remise_except;
                $line->fk_product       = $objp->fk_product;
                $line->date_start       = $this->db->jdate($objp->date_start);
                $line->date_end         = $this->db->jdate($objp->date_end);
                $line->date_start       = $this->db->jdate($objp->date_start);
                $line->date_end         = $this->db->jdate($objp->date_end);
                $line->info_bits        = $objp->info_bits;
                $line->total_ht         = $objp->total_ht;
                $line->total_tva        = $objp->total_tva;
                $line->total_localtax1  = $objp->total_localtax1;
                $line->total_localtax2  = $objp->total_localtax2;
                $line->total_ttc        = $objp->total_ttc;
                $line->export_compta    = $objp->fk_export_compta;
                $line->code_ventilation = $objp->fk_code_ventilation;
                $line->rang				= $objp->rang;
                $line->special_code		= $objp->special_code;
                $line->fk_parent_line	= $objp->fk_parent_line;

                // Ne plus utiliser
                $line->price            = $objp->price;
                $line->remise           = $objp->remise;

                $this->lines[$i] = $line;

                $i++;
            }
            $this->db->free($result);
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog('Facture::fetch_lines: Error '.$this->error,LOG_ERR);
            return -3;
        }
    }


    /**
     *      \brief      Update database
     *      \param      user        	User that modify
     *      \param      notrigger	    0=launch triggers after, 1=disable triggers
     *      \return     int         	<0 if KO, >0 if OK
     */
    function update($user=0, $notrigger=0)
    {
        global $conf, $langs;
        $error=0;

        // Clean parameters

        if (isset($this->facnumber)) $this->facnumber=trim($this->ref);
        if (isset($this->type)) $this->type=trim($this->type);
        if (isset($this->ref_client)) $this->ref_client=trim($this->ref_client);
        if (isset($this->increment)) $this->increment=trim($this->increment);
        if (isset($this->socid)) $this->socid=trim($this->socid);
        if (isset($this->paye)) $this->paye=trim($this->paye);
        if (isset($this->amount)) $this->amount=trim($this->amount);
        if (isset($this->remise_percent)) $this->remise_percent=trim($this->remise_percent);
        if (isset($this->remise_absolue)) $this->remise_absolue=trim($this->remise_absolue);
        if (isset($this->remise)) $this->remise=trim($this->remise);
        if (isset($this->close_code)) $this->close_code=trim($this->close_code);
        if (isset($this->close_note)) $this->close_note=trim($this->close_note);
        if (isset($this->total_tva)) $this->tva=trim($this->total_tva);
        if (isset($this->total_localtax1)) $this->tva=trim($this->total_localtax1);
        if (isset($this->total_localtax2)) $this->tva=trim($this->total_localtax2);
        if (isset($this->total_ht)) $this->total_ht=trim($this->total_ht);
        if (isset($this->total_ttc)) $this->total_ttc=trim($this->total_ttc);
        if (isset($this->statut)) $this->statut=trim($this->statut);
        if (isset($this->user_author)) $this->user_author=trim($this->user_author);
        if (isset($this->fk_user_valid)) $this->fk_user_valid=trim($this->fk_user_valid);
        if (isset($this->fk_facture_source)) $this->fk_facture_source=trim($this->fk_facture_source);
        if (isset($this->fk_project)) $this->fk_project=trim($this->fk_project);
        if (isset($this->cond_reglement_id)) $this->cond_reglement_id=trim($this->cond_reglement_id);
        if (isset($this->mode_reglement_id)) $this->mode_reglement_id=trim($this->mode_reglement_id);
        if (isset($this->note)) $this->note=trim($this->note);
        if (isset($this->note_public)) $this->note_public=trim($this->note_public);
        if (isset($this->modelpdf)) $this->modelpdf=trim($this->modelpdf);
        if (isset($this->import_key)) $this->import_key=trim($this->import_key);

        // Check parameters
        // Put here code to add control on parameters values

        // Update request
        $sql = "UPDATE ".MAIN_DB_PREFIX."facture SET";

        $sql.= " facnumber=".(isset($this->ref)?"'".$this->db->escape($this->ref)."'":"null").",";
        $sql.= " type=".(isset($this->type)?$this->type:"null").",";
        $sql.= " ref_client=".(isset($this->ref_client)?"'".$this->db->escape($this->ref_client)."'":"null").",";
        $sql.= " increment=".(isset($this->increment)?"'".$this->db->escape($this->increment)."'":"null").",";
        $sql.= " fk_soc=".(isset($this->socid)?$this->socid:"null").",";
        $sql.= " datec=".(strval($this->date_creation)!='' ? "'".$this->db->idate($this->date_creation)."'" : 'null').",";
        $sql.= " datef=".(strval($this->date)!='' ? "'".$this->db->idate($this->date)."'" : 'null').",";
        $sql.= " date_valid=".(strval($this->date_validation)!='' ? "'".$this->db->idate($this->date_validation)."'" : 'null').",";
        $sql.= " paye=".(isset($this->paye)?$this->paye:"null").",";
        $sql.= " amount=".(isset($this->amount)?$this->amount:"null").",";
        $sql.= " remise_percent=".(isset($this->remise_percent)?$this->remise_percent:"null").",";
        $sql.= " remise_absolue=".(isset($this->remise_absolue)?$this->remise_absolue:"null").",";
        $sql.= " remise=".(isset($this->remise)?$this->remise:"null").",";
        $sql.= " close_code=".(isset($this->close_code)?"'".$this->db->escape($this->close_code)."'":"null").",";
        $sql.= " close_note=".(isset($this->close_note)?"'".$this->db->escape($this->close_note)."'":"null").",";
        $sql.= " tva=".(isset($this->total_tva)?$this->total_tva:"null").",";
        $sql.= " localtax1=".(isset($this->total_localtax1)?$this->total_localtax1:"null").",";
        $sql.= " localtax2=".(isset($this->total_localtax2)?$this->total_localtax2:"null").",";
        $sql.= " total=".(isset($this->total_ht)?$this->total_ht:"null").",";
        $sql.= " total_ttc=".(isset($this->total_ttc)?$this->total_ttc:"null").",";
        $sql.= " fk_statut=".(isset($this->statut)?$this->statut:"null").",";
        $sql.= " fk_user_author=".(isset($this->user_author)?$this->user_author:"null").",";
        $sql.= " fk_user_valid=".(isset($this->fk_user_valid)?$this->fk_user_valid:"null").",";
        $sql.= " fk_facture_source=".(isset($this->fk_facture_source)?$this->fk_facture_source:"null").",";
        $sql.= " fk_projet=".(isset($this->fk_project)?$this->fk_project:"null").",";
        $sql.= " fk_cond_reglement=".(isset($this->cond_reglement_id)?$this->cond_reglement_id:"null").",";
        $sql.= " fk_mode_reglement=".(isset($this->mode_reglement_id)?$this->mode_reglement_id:"null").",";
        $sql.= " date_lim_reglement=".(strval($this->date_lim_reglement)!='' ? "'".$this->db->idate($this->date_lim_reglement)."'" : 'null').",";
        $sql.= " note=".(isset($this->note)?"'".$this->db->escape($this->note)."'":"null").",";
        $sql.= " note_public=".(isset($this->note_public)?"'".$this->db->escape($this->note_public)."'":"null").",";
        $sql.= " model_pdf=".(isset($this->modelpdf)?"'".$this->db->escape($this->modelpdf)."'":"null").",";
        $sql.= " import_key=".(isset($this->import_key)?"'".$this->db->escape($this->import_key)."'":"null")."";

        $sql.= " WHERE rowid=".$this->id;

        $this->db->begin();

        dol_syslog(get_class($this)."::update sql=".$sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (! $resql) { $error++; $this->errors[]="Error ".$this->db->lasterror(); }

        if (! $error)
        {
            if (! $notrigger)
            {
                // Call triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('BILL_MODIFY',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // End call triggers
            }
        }

        // Commit or rollback
        if ($error)
        {
            foreach($this->errors as $errmsg)
            {
                dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
                $this->error.=($this->error?', '.$errmsg:$errmsg);
            }
            $this->db->rollback();
            return -1*$error;
        }
        else
        {
            $this->db->commit();
            return 1;
        }
    }


    /**
     *    \brief     Ajout en base d'une ligne remise fixe en ligne de facture
     *    \param     idremise			Id de la remise fixe
     *    \return    int          		>0 si ok, <0 si ko
     */
    function insert_discount($idremise)
    {
        global $langs;

        include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');
        include_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

        $this->db->begin();

        $remise=new DiscountAbsolute($this->db);
        $result=$remise->fetch($idremise);

        if ($result > 0)
        {
            if ($remise->fk_facture)	// Protection against multiple submission
            {
                $this->error=$langs->trans("ErrorDiscountAlreadyUsed");
                $this->db->rollback();
                return -5;
            }

            $facligne=new FactureLigne($this->db);
            $facligne->fk_facture=$this->id;
            $facligne->fk_remise_except=$remise->id;
            $facligne->desc=$remise->description;   	// Description ligne
            $facligne->tva_tx=$remise->tva_tx;
            $facligne->subprice=-$remise->amount_ht;
            $facligne->fk_product=0;					// Id produit predefini
            $facligne->qty=1;
            $facligne->remise_percent=0;
            $facligne->rang=-1;
            $facligne->info_bits=2;

            // Ne plus utiliser
            $facligne->price=-$remise->amount_ht;
            $facligne->remise=0;

            $facligne->total_ht  = -$remise->amount_ht;
            $facligne->total_tva = -$remise->amount_tva;
            $facligne->total_ttc = -$remise->amount_ttc;

            $lineid=$facligne->insert();
            if ($lineid > 0)
            {
                $result=$this->update_price(1);
                if ($result > 0)
                {
                    // Cr�e lien entre remise et ligne de facture
                    $result=$remise->link_to_invoice($lineid,0);
                    if ($result < 0)
                    {
                        $this->error=$remise->error;
                        $this->db->rollback();
                        return -4;
                    }

                    $this->db->commit();
                    return 1;
                }
                else
                {
                    $this->error=$facligne->error;
                    $this->db->rollback();
                    return -1;
                }
            }
            else
            {
                $this->error=$facligne->error;
                $this->db->rollback();
                return -2;
            }
        }
        else
        {
            $this->db->rollback();
            return -3;
        }
    }


    function set_ref_client($ref_client)
    {
        $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
        if (empty($ref_client))
        $sql .= ' SET ref_client = NULL';
        else
        $sql .= ' SET ref_client = \''.$this->db->escape($ref_client).'\'';
        $sql .= ' WHERE rowid = '.$this->id;
        if ($this->db->query($sql))
        {
            $this->ref_client = $ref_client;
            return 1;
        }
        else
        {
            dol_print_error($this->db);
            return -1;
        }
    }

    /**
     *	Delete invoice
     *	@param     	rowid      	Id of invoice to delete
     *	@return		int			<0 if KO, >0 if OK
     */
    function delete($rowid=0)
    {
        global $user,$langs,$conf;

        if (! $rowid) $rowid=$this->id;

        dol_syslog("Facture::delete rowid=".$rowid, LOG_DEBUG);

        // TODO Test if there is at least on payment. If yes, refuse to delete.

        $error=0;
        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."element_element";
        $sql.= " WHERE fk_target = ".$rowid;
        $sql.= " AND targettype = '".$this->element."'";

        if ($this->db->query($sql))
        {
        	// If invoice was converted into a discount not yet consumed, we remove discount
            $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'societe_remise_except';
            $sql.= ' WHERE fk_facture_source = '.$rowid;
            $sql.= ' AND fk_facture_line IS NULL';
            $resql=$this->db->query($sql);

            // If invoice has consumned discounts
            $list_rowid_det=array();
            $sql = 'SELECT fd.rowid FROM '.MAIN_DB_PREFIX.'facturedet as fd WHERE fk_facture = '.$rowid;
            $resql=$this->db->query($sql);
            while ($obj = $this->db->fetch_object($resql))
            {
                $list_rowid_det[]=$obj->rowid;
            }

            // Consumned discounts are freed
            if (sizeof($list_rowid_det))
            {
                $sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_remise_except';
                $sql.= ' SET fk_facture = NULL';
                $sql.= ' WHERE fk_facture IN ('.join(',',$list_rowid_det).')';

                dol_syslog("Facture.class::delete sql=".$sql);
                if (! $this->db->query($sql))
                {
                    $this->error=$this->db->error()." sql=".$sql;
                    dol_syslog("Facture.class::delete ".$this->error, LOG_ERR);
                    $this->db->rollback();
                    return -5;
                }
            }

            $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'facturedet WHERE fk_facture = '.$rowid;
            if ($this->db->query($sql) && $this->delete_linked_contact())
            {
                $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'facture WHERE rowid = '.$rowid;
                $resql=$this->db->query($sql);
                if ($resql)
                {
                    // Appel des triggers
                    include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                    $interface=new Interfaces($this->db);
                    $result=$interface->run_triggers('BILL_DELETE',$this,$user,$langs,$conf);
                    if ($result < 0) { $error++; $this->errors=$interface->errors; }
                    // Fin appel triggers

                    $this->db->commit();
                    return 1;
                }
                else
                {
                    $this->error=$this->db->error()." sql=".$sql;
                    dol_syslog("Facture.class::delete ".$this->error, LOG_ERR);
                    $this->db->rollback();
                    return -6;
                }
            }
            else
            {
                $this->error=$this->db->error()." sql=".$sql;
                dol_syslog("Facture.class::delete ".$this->error, LOG_ERR);
                $this->db->rollback();
                return -4;
            }
        }
        else
        {
            $this->error=$this->db->error()." sql=".$sql;
            dol_syslog("Facture.class::delete ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }


    /**
     *	Renvoi une date limite de reglement de facture en fonction des
     *	conditions de reglements de la facture et date de facturation
     *	@param      cond_reglement_id   Condition de reglement a utiliser, 0=Condition actuelle de la facture
     *	@return     date                Date limite de reglement si ok, <0 si ko
     */
    function calculate_date_lim_reglement($cond_reglement_id=0)
    {
        if (! $cond_reglement_id)
        $cond_reglement_id=$this->cond_reglement_id;
        $sqltemp = 'SELECT c.fdm,c.nbjour,c.decalage';
        $sqltemp.= ' FROM '.MAIN_DB_PREFIX.'c_payment_term as c';
        $sqltemp.= ' WHERE c.rowid='.$cond_reglement_id;
        $resqltemp=$this->db->query($sqltemp);
        if ($resqltemp)
        {
            if ($this->db->num_rows($resqltemp))
            {
                $obj = $this->db->fetch_object($resqltemp);
                $cdr_nbjour = $obj->nbjour;
                $cdr_fdm = $obj->fdm;
                $cdr_decalage = $obj->decalage;
            }
        }
        else
        {
            $this->error=$this->db->error();
            return -1;
        }
        $this->db->free($resqltemp);

        /* Definition de la date limite */

        // 1 : ajout du nombre de jours
        $datelim = $this->date + ( $cdr_nbjour * 3600 * 24 );

        // 2 : application de la regle "fin de mois"
        if ($cdr_fdm)
        {
            $mois=date('m', $datelim);
            $annee=date('Y', $datelim);
            if ($mois == 12)
            {
                $mois = 1;
                $annee += 1;
            }
            else
            {
                $mois += 1;
            }
            // On se deplace au debut du mois suivant, et on retire un jour
            $datelim=dol_mktime(12,0,0,$mois,1,$annee);
            $datelim -= (3600 * 24);
        }

        // 3 : application du decalage
        $datelim += ( $cdr_decalage * 3600 * 24);

        return $datelim;
    }

    /**
     *      Tag la facture comme paye completement (close_code non renseigne) ou partiellement (close_code renseigne) + appel trigger BILL_PAYED
     *      @param      user      	Objet utilisateur qui modifie
     *		@param      close_code	Code renseigne si on classe a payee completement alors que paiement incomplet (cas escompte par exemple)
     *	   	@param      close_note	Commentaire renseigne si on classe a payee alors que paiement incomplet (cas escompte par exemple)
     *      @return     int         <0 si ok, >0 si ok
     */
    function set_paid($user,$close_code='',$close_note='')
    {
        global $conf,$langs;
        $error=0;

        if ($this->paye != 1)
        {
            $this->db->begin();

            dol_syslog("Facture::set_paid rowid=".$this->id, LOG_DEBUG);
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture SET';
            $sql.= ' fk_statut=2';
            if (! $close_code) $sql.= ', paye=1';
            if ($close_code) $sql.= ", close_code='".$this->db->escape($close_code)."'";
            if ($close_note) $sql.= ", close_note='".$this->db->escape($close_note)."'";
            $sql.= ' WHERE rowid = '.$this->id;

            $resql = $this->db->query($sql);
            if ($resql)
            {
                $this->use_webcal=($conf->global->PHPWEBCALENDAR_BILLSTATUS=='always'?1:0);

                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('BILL_PAYED',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }
            else
            {
                $error++;
                $this->error=$this->db->error();
                dol_print_error($this->db);
            }

            if (! $error)
            {
                $this->db->commit();
                return 1;
            }
            else
            {
                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            return 0;
        }
    }


    /**
     *      \brief      Tag la facture comme non payee completement + appel trigger BILL_UNPAYED
     *				   	Fonction utilisee quand un paiement prelevement est refuse,
     * 					ou quand une facture annulee et reouverte.
     *      \param      user        Object user that change status
     *      \return     int         <0 si ok, >0 si ok
     */
    function set_unpaid($user)
    {
        global $conf,$langs;
        $error=0;

        $this->db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
        $sql.= ' SET paye=0, fk_statut=1, close_code=null, close_note=null';
        $sql.= ' WHERE rowid = '.$this->id;

        dol_syslog("Facture::set_unpaid sql=".$sql);
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $this->use_webcal=($conf->global->PHPWEBCALENDAR_BILLSTATUS=='always'?1:0);

            // Appel des triggers
            include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
            $interface=new Interfaces($this->db);
            $result=$interface->run_triggers('BILL_UNPAYED',$this,$user,$langs,$conf);
            if ($result < 0) { $error++; $this->errors=$interface->errors; }
            // Fin appel triggers
        }
        else
        {
            $error++;
            $this->error=$this->db->error();
            dol_print_error($this->db);
        }

        if (! $error)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            return -1;
        }
    }


    /**
     *	\brief      Tag la facture comme abandonnee, sans paiement dessus (exemple car facture de remplacement) + appel trigger BILL_CANCEL
     *	\param      user        Objet utilisateur qui modifie
     *	\param		close_code	Code de fermeture
     *	\param		close_note	Commentaire de fermeture
     *	\return     int         <0 si ok, >0 si ok
     */
    function set_canceled($user,$close_code='',$close_note='')
    {
        global $conf,$langs;

        dol_syslog("Facture::set_canceled rowid=".$this->id, LOG_DEBUG);

        $this->db->begin();

        $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture SET';
        $sql.= ' fk_statut=3';
        if ($close_code) $sql.= ", close_code='".$this->db->escape($close_code)."'";
        if ($close_note) $sql.= ", close_note='".$this->db->escape($close_note)."'";
        $sql.= ' WHERE rowid = '.$this->id;

        $resql = $this->db->query($sql);
        if ($resql)
        {
            // On desaffecte de la facture les remises liees
            // car elles n'ont pas ete utilisees vu que la facture est abandonnee.
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_remise_except';
            $sql.= ' SET fk_facture = NULL';
            $sql.= ' WHERE fk_facture = '.$this->id;

            $resql=$this->db->query($sql);
            if ($resql)
            {
                $this->use_webcal=($conf->global->PHPWEBCALENDAR_BILLSTATUS=='always'?1:0);

                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('BILL_CANCEL',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers

                $this->db->commit();
                return 1;
            }
            else
            {
                $this->error=$this->db->error()." sql=".$sql;
                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            $this->error=$this->db->error()." sql=".$sql;
            $this->db->rollback();
            return -2;
        }
    }

    /**
     *      Tag invoice as validated + call trigger BILL_VALIDATE
     *      @param     	user            Object user that validate
     *      @param     	force_number	Reference to force on invoice
     *	    @return		int				<0 if KO, >0 if OK
     */
    function validate($user, $force_number='')
    {
        global $conf,$langs;
        require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");

        $error=0;

        // Protection
        if (! $this->brouillon)
        {
            dol_syslog("Facture::validate no draft status", LOG_WARNING);
            return 0;
        }

        if (! $user->rights->facture->valider)
        {
            $this->error='Permission denied';
            dol_syslog("Facture::validate ".$this->error, LOG_ERR);
            return -1;
        }

        $this->db->begin();

        $this->fetch_thirdparty();
        $this->fetch_lines();

        // Check parameters
        if ($this->type == 1)		// si facture de remplacement
        {
            // Controle que facture source connue
            if ($this->fk_facture_source <= 0)
            {
                $this->error=$langs->trans("ErrorFieldRequired",$langs->trans("InvoiceReplacement"));
                $this->db->rollback();
                return -10;
            }

            // Charge la facture source a remplacer
            $facreplaced=new Facture($this->db);
            $result=$facreplaced->fetch($this->fk_facture_source);
            if ($result <= 0)
            {
                $this->error=$langs->trans("ErrorBadInvoice");
                $this->db->rollback();
                return -11;
            }

            // Controle que facture source non deja remplacee par une autre
            $idreplacement=$facreplaced->getIdReplacingInvoice('validated');
            if ($idreplacement && $idreplacement != $this->id)
            {
                $facreplacement=new Facture($this->db);
                $facreplacement->fetch($idreplacement);
                $this->error=$langs->trans("ErrorInvoiceAlreadyReplaced",$facreplaced->ref,$facreplacement->ref);
                $this->db->rollback();
                return -12;
            }

            $result=$facreplaced->set_canceled($user,'replaced','');
            if ($result < 0)
            {
                $this->error=$facreplaced->error." sql=".$sql;
                $this->db->rollback();
                return -13;
            }
        }

        // Define new ref
        if ($force_number)
        {
            $num = $force_number;
        }
        else if (preg_match('/^[\(]?PROV/i', $this->ref))
        {
            if (! empty($conf->global->FAC_FORCE_DATE_VALIDATION))	// If option enabled, we force invoice date
            {
                $this->date=dol_now();
                $this->date_lim_reglement=$this->calculate_date_lim_reglement();
            }
            $num = $this->getNextNumRef($this->client);
        }
        else
        {
            $num = $this->ref;
        }

        if ($num)
        {
            $this->update_price(1);

            // Validate
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
            $sql.= " SET facnumber='".$num."', fk_statut = 1, fk_user_valid = ".$user->id;
            if (! empty($conf->global->FAC_FORCE_DATE_VALIDATION))	// If option enabled, we force invoice date
            {
                $sql.= ', datef='.$this->db->idate($this->date);
                $sql.= ', date_lim_reglement='.$this->db->idate($this->date_lim_reglement);
            }
            $sql.= ' WHERE rowid = '.$this->id;

            dol_syslog("Facture::validate sql=".$sql);
            $resql=$this->db->query($sql);
            if (! $resql)
            {
                dol_syslog("Facture::validate Echec update - 10 - sql=".$sql, LOG_ERR);
                dol_print_error($this->db);
                $error++;
            }

            // On verifie si la facture etait une provisoire
            if (! $error && (preg_match('/^[\(]?PROV/i', $this->ref)))
            {
                // La verif qu'une remise n'est pas utilisee 2 fois est faite au moment de l'insertion de ligne
            }

            if (! $error)
            {
                // Define third party as a customer
                $result=$this->client->set_as_client();

                // Si active on decremente le produit principal et ses composants a la validation de facture
                if ($result >= 0 && $conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_BILL)
                {
                    require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");
                    $langs->load("agenda");

                    // Loop on each line
                    for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
                    {
                        if ($this->lines[$i]->fk_product > 0)
                        {
                            $mouvP = new MouvementStock($this->db);
                            // We decrease stock for product
                            $entrepot_id = "1"; // TODO ajouter possibilite de choisir l'entrepot
                            $result=$mouvP->livraison($user, $this->lines[$i]->fk_product, $entrepot_id, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("InvoiceValidatedInDolibarr",$num));
                            if ($result < 0) { $error++; }
                        }
                    }
                }
            }

            if (! $error)
            {
            	$this->oldref = '';
            	
                // Rename directory if dir was a temporary ref
                if (preg_match('/^[\(]?PROV/i', $this->ref))
                {
                    // On renomme repertoire facture ($this->ref = ancienne ref, $num = nouvelle ref)
                    // afin de ne pas perdre les fichiers attaches
                    $facref = dol_sanitizeFileName($this->ref);
                    $snumfa = dol_sanitizeFileName($num);
                    $dirsource = $conf->facture->dir_output.'/'.$facref;
                    $dirdest = $conf->facture->dir_output.'/'.$snumfa;
                    if (file_exists($dirsource))
                    {
                        dol_syslog("Facture::validate rename dir ".$dirsource." into ".$dirdest);

                        if (@rename($dirsource, $dirdest))
                        {
                        	$this->oldref = $facref;
                        	
                            dol_syslog("Rename ok");
                            // Suppression ancien fichier PDF dans nouveau rep
                            dol_delete_file($conf->facture->dir_output.'/'.$snumfa.'/'.$facref.'.*');
                        }
                    }
                }
            }

            // Set new ref and define current statut
            if (! $error)
            {
            	$this->ref = $num;
                $this->facnumber=$num;
                $this->statut=1;
            }

            $this->use_webcal=($conf->global->PHPWEBCALENDAR_BILLSTATUS=='always'?1:0);

            // Trigger calls
            if (! $error)
            {
                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result=$interface->run_triggers('BILL_VALIDATE',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }
        }
        else
        {
            $error++;
        }

        if (! $error)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->db->rollback();
            $this->error=$this->db->lasterror();
            return -1;
        }
    }

    /**
     *		\brief		Set draft status
     *		\param		user		Object user that modify
     *		\param		int			<0 if KO, >0 if OK
     */
    function set_draft($user)
    {
        global $conf,$langs;

        $error=0;

        if ($this->statut == 0)
        {
            dol_syslog("Facture::set_draft already draft status", LOG_WARNING);
            return 0;
        }

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX."facture";
        $sql.= " SET fk_statut = 0";
        $sql.= " WHERE rowid = ".$this->id;

        dol_syslog("Facture::set_draft sql=".$sql, LOG_DEBUG);
        if ($this->db->query($sql))
        {
            // Si on decremente le produit principal et ses composants a la validation de facture, on réincrement
            if ($result >= 0 && $conf->stock->enabled && $conf->global->STOCK_CALCULATE_ON_BILL)
            {
                require_once(DOL_DOCUMENT_ROOT."/product/stock/class/mouvementstock.class.php");
                $langs->load("agenda");

                for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
                {
                    if ($this->lines[$i]->fk_product > 0)
                    {
                        $mouvP = new MouvementStock($this->db);
                        // We decrease stock for product
                        $entrepot_id = "1"; // TODO ajouter possibilite de choisir l'entrepot
                        $result=$mouvP->reception($user, $this->lines[$i]->fk_product, $entrepot_id, $this->lines[$i]->qty, $this->lines[$i]->subprice, $langs->trans("InvoiceBackToDraftInDolibarr",$this->ref));
                    }
                }
            }

            if ($error == 0)
            {
                $this->db->commit();
                return 1;
            }
            else
            {
                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            $this->error=$this->db->error();
            $this->db->rollback();
            return -1;
        }
    }


    /**
     * 		Add an invoice line into database (linked to product/service or not)
     * 		\param    	facid           	Id de la facture
     * 		\param    	desc            	Description de la ligne
     * 		\param    	pu_ht              	Prix unitaire HT (> 0 even for credit note)
     * 		\param    	qty             	Quantite
     * 		\param    	txtva           	Taux de tva force, sinon -1
     * 		\param		txlocaltax1			Local tax 1 rate
     *  	\param		txlocaltax2			Local tax 2 rate
     *		\param    	fk_product      	Id du produit/service predefini
     * 		\param    	remise_percent  	Pourcentage de remise de la ligne
     * 		\param    	date_start      	Date de debut de validite du service
     * 		\param    	date_end        	Date de fin de validite du service
     * 		\param    	ventil          	Code de ventilation comptable
     * 		\param    	info_bits			Bits de type de lignes
     *		\param    	fk_remise_except	Id remise
     *		\param		price_base_type		HT or TTC
     * 		\param    	pu_ttc             	Prix unitaire TTC (> 0 even for credit note)
     * 		\param		type				Type of line (0=product, 1=service)
     *      \param      rang                Position of line
     *    	\return    	int             	>0 if OK, <0 if KO
     * 		\remarks	Les parametres sont deja cense etre juste et avec valeurs finales a l'appel
     *					de cette methode. Aussi, pour le taux tva, il doit deja avoir ete defini
     *					par l'appelant par la methode get_default_tva(societe_vendeuse,societe_acheteuse,produit)
     *					et le desc doit deja avoir la bonne valeur (a l'appelant de gerer le multilangue)
     */
    function addline($facid, $desc, $pu_ht, $qty, $txtva, $txlocaltax1=0, $txlocaltax2=0, $fk_product=0, $remise_percent=0, $date_start='', $date_end='', $ventil=0, $info_bits=0, $fk_remise_except='', $price_base_type='HT', $pu_ttc=0, $type=0, $rang=-1, $special_code=0, $origin='', $origin_id=0, $fk_parent_line=0)
    {
        dol_syslog("Facture::Addline facid=$facid,desc=$desc,pu_ht=$pu_ht,qty=$qty,txtva=$txtva, txlocaltax1=$txlocaltax1, txlocaltax2=$txlocaltax2, fk_product=$fk_product,remise_percent=$remise_percent,date_start=$date_start,date_end=$date_end,ventil=$ventil,info_bits=$info_bits,fk_remise_except=$fk_remise_except,price_base_type=$price_base_type,pu_ttc=$pu_ttc,type=$type", LOG_DEBUG);
        include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

        // Clean parameters
        if (empty($remise_percent)) $remise_percent=0;
        if (empty($qty)) $qty=0;
        if (empty($info_bits)) $info_bits=0;
        if (empty($rang)) $rang=0;
        if (empty($ventil)) $ventil=0;
        if (empty($txtva)) $txtva=0;
        if (empty($txlocaltax1)) $txlocaltax1=0;
        if (empty($txlocaltax2)) $txlocaltax2=0;
        if (empty($fk_parent_line) || $fk_parent_line < 0) $fk_parent_line=0;

        $remise_percent=price2num($remise_percent);
        $qty=price2num($qty);
        $pu_ht=price2num($pu_ht);
        $pu_ttc=price2num($pu_ttc);
        $txtva=price2num($txtva);
        $txlocaltax1=price2num($txlocaltax1);
        $txlocaltax2=price2num($txlocaltax2);

        if ($price_base_type=='HT')
        {
            $pu=$pu_ht;
        }
        else
        {
            $pu=$pu_ttc;
        }

        // Check parameters
        if ($type < 0) return -1;

        if ($this->brouillon)
        {
            $this->db->begin();

            // Calcul du total TTC et de la TVA pour la ligne a partir de
            // qty, pu, remise_percent et txtva
            // TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
            // la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
            $tabprice = calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
            $total_ht  = $tabprice[0];
            $total_tva = $tabprice[1];
            $total_ttc = $tabprice[2];
            $total_localtax1 = $tabprice[9];
            $total_localtax2 = $tabprice[10];
            $pu_ht = $tabprice[3];

            // Rang to use
            $rangtouse = $rang;
            if ($rangtouse == -1)
            {
                $rangmax = $this->line_max($fk_parent_line);
                $rangtouse = $rangmax + 1;
            }

            // TODO A virer
            // Anciens indicateurs: $price, $remise (a ne plus utiliser)
            $price = $pu;
            $remise = 0;
            if ($remise_percent > 0)
            {
                $remise = round(($pu * $remise_percent / 100),2);
                $price = ($pu - $remise);
            }

            $product_type=$type;
            if ($fk_product)
            {
                $product=new Product($this->db);
                $result=$product->fetch($fk_product);
                $product_type=$product->type;
            }

            // Insert line
            $this->line=new FactureLigne($this->db);
            $this->line->fk_facture=$facid;
            $this->line->desc=$desc;
            $this->line->qty=$qty;
            $this->line->tva_tx=$txtva;
            $this->line->localtax1_tx=$txlocaltax1;
            $this->line->localtax2_tx=$txlocaltax2;
            $this->line->fk_product=$fk_product;
            $this->line->product_type=$product_type;
            $this->line->remise_percent=$remise_percent;
            $this->line->subprice=       ($this->type==2?-1:1)*abs($pu_ht);
            $this->line->date_start=$date_start;
            $this->line->date_end=$date_end;
            $this->line->ventil=$ventil;
            $this->line->rang=$rangtouse;
            $this->line->info_bits=$info_bits;
            $this->line->fk_remise_except=$fk_remise_except;
            $this->line->total_ht=       ($this->type==2?-1:1)*abs($total_ht);
            $this->line->total_tva=      ($this->type==2?-1:1)*abs($total_tva);
            $this->line->total_localtax1=($this->type==2?-1:1)*abs($total_localtax1);
            $this->line->total_localtax2=($this->type==2?-1:1)*abs($total_localtax2);
            $this->line->total_ttc=      ($this->type==2?-1:1)*abs($total_ttc);
            $this->line->special_code=$special_code;
            $this->line->fk_parent_line=$fk_parent_line;
            $this->line->origin=$origin;
            $this->line->origin_id=$origin_id;

            // TODO Ne plus utiliser
            $this->line->price=($this->type==2?-1:1)*abs($price);
            $this->line->remise=($this->type==2?-1:1)*abs($remise);

            $result=$this->line->insert();
            if ($result > 0)
            {
            	// Reorder if child line
				if (! empty($fk_parent_line)) $this->line_order(true,'DESC');

                // Mise a jour informations denormalisees au niveau de la facture meme
                $this->id=$facid;	// TODO To move this we must remove parameter facid into this function declaration
                $result=$this->update_price(1);
                if ($result > 0)
                {
                    $this->db->commit();
                    return $this->line->rowid;
                }
                else
                {
                    $this->error=$this->db->error();
                    dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
                    $this->db->rollback();
                    return -1;
                }
            }
            else
            {
                $this->error=$this->line->error;
                $this->db->rollback();
                return -2;
            }
        }
    }

    /**
     *      Update a detail line
     *      @param     	rowid           Id of line to update
     *      @param     	desc            Description of line
     *      @param     	pu              Prix unitaire (HT ou TTC selon price_base_type) (> 0 even for credit note lines)
     *      @param     	qty             Quantity
     *      @param     	remise_percent  Pourcentage de remise de la ligne
     *      @param     	date_start      Date de debut de validite du service
     *      @param     	date_end        Date de fin de validite du service
     *      @param     	tva_tx          VAT Rate
     * 		@param		txlocaltax1		Local tax 1 rate
     *  	@param		txlocaltax2		Local tax 2 rate
     * 	   	@param     	price_base_type HT or TTC
     * 	   	@param     	info_bits       Miscellanous informations
     * 		@param		type			Type of line (0=product, 1=service)
     *      @return    	int             < 0 if KO, > 0 if OK
     */
    function updateline($rowid, $desc, $pu, $qty, $remise_percent=0, $date_start, $date_end, $txtva, $txlocaltax1=0, $txlocaltax2=0,$price_base_type='HT', $info_bits=0, $type=0, $fk_parent_line=0, $skip_update_total=0)
    {
        include_once(DOL_DOCUMENT_ROOT.'/lib/price.lib.php');

        dol_syslog("Facture::UpdateLine $rowid, $desc, $pu, $qty, $remise_percent, $date_start, $date_end, $txtva, $txlocaltax1, $txlocaltax2, $price_base_type, $info_bits, $type", LOG_DEBUG);

        if ($this->brouillon)
        {
            $this->db->begin();

            // Clean parameters
            $remise_percent=price2num($remise_percent);
            $qty=price2num($qty);
            if (! $qty) $qty=0;
            $pu = price2num($pu);
            $txtva=price2num($txtva);
            $txlocaltax1=price2num($txlocaltax1);
            $txlocaltax2=price2num($txlocaltax2);
            // Check parameters
            if ($type < 0) return -1;

            // Calculate total with, without tax and tax from qty, pu, remise_percent and txtva
            // TRES IMPORTANT: C'est au moment de l'insertion ligne qu'on doit stocker
            // la part ht, tva et ttc, et ce au niveau de la ligne qui a son propre taux tva.
            $tabprice=calcul_price_total($qty, $pu, $remise_percent, $txtva, $txlocaltax1, $txlocaltax2, 0, $price_base_type, $info_bits);
            $total_ht  = $tabprice[0];
            $total_tva = $tabprice[1];
            $total_ttc = $tabprice[2];
            $total_localtax1=$tabprice[9];
            $total_localtax2=$tabprice[10];
            $pu_ht  = $tabprice[3];
            $pu_tva = $tabprice[4];
            $pu_ttc = $tabprice[5];

            // Old properties: $price, $remise (deprecated)
            $price = $pu;
            $remise = 0;
            if ($remise_percent > 0)
            {
                $remise = round(($pu * $remise_percent / 100),2);
                $price = ($pu - $remise);
            }
            $price    = price2num($price);

            // Update line into database
            $this->line=new FactureLigne($this->db);
            
            // Stock previous line records
			$staticline=new FactureLigne($this->db);
			$staticline->fetch($rowid);
			$this->line->oldline = $staticline;
			
            $this->line->rowid				= $rowid;
            $this->line->desc				= $desc;
            $this->line->qty				= $qty;
            $this->line->tva_tx				= $txtva;
            $this->line->localtax1_tx		= $txlocaltax1;
            $this->line->localtax2_tx		= $txlocaltax2;
            $this->line->remise_percent		= $remise_percent;
            $this->line->subprice			= ($this->type==2?-1:1)*abs($pu);
            $this->line->date_start			= $date_start;
            $this->line->date_end			= $date_end;
            $this->line->total_ht			= ($this->type==2?-1:1)*abs($total_ht);
            $this->line->total_tva			= ($this->type==2?-1:1)*abs($total_tva);
            $this->line->total_localtax1	= ($this->type==2?-1:1)*abs($total_localtax1);
            $this->line->total_localtax2	= ($this->type==2?-1:1)*abs($total_localtax2);
            $this->line->total_ttc			= ($this->type==2?-1:1)*abs($total_ttc);
            $this->line->info_bits			= $info_bits;
            $this->line->product_type		= $type;
            $this->line->fk_parent_line		= $fk_parent_line;
            $this->line->skip_update_total	= $skip_update_total;

            // A ne plus utiliser
            $this->line->price=$price;
            $this->line->remise=$remise;

            $result=$this->line->update();
            if ($result > 0)
            {
                // Mise a jour info denormalisees au niveau facture
                $this->update_price(1);
                $this->db->commit();
                return $result;
            }
            else
            {
                $this->db->rollback();
                return -1;
            }
        }
        else
        {
            $this->error="Facture::UpdateLine Invoice statut makes operation forbidden";
            return -2;
        }
    }

    /**
     *	Delete line in database
     *	@param		rowid		Id of line to delete
     *	@return		int			<0 if KO, >0 if OK
     */
    function deleteline($rowid)
    {
        global $langs, $conf;

        dol_syslog("Facture::Deleteline rowid=".$rowid, LOG_DEBUG);

        if (! $this->brouillon)
        {
            $this->error='ErrorBadStatus';
            return -1;
        }

        $this->db->begin();

        // Libere remise liee a ligne de facture
        $sql = 'UPDATE '.MAIN_DB_PREFIX.'societe_remise_except';
        $sql.= ' SET fk_facture_line = NULL';
        $sql.= ' WHERE fk_facture_line = '.$rowid;

        dol_syslog("Facture::Deleteline sql=".$sql);
        $result = $this->db->query($sql);
        if (! $result)
        {
            $this->error=$this->db->error();
            dol_syslog("Facture::Deleteline Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        $line=new FactureLigne($this->db);

        // For triggers
        $line->fetch($rowid);

        if ($line->delete() > 0)
        {
        	$result=$this->update_price(1);

        	if ($result > 0)
        	{
        		$this->db->commit();
        		return 1;
        	}
        	else
        	{
        		$this->db->rollback();
        		$this->error=$this->db->lasterror();
        		return -1;
        	}
        }
        else
        {
        	$this->db->rollback();
        	$this->error=$this->db->lasterror();
        	return -1;
        }
    }

    /**
     * 		\brief     	Applique une remise relative
     * 		\param     	user		User qui positionne la remise
     * 		\param     	remise
     *		\return		int 		<0 si ko, >0 si ok
     */
    function set_remise($user, $remise)
    {
        $remise=trim($remise)?trim($remise):0;

        if ($user->rights->facture->creer)
        {
            $remise=price2num($remise);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
            $sql.= ' SET remise_percent = '.$remise;
            $sql.= ' WHERE rowid = '.$this->id;
            $sql.= ' AND fk_statut = 0 ;';

            if ($this->db->query($sql))
            {
                $this->remise_percent = $remise;
                $this->update_price(1);
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                return -1;
            }
        }
    }


    /**
     * 		\brief     	Applique une remise absolue
     * 		\param     	user 		User qui positionne la remise
     * 		\param     	remise
     *		\return		int 		<0 si ko, >0 si ok
     */
    function set_remise_absolue($user, $remise)
    {
        $remise=trim($remise)?trim($remise):0;

        if ($user->rights->facture->creer)
        {
            $remise=price2num($remise);

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
            $sql.= ' SET remise_absolue = '.$remise;
            $sql.= ' WHERE rowid = '.$this->id;
            $sql.= ' AND fk_statut = 0 ;';

            dol_syslog("Facture::set_remise_absolue sql=$sql");

            if ($this->db->query($sql))
            {
                $this->remise_absolue = $remise;
                $this->update_price(1);
                return 1;
            }
            else
            {
                $this->error=$this->db->error();
                return -1;
            }
        }
    }


    /**
     * 	Return amount of payments already done
     *	@return		int		Amount of payment already done, <0 if KO
     */
    function getSommePaiement()
    {
        $table='paiement_facture';
        $field='fk_facture';
        if ($this->element == 'facture_fourn' || $this->element == 'invoice_supplier')
        {
            $table='paiementfourn_facturefourn';
            $field='fk_facturefourn';
        }

        $sql = 'SELECT sum(amount) as amount';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$table;
        $sql.= ' WHERE '.$field.' = '.$this->id;

        dol_syslog("Facture::getSommePaiement sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj = $this->db->fetch_object($resql);
            $this->db->free($resql);
            return $obj->amount;
        }
        else
        {
            $this->error=$this->db->lasterror();
            return -1;
        }
    }

    /**
     *  Return list of payments
     *  @return     Array with list of payments
     */
    function getListOfPayments($filtertype='')
    {
        $retarray=array();

        $table='paiement_facture';
        $table2='paiement';
        $field='fk_facture';
        $field2='fk_paiement';
        if ($this->element == 'facture_fourn' || $this->element == 'invoice_supplier')
        {
            $table='paiementfourn_facturefourn';
            $table2='paiementfourn';
            $field='fk_facturefourn';
            $field2='fk_paiementfourn';
        }

        $sql = 'SELECT pf.amount, p.fk_paiement, p.datep, t.code';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$table.' as pf, '.MAIN_DB_PREFIX.$table2.' as p, '.MAIN_DB_PREFIX.'c_paiement as t';
        $sql.= ' WHERE pf.'.$field.' = '.$this->id;
        $sql.= ' AND pf.'.$field2.' = p.rowid';
        $sql.= ' AND p.fk_paiement = t.id';
        if ($filtertype) $sql.=" AND t.code='PRE'";

        dol_syslog("Facture::getListOfPayments sql=".$sql, LOG_DEBUG);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i=0;
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);
                $retarray[]=array('amount'=>$obj->amount,'type'=>$obj->code, 'date'=>$obj->datep);
                $i++;
            }
            $this->db->free($resql);
            return $retarray;
        }
        else
        {
            $this->error=$this->db->lasterror();
            dol_print_error($this->db);
            return array();
        }
    }


    /**
     *    	Return amount (with tax) of all credit notes and deposits invoices used by invoice
     *		@return		int			<0 if KO, Sum of credit notes and deposits amount otherwise
     */
    function getSumCreditNotesUsed()
    {
        require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

        $discountstatic=new DiscountAbsolute($this->db);
        $result=$discountstatic->getSumCreditNotesUsed($this);
        if ($result >= 0)
        {
            return $result;
        }
        else
        {
            $this->error=$discountstatic->error;
            return -1;
        }
    }

    /**
     *    	Return amount (with tax) of all deposits invoices used by invoice
     *		@return		int			<0 if KO, Sum of deposits amount otherwise
     */
    function getSumDepositsUsed()
    {
        require_once(DOL_DOCUMENT_ROOT.'/core/class/discount.class.php');

        $discountstatic=new DiscountAbsolute($this->db);
        $result=$discountstatic->getSumDepositsUsed($this);
        if ($result >= 0)
        {
            return $result;
        }
        else
        {
            $this->error=$discountstatic->error;
            return -1;
        }
    }

    /**
     * 	\brief     	Renvoie tableau des ids de facture avoir issus de la facture
     *	\return		array		Tableau d'id de factures avoirs
     */
    function getListIdAvoirFromInvoice()
    {
        $idarray=array();

        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
        $sql.= ' WHERE fk_facture_source = '.$this->id;
        $sql.= ' AND type = 2';
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num)
            {
                $row = $this->db->fetch_row($resql);
                $idarray[]=$row[0];
                $i++;
            }
        }
        else
        {
            dol_print_error($this->db);
        }
        return $idarray;
    }

    /**
     * 	\brief     	Renvoie l'id de la facture qui la remplace
     *	\param		option		filtre sur statut ('', 'validated', ...)
     *	\return		int			<0 si KO, 0 si aucune facture ne remplace, id facture sinon
     */
    function getIdReplacingInvoice($option='')
    {
        $sql = 'SELECT rowid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.$this->table_element;
        $sql.= ' WHERE fk_facture_source = '.$this->id;
        $sql.= ' AND type < 2';
        if ($option == 'validated') $sql.= ' AND fk_statut = 1';
        // PROTECTION BAD DATA
        // Au cas ou base corrompue et qu'il y a une facture de remplacement validee
        // et une autre non, on donne priorite a la validee.
        // Ne devrait pas arriver (sauf si acces concurrentiel et que 2 personnes
        // ont cree en meme temps une facture de remplacement pour la meme facture)
        $sql.= ' ORDER BY fk_statut DESC';

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $obj = $this->db->fetch_object($resql);
            if ($obj)
            {
                // Si il y en a
                return $obj->rowid;
            }
            else
            {
                // Si aucune facture ne remplace
                return 0;
            }
        }
        else
        {
            return -1;
        }
    }

    /**
     *    \brief      Retourne le libelle du type de facture
     *    \return     string        Libelle
     */
    function getLibType()
    {
        global $langs;
        if ($this->type == 0) return $langs->trans("InvoiceStandard");
        if ($this->type == 1) return $langs->trans("InvoiceReplacement");
        if ($this->type == 2) return $langs->trans("InvoiceAvoir");
        if ($this->type == 3) return $langs->trans("InvoiceDeposit");
        if ($this->type == 4) return $langs->trans("InvoiceProForma");
        return $langs->trans("Unknown");
    }


    /**
     *  Return label of object status
     *  @param      mode            0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=short label + picto
     *  @param      alreadypaid     0=No payment already done, 1=Some payments already done
     *  @return     string          Label
     */
    function getLibStatut($mode=0,$alreadypaid=-1)
    {
        return $this->LibStatut($this->paye,$this->statut,$mode,$alreadypaid,$this->type);
    }

    /**
     *    	\brief      Renvoi le libelle d'un statut donne
     *    	\param      paye          	Etat paye
     *    	\param      statut        	Id statut
     *    	\param      mode          	0=libelle long, 1=libelle court, 2=Picto + Libelle court, 3=Picto, 4=Picto + Libelle long, 5=Libelle court + Picto
     *		\param		alreadypaid	    Montant deja paye
     *		\param		type			Type facture
     *    	\return     string        	Libelle du statut
     */
    function LibStatut($paye,$statut,$mode=0,$alreadypaid=-1,$type=0)
    {
        global $langs;
        $langs->load('bills');

        //print "$paye,$statut,$mode,$alreadypaid,$type";
        if ($mode == 0)
        {
            $prefix='';
            if (! $paye)
            {
                if ($statut == 0) return $langs->trans('Bill'.$prefix.'StatusDraft');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusClosedUnpaid');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return $langs->trans('Bill'.$prefix.'StatusClosedPaidPartially');
                if ($alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusNotPaid');
                return $langs->trans('Bill'.$prefix.'StatusStarted');
            }
            else
            {
                if ($type == 2) return $langs->trans('Bill'.$prefix.'StatusPaidBackOrConverted');
                elseif ($type == 3) return $langs->trans('Bill'.$prefix.'StatusConverted');
                else return $langs->trans('Bill'.$prefix.'StatusPaid');
            }
        }
        if ($mode == 1)
        {
            $prefix='Short';
            if (! $paye)
            {
                if ($statut == 0) return $langs->trans('Bill'.$prefix.'StatusDraft');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusCanceled');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return $langs->trans('Bill'.$prefix.'StatusClosedPaidPartially');
                if ($alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusNotPaid');
                return $langs->trans('Bill'.$prefix.'StatusStarted');
            }
            else
            {
                if ($type == 2) return $langs->trans('Bill'.$prefix.'StatusPaidBackOrConverted');
                elseif ($type == 3) return $langs->trans('Bill'.$prefix.'StatusConverted');
                else return $langs->trans('Bill'.$prefix.'StatusPaid');
            }
        }
        if ($mode == 2)
        {
            $prefix='Short';
            if (! $paye)
            {
                if ($statut == 0) return img_picto($langs->trans('BillStatusDraft'),'statut0').' '.$langs->trans('Bill'.$prefix.'StatusDraft');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return img_picto($langs->trans('StatusCanceled'),'statut5').' '.$langs->trans('Bill'.$prefix.'StatusCanceled');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return img_picto($langs->trans('BillStatusClosedPaidPartially'),'statut7').' '.$langs->trans('Bill'.$prefix.'StatusClosedPaidPartially');
                if ($alreadypaid <= 0) return img_picto($langs->trans('BillStatusNotPaid'),'statut1').' '.$langs->trans('Bill'.$prefix.'StatusNotPaid');
                return img_picto($langs->trans('BillStatusStarted'),'statut3').' '.$langs->trans('Bill'.$prefix.'StatusStarted');
            }
            else
            {
                if ($type == 2) return img_picto($langs->trans('BillStatusPaidBackOrConverted'),'statut6').' '.$langs->trans('Bill'.$prefix.'StatusPaidBackOrConverted');
                elseif ($type == 3) return img_picto($langs->trans('BillStatusConverted'),'statut6').' '.$langs->trans('Bill'.$prefix.'StatusConverted');
                else return img_picto($langs->trans('BillStatusPaid'),'statut6').' '.$langs->trans('Bill'.$prefix.'StatusPaid');
            }
        }
        if ($mode == 3)
        {
            $prefix='Short';
            if (! $paye)
            {
                if ($statut == 0) return img_picto($langs->trans('BillStatusDraft'),'statut0');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return img_picto($langs->trans('BillStatusCanceled'),'statut5');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return img_picto($langs->trans('BillStatusClosedPaidPartially'),'statut7');
                if ($alreadypaid <= 0) return img_picto($langs->trans('BillStatusNotPaid'),'statut1');
                return img_picto($langs->trans('BillStatusStarted'),'statut3');
            }
            else
            {
                if ($type == 2) return img_picto($langs->trans('BillStatusPaidBackOrConverted'),'statut6');
                elseif ($type == 3) return img_picto($langs->trans('BillStatusConverted'),'statut6');
                else return img_picto($langs->trans('BillStatusPaid'),'statut6');
            }
        }
        if ($mode == 4)
        {
            if (! $paye)
            {
                if ($statut == 0) return img_picto($langs->trans('BillStatusDraft'),'statut0').' '.$langs->trans('BillStatusDraft');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return img_picto($langs->trans('BillStatusCanceled'),'statut5').' '.$langs->trans('Bill'.$prefix.'StatusCanceled');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return img_picto($langs->trans('BillStatusClosedPaidPartially'),'statut7').' '.$langs->trans('Bill'.$prefix.'StatusClosedPaidPartially');
                if ($alreadypaid <= 0) return img_picto($langs->trans('BillStatusNotPaid'),'statut1').' '.$langs->trans('BillStatusNotPaid');
                return img_picto($langs->trans('BillStatusStarted'),'statut3').' '.$langs->trans('BillStatusStarted');
            }
            else
            {
                if ($type == 2) return img_picto($langs->trans('BillStatusPaidBackOrConverted'),'statut6').' '.$langs->trans('BillStatusPaidBackOrConverted');
                elseif ($type == 3) return img_picto($langs->trans('BillStatusConverted'),'statut6').' '.$langs->trans('BillStatusConverted');
                else return img_picto($langs->trans('BillStatusPaid'),'statut6').' '.$langs->trans('BillStatusPaid');
            }
        }
        if ($mode == 5)
        {
            $prefix='Short';
            if (! $paye)
            {
                if ($statut == 0) return $langs->trans('Bill'.$prefix.'StatusDraft').' '.img_picto($langs->trans('BillStatusDraft'),'statut0');
                if (($statut == 3 || $statut == 2) && $alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusCanceled').' '.img_picto($langs->trans('BillStatusCanceled'),'statut5');
                if (($statut == 3 || $statut == 2) && $alreadypaid > 0) return $langs->trans('Bill'.$prefix.'StatusClosedPaidPartially').' '.img_picto($langs->trans('BillStatusClosedPaidPartially'),'statut7');
                if ($alreadypaid <= 0) return $langs->trans('Bill'.$prefix.'StatusNotPaid').' '.img_picto($langs->trans('BillStatusNotPaid'),'statut1');
                return $langs->trans('Bill'.$prefix.'StatusStarted').' '.img_picto($langs->trans('BillStatusStarted'),'statut3');
            }
            else
            {
                if ($type == 2) return $langs->trans('Bill'.$prefix.'StatusPaidBackOrConverted').' '.img_picto($langs->trans('BillStatusPaidBackOrConverted'),'statut6');
                elseif ($type == 3) return $langs->trans('Bill'.$prefix.'StatusConverted').' '.img_picto($langs->trans('BillStatusConverted'),'statut6');
                else return $langs->trans('Bill'.$prefix.'StatusPaid').' '.img_picto($langs->trans('BillStatusPaid'),'statut6');
            }
        }
    }

    /**
     *      Return next reference of invoice not already used (or last reference)
     *      according to numbering module defined into constant FACTURE_ADDON
     *      @param	   soc  		           objet company
     *      @param     mode                    'next' for next value or 'last' for last value
     *      @return    string                  free ref or last ref
     */
    function getNextNumRef($soc,$mode='next')
    {
        global $conf, $db, $langs;
        $langs->load("bills");

        // Clean parameters (if not defined or using deprecated value)
        if (empty($conf->global->FACTURE_ADDON)) $conf->global->FACTURE_ADDON='mod_facture_terre';
        else if ($conf->global->FACTURE_ADDON=='terre') $conf->global->FACTURE_ADDON='mod_facture_terre';
        else if ($conf->global->FACTURE_ADDON=='mercure') $conf->global->FACTURE_ADDON='mod_facture_mercure';

        $mybool=false;

        $file = $conf->global->FACTURE_ADDON.".php";
        $classname = $conf->global->FACTURE_ADDON;
        // Include file with class
        foreach ($conf->file->dol_document_root as $dirroot)
        {
            $dir = $dirroot."/includes/modules/facture/";
            // Load file with numbering class (if found)
            $mybool|=@include_once($dir.$file);
        }

        // For compatibility
        if (! $mybool)
        {
            $file = $conf->global->FACTURE_ADDON."/".$conf->global->FACTURE_ADDON.".modules.php";
            $classname = "mod_facture_".$conf->global->FACTURE_ADDON;
            // Include file with class
            foreach ($conf->file->dol_document_root as $dirroot)
            {
                $dir = $dirroot."/includes/modules/facture/";
                // Load file with numbering class (if found)
                $mybool|=@include_once($dir.$file);
            }
        }
        //print "xx".$mybool.$dir.$file."-".$classname;

        if (! $mybool)
        {
            dol_print_error('',"Failed to include file ".$file);
            return '';
        }

        $obj = new $classname();

        $numref = "";
        $numref = $obj->getNumRef($soc,$this,$mode);

        if ( $numref != "")
        {
            return $numref;
        }
        else
        {
            //dol_print_error($db,"Facture::getNextNumRef ".$obj->error);
            return false;
        }
    }

    /**
     *      \brief     Charge les informations de l'onglet info dans l'objet facture
     *      \param     id       	Id de la facture a charger
     */
    function info($id)
    {
        $sql = 'SELECT c.rowid, datec, date_valid as datev, tms as datem,';
        $sql.= ' fk_user_author, fk_user_valid';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'facture as c';
        $sql.= ' WHERE c.rowid = '.$id;

        $result=$this->db->query($sql);
        if ($result)
        {
            if ($this->db->num_rows($result))
            {
                $obj = $this->db->fetch_object($result);
                $this->id = $obj->rowid;
                if ($obj->fk_user_author)
                {
                    $cuser = new User($this->db);
                    $cuser->fetch($obj->fk_user_author);
                    $this->user_creation     = $cuser;
                }
                if ($obj->fk_user_valid)
                {
                    $vuser = new User($this->db);
                    $vuser->fetch($obj->fk_user_valid);
                    $this->user_validation = $vuser;
                }
                $this->date_creation     = $this->db->jdate($obj->datec);
                $this->date_modification = $this->db->jdate($obj->datem);
                $this->date_validation   = $this->db->jdate($obj->datev);	// Should be in log table
            }
            $this->db->free($result);
        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     *  \brief      Change les conditions de reglement de la facture
     *  \param      cond_reglement_id      	Id de la nouvelle condition de reglement
     * 	\param		date					Date to force payment term
     *  \return     int                    	>0 si ok, <0 si ko
     */
    function cond_reglement($cond_reglement_id,$date='')
    {
        if ($this->statut >= 0 && $this->paye == 0)
        {
            // Define cond_reglement_id and datelim
            if (strval($date) != '')
            {
                $datelim=$date;
                $cond_reglement_id=0;
            }
            else
            {
                $datelim=$this->calculate_date_lim_reglement($cond_reglement_id);
                $cond_reglement_id=$cond_reglement_id;
            }

            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
            $sql.= ' SET fk_cond_reglement = '.$cond_reglement_id.',';
            $sql.= ' date_lim_reglement='.$this->db->idate($datelim);
            $sql.= ' WHERE rowid='.$this->id;

            dol_syslog('Facture::cond_reglement sql='.$sql, LOG_DEBUG);
            if ( $this->db->query($sql) )
            {
                $this->cond_reglement_id = $cond_reglement_id;
                return 1;
            }
            else
            {
                dol_syslog('Facture::cond_reglement Erreur '.$sql.' - '.$this->db->error());
                $this->error=$this->db->error();
                return -1;
            }
        }
        else
        {
            dol_syslog('Facture::cond_reglement, etat facture incompatible');
            $this->error='Entity status not compatible '.$this->statut.' '.$this->paye;
            return -2;
        }
    }


    /**
     *   \brief      Change le mode de reglement
     *   \param      mode        Id du nouveau mode
     *   \return     int         >0 si ok, <0 si ko
     */
    function mode_reglement($mode_reglement_id)
    {
        dol_syslog('Facture::mode_reglement('.$mode_reglement_id.')', LOG_DEBUG);
        if ($this->statut >= 0 && $this->paye == 0)
        {
            $sql = 'UPDATE '.MAIN_DB_PREFIX.'facture';
            $sql .= ' SET fk_mode_reglement = '.$mode_reglement_id;
            $sql .= ' WHERE rowid='.$this->id;
            if ( $this->db->query($sql) )
            {
                $this->mode_reglement_id = $mode_reglement_id;
                return 1;
            }
            else
            {
                dol_syslog('Facture::mode_reglement Erreur '.$sql.' - '.$this->db->error());
                $this->error=$this->db->error();
                return -1;
            }
        }
        else
        {
            dol_syslog('Facture::mode_reglement, etat facture incompatible');
            $this->error='Etat facture incompatible '.$this->statut.' '.$this->paye;
            return -2;
        }
    }


    /**
     *   \brief      Renvoi si les lignes de facture sont ventilees et/ou exportees en compta
     *   \param      user        Utilisateur creant la demande
     *   \return     int         <0 if KO, 0=no, 1=yes
     */
    function getVentilExportCompta()
    {
        // On verifie si les lignes de factures ont ete exportees en compta et/ou ventilees
        $ventilExportCompta = 0 ;
        for ($i = 0 ; $i < sizeof($this->lines) ; $i++)
        {
            if ($this->lines[$i]->export_compta <> 0 && $this->lines[$i]->code_ventilation <> 0)
            {
                $ventilExportCompta++;
            }
        }

        if ($ventilExportCompta <> 0)
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }


    /**
     *  Return if an invoice can be deleted
     *	Rule is:
     *	If hidden option FACTURE_CAN_BE_REMOVED is on, we can
     *  If invoice has a definitive ref, is last, without payment and not dipatched into accountancy -> yes end of rule
     *  If invoice is draft and ha a temporary ref -> yes
     *  @return    int         <0 if KO, 0=no, 1=yes
     */
    function is_erasable()
    {
        global $conf;

        if (! empty($conf->global->FACTURE_CAN_BE_REMOVED)) return 1;

        // on verifie si la facture est en numerotation provisoire
        $facref = substr($this->ref, 1, 4);

        // If not a draft invoice and not temporary invoice
        if ($facref != 'PROV')
        {
            $maxfacnumber = $this->getNextNumRef($this->client,'last');
            $ventilExportCompta = $this->getVentilExportCompta();
            // Si derniere facture et si non ventilee, on peut supprimer
            if ($maxfacnumber == $this->ref && $ventilExportCompta == 0)
            {
                return 1;
            }
        }
        else if ($this->statut == 0 && $facref == 'PROV') // Si facture brouillon et provisoire
        {
            return 1;
        }

        return 0;
    }


    /**
     *	\brief     	Renvoi liste des factures remplacables
     *				Statut validee ou abandonnee pour raison autre + non payee + aucun paiement + pas deja remplacee
     *	\param		socid		Id societe
     *	\return    	array		Tableau des factures ('id'=>id, 'ref'=>ref, 'status'=>status, 'paymentornot'=>0/1)
     */
    function list_replacable_invoices($socid=0)
    {
        global $conf;

        $return = array();

        $sql = "SELECT f.rowid as rowid, f.facnumber, f.fk_statut,";
        $sql.= " ff.rowid as rowidnext";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid = pf.fk_facture";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture as ff ON f.rowid = ff.fk_facture_source";
        $sql.= " WHERE (f.fk_statut = 1 OR (f.fk_statut = 3 AND f.close_code = 'abandon'))";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.paye = 0";					// Pas classee payee completement
        $sql.= " AND pf.fk_paiement IS NULL";		// Aucun paiement deja fait
        $sql.= " AND ff.fk_statut IS NULL";			// Renvoi vrai si pas facture de remplacement
        if ($socid > 0) $sql.=" AND f.fk_soc = ".$socid;
        $sql.= " ORDER BY f.facnumber";

        dol_syslog("Facture::list_replacable_invoices sql=$sql");
        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $return[$obj->rowid]=array(	'id' => $obj->rowid,
				'ref' => $obj->facnumber,
				'status' => $obj->fk_statut);
            }
            //print_r($return);
            return $return;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("Facture::list_replacable_invoices ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *  	\brief     	Renvoi liste des factures qualifiables pour correction par avoir
     *					Les factures qui respectent les regles suivantes sont retournees:
     * 					(validee + paiement en cours) ou classee (payee completement ou payee partiellement) + pas deja remplacee + pas deja avoir
     *		\param		socid		Id societe
     *   	\return    	array		Tableau des factures ($id => $ref)
     */
    function list_qualified_avoir_invoices($socid=0)
    {
        global $conf;

        $return = array();

        $sql = "SELECT f.rowid as rowid, f.facnumber, f.fk_statut, pf.fk_paiement";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."paiement_facture as pf ON f.rowid = pf.fk_facture";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."facture as ff ON (f.rowid = ff.fk_facture_source AND ff.type=1)";
        $sql.= " WHERE f.entity = ".$conf->entity;
        $sql.= " AND f.fk_statut in (1,2)";
        //  $sql.= " WHERE f.fk_statut >= 1";
        //	$sql.= " AND (f.paye = 1";				// Classee payee completement
        //	$sql.= " OR f.close_code IS NOT NULL)";	// Classee payee partiellement
        $sql.= " AND ff.type IS NULL";			// Renvoi vrai si pas facture de remplacement
        $sql.= " AND f.type != 2";				// Type non 2 si facture non avoir
        if ($socid > 0) $sql.=" AND f.fk_soc = ".$socid;
        $sql.= " ORDER BY f.facnumber";

        dol_syslog("Facture::list_qualified_avoir_invoices sql=$sql");
        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $qualified=0;
                if ($obj->fk_statut == 1) $qualified=1;
                if ($obj->fk_statut == 2) $qualified=1;
                if ($qualified)
                {
                    //$ref=$obj->facnumber;
                    $paymentornot=($obj->fk_paiement?1:0);
                    $return[$obj->rowid]=$paymentornot;
                }
            }

            return $return;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("Facture::list_avoir_invoices ".$this->error, LOG_ERR);
            return -1;
        }
    }


    /**
     *   \brief      Create a withdrawal request for a standing order
     *   \param      user        User asking standing order
     *   \return     int         <0 if KO, >0 if OK
     */
    function demande_prelevement($user)
    {
        dol_syslog("Facture::demande_prelevement", LOG_DEBUG);

        $soc = new Societe($this->db);
        $soc->id = $this->socid;
        $soc->load_ban();

        if ($this->statut > 0 && $this->paye == 0)
        {
            $sql = 'SELECT count(*)';
            $sql.= ' FROM '.MAIN_DB_PREFIX.'prelevement_facture_demande';
            $sql.= ' WHERE fk_facture = '.$this->id;
            $sql.= ' AND traite = 0';

            $resql=$this->db->query($sql);
            if ($resql)
            {
                $row = $this->db->fetch_row($resql);
                if ($row[0] == 0)
                {
                    $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'prelevement_facture_demande';
                    $sql .= ' (fk_facture, amount, date_demande, fk_user_demande, code_banque, code_guichet, number, cle_rib)';
                    $sql .= ' VALUES ('.$this->id;
                    $sql .= ",'".price2num($this->total_ttc)."'";
                    $sql .= ",".$this->db->idate(mktime()).",".$user->id;
                    $sql .= ",'".$soc->bank_account->code_banque."'";
                    $sql .= ",'".$soc->bank_account->code_guichet."'";
                    $sql .= ",'".$soc->bank_account->number."'";
                    $sql .= ",'".$soc->bank_account->cle_rib."')";
                    if ( $this->db->query($sql))
                    {
                        return 1;
                    }
                    else
                    {
                        $this->error=$this->db->error();
                        dol_syslog('Facture::DemandePrelevement Erreur');
                        return -1;
                    }
                }
                else
                {
                    $this->error="A request already exists";
                    dol_syslog('Facture::DemandePrelevement Impossible de creer une demande, demande deja en cours');
                }
            }
            else
            {
                $this->error=$this->db->error();
                dol_syslog('Facture::DemandePrelevement Erreur -2');
                return -2;
            }
        }
        else
        {
            $this->error="Status of invoice does not allow this";
            dol_syslog("Facture::DemandePrelevement ".$this->error." $this->statut, $this->paye, $this->mode_reglement_id");
            return -3;
        }
    }

    /**
     *  Supprime une demande de prelevement
     *  @param     user         utilisateur creant la demande
     *  @param     did          id de la demande a supprimer
     */
    function demande_prelevement_delete($user, $did)
    {
        $sql = 'DELETE FROM '.MAIN_DB_PREFIX.'prelevement_facture_demande';
        $sql .= ' WHERE rowid = '.$did;
        $sql .= ' AND traite = 0';
        if ( $this->db->query( $sql) )
        {
            return 0;
        }
        else
        {
            dol_syslog('Facture::DemandePrelevement Erreur');
            return -1;
        }
    }


    /**
     *      Load indicators for dashboard (this->nbtodo and this->nbtodolate)
     *      @param      user                Objet user
     *      @return     int                 <0 if KO, >0 if OK
     */
    function load_board($user)
    {
        global $conf, $user;

        $now=dol_now();

        $this->nbtodo=$this->nbtodolate=0;
        $clause = " WHERE";

        $sql = "SELECT f.rowid, f.date_lim_reglement as datefin";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
        {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON f.fk_soc = sc.fk_soc";
            $sql.= " WHERE sc.fk_user = " .$user->id;
            $clause = " AND";
        }
        $sql.= $clause." f.paye=0";
        $sql.= " AND f.entity = ".$conf->entity;
        $sql.= " AND f.fk_statut = 1";
        if ($user->societe_id) $sql.= " AND f.fk_soc = ".$user->societe_id;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $this->nbtodo++;
                if ($this->db->jdate($obj->datefin) < ($now - $conf->facture->client->warning_delay)) $this->nbtodolate++;
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


    /* gestion des contacts d'une facture */

    /**
     *      \brief      Retourne id des contacts clients de facturation
     *      \return     array       Liste des id contacts facturation
     */
    function getIdBillingContact()
    {
        return $this->getIdContact('external','BILLING');
    }

    /**
     *      \brief      Retourne id des contacts clients de livraison
     *      \return     array       Liste des id contacts livraison
     */
    function getIdShippingContact()
    {
        return $this->getIdContact('external','SHIPPING');
    }


    /**
     *		Initialise an example of invoice with random values
     *		Used to build previews or test instances
     */
    function initAsSpecimen()
    {
        global $user,$langs,$conf;

        $prodids = array();
        $sql = "SELECT rowid";
        $sql.= " FROM ".MAIN_DB_PREFIX."product";
        $sql.= " WHERE entity = ".$conf->entity;
        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num_prods = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num_prods)
            {
                $i++;
                $row = $this->db->fetch_row($resql);
                $prodids[$i] = $row[0];
            }
        }

        // Initialize parameters
        $this->id=0;
        $this->ref = 'SPECIMEN';
        $this->specimen=1;
        $this->socid = 1;
        $this->date = time();
        $this->date_lim_reglement=$this->date+3600*24*30;
        $this->cond_reglement_id   = 1;
        $this->cond_reglement_code = 'RECEP';
        $this->mode_reglement_id   = 7;
        $this->mode_reglement_code = '';  // No particular payment mode defined
        $this->note_public='This is a comment (public)';
        $this->note='This is a comment (private)';
        // Lines
        $nbp = 5;
        $xnbp = 0;
        while ($xnbp < $nbp)
        {
            $line=new FactureLigne($this->db);
            $line->desc=$langs->trans("Description")." ".$xnbp;
            $line->qty=1;
            $line->subprice=100;
            $line->price=100;
            $line->tva_tx=19.6;
            $line->localtax1_tx=0;
            $line->localtax2_tx=0;
            $line->remise_percent=10;
            $line->total_ht=90;
            $line->total_ttc=107.64;    // 90 * 1.196
            $line->total_tva=17.64;
            $prodid = rand(1, $num_prods);
            $line->fk_product=$prodids[$prodid];

            $this->lines[$xnbp]=$line;

            $xnbp++;
        }
        // Add a line "offered"
        $line=new FactureLigne($this->db);
        $line->desc=$langs->trans("Description")." ".$xnbp;
        $line->qty=1;
        $line->subprice=100;
        $line->price=100;
        $line->tva_tx=19.6;
        $line->localtax1_tx=0;
        $line->localtax2_tx=0;
        $line->remise_percent=100;
        $line->total_ht=0;
        $line->total_ttc=0;    // 90 * 1.196
        $line->total_tva=0;
        $prodid = rand(1, $num_prods);
        $line->fk_product=$prodids[$prodid];

        $this->lines[$xnbp]=$line;

        $xnbp++;

        $this->amount_ht      = $xnbp*90;
        $this->total_ht       = $xnbp*90;
        $this->total_tva      = $xnbp*90*0.196;
        $this->total_ttc      = $xnbp*90*1.196;
    }

    /**
     *      \brief      Charge indicateurs this->nb de tableau de bord
     *      \return     int         <0 si ko, >0 si ok
     */
    function load_state_board()
    {
        global $conf, $user;

        $this->nb=array();

        $clause = "WHERE";

        $sql = "SELECT count(f.rowid) as nb";
        $sql.= " FROM ".MAIN_DB_PREFIX."facture as f";
        $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe as s ON f.fk_soc = s.rowid";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
        {
            $sql.= " LEFT JOIN ".MAIN_DB_PREFIX."societe_commerciaux as sc ON s.rowid = sc.fk_soc";
            $sql.= " WHERE sc.fk_user = " .$user->id;
            $clause = "AND";
        }
        $sql.= " ".$clause." f.entity = ".$conf->entity;

        $resql=$this->db->query($sql);
        if ($resql)
        {
            while ($obj=$this->db->fetch_object($resql))
            {
                $this->nb["invoices"]=$obj->nb;
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

    /**
     * 	Return an array of invoice lines
     */
    function getLinesArray()
    {
        $sql = 'SELECT l.rowid, l.description, l.fk_product, l.product_type, l.qty, l.tva_tx,';
        $sql.= ' l.fk_remise_except,';
        $sql.= ' l.remise_percent, l.subprice, l.info_bits, l.rang, l.special_code,';
        $sql.= ' l.total_ht, l.total_tva, l.total_ttc,';
        $sql.= ' l.date_start,';
        $sql.= ' l.date_end,';
        $sql.= ' l.product_type,';
        $sql.= ' p.ref as product_ref, p.fk_product_type, p.label as product_label,';
        $sql.= ' p.description as product_desc';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'facturedet as l';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product p ON l.fk_product=p.rowid';
        $sql.= ' WHERE l.fk_facture = '.$this->id;
        $sql.= ' ORDER BY l.rang ASC, l.rowid';

        $resql = $this->db->query($sql);
        if ($resql)
        {
            $num = $this->db->num_rows($resql);
            $i = 0;

            while ($i < $num)
            {
                $obj = $this->db->fetch_object($resql);

                $this->lines[$i]->id				= $obj->rowid;
                $this->lines[$i]->description 		= $obj->description;
                $this->lines[$i]->fk_product		= $obj->fk_product;
                $this->lines[$i]->ref				= $obj->product_ref;
                $this->lines[$i]->product_label		= $obj->product_label;
                $this->lines[$i]->product_desc		= $obj->product_desc;
                $this->lines[$i]->fk_product_type	= $obj->fk_product_type;
                $this->lines[$i]->product_type		= $obj->product_type;
                $this->lines[$i]->qty				= $obj->qty;
                $this->lines[$i]->subprice			= $obj->subprice;
                $this->lines[$i]->fk_remise_except 	= $obj->fk_remise_except;
                $this->lines[$i]->remise_percent	= $obj->remise_percent;
                $this->lines[$i]->tva_tx			= $obj->tva_tx;
                $this->lines[$i]->info_bits			= $obj->info_bits;
                $this->lines[$i]->total_ht			= $obj->total_ht;
                $this->lines[$i]->total_tva			= $obj->total_tva;
                $this->lines[$i]->total_ttc			= $obj->total_ttc;
                $this->lines[$i]->special_code		= $obj->special_code;
                $this->lines[$i]->rang				= $obj->rang;
                $this->lines[$i]->date_start		= $this->db->jdate($obj->date_start);
                $this->lines[$i]->date_end			= $this->db->jdate($obj->date_end);

                $i++;
            }
            $this->db->free($resql);

            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("Error sql=$sql, error=".$this->error,LOG_ERR);
            return -1;
        }
    }

}



/**
 *	\class      	FactureLigne
 *	\brief      	Classe permettant la gestion des lignes de factures
 *	\remarks		Gere des lignes de la table llx_facturedet
 */
class FactureLigne
{
    var $db;
    var $error;
    
    var $oldline;

    //! From llx_facturedet
    var $rowid;
    //! Id facture
    var $fk_facture;
    //! Id parent line
    var $fk_parent_line;
    //! Description ligne
    var $desc;
    var $fk_product;		// Id of predefined product
    var $product_type = 0;	// Type 0 = product, 1 = Service

    var $qty;				// Quantity (example 2)
    var $tva_tx;			// Taux tva produit/service (example 19.6)
    var $localtax1_tx;		// Local tax 1
    var $localtax2_tx;		// Local tax 2
    var $subprice;      	// P.U. HT (example 100)
    var $remise_percent;	// % de la remise ligne (example 20%)
    var $fk_remise_except;	// Link to line into llx_remise_except
    var $rang = 0;

    var $info_bits = 0;		// Liste d'options cumulables:
    // Bit 0:	0 si TVA normal - 1 si TVA NPR
    // Bit 1:	0 si ligne normal - 1 si bit discount (link to line into llx_remise_except)

    var $special_code;	// Liste d'options non cumulabels:
    // 1: frais de port
    // 2: ecotaxe
    // 3: ??

    var $origin;
    var $origin_id;

    //! Total HT  de la ligne toute quantite et incluant la remise ligne
    var $total_ht;
    //! Total TVA  de la ligne toute quantite et incluant la remise ligne
    var $total_tva;
    var $total_localtax1; //Total Local tax 1 de la ligne
    var $total_localtax2; //Total Local tax 2 de la ligne
    //! Total TTC de la ligne toute quantite et incluant la remise ligne
    var $total_ttc;

    var $fk_code_ventilation = 0;
    var $fk_export_compta = 0;

    var $date_start;
    var $date_end;

    // Ne plus utiliser
    var $price;         	// P.U. HT apres remise % de ligne (exemple 80)
    var $remise;			// Montant calcule de la remise % sur PU HT (exemple 20)

    // From llx_product
    var $ref;				// Product ref (deprecated)
    var $product_ref;       // Product ref
    var $libelle;      		// Product label (deprecated)
    var $product_label;     // Product label
    var $product_desc;  	// Description produit

    var $skip_update_total; // Skip update price total for special lines


    /**
     *  \brief     Constructeur d'objets ligne de facture
     *  \param     DB      handler d'acces base de donnee
     */
    function FactureLigne($DB)
    {
        $this->db= $DB ;
    }

    /**
     *	\brief     Recupere l'objet ligne de facture
     *	\param     rowid           id de la ligne de facture
     */
    function fetch($rowid)
    {
        $sql = 'SELECT fd.rowid, fd.fk_facture, fd.fk_parent_line, fd.fk_product, fd.product_type, fd.description, fd.price, fd.qty, fd.tva_tx,';
        $sql.= ' fd.localtax1_tx, fd. localtax2_tx, fd.remise, fd.remise_percent, fd.fk_remise_except, fd.subprice,';
        $sql.= ' fd.date_start as date_start, fd.date_end as date_end,';
        $sql.= ' fd.info_bits, fd.total_ht, fd.total_tva, fd.total_ttc, fd.rang,';
        $sql.= ' fd.fk_code_ventilation, fd.fk_export_compta,';
        $sql.= ' p.ref as product_ref, p.label as product_libelle, p.description as product_desc';
        $sql.= ' FROM '.MAIN_DB_PREFIX.'facturedet as fd';
        $sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'product as p ON fd.fk_product = p.rowid';
        $sql.= ' WHERE fd.rowid = '.$rowid;

        $result = $this->db->query($sql);
        if ($result)
        {
            $objp = $this->db->fetch_object($result);

            $this->rowid				= $objp->rowid;
            $this->fk_facture			= $objp->fk_facture;
            $this->fk_parent_line		= $objp->fk_parent_line;
            $this->desc					= $objp->description;
            $this->qty					= $objp->qty;
            $this->subprice				= $objp->subprice;
            $this->tva_tx				= $objp->tva_tx;
            $this->localtax1_tx			= $objp->localtax1_tx;
            $this->localtax2_tx			= $objp->localtax2_tx;
            $this->remise_percent		= $objp->remise_percent;
            $this->fk_remise_except		= $objp->fk_remise_except;
            $this->fk_product			= $objp->fk_product;
            $this->product_type			= $objp->product_type;
            $this->date_start			= $this->db->jdate($objp->date_start);
            $this->date_end				= $this->db->jdate($objp->date_end);
            $this->info_bits			= $objp->info_bits;
            $this->total_ht				= $objp->total_ht;
            $this->total_tva			= $objp->total_tva;
            $this->total_localtax1		= $objp->total_localtax1;
            $this->total_localtax2		= $objp->total_localtax2;
            $this->total_ttc			= $objp->total_ttc;
            $this->fk_code_ventilation	= $objp->fk_code_ventilation;
            $this->fk_export_compta		= $objp->fk_export_compta;
            $this->rang					= $objp->rang;

            // Ne plus utiliser
            $this->price				= $objp->price;
            $this->remise				= $objp->remise;

            $this->ref					= $objp->product_ref;      // deprecated
            $this->product_ref			= $objp->product_ref;
            $this->libelle				= $objp->product_libelle;  // deprecated
            $this->product_label		= $objp->product_libelle;
            $this->product_desc			= $objp->product_desc;

            $this->db->free($result);
        }
        else
        {
            dol_print_error($this->db);
        }
    }

    /**
     *	\brief     	Insert line in database
     *	\param      notrigger		1 no triggers
     *	\return		int				<0 if KO, >0 if OK
     */
    function insert($notrigger=0)
    {
        global $langs,$user,$conf;

        dol_syslog("FactureLigne::Insert rang=".$this->rang, LOG_DEBUG);

        // Clean parameters
        $this->desc=trim($this->desc);
        if (empty($this->tva_tx)) $this->tva_tx=0;
        if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
        if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
        if (empty($this->total_localtax1)) $this->total_localtax1=0;
        if (empty($this->total_localtax2)) $this->total_localtax2=0;
        if (empty($this->rang)) $this->rang=0;
        if (empty($this->remise)) $this->remise=0;
        if (empty($this->remise_percent)) $this->remise_percent=0;
        if (empty($this->info_bits)) $this->info_bits=0;
        if (empty($this->subprice)) $this->subprice=0;
        if (empty($this->price))    $this->price=0;
        if (empty($this->special_code)) $this->special_code=0;
        if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

        // Check parameters
        if ($this->product_type < 0) return -1;

        $this->db->begin();

        // Insertion dans base de la ligne
        $sql = 'INSERT INTO '.MAIN_DB_PREFIX.'facturedet';
        $sql.= ' (fk_facture, fk_parent_line, description, qty, tva_tx, localtax1_tx, localtax2_tx,';
        $sql.= ' fk_product, product_type, remise_percent, subprice, price, remise, fk_remise_except,';
        $sql.= ' date_start, date_end, fk_code_ventilation, fk_export_compta, ';
        $sql.= ' rang, special_code,';
        $sql.= ' info_bits, total_ht, total_tva, total_ttc, total_localtax1, total_localtax2)';
        $sql.= " VALUES (".$this->fk_facture.",";
        $sql.= " ".($this->fk_parent_line>0?"'".$this->fk_parent_line."'":"null").",";
        $sql.= " '".$this->db->escape($this->desc)."',";
        $sql.= " ".price2num($this->qty).",";
        $sql.= " ".price2num($this->tva_tx).",";
        $sql.= " ".price2num($this->localtax1_tx).",";
        $sql.= " ".price2num($this->localtax2_tx).",";
        if ($this->fk_product) { $sql.= "'".$this->fk_product."',"; }
        else { $sql.='null,'; }
        $sql.= " ".$this->product_type.",";
        $sql.= " ".price2num($this->remise_percent).",";
        $sql.= " ".price2num($this->subprice).",";
        $sql.= " ".price2num($this->price).",";
        $sql.= " ".($this->remise?price2num($this->remise):'0').",";	// Deprecated
        if ($this->fk_remise_except) $sql.= $this->fk_remise_except.",";
        else $sql.= 'null,';
        if ($this->date_start) { $sql.= "'".$this->db->idate($this->date_start)."',"; }
        else { $sql.='null,'; }
        if ($this->date_end)   { $sql.= "'".$this->db->idate($this->date_end)."',"; }
        else { $sql.='null,'; }
        $sql.= ' '.$this->fk_code_ventilation.',';
        $sql.= ' '.$this->fk_export_compta.',';
        $sql.= ' '.$this->rang.',';
        $sql.= ' '.$this->special_code.',';
        $sql.= " '".$this->info_bits."',";
        $sql.= " ".price2num($this->total_ht).",";
		$sql.= " ".price2num($this->total_tva).",";
		$sql.= " ".price2num($this->total_ttc).",";
        $sql.= " ".price2num($this->total_localtax1).",";
        $sql.= " ".price2num($this->total_localtax2);
        $sql.= ')';

        dol_syslog("FactureLigne::insert sql=".$sql);
        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->rowid=$this->db->last_insert_id(MAIN_DB_PREFIX.'facturedet');

            // Si fk_remise_except defini, on lie la remise a la facture
            // ce qui la flague comme "consommee".
            if ($this->fk_remise_except)
            {
                $discount=new DiscountAbsolute($this->db);
                $result=$discount->fetch($this->fk_remise_except);
                if ($result >= 0)
                {
                    // Check if discount was found
                    if ($result > 0)
                    {
                        // Check if discount not already affected to another invoice
                        if ($discount->fk_facture)
                        {
                            $this->error=$langs->trans("ErrorDiscountAlreadyUsed",$discount->id);
                            dol_syslog("FactureLigne::insert Error ".$this->error, LOG_ERR);
                            $this->db->rollback();
                            return -3;
                        }
                        else
                        {
                            $result=$discount->link_to_invoice($this->rowid,0);
                            if ($result < 0)
                            {
                                $this->error=$discount->error;
                                dol_syslog("FactureLigne::insert Error ".$this->error, LOG_ERR);
                                $this->db->rollback();
                                return -3;
                            }
                        }
                    }
                    else
                    {
                        $this->error=$langs->trans("ErrorADiscountThatHasBeenRemovedIsIncluded");
                        dol_syslog("FactureLigne::insert Error ".$this->error, LOG_ERR);
                        $this->db->rollback();
                        return -3;
                    }
                }
                else
                {
                    $this->error=$discount->error;
                    dol_syslog("FactureLigne::insert Error ".$this->error, LOG_ERR);
                    $this->db->rollback();
                    return -3;
                }
            }

            if (! $notrigger)
            {
                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result = $interface->run_triggers('LINEBILL_INSERT',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }

            $this->db->commit();
            return $this->rowid;

        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("FactureLigne::insert Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }

    /**
     *  	Update line into database
     *		@return		int		<0 if KO, >0 if OK
     */
    function update()
    {
        global $user,$langs,$conf;

        // Clean parameters
        $this->desc=trim($this->desc);
		if (empty($this->tva_tx)) $this->tva_tx=0;
		if (empty($this->localtax1_tx)) $this->localtax1_tx=0;
		if (empty($this->localtax2_tx)) $this->localtax2_tx=0;
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;
		if (empty($this->remise)) $this->remise=0;
		if (empty($this->remise_percent)) $this->remise_percent=0;
		if (empty($this->info_bits)) $this->info_bits=0;
		if (empty($this->product_type)) $this->product_type=0;
		if (empty($this->fk_parent_line)) $this->fk_parent_line=0;

        // Check parameters
        if ($this->product_type < 0) return -1;

        $this->db->begin();

        // Mise a jour ligne en base
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET";
        $sql.= " description='".$this->db->escape($this->desc)."'";
        $sql.= ",subprice=".price2num($this->subprice)."";
        $sql.= ",price=".price2num($this->price)."";
        $sql.= ",remise=".price2num($this->remise)."";
        $sql.= ",remise_percent=".price2num($this->remise_percent)."";
        if ($this->fk_remise_except) $sql.= ",fk_remise_except=".$this->fk_remise_except;
        else $sql.= ",fk_remise_except=null";
        $sql.= ",tva_tx=".price2num($this->tva_tx)."";
        $sql.= ",localtax1_tx=".price2num($this->localtax1_tx)."";
        $sql.= ",localtax2_tx=".price2num($this->localtax2_tx)."";
        $sql.= ",qty=".price2num($this->qty)."";
        if ($this->date_start) { $sql.= ",date_start='".$this->db->idate($this->date_start)."'"; }
        else { $sql.=',date_start=null'; }
        if ($this->date_end) { $sql.= ",date_end='".$this->db->idate($this->date_end)."'"; }
        else { $sql.=',date_end=null'; }
        $sql.= ",product_type=".$this->product_type;
        $sql.= ",rang='".$this->rang."'";
        $sql.= ",info_bits='".$this->info_bits."'";
        if (empty($this->skip_update_total))
        {
        	$sql.= ",total_ht=".price2num($this->total_ht)."";
        	$sql.= ",total_tva=".price2num($this->total_tva)."";
        	$sql.= ",total_ttc=".price2num($this->total_ttc)."";
        }
        $sql.= ",total_localtax1=".price2num($this->total_localtax1)."";
        $sql.= ",total_localtax2=".price2num($this->total_localtax2)."";
        $sql.= ",fk_parent_line=".($this->fk_parent_line>0?$this->fk_parent_line:"null");
        $sql.= " WHERE rowid = ".$this->rowid;

        dol_syslog("FactureLigne::update sql=".$sql);

        $resql=$this->db->query($sql);
        if ($resql)
        {
            if (! $notrigger)
            {
                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface=new Interfaces($this->db);
                $result = $interface->run_triggers('LINEBILL_UPDATE',$this,$user,$langs,$conf);
                if ($result < 0) { $error++; $this->errors=$interface->errors; }
                // Fin appel triggers
            }
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("FactureLigne::update Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }

	/**
	 * 	Delete line in database
	 *	@return	 int  <0 si ko, >0 si ok
	 */
	function delete()
	{
		global $conf,$langs,$user;

		$this->db->begin();

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."facturedet WHERE rowid = ".$this->rowid;
		dol_syslog("FactureLigne::delete sql=".$sql, LOG_DEBUG);
		if ($this->db->query($sql) )
		{
			// Appel des triggers
			include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
			$interface=new Interfaces($this->db);
			$result = $interface->run_triggers('LINEBILL_DELETE',$this,$user,$langs,$conf);
			if ($result < 0) { $error++; $this->errors=$interface->errors; }
			// Fin appel triggers

			$this->db->commit();

			return 1;
		}
		else
		{
			$this->error=$this->db->error()." sql=".$sql;
			dol_syslog("FactureLigne::delete Error ".$this->error, LOG_ERR);
			$this->db->rollback();
			return -1;
		}
	}

    /**
     *      \brief     	Mise a jour en base des champs total_xxx de ligne de facture
     *		\return		int		<0 si ko, >0 si ok
     */
    function update_total()
    {
        $this->db->begin();
        dol_syslog("FactureLigne::update_total", LOG_DEBUG);

        // Clean parameters
		if (empty($this->total_localtax1)) $this->total_localtax1=0;
		if (empty($this->total_localtax2)) $this->total_localtax2=0;

        // Mise a jour ligne en base
        $sql = "UPDATE ".MAIN_DB_PREFIX."facturedet SET";
        $sql.= " total_ht=".price2num($this->total_ht)."";
        $sql.= ",total_tva=".price2num($this->total_tva)."";
        $sql.= ",total_localtax1=".price2num($this->total_localtax1)."";
        $sql.= ",total_localtax2=".price2num($this->total_localtax2)."";
        $sql.= ",total_ttc=".price2num($this->total_ttc)."";
        $sql.= " WHERE rowid = ".$this->rowid;

        dol_syslog("PropaleLigne::update_total sql=".$sql, LOG_DEBUG);

        $resql=$this->db->query($sql);
        if ($resql)
        {
            $this->db->commit();
            return 1;
        }
        else
        {
            $this->error=$this->db->error();
            dol_syslog("FactureLigne::update_total Error ".$this->error, LOG_ERR);
            $this->db->rollback();
            return -2;
        }
    }
}

?>
