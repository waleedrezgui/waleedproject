<?php
/* Copyright (C) 2009  Regis Houssin  <regis@dolibarr.fr>
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
 *	\file       htdocs/core/class/cookie.class.php
 *	\ingroup    core
 *	\version	$Id: cookie.class.php,v 1.7 2011/07/31 23:45:13 eldy Exp $
 *	\brief      File of class to manage cookies
 */


/**
 *	\class      DolCookie
 *	\brief      Class to manage cookies
 */
class DolCookie
{
	var $myKey;
	var $myCookie;
	var $myValue;
	var $myExpire;
	var $myPath;
	var $myDomain;
	var	$mySsecure;
	var $cookiearray;
	var $cookie;

	/**
	 *      \brief      Constructor
	 *      \param      key      Personnal key
	 */
	function DolCookie($key = '')
	{
		$this->myKey = $key;
		$this->cookiearray = array();
		$this->cookie = "";
		$this->myCookie = "";
		$this->myValue = "";
	}


	/**
	 *      \brief      Encrypt en create the cookie
	 */
	function cryptCookie()
	{
		if (!empty($this->myKey))
		{
			$valuecrypt = base64_encode($this->myValue);
			for ($f=0 ; $f<=dol_strlen($valuecrypt)-1; $f++)
			{
				$this->cookie .= intval(ord($valuecrypt[$f]))*$this->myKey."|";
			}
		}
		else
		{
			$this->cookie = $this->myValue;
		}

		setcookie($this->myCookie, $this->cookie, $this->myExpire, $this->myPath, $this->myDomain, $this->mySecure);
	}

	/**
	 *      \brief      Decrypt the cookie
	 */
	function decryptCookie()
	{
		if (!empty($this->myKey))
		{
			$this->cookiearray = explode("|",$_COOKIE[$this->myCookie]);
			$this->myValue = "" ;
			for ($f=0 ; $f<=count($this->cookiearray)-2; $f++)
			{
				$this->myValue .= strval(chr($this->cookiearray[$f]/$this->myKey));
			}

			return(base64_decode($this->myValue)) ;
		}
		else
		{
			return($_COOKIE[$this->myCookie]);
		}
	}

	/**
	 *      \brief  Set and create the cookie
	 *      \param  cookie  Cookie name
	 *      \param  value   Cookie value
	 */
	function _setCookie($cookie, $value, $expire=0, $path="/", $domain="", $secure=0)
	{
		$this->myCookie = $cookie;
		$this->myValue = $value;
		$this->myExpire = $expire;
		$this->myPath = $path;
		$this->myDomain = $domain;
		$this->mySecure = $secure;

		//print 'key='.$this->myKey.' name='.$this->myCookie.' value='.$this->myValue.' expire='.$this->myExpire;

		$this->cryptCookie();
	}

	/**
	 *      \brief   Get the cookie
	 *      \param   cookie         Cookie name
	 *      \param   value          Cookie value
	 *      \return  decryptValue   Decrypted value
	 */
	function _getCookie($cookie)
	{
		$this->myCookie = $cookie;

		$decryptValue = $this->decryptCookie();

		return $decryptValue;
	}

}

?>