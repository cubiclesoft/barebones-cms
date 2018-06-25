<?php
	// The official PHP SDK for lightweight Barebones CMS frontends.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class BarebonesCMSLite
	{
		public static function ProcessInfoDefaults($info, $defaults)
		{
			foreach ($defaults as $key => $val)
			{
				if (!isset($info[$key]))  $info[$key] = $val;
			}

			return $info;
		}

		public static function NormalizeAsset($info)
		{
			$defaults = array(
				"type" => "", "langinfo" => array(), "publish" => 0, "unpublish" => 0,
				"tags" => array(), "lastupdated" => 0, "lastupdatedby" => "", "lastip" => "",
				"files" => array(), "filesinfo" => array(), "protected" => array(), "uuid" => ""
			);

			return self::ProcessInfoDefaults($info, $defaults);
		}

		public static function GetPreferredLanguage($acceptlangs, $defaultlang)
		{
			$langs = array();
			$langs2 = array();
			$acceptlangs = explode(",", strtolower($acceptlangs));
			foreach ($acceptlangs as $lang)
			{
				$lang = trim($lang);
				$pos = strpos($lang, ";");
				if ($pos === false)  $q = 100;
				else
				{
					$q = trim(substr($lang, $pos + 1));
					$lang = substr($lang, 0, $pos);

					if (substr($q, 0, 2) == "q=")  $q = (int)(trim(substr($q, 2)) * 100);
				}

				$lang = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z0-9]/', " ", trim($lang))));

				if ($lang !== "" && !isset($langs2[$lang]))
				{
					while (isset($langs[$q]))  $q--;

					$langs[$q] = $lang;
					$langs2[$lang] = true;
				}
			}
			krsort($langs);

			// Look for an exact match in language preference order.
			foreach ($langs as $lang)
			{
				return $lang;
			}

			return $defaultlang;
		}

		public static function GetPreferredAssetLanguage($asset, $acceptlangs, $defaultlangs, $fallbackfirstlang = true, $removestrs = array())
		{
			if (!isset($asset["langinfo"]))  return false;

			// If the asset only has one language, then return that.
			if (count($asset["langinfo"]) < 2)
			{
				foreach ($asset["langinfo"] as $lang => &$info)  return $lang;

				return false;
			}

			// Parse the list of languages from Accept-Language (e.g. Accept-Language: fr-CH, fr;q=0.9, de;q=0.7, en;q=0.8, *;q=0.5).
			$langs = array();
			$langs2 = array();
			$acceptlangs = explode(",", strtolower($acceptlangs));
			foreach ($acceptlangs as $lang)
			{
				$lang = trim($lang);
				$pos = strpos($lang, ";");
				if ($pos === false)  $q = 100;
				else
				{
					$q = trim(substr($lang, $pos + 1));
					$lang = substr($lang, 0, $pos);

					if (substr($q, 0, 2) == "q=")  $q = (int)(trim(substr($q, 2)) * 100);
				}

				$lang = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z0-9]/', " ", trim($lang))));

				// Remove strings (e.g. internal language suffixes) found in user-submitted Accept-Language strings (e.g. '-newsletter' would be removed from 'en-us-newsletter').
				$found = false;
				foreach ($removestrs as $str)
				{
					if (stripos($lang, $str) !== false)
					{
						$lang = str_ireplace($str, "", $lang);

						$found = true;
					}
				}

				if ($found)  $lang = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z0-9]/', " ", $lang)));

				if ($lang !== "" && !isset($langs2[$lang]))
				{
					while (isset($langs[$q]))  $q--;

					$langs[$q] = $lang;
					$langs2[$lang] = true;
				}
			}
			krsort($langs);

			// Look for an exact match in language preference order.
			foreach ($langs as $lang)
			{
				if (isset($asset["langinfo"][$lang]))  return $lang;
			}

			// Look for a partial hyphenated match (e.g. 'en' is a 66.67% partial match for 'en-us' - that is, two out of three segments match).
			$partiallang = false;
			$partialpercent = 0;
			$langs2 = array();
			foreach ($asset["langinfo"] as $lang2 => &$info)  $langs2[$lang2] = explode("-", $lang2);
			foreach ($langs as $lang)
			{
				$words = explode("-", $lang);
				$numwords = count($words);

				foreach ($langs2 as $lang2 => $words2)
				{
					$y = min($numwords, count($words2));
					for ($x = 0; $x < $y && $words[$x] === $words2[$x]; $x++)
					{
					}

					if ($x)
					{
						$percent = ($x * 2) / ($numwords + count($words2));
						if ($partialpercent < $percent)
						{
							$partiallang = $lang2;
							$partialpercent = $percent;
						}
					}
				}
			}

			if ($partiallang !== false)  return $partiallang;

			// A default language, if it exists.
			if (!is_array($defaultlangs))  $defaultlangs = array($defaultlangs);
			foreach ($defaultlangs as $lang)
			{
				if (isset($asset["langinfo"][$lang]))  return $lang;
			}

			// First available option.
			if ($fallbackfirstlang)
			{
				foreach ($asset["langinfo"] as $lang => &$info)  return $lang;
			}

			return false;
		}

		public static function GetPreferredTag($tags, $prefix, $overrideprefix = false)
		{
			$preftag = false;
			$y = strlen($prefix);
			if ($overrideprefix !== false)  $y2 = strlen($overrideprefix);
			foreach ($tags as $tag)
			{
				if ($preftag === false && !strncmp($tag, $prefix, $y))  $preftag = $tag;
				else if ($overrideprefix !== false && !strncmp($tag, $overrideprefix, $y2))  $preftag = substr($tag, 1);
			}

			return $preftag;
		}

		// Determines if the user has supplied a valid content refresh token.
		public static function CanRefreshContent($validtoken, $requestkey = "refresh")
		{
			// If PHP sesssion are enabled and the user appears to have a valid session or request, check that first.
			unset($_COOKIE["bb_valid"]);
			if (session_status() !== PHP_SESSION_DISABLED)
			{
				if (isset($_COOKIE["bb"]) || (isset($_GET[$requestkey]) && $_GET[$requestkey] === $validtoken) || (isset($_POST[$requestkey]) && $_POST[$requestkey] === $validtoken))
				{
					// Close an existing session.
					$currsession = (session_status() === PHP_SESSION_ACTIVE);
					if ($currsession)  @session_write_close();

					// Switch to the 'bb' session.
					$prevname = @session_name("bb");
					@session_start();

					$_SESSION["ts"] = time();

					if (!isset($_SESSION["bb_cms_refresh_keys"]))  $_SESSION["bb_cms_refresh_keys"] = array();
					if (!isset($_SESSION["bb_cms_refresh_keys"][$validtoken]) && ((isset($_GET[$requestkey]) && $_GET[$requestkey] === $validtoken) || (isset($_POST[$requestkey]) && $_POST[$requestkey] === $validtoken)))  $_SESSION["bb_cms_refresh_keys"][$validtoken] = true;
					$valid = isset($_SESSION["bb_cms_refresh_keys"][$validtoken]);

					@session_write_close();
					@session_name($prevname);

					// Restore previous session (if any).
					if ($currsession)  @session_start();

					// Stop processing when the request is simply a heartbeat.
					if (isset($_POST["bb_heartbeat"]))  exit();

					if ($valid)  $_COOKIE["bb_valid"] = true;

					return $valid;
				}
			}

			// Fallback to using browser cookies.  Only supports one active refresh token at a time.
			if ((isset($_COOKIE[$requestkey]) && $_COOKIE[$requestkey] === $validtoken) || (isset($_GET[$requestkey]) && $_GET[$requestkey] === $validtoken) || (isset($_POST[$requestkey]) && $_POST[$requestkey] === $validtoken))
			{
				if (!isset($_COOKIE[$requestkey]) || $_COOKIE[$requestkey] !== $validtoken)  @setcookie($requestkey, $validtoken, 0, "", "", false, true);

				return true;
			}

			return false;
		}

		// Calculates the full path and filename to cached assets.
		public static function GetCachedAssetsFilename($contentdir, $type, $key)
		{
			$key = md5(json_encode($key, JSON_UNESCAPED_SLASHES));
			$filename = $contentdir . "/" . $type . "/" . substr($key, 0, 2) . "/assets_" . $key . ".dat";

			return $filename;
		}

		// Loads cached assets into memory.  Pays heed to publish/unpublish times.
		public static function LoadCachedAssets($contentdirfilename, $type = false, $key = false)
		{
			if ($type === false || $key === false)  $filename = $contentdirfilename;
			else  $filename = self::GetCachedAssetsFilename($contentdirfilename, $type, $key);

			if (!file_exists($filename))  return array();

			$data = @json_decode(file_get_contents($filename), true);
			if (!is_array($data))  return array();
			$assets = $data["assets"];
			unset($data);

			$ts = time();
			$result = array();
			foreach ($assets as $num => $asset)
			{
				$asset = self::NormalizeAsset($asset);
				unset($asset["lastupdatedby"]);
				unset($asset["lastip"]);
				unset($asset["protected"]);

				if ($asset["publish"] > 0 && $asset["publish"] <= $ts && ($asset["unpublish"] == 0 || $asset["unpublish"] > $ts))  $result[] = $asset;
			}

			return $result;
		}
	}

	class Request
	{
		protected static $hostcache;

		protected static function ProcessSingleInput($data)
		{
			foreach ($data as $key => $val)
			{
				if (is_string($val))  $_REQUEST[$key] = trim($val);
				else if (is_array($val))
				{
					$_REQUEST[$key] = array();
					foreach ($val as $key2 => $val2)  $_REQUEST[$key][$key2] = (is_string($val2) ? trim($val2) : $val2);
				}
				else  $_REQUEST[$key] = $val;
			}
		}

		// Cleans up all PHP input issues so that $_REQUEST may be used as expected.
		public static function Normalize()
		{
			self::ProcessSingleInput($_COOKIE);
			self::ProcessSingleInput($_GET);
			self::ProcessSingleInput($_POST);
		}

		public static function IsSSL()
		{
			return ((isset($_SERVER["HTTPS"]) && ($_SERVER["HTTPS"] == "on" || $_SERVER["HTTPS"] == "1")) || (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == "443") || (isset($_SERVER["REQUEST_URI"]) && str_replace("\\", "/", strtolower(substr($_SERVER["REQUEST_URI"], 0, 8))) == "https://"));
		}

		// Returns 'http[s]://www.something.com[:port]' based on the current page request.
		public static function GetHost($protocol = "")
		{
			$protocol = strtolower($protocol);
			$ssl = ($protocol == "https" || ($protocol == "" && self::IsSSL()));
			if ($protocol == "")  $type = "def";
			else if ($ssl)  $type = "https";
			else  $type = "http";

			if (!isset(self::$hostcache))  self::$hostcache = array();
			if (isset(self::$hostcache[$type]))  return self::$hostcache[$type];

			$url = "http" . ($ssl ? "s" : "") . "://";

			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			$str2 = strtolower($str);
			if (substr($str2, 0, 7) == "http://")
			{
				$pos = strpos($str, "/", 7);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 7, $pos);
			}
			else if (substr($str2, 0, 8) == "https://")
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "";
				else  $str = substr($str, 8, $pos);
			}
			else  $str = "";

			if ($str != "")  $host = $str;
			else if (isset($_SERVER["HTTP_HOST"]))  $host = $_SERVER["HTTP_HOST"];
			else  $host = $_SERVER["SERVER_NAME"] . ":" . (int)$_SERVER["SERVER_PORT"];

			$pos = strpos($host, ":");
			if ($pos === false)  $port = 0;
			else
			{
				$port = (int)substr($host, $pos + 1);
				$host = substr($host, 0, $pos);
			}
			if ($port < 1 || $port > 65535)  $port = ($ssl ? 443 : 80);
			$url .= preg_replace('/[^a-z0-9.\-]/', "", strtolower($host));
			if ($protocol == "" && ((!$ssl && $port != 80) || ($ssl && $port != 443)))  $url .= ":" . $port;
			else if ($protocol == "http" && !$ssl && $port != 80)  $url .= ":" . $port;
			else if ($protocol == "https" && $ssl && $port != 443)  $url .= ":" . $port;

			self::$hostcache[$type] = $url;

			return $url;
		}

		public static function GetURLBase()
		{
			$str = (isset($_SERVER["REQUEST_URI"]) ? str_replace("\\", "/", $_SERVER["REQUEST_URI"]) : "/");
			$pos = strpos($str, "?");
			if ($pos !== false)  $str = substr($str, 0, $pos);
			if (strncasecmp($str, "http://", 7) == 0 || strncasecmp($str, "https://", 8) == 0)
			{
				$pos = strpos($str, "/", 8);
				if ($pos === false)  $str = "/";
				else  $str = substr($str, $pos);
			}

			return $str;
		}

		public static function GetFullURLBase($protocol = "")
		{
			return self::GetHost($protocol) . self::GetURLBase();
		}

		public static function PrependHost($url, $protocol = "")
		{
			// Handle protocol-only.
			if (strncmp($url, "//", 2) == 0)
			{
				$host = self::GetHost($protocol);
				$pos = strpos($host, ":");
				if ($pos === false)  return $url;

				return substr($host, 0, $pos + 1) . $url;
			}

			if (strpos($url, ":") !== false)  return $url;

			// Handle relative paths.
			if ($url === "" || $url{0} !== "/")  return rtrim(self::GetFullURLBase($protocol), "/") . "/" . $url;

			// Handle '/path/'.
			$host = self::GetHost($protocol);

			return $host . $url;
		}
	}
?>