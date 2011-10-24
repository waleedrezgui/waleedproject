<?php
/* Copyright (C) 2006-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
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
 *		\file 		htdocs/admin/tools/export.php
 *		\brief      Page to export a database into a dump file
 *		\version    $Id: export.php,v 1.45 2011/08/03 00:45:43 eldy Exp $
 */

require("../../main.inc.php");
require_once(DOL_DOCUMENT_ROOT."/lib/admin.lib.php");
require_once(DOL_DOCUMENT_ROOT."/lib/files.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/class/html.formfile.class.php");
include_once $dolibarr_main_document_root."/lib/databases/".$conf->db->type.".lib.php";

$what=$_REQUEST["what"];
$export_type=$_REQUEST["export_type"];
$file=isset($_POST['filename_template']) ? $_POST['filename_template'] : '';

$langs->load("admin");

if (! $user->admin)
  accessforbidden();


if ($file && ! $what)
{
   //print DOL_URL_ROOT.'/dolibarr_export.php';
	header("Location: ".DOL_URL_ROOT.'/admin/tools/dolibarr_export.php?msg='.urlencode($langs->trans("ErrorFieldRequired",$langs->transnoentities("ExportMethod"))));
/*
	print '<div class="error">'.$langs->trans("ErrorFieldRequired",$langs->trans("ExportMethod")).'</div>';
	print '<br>';
*/
	exit;
}



/*
 * View
 */

llxHeader('','','EN:Backups|FR:Sauvegardes|ES:Copias_de_seguridad');

$html=new Form($db);
$formfile = new FormFile($db);

print_fiche_titre($langs->trans("Backup"),'','setup');

$ExecTimeLimit=600;
if (!empty($ExecTimeLimit)) {
    // Cette page peut etre longue. On augmente le delai autorise.
    // Ne fonctionne que si on est pas en safe_mode.
    $err=error_reporting();
    error_reporting(0);     // Disable all errors
    //error_reporting(E_ALL);
    @set_time_limit($ExecTimeLimit);   // Need more than 240 on Windows 7/64
    error_reporting($err);
}
if (!empty($MemoryLimit)) {
    @ini_set('memory_limit', $MemoryLimit);
}

// Start with empty buffer
$dump_buffer = '';
$dump_buffer_len = 0;

// We send fake headers to avoid browser timeout when buffering
$time_start = time();


// MYSQL
if ($what == 'mysql')
{
	$cmddump=$_POST["mysqldump"];
	if ($cmddump)
	{
		dolibarr_set_const($db, 'SYSTEMTOOLS_MYSQLDUMP', $cmddump,'chaine',0,'',$conf->entity);
	}

	$outputdir  = $conf->admin->dir_output.'/backup';
	$outputfile = $outputdir.'/'.$file;
	// for compression format, we add extension
	$compression=isset($_POST['compression']) ? $_POST['compression'] : 'none';
	if ($compression == 'gz') $outputfile.='.gz';
	if ($compression == 'bz') $outputfile.='.bz2';
	$outputerror = $outputfile.'.err';
	dol_mkdir($conf->admin->dir_output.'/backup');

	// Parameteres execution
	$command=$cmddump;
	if (preg_match("/\s/",$command)) $command=escapeshellarg($command);	// Use quotes on command

	//$param=escapeshellarg($dolibarr_main_db_name)." -h ".escapeshellarg($dolibarr_main_db_host)." -u ".escapeshellarg($dolibarr_main_db_user)." -p".escapeshellarg($dolibarr_main_db_pass);
	$param=$dolibarr_main_db_name." -h ".$dolibarr_main_db_host;
	$param.=" -u ".$dolibarr_main_db_user;
	if (! empty($dolibarr_main_db_port)) $param.=" -P ".$dolibarr_main_db_port;
	if (! $_POST["use_transaction"]) $param.=" -l --single-transaction";
	if ($_POST["disable_fk"])        $param.=" -K";
	if ($_POST["sql_compat"] && $_POST["sql_compat"] != 'NONE') $param.=" --compatible=".$_POST["sql_compat"];
	if ($_POST["drop_database"])     $param.=" --add-drop-database";
	if ($_POST["sql_structure"])
	{
		if ($_POST["drop"])			 $param.=" --add-drop-table";
	}
	else
	{
		$param.=" -t";
	}
	if ($_POST["sql_data"])
	{
		$param.=" --tables";
		if ($_POST["showcolumns"])	$param.=" -c";
		if ($_POST["extended_ins"])	$param.=" -e";
		if ($_POST["delayed"])	 	$param.=" --delayed-insert";
		if ($_POST["sql_ignore"])	$param.=" --insert-ignore";
		if ($_POST["hexforbinary"])	$param.=" --hex-blob";
	}
	else
	{
		$param.=" -d";
	}
	$paramcrypted=$param;
	$paramclear=$param;
	if (! empty($dolibarr_main_db_pass))
	{
		$paramcrypted.=" -p".preg_replace('/./i','*',$dolibarr_main_db_pass);
		$paramclear.=" -p".$dolibarr_main_db_pass;
	}

	print '<b>'.$langs->trans("RunCommandSummary").':</b><br>'."\n";
	print '<textarea rows="'.ROWS_2.'" cols="120">'.$command." ".$paramcrypted.'</textarea><br>'."\n";

	print '<br>';


	// Now run command and show result
	print '<b>'.$langs->trans("BackupResult").':</b> ';

	$errormsg='';

	$result=dol_mkdir($outputdir);

	// Debut appel methode execution
	$fullcommandcrypted=$command." ".$paramcrypted." 2>&1";
	$fullcommandclear=$command." ".$paramclear." 2>&1";
	if ($compression == 'none') $handle = fopen($outputfile, 'w');
	if ($compression == 'gz')   $handle = gzopen($outputfile, 'w');
	if ($compression == 'bz')   $handle = bzopen($outputfile, 'w');

	if ($handle)
	{
	    $ok=0;
		dol_syslog("Run command ".$fullcommandcrypted);
		$handlein = popen($fullcommandclear, 'r');
		while (!feof($handlein))
		{
			$read = fgets($handlein);
			fwrite($handle,$read);
			if (preg_match('/-- Dump completed/i',$read)) $ok=1;
		}
		pclose($handlein);

		if ($compression == 'none') fclose($handle);
		if ($compression == 'gz')   gzclose($handle);
		if ($compression == 'bz')   bzclose($handle);

		if (! empty($conf->global->MAIN_UMASK))
			@chmod($outputfile, octdec($conf->global->MAIN_UMASK));
	}
	else
	{
		$langs->load("errors");
		dol_syslog("Failed to open file ".$outputfile,LOG_ERR);
		$errormsg=$langs->trans("ErrorFailedToWriteInDir");
	}
	// Get errorstring
	if ($compression == 'none') $handle = fopen($outputfile, 'r');
	if ($compression == 'gz')   $handle = gzopen($outputfile, 'r');
	if ($compression == 'bz')   $handle = bzopen($outputfile, 'r');
	if ($handle)
	{
		// Get 2048 first chars of error message.
		$errormsg = fgets($handle,2048);
		// Close file
		if ($compression == 'none') fclose($handle);
		if ($compression == 'gz')   gzclose($handle);
		if ($compression == 'bz')   bzclose($handle);
		if ($ok && preg_match('/^-- MySql/i',$errormsg)) $errormsg='';	// Pas erreur
		else
		{
			// Renommer fichier sortie en fichier erreur
			//print "$outputfile -> $outputerror";
			@dol_delete_file($outputerror,1);
			@rename($outputfile,$outputerror);
			// Si safe_mode on et command hors du parametre exec, on a un fichier out vide donc errormsg vide
			if (! $errormsg) $errormsg=$langs->trans("ErrorFailedToRunExternalCommand");
		}
	}
	// Fin execution commande
}

// POSTGRESQL
if ($what == 'postgresql')
{
	$cmddump=$_POST["postgresqldump"];
	if ($cmddump)
	{
		dolibarr_set_const($db, 'SYSTEMTOOLS_POSTGRESQLDUMP', $cmddump,'chaine',0,'',$conf->entity);
	}

	$outputdir  = $conf->admin->dir_output.'/backup';
	$outputfile = $outputdir.'/'.$file;
	// for compression format, we add extension
	$compression=isset($_POST['compression']) ? $_POST['compression'] : 'none';
	if ($compression == 'gz') $outputfile.='.gz';
	if ($compression == 'bz') $outputfile.='.bz2';
	$outputerror = $outputfile.'.err';
	dol_mkdir($conf->admin->dir_output.'/backup');

	// Parameteres execution
	$command=$cmddump;
	if (preg_match("/\s/",$command)) $command=$command=escapeshellarg($command);	// Use quotes on command

	//$param=escapeshellarg($dolibarr_main_db_name)." -h ".escapeshellarg($dolibarr_main_db_host)." -u ".escapeshellarg($dolibarr_main_db_user)." -p".escapeshellarg($dolibarr_main_db_pass);
	$param=" --no-tablespaces --inserts -h ".$dolibarr_main_db_host;
	$param.=" -U ".$dolibarr_main_db_user;
	if (! empty($dolibarr_main_db_port)) $param.=" -p ".$dolibarr_main_db_port;
	if ($_POST["sql_compat"] && $_POST["sql_compat"] == 'ANSI') $param.="  --disable-dollar-quoting";
	if ($_POST["drop_database"])     $param.=" -c -C";
	if ($_POST["sql_structure"])
	{
		if ($_POST["drop"])			 $param.=" --add-drop-table";
		if (empty($_POST["sql_data"])) $param.=" -s";
	}
	if ($_POST["sql_data"])
	{
		if (empty($_POST["sql_structure"]))	 	$param.=" -a";
		if ($_POST["showcolumns"])	$param.=" -c";
	}
	$param.=' -f "'.$outputfile.'"';
	//if ($compression == 'none')
	if ($compression == 'gz')   $param.=' -Z 9';
	//if ($compression == 'bz')
	$paramcrypted=$param;
	$paramclear=$param;
	if (! empty($dolibarr_main_db_pass))
	{
		//$paramcrypted.=" -W".preg_replace('/./i','*',$dolibarr_main_db_pass);
		//$paramclear.=" -W".$dolibarr_main_db_pass;
	}
	$paramcrypted.=" -w ".$dolibarr_main_db_name;
	$paramclear.=" -w ".$dolibarr_main_db_name;

	print $langs->trans("RunCommandSummaryToLaunch").':<br>'."\n";
	print '<textarea rows="'.ROWS_3.'" cols="120">'.$command." ".$paramcrypted.'</textarea><br>'."\n";

	print '<br>';


	// Now show to ask to run command
	print $langs->trans("YouMustRunCommandFromCommandLineAfterLoginToUser",$dolibarr_main_db_user,$dolibarr_main_db_user);

	print '<br>';
	print '<br>';

	$what='';
}




// Si on a demande une generation
if ($what)
{
	if ($errormsg)
	{
		print '<div class="error">'.$langs->trans("Error")." : ".$errormsg.'</div>';
//		print '<a href="'.DOL_URL_ROOT.$relativepatherr.'">'.$langs->trans("DownloadErrorFile").'</a><br>';
		print '<br>';
		print '<br>';
	}
	else
	{
		print '<div class="ok">';
		print $langs->trans("BackupFileSuccessfullyCreated").'.<br>';
		print $langs->trans("YouCanDownloadBackupFile");
		print '</div>';
		print '<br>';
	}
}

$result=$formfile->show_documents('systemtools','backup',$conf->admin->dir_output.'/backup',$_SERVER['PHP_SELF'],0,1,'',1,0,0,54,0,'',$langs->trans("PreviousDumpFiles"));

if ($result == 0)
{
	print $langs->trans("NoBackupFileAvailable").'<br>';
	print $langs->trans("ToBuildBackupFileClickHere",DOL_URL_ROOT.'/admin/tools/dolibarr_export.php').'<br>';
}

print '<br>';

$time_end = time();

llxFooter('$Date: 2011/08/03 00:45:43 $ - $Revision: 1.45 $');
?>
