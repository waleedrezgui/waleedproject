<?php
/* Copyright (C) 2004-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
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
 *		\file       htdocs/install/etape1.php
 *		\ingroup	install
 *		\brief      Build conf file on disk
 *		\version    $Id: etape1.php,v 1.137 2011/08/04 13:19:26 eldy Exp $
 */

define('DONOTLOADCONF',1);	// To avoid loading conf by file inc.php

include("./inc.php");

$action=GETPOST('action');
$setuplang=isset($_POST["selectlang"])?$_POST["selectlang"]:(isset($_GET["selectlang"])?$_GET["selectlang"]:'auto');
$langs->setDefaultLang($setuplang);

$langs->load("admin");
$langs->load("install");

// Init "forced values" to nothing. "forced values" are used after an doliwamp install wizard.
$useforcedwizard=false;
if (file_exists("./install.forced.php")) { $useforcedwizard=true; include_once("./install.forced.php"); }
else if (file_exists("/etc/dolibarr/install.forced.php")) { $useforcedwizard=include_once("/etc/dolibarr/install.forced.php"); }

dolibarr_install_syslog("--- etape1: Entering etape1.php page");


/*
 *	View
 */

pHeader($langs->trans("ConfigurationFile"),"etape2");

// Test if we can run a first install process
if (! is_writable($conffile))
{
    print $langs->trans("ConfFileIsNotWritable",$conffiletoshow);
    pFooter(1,$setuplang,'jscheckparam');
    exit;
}

$error = 0;

// Repertoire des pages dolibarr
$main_dir=isset($_POST["main_dir"])?trim($_POST["main_dir"]):'';

// Remove last / into dans main_dir
if (substr($main_dir, dol_strlen($main_dir) -1) == "/")
{
	$main_dir = substr($main_dir, 0, dol_strlen($main_dir)-1);
}

// Remove last / into dans main_url
if (! empty($_POST["main_url"]) && substr($_POST["main_url"], dol_strlen($_POST["main_url"]) -1) == "/")
{
	$_POST["main_url"] = substr($_POST["main_url"], 0, dol_strlen($_POST["main_url"])-1);
}

// Directory for generated documents (invoices, orders, ecm, etc...)
$main_data_dir=isset($_POST["main_data_dir"])?$_POST["main_data_dir"]:'';
if (! $main_data_dir) { $main_data_dir="$main_dir/documents"; }


if ($action == "set")
{
	umask(0);
	foreach($_POST as $cle=>$valeur)
	{
		if (! preg_match('/^db_pass/i',$cle)) dolibarr_install_syslog("Choice for ".$cle." = ".$valeur);
	}

	// Show title of step
	print '<h3>'.$langs->trans("ConfigurationFile").'</h3>';
	print '<table cellspacing="0" width="100%" cellpadding="1" border="0">';

	// Check parameter main_dir
	if (! $error)
	{
		if (! is_dir($main_dir))
		{
			dolibarr_install_syslog("etape1: Repertoire '".$main_dir."' inexistant ou non accessible");

			print "<tr><td>";
			print $langs->trans("ErrorDirDoesNotExists",$main_dir).'<br>';
			print $langs->trans("ErrorWrongValueForParameter",$langs->trans("WebPagesDirectory")).'<br>';
			print $langs->trans("ErrorGoBackAndCorrectParameters").'<br><br>';
			print '</td><td>';
			print $langs->trans("Error");
			print "</td></tr>";
			$error++;
		}
	}

	// Chargement driver acces bases
	if (! $error)
	{
		dolibarr_install_syslog("etape1: Directory '".$main_dir."' exists");

		require_once($main_dir."/lib/databases/".$_POST["db_type"].".lib.php");
	}


	/***************************************************************************
	 * Create directories
	 ***************************************************************************/

	// Create subdirectory main_data_dir
	if (! $error)
	{
		// Create directory for documents
		if (! is_dir($main_data_dir))
		{
			create_exdir($main_data_dir);
		}

		if (! is_dir($main_data_dir))
		{
			print "<tr><td>".$langs->trans("ErrorDirDoesNotExists",$main_data_dir);
			print ' '.$langs->trans("YouMustCreateItAndAllowServerToWrite");
			print '</td><td>';
			print '<font class="error">'.$langs->trans("Error").'</font>';
			print "</td></tr>";
			print '<tr><td colspan="2"><br>'.$langs->trans("CorrectProblemAndReloadPage",$_SERVER['PHP_SELF'].'?testget=ok').'</td></tr>';
			$error++;
		}
		else
		{
			// Create .htaccess file in document directory
			$pathhtaccess=$main_data_dir.'/.htaccess';
			if (! file_exists($pathhtaccess))
			{
				dolibarr_install_syslog("etape1: .htaccess file does not exists, we create it in '".$main_data_dir."'");
				$handlehtaccess=@fopen($pathhtaccess,'w');
				if ($handlehtaccess)
				{
					fwrite($handlehtaccess,'Order allow,deny'."\n");
					fwrite($handlehtaccess,'Deny from all'."\n");

					fclose($handlehtaccess);
					dolibarr_install_syslog("etape1: .htaccess file created");
				}
			}

			// Les documents sont en dehors de htdocs car ne doivent pas pouvoir etre telecharges en passant outre l'authentification
			$dir[0] = "$main_data_dir/facture";
			$dir[1] = "$main_data_dir/users";
			$dir[2] = "$main_data_dir/propale";
			$dir[3] = "$main_data_dir/mycompany";
			$dir[4] = "$main_data_dir/ficheinter";
			$dir[5] = "$main_data_dir/produit";
			$dir[6] = "$main_data_dir/rapport";

			// Boucle sur chaque repertoire de dir[] pour les creer s'ils nexistent pas
			for ($i = 0 ; $i < sizeof($dir) ; $i++)
			{
				if (is_dir($dir[$i]))
				{
					dolibarr_install_syslog("etape1: Directory '".$dir[$i]."' exists");
				}
				else
				{
					if (create_exdir($dir[$i]) < 0)
					{
						print "<tr><td>";
						print "Failed to create directory: ".$dir[$i];
						print '</td><td>';
						print $langs->trans("Error");
						print "</td></tr>";
						$error++;
					}
					else
					{
						dolibarr_install_syslog("etape1: Directory '".$dir[$i]."' created");
					}
				}
			}
			if ($error)
			{
				print "<tr><td>".$langs->trans("ErrorDirDoesNotExists",$main_data_dir);
				print ' '.$langs->trans("YouMustCreateItAndAllowServerToWrite");
				print '</td><td>';
				print '<font class="error">'.$langs->trans("Error").'</font>';
				print "</td></tr>";
				print '<tr><td colspan="2"><br>'.$langs->trans("CorrectProblemAndReloadPage",$_SERVER['PHP_SELF'].'?testget=ok').'</td></tr>';
			}
		}
	}

	// Force https
	$main_force_https = ( (GETPOST("main_force_https") && ( GETPOST("main_force_https") == "on" || GETPOST("main_force_https") == 1) ) ? '1' : '0');

	// Use alternative directory
	$main_use_alt_dir = ( (GETPOST("main_use_alt_dir") && ( GETPOST("main_use_alt_dir") == "on" || GETPOST("main_use_alt_dir") == 1) ) ? '' : '#');

	// Alternative root directory name
	$main_alt_dir_name = ( (GETPOST("main_alt_dir_name") && GETPOST("main_alt_dir_name") != '') ? GETPOST("main_alt_dir_name") : 'custom');

	/**
	 * Write conf file on disk
	 */
	if (! $error)
	{
		// Save old conf file on disk
		if (file_exists("$conffile"))
		{
			// We must ignore errors as an existing old file may already exists and not be replacable or
			// the installer (like for ubuntu) may not have permission to create another file than conf.php.
			// Also no other process must be able to read file or we expose the new file so content with password.
			@dol_copy($conffile, $conffile.'.old', '0400');
		}

		$error+=write_conf_file($conffile);
	}

	/**
	 * Create database and admin user database
	 */
	if (! $error)
	{
	    // We reload configuration file
		conf($dolibarr_main_document_root);

        print '<tr><td>';
        print $langs->trans("ConfFileReload");
        print '</td>';
        print '<td>'.$langs->trans("OK").'</td></tr>';


		$userroot=isset($_POST["db_user_root"])?$_POST["db_user_root"]:"";
		$passroot=isset($_POST["db_pass_root"])?$_POST["db_pass_root"]:"";


		/**
		 * 	Si creation utilisateur admin demandee, on le cree
		 */
		if (isset($_POST["db_create_user"]) && $_POST["db_create_user"] == "on")
		{
			dolibarr_install_syslog("etape1: Create database user: ".$dolibarr_main_db_user);

			//print $conf->db->host." , ".$conf->db->name." , ".$conf->db->user." , ".$conf->db->port;
			$databasefortest=$conf->db->name;
			if ($conf->db->type == 'mysql' || $conf->db->type == 'mysqli')
			{
				$databasefortest='mysql';
			}
			else if ($conf->db->type == 'pgsql')
			{
				$databasefortest='postgres';
			}
			else if ($conf->db->type == 'mssql')
			{
				$databasefortest='mssql';
			}

			// Creation handler de base, verification du support et connexion

			$db = new DoliDb($conf->db->type,$conf->db->host,$userroot,$passroot,$databasefortest,$conf->db->port);
			if ($db->error)
			{
				print '<div class="error">'.$db->error.'</div>';
				$error++;
			}

			if (! $error)
			{
				if ($db->connected)
				{
					$result=$db->DDLCreateUser($dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_pass,$dolibarr_main_db_name);

					if ($result > 0)
					{

						print '<tr><td>';
						print $langs->trans("UserCreation").' : ';
						print $dolibarr_main_db_user;
						print '</td>';
						print '<td>'.$langs->trans("OK").'</td></tr>';
					}
					else
					{
						if ($db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS'
						|| $db->errno() == 'DB_ERROR_KEY_NAME_ALREADY_EXISTS')
						{
							dolibarr_install_syslog("etape1: User already exists");
							print '<tr><td>';
							print $langs->trans("UserCreation").' : ';
							print $dolibarr_main_db_user;
							print '</td>';
							print '<td>'.$langs->trans("LoginAlreadyExists").'</td></tr>';
						}
						else
						{
							dolibarr_install_syslog("etape1: Failed to create user");
							print '<tr><td>';
							print $langs->trans("UserCreation").' : ';
							print $dolibarr_main_db_user;
							print '</td>';
							print '<td>'.$langs->trans("Error").' '.$db->error()."</td></tr>";
						}
					}

					$db->close();
				}
				else
				{
					print '<tr><td>';
					print $langs->trans("UserCreation").' : ';
					print $dolibarr_main_db_user;
					print '</td>';
					print '<td>'.$langs->trans("Error").'</td>';
					print '</tr>';

					// Affiche aide diagnostique
					print '<tr><td colspan="2"><br>';
					print $langs->trans("YouAskDatabaseCreationSoDolibarrNeedToConnect",$dolibarr_main_db_user,$dolibarr_main_db_host,$userroot);
					print '<br>';
					print $langs->trans("BecauseConnectionFailedParametersMayBeWrong").'<br><br>';
					print $langs->trans("ErrorGoBackAndCorrectParameters").'<br><br>';
					print '</td></tr>';

					$error++;
				}
			}
		}   // Fin si "creation utilisateur"


		/*
		 * If database creation is asked, we create it
		 */
		if (! $error && (isset($_POST["db_create_database"]) && $_POST["db_create_database"] == "on"))
		{
			dolibarr_install_syslog("etape1: Create database : ".$dolibarr_main_db_name, LOG_DEBUG);
			$db = new DoliDb($conf->db->type,$conf->db->host,$userroot,$passroot,'',$conf->db->port);

			if ($db->connected)
			{
				$result=$db->DDLCreateDb($dolibarr_main_db_name, $dolibarr_main_db_character_set, $dolibarr_main_db_collation, $dolibarr_main_db_user);

				if ($result)
				{
					print '<tr><td>';
					print $langs->trans("DatabaseCreation")." (".$langs->trans("User")." ".$userroot.") : ";
					print $dolibarr_main_db_name;
					print '</td>';
					print "<td>".$langs->trans("OK")."</td></tr>";

					$check1=$db->getDefaultCharacterSetDatabase();
					$check2=$db->getDefaultCollationDatabase();
					dolibarr_install_syslog('etape1: Note that default server was charset='.$check1.' collation='.$check2, LOG_DEBUG);

					// If values differs, we save conf file again
					//if ($check1 != $dolibarr_main_db_character_set) dolibarr_install_syslog('etape1: Value for character_set is not the one asked for database creation', LOG_WARNING);
					//if ($check2 != $dolibarr_main_db_collation)     dolibarr_install_syslog('etape1: Value for collation is not the one asked for database creation', LOG_WARNING);
				}
				else
				{
					// Affiche aide diagnostique
					print '<tr><td colspan="2"><br>';
					print $langs->trans("ErrorFailedToCreateDatabase",$dolibarr_main_db_name).'<br>';
					print $langs->trans("IfDatabaseExistsGoBackAndCheckCreate");
					print '<br>';
					print '</td></tr>';

					dolibarr_install_syslog('etape1: Failed to create database '.$dolibarr_main_db_name.' '.$db->lasterrno().' '.$db->lasterror(), LOG_ERR);
					$error++;
				}
				$db->close();
			}
			else {
				print '<tr><td>';
				print $langs->trans("DatabaseCreation")." (".$langs->trans("User")." ".$userroot.") : ";
				print $dolibarr_main_db_name;
				print '</td>';
				print '<td>'.$langs->trans("Error").'</td>';
				print '</tr>';

				// Affiche aide diagnostique
				print '<tr><td colspan="2"><br>';
				print $langs->trans("YouAskDatabaseCreationSoDolibarrNeedToConnect",$dolibarr_main_db_user,$dolibarr_main_db_host,$userroot);
				print '<br>';
				print $langs->trans("BecauseConnectionFailedParametersMayBeWrong").'<br><br>';
				print $langs->trans("ErrorGoBackAndCorrectParameters").'<br><br>';
				print '</td></tr>';

				$error++;
			}
		}   // Fin si "creation database"


		/*
		 * We testOn test maintenant l'acces par le user base dolibarr
		 */
		if (! $error)
		{
			dolibarr_install_syslog("etape1: connexion de type=".$conf->db->type." sur host=".$conf->db->host." port=".$conf->db->port." user=".$conf->db->user." name=".$conf->db->name, LOG_DEBUG);
			//print "connexion de type=".$conf->db->type." sur host=".$conf->db->host." port=".$conf->db->port." user=".$conf->db->user." name=".$conf->db->name;

			$db = new DoliDb($conf->db->type,$conf->db->host,$conf->db->user,$conf->db->pass,$conf->db->name,$conf->db->port);

			if ($db->connected == 1)
			{
				// si acces serveur ok et acces base ok, tout est ok, on ne va pas plus loin, on a meme pas utilise le compte root.
				if ($db->database_selected == 1)
				{
					dolibarr_install_syslog("etape1: connexion to server by user ".$conf->db->user." is ok", LOG_DEBUG);
					print "<tr><td>";
					print $langs->trans("ServerConnection")." (".$langs->trans("User")." ".$conf->db->user.") : ";
					print $dolibarr_main_db_host;
					print "</td><td>";
					print $langs->trans("OK");
					print "</td></tr>";

					dolibarr_install_syslog("etape1: connexion to database : ".$conf->db->name.", by user : ".$conf->db->user." is ok", LOG_DEBUG);
					print "<tr><td>";
					print $langs->trans("DatabaseConnection")." (".$langs->trans("User")." ".$conf->db->user.") : ";
					print $dolibarr_main_db_name;
					print "</td><td>";
					print $langs->trans("OK");
					print "</td></tr>";

					$error = 0;
				}
				else
				{
					dolibarr_install_syslog("etape1: connexion to server by user ".$conf->db->user." is ok", LOG_DEBUG);
					print "<tr><td>";
					print $langs->trans("ServerConnection")." (".$langs->trans("User")." ".$conf->db->user.") : ";
					print $dolibarr_main_db_host;
					print "</td><td>";
					print $langs->trans("OK");
					print "</td></tr>";

					dolibarr_install_syslog("etape1: connexion to database ".$conf->db->name.", by user : ".$conf->db->user." has failed", LOG_ERR);
					print "<tr><td>";
					print $langs->trans("DatabaseConnection")." (".$langs->trans("User")." ".$conf->db->user.") : ";
					print $dolibarr_main_db_name;
					print '</td><td>';
					print $langs->trans("Error");
					print "</td></tr>";

					// Affiche aide diagnostique
					print '<tr><td colspan="2"><br>';
					print $langs->trans('CheckThatDatabasenameIsCorrect',$dolibarr_main_db_name).'<br>';
					print $langs->trans('IfAlreadyExistsCheckOption').'<br>';
					print $langs->trans("ErrorGoBackAndCorrectParameters").'<br><br>';
					print '</td></tr>';

					$error++;
				}
			}
			else
			{
				dolibarr_install_syslog("etape1: la connexion au serveur par le user ".$conf->db->user." est rate");
				print "<tr><td>";
				print $langs->trans("ServerConnection")." (".$langs->trans("User")." ".$conf->db->user.") : ";
				print $dolibarr_main_db_host;
				print '</td><td>';
				print '<font class="error">'.$db->error.'</div>';
				print "</td></tr>";

				// Affiche aide diagnostique
				print '<tr><td colspan="2"><br>';
				print $langs->trans("ErrorConnection",$conf->db->host,$conf->db->name,$conf->db->user);
				print $langs->trans('IfLoginDoesNotExistsCheckCreateUser').'<br>';
				print $langs->trans("ErrorGoBackAndCorrectParameters").'<br><br>';
				print '</td></tr>';

				$error++;
			}
		}
	}

	print '</table>';
}

?>

<script type="text/javascript" language="javascript">
function jsinfo()
{
	ok=true;

	//alert('<?php echo dol_escape_js($langs->transnoentities("NextStepMightLastALongTime")); ?>');

	document.getElementById('nextbutton').style.visibility="hidden";
	document.getElementById('pleasewait').style.visibility="visible";

	return ok;
}
</script>

<?php

dolibarr_install_syslog("--- install/etape1.php end", LOG_INFO);

pFooter($error,$setuplang,'jsinfo');


/**
 *  Save configuration file. No particular permissions are set by installer.
 *  @param      conffile        Path to conf file
 */
function write_conf_file($conffile)
{
	global $conf,$langs;
	global $_POST,$main_dir,$main_data_dir,$main_force_https,$main_use_alt_dir,$main_alt_dir_name;
	global $dolibarr_main_url_root,$dolibarr_main_document_root,$dolibarr_main_data_root,$dolibarr_main_db_host;
	global $dolibarr_main_db_port,$dolibarr_main_db_name,$dolibarr_main_db_user,$dolibarr_main_db_pass;
	global $dolibarr_main_db_type,$dolibarr_main_db_character_set,$dolibarr_main_db_collation,$dolibarr_main_authentication;
    global $conffile,$conffiletoshow,$conffiletoshowshort;

	$error=0;

	$key = md5(uniqid(mt_rand(),TRUE)); // Genere un hash d'un nombre aleatoire

	$fp = fopen("$conffile", "w");
	if($fp)
	{
		clearstatcache();

		fputs($fp, '<?php');
		fputs($fp,"\n");
		fputs($fp,"#\n");
		fputs($fp,"# File generated by Dolibarr installer ".DOL_VERSION." on ".dol_print_date(dol_now(),''));
		fputs($fp,"\n");
		fputs($fp,"#\n");
		fputs($fp,"# Take a look at conf.php.example file for an example of ".$conffiletoshowshort." file\n");
		fputs($fp,"# and explanations for all possibles parameters.\n");
		fputs($fp,"#\n");

		fputs($fp, '$dolibarr_main_url_root=\''.addslashes($_POST["main_url"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_document_root=\''.addslashes($main_dir).'\';');
		fputs($fp,"\n");

		fputs($fp, $main_use_alt_dir.'$dolibarr_main_url_root_alt=\''.addslashes($_POST["main_url"]."/".$main_alt_dir_name).'\';');
		fputs($fp,"\n");

		fputs($fp, $main_use_alt_dir.'$dolibarr_main_document_root_alt=\''.addslashes($main_dir."/".$main_alt_dir_name).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_data_root=\''.addslashes($main_data_dir).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_host=\''.addslashes($_POST["db_host"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_port=\''.addslashes($_POST["db_port"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_name=\''.addslashes($_POST["db_name"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_user=\''.addslashes($_POST["db_user"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_pass=\''.addslashes($_POST["db_pass"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_type=\''.addslashes($_POST["db_type"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_character_set=\''.addslashes($_POST["dolibarr_main_db_character_set"]).'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_db_collation=\''.addslashes($_POST["dolibarr_main_db_collation"]).'\';');
		fputs($fp,"\n");

		/* Authentication */
		fputs($fp, '$dolibarr_main_authentication=\'dolibarr\';');
		fputs($fp,"\n\n");

		fputs($fp, '# Specific settings');
        fputs($fp,"\n");

        fputs($fp, '$dolibarr_main_prod=\'0\';');
        fputs($fp,"\n");

        fputs($fp, '$dolibarr_nocsrfcheck=\'0\';');
        fputs($fp,"\n");

        fputs($fp, '$dolibarr_main_force_https=\''.$main_force_https.'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_main_cookie_cryptkey=\''.$key.'\';');
		fputs($fp,"\n");

		fputs($fp, '$dolibarr_mailing_limit_sendbyweb=\'0\';');
        fputs($fp,"\n");

		fputs($fp, '?>');
		fclose($fp);

		if (file_exists("$conffile"))
		{
			include("$conffile");	// On force rechargement. Ne pas mettre include_once !
			conf($dolibarr_main_document_root);

			print "<tr><td>";
			print $langs->trans("SaveConfigurationFile");
			print ' <strong>'.$conffile.'</strong>';
			print "</td><td>";
			print $langs->trans("OK");
			print "</td></tr>";
		}
		else
		{
			$error++;
		}
	}

	return $error;
}

?>
