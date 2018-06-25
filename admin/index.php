<?php
	// Barebones CMS Admin Interface
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	require_once "support/str_basics.php";
	require_once "support/page_basics.php";

	Str::ProcessAllInput();

	require_once "config.php";

	$bb_randpage = $config["token_secret"];
	$bb_rootname = "Barebones CMS";

	$bb_usertoken = "";
	$bb_username = "";
	$bb_admin_version = array(2, 0, 0);

	@session_start();

	// Allow developers to interface with a login system.
	if (file_exists($config["rootpath"] . "/index_hook.php"))  require_once $config["rootpath"] . "/index_hook.php";

	BB_ProcessPageToken("action");


	// Initialize API access.
	require_once $config["rootpath"] . "/support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo($config["readwrite_url"], $config["readwrite_apikey"], $config["readwrite_secret"]);

	// Retrieve API information to configure the session.
	$sessionkey = "bb_cms_admin_" . md5($config["readwrite_url"]);
	if (!isset($_SESSION[$sessionkey]))  $_SESSION[$sessionkey] = array();
	if (!isset($_SESSION[$sessionkey]["api_info"]))
	{
		$result = $cms->CheckAccess();
		if (!$result["success"])  BB_SetPageMessage("error", "Failed to load critical Barebones CMS API configuration information.  " . $result["error"] . " (" . $result["errorcode"] . ")");
		else
		{
			$_SESSION[$sessionkey]["api_info"] = $result;
			$_SESSION[$sessionkey]["lang"] = $result["default_lang"];
		}
	}

	BB_InitLangmap($config["rootpath"] . "/lang/");
	if (isset($_SESSION[$sessionkey]["lang"]))  BB_SetLanguage($config["rootpath"] . "/lang/", $_SESSION[$sessionkey]["lang"]);

	// Retrieve the most recently used tags.
	if (!isset($_SESSION[$sessionkey]["recent_tags"]))
	{
		$result = $cms->GetTags(array("order" => "publish"), 100);
		if (!$result["success"])  BB_SetPageMessage("error", "Failed to load Barebones CMS API tag information.  " . $result["error"] . " (" . $result["errorcode"] . ")");
		else
		{
			$tags = array();
			foreach ($result["tags"] as $tag => $num)
			{
				$tags[$tag] = $tag;
			}

			$_SESSION[$sessionkey]["recent_tags"] = $tags;
		}
	}

	// Delete old cache files.
	if (!isset($_SESSION[$sessionkey]["cache_cleanup"]) || $_SESSION[$sessionkey]["cache_cleanup"] < time() - 3 * 60 * 60)
	{
		$cms->CleanFileCache($config["rootpath"] . "/files");

		$_SESSION[$sessionkey]["cache_cleanup"] = time();
	}

	// Menu/Navigation options.
	$menuopts = array(
	);

	// Menu title underline:  Colors with 60% saturation and 75% brightness generally look good.
	ob_start();
?>
<style type="text/css">
#menuwrap .menu .title { border-bottom: 2px solid #C48851; }
</style>

<script type="text/javascript">
setInterval(function() {
	jQuery.post('<?=BB_GetRequestURLBase()?>', {
		'action': 'heartbeat',
		'sec_t': '<?=BB_CreateSecurityToken("heartbeat")?>'
	});
}, 5 * 60 * 1000);
</script>
<?php
	$bb_layouthead = ob_get_contents();
	ob_end_clean();

	function BB_InjectLayoutHead()
	{
		global $bb_layouthead;

		echo $bb_layouthead;
	}

	// Add menu options.
	$files = array();
	$dir = @opendir($config["rootpath"] . "/menus");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".php")  $files[] = $config["rootpath"] . "/menus/" . $file;
		}

		closedir($dir);
	}

	sort($files, SORT_NATURAL);
	foreach ($files as $file)  require_once $file;
	unset($files);

	// Handle common action initialization.
	if (isset($_REQUEST["action"]) && stripos($_REQUEST["action"], "asset") !== false)
	{
		$assetid = (isset($_REQUEST["id"]) ? (int)$_REQUEST["id"] : 0);
		$revnum = 0;

		// Attempt to load the asset.
		if ($assetid > 0)
		{
			if (isset($_REQUEST["revision"]) && (int)$_REQUEST["revision"] > 0)
			{
				$result = $cms->GetRevisions((string)$assetid, (int)$_REQUEST["revision"]);
				if (!$result["success"])  BB_SetPageMessage("error", BB_Translate("Unable to load revisions.  API failure.  %s (%s)", $result["error"], $result["errorcode"]));
				else if (!count($result["revisions"]))  unset($_REQUEST["revision"]);
				else
				{
					$revrow = $result["revisions"][0];
					$revnum = (int)$revrow["revision"];
					$asset = $cms->NormalizeAsset($revrow["info"]);
				}
			}

			if ($revnum < 1)
			{
				$result = $cms->GetAssets(array("id" => (string)$assetid));
				if (!$result["success"] || !count($result["assets"]))
				{
					if (!$result["success"])  BB_SetPageMessage("error", BB_Translate("Unable to load asset.  API failure.  %s (%s)", $result["error"], $result["errorcode"]));
					else  BB_SetPageMessage("info", BB_Translate("Unable to load asset %u.  Asset does not exist.", $assetid));

					$assetid = 0;
					$asset = array();
					if (isset($_REQUEST["type"]) && is_string($_REQUEST["type"]))  $asset["type"] = $_REQUEST["type"];
					$asset = $cms->NormalizeAsset($asset);
				}
				else
				{
					$asset = $cms->NormalizeAsset($result["assets"][0]);
					$_REQUEST["type"] = $asset["type"];
				}
			}
		}
		else
		{
			$asset = array();
			if (isset($_REQUEST["type"]) && is_string($_REQUEST["type"]))  $asset["type"] = $_REQUEST["type"];
			$asset = $cms->NormalizeAsset($asset);
		}

		// Add current language if missing.
		if (isset($_REQUEST["lang"]))
		{
			if (is_string($_REQUEST["lang"]))  $_REQUEST["lang"] = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z0-9]/', " ", strtolower(trim($_REQUEST["lang"])))));
			else  unset($_REQUEST["lang"]);
		}
		$assetlang = (isset($_REQUEST["lang"]) && $_REQUEST["lang"] !== "" ? $_REQUEST["lang"] : $_SESSION[$sessionkey]["lang"]);
		if (!isset($asset["langinfo"][$assetlang]))  $asset["langinfo"][$assetlang] = array();
		if (!isset($asset["langinfo"][$assetlang]["title"]))  $asset["langinfo"][$assetlang]["title"] = "";
		if (!isset($asset["langinfo"][$assetlang]["body"]))  $asset["langinfo"][$assetlang]["body"] = "";
	}

	// Event manager setup.
	require_once $config["rootpath"] . "/support/event_manager.php";

	$em = new EventManager();

	// Plugin initialization.
	$dir = @opendir($config["rootpath"] . "/plugins");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".php")  require_once $config["rootpath"] . "/plugins/" . $file;
		}

		closedir($dir);
	}

	$em->Fire("plugins_loaded", array());

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "heartbeat")
	{
		$em->Fire("heartbeat", array());

		$_SESSION[$sessionkey]["lastts"] = time();

		echo "OK";
		exit();
	}

	// Generate MIME type information for asset file management.
	$mimeinfomap = $cms->GetDefaultMimeInfoMap();

	$em->Fire("mimeinfo_map", array(&$mimeinfomap));

	// Process actions.
	$dir = @opendir($config["rootpath"] . "/actions");
	if ($dir)
	{
		while (($file = readdir($dir)) !== false)
		{
			if (substr($file, -4) === ".php")  require_once $config["rootpath"] . "/actions/" . $file;
		}

		closedir($dir);
	}

	// Fallback.
	$contentopts = array(
		"desc" => "Pick an option from the menu."
	);

	$em->Fire("fallback_contentopts", array(&$contentopts));

	BB_GeneratePage("Home", $menuopts, $contentopts);
?>