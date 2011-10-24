<?PHP
/* Copyright (C) 2002-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Xavier Dutoit        <doli@sydesy.com>
 * Copyright (C) 2004-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2005-2011 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2005 	   Simon Tosser         <simon@kornog-computing.com>
 * Copyright (C) 2006 	   Andre Cianfarani     <andre.cianfarani@acdeveloppement.net>
 * Copyright (C) 2010      Juanjo Menent        <jmenent@2byte.es>
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
 *	\file       htdocs/filefunc.inc.php
 * 	\ingroup	core
 *  \brief      File that include conf.php file and functions.lib.php
 *  \version    $Id: filefunc.inc.php,v 1.22 2011/08/04 12:07:29 eldy Exp $
 */

define('DOL_VERSION','3.1.0');	// Also defined in htdocs/install/inc.php (Ex: x.y.z-alpha, x.y.z)
define('EURO',chr(128));

// Definition des constantes syslog
if (function_exists("define_syslog_variables"))
{
	if (version_compare(PHP_VERSION, '5.3.0', '<'))
	{
		define_syslog_variables(); // Deprecated since php 5.3.0, syslog variables no longer need to be initialized
	}
}
else
{
	// Pour PHP sans syslog (comme sous Windows)
	define('LOG_EMERG',0);
	define('LOG_ALERT',1);
	define('LOG_CRIT',2);
	define('LOG_ERR',3);
	define('LOG_WARNING',4);
	define('LOG_NOTICE',5);
	define('LOG_INFO',6);
	define('LOG_DEBUG',7);
}


// Forcage du parametrage PHP error_reporting (Dolibarr non utilisable en mode error E_ALL)
error_reporting(E_ALL ^ E_NOTICE);
//error_reporting(E_ALL);


# Define vars
$conffiletoshowshort = "conf.php";
# Define localization of conf file
$conffile = "conf/conf.php";
$conffiletoshow = "htdocs/conf/conf.php";
# For debian/redhat like systems
#$conffile = "/etc/dolibarr/conf.php";
#$conffiletoshow = "/etc/dolibarr/conf.php";


// Include configuration
$result=@include_once($conffile);
if (! $result && ! empty($_SERVER["GATEWAY_INTERFACE"]))    // If install not done and we are in a web session
{
	header("Location: install/index.php");
	exit;
}

if (empty($dolibarr_main_db_port)) $dolibarr_main_db_port=0;		// Pour compatibilite avec anciennes configs, si non defini, on prend 'mysql'
if (empty($dolibarr_main_db_type)) $dolibarr_main_db_type='mysql';	// Pour compatibilite avec anciennes configs, si non defini, on prend 'mysql'
if (empty($dolibarr_main_db_prefix)) $dolibarr_main_db_prefix='llx_';
if (empty($dolibarr_main_db_character_set)) $dolibarr_main_db_character_set='latin1';		// Old installation
if (empty($dolibarr_main_db_collation)) $dolibarr_main_db_collation='latin1_swedish_ci';	// Old installation
if (empty($dolibarr_main_db_encryption)) $dolibarr_main_db_encryption=0;
if (empty($dolibarr_main_db_cryptkey)) $dolibarr_main_db_cryptkey='';
if (empty($dolibarr_main_limit_users)) $dolibarr_main_limit_users=0;
if (empty($dolibarr_mailing_limit_sendbyweb)) $dolibarr_mailing_limit_sendbyweb=0;
if (empty($force_charset_do_notuse)) $force_charset_do_notuse='UTF-8';

// Security: CSRF protection
// This test check if referrer ($_SERVER['HTTP_REFERER']) is same web site than Dolibarr ($_SERVER['HTTP_HOST'])
// when we post forms (we allow GET to allow direct link to access a particular page).
if (! defined('NOCSRFCHECK') && empty($dolibarr_nocsrfcheck) && ! empty($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] != 'GET' && ! empty($_SERVER['HTTP_HOST']) && ! empty($_SERVER['HTTP_REFERER']) && ! preg_match('/'.preg_quote($_SERVER['HTTP_HOST'],'/').'/i', $_SERVER['HTTP_REFERER']))
{
	//print 'HTTP_POST='.$_SERVER['HTTP_HOST'].' HTTP_REFERER='.$_SERVER['HTTP_REFERER'];
	print "Access refused by CSRF protection in main.inc.php.\n";
	print "If you access your server behind a proxy using url rewriting, you might add the line \$dolibarr_nocsrfcheck=1 into your conf.php file.\n";
	die;
}
if (empty($dolibarr_main_db_host))
{
	print 'Dolibarr setup is not yet complete.<br><br>'."\n";
	print '<a href="install/index.php">Click here to finish Dolibarr install process</a> ...'."\n";
	die;
}
if (empty($dolibarr_main_url_root))
{
	print 'Value for parameter \'dolibarr_main_url_root\' is not defined in your \'htdocs\conf\conf.php\' file.<br>'."\n";
	print 'You must add this parameter with your full Dolibarr root Url (Example: http://myvirtualdomain/ or http://mydomain/mydolibarrurl/)'."\n";
	die;
}
if (empty($dolibarr_main_db_type)) $dolibarr_main_db_type='mysql';   // Pour compatibilite avec anciennes configs, si non defini, on prend 'mysql'
if (empty($dolibarr_main_data_root))
{
	// Si repertoire documents non defini, on utilise celui par defaut
	$dolibarr_main_data_root=str_replace("/htdocs","",$dolibarr_main_document_root);
	$dolibarr_main_data_root.="/documents";
}

// Define some constants
define('DOL_CLASS_PATH', 'class/');									// Filesystem path to class dir (defined only for some code that want to be compatible with old versions without this parameter)
define('DOL_DATA_ROOT', $dolibarr_main_data_root);					// Filesystem data (documents)
define('DOL_DOCUMENT_ROOT', $dolibarr_main_document_root);			// Filesystem core php (htdocs)
if (! empty($dolibarr_main_document_root_alt))
{
	define('DOL_DOCUMENT_ROOT_ALT', $dolibarr_main_document_root_alt);	// Filesystem paths to alternate core php (alternate htdocs)
}
// Define DOL_MAIN_URL_ROOT and DOL_URL_ROOT
$tmp='';
$found=0;
$real_dolibarr_main_document_root=str_replace('\\','/',realpath($dolibarr_main_document_root));
$pathroot=$_SERVER["DOCUMENT_ROOT"];
$paths=explode('/',str_replace('\\','/',$_SERVER["SCRIPT_NAME"]));
$concatpath='';
foreach($paths as $tmppath)
{
    if ($tmppath) $concatpath.='/'.$tmppath;
    //print $real_$dolibarr_main_document_root.'-'.realpath($pathroot.$concatpath).'<br>';
    if ($real_dolibarr_main_document_root == realpath($pathroot.$concatpath))
    {
        $tmp3=$concatpath;
        //print "Found relative url = ".$tmp3;
        $found=1;
        break;
    }
}
if (! $found)	// If autodetect fails (Ie: when uing apache alias that point outside default DOCUMENT_ROOT.
{
	$tmp=$dolibarr_main_url_root;
}
else $tmp='http'.((empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != 'on')?'':'s').'://'.$_SERVER["SERVER_NAME"].((empty($_SERVER["SERVER_PORT"])||$_SERVER["SERVER_PORT"]==80)?'':':'.$_SERVER["SERVER_PORT"]).($tmp3?(preg_match('/^\//',$tmp3)?'':'/').$tmp3:'');
//print "tmp1=".$tmp1." tmp2=".$tmp2." tmp3=".$tmp3." tmp=".$tmp;

if (! empty($dolibarr_main_force_https)) $tmp=preg_replace('/^http:/i','https:',$tmp);
define('DOL_MAIN_URL_ROOT', $tmp);											// URL absolute root (https://sss/dolibarr, ...)
$uri=preg_replace('/^http(s?):\/\//i','',constant('DOL_MAIN_URL_ROOT'));	// $uri contains url without http*
$suburi = strstr($uri, '/');												// $suburi contains url without domain
if ($suburi == '/') $suburi = '';											// If $suburi is /, it is now ''
define('DOL_URL_ROOT', $suburi);											// URL relative root ('', '/dolibarr', ...)
// Define DOL_MAIN_URL_ROOT_ALT and DOL_URL_ROOT_ALT
if (! empty($dolibarr_main_url_root_alt))
{
    $altpart=str_replace($dolibarr_main_url_root,'',$dolibarr_main_url_root_alt);
    if (! preg_match('/^\//',$altpart) && ! empty($altpart)) { $tmp_alt=$dolibarr_main_url_root_alt; }	// Manage case url=http://localhost/aaa and url_alt=http://localhost/aaabbb
    else $tmp_alt=$tmp.((preg_match('/\/$/',$tmp)||preg_match('/^\//',$altpart))?'':'/').$altpart;
	//$tmp_alt=$dolibarr_main_url_root_alt;
    define('DOL_MAIN_URL_ROOT_ALT', $tmp_alt);           							// URL absolute root (https://sss/dolibarr/custom, ...)
	$uri=preg_replace('/^http(s?):\/\//i','',constant('DOL_MAIN_URL_ROOT_ALT'));    // $uri contains url without http*
	$suburi = strstr($uri, '/');        											// $suburi contains url without domain
	if ($suburi == '/') $suburi = '';   											// If $suburi is /, it is now ''
	define('DOL_URL_ROOT_ALT', $suburi);    										// URL relative root ('', '/dolibarr/custom', ...)
}
// Define prefix
define('MAIN_DB_PREFIX',$dolibarr_main_db_prefix);
//print DOL_URL_ROOT.'-'.DOL_URL_ROOT_ALT;

/*
 * Include functions
 */

if (! file_exists(DOL_DOCUMENT_ROOT ."/lib/functions.lib.php"))
{
	print "Error: Dolibarr config file content seems to be not correctly defined.<br>\n";
	print "Please run dolibarr setup by calling page <b>/install</b>.<br>\n";
	exit;
}

include_once(DOL_DOCUMENT_ROOT ."/lib/functions.lib.php");	// Need 970ko memory (1.1 in 2.2)


// If password is encoded, we decode it
if (preg_match('/crypted:/i',$dolibarr_main_db_pass) || ! empty($dolibarr_main_db_encrypted_pass))
{
	require_once(DOL_DOCUMENT_ROOT ."/lib/security.lib.php");
	if (preg_match('/crypted:/i',$dolibarr_main_db_pass))
	{
		$dolibarr_main_db_pass = preg_replace('/crypted:/i', '', $dolibarr_main_db_pass);
		$dolibarr_main_db_pass = dol_decode($dolibarr_main_db_pass);
		$dolibarr_main_db_encrypted_pass = $dolibarr_main_db_pass;	// We need to set this as it is used to know the password was initially crypted
	}
	else $dolibarr_main_db_pass = dol_decode($dolibarr_main_db_encrypted_pass);
}
//print memory_get_usage();

?>
