<?php
/* Copyright (C) 2005      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2005-2007 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *	\defgroup   bookmark    Module bookmarks
 *	\brief      Module to manage Bookmarks
 *	\version	$Id: modBookmark.class.php,v 1.26 2011/07/31 23:28:12 eldy Exp $
 */

/**
 *	\file       htdocs/includes/modules/modBookmark.class.php
 *	\ingroup    bookmark
 *	\brief      Fichier de description et activation du module Bookmarks
 */

include_once(DOL_DOCUMENT_ROOT ."/includes/modules/DolibarrModules.class.php");


/**
 \class      modBookmark
 \brief      Classe de description et activation du module Bookmark
 */

class modBookmark extends DolibarrModules
{

	/**
	 *   \brief      Constructeur. Definit les noms, constantes et boites
	 *   \param      DB      handler d'acc�s base
	 */
	function modBookmark($DB)
	{
		$this->db = $DB ;
		$this->numero = 330;

		$this->family = "technic";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		$this->description = "Gestion des Bookmarks";

		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version
		$this->version = 'dolibarr';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->special = 2;
		$this->picto='bookmark';

		// Data directories to create when module is enabled
		$this->dirs = array();

		// Dependancies
		$this->depends = array();
		$this->requiredby = array();
		$this->langfiles = array("bookmarks");

		// Config pages
		$this->config_page_url = array('bookmark.php@bookmarks');

		// Constantes
		$this->const = array();

		// Boites
		$this->boxes = array();
		$this->boxes[0][1] = "box_bookmarks.php";

		// Permissions
		$this->rights = array();
		$this->rights_class = 'bookmark';
		$r=0;

		$r++;
		$this->rights[$r][0] = 331; // id de la permission
		$this->rights[$r][1] = 'Lire les bookmarks'; // libelle de la permission
		$this->rights[$r][2] = 'r'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'lire';

		$r++;
		$this->rights[$r][0] = 332; // id de la permission
		$this->rights[$r][1] = 'Creer/modifier les bookmarks'; // libelle de la permission
		$this->rights[$r][2] = 'r'; // type de la permission (deprecie a ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par defaut
		$this->rights[$r][4] = 'creer';

		$r++;
		$this->rights[$r][0] = 333; // id de la permission
		$this->rights[$r][1] = 'Supprimer les bookmarks'; // libelle de la permission
		$this->rights[$r][2] = 'r'; // type de la permission (d�pr�ci� � ce jour)
		$this->rights[$r][3] = 1; // La permission est-elle une permission par d�faut
		$this->rights[$r][4] = 'supprimer';

	}

	/**
	 *   \brief      Fonction appel�e lors de l'activation du module. Ins�re en base les constantes, boites, permissions du module.
	 *               D�finit �galement les r�pertoires de donn�es � cr�er pour ce module.
	 */
	function init()
	{

		$sql = array();

		return $this->_init($sql);
	}

	/**
	 *    \brief      Fonction appel�e lors de la d�sactivation d'un module.
	 *                Supprime de la base les constantes, boites et permissions du module.
	 */
	function remove()
	{
		$sql = array();

		return $this->_remove($sql);
	}
}
?>
