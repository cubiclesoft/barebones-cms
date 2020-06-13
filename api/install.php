<?php
	// Barebones CMS API Installer
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (file_exists("config.php") && (!isset($_REQUEST["action"]) || $_REQUEST["action"] != "done"))  exit();

	require_once "support/str_basics.php";
	require_once "support/flex_forms.php";
	require_once "support/bb_functions.php";
	require_once "support/random.php";
	require_once "support/csdb/db.php";

	Str::ProcessAllInput();

	session_start();
	if (!isset($_SESSION["bb_cms_api_install"]))  $_SESSION["bb_cms_api_install"] = array();
	if (!isset($_SESSION["bb_cms_api_install"]["secret"]))
	{
		$rng = new CSPRNG();
		$_SESSION["bb_cms_api_install"]["secret"] = $rng->GetBytes(64);
	}

	$ff = new FlexForms();
	$ff->SetSecretKey($_SESSION["bb_cms_api_install"]["secret"]);
	$ff->CheckSecurityToken("action");

	function OutputHeader($title)
	{
		global $ff;

		header("Content-type: text/html; charset=UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?=htmlspecialchars($title)?> | Barebones CMS API Installer</title>
<link rel="stylesheet" href="support/install.css" type="text/css" media="all" />
<?php
		$ff->OutputJQuery();
?>
<script type="text/javascript">
setInterval(function() {
	$.post('<?=$ff->GetRequestURLBase()?>', {
		'action': 'heartbeat',
		'sec_t': '<?=$ff->CreateSecurityToken("heartbeat")?>'
	});
}, 5 * 60 * 1000);
</script>
</head>
<body>
<div id="headerwrap"><div id="header">Barebones CMS API Installer</div></div>
<div id="contentwrap"><div id="content">
<h1><?=htmlspecialchars($title)?></h1>
<?php
	}

	function OutputFooter()
	{
?>
</div></div>
<div id="footerwrap"><div id="footer">
&copy <?=date("Y")?> CubicleSoft.  All Rights Reserved.
</div></div>
</body>
</html>
<?php
	}

	$errors = array();
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "heartbeat")
	{
		echo "OK";
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "done")
	{
		if (!isset($_SESSION["bb_cms_api_installed"]) || !$_SESSION["bb_cms_api_installed"])  exit();

		OutputHeader("Installation Finished");

		$ff->OutputMessage("success", "The installation completed successfully.");

?>
<p>The Barebones CMS API was successfully installed.  You now have a high-performance server-side API for hosting content for other resources to leverage.</p>

<p>What's next?  Secure the root Barebones CMS API directory and 'config.php' so that the web server can't write to it.  Then install the Barebones CMS admin interface to begin editing content or use the Barebones CMS SDK to push and pull content from other sources.</p>

<p>API key information is stored in the generated 'config.php' file.</p>

<p>If you opted to use Cloud Storage Server /feeds, the installer generated a working 'future_' data prefill PHP file (e.g. 'future_cms_api.php') that you will need to move into the 'user_init/feeds/' directory of your Cloud Storage Server instance.</p>
<?php

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "step4")
	{
		if (!isset($_SESSION["bb_cms_api_install"]["files_path"]))  $_SESSION["bb_cms_api_install"]["files_path"] = $_SERVER["DOCUMENT_ROOT"] . "/files";
		if (!isset($_SESSION["bb_cms_api_install"]["files_url"]))  $_SESSION["bb_cms_api_install"]["files_url"] = "/files";
		if (!isset($_SESSION["bb_cms_api_install"]["file_exts"]))  $_SESSION["bb_cms_api_install"]["file_exts"] = ".jpg; .jpeg; .gif; .png";
		if (!isset($_SESSION["bb_cms_api_install"]["default_lang"]))  $_SESSION["bb_cms_api_install"]["default_lang"] = (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? BB_ExtractLanguage($_SERVER["HTTP_ACCEPT_LANGUAGE"], "en") : "en");
		if (!isset($_SESSION["bb_cms_api_install"]["max_revisions"]))  $_SESSION["bb_cms_api_install"]["max_revisions"] = "50";
		if (!isset($_SESSION["bb_cms_api_install"]["css_feeds_host"]))  $_SESSION["bb_cms_api_install"]["css_feeds_host"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["css_feeds_apikey"]))  $_SESSION["bb_cms_api_install"]["css_feeds_apikey"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["css_feeds_name"]))  $_SESSION["bb_cms_api_install"]["css_feeds_name"] = "cms_api";

		$message = "";
		if (isset($_REQUEST["files_path"]))
		{
			// Test settings.
			if ($_REQUEST["file_exts"] != "")
			{
				$html = "<html><head><title>Test</title></head><body>Test</body></html>";
				if (!is_dir($_REQUEST["files_path"]))  $errors["files_path"] = "The specified directory does not exist.  Please create the directory and make sure it is writeable by the web server (e.g. chown www-data, chmod 775).";
				else if (!@file_put_contents($_REQUEST["files_path"] . "/test.html", $html))  $errors["files_path"] = "The specified directory exists but is not writeable.  Please make sure the directory is writeable by the web server (e.g. chown www-data, chmod 775).";
				else if ($_REQUEST["files_url"] != "")
				{
					require_once "support/web_browser.php";

					$web = new WebBrowser();

					$url = HTTP::ConvertRelativeToAbsoluteURL($ff->GetRequestHost("http"), $_REQUEST["files_url"] . "/test.html");
					$result = $web->Process($url);
					if (!$result["success"])  $errors["files_url"] = "Unable to connect to '" . htmlspecialchars($url) . "'.  " . $result["error"] . " (" . $result["errorcode"] . ")";
					else if ($result["response"]["code"] != 200)  $errors["files_url"] = "Expected file was not found at '" . htmlspecialchars($url) . "'.  Expected 200 OK response.  Received '" . htmlspecialchars($result["response"]["line"]) . "'.";
					else if ($result["body"] != $html)  $errors["files_url"] = "Expected file '" . htmlspecialchars($url) . "' did not contain expected data.";
					else  $message .= "Storage path and URL look okay.<br><b>Test URL was '" . htmlspecialchars($url) . "'.</b><br>";

					@unlink($_REQUEST["files_path"] . "/test.html");
				}
				else
				{
					@unlink($_REQUEST["files_path"] . "/test.html");

					$message .= "Storage path looks okay.<br>";
				}
			}

			if ((int)$_REQUEST["max_revisions"] < 0)  $errors["max_revisions"] = "Please enter a non-negative integer.";

			$_REQUEST["default_lang"] = BB_ExtractLanguage($_REQUEST["default_lang"], "");
			if ($_REQUEST["default_lang"] == "")  $errors["default_lang"] = "Please enter a valid IANA language code.";

			if ($_REQUEST["css_feeds_host"] != "")
			{
				$_REQUEST["css_feeds_name"] = preg_replace('/\s+/', "_", preg_replace('/[^A-Za-z0-9_\-]/', " ", $_REQUEST["css_feeds_name"]));

				if ($_REQUEST["css_feeds_apikey"] == "")  $errors["css_feeds_apikey"] = "Please fill in this field.";
				else if ($_REQUEST["css_feeds_name"] == "")  $errors["css_feeds_name"] = "Please specify a valid feed name.";
				else
				{
					$config = array(
						"rootpath" => str_replace("\\", "/", dirname(__FILE__)),
						"css_feeds_host" => $_REQUEST["css_feeds_host"],
						"css_feeds_apikey" => $_REQUEST["css_feeds_apikey"],
						"css_feeds_name" => $_REQUEST["css_feeds_name"]
					);

					$css = BB_CloudStorageServerFeedsInit($config);
					$result = BB_CloudStorageServerFeedsNotify($css, $config["css_feeds_name"], "insert", "1", array("subject" => "Installer test"), time());
					if (!$result["success"])  $errors["css_feeds_host"] = "Unable to send test notification.  " . $result["error"] . " (" . $result["errorcode"] . ")";
					else  $message .= "Cloud Storage Server host and API key look okay.<br><b>Connected to Cloud Storage Server and sent a test notification.</b><br>";
				}
			}

			if (count($errors))  $errors["msg"] = "Please correct the errors below and try again.";
			else if (isset($_REQUEST["next"]))
			{
				$_SESSION["bb_cms_api_install"]["files_path"] = $_REQUEST["files_path"];
				$_SESSION["bb_cms_api_install"]["files_url"] = $_REQUEST["files_url"];
				$_SESSION["bb_cms_api_install"]["file_exts"] = $_REQUEST["file_exts"];
				$_SESSION["bb_cms_api_install"]["default_lang"] = $_REQUEST["default_lang"];
				$_SESSION["bb_cms_api_install"]["max_revisions"] = $_REQUEST["max_revisions"];
				$_SESSION["bb_cms_api_install"]["css_feeds_host"] = $_REQUEST["css_feeds_host"];
				$_SESSION["bb_cms_api_install"]["css_feeds_apikey"] = $_REQUEST["css_feeds_apikey"];
				$_SESSION["bb_cms_api_install"]["css_feeds_name"] = $_REQUEST["css_feeds_name"];

				$rng = new CSPRNG(true);

				$databases = BB_GetSupportedDatabases();
				$database = $_SESSION["bb_cms_api_install"]["db_select"];
				if (!isset($databases[$database]))  $errors["msg"] = "Invalid database selected.  Go back and try again.";

				// Generate the configuration.
				if (!count($errors))
				{
					$config = array(
						"rootpath" => str_replace("\\", "/", dirname(__FILE__)),
						"rooturl" => dirname($ff->GetFullRequestURLBase()) . "/",
						"read_apikey" => $rng->GenerateString(64),
						"readwrite_apikey" => $rng->GenerateString(64),
						"readwrite_secret" => $rng->GenerateString(64),
						"db_select" => $_SESSION["bb_cms_api_install"]["db_select"],
						"db_dsn" => $_SESSION["bb_cms_api_install"]["db_dsn"],
						"db_login" => $databases[$database]["login"],
						"db_user" => $_SESSION["bb_cms_api_install"]["db_user"],
						"db_pass" => $_SESSION["bb_cms_api_install"]["db_pass"],
						"db_name" => $_SESSION["bb_cms_api_install"]["db_name"],
						"db_table_prefix" => $_SESSION["bb_cms_api_install"]["db_table_prefix"],
						"db_master_dsn" => $_SESSION["bb_cms_api_install"]["db_master_dsn"],
						"db_master_user" => $_SESSION["bb_cms_api_install"]["db_master_user"],
						"db_master_pass" => $_SESSION["bb_cms_api_install"]["db_master_pass"],
						"files_path" => $_SESSION["bb_cms_api_install"]["files_path"],
						"files_url" => $_SESSION["bb_cms_api_install"]["files_url"],
						"file_exts" => $_SESSION["bb_cms_api_install"]["file_exts"],
						"default_lang" => $_SESSION["bb_cms_api_install"]["default_lang"],
						"max_revisions" => (int)$_SESSION["bb_cms_api_install"]["max_revisions"],
						"css_feeds_host" => $_SESSION["bb_cms_api_install"]["css_feeds_host"],
						"css_feeds_apikey" => $_SESSION["bb_cms_api_install"]["css_feeds_apikey"],
						"css_feeds_name" => $_SESSION["bb_cms_api_install"]["css_feeds_name"],
					);
				}

				// Database setup.
				if (!count($errors))
				{
					require_once "support/csdb/db_" . $config["db_select"] . ".php";

					$dbclassname = "CSDB_" . $config["db_select"];

					try
					{
						$db = new $dbclassname($config["db_select"] . ":" . $config["db_dsn"], ($config["db_login"] ? $config["db_user"] : false), ($config["db_login"] ? $config["db_pass"] : false));
						if ($config["db_master_dsn"] != "")  $db->SetMaster($config["db_select"] . ":" . $config["db_master_dsn"], ($config["db_login"] ? $config["db_master_user"] : false), ($config["db_login"] ? $config["db_master_pass"] : false));
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Database connection failed.  " . htmlspecialchars($e->getMessage());
					}
				}

				if (!count($errors))
				{
					try
					{
						$db->GetDisplayName();
						$db->GetVersion();
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Database connection succeeded but unable to get server version.  " . htmlspecialchars($e->getMessage());
					}
				}

				// Create/Use the database.
				if (!count($errors))
				{
					try
					{
						$db->Query("USE", $config["db_name"]);
					}
					catch (Exception $e)
					{
						try
						{
							$db->Query("CREATE DATABASE", array($config["db_name"], "CHARACTER SET" => "utf8", "COLLATE" => "utf8_general_ci"));
							$db->Query("USE", $config["db_name"]);
						}
						catch (Exception $e)
						{
							$errors["msg"] = "Unable to create/use database '" . htmlspecialchars($config["db_name"]) . "'.  " . htmlspecialchars($e->getMessage());
						}
					}
				}

				// Create database tables.
				if (!count($errors))
				{
					$dbprefix = $config["db_table_prefix"];
					$api_db_assets = $dbprefix . "assets";
					$api_db_tags = $dbprefix . "tags";
					$api_db_revisions = $dbprefix . "revisions";
					try
					{
						$assetsfound = $db->TableExists($api_db_assets);
						$tagsfound = $db->TableExists($api_db_tags);
						$revisionsfound = $db->TableExists($api_db_revisions);
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Unable to determine the existence of a database table.  " . htmlspecialchars($e->getMessage());
					}
				}

				if (!count($errors) && !$assetsfound)
				{
					try
					{
						$db->Query("CREATE TABLE", array($api_db_assets, array(
							"id" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true, "PRIMARY KEY" => true, "AUTO INCREMENT" => true),
							"publish" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"unpublish" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"lastupdated" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"type" => array("STRING", 1, 20, "NOT NULL" => true),
							"uuid" => array("STRING", 1, 55, "NOT NULL" => true),
							"title" => array("STRING", 1, 255, "NOT NULL" => true),
							"info" => array("STRING", 3, "NOT NULL" => true),
						),
						array(
							array("KEY", array("publish", "type"), "NAME" => "publish_type"),
							array("KEY", array("lastupdated", "type"), "NAME" => "lastupdated_type"),
							array("UNIQUE", array("uuid"), "NAME" => "uuid"),
						)));
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Unable to create the database table '" . htmlspecialchars($api_db_assets) . "'.  " . htmlspecialchars($e->getMessage());
					}
				}

				if (!count($errors) && !$tagsfound)
				{
					try
					{
						$db->Query("CREATE TABLE", array($api_db_tags, array(
							"aid" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"tag" => array("STRING", 1, 255, "NOT NULL" => true),
							"publish" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
						),
						array(
							array("PRIMARY", array("aid", "tag"), "NAME" => "tag"),
							array("KEY", array("tag", "publish"), "NAME" => "publish"),
						)));
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Unable to create the database table '" . htmlspecialchars($api_db_tags) . "'.  " . htmlspecialchars($e->getMessage());
					}
				}

				if (!count($errors) && !$revisionsfound)
				{
					try
					{
						$db->Query("CREATE TABLE", array($api_db_revisions, array(
							"aid" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"revnum" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"created" => array("INTEGER", 8, "UNSIGNED" => true, "NOT NULL" => true),
							"info" => array("STRING", 3, "NOT NULL" => true),
						),
						array(
							array("PRIMARY", array("aid", "revnum"), "NAME" => "revision"),
						)));
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Unable to create the database table '" . htmlspecialchars($api_db_revisions) . "'.  " . htmlspecialchars($e->getMessage());
					}
				}

				// Prepare the /feeds template.
				if (!count($errors) && $config["css_feeds_host"] != "")
				{
					$data = @file_get_contents($config["rootpath"] . "/support/bb_feeds_api_base.php.template");
					if (!is_string($data))  $errors["msg"] = "Unable to load /feeds template for generation.";
					else
					{
						$data = str_replace("@NAME@", $config["css_feeds_name"], $data);
						$data = str_replace("@CONFIG@", var_export($config, true), $data);

						$filename = $config["rootpath"] . "/future_" . $config["css_feeds_name"] . ".php";
						if (@file_put_contents($filename, $data) === false)  $errors["msg"] = "Unable to write /feeds future calculation PHP code to '" . htmlspecialchars($filename) . "'.";
						else if (function_exists("opcache_invalidate"))  @opcache_invalidate($filename, true);
					}
				}

				// Write the configuration to disk.
				if (!count($errors))
				{
					$data = "<" . "?php\n";
					$data .= "\$config = " . var_export($config, true) . ";\n";
					$data .= "?" . ">";

					$filename = $config["rootpath"] . "/config.php";
					if (@file_put_contents($filename, $data) === false)  $errors["msg"] = "Unable to write configuration to '" . htmlspecialchars($filename) . "'.";
					else if (function_exists("opcache_invalidate"))  @opcache_invalidate($filename, true);
				}

				if (!count($errors))
				{
					$_SESSION["bb_cms_api_installed"] = true;

					header("Location: " . $ff->GetFullRequestURLBase() . "?action=done&sec_t=" . $ff->CreateSecurityToken("done"));

					exit();
				}
			}
		}

		OutputHeader("Step 4:  Configure Settings");

		if (count($errors))  $ff->OutputMessage("error", $errors["msg"]);
		else if ($message != "")  $ff->OutputMessage("info", $message);

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "* File storage path",
					"type" => "text",
					"name" => "files_path",
					"default" => $_SESSION["bb_cms_api_install"]["files_path"],
					"desc" => "The exact physical path on the server where binary files (e.g. images) are to be stored.  The directory must exist and be writeable by the web server."
				),
				array(
					"title" => "File storage base URL",
					"type" => "text",
					"name" => "files_url",
					"default" => $_SESSION["bb_cms_api_install"]["files_url"],
					"desc" => "The exact base URL where the stored binary files can be accessed via a web browser at the file storage path above."
				),
				array(
					"title" => "Allowed file extensions",
					"type" => "text",
					"name" => "file_exts",
					"default" => $_SESSION["bb_cms_api_install"]["file_exts"],
					"desc" => "A semi-colon separated list of allowed file extensions.  The default allows standard web image files."
				),
				array(
					"title" => "* Maximum revisions",
					"type" => "text",
					"name" => "max_revisions",
					"default" => $_SESSION["bb_cms_api_install"]["max_revisions"],
					"desc" => "The maximum number of revisions of each asset to keep.  The default is 50."
				),
				array(
					"title" => "* Default language code",
					"type" => "text",
					"name" => "default_lang",
					"default" => $_SESSION["bb_cms_api_install"]["default_lang"],
					"htmldesc" => "The specific default <a href=\"http://www.iana.org/assignments/language-subtag-registry\" target=\"blank\">IANA language code</a> to use for content storage.  This feature is only useful if you plan on storing multilingual content."
				),
				array(
					"title" => "Cloud Storage Server /feeds host",
					"type" => "text",
					"name" => "css_feeds_host",
					"default" => $_SESSION["bb_cms_api_install"]["css_feeds_host"],
					"desc" => "Enter a valid Cloud Storage Server host with the /feeds API to enable realtime publishing notifications.  Usually:  http://127.0.0.1:9893 or http://127.0.0.1:9892."
				),
				array(
					"title" => "Cloud Storage Server /feeds API key",
					"type" => "text",
					"name" => "css_feeds_apikey",
					"default" => $_SESSION["bb_cms_api_install"]["css_feeds_apikey"],
					"desc" => "Enter a valid Cloud Storage Server API key with /feeds extension support to enable realtime publishing notifications."
				),
				array(
					"title" => "Cloud Storage Server /feeds feed name",
					"type" => "text",
					"name" => "css_feeds_name",
					"default" => $_SESSION["bb_cms_api_install"]["css_feeds_name"],
					"desc" => "The feed name to use for /feeds notifications."
				)
			),
			"submit" => array("test" => "Test Settings", "next" => "Install")
		);

		$ff->Generate($contentopts, $errors);

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "step3")
	{
		$databases = BB_GetSupportedDatabases();
		$database = $_SESSION["bb_cms_api_install"]["db_select"];

		if (!isset($_SESSION["bb_cms_api_install"]["db_dsn"]))
		{
			$rng = new CSPRNG(true);

			$dsn = (isset($databases[$database]) ? $databases[$database]["default_dsn"] : "");
			$dsn = str_replace("@RANDOM@", $rng->GenerateString(), $dsn);
			if (strpos($dsn, "@PATH@") !== false)
			{
				@mkdir("db", 0775);
				@file_put_contents("db/index.html", "");
			}
			$dsn = str_replace("@PATH@", str_replace("\\", "/", dirname(__FILE__)) . "/db", $dsn);

			$_SESSION["bb_cms_api_install"]["db_dsn"] = $dsn;
		}

		if (!isset($_SESSION["bb_cms_api_install"]["db_user"]))  $_SESSION["bb_cms_api_install"]["db_user"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["db_pass"]))  $_SESSION["bb_cms_api_install"]["db_pass"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["db_name"]))  $_SESSION["bb_cms_api_install"]["db_name"] = "cms";
		if (!isset($_SESSION["bb_cms_api_install"]["db_table_prefix"]))  $_SESSION["bb_cms_api_install"]["db_table_prefix"] = "cms_";
		if (!isset($_SESSION["bb_cms_api_install"]["db_master_dsn"]))  $_SESSION["bb_cms_api_install"]["db_master_dsn"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["db_master_user"]))  $_SESSION["bb_cms_api_install"]["db_master_user"] = "";
		if (!isset($_SESSION["bb_cms_api_install"]["db_master_pass"]))  $_SESSION["bb_cms_api_install"]["db_master_pass"] = "";

		$message = "";
		if (isset($_REQUEST["db_dsn"]))
		{
			// Test database access.
			$_REQUEST["db_name"] = preg_replace('/[^a-z]/', "_", strtolower($_REQUEST["db_name"]));
			if (!isset($databases[$database]))  $errors["msg"] = "Invalid database selected.  Go back and try again.";
			else if ($_REQUEST["db_dsn"] == "")
			{
				$errors["msg"] = "Please correct the errors below and try again.";
				$errors["db_dsn"] = "Please fill in this field with a valid DSN.";
			}
			else if ($_REQUEST["db_name"] == "")
			{
				$errors["msg"] = "Please correct the errors below and try again.";
				$errors["db_name"] = "Please fill in this field.";
			}
			else
			{
				require_once "support/csdb/db_" . $database . ".php";

				$classname = "CSDB_" . $database;

				try
				{
					$db = new $classname();
					$db->SetDebug(true);
					$db->Connect($database . ":" . $_REQUEST["db_dsn"], ($databases[$database]["login"] ? $_REQUEST["db_user"] : false), ($databases[$database]["login"] ? $_REQUEST["db_pass"] : false));
					$message = "Successfully connected to the server.<br><b>Running " . htmlspecialchars($db->GetDisplayName() . " " . $db->GetVersion()) . "</b><br>";
					unset($db);
				}
				catch (Exception $e)
				{
					$errors["msg"] = "Database connection attempt failed.<br>" . htmlspecialchars($e->getMessage());
				}

				if ($databases[$database]["replication"] && $_REQUEST["db_master_dsn"] != "")
				{
					try
					{
						$db = new $classname();
						$db->SetDebug(true);
						$db->Connect($database . ":" . $_REQUEST["db_master_dsn"], ($databases[$type]["login"] ? $_REQUEST["db_master_user"] : false), ($databases[$type]["login"] ? $_REQUEST["db_master_pass"] : false));
						$message .= "Successfully connected to the server.<br><b>Running " . htmlspecialchars($db->GetDisplayName() . " " . $db->GetVersion()) . "</b><br>";
						unset($db);
					}
					catch (Exception $e)
					{
						$errors["msg"] = "Database connection attempt failed.<br>" . htmlspecialchars($e->getMessage());
					}
				}
			}

			if (!count($errors) && isset($_REQUEST["next"]))
			{
				$_SESSION["bb_cms_api_install"]["db_dsn"] = $_REQUEST["db_dsn"];
				if ($databases[$database]["login"])  $_SESSION["bb_cms_api_install"]["db_user"] = $_REQUEST["db_user"];
				if ($databases[$database]["login"])  $_SESSION["bb_cms_api_install"]["db_pass"] = $_REQUEST["db_pass"];
				$_SESSION["bb_cms_api_install"]["db_name"] = $_REQUEST["db_name"];
				$_SESSION["bb_cms_api_install"]["db_table_prefix"] = $_REQUEST["db_table_prefix"];
				if ($databases[$database]["replication"])
				{
					$_SESSION["bb_cms_api_install"]["db_master_dsn"] = $_REQUEST["db_master_dsn"];
					if ($databases[$database]["login"])  $_SESSION["bb_cms_api_install"]["db_master_user"] = $_REQUEST["db_master_user"];
					if ($databases[$database]["login"])  $_SESSION["bb_cms_api_install"]["db_master_pass"] = $_REQUEST["db_master_pass"];
				}

				header("Location: " . $ff->GetFullRequestURLBase() . "?action=step4&sec_t=" . $ff->CreateSecurityToken("step4"));

				exit();
			}
		}

		OutputHeader("Step 3:  Configure Database");

		if (isset($databases[$database]))
		{
			if (count($errors))  $ff->OutputMessage("error", $errors["msg"]);
			else if ($message != "")  $ff->OutputMessage("info", $message);

			$contentopts = array(
				"fields" => array(
					array(
						"title" => "* DSN options",
						"type" => "text",
						"name" => "db_dsn",
						"default" => $_SESSION["bb_cms_api_install"]["db_dsn"],
						"desc" => "The initial connection string to connect to the database server.  Options are driver specific.  Usually takes the form of:  host=ipaddr_or_hostname[;port=portnum] (e.g. host=127.0.0.1;port=3306)"
					),
					array(
						"use" => $databases[$database]["login"],
						"title" => "Username",
						"type" => "text",
						"name" => "db_user",
						"default" => $_SESSION["bb_cms_api_install"]["db_user"],
						"desc" => "The username to use to log into the database server."
					),
					array(
						"use" => $databases[$database]["login"],
						"title" => "Password",
						"type" => "password",
						"name" => "db_pass",
						"default" => $_SESSION["bb_cms_api_install"]["db_pass"],
						"desc" => "The password to use to log into the database server."
					),
					array(
						"title" => "* Database",
						"type" => "text",
						"name" => "db_name",
						"default" => $_SESSION["bb_cms_api_install"]["db_name"],
						"desc" => "The database to select after connecting into the database server."
					),
					array(
						"title" => "Table prefix",
						"type" => "text",
						"name" => "db_table_prefix",
						"default" => $_SESSION["bb_cms_api_install"]["db_table_prefix"],
						"desc" => "The prefix to use for table names in the selected database."
					),
					array(
						"use" => $databases[$database]["replication"],
						"title" => "Replication master - DSN options",
						"type" => "text",
						"name" => "db_master_dsn",
						"default" => $_SESSION["bb_cms_api_install"]["db_master_dsn"],
						"desc" => "The connection string to connect to the master database server.  Leave blank if you aren't using database replication!  Options are driver specific.  Usually takes the form of:  host=ipaddr_or_hostname[;port=portnum] (e.g. host=somehost;port=3306)"
					),
					array(
						"use" => $databases[$database]["replication"] && $databases[$database]["login"],
						"title" => "Replication master - Username",
						"type" => "text",
						"name" => "db_master_user",
						"default" => $_SESSION["bb_cms_api_install"]["db_master_user"],
						"desc" => "The username to use to log into the replication master database server."
					),
					array(
						"use" => $databases[$database]["replication"] && $databases[$database]["login"],
						"title" => "Replication master - Password",
						"type" => "password",
						"name" => "db_master_pass",
						"default" => $_SESSION["bb_cms_api_install"]["db_master_pass"],
						"desc" => "The password to use to log into the replication master database server."
					)
				),
				"submit" => array("test" => "Test Connection", "next" => "Next Step")
			);

			$ff->Generate($contentopts, $errors);
		}
		else
		{
			$ff->OutputMessage("error", "Invalid database selected.  Go back and try again.");
		}

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "step2")
	{
		$databases2 = array();
		$databases = BB_GetSupportedDatabases();
		foreach ($databases as $database => $info)
		{
			require_once "support/csdb/db_" . $database . ".php";

			try
			{
				$classname = "CSDB_" . $database;
				$db = new $classname();
				if ($db->IsAvailable() !== false)  $databases2[$database] = $db->GetDisplayName() . (!$info["production"] ? " [NOT for production use]" : "");
			}
			catch (Exception $e)
			{
			}
		}

		if (!isset($_SESSION["bb_cms_api_install"]["db_select"]))  $_SESSION["bb_cms_api_install"]["db_select"] = "";

		if (isset($_REQUEST["db_select"]))
		{
			if (!isset($databases[$_REQUEST["db_select"]]))  $errors["db_select"] = "Please select a database.  If none are available, make sure at least one supported PDO database driver is enabled in your PHP installation.";

			if (!count($errors))
			{
				$_SESSION["bb_cms_api_install"]["db_select"] = $_REQUEST["db_select"];
				unset($_SESSION["bb_cms_api_install"]["db_dsn"]);

				header("Location: " . $ff->GetFullRequestURLBase() . "?action=step3&sec_t=" . $ff->CreateSecurityToken("step3"));

				exit();
			}
		}

		OutputHeader("Step 2:  Select Database");

		if (count($errors))  $ff->OutputMessage("error", "Please correct the errors below to continue.");

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "* Available databases",
					"type" => "select",
					"name" => "db_select",
					"options" => $databases2,
					"default" => $_SESSION["bb_cms_api_install"]["db_select"],
					"desc" => (isset($databases2["sqlite"]) ? "SQLite should only be used for smaller installations." : "")
				),
			),
			"submit" => "Next Step"
		);

		$ff->Generate($contentopts, $errors);

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "step1")
	{
		if (isset($_REQUEST["submit"]))
		{
			header("Location: " . $ff->GetFullRequestURLBase() . "?action=step2&sec_t=" . $ff->CreateSecurityToken("step2"));

			exit();
		}

		OutputHeader("Step 1:  Environment Check");

		if ((double)phpversion() < 5.6)  $errors["phpversion"] = "The server is running PHP " . phpversion() . ".  The installation may succeed but the API will not function.  Running outdated versions of PHP poses a serious website security risk.  Please contact your system administrator to upgrade your PHP installation.";

		if (file_put_contents("test.dat", "a") === false)  $errors["createfiles"] = "Unable to create 'test.dat'.  Temporarily running chmod 777 on the directory may fix the problem.  You can change permissions back after installation.";
		else if (!unlink("test.dat"))  $errors["createfiles"] = "Unable to delete 'test.dat'.  Temporarily running chmod 777 on the directory may fix the problem.  You can change permissions back after installation.";

		if (!isset($_SERVER["REQUEST_URI"]))  $errors["requesturi"] = "The server does not appear to support this feature.  The installation may fail and the site might not work.";

		if (!$ff->IsSSLRequest())  $errors["ssl"] = "The API should be installed over SSL.  SSL/TLS certificates can be obtained for free.  Proceed only if this major security risk is acceptable.";

		try
		{
			$rng = new CSPRNG(true);
		}
		catch (Exception $e)
		{
			$error["csprng"] = "Please ask your system administrator to install a supported PHP version (e.g. PHP 7 or later) or extension (e.g. OpenSSL).";
		}

?>
<p>The current PHP environment has been evaluated against the minimum system requirements.  Any issues found are noted below.  After correcting any issues, reload the page.</p>
<?php

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "PHP 5.6.x or later",
					"type" => "static",
					"name" => "phpversion",
					"value" => (isset($errors["phpversion"]) ? "No.  Test failed." : "Yes.  Test passed.")
				),
				array(
					"title" => "Able to create files in ./",
					"type" => "static",
					"name" => "createfiles",
					"value" => (isset($errors["createfiles"]) ? "No.  Test failed." : "Yes.  Test passed.")
				),
				array(
					"title" => "\$_SERVER[\"REQUEST_URI\"] supported",
					"type" => "static",
					"name" => "requesturi",
					"value" => (isset($errors["requesturi"]) ? "No.  Test failed." : "Yes.  Test passed.")
				),
				array(
					"title" => "Installation over SSL",
					"type" => "static",
					"name" => "ssl",
					"value" => (isset($errors["ssl"]) ? "No.  Test failed." : "Yes.  Test passed.")
				),
				array(
					"title" => "Crypto-safe CSPRNG available",
					"type" => "static",
					"name" => "csprng",
					"value" => (isset($errors["csprng"]) ? "No.  Test failed." : "Yes.  Test passed.")
				)
			),
			"submit" => "Next Step",
			"submitname" => "submit"
		);

		$functions = array(
			"stream_socket_client" => "Cloud Storage Server /feeds integration",
			"json_encode" => "JSON encoding/decoding (critical!)",
		);

		foreach ($functions as $function => $info)
		{
			if (!function_exists($function))  $errors["function|" . $function] = "The software will be unable to use " . $info . ".  The installation might succeed but the product may not function at all.";

			$contentopts["fields"][] = array(
				"title" => "'" . $function . "' available",
				"type" => "static",
				"name" => "function|" . $function,
				"value" => (isset($errors["function|" . $function]) ? "No.  Test failed." : "Yes.  Test passed.")
			);
		}

		$classes = array(
			"PDO" => "PDO database classes",
		);

		foreach ($classes as $class => $info)
		{
			if (!class_exists($class))  $errors["class|" . $function] = "The software will be unable to use " . $info . ".  The installation might succeed but the product may not function at all.";

			$contentopts["fields"][] = array(
				"title" => "'" . $class . "' available",
				"type" => "static",
				"name" => "class|" . $class,
				"value" => (isset($errors["class|" . $class]) ? "No.  Test failed." : "Yes.  Test passed.")
			);
		}

		$ff->Generate($contentopts, $errors);

		OutputFooter();
	}
	else
	{
		OutputHeader("Introduction");

		foreach ($_GET as $key => $val)
		{
			if (!isset($_SESSION["bb_cms_api_install"][$key]))  $_SESSION["bb_cms_api_install"][$key] = (string)$val;
		}

?>
<p>You are about to install the Barebones CMS API:  A high-performance server-side API for hosting content for other resources to leverage.</p>

<p>For the best performance, consider setting up a <a href="https://github.com/cubiclesoft/cloud-storage-server-ext-feeds" target="_blank">Cloud Storage Server /feeds</a> instance before installing.  You can always connect the API to Cloud Storage Server later on by manually editing the configuration file (config.php) that is generated by this installer.</p>

<p><a href="<?=$ff->GetRequestURLBase()?>?action=step1&sec_t=<?=$ff->CreateSecurityToken("step1")?>">Start installation</a></p>
<?php

		OutputFooter();
	}
?>