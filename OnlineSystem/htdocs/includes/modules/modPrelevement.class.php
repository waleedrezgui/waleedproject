<?php
/* Copyright (C) 2004-2005 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2010-2011 Juanjo Menent 		<jmenent@2byte.es>
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
 *	\defgroup   	prelevement     Module prelevement
 *	\brief      	Module de gestion des prelevements bancaires
 *	\version		$Id: modPrelevement.class.php,v 1.42 2011/07/31 23:28:10 eldy Exp $
 */

/**
 *	\file       htdocs/includes/modules/modPrelevement.class.php
 *	\ingroup    prelevement
 *	\brief      Fichier de description et activation du module Prelevement
 */

include_once(DOL_DOCUMENT_ROOT ."/includes/modules/DolibarrModules.class.php");


/**
 *	\class 		modPrelevement
 *	\brief      Classe de description et activation du module Prelevement
 */
class modPrelevement extends DolibarrModules
{

	/**
	 *   \brief      Constructeur. Definit les noms, constantes et boites
	 *   \param      DB      handler d'acces base
	 */
	function modPrelevement($DB)
	{
		global $conf;

		$this->db = $DB ;
		$this->numero = 57 ;

		$this->family = "financial";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "Gestion des Prelevements";

		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = 'dolibarr';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 0;
		// Name of png file (without png) used for this module
		$this->picto='payment';

		// Data directories to create when module is enabled
		$this->dirs = array("/prelevement/temp","/prelevement/receipts");

		// Dependancies
		$this->depends = array("modFacture","modBanque");
		$this->requiredby = array();

		// Config pages
		$this->config_page_url = array("prelevement.php");

		// Constantes
		$this->const = array();

		// Boites
		$this->boxes = array();

		// Permissions
		$this->rights = array();
		$this->rights_class = 'prelevement';
		$r=0;
		$r++;
		$this->rights[$r][0] = 151;
		$this->rights[$r][1] = 'Read withdrawals';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'bons';
		$this->rights[$r][5] = 'lire';

		$r++;
		$this->rights[$r][0] = 152;
		$this->rights[$r][1] = 'Create/modify a withdrawals';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'bons';
		$this->rights[$r][5] = 'creer';

		$r++;
		$this->rights[$r][0] = 153;
		$this->rights[$r][1] = 'Send withdrawals to bank';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'bons';
		$this->rights[$r][5] = 'send';
		
		$r++;
		$this->rights[$r][0] = 154;
		$this->rights[$r][1] = 'credit/refuse withdrawals';
		$this->rights[$r][2] = 'a';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'bons';
		$this->rights[$r][5] = 'credit';
		
/*        $this->rights[2][0] = 154;
        $this->rights[2][1] = 'Setup withdraw account';
        $this->rights[2][2] = 'w';
        $this->rights[2][3] = 0;
        $this->rights[2][4] = 'bons';
        $this->rights[2][5] = 'configurer';
*/
	}


	/**
	 *   \brief      Fonction appelee lors de l'activation du module. Insere en base les constantes, boites, permissions du module.
	 *               Definit egalement les repertoires de donnees a creer pour ce module.
	 */
	function init()
	{
		global $conf;

		// Permissions
		$this->remove();

		$sql = array();

		return $this->_init($sql);
	}

	/**
	 *    \brief      Fonction appelee lors de la desactivation d'un module.
	 *                Supprime de la base les constantes, boites et permissions du module.
	 */
	function remove()
	{
		$sql = array();

		return $this->_remove($sql);
	}

}
?>
