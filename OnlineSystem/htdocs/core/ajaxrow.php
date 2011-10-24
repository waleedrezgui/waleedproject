<?php
/* Copyright (C) 2010-2011 Regis Houssin  <regis@dolibarr.fr>
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
 *       \file       htdocs/core/ajaxrow.php
 *       \brief      File to return Ajax response on Row move
 *       \version    $Id: ajaxrow.php,v 1.4 2011/07/31 23:45:15 eldy Exp $
 */

if (! defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL','1'); // Disables token renewal
if (! defined('NOREQUIREMENU'))  define('NOREQUIREMENU','1');
if (! defined('NOREQUIREHTML'))  define('NOREQUIREHTML','1');
if (! defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');
if (! defined('NOREQUIRESOC'))   define('NOREQUIRESOC','1');
if (! defined('NOREQUIRETRAN'))  define('NOREQUIRETRAN','1');

require('../main.inc.php');
require_once(DOL_DOCUMENT_ROOT."/core/class/commonobject.class.php");


/*
 * View
 */

// Ajout directives pour resoudre bug IE
//header('Cache-Control: Public, must-revalidate');
//header('Pragma: public');

//top_htmlhead("", "", 1);  // Replaced with top_httphead. An ajax page does not need html header.
top_httphead();

print '<!-- Ajax page called with url '.$_SERVER["PHP_SELF"].'?'.$_SERVER["QUERY_STRING"].' -->'."\n";

// Registering the location of boxes
if((isset($_GET['roworder']) && !empty($_GET['roworder'])) && (isset($_GET['table_element_line']) && !empty($_GET['table_element_line']))
	&& (isset($_GET['fk_element']) && !empty($_GET['fk_element'])) && (isset($_GET['element_id']) && !empty($_GET['element_id'])) )
{
	$roworder = explode(',',$_GET['roworder']);
	
	foreach($roworder as $value)
	{
		if (!empty($value))
		{
			$newroworder[] = $value;
		}
	}
	
	$roworder = implode(',',$newroworder);
	
	dol_syslog("AjaxRow roworder=".$_GET['roworder']." neworder=".$roworder." element=".$_GET['element'], LOG_DEBUG);

	$row=new CommonObject($db);
	$row->table_element_line = $_GET['table_element_line'];
	$row->fk_element = $_GET['fk_element'];
	$row->id = $_GET['element_id'];
	$result=$row->line_ajaxorder($roworder);
	$result=$row->line_order(true);
}

?>
