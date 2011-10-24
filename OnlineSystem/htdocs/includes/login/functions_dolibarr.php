<?php
/* Copyright (C) 2007-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2007-2009 Regis Houssin        <regis@dolibarr.fr>
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
 *      \file       htdocs/includes/login/functions_dolibarr.php
 *      \ingroup    core
 *      \brief      Authentication functions for Dolibarr mode
 *		\version	$Id: functions_dolibarr.php,v 1.13 2011/07/31 23:29:11 eldy Exp $
 */


/**
 *      Check user and password
 *      @param		usertotest		Login
 *      @param		passwordtotest	Password
 *      @param      entitytotest    Entity
 *      @return		string			Login if ok, '' if ko.
 */
function check_user_password_dolibarr($usertotest,$passwordtotest,$entitytotest=1)
{
	global $db,$conf,$langs;

	dol_syslog("functions_dolibarr::check_user_password_dolibarr usertotest=".$usertotest);

	$login='';

	if (! empty($usertotest))
	{
		// If test username/password asked, we define $test=false and $login var if ok, set $_SESSION["dol_loginmesg"] if ko
		$table = MAIN_DB_PREFIX."user";
		$usernamecol = 'login';
		$entitycol = 'entity';

		$sql ='SELECT pass, pass_crypted';
		$sql.=' FROM '.$table;
		$sql.=' WHERE '.$usernamecol." = '".$db->escape($usertotest)."'";
		$sql.=' AND '.$entitycol." IN (0," . ($entitytotest ? $entitytotest : 1) . ")";

		dol_syslog("functions_dolibarr::check_user_password_dolibarr sql=".$sql);
		$resql=$db->query($sql);
		if ($resql)
		{
			$obj=$db->fetch_object($resql);
			if ($obj)
			{
				$passclear=$obj->pass;
				$passcrypted=$obj->pass_crypted;
				$passtyped=$passwordtotest;

				$passok=false;

				// Check crypted password
				$cryptType='';
				if (! empty($conf->global->DATABASE_PWD_ENCRYPTED)) $cryptType=$conf->global->DATABASE_PWD_ENCRYPTED;
				// By default, we used MD5
				if (! in_array($cryptType,array('md5'))) $cryptType='md5';
				// Check crypted password according to crypt algorithm
				if ($cryptType == 'md5')
				{
					if (md5($passtyped) == $passcrypted)
					{
						$passok=true;
						dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification ok - ".$cryptType." of pass is ok");
					}
				}

				// For compatibility with old versions
				if (! $passok)
				{
					if ((! $passcrypted || $passtyped)
						&& ($passtyped == $passclear))
					{
						$passok=true;
						dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification ok - found pass in database");
					}
				}

				// Password ok ?
				if ($passok)
				{
					$login=$usertotest;
				}
				else
				{
					dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification ko bad password pour '".$usertotest."'");
					sleep(1);
					$langs->load('main');
					$langs->load('other');
					$_SESSION["dol_loginmesg"]=$langs->trans("ErrorBadLoginPassword");
				}
			}
			else
			{
				dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification ko user not found for '".$usertotest."'");
				sleep(1);
				$langs->load('main');
				$langs->load('other');
				$_SESSION["dol_loginmesg"]=$langs->trans("ErrorBadLoginPassword");
			}
		}
		else
		{
			dol_syslog("functions_dolibarr::check_user_password_dolibarr Authentification ko db error for '".$usertotest."' error=".$db->lasterror());
			sleep(1);
			$_SESSION["dol_loginmesg"]=$db->lasterror();
		}
	}

	return $login;
}


?>