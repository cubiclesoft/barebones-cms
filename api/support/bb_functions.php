<?php
	// Barebones CMS API support functions.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	function BB_GetSupportedDatabases()
	{
		$result = array(
			"sqlite" => array("production" => true, "login" => false, "replication" => false, "default_dsn" => "@PATH@/sqlite_@RANDOM@.db"),
			"mysql" => array("production" => true, "login" => true, "replication" => true, "default_dsn" => "host=127.0.0.1"),
			"pgsql" => array("production" => true, "login" => true, "replication" => true, "default_dsn" => "host=localhost"),
			"oci" => array("production" => false, "login" => true, "replication" => true, "default_dsn" => "dbname=//localhost/ORCL")
		);

		return $result;
	}

	function BB_SanitizeStr($str)
	{
		return preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z0-9]/', " ", strtolower(trim($str)))));
	}

	function BB_ExtractLanguage($str, $default)
	{
		$langs = explode(",", $str);
		foreach ($langs as $lang)
		{
			$lang = trim($lang);
			$pos = strpos($lang, ";");
			if ($pos !== false)  $lang = substr($lang, 0, $pos);
			$lang = BB_SanitizeStr($lang);
			if ($lang !== "")  return $lang;
		}

		return $default;
	}

	function BB_CloudStorageServerFeedsInit($config)
	{
		if ($config["css_feeds_host"] == "")  return array("success" => true);

		if (!class_exists("HTTP", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/http.php";
		if (!class_exists("CloudStorageServerFeeds", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/sdk_cloud_storage_server_feeds.php";

		$css = new CloudStorageServerFeeds();

		$url = HTTP::ExtractURL($config["css_feeds_host"]);
		if ($url["scheme"] === "https")
		{
			@mkdir($config["rootpath"] . "/cache", 0775);

			$result = $css->InitSSLCache($config["css_feeds_host"], $config["rootpath"] . "/cache/css_ca.pem", $config["rootpath"] . "/cache/css_cert.pem");
			if (!$result["success"])  return $result;

			$css->SetAccessInfo($config["css_feeds_host"], $config["css_feeds_apikey"], $config["rootpath"] . "/cache/css_ca.pem", file_get_contents($config["rootpath"] . "/cache/css_cert.pem"));
		}
		else
		{
			$css->SetAccessInfo($config["css_feeds_host"], $config["css_feeds_apikey"], "", "");
		}

		return $css;
	}

	function BB_CloudStorageServerFeedsNotify($css, $feedname, $type, $id, $data, $ts)
	{
		if (is_array($css) && isset($css["success"]) && $css["success"])  return array("success" => true);

		if ($type === "update" && $ts > time())  $type = "insert";

		$result = $css->Notify($feedname, $type, (string)$id, $data, $ts);

		return $result;
	}

	// Constant-time string comparison.  Ported from CubicleSoft C++ code.
	function BB_CTstrcmp($secret, $userinput)
	{
		$sx = 0;
		$sy = strlen($secret);
		$uy = strlen($userinput);
		$result = $sy - $uy;
		for ($ux = 0; $ux < $uy; $ux++)
		{
			$result |= ord($userinput{$ux}) ^ ord($secret{$sx});
			$sx = ($sx + 1) % $sy;
		}

		return $result;
	}

	function BB_ConnectDB($config, $write, $full = false)
	{
		require_once $config["rootpath"] . "/support/csdb/db_" . $config["db_select"] . ($full ? "" : "_lite") . ".php";

		$dbclassname = "CSDB_" . $config["db_select"] . ($full ? "" : "_lite");

		try
		{
			if ($write && $config["db_master_dsn"] != "")  $db = new $dbclassname($config["db_select"] . ":" . $config["db_master_dsn"], ($config["db_login"] ? $config["db_master_user"] : false), ($config["db_login"] ? $config["db_master_pass"] : false));
			else
			{
				$db = new $dbclassname($config["db_select"] . ":" . $config["db_dsn"], ($config["db_login"] ? $config["db_user"] : false), ($config["db_login"] ? $config["db_pass"] : false));
				if ($config["db_master_dsn"] != "")  $db->SetMaster($config["db_select"] . ":" . $config["db_master_dsn"], ($config["db_login"] ? $config["db_master_user"] : false), ($config["db_login"] ? $config["db_master_pass"] : false));
			}

			$db->Query("USE", $config["db_name"]);

			return array("success" => true, "db" => $db);
		}
		catch (Exception $e)
		{
			return array("success" => false, "error" => "Database connection failed.", "errorcode" => "db_connect_failed", "info" => $e->getMessage());
		}
	}

	// A reusable class to allow for re-implementing the API as a dedicated or remoted API service.
	class BB_API_Helper
	{
		public $config, $write, $db, $api_db_assets, $api_db_tags, $api_db_revisions;

		public function __construct($config, $write)
		{
			$this->config = $config;
			$this->write = $write;
			$this->db = false;
		}

		public function ConnectDB()
		{
			$result = BB_ConnectDB($this->config, $this->write);
			if (!$result["success"])  return $result;

			$this->db = $result["db"];

			$dbprefix = $this->config["db_table_prefix"];
			$this->api_db_assets = $dbprefix . "assets";
			$this->api_db_tags = $dbprefix . "tags";
			$this->api_db_revisions = $dbprefix . "revisions";

			return $result;
		}

		public function CreateRevision($row)
		{
			try
			{
				// Retrieve asset revision information.
				$revrow = $this->db->GetRow("SELECT", array(
					"MIN(revnum) AS minrevnum, MAX(revnum) AS maxrevnum, COUNT(*) AS numrevs",
					"FROM" => "?",
					"WHERE" => "aid = ?"
				), $this->api_db_revisions, $row->id);

				if (!$revrow)  $revrow = (object)array("minrevnum" => 0, "maxrevnum" => 0, "numrevs" => 0);

				$this->db->Query("INSERT", array($this->api_db_revisions, array(
					"aid" => $row->id,
					"revnum" => $revrow->maxrevnum + 1,
					"created" => time(),
					"info" => $row->info
				)));

				$revrow->numrevs++;

				while ($revrow->numrevs > $this->config["max_revisions"])
				{
					$this->db->Query("DELETE", array($this->api_db_revisions, "WHERE" => "aid = ? AND revnum = ?"), $row->id, $revrow->minrevnum);

					$revrow->minrevnum++;
					$revrow->numrevs--;
				}

				return array("success" => true);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to create a revision.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}
		}

		public static function ForwardFileSeek($fp, $size)
		{
			if (PHP_INT_SIZE < 8)
			{
				while ($size > 1)
				{
					$amount = ($size > 1073741824 ? 1073741824 : $size);
					if (@fseek($fp, $amount, SEEK_CUR) === -1)  break;

					if (@fgetc($fp) === false)
					{
						@fseek($fp, -$amount, SEEK_CUR);
						$size -= (int)($size / 2);
					}
					else
					{
						@fseek($fp, -1, SEEK_CUR);
						$size -= $amount;
					}
				}

				if ($size > 1)
				{
					// Unfortunately, fseek() failed for some reason.  Going to have to do this the old-fashioned way.
					do
					{
						$amount = ($size > 10485760 ? 10485760 : $size);
						$data = @fread($fp, $amount);
						if ($data === false)  $data = "";
						$size -= strlen($data);
					} while ($data !== "" && $size > 0);
				}
				else
				{
					while ($size > 0 && @fgetc($fp) !== false)  $size--;
				}
			}
			else
			{
				@fseek($fp, $size, SEEK_CUR);
			}
		}

		public function GetAssets($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");

			if (!isset($data["limit"]) || !is_numeric($data["limit"]))  $data["limit"] = 25;
			else  $data["limit"] = (int)$data["limit"];

			if (!isset($data["tag"]))  $data["tag"] = array();
			else if (!is_array($data["tag"]))  $data["tag"] = array($data["tag"]);

			if (!isset($data["id"]))  $data["id"] = array();
			else if (!is_array($data["id"]))  $data["id"] = explode(",", (string)$data["id"]);

			if (!isset($data["uuid"]))  $data["uuid"] = array();
			else if (!is_array($data["uuid"]))  $data["uuid"] = explode(",", (string)$data["uuid"]);

			if (!isset($data["type"]))  $data["type"] = array();
			else if (!is_array($data["type"]))  $data["type"] = explode(",", (string)$data["type"]);

			if (!isset($data["q"]))  $data["q"] = array();
			else if (!is_array($data["q"]))  $data["q"] = array($data["q"]);

			if (!isset($data["qe"]))  $data["qe"] = array();
			else if (!is_array($data["qe"]))  $data["qe"] = array($data["qe"]);

			if (isset($data["start"]) && is_numeric($data["start"]))  $data["start"] = (int)$data["start"];
			else  unset($data["start"]);

			if (isset($data["end"]) && is_numeric($data["end"]))  $data["end"] = (int)$data["end"];
			else  unset($data["end"]);

			$sqlwhere = array();
			$sqlvars = array($this->api_db_tags);

			// Tag query.  Fast reduction.
			$negatefound = false;
			foreach ($data["tag"] as $num => $tag)
			{
				$tag = str_replace(array("_", "%"), "-", (string)$tag);

				$partial = false;
				$negate = false;
				while (substr($tag, 0, 1) === "~" || substr($tag, 0, 1) === "!")
				{
					if (substr($tag, 0, 1) === "~")  $partial = true;
					else  $negate = true;

					$tag = substr($tag, 1);
				}

				if ($negate)
				{
					$data["tag"][$num] = array("partial" => $partial, "tag" => $tag);

					$negatefound = true;
				}
				else if (!$partial || strlen($tag) > 2)
				{
					$sqlwhere[] = ($partial ? "tag LIKE ?" : "tag = ?");
					$sqlvars[] = ($partial ? $tag . "%" : $tag);

					unset($data["tag"][$num]);
				}
			}

			if (count($sqlwhere))
			{
				try
				{
					$result = $this->db->Query("SELECT", array(
						"DISTINCT aid",
						"FROM" => "?",
						"WHERE" => "(" . implode(" OR ", $sqlwhere) . ")" . ($this->write ? "" : " AND publish > 0") . (isset($data["start"]) ? " AND publish >= " . (int)$data["start"] : "") . (isset($data["end"]) ? " AND publish <= " . (int)$data["end"] : ""),
						"ORDER BY" => "publish DESC",
						"LIMIT" => (int)$data["limit"] * ($negatefound ? 4 : 3)
					), $sqlvars);
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Unable to retrieve matching tags.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
				}

				// Appending ID's might seem strange but allows for specific assets to always be returned PLUS assets that match other rules.
				$found = false;
				while ($row = $result->NextRow())
				{
					$data["id"][] = $row->aid;
					$found = true;
				}

				if (!$found)  $data["id"][] = "0";
			}

			// Main query.
			$sqlwhere = array();
			$sqlvars = array($this->api_db_assets);

			if (!$this->write)  $sqlwhere[] = "publish > 0";

			if (isset($data["start"]))
			{
				$sqlwhere[] = "publish >= ?";
				$sqlvars[] = (int)$data["start"];
			}

			if (isset($data["end"]))
			{
				$sqlwhere[] = "publish <= ?";
				$sqlvars[] = (int)$data["end"];
			}

			// Asset IDs.
			$ids = array();
			$ids2 = array();
			foreach ($data["id"] as $num => $id)
			{
				$negate = (substr($id, 0, 1) === "!");

				$id = preg_replace('/[^0-9]/', "", $id);
				if ($id != "")
				{
					if ($negate)  $ids2["_" . $id] = true;
					else  $ids[$id] = $id;
				}
			}

			if (count($ids))  $sqlwhere[] = "id IN (" . implode(",", $ids) . ")";

			// Asset UUIDs.
			$uuids = array();
			$uuids2 = array();
			foreach ($data["uuid"] as $num => $id)
			{
				$negate = (substr($id, 0, 1) === "!");

				$id = BB_SanitizeStr(ltrim($id, "!"));
				if ($id != "")
				{
					if ($negate)  $uuids2[$id] = true;
					else
					{
						$id = $this->db->Quote($id);
						$uuids[$id] = $id;
					}
				}
			}

			if (count($uuids))  $sqlwhere[] = "uuid IN (" . implode(",", $uuids) . ")";

			// Asset types.
			$types = array();
			$types2 = array();
			foreach ($data["type"] as $num => $type)
			{
				$negate = (substr($type, 0, 1) === "!");

				$type = BB_SanitizeStr(ltrim($type, "!"));
				if ($type != "")
				{
					if ($negate)  $types2[$type] = true;
					else
					{
						$type = $this->db->Quote($type);
						$types[$type] = $type;
					}
				}
			}

			if (count($types))  $sqlwhere[] = "type IN (" . implode(",", $types) . ")";

			// Query phrase match.
			foreach ($data["q"] as $num => $words)
			{
				$negate = (substr($words, 0, 1) === "!");

				$words = ltrim($words, "!");
				if ($words != "")
				{
					$words = explode(" ", preg_replace('/\s+/', " ", substr(json_encode($words, JSON_UNESCAPED_SLASHES), 1, -1)));

					foreach ($words as $word)
					{
						$sqlwhere[] = ($negate ? "info NOT LIKE ?" : "info LIKE ?");
						$sqlvars[] = "%" . $word . "%";
					}
				}
			}

			// Query exact match.
			foreach ($data["qe"] as $num => $words)
			{
				$negate = (substr($words, 0, 1) === "!");

				$words = ltrim($words, "!");
				if ($words != "")
				{
					$sqlwhere[] = ($negate ? "info NOT LIKE ?" : "info LIKE ?");
					$sqlvars[] = "%" . $words . "%";
				}
			}

			$sql = array(
				"*",
				"FROM" => "?"
			);

			if (count($sqlwhere))  $sql["WHERE"] = implode(" AND ", $sqlwhere);

			if (isset($data["order"]) && $data["order"] == "lastupdated")  $sql["ORDER BY"] = "lastupdated DESC";
			else if (isset($data["order"]) && $data["order"] == "unpublish")  $sql["ORDER BY"] = "unpublish DESC";
			else  $sql["ORDER BY"] = "publish DESC";

			$sql["LIMIT"] = (int)$data["limit"] * 3;

			try
			{
				$result = $this->db->Query("SELECT", $sql, $sqlvars);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve search results.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$bulk = (isset($data["bulk"]) && (int)$data["bulk"]);

			$result2 = array(
				"success" => true,
				"assets" => array()
			);

			$numrows = 0;
			while ($row = $result->NextRow())
			{
				$asset = @json_decode($row->info, true);
				if (!is_array($asset))  continue;

				if (isset($ids2["_" . $row->id]) || isset($uuids2[$asset["uuid"]]) || isset($types2[$asset["type"]]))  continue;

				// Skip unpublished assets.
				if (!$this->write && $asset["unpublish"] && $asset["unpublish"] <= $ts)  continue;

				// Skip assets with negated tags (precalculated earlier).
				$skip = false;
				foreach ($data["tag"] as $taginfo)
				{
					foreach ($info["tags"] as $tag)
					{
						if ((!$taginfo["partial"] && $tag === $taginfo["tag"]) || ($taginfo["partial"] && substr($tag, 0, strlen($taginfo["tag"])) === $taginfo["tag"]))
						{
							$skip = true;

							break;
						}
					}

					if ($skip)  break;
				}
				if ($skip)  continue;

				$asset["id"] = $row->id;

				if ($bulk)
				{
					$result2 = array(
						"success" => true,
						"asset" => $asset
					);

					call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));
				}
				else
				{
					$result2["assets"][] = $asset;
				}

				$numrows++;
				if ($numrows >= $data["limit"])  break;
			}

			if (!$bulk)  call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));

			return $result2;
		}

		public function GetTags($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");

			if (isset($data["tag"]) && !is_string($data["tag"]))  return array("success" => false, "error" => "Invalid asset tag.", "errorcode" => "invalid_asset_tag");

			if (isset($data["start"]) && is_numeric($data["start"]))  $data["start"] = (int)$data["start"];
			else  unset($data["start"]);

			if (isset($data["end"]) && is_numeric($data["end"]))  $data["end"] = (int)$data["end"];
			else  unset($data["end"]);

			$sqlwhere = array();
			$sqlvars = array($this->api_db_tags);

			if (!$this->write)  $sqlwhere[] = "publish > 0";

			if (isset($data["start"]))
			{
				$sqlwhere[] = "publish >= ?";
				$sqlvars[] = (int)$data["start"];
			}

			if (isset($data["end"]))
			{
				$sqlwhere[] = "publish <= ?";
				$sqlvars[] = (int)$data["end"];
			}

			if (isset($data["tag"]))
			{
				$tag = str_replace(array("_", "%"), "-", $data["tag"]);

				$partial = false;
				while (substr($tag, 0, 1) === "~")
				{
					$partial = true;

					$tag = substr($tag, 1);
				}

				if (!$partial || strlen($tag) > 2)
				{
					$sqlwhere[] = ($partial ? "tag LIKE ?" : "tag = ?");
					$sqlvars[] = ($partial ? $tag . "%" : $tag);
				}
			}

			$sql = array(
				"tag, COUNT(*) as numtags",
				"FROM" => "?"
			);

			if (count($sqlwhere))  $sql["WHERE"] = implode(" AND ", $sqlwhere);

			$sql["GROUP BY"] = "tag";

			if (isset($data["order"]) && $data["order"] === "tag")  $sql["ORDER BY"] = "tag";
			else if (isset($data["order"]) && $data["order"] === "publish")  $sql["ORDER BY"] = "publish DESC";
			else  $sql["ORDER BY"] = "numtags DESC";

			if (isset($data["limit"]) && is_numeric($data["limit"]))
			{
				$limit = array();
				if (isset($data["offset"]) && is_numeric($data["offset"]))  $limit[] = (int)$data["offset"];
				$limit[] = (int)$data["limit"];

				$sql["LIMIT"] = $limit;
			}

			try
			{
				$result = $this->db->Query("SELECT", $sql, $sqlvars);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve tags.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$bulk = (isset($data["bulk"]) && (int)$data["bulk"]);

			$result2 = array(
				"success" => true,
				"tags" => array()
			);

			while ($row = $result->NextRow())
			{
				if ($bulk)
				{
					$result2 = array(
						"success" => true,
						"tag" => (array)$row
					);

					call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));
				}
				else
				{
					$result2["tags"][$row->tag] = (int)$row->numtags;
				}
			}

			if (!$bulk)  call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));

			return $result2;
		}

		public function GetRevisions($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");

			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");

			$sqlwhere = array("aid = ?");
			$sqlvars = array($this->api_db_revisions, $data["id"]);

			if (isset($data["revision"]) && is_numeric($data["revision"]))
			{
				$sqlwhere[] = "revnum = ?";
				$sqlvars[] = $data["revision"];
			}

			$sql = array(
				"*",
				"FROM" => "?",
				"WHERE" => implode(" AND ", $sqlwhere),
				"ORDER BY" => "revnum"
			);

			if (isset($data["limit"]) && is_numeric($data["limit"]))
			{
				$limit = array();
				if (isset($data["offset"]) && is_numeric($data["offset"]))  $limit[] = (int)$data["offset"];
				$limit[] = (int)$data["limit"];

				$sql["LIMIT"] = $limit;
			}

			try
			{
				$result = $this->db->Query("SELECT", $sql, $sqlvars);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve revisions list.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$bulk = (isset($data["bulk"]) && (int)$data["bulk"]);

			$result2 = array(
				"success" => true,
				"revisions" => array()
			);

			while ($row = $result->NextRow())
			{
				$row->info = @json_decode($row->info, true);
				$row->info["id"] = $row->aid;

				$row->revision = $row->revnum;
				unset($row->revnum);

				if ($bulk)
				{
					$result2 = array(
						"success" => true,
						"revision" => (array)$row
					);

					call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));
				}
				else
				{
					$result2["revisions"][] = (array)$row;
				}
			}

			if (!$bulk)  call_user_func_array($outputcallback, array($result2, $bulk, &$outputcallbackopts));

			return $result2;
		}

		// A file may be able to be downloaded directly without using the API for performance gains.  However, the API may be more reliable depending on usage.
		public function DownloadFile($data)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");

			// Write binary data.
			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");
			if (!isset($data["filename"]) || !is_string($data["filename"]))  return array("success" => false, "error" => "Missing or invalid 'filename'.", "errorcode" => "missing_or_invalid_filename");

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info) || !isset($info["files"]))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);
			if (!isset($info["files"][$data["filename"]]))  return array("success" => false, "error" => "Asset does not contain a started upload.", "errorcode" => "upload_not_started", "info" => $data["filename"]);

			$fileinfo = $info["files"][$data["filename"]];

			$filename = $this->config["files_path"] . "/" . $fileinfo["path"] . "/" . $fileinfo["filename"];
			$fp = @fopen($filename, "rb");
			if ($fp === false)  return array("success" => false, "error" => "Unable to open the specified file for reading.", "errorcode" => "open_failed", "info" => $filename);

			if (!isset($data["start"]))  $data["start"] = 0;
			if (is_numeric($data["start"]) && $data["start"] > 0)  fseek($fp, (int)$data["start"]);
			$size = (isset($data["size"]) && is_numeric($data["size"]) && $data["size"] < $fileinfo["size"] - $data["start"] ? (int)$data["size"] : $fileinfo["size"] - $data["start"]);

			// Carefully clear out various PHP restrictions.
			@set_time_limit(0);
			@ob_clean();
			if (function_exists("apache_setenv"))  @apache_setenv("no-gzip", 1);
			@ini_set("zlib.output_compression", "Off");

			header("Content-Type: application/octet-stream");
			header("Content-Length: " . $size);

			while ($size >= 1048576)
			{
				echo fread($fp, 1048576);
				$size -= 1048576;
			}

			if ($size)  echo fread($fp, $size);

			return array("success" => true);
		}

		public static function GetMaxUploadFileSize()
		{
			$maxpostsize = floor(self::ConvertUserStrToBytes(ini_get("post_max_size")) * 3 / 4);
			if ($maxpostsize > 4096)  $maxpostsize -= 4096;

			$maxuploadsize = self::ConvertUserStrToBytes(ini_get("upload_max_filesize"));
			if ($maxuploadsize < 1)  $maxuploadsize = ($maxpostsize < 1 ? -1 : $maxpostsize);

			return ($maxpostsize < 1 ? $maxuploadsize : min($maxpostsize, $maxuploadsize));
		}

		// Copy included for self-containment.
		public static function ConvertUserStrToBytes($str)
		{
			$str = trim($str);
			$num = (double)$str;
			if (strtoupper(substr($str, -1)) == "B")  $str = substr($str, 0, -1);
			switch (strtoupper(substr($str, -1)))
			{
				case "P":  $num *= 1024;
				case "T":  $num *= 1024;
				case "G":  $num *= 1024;
				case "M":  $num *= 1024;
				case "K":  $num *= 1024;
			}

			return $num;
		}

		public function GetAccessInfo($outputcallback, &$outputcallbackopts)
		{
			$result = array(
				"success" => true,
				"write" => $this->write,
				"files_url" => $this->config["files_url"],
				"file_exts" => strtolower($this->config["file_exts"]),
				"default_lang" => $this->config["default_lang"],
				"max_revisions" => $this->config["max_revisions"],
				"max_chunk_size" => self::GetMaxUploadFileSize(),
				"chunked_uploads" => true
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		// Delete an uploaded file.
		public function DeleteUpload($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");
			if (!isset($data["filename"]) || !is_string($data["filename"]))  return array("success" => false, "error" => "Missing or invalid 'filename'.", "errorcode" => "missing_or_invalid_filename");

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info) || !isset($info["files"]))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);
			if (!isset($info["files"][$data["filename"]]))  return array("success" => false, "error" => "Asset does not contain the specified upload.", "errorcode" => "upload_missing", "info" => $data["filename"]);

			// Copy the asset to revisions even if reverting doesn't make much sense.
			if ($this->config["max_revisions"] > 0 && (!isset($data["makerevision"]) || (int)$data["makerevision"]))  $this->CreateRevision($row);

			// Remove the file.
			$fileinfo = $info["files"][$data["filename"]];
			@unlink($this->config["files_path"] . "/" . $fileinfo["path"] . "/" . $fileinfo["filename"]);
			@rmdir($this->config["files_path"] . "/" . $fileinfo["path"]);

			// Update the asset.
			unset($info["files"][$data["filename"]]);
			unset($info["filesinfo"][$data["filename"]]);
			$info["lastupdated"] = time();
			$info["lastupdatedby"] = (isset($data["lastupdatedby"]) && is_string($data["lastupdatedby"]) ? $data["lastupdatedby"] : "");
			$info["lastip"] = $_SERVER["REMOTE_ADDR"];

			try
			{
				$this->db->Query("UPDATE", array($this->api_db_assets, array(
					"lastupdated" => $info["lastupdated"],
					"info" => json_encode($info, JSON_UNESCAPED_SLASHES),
				), "WHERE" => "id = ?"), $row->id);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to update the asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$result = array(
				"success" => true,
				"id" => $row->id,
				"filename" => $data["filename"]
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		// Delete an asset but not its revision history.
		public function DeleteAsset($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);

			// Copy the asset to revisions.
			if ($this->config["max_revisions"] > 0 && (!isset($data["makerevision"]) || (int)$data["makerevision"]))  $this->CreateRevision($row);

			try
			{
				$this->db->Query("DELETE", array($this->api_db_tags, "WHERE" => "aid = ?"), $row->id);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to remove existing asset tags.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			try
			{
				$this->db->Query("DELETE", array($this->api_db_assets, "WHERE" => "id = ?"), $row->id);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to remove existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			// Send Cloud Storage Server /feeds notifications.
			$css = BB_CloudStorageServerFeedsInit($this->config);

			$prevasset = $info;
			$info["unpublish"] = time() - 1;

			$data2 = array(
				"asset" => $info,
				"prevasset" => $prevasset
			);

			BB_CloudStorageServerFeedsNotify($css, $this->config["css_feeds_name"], "delete", $row->id, $data2, $info["unpublish"]);

			$result = array(
				"success" => true,
				"id" => $row->id
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		public function StoreAsset($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			// Validate standard asset options.
			if (!isset($data["asset"]) || !is_array($data["asset"]))  return array("success" => false, "error" => "Missing or invalid 'asset'.  Expected an array.", "errorcode" => "missing_or_invalid_asset");
			if (!isset($data["asset"]["type"]))  return array("success" => false, "error" => "Missing asset 'type'.", "errorcode" => "missing_asset_type");
			if (!is_string($data["asset"]["type"]))  return array("success" => false, "error" => "Invalid asset 'type'.  Expected a non-empty string.", "errorcode" => "invalid_asset_type");
			$data["asset"]["type"] = BB_SanitizeStr(ltrim($data["asset"]["type"], "!"));

			if ($data["asset"]["type"] == "")  return array("success" => false, "error" => "Invalid asset 'type'.  Expected a non-empty string.", "errorcode" => "invalid_asset_type");
			if (!isset($data["asset"]["langinfo"]) || !is_array($data["asset"]["langinfo"]))  return array("success" => false, "error" => "Invalid 'langinfo'.  Expected an array.", "errorcode" => "invalid_langinfo");

			// Rearrange languages.  Default first, everything else in alpha order.
			$langinfo = array();
			if (isset($data["asset"]["langinfo"][$this->config["default_lang"]]))
			{
				$langinfo[$this->config["default_lang"]] = $data["asset"]["langinfo"][$this->config["default_lang"]];

				unset($data["asset"]["langinfo"][$this->config["default_lang"]]);
			}

			ksort($data["asset"]["langinfo"]);
			if (count($langinfo))  $data["asset"]["langinfo"] = $langinfo + $data["asset"]["langinfo"];

			foreach ($data["asset"]["langinfo"] as $lang => $info)
			{
				$lang2 = BB_SanitizeStr($lang);
				if ($lang2 !== $lang || $lang === "")  return array("success" => false, "error" => "Invalid IANA language code found in 'langinfo'.  Expected a hyphenated, lowercase, alphanumeric string.", "errorcode" => "invalid_iana_language_code");

				if (!is_array($info))  unset($data["asset"]["langinfo"][$lang]);
				else
				{
					if (!isset($info["title"]) || !is_string($info["title"]))  $info["title"] = "";
					if (!isset($info["body"]) || !is_string($info["body"]))  $info["body"] = "";

					$data["asset"]["langinfo"][$lang] = $info;
				}
			}

			// Calculate the title for the associated database column and the UUID.
			if (isset($data["asset"]["langinfo"][$this->config["default_lang"]]) && $data["asset"]["langinfo"][$this->config["default_lang"]]["title"] != "")  $title = $data["asset"]["langinfo"][$this->config["default_lang"]]["title"];
			else
			{
				$title = false;
				foreach ($data["asset"]["langinfo"] as $lang => $info)
				{
					if ($info["title"] != "" && ($title === false || substr($this->config["default_lang"], 0, strlen($lang) + 1) === $lang . "-"))  $title = $info["title"];
				}
			}
			if ($title === false)  return array("success" => false, "error" => "Missing asset 'title'.", "errorcode" => "missing_asset_title");

			if (!isset($data["asset"]["publish"]))  $data["asset"]["publish"] = 0;
			if (!is_int($data["asset"]["publish"]) || $data["asset"]["publish"] < 0)  return array("success" => false, "error" => "Invalid asset 'publish' timestamp.  Expected non-negative integer.", "errorcode" => "invalid_asset_publish");
			if (!isset($data["asset"]["unpublish"]))  $data["asset"]["unpublish"] = 0;
			if (!is_int($data["asset"]["unpublish"]) || $data["asset"]["unpublish"] < 0)  return array("success" => false, "error" => "Invalid asset 'unpublish' timestamp.  Expected non-negative integer.", "errorcode" => "invalid_asset_unpublish");

			// Clean up the asset.
			$data["asset"]["langorder"] = array_keys($data["asset"]["langinfo"]);

			unset($data["asset"]["files"]);

			$tags = array();
			if (!isset($data["asset"]["tags"]) || !is_array($data["asset"]["tags"]))  $data["asset"]["tags"] = array();
			foreach ($data["asset"]["tags"] as $tag)  $tags[ltrim(str_replace(array("_", "%"), "-", (string)$tag), "~!")] = true;
			$data["asset"]["tags"] = array_keys($tags);

			$data["asset"]["lastupdated"] = time();
			if (isset($data["asset"]["lastupdatedby"]) && !is_string($data["asset"]["lastupdatedby"]))  $data["asset"]["lastupdatedby"] = "";
			$data["asset"]["lastip"] = $_SERVER["REMOTE_ADDR"];

			// Check for an existing asset.
			$row = false;
			if (isset($data["asset"]["id"]))
			{
				try
				{
					$row = $this->db->GetRow("SELECT", array(
						"*",
						"FROM" => "?",
						"WHERE" => "id = ?"
					), $this->api_db_assets, $data["asset"]["id"]);

					if ($row === false)
					{
						// Check revision history for an orphaned asset.
						$revrow = $this->db->GetRow("SELECT", array(
							"*",
							"FROM" => "?",
							"WHERE" => "aid = ?",
							"ORDER BY" => "revnum DESC",
							"LIMIT" => 1
						), $this->api_db_revisions, $data["asset"]["id"]);

						if ($revrow)
						{
							$info = @json_decode($revrow->info, true);
							if (is_array($info))
							{
								// Recreate the asset.
								$this->db->Query("INSERT", array($this->api_db_assets, array(
									"id" => $revrow->aid,
									"publish" => $info["publish"],
									"unpublish" => $info["unpublish"],
									"lastupdated" => $info["lastupdated"],
									"type" => $info["type"],
									"uuid" => $info["uuid"],
									"title" => $title,
									"info" => $revrow->info,
								)));

								// Retrieve the asset.
								$row = $this->db->GetRow("SELECT", array(
									"*",
									"FROM" => "?",
									"WHERE" => "id = ?"
								), $this->api_db_assets, $data["asset"]["id"]);
							}
						}
					}
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
				}

				if ($row)
				{
					if ($row->type !== $data["asset"]["type"])  return array("success" => false, "error" => "Asset type mismatch.  An existing asset for the specified asset ID has a different type.", "errorcode" => "asset_type_mismatch", "info" => $row->type);

					$prevasset = @json_decode($row->info, true);
					$data["asset"]["files"] = (is_array($prevasset) ? $prevasset["files"] : array());

					// Copy the asset to revisions.
					if ($this->config["max_revisions"] > 0 && (!isset($data["makerevision"]) || (int)$data["makerevision"]))  $this->CreateRevision($row);
				}
				else
				{
					unset($data["asset"]["id"]);
					$data["asset"]["files"] = array();
				}
			}

			// Normalize file information/metadata.
			if (!isset($data["asset"]["files"]))  $data["asset"]["files"] = array();
			if (!isset($data["asset"]["filesinfo"]))  $data["asset"]["filesinfo"] = array();
			$info = array();
			foreach ($data["asset"]["files"] as $name => $info2)
			{
				$info[$name] = (isset($data["asset"]["filesinfo"][$name]) && is_array($data["asset"]["filesinfo"][$name]) ? $data["asset"]["filesinfo"][$name] : array());
			}
			$data["asset"]["filesinfo"] = $info;

			if (!isset($data["asset"]["uuid"]) || $data["asset"]["uuid"] == "")
			{
				// Calculate a new UUID.
				$uuid = (string)(int)(($data["asset"]["publish"] > 0 ? $data["asset"]["publish"] : time()) / 86400);
				$words = explode(" ", preg_replace('/\s+/', " ", trim($title)));
				foreach ($words as $word)
				{
					$word2 = "";
					$y = strlen($word);
					for ($x = 0; $x < $y; $x++)
					{
						if (ord($word{$x}) > 126)  $word2 .= bin2hex($word{$x});
						else  $word2 .= $word{$x};
					}
					$word = "-" . BB_SanitizeStr($word2);

					if (strlen($uuid) + strlen($word) > 50)  break;

					$uuid .= $word;
				}

				// Adjust the UUID until it is unique.
				$baseuuid = $uuid;
				$num = 1;
				do
				{
					if ($num > 1)  $uuid = $baseuuid . "-" . $num;
					$num++;

					try
					{
						$found = (bool)(int)$this->db->GetOne("SELECT", array(
							"COUNT(*)",
							"FROM" => "?",
							"WHERE" => "uuid = ? AND id <> ?",
						), $this->api_db_assets, $uuid, ($row !== false ? $row->id : 0));
					}
					catch (Exception $e)
					{
						return array("success" => false, "error" => "Unable to generate a valid UUID.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
					}
				} while ($found);

				$data["asset"]["uuid"] = $uuid;
			}

			$data["asset"]["uuid"] = BB_SanitizeStr(ltrim($data["asset"]["uuid"], "!"));

			if ($row === false)
			{
				try
				{
					$this->db->Query("INSERT", array($this->api_db_assets, array(
						"publish" => $data["asset"]["publish"],
						"unpublish" => $data["asset"]["unpublish"],
						"lastupdated" => $data["asset"]["lastupdated"],
						"type" => $data["asset"]["type"],
						"uuid" => $data["asset"]["uuid"],
						"title" => $title,
						"info" => json_encode($data["asset"], JSON_UNESCAPED_SLASHES),
					), "AUTO INCREMENT" => "id"));

					$id = $this->db->GetInsertID();
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Unable to create a new asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
				}
			}
			else
			{
				try
				{
					$this->db->Query("UPDATE", array($this->api_db_assets, array(
						"publish" => $data["asset"]["publish"],
						"unpublish" => $data["asset"]["unpublish"],
						"lastupdated" => $data["asset"]["lastupdated"],
						"uuid" => $data["asset"]["uuid"],
						"title" => $title,
						"info" => json_encode($data["asset"], JSON_UNESCAPED_SLASHES),
					), "WHERE" => "id = ?"), $row->id);
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Unable to update the asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
				}

				$id = $row->id;

				// Remove existing tags.
				try
				{
					$this->db->Query("DELETE", array($this->api_db_tags, "WHERE" => "aid = ?"), $row->id);
				}
				catch (Exception $e)
				{
					return array("success" => false, "error" => "Unable to remove existing asset tags.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
				}
			}

			// Generate the tags for the asset.
			// The duplicate publish field is required for high-performance tag lookups.
			try
			{
				foreach ($data["asset"]["tags"] as $tag)
				{
					$this->db->Query("INSERT", array($this->api_db_tags, array(
						"aid" => $id,
						"tag" => $tag,
						"publish" => $data["asset"]["publish"],
					)));
				}
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to insert asset tags.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			// Send Cloud Storage Server /feeds notifications.
			if ($data["asset"]["publish"] > 0 || $data["asset"]["unpublish"] > 0 || ($row !== false && ($prevasset["publish"] != $data["asset"]["publish"] || $prevasset["unpublish"] != $data["asset"]["unpublish"])))
			{
				$css = BB_CloudStorageServerFeedsInit($this->config);

				$data2 = array(
					"asset" => $data["asset"],
					"prevasset" => ($row ? $prevasset : false)
				);

				if ($row !== false && ($prevasset["publish"] != $data["asset"]["publish"] || $prevasset["unpublish"] != $data["asset"]["unpublish"]))  BB_CloudStorageServerFeedsNotify($css, $this->config["css_feeds_name"], "delete", $id, $data2, time() - 1);
				if ($data["asset"]["publish"] > 0 && $data["asset"]["unpublish"] > time())  BB_CloudStorageServerFeedsNotify($css, $this->config["css_feeds_name"], ($data["asset"]["publish"] > time() ? "insert" : "update"), $id, $data2, $data["asset"]["publish"]);
				if ($data["asset"]["unpublish"] > 0)  BB_CloudStorageServerFeedsNotify($css, $this->config["css_feeds_name"], "delete", $id, $data2, $data["asset"]["unpublish"]);
			}

			$result = array(
				"success" => true,
				"id" => (string)$id,
				"uuid" => $data["asset"]["uuid"]
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		public function StartUpload($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			// Start a new upload.
			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");
			if (!isset($data["filename"]) || !is_string($data["filename"]))  return array("success" => false, "error" => "Missing or invalid 'filename'.", "errorcode" => "missing_or_invalid_filename");

			// Process the input filename.
			if (!class_exists("UTF8", false))  require_once $this->config["rootpath"] . "/support/utf8.php";
			$filename = UTF8::MakeValid(preg_replace('/\s+/', "-", trim(trim(preg_replace('/[^A-Za-z0-9_.\-\x80-\xff]/', " ", $data["filename"]), "."))));
			$pos = strrpos($filename, ".");
			if ($pos === false)  return array("success" => false, "error" => "The 'filename' does not contain a filename extension.", "errorcode" => "invalid_filename");
			$fileext = strtolower(substr($filename, $pos));
			$filename2 = str_replace(".", "_", substr($filename, 0, $pos));
			$fileexts = explode(";", strtolower($this->config["file_exts"]));
			$found = false;
			foreach ($fileexts as $ext)
			{
				if (strpos($ext, "/") !== false)  continue;

				if (trim($ext) === $fileext)  $found = true;
			}
			if (!$found)  return array("success" => false, "error" => "The 'filename' does not contain an allowed filename extension.", "errorcode" => "invalid_filename_extension");

			// Convert UTF-8 filename to a safe name.
			$filename = "";
			$y = strlen($filename2);
			for ($x = 0; $x < $y; $x++)
			{
				$filename .= (ord($filename2{$x}) >= 0x80 ? "~" . bin2hex($filename2{$x}) : $filename2{$x});
			}

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info) || !isset($info["files"]))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);

			// Copy the asset to revisions.
			if ($this->config["max_revisions"] > 0 && (!isset($data["makerevision"]) || (int)$data["makerevision"]))  $this->CreateRevision($row);

			// Create the file storage path and make the filename unique.
			$path = (string)(int)(($info["publish"] > 0 ? $info["publish"] : time()) / 86400);
			@mkdir($this->config["files_path"] . "/" . $path);
			@file_put_contents($this->config["files_path"] . "/" . $path . "/index.html", "");
			$basefilename = (strlen($filename) < 6 ? $path . "-" : "") . $filename;
			$num = 0;
			do
			{
				$num++;
				if ($num > 1)  $filename = $basefilename . "-" . $num;
			} while (file_exists($this->config["files_path"] . "/" . $path . "/" . $filename . $fileext));

			// Update the asset.
			$filename .= $fileext;
			if (@file_put_contents($this->config["files_path"] . "/" . $path . "/" . $filename, "") === false)  return array("success" => false, "error" => "Unable to create the file.", "errorcode" => "create_file_failed", "info" => $this->config["files_path"] . "/" . $path . "/" . $filename);
			$info["files"][$filename] = array(
				"path" => $path,
				"filename" => $filename,
				"origfilename" => $filename2 . ($num > 1 ? "-" . $num : "") . $fileext,
				"size" => 0,
				"created" => filectime($this->config["files_path"] . "/" . $path . "/" . $filename),
				"modified" => filemtime($this->config["files_path"] . "/" . $path . "/" . $filename)
			);
			$info["filesinfo"][$filename] = (isset($data["fileinfo"]) && is_array($data["fileinfo"]) ? $data["fileinfo"] : array());

			$info["lastupdated"] = time();
			$info["lastupdatedby"] = (isset($data["lastupdatedby"]) && is_string($data["lastupdatedby"]) ? $data["lastupdatedby"] : "");
			$info["lastip"] = $_SERVER["REMOTE_ADDR"];

			try
			{
				$this->db->Query("UPDATE", array($this->api_db_assets, array(
					"lastupdated" => $info["lastupdated"],
					"info" => json_encode($info, JSON_UNESCAPED_SLASHES),
				), "WHERE" => "id = ?"), $row->id);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to update the asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$result = array(
				"success" => true,
				"id" => $row->id,
				"filename" => $filename,
				"file" => $info["files"][$filename],
				"fileinfo" => $info["filesinfo"][$filename]
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		public function ProcessUpload($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			// Write binary data.
			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");
			if (!isset($data["filename"]) || !is_string($data["filename"]))  return array("success" => false, "error" => "Missing or invalid 'filename'.", "errorcode" => "missing_or_invalid_filename");
			if (!isset($data["start"]) || !is_numeric($data["start"]) || $data["start"] < 0)  return array("success" => false, "error" => "Missing or invalid 'start'.  Expected a non-negative integer.", "errorcode" => "missing_or_invalid_start");
			if (!isset($_FILES["file"]) || !isset($_FILES["file"]["error"]) || !isset($_FILES["file"]["tmp_name"]) || !is_string($_FILES["file"]["tmp_name"]))  return array("success" => false, "error" => "Missing or invalid 'file'.  Expected a file upload.", "errorcode" => "missing_file_data");
			if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"]))  return array("success" => false, "error" => "Missing or invalid 'file'.  File upload data is missing or was corrupt.", "errorcode" => "missing_file_data");

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info) || !isset($info["files"]))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);
			if (!isset($info["files"][$data["filename"]]))  return array("success" => false, "error" => "Asset does not contain a started upload.", "errorcode" => "upload_not_started", "info" => $data["filename"]);

			$fileinfo = $info["files"][$data["filename"]];
			if ($fileinfo["size"] < $data["start"])  return array("success" => false, "error" => "The 'start' position is located past the end of the file.", "errorcode" => "invalid_start", "info" => $fileinfo["size"]);

			$filename = $this->config["files_path"] . "/" . $fileinfo["path"] . "/" . $fileinfo["filename"];
			if ($data["start"] == $fileinfo["size"])  $fp = @fopen($filename, "ab");
			else
			{
				$fp = @fopen($filename, "r+b");
				if ($fp !== false)  self::ForwardFileSeek($fp, $data["start"]);
			}

			if ($fp === false)  return array("success" => false, "error" => "Unable to open the specified file for writing.", "errorcode" => "open_failed", "info" => $filename);

			$fp2 = @fopen($_FILES["file"]["tmp_name"], "rb");
			if ($fp2 === false)
			{
				fclose($fp);

				return array("success" => false, "error" => "Unable to open the uploaded file for reading.", "errorcode" => "open_failed");
			}

			do
			{
				$data2 = @fread($fp2, 10485760);
				if ($data2 === false)  $data2 = "";
				@fwrite($fp, $data2);
				$fileinfo["size"] += strlen($data2);
			} while ($data2 !== "");

			fclose($fp2);
			fclose($fp);

			// Update the asset.
			$fileinfo["modified"] = filemtime($this->config["files_path"] . "/" . $fileinfo["path"] . "/" . $fileinfo["filename"]);
			$info["files"][$data["filename"]] = $fileinfo;
			$info["lastupdated"] = time();

			try
			{
				$this->db->Query("UPDATE", array($this->api_db_assets, array(
					"lastupdated" => $info["lastupdated"],
					"info" => json_encode($info, JSON_UNESCAPED_SLASHES),
				), "WHERE" => "id = ?"), $row->id);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to update the asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			$result = array(
				"success" => true,
				"id" => $row->id,
				"filename" => $data["filename"],
				"file" => $fileinfo
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		public function UploadDone($data, $outputcallback, &$outputcallbackopts)
		{
			if ($this->db === false)  return array("success" => false, "error" => "Not connected to the database.", "errorcode" => "call_connect_db");
			if (!$this->write)  return array("success" => false, "error" => "Database not opened for write access.", "access_denied");

			// Finalize the upload.  Useful for override classes.
			if (!isset($data["id"]) || !is_string($data["id"]))  return array("success" => false, "error" => "Missing or invalid asset ID.", "errorcode" => "missing_or_invalid_asset_id");
			if (!isset($data["filename"]) || !is_string($data["filename"]))  return array("success" => false, "error" => "Missing or invalid 'filename'.", "errorcode" => "missing_or_invalid_filename");

			try
			{
				$row = $this->db->GetRow("SELECT", array(
					"*",
					"FROM" => "?",
					"WHERE" => "id = ?"
				), $this->api_db_assets, $data["id"]);
			}
			catch (Exception $e)
			{
				return array("success" => false, "error" => "Unable to retrieve the existing asset.  A database error occurred.", "errorcode" => "db_error", "info" => $e->getMessage());
			}

			if ($row === false)  return array("success" => false, "error" => "No asset found.", "errorcode" => "asset_not_found", "info" => $data["id"]);

			$info = @json_decode($row->info, true);
			if (!is_array($info) || !isset($info["files"]))  return array("success" => false, "error" => "Asset found but is corrupt.", "errorcode" => "asset_corrupted", "info" => $row->id);
			if (!isset($info["files"][$data["filename"]]))  return array("success" => false, "error" => "Asset does not contain a started upload.", "errorcode" => "upload_not_started", "info" => $data["filename"]);

			$fileinfo = $info["files"][$data["filename"]];

			$result = array(
				"success" => true,
				"id" => $row->id,
				"filename" => $data["filename"],
				"file" => $info["files"][$data["filename"]],
				"fileinfo" => $info["filesinfo"][$data["filename"]]
			);

			call_user_func_array($outputcallback, array($result, false, &$outputcallbackopts));

			return $result;
		}

		public function UnknownAPI($reqmethod, $data, $outputcallback, &$outputcallbackopts)
		{
			return array("success" => false, "error" => "Unrecognized or invalid 'api'.", "errorcode" => "unrecognized_api");
		}
	}
?>