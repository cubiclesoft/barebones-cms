<?php
	// The official PHP SDK for the Barebones CMS API.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class BarebonesCMS
	{
		protected $web, $fp, $host, $apikey, $apisecret;

		public function __construct()
		{
			if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";
			if (!class_exists("RemotedAPI", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/sdk_remotedapi.php";

			$this->web = new WebBrowser();
			$this->fp = false;
			$this->host = false;
			$this->apikey = false;
			$this->apisecret = false;
		}

		public function SetAccessInfo($host, $apikey, $apisecret = false)
		{
			$this->web = new WebBrowser();
			if (is_resource($this->fp))  @fclose($this->fp);
			$this->fp = false;
			$this->host = $host;
			$this->apikey = $apikey;
			$this->apisecret = $apisecret;
		}

		public function CheckAccess()
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "access"
			);

			return $this->RunAPI("GET", $apipath);
		}

		public function Internal_BulkCallback($response, $body, &$opts)
		{
			if ($response["code"] == 200)
			{
				$opts["body"] .= $body;

				while (($pos = strpos($opts["body"], "\n")) !== false)
				{
					$data = @json_decode(trim(substr($opts["body"], 0, $pos)), true);
					$opts["body"] = substr($opts["body"], $pos + 1);

					if (is_array($data) && isset($data["success"]))  call_user_func_array($opts["callback"], array($data));
				}
			}

			return true;
		}

		public function GetRevisions($id, $revision = false, $bulkcallback = false, $limit = false, $offset = false)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "revisions",
				"id" => $id
			);

			if ($revision !== false)  $apipath["revision"] = $revision;

			$options = array();
			if (!is_callable($bulkcallback))  $bulkcallback = false;
			if ($bulkcallback !== false)
			{
				$apipath["bulk"] = "1";

				$options["read_body_callback"] = array($this, "Internal_BulkCallback");
				$options["read_body_callback_opts"] = array("body" => "", "callback" => $bulkcallback);
			}

			if ($limit !== false)
			{
				$apipath["limit"] = (int)$limit;
				if ($offset !== false)  $apipath["offset"] = (int)$offset;
			}

			return $this->RunAPI("GET", $apipath, $options, 200, true, ($bulkcallback === false));
		}

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

		private static function Internal_AppendGetAssetsOption(&$apipath, &$options, $key)
		{
			if (isset($options[$key]))
			{
				if (!is_array($options[$key]))  $options[$key] = array($options[$key]);
				$items = array();
				foreach ($options[$key] as $item)
				{
					if (is_string($item))  $items[] = $item;
				}

				if (count($items))  $apipath[$key . "[]"] = $items;
			}
		}

		// Valid options:
		// * tag - A string or an array of tags to search for.  Prefix ~ to perform a partial match.  Prefix ! to not allow the tag to match.
		// * id - A string or array of IDs to retrieve.  Prefix ! to not match a given ID.
		// * uuid - A string or array of UUIDs to retrieve.  Prefix ! to not match a given UUID.
		// * type - A string or array of types to retrieve.  Prefix ! to not match a given type.
		// * q - A string or array of strings to search for a phrase match in the data blobs (can be slow).  Prefix ! to not match a given query string.
		// * qe - A string or array of strings to search for an exact match in the data blobs (can be slow).  Prefix ! to not match a given query string.
		// * start - An integer containing a UNIX timestamp.
		// * end - An integer containing a UNIX timestamp.
		// * order - A string containing one of 'lastupdated', 'unpublish', or 'publish'
		public function GetAssets($options, $limit = false, $bulkcallback = false)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "assets"
			);

			self::Internal_AppendGetAssetsOption($apipath, $options, "tag");
			self::Internal_AppendGetAssetsOption($apipath, $options, "id");
			self::Internal_AppendGetAssetsOption($apipath, $options, "uuid");
			self::Internal_AppendGetAssetsOption($apipath, $options, "type");
			self::Internal_AppendGetAssetsOption($apipath, $options, "q");
			self::Internal_AppendGetAssetsOption($apipath, $options, "qe");

			if (isset($options["start"]) && is_int($options["start"]))  $apipath["start"] = (int)$options["start"];
			if (isset($options["end"]) && is_int($options["end"]))  $apipath["end"] = (int)$options["end"];
			if (isset($options["order"]) && is_string($options["order"]) && ($options["order"] === "lastupdated" || $options["order"] === "unpublish" || $options["order"] === "publish"))  $apipath["order"] = $options["order"];
			if ($limit !== false && is_int($limit))  $apipath["limit"] = (int)$limit;

			$options = array();
			if (!is_callable($bulkcallback))  $bulkcallback = false;
			if ($bulkcallback !== false)
			{
				$apipath["bulk"] = "1";

				$options["read_body_callback"] = array($this, "Internal_BulkCallback");
				$options["read_body_callback_opts"] = array("body" => "", "callback" => $bulkcallback);
			}

			return $this->RunAPI("GET", $apipath, $options, 200, true, ($bulkcallback === false));
		}

		// Minimum requirements:
		// * type - A string.
		// * langinfo - An array containing at least one non-emtpy 'title'.
		public function StoreAsset($asset, $makerevision = true)
		{
			return $this->RunAPI("POST", array(), array("ver" => "1", "ts" => time(), "api" => "asset", "asset" => $this->NormalizeAsset($asset), "makerevision" => ($makerevision ? "1" : "0")));
		}

		public function DeleteAsset($id)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "delete_asset",
				"id" => $id
			);

			return $this->RunAPI("GET", $apipath, array(), 200, false);
		}

		// Valid options:
		// * tag - A string to search for.  Prefix ~ to perform a partial match.
		// * start - An integer containing a UNIX timestamp.
		// * end - An integer containing a UNIX timestamp.
		// * order - A string containing one of 'tag', 'publish', or 'numtags'
		public function GetTags($options, $limit = false, $offset = false, $bulkcallback = false)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "tags"
			);

			if (isset($options["tag"]) && is_string($options["tag"]))  $apipath["tag"] = $options["tag"];
			if (isset($options["start"]) && is_int($options["start"]))  $apipath["start"] = (int)$options["start"];
			if (isset($options["end"]) && is_int($options["end"]))  $apipath["end"] = (int)$options["end"];
			if (isset($options["order"]) && is_string($options["order"]) && ($options["order"] === "tag" || $options["order"] === "publish" || $options["order"] === "numtags"))  $apipath["order"] = $options["order"];
			if ($limit !== false && is_int($limit))  $apipath["limit"] = (int)$limit;
			if ($offset !== false && is_int($offset))  $apipath["offset"] = (int)$offset;

			$options = array();
			if (!is_callable($bulkcallback))  $bulkcallback = false;
			if ($bulkcallback !== false)
			{
				$apipath["bulk"] = "1";

				$options["read_body_callback"] = array($this, "Internal_BulkCallback");
				$options["read_body_callback_opts"] = array("body" => "", "callback" => $bulkcallback);
			}

			return $this->RunAPI("GET", $apipath, $options, 200, true, ($bulkcallback === false));
		}

		public function StartUpload($id, $filename, $lastupdatedby = "", $makerevision = true)
		{
			$options = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "upload_start",
				"id" => (string)$id,
				"filename" => (string)$filename,
				"lastupdatedby" => $lastupdatedby,
				"makerevision" => ($makerevision ? "1" : "0")
			);

			return $this->RunAPI("POST", array(), $options);
		}

		public function UploadFile($id, $filename, $start, $data)
		{
			$options = array(
				"postvars" => array(
					"ver" => "1",
					"ts" => (string)time(),
					"api" => "upload",
					"id" => (string)$id,
					"filename" => $filename,
					"start" => (string)$start
				),
				"files" => array(
					array(
						"name" => "file",
						"filename" => $filename,
						"type" => "application/octet-stream",
						"data" => $data
					)
				)
			);

			return $this->RunAPI("POST", array(), $options, 200, false);
		}

		public function UploadDone($id, $filename)
		{
			$options = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "upload_done",
				"id" => (string)$id,
				"filename" => (string)$filename
			);

			return $this->RunAPI("POST", array(), $options);
		}

		public function Internal_DownloadCallback($response, $body, &$opts)
		{
			if ($response["code"] == 200)
			{
				if (is_resource($opts[0]))  fwrite($opts[0], $body);
				else if (is_callable($opts[0]))  call_user_func_array($opts[0], array($opts[1], $body));
			}

			return true;
		}

		// Depending on what happens with file uploads on the server (e.g. a process that moves uploaded files to a CDN), this may or may not work.
		// Useful for dealing with files managed by Remoted API Server or where additional processing is required (e.g. resizing/cropping images).
		// A local file cache is highly recommended to avoid using the API for repeated binary data retrieval.
		public function DownloadFile($id, $filename, $fpcallback = false, $callbackopts = false, $start = 0, $size = false, $recvratelimit = false)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "file",
				"id" => (string)$id,
				"filename" => (string)$filename
			);

			if ($start > 0)  $apipath["start"] = $start;
			if ($size !== false)  $apipath["size"] = $size;

			$options = array();
			if ($fpcallback !== false)
			{
				$options["read_body_callback"] = array($this, "Internal_DownloadCallback");
				$options["read_body_callback_opts"] = array($fpcallback, $callbackopts);
			}
			if ($recvratelimit !== false)  $options["recvratelimit"] = $recvratelimit;

			return $this->RunAPI("GET", $apipath, $options, 200, true, false);
		}

		public function DeleteUpload($id, $filename, $lastupdatedby = "", $makerevision = true)
		{
			$apipath = array(
				"ver" => "1",
				"ts" => time(),
				"api" => "delete_upload",
				"id" => $id,
				"filename" => $filename,
				"lastupdatedby" => $lastupdatedby,
				"makerevision" => ($makerevision ? "1" : "0")
			);

			return $this->RunAPI("GET", $apipath, array(), 200, false);
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

		public static function GetDefaultMimeInfoMap()
		{
			$result = array(
				".jpg" => array("type" => "image/jpeg", "preview" => true),
				".jpeg" => array("type" => "image/jpeg", "preview" => true),
				".png" => array("type" => "image/png", "preview" => true),
				".gif" => array("type" => "image/gif", "preview" => true),
				".svg" => array("type" => "image/svg+xml", "preview" => false),
				".mp3" => array("type" => "audio/mpeg", "preview" => true),
				".wav" => array("type" => "audio/wav", "preview" => true),
				".mp4" => array("type" => "video/mp4", "preview" => true),
				".webm" => array("type" => "video/webm", "preview" => true),
				".pdf" => array("type" => "application/pdf", "preview" => false),
			);

			return $result;
		}

		public static function GetFileExtension($filename)
		{
			$pos = strrpos($filename, ".");
			if ($pos === false)  return "";

			return substr($filename, $pos);
		}

		// Generates a digital signature to prevent abuse of system resources (image manipulation operations use a lot of CPU and cache storage might be limited).
		public static function GetFileSignature($id, $path, $filename, $crop, $maxwidth, $secret)
		{
			return hash_hmac("sha1", (string)$id . "|" . (string)$path . "|" . (string)$filename . "|" . (string)$crop . "|" . (string)$maxwidth, $secret);
		}

		public static function IsValidFileSignature($id, $path, $filename, $crop, $maxwidth, $signature, $secret)
		{
			return (self::GetFileSignature($id, $path, $filename, $crop, $maxwidth, $secret) === $signature);
		}

		// Produces results that are similar to the API.
		public static function SanitizeFilename($filename)
		{
			return preg_replace('/\s+/', "-", trim(trim(preg_replace('/[^A-Za-z0-9_.\-~]/', " ", $filename), ".")));
		}

		public static function CanResizeImage($mimeinfomap, $fileext)
		{
			return (isset($mimeinfomap[$fileext]) && ($mimeinfomap[$fileext]["type"] === "image/jpeg" || $mimeinfomap[$fileext]["type"] === "image/png" || $mimeinfomap[$fileext]["type"] === "image/gif"));
		}

		public static function GetCroppedAndScaledFilename($filename, $fileext, $crop, $maxwidth)
		{
			$crop = explode(",", preg_replace('/[^0-9.,]/', "", $crop));

			if (count($crop) != 4 || $crop[0] === $crop[2] || $crop[1] === $crop[3] || $crop[0] < 0 || $crop[1] < 0 || $crop[2] < 0 || $crop[3] < 0)  $crop = "";
			else
			{
				// Truncate percentage crops to 5 decimal places.
				foreach ($crop as $num => $val)
				{
					$pos = strpos($val, ".");
					if ($pos !== false)  $crop[$num] = substr($val, 0, $pos + 6);
				}

				$crop = implode(",", $crop);
			}

			$result = substr($filename, 0, -strlen($fileext)) . "." . ($crop !== "" ? $crop . "_" : "") . (int)$maxwidth . $fileext;

			return $result;
		}

		public static function GetDestCropAndSize(&$cropx, &$cropy, &$cropw, &$croph, &$destwidth, &$destheight, $srcwidth, $srcheight, $crop, $maxwidth)
		{
			$cropx = 0;
			$cropy = 0;
			$cropw = (int)$srcwidth;
			$croph = (int)$srcheight;

			if ($crop !== "")
			{
				$crop = explode(",", preg_replace('/[^0-9.,]/', "", $crop));
				if (count($crop) == 4 && $crop[0] !== $crop[2] && $crop[1] !== $crop[3] && $crop[0] >= 0 && $crop[1] >= 0 && $crop[2] >= 0 && $crop[3] >= 0 && $crop[0] < $srcwidth && $crop[1] < $srcheight && $crop[2] < $srcwidth && $crop[3] < $srcheight)
				{
					$cropx = (double)$crop[0];
					$cropy = (double)$crop[1];
					$cropw = (double)$crop[2];
					$croph = (double)$crop[3];

					// Normalize.
					if ($cropx > $cropw)
					{
						$temp = $cropx;
						$cropx = $cropw;
						$cropw = $temp;
					}
					if ($cropy > $croph)
					{
						$temp = $cropy;
						$cropy = $croph;
						$croph = $temp;
					}
					if ($cropw < 1.00001 && $croph < 1.00001)
					{
						// Assume percentage of the image.
						$cropx = $cropx * $srcwidth;
						$cropy = $cropy * $srcheight;
						$cropw = $cropw * $srcwidth;
						$croph = $croph * $srcheight;
					}

					$cropw = (int)($cropw - $cropx);
					$croph = (int)($croph - $cropy);
					$cropx = (int)$cropx;
					$cropy = (int)$cropy;
				}
			}

			// Calculate final image width and height.
			if ($cropw <= $maxwidth)
			{
				$destwidth = $cropw;
				$destheight = $croph;
			}
			else
			{
				$destwidth = $maxwidth;
				$destheight = (int)($croph * $destwidth / $cropw);
			}
		}

		public static function CropAndScaleImage($data, $crop, $maxwidth)
		{
			@ini_set("memory_limit", "512M");

			// Detect which image library is available to crop and scale the image.
			if (extension_loaded("imagick"))
			{
				// ImageMagick.
				try
				{
					$img = new Imagick();
					$img->readImageBlob($data);
					$info = $img->getImageGeometry();
					$srcwidth = $info["width"];
					$srcheight = $info["height"];

					if ($srcwidth <= $maxwidth && $crop === "")  return array("success" => true, "data" => $data);

					// Calculate various points.
					self::GetDestCropAndSize($cropx, $cropy, $cropw, $croph, $destwidth, $destheight, $srcwidth, $srcheight, $crop, $maxwidth);
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => self::CMS_Translate("Unable to load image."), "errorcode" => "image_load_failed");
				}

				try
				{
					// Crop the image.
					if ($crop !== "")
					{
						$img->cropImage($cropw, $croph, $cropx, $cropy);

						// Strip out EXIF and 8BIM profiles (if any) since embedded thumbnails no longer match the actual image.
						$profiles = $img->getImageProfiles("*", false);
						foreach ($profiles as $profile)
						{
							if ($profile === "exif" || $profile === "8bim")  $img->removeImageProfile($profile);
						}
					}

					// Resize the image.
					$img->resizeImage($destwidth, $destheight, imagick::FILTER_CATROM, 1);

					// Gather the result.
					return array("success" => true, "data" => $img->getImageBlob());
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => self::CMS_Translate("Unable to crop/resize image."), "errorcode" => "image_crop_resize_failed");
				}
			}
			else if (extension_loaded("gd") && function_exists("gd_info"))
			{
				// GD.
				$info = @getimagesizefromstring($data);
				if ($info === false)  return array("success" => false, "error" => self::CMS_Translate("Unable to load image."), "errorcode" => "image_load_failed");
				$srcwidth = $info[0];
				$srcheight = $info[1];
				$type = $info[2];

				if ($type !== IMAGETYPE_JPEG && $type !== IMAGETYPE_PNG && $type !== IMAGETYPE_GIF)  return array("success" => false, "error" => self::CMS_Translate("Unsupported image format."), "errorcode" => "unsupported_image_format");
				if ($srcwidth <= $maxwidth && $crop === "")  return array("success" => true, "data" => $data);

				// Calculate various points.
				self::GetDestCropAndSize($cropx, $cropy, $cropw, $croph, $destwidth, $destheight, $srcwidth, $srcheight, $crop, $maxwidth);

				$img = @imagecreatefromstring($data);
				if ($img === false)  return array("success" => false, "error" => self::CMS_Translate("Unable to load image."), "errorcode" => "image_load_failed");
				$data = "";

				$img2 = @imagecreatetruecolor($destwidth, $destheight);
				if ($img2 === false)
				{
					imagedestroy($img);

					return array("success" => false, "error" => self::CMS_Translate("Unable to crop/resize image."), "errorcode" => "image_crop_resize_failed");
				}

				// Make fully transparent (if relevant).
				if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF)
				{
					$transparent = imagecolorallocatealpha($img2, 0, 0, 0, 127);
					imagecolortransparent($img2, $transparent);
					imagealphablending($img2, false);
					imagesavealpha($img2, true);
					imagefill($img2, 0, 0, $transparent);
				}

				// Copy the source onto the destination, resizing in the process.
				imagecopyresampled($img2, $img, 0, 0, $cropx, $cropy, $destwidth, $destheight, $cropw, $croph);
				imagedestroy($img);

				ob_start();
				if ($type === IMAGETYPE_JPEG)  @imagejpeg($img2, NULL, 85);
				else if ($type === IMAGETYPE_PNG)  @imagepng($img2);
				else if ($type === IMAGETYPE_GIF)  @imagegif($img2);
				$data = ob_get_contents();
				ob_end_clean();

				imagedestroy($img2);

				return array("success" => true, "data" => $data);
			}

			return array("success" => false, "error" => self::CMS_Translate("A supported image library is not installed/configured."), "errorcode" => "missing_image_library");
		}

		public function Internal_DeliverFileDownloadCallback($state, $body)
		{
			if ($state->size > 0)
			{
				if (strlen($body) > $state->size)  $body = substr($body, 0, $state->size);

				if ($body !== "")
				{
					if (!$state->cacheonly)
					{
						if ($state->body !== false)  $state->body .= $body;
						else if (!connection_aborted())  echo $body;
					}

					if ($state->cachefp !== false)  fwrite($state->cachefp, $body);

					$state->size -= strlen($body);
				}
			}
		}

		// Swiped from 'http.php'.
		private static function ProcessRateLimit($size, $start, $limit, $async)
		{
			$difftime = microtime(true) - $start;
			if ($difftime > 0.0)
			{
				if ($size / $difftime > $limit)
				{
					// Sleeping for some amount of time will equalize the rate.
					// So, solve this for $x:  $size / ($x + $difftime) = $limit
					$amount = ($size - ($limit * $difftime)) / $limit;

					if ($async)  return microtime(true) + $amount;
					else  usleep($amount);
				}
			}

			return -1.0;
		}

		// A powerful function for handling requests for file content from a web browser with local caching, cropping/scaling (images), partial data (Range requests), and rate limiting options.
		public function DeliverFile($id, $filename, $options)
		{
			// Normalize options.
			if (!isset($options["cachedir"]) || !is_string($options["cachedir"]) || !is_dir($options["cachedir"]))  $options["cachedir"] = false;
			if (!isset($options["apidir"]) || !is_string($options["apidir"]) || !is_dir($options["apidir"]))  $options["apidir"] = false;
			if (!isset($options["maxcachefilesize"]) || !is_numeric($options["maxcachefilesize"]))  $options["maxcachefilesize"] = 10000000;
			if (!isset($options["path"]) || !is_string($options["path"]))  $options["path"] = "";
			$options["path"] = preg_replace('/[^0-9]/', "", $options["path"]);
			if (!isset($options["download"]) || !is_string($options["download"]))  $options["download"] = false;
			else  $options["download"] = preg_replace('/\s+/', "-", trim(trim(preg_replace('/[^A-Za-z0-9_.\-\x80-\xff]/', " ", $options["download"]), ".")));
			if (!isset($options["crop"]) || !is_string($options["crop"]))  $options["crop"] = "";
			if (!isset($options["maxwidth"]) || !is_numeric($options["maxwidth"]))  $options["maxwidth"] = -1;
			if (!isset($options["mimeinfomap"]) || !is_array($options["mimeinfomap"]))  $options["mimeinfomap"] = self::GetDefaultMimeInfoMap();
			if (!isset($options["recvratelimit"]) || $options["recvratelimit"] < 1)  $options["recvratelimit"] = false;
			if (!isset($options["cacheonly"]) || !is_bool($options["cacheonly"]))  $options["cacheonly"] = false;

			// Process filename.
			$filename = self::SanitizeFilename($filename);
			$fileext = self::GetFileExtension($filename);
			$isimage = self::CanResizeImage($options["mimeinfomap"], $fileext);
			if ($options["download"] === "")  $options["download"] = $filename;

			// Handle final filename generation for scaled/cropped images.
			if (!$isimage || $options["maxwidth"] < 1)  $finalfilename = $filename;
			else  $finalfilename = self::GetCroppedAndScaledFilename($filename, $fileext, $options["crop"], $options["maxwidth"]);

			// Check for an already cached/local file to avoid querying the API.
			$final = ($finalfilename === $filename);
			do
			{
				$filesrc = "api";
				$filesrcname = $filename;
				$mainfp = false;
				$size = false;
				$ts = false;
				$retry = false;

				if ($options["path"] != "" && $options["cachedir"] !== false && is_file($options["cachedir"] . "/" . $options["path"] . "/" . $filename . ".dat"))
				{
					$srcdata = @json_decode(file_get_contents($options["cachedir"] . "/" . $options["path"] . "/" . $filename . ".dat"), true);
					if (is_array($srcdata) && isset($srcdata["modified"]) && is_file($options["cachedir"] . "/" . $options["path"] . "/" . $finalfilename) && filemtime($options["cachedir"] . "/" . $options["path"] . "/" . $finalfilename) < time() - 1)
					{
						$filesrc = "cachedir";
						$filesrcname = $finalfilename;
						$mainfp = $options["cachedir"] . "/" . $options["path"] . "/" . $finalfilename;
						$final = true;

						$size = filesize($mainfp);
						$ts = $srcdata["modified"];
					}
					else if (is_array($srcdata) && isset($srcdata["size"]) && isset($srcdata["modified"]) && is_file($options["cachedir"] . "/" . $options["path"] . "/" . $filename) && filesize($options["cachedir"] . "/" . $options["path"] . "/" . $filename) >= $srcdata["size"])
					{
						$filesrc = "cachedir";
						$mainfp = $options["cachedir"] . "/" . $options["path"] . "/" . $filename;
						$size = $srcdata["size"];
						$ts = $srcdata["modified"];
					}
				}
				if ($mainfp === false && $options["path"] != "" && ($options["apidir"] !== false && is_file($options["apidir"] . "/" . $options["path"] . "/" . $filename)))
				{
					$filesrc = "apidir";
					$mainfp = $options["apidir"] . "/" . $options["path"] . "/" . $filename;
					$size = filesize($mainfp);
					$ts = filemtime($mainfp);
				}

				// Retrieve information about the file.
				$asset = (is_array($id) && isset($id["files"]) ? $id : false);
				if ($asset !== false)  $id = (string)$asset["id"];
				else if ($mainfp === false)
				{
					$result = $this->GetAssets(array("id" => (string)$id));
					if (!$result["success"])
					{
						// API failure.
						if ($options["cacheonly"])  return $result;

						http_response_code(502);

						exit();
					}
					else if (!count($result["assets"]))
					{
						// Failed to retrieve the asset.
						if ($options["cacheonly"])  return array("success" => false, "error" => self::CMS_Translate("Unable to locate the requested asset."), "errorcode" => "asset_not_found");

						http_response_code(404);

						exit();
					}

					$asset = self::NormalizeAsset($result["assets"][0]);
				}

				if ($asset !== false)
				{
					if (!isset($asset["files"]) || !isset($asset["files"][$filename]))
					{
						// The asset exists but does not contain the requested file.
						if ($options["cacheonly"])  return array("success" => false, "error" => self::CMS_Translate("Asset does not contain the requested file."), "errorcode" => "asset_file_not_found");

						http_response_code(404);

						exit();
					}

					$prevpath = $options["path"];
					$options["path"] = preg_replace('/[^0-9]/', "", $asset["files"][$filename]["path"]);
					if ($prevpath !== $options["path"])
					{
						$id = $asset;

						$retry = true;
					}
					else
					{
						$size = $asset["files"][$filename]["size"];
						$ts = $asset["files"][$filename]["modified"];
					}
				}
			} while ($retry);

			// Shortcut for cache only calls to return immediately when the file is already on disk.
			if ($mainfp !== false && $options["cacheonly"] && $final)
			{
				$result = array(
					"success" => true,
					"filesrc" => $filesrc,
					"path" => $options["path"],
					"filename" => $filesrcname
				);

				return $result;
			}

			// Handle HEAD requests (i.e. just wanting to know information).
			if (!$options["cacheonly"] && strtoupper($_SERVER["REQUEST_METHOD"]) === "HEAD")
			{
				// Return HTTP 405 if the resource is a supported resizable image file that doesn't exist yet.
				if ($isimage && !$final)  http_response_code(405);
				else
				{
					if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && HTTP::GetDateTimestamp($_SERVER["HTTP_IF_MODIFIED_SINCE"]) === $ts)  http_response_code(304);

					if ($options["download"] === false && isset($options["mimeinfomap"][$fileext]))  header("Content-Type: " . $options["mimeinfomap"][$fileext]["type"]);
					else
					{
						header("Content-Type: application/octet-stream");
						header("Content-Disposition: attachment; filename=\"" . $options["download"] . "\"");
					}

					header("Accept-Ranges: bytes");

					if (http_response_code() !== 304)
					{
						header("Last-Modified: " . gmdate("D, d M Y H:i:s", $ts) . " GMT");
						header("Content-Length: " . $size);
					}
				}

				exit();
			}

			// Calculate the amount of data to transfer.  Only implement partial support for the Range header (coalesce requests into a single range).
			$start = 0;
			$origsize = $size;
			if (!$options["cacheonly"] && (!$isimage || $final) && isset($_SERVER["HTTP_RANGE"]) && $size > 0)
			{
				$min = false;
				$max = false;
				$ranges = explode(";", $_SERVER["HTTP_RANGE"]);
				foreach ($ranges as $range)
				{
					$range = explode("=", trim($range));
					if (count($range) > 1 && strtolower($range[0]) === "bytes")
					{
						$chunks = explode(",", $range[1]);
						foreach ($chunks as $chunk)
						{
							$chunk = explode("-", trim($chunk));
							if (count($chunk) == 2)
							{
								$pos = trim($chunk[0]);
								$pos2 = trim($chunk[1]);

								if ($pos === "" && $pos2 === "")
								{
									// Ignore invalid range.
								}
								else if ($pos === "")
								{
									if ($min === false || $min > $size - (int)$pos)  $min = $size - (int)$pos;
								}
								else if ($pos2 === "")
								{
									if ($min === false || $min > (int)$pos)  $min = (int)$pos;
								}
								else
								{
									if ($min === false || $min > (int)$pos)  $min = (int)$pos;
									if ($max === false || $max < (int)$pos2)  $max = (int)$pos2;
								}
							}
						}
					}
				}

				// Normalize and cap byte ranges.
				if ($min === false)  $min = 0;
				if ($max === false)  $max = $size - 1;
				if ($min < 0)  $min = 0;
				if ($min > $size - 1)  $min = $size - 1;
				if ($max < 0)  $max = 0;
				if ($max > $size - 1)  $max = $size - 1;
				if ($max < $min)  $max = $min;

				// Translate to start and size.
				$start = $min;
				$size = $max - $min + 1;
			}

			// Begin writing local cache information to disk if the file extension is a supported mime type, the file doesn't exceed the local cache file size limit, and this isn't a If-Modified-Since or byte Range request.
			$state = new stdClass();
			$state->cacheonly = false;
			$state->cachefp = false;
			if ($mainfp === false && isset($options["mimeinfomap"][$fileext]) && $options["cachedir"] !== false && $options["apidir"] === false && $asset !== false && $asset["files"][$filename]["size"] <= $options["maxcachefilesize"] && !isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && $start === 0 && $size === $origsize)
			{
				@mkdir($options["cachedir"] . "/" . $options["path"]);
				if (!file_exists($options["cachedir"] . "/" . $options["path"] . "/index.html"))  @file_put_contents($options["cachedir"] . "/" . $options["path"] . "/index.html", "");
				@file_put_contents($options["cachedir"] . "/" . $options["path"] . "/" . $filename . ".dat", json_encode($asset["files"][$filename], JSON_UNESCAPED_SLASHES));

				$state->cachefp = @fopen($options["cachedir"] . "/" . $options["path"] . "/" . $filename, "wb");
				if ($state->cachefp !== false)  $filesrc = "cachedir";

				// Turn off user abort when writing to the local cache (avoids partial writes).
				@ignore_user_abort(true);
			}

			// Carefully clear out various PHP restrictions.
			@set_time_limit(0);

			if (!$options["cacheonly"])
			{
				@ob_clean();
				if (function_exists("apache_setenv"))  @apache_setenv("no-gzip", 1);
				@ini_set("zlib.output_compression", "Off");
			}

			// Open the file for reading.
			if ($mainfp !== false)
			{
				$mainfp = @fopen($mainfp, "rb");
				if ($mainfp === false)
				{
					// Permissions failure.
					if ($options["cacheonly"])  return array("success" => false, "error" => self::CMS_Translate("Unable to open file for reading."), "errorcode" => "access_denied");

					http_response_code(550);

					exit();
				}
			}

			$state->size = $size;
			if ($isimage && !$final)
			{
				// Load the image data.
				if ($mainfp !== false)
				{
					$state->body = fread($mainfp, $origsize);

					fclose($mainfp);
				}
				else
				{
					$state->body = "";
					$result = $this->DownloadFile($id, $filename, array($this, "Internal_DeliverFileDownloadCallback"), $state);
					if (!$result["success"])
					{
						// Cleanup and retry later.
						if ($state->cachefp !== false)
						{
							fclose($state->cachefp);

							@unlink($options["cachedir"] . "/" . $options["path"] . "/" . $filename, $ts);
						}

						// API failure.
						if ($options["cacheonly"])  return array("success" => false, "error" => self::CMS_Translate("Unable to retrieve the file from the API."), "errorcode" => "api_failure");

						http_response_code(502);

						exit();
					}

					if ($state->cachefp !== false)
					{
						fclose($state->cachefp);

						@touch($options["cachedir"] . "/" . $options["path"] . "/" . $filename, $ts);

						$state->cachefp = false;
					}
				}

				$result = self::CropAndScaleImage($state->body, $options["crop"], $options["maxwidth"]);
				$state->body = false;

				if (!$result["success"])
				{
					// A supported image library is not installed/configured.
					if ($options["cacheonly"])  return $result;

					http_response_code(($result["errorcode"] === "missing_image_library" ? 501 : 500));

					exit();
				}

				$mainfp = $result["data"];

				// Write the resized file to disk (if caching).
				if ($options["cachedir"] !== false && strlen($mainfp) <= $options["maxcachefilesize"])
				{
					@mkdir($options["cachedir"] . "/" . $options["path"]);
					if (!file_exists($options["cachedir"] . "/" . $options["path"] . "/index.html"))  @file_put_contents($options["cachedir"] . "/" . $options["path"] . "/index.html", "");
					if ($asset !== false && !file_exists($options["cachedir"] . "/" . $options["path"] . "/" . $filename . ".dat"))  @file_put_contents($options["cachedir"] . "/" . $options["path"] . "/" . $filename . ".dat", json_encode($asset["files"][$filename], JSON_UNESCAPED_SLASHES));
					if (@file_put_contents($options["cachedir"] . "/" . $options["path"] . "/" . $finalfilename, $mainfp) !== false)  $filesrc = "cachedir";
					@touch($options["cachedir"] . "/" . $options["path"] . "/" . $finalfilename, $ts);

					if ($options["cacheonly"])
					{
						if ($filesrc !== "cachedir")  return array("success" => false, "error" => self::CMS_Translate("Unable to cache the file to disk."), "errorcode" => "access_denied");

						$result = array(
							"success" => true,
							"filesrc" => $filesrc,
							"path" => $options["path"],
							"filename" => $finalfilename
						);

						return $result;
					}
				}

				$origsize = strlen($mainfp);
				$size = $origsize;
			}

			// Process cache only download, cleanup, and finalize.
			if ($options["cacheonly"])
			{
				if ($mainfp !== false)
				{
					if (!is_string($mainfp))  fclose($mainfp);
				}
				else if ($state->cachefp !== false)
				{
					$state->cacheonly = true;
					$state->body = false;
					$this->DownloadFile($id, $filename, array($this, "Internal_DeliverFileDownloadCallback"), $state, $start, $size, $options["recvratelimit"]);

					fclose($state->cachefp);

					@touch($options["cachedir"] . "/" . $options["path"] . "/" . $filename, $ts);
				}

				$result = array(
					"success" => true,
					"filesrc" => $filesrc,
					"path" => $options["path"],
					"filename" => $filename
				);

				return $result;
			}

			// Deliver the final content.
			if (isset($_SERVER["HTTP_IF_MODIFIED_SINCE"]) && HTTP::GetDateTimestamp($_SERVER["HTTP_IF_MODIFIED_SINCE"]) === $ts)  http_response_code(304);
			else if ($start > 0 || $size !== $origsize)
			{
				http_response_code(206);

				header("Content-Range: bytes " . $start . "-" . $size . "/" . $origsize);
			}

			if ($options["download"] === false && isset($options["mimeinfomap"][$fileext]))  header("Content-Type: " . $options["mimeinfomap"][$fileext]["type"]);
			else
			{
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=\"" . $options["download"] . "\"");
			}

			header("Accept-Ranges: bytes");

			if (http_response_code() !== 304)
			{
				header("Last-Modified: " . gmdate("D, d M Y H:i:s", $ts) . " GMT");
				header("Content-Length: " . $size);

				if ($mainfp !== false)
				{
					// Dump the data out.
					$startts = microtime(true);
					if (is_string($mainfp))
					{
						$y = strlen($mainfp);
						if ($options["recvratelimit"] === false)  $x = 0;
						else
						{
							for ($x = 0; $x + 65536 <= $y; $x += 65536)
							{
								echo substr($mainfp, $x, 65536);

								self::ProcessRateLimit($x, $startts, $options["recvratelimit"], false);
							}
						}

						if ($x < $y)  echo substr($mainfp, $x);
					}
					else
					{
						fseek($mainfp, $start);

						$sentbytes = 0;
						while ($size >= 65536)
						{
							echo fread($mainfp, 65536);
							$size -= 65536;
							$sentbytes += 65536;

							if ($options["recvratelimit"] !== false)  self::ProcessRateLimit($sentbytes, $startts, $options["recvratelimit"], false);
						}

						if ($size)  echo fread($mainfp, $size);

						fclose($mainfp);
					}
				}
				else
				{
					$state->body = false;
					$this->DownloadFile($id, $filename, array($this, "Internal_DeliverFileDownloadCallback"), $state, $start, $size, $options["recvratelimit"]);

					if ($state->cachefp !== false)
					{
						fclose($state->cachefp);

						@touch($options["cachedir"] . "/" . $options["path"] . "/" . $filename, $ts);
					}
				}
			}

			exit();
		}

		// A basic wrapper around DeliverFile() to precache files locally without emitting HTTP status codes or headers.
		// Also determines the final file URL, if any, that should be used.
		public function PrecacheDeliverFile($id, $filename, $options)
		{
			// Normalize options.
			if (!isset($options["cachedir"]) || !is_string($options["cachedir"]) || !is_dir($options["cachedir"]))  $options["cachedir"] = false;
			if (!isset($options["cacheurl"]) || !is_string($options["cacheurl"]) || $options["cachedir"] === false)  $options["cacheurl"] = false;
			if (!isset($options["apidir"]) || !is_string($options["apidir"]) || !is_dir($options["apidir"]))  $options["apidir"] = false;
			if (!isset($options["apiurl"]) || !is_string($options["apiurl"]) || $options["apidir"] === false)  $options["apiurl"] = false;
			if (!isset($options["getfileurl"]) || !is_string($options["getfileurl"]))  $options["getfileurl"] = false;
			if (!isset($options["getfilesecret"]) || !is_string($options["getfilesecret"]))  $options["getfilesecret"] = false;
			if (!isset($options["download"]) || !is_string($options["download"]))  $options["download"] = false;

			if ($options["cacheurl"] === false && $options["apiurl"] === false)
			{
				// There's no need to call DeliverFile() if it won't actually cache anything.
				$result = array(
					"success" => true,
					"filesrc" => "api",
					"path" => (isset($options["path"]) && is_string($options["path"]) ? $options["path"] : ""),
					"filename" => $filename
				);
			}
			else
			{
				// Bypass browser file delivery.
				$options["cacheonly"] = true;

				$result = $this->DeliverFile($id, $filename, $options);
				if (!$result["success"])  return $result;
			}

			// File in cache directory with public cache URL.
			if ($result["filesrc"] === "cachedir" && $options["cacheurl"] !== false && $options["download"] === false)
			{
				if (substr($options["cacheurl"], -1) !== "/")  $options["cacheurl"] .= "/";

				return array("success" => true, "url" => $options["cacheurl"] . $result["path"] . "/" . $result["filename"]);
			}

			// File in local API directory with public files API URL.
			if ($result["filesrc"] === "apidir" && $options["apiurl"] !== false && $options["download"] === false)
			{
				if (substr($options["apiurl"], -1) !== "/")  $options["apiurl"] .= "/";

				return array("success" => true, "url" => $options["apiurl"] . $result["path"] . "/" . $result["filename"]);
			}

			// All other requests with public file URL support.
			if ($options["getfileurl"] !== false)
			{
				if (is_array($id))  $id = $id["id"];
				if (!isset($options["crop"]) || !is_string($options["crop"]))  $options["crop"] = "";
				if (!isset($options["maxwidth"]) || !is_numeric($options["maxwidth"]))  $options["maxwidth"] = -1;

				$url = $options["getfileurl"];
				$url .= (strpos($url, "?") === false ? "?" : "&");
				$url .= "id=" . urlencode($id) . "&path=" . urlencode($result["path"]) . "&filename=" . urlencode($result["filename"]);
				if ($options["download"] !== false)  $url .= "&download=" . urlencode($options["download"]);
				if ($options["crop"] != "")  $url .= "&crop=" . urlencode($options["crop"]);
				if ($options["maxwidth"] > 0)  $url .= "&maxwidth=" . (int)$options["maxwidth"];

				if ($options["getfilesecret"] !== false)  $url .= "&sig=" . self::GetFileSignature($id, $result["path"], $result["filename"], $options["crop"], ($options["maxwidth"] > 0 ? (int)$options["maxwidth"] : ""), $options["getfilesecret"]);

				return array("success" => true, "url" => $url);
			}

			return array("success" => false, "error" => self::CMS_Translate("The file exists but no URL prefix option was specified that allows the file to be retrieved."), "errorcode" => "missing_url");
		}

		// Cleans up a local file cache used by DeliverFile().  Intended to be used by a cron job/scheduled task.
		// The default cache time is 3 * 24 * 60 * 60 = 259200 (3 days).
		public static function CleanFileCache($cachedir, $keepfor = 259200)
		{
			$dir = @opendir($cachedir);
			if ($dir)
			{
				while (($file = readdir($dir)) !== false)
				{
					if ($file !== "." && $file !== ".." && is_dir($cachedir . "/" . $file) && file_exists($cachedir . "/" . $file . "/index.html") && filemtime($cachedir . "/" . $file . "/index.html") < time() - $keepfor)
					{
						$dir2 = @opendir($cachedir . "/" . $file);
						if ($dir2)
						{
							while (($file2 = readdir($dir2)) !== false)
							{
								if ($file2 !== "." && $file2 !== ".." && $file2 !== "index.html")  @unlink($cachedir . "/" . $file . "/" . $file2);
							}

							closedir($dir2);
						}

						$hasfiles = false;
						$dir2 = @opendir($cachedir . "/" . $file);
						if ($dir2)
						{
							while (($file2 = readdir($dir2)) !== false)
							{
								if ($file2 !== "." && $file2 !== ".." && $file2 !== "index.html")  $hasfiles = true;
							}

							closedir($dir2);
						}

						if (!$hasfiles)
						{
							@unlink($cachedir . "/" . $file . "/index.html");
							@rmdir($cachedir . "/" . $file);
						}
					}
				}

				closedir($dir);
			}
		}

		public function Internal_TransformStoryAssetBodyCallback($stack, &$content, $open, $tagname, &$attrs, $options)
		{
			// Handle 'div-embed' tags.
			if ($open && $tagname === "div-embed" && $options["tag_callback_opts"]->processdivembed)
			{
				unset($attrs["aria-label"]);

				// Handle 'data-html' attributes.  They contain the full inner HTML.
				if (isset($attrs["data-html"]))
				{
					$data = @json_decode($attrs["data-html"], true);
					unset($attrs["data-html"]);

					if (is_string($data))
					{
						$data = TagFilter::Run($data, $options);

						return array("state" => $data);
					}
				}
			}

			// Handle 'data-src-info'.
			if ($open && isset($attrs["data-src-info"]) && (($pos = TagFilter::GetParentPos($stack, "div-embed")) === false || $stack[$pos]["state"] === false))
			{
				$data = @json_decode($attrs["data-src-info"], true);
				if (!is_array($data))  unset($attrs["data-src-info"]);
				else
				{
					if (!$options["tag_callback_opts"]->keepsrcinfo)  unset($attrs["data-src-info"]);

					$fileext = self::GetFileExtension($data["file"]["filename"]);
					$image = ($tagname === "img" && self::CanResizeImage($options["tag_callback_opts"]->mimeinfomap, $fileext));
					unset($attrs["srcset"]);

					$found = false;
					foreach ($options["process_attrs"] as $attr => $type)
					{
						if ($type === "uri" && isset($attrs[$attr]))  $found = true;
					}

					if (!$found)  $attrs[($tagname === "a" ? "href" : "src")] = "//0.0.0.0/transform.gif";

					foreach ($options["process_attrs"] as $attr => $type)
					{
						if ($type === "uri" && isset($attrs[$attr]))
						{
							$options2 = array(
								"cachedir" => $options["tag_callback_opts"]->cachedir,
								"cacheurl" => $options["tag_callback_opts"]->cacheurl,
								"apidir" => $options["tag_callback_opts"]->apidir,
								"apiurl" => $options["tag_callback_opts"]->apiurl,
								"getfileurl" => $options["tag_callback_opts"]->getfileurl,
								"getfilesecret" => $options["tag_callback_opts"]->getfilesecret,
							);

							if (isset($data["download"]))  $options2["download"] = $data["file"]["origfilename"];

							if ($image)
							{
								$options2["maxwidth"] = (int)(isset($attrs["data-max-width"]) && $options["tag_callback_opts"]->usedatamaxwidth ? $attrs["data-max-width"] : $options["tag_callback_opts"]->maxwidth);
								if (isset($data["crop"]))  $options2["crop"] = $data["crop"];
							}

							$result = $this->PrecacheDeliverFile(((string)$data["id"] !== (string)$options["tag_callback_opts"]->asset["id"] ? $data["id"] : $options["tag_callback_opts"]->asset), $data["file"]["filename"], $options2);
							if ($result["success"])  $attrs[$attr] = $result["url"];
							else  $attrs["data-transform-error"] = $result["error"] . " (" . $result["errorcode"] . ")";

							if (isset($options["tag_callback_opts"]->em))  $options["tag_callback_opts"]->em->Fire($options["tag_callback_opts"]->emfire . "_datasrc", array($tagname, &$attrs, $data));
						}
					}
				}
			}

			if (!$open)
			{
				// Transform 'div-embed' tags with 'data-html' attributes.
				if ($tagname === "/div-embed" && $options["tag_callback_opts"]->processdivembed)
				{
					$pos = TagFilter::GetParentPos($stack, "div-embed");
					if ($stack[$pos]["state"] !== false)  $content = $stack[$pos]["state"];
				}

				// Trim content and remove empty block-level tags.  Reduces storage requirements and tends to look nicer.
				if (isset($options["tag_callback_opts"]->trimcontent[substr($tagname, 1)]))  $content = trim(str_replace("&nbsp;", " ", $content));

				if (isset($options["tag_callback_opts"]->removeempty[substr($tagname, 1)]) && trim($content) === "")  return array("keep_tag" => false);
			}

			$result = array();
			if (isset($options["tag_callback_opts"]->em))
			{
				$results = $options["tag_callback_opts"]->em->Fire($options["tag_callback_opts"]->emfire, array($stack, &$content, $open, $tagname, &$attrs, $options));

				foreach ($results as $result2)
				{
					$result = array_replace($result, $result2);
				}
			}

			return $result;
		}

		// Transforms story asset body content for viewing.
		public function TransformStoryAssetBody($asset, $options = array(), $lang = false)
		{
			if (!class_exists("TagFilter", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/tag_filter.php";

			// Normalize options.
			if (!isset($options["trimcontent"]))  $options["trimcontent"] = "p,h1,h2,h3,h4,h5,h6,blockquote,pre,li";
			if (!isset($options["removeempty"]))  $options["removeempty"] = "p,h1,h2,h3,h4,h5,h6,blockquote,pre,ol,ul,li,table,thead,tbody,tr";
			if (!isset($options["processdivembed"]))  $options["processdivembed"] = true;
			if (!isset($options["keepsrcinfo"]))  $options["keepsrcinfo"] = false;
			if (!isset($options["usedatamaxwidth"]))  $options["usedatamaxwidth"] = true;
			if (!isset($options["maxwidth"]) || !is_numeric($options["maxwidth"]))  $options["maxwidth"] = -1;
			if (!isset($options["mimeinfomap"]) || !is_array($options["mimeinfomap"]))  $options["mimeinfomap"] = self::GetDefaultMimeInfoMap();
			if (!isset($options["cachedir"]))  $options["cachedir"] = false;
			if (!isset($options["cacheurl"]))  $options["cacheurl"] = false;
			if (!isset($options["apidir"]))  $options["apidir"] = false;
			if (!isset($options["apiurl"]))  $options["apiurl"] = false;
			if (!isset($options["getfileurl"]))  $options["getfileurl"] = false;
			if (!isset($options["getfilesecret"]))  $options["getfilesecret"] = false;
			if (!isset($options["siteurl"]))  $options["siteurl"] = false;
			if (!isset($options["emfire"]))  $options["emfire"] = "";

			$options["asset"] = $asset;

			$result = TagFilter::NormalizeHTMLPurifyOptions(array("allowed_tags" => $options["trimcontent"], "remove_empty" => $options["removeempty"]));
			$options["trimcontent"] = $result["allowed_tags"];
			$options["removeempty"] = $result["remove_empty"];

			$htmloptions = TagFilter::GetHTMLOptions();
			$htmloptions["tag_callback"] = array($this, "Internal_TransformStoryAssetBodyCallback");
			$htmloptions["tag_callback_opts"] = (object)$options;

			if ($lang !== false)
			{
				$asset["langinfo"][$lang]["body"] = TagFilter::Run($asset["langinfo"][$lang]["body"], $htmloptions);

				if ($options["siteurl"] !== false)  $asset["langinfo"][$lang]["body"] = str_replace("site://", htmlspecialchars($options["siteurl"] . "/"), $asset["langinfo"][$lang]["body"]);
			}
			else
			{
				foreach ($asset["langinfo"] as $lang => $info)
				{
					$info["body"] = TagFilter::Run($info["body"], $htmloptions);

					if ($options["siteurl"] !== false)  $info["body"] = str_replace("site://", htmlspecialchars($options["siteurl"] . "/"), $info["body"]);

					$asset["langinfo"][$lang]["body"] = $info["body"];
				}
			}

			return $asset;
		}

		// Generates summary information for an asset for a list view.
		public static function GenerateStoryAssetSummary($asset, $options = array(), $lang = false)
		{
			if (!class_exists("TagFilter", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/tag_filter.php";

			// Normalize options.
			if (!isset($options["paragraphs"]) || !is_numeric($options["paragraphs"]))  $options["paragraphs"] = 2;
			if (!isset($options["html"]) || !is_bool($options["html"]))  $options["html"] = true;
			if (!isset($options["keepbody"]) || !is_bool($options["keepbody"]))  $options["keepbody"] = false;

			$htmloptions = TagFilter::GetHTMLOptions();

			foreach ($asset["langinfo"] as $lang2 => $info)
			{
				if ($lang !== false && $lang !== $lang2)  continue;

				$html = TagFilter::Explode($info["body"], $htmloptions);
				$root = $html->Get();

				// Extract the first image found.
				$info["img"] = false;
				$rows = $root->Find('img[src]');
				foreach ($rows as $row)
				{
					if (strncmp($row->src, "//0.0.0.0/", 10))
					{
						$info["img"] = $row->GetOuterHTML();
						$html->Remove($row->ID());

						break;
					}
				}

				// Extract the desired number of paragraphs.
				$info["summary"] = array();
				$rows = $root->Find('p');
				foreach ($rows as $row)
				{
					$info["summary"][] = ($options["html"] ? $row->GetOuterHTML() : $row->GetPlainText());

					if (count($info["summary"]) >= $options["paragraphs"])  break;
				}

				if (!$options["keepbody"])  unset($info["body"]);

				$asset["langinfo"][$lang2] = $info;
			}

			return $asset;
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

		// Output a heartbeat AJAX function.  Intended for keep-alive of an active refresh PHP session.
		// Default request rate is every 5 minutes (5 * 60 = 300).
		public static function OutputHeartbeat($every = 300)
		{
			if (isset($_COOKIE["bb_valid"]))
			{
?>
<script type="text/javascript">
setInterval(function() {
	var r = new XMLHttpRequest();
	r.open('POST', '', true);
	r.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	r.send('bb_heartbeat=1');
}, <?php echo (int)$every; ?> * 1000);
</script>
<?php
			}
		}

		// Get an edit URL.
		public static function GetAdminEditURL($adminurl, &$asset, $lang = false)
		{
			return $adminurl . "?wantaction=addeditasset&id=" . (int)$asset["id"] . "&type=" . urlencode($asset["type"]) . ($lang !== false ? "&lang=" . urlencode($lang) : "");
		}

		// Outputs a link to an edit URL as a button in a corner of the page.
		public static function OutputPageAdminEditButton($adminurl, &$asset, $lang = false, $vertpos = "bottom", $horzpos = "right")
		{
?>
<a href="<?php echo htmlspecialchars(self::GetAdminEditURL($adminurl, $asset, $lang))?>" style="position: fixed; <?php echo $vertpos; ?>: 1.0em; <?php echo $horzpos; ?>: 1.0em; display: inline-block; font-size: 1.5em; padding: 0.4em 0.6em; border-radius: 2.0em; background-color: #222222; color: #FFFFFF; opacity: 0.9; z-index: 30000; text-decoration: none; border: 3px solid #CCCCCC;">&#x270E;</a>
<?php
		}

		// Sanitizes assets and stores them on disk.
		public static function CacheAssets($contentdir, $type, $key, $assets)
		{
			foreach ($assets as &$asset)
			{
				unset($asset["lastupdatedby"]);
				unset($asset["lastip"]);
				unset($asset["protected"]);

				foreach ($asset["langinfo"] as $lang => $info)  unset($asset["langinfo"][$lang]["protected"]);
			}

			$key2 = md5(json_encode($key, JSON_UNESCAPED_SLASHES));

			$basedir = $contentdir . "/" . $type;
			@mkdir($basedir);
			if (!file_exists($basedir . "/index.html"))  @file_put_contents($basedir . "/index.html", "");

			$basedir .= "/" . substr($key2, 0, 2);
			@mkdir($basedir);
			if (!file_exists($basedir . "/index.html"))  @file_put_contents($basedir . "/index.html", "");

			file_put_contents($basedir . "/assets_" . $key2 . ".dat", json_encode(array("key" => $key, "assets" => $assets), JSON_UNESCAPED_SLASHES));
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

		protected static function CMS_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		protected function RunAPI($method, $apipath, $options = array(), $expected = 200, $encodejson = true, $decodebody = true)
		{
			if ($this->host === false || $this->apikey === false)  return array("success" => false, "error" => self::CMS_Translate("Missing host or API key."), "errorcode" => "no_access_info");

			$url = $this->host;

			// Handle Remoted API connections.
			if ($this->fp === false && RemotedAPI::IsRemoted($this->host))
			{
				$result = RemotedAPI::Connect($this->host);
				if (!$result["success"])  return $result;

				$this->fp = $result["fp"];
				$url = $result["url"];
			}

			$options2 = array(
				"method" => $method,
				"headers" => array(
					"Connection" => "keep-alive",
					"X-APIKey" => $this->apikey
				)
			);

			if ($this->fp !== false)  $options2["fp"] = $this->fp;

			if ($this->apisecret !== false)
			{
				$data = array();

				foreach ($apipath as $key => $val)
				{
					if (substr($key, -2) !== "[]")  $data[(string)$key] = (string)$val;
					else
					{
						$key = substr($key, 0, -2);
						if (!isset($data[$key]) || !is_array($data[$key]))  $data[$key] = array();
						foreach ($val as $val2)  $data[$key][] = (string)$val2;
					}
				}
			}

			if ($encodejson && $method !== "GET")
			{
				$options2["headers"]["Content-Type"] = "application/json";
				$options2["body"] = json_encode($options, JSON_UNESCAPED_SLASHES);

				if ($this->apisecret !== false)  $data = array_merge($data, $options);
			}
			else
			{
				$options2 = array_merge($options2, $options);

				if ($this->apisecret !== false && isset($options["postvars"]))
				{
					foreach ($options["postvars"] as $key => $val)
					{
						if (substr($key, -2) !== "[]")  $data[(string)$key] = (string)$val;
						else
						{
							$key = substr($key, 0, -2);
							if (!isset($data[$key]) || !is_array($data[$key]))  $data[$key] = array();
							foreach ($val as $val2)  $data[$key][] = (string)$val2;
						}
					}
				}
			}

			// Generate signature.
			if ($this->apisecret !== false)  $options2["headers"]["X-Signature"] = base64_encode(hash_hmac("sha256", json_encode($data, JSON_UNESCAPED_SLASHES), $this->apisecret, true));

			// Calculate the API path.
			$apipath2 = "";
			foreach ($apipath as $key => $val)
			{
				if (!is_array($val))  $val = array($val);
				foreach ($val as $val2)  $apipath2 .= ($apipath2 === "" ? "?" : "&") . urlencode($key) . "=" . urlencode($val2);
			}

			$result = $this->web->Process($url . $apipath2, $options2);

			if (!$result["success"] && $this->fp !== false)
			{
				// If the server terminated the connection, then re-establish the connection and rerun the request.
				@fclose($this->fp);
				$this->fp = false;

				return $this->RunAPI($method, $apipath, $options, $expected, $encodejson, $decodebody);
			}

			if (!$result["success"])  return $result;

			if (isset($result["fp"]) && is_resource($result["fp"]))  $this->fp = $result["fp"];
			else  $this->fp = false;

			if ($result["response"]["code"] != $expected)  return array("success" => false, "error" => self::CMS_Translate("Expected a %d response from the Barebones CMS API.  Received '%s'.", $expected, $result["response"]["line"]), "errorcode" => "unexpected_barebones_cms_api_response", "info" => $result);

			if ($decodebody)  return json_decode($result["body"], true);

			return $result;
		}
	}
?>