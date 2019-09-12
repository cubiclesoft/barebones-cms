Frontend Patterns
=================

There are a million different ways to build a website.  Intentionally and by design, the release distribution of Barebones CMS itself does not do anything beyond creation and management of content.

The Barebones CMS SDK is designed to aid in building one or more website frontends.  A website frontend is how website visitors see your content.  The patterns found in this document will give you boilerplate code to get started with building your new website frontend for anything from an entire website or just part of it.

Before starting, make sure your web server is [set up properly](http://cubicspot.blogspot.com/2017/05/secure-web-server-permissions-that-just.html).

All of the frontend examples require a copy of the Barebones CMS SDK in a subdirectory called 'support'.  The examples follow the [suggested tagging approach](asset-tagging.md) and assume a mostly static content website with some dynamic elements (i.e. a standard business website).  Adjust the examples for your use-case.

File Retrieval Pattern
----------------------

The file retrieval pattern allows a web browser to retrieve a file stored via the API.  Files uploaded to the API may or may not be publicly accessible depending on how the API is configured.

Example file retrieval pattern usage:

* Cropping and resizing images.
* Streaming video or audio files.
* Downloading large files such as PDF or ZIP files.

First, create a directory where files will be retrieved from (e.g. 'getfile').  For the remainder of this documentation, this directory will be referred to as '/getfile/'.

Next, create a 'config.php' file in the new directory:

```php
<?php
	$config = array();
	$config["rootpath"] = str_replace("\\", "/", dirname(__FILE__));

	// URL and read-only API key from the API's 'config.php' file.
	$config["read_url"] = "";
	$config["read_apikey"] = "";

	// Random bytes to use for the secret for generating digital signatures.
	// https://www.random.org/integers/?num=100&min=0&max=255&col=10&base=16&format=plain&rnd=new
	$config["secret"] = "";

	// Cache directory options.
	// See DeliverFile() in the SDK documentation for details.
	$config["cache_dir"] = $config["rootpath"] . "/cache";
	$config["api_dir"] = false;
```

The above configuration requires a 'cache' subdirectory to exist that can be written to by the web server.  The SDK will cache files to that location on disk for faster retrieval/processing later.

Next, create an 'index.php' file in the same directory:

```php
<?php
	// Load the configuration.
	require_once "config.php";

	require_once $config["rootpath"] . "/support/sdk_barebones_cms_api.php";

	if (!isset($_REQUEST["id"]) || !is_string($_REQUEST["id"]))
	{
		http_response_code(404);

		echo "Missing asset ID.\n";

		exit();
	}

	if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))
	{
		http_response_code(404);

		echo "Missing 'filename'.\n";

		exit();
	}

	// Verify the digital signature.  This helps prevent abuse of system resources.
	if (!isset($_REQUEST["sig"]) || !is_string($_REQUEST["sig"]) || !BarebonesCMS::IsValidFileSignature($_REQUEST["id"], (isset($_REQUEST["path"]) ? $_REQUEST["path"] : ""), $_REQUEST["filename"], (isset($_REQUEST["crop"]) ? $_REQUEST["crop"] : ""), (isset($_REQUEST["maxwidth"]) ? $_REQUEST["maxwidth"] : ""), $_REQUEST["sig"], $config["secret"]))
	{
		http_response_code(403);

		echo "Invalid 'sig'.\n";

		exit();
	}

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo($config["read_url"], $config["read_apikey"]);

	$mimeinfomap = $cms->GetDefaultMimeInfoMap();

	$options = array(
		"cachedir" => $config["cache_dir"],
		"apidir" => $config["api_dir"],
		"path" => (isset($_REQUEST["path"]) && is_string($_REQUEST["path"]) ? $_REQUEST["path"] : ""),
		"download" => (isset($_REQUEST["download"]) && is_string($_REQUEST["download"]) ? $_REQUEST["download"] : false),
		"crop" => (isset($_REQUEST["crop"]) && is_string($_REQUEST["crop"]) ? $_REQUEST["crop"] : ""),
		"maxwidth" => (isset($_REQUEST["maxwidth"]) && is_numeric($_REQUEST["maxwidth"]) ? (int)$_REQUEST["maxwidth"] : -1),
		"mimeinfomap" => $mimeinfomap
	);

	$cms->DeliverFile($_REQUEST["id"], $_REQUEST["filename"], $options);
```

It is recommended to periodically clean up the cache directory via a cron job.  Create a 'cleanup.php' file in the same directory:

```php
<?php
	if (!isset($_SERVER["argc"]) || !$_SERVER["argc"])
	{
		echo "This file is intended to be run from the command-line.";

		exit();
	}

	$rootpath = str_replace("\\", "/", dirname(__FILE__));

	require_once $rootpath . "/config.php";
	require_once $rootpath . "/support/sdk_barebones_cms_api.php";

	BarebonesCMS::CleanFileCache($config["cache_dir"]);
?>
```

And schedule it to run hourly via cron.

Static Page, One Asset Pattern
------------------------------

The static page pattern is for parts of a website that are located at a fixed URL, change infrequently, and generally retrieve a very specific asset for that page.

Example static page pattern usage:

* Homepage.
* About Us page.
* Content for a Contact Us form.
* Privacy Policy, Terms of Service, or other legal forms and verbiage.

First, create a 'config.php' file at the location for the static page(s):

```php
<?php
	$config = array();
	$config["rootpath"] = str_replace("\\", "/", dirname(__FILE__));

	// The base URL path (no trailing slash).
	$config["rooturl"] = "";

	// The amount of the URL to remove to calculate the asset tag to search for.
	$config["tag_base_path"] = "/";

	// A prefix to add to the tag to calculate the asset tag to search for.
	$config["tag_base_prefix"] = "";

	// The maximum number of assets to load.
	$config["max_assets"] = 25;

	// A passphrase or random bytes to use for bypassing the cache.
	// https://www.random.org/integers/?num=100&min=0&max=255&col=10&base=16&format=plain&rnd=new
	$config["refresh_key"] = "";

	// URL and read-only API key from the API's 'config.php' file.
	$config["read_url"] = "";
	$config["read_apikey"] = "";

	// These options are for precaching files and calculating URLs to files.
	// See PrecacheDeliverFile() in the SDK documentation for details.
	$config["file_cache_dir"] = $config["rootpath"] . "/file_cache";
	$config["file_cache_url"] = $config["rooturl"] . "/file_cache/";
	$config["api_files_dir"] = false;
	$config["api_files_url"] = false;
	$config["get_file_url"] = "/getfile/";
	$config["get_file_secret"] = "YourFileRetrievalSecretGoesHere";

	// The directory in which to cache transformed assets.
	// Ideally located outside the web root or at least protected from direct access.
	$config["content_dir"] = $config["rootpath"] . "/content";

	// The default IANA language code to use.  When in doubt, use the option from the API 'config.php' file.
	$config["default_lang"] = "en-us";

	// The URL to your Barebones CMS administrative interface installation.
	// Enables easy editing of an asset.
	$config["admin_url"] = false;
?>
```

Create the directories as specified by the 'file_cache_dir' and 'content_dir' configuration options and make sure they are owned by the web server user.

Create an 'index.php' file in the same directory as the 'config.php' file:

```php
<?php
	// Static page pattern.

	// Load the configuration.
	require_once "config.php";

	// Load the SDK.
	require_once $config["rootpath"] . "/support/sdk_barebones_cms_lite.php";

	// Calculate the tag to retrieve based on the current URL.
	$tag = Request::GetURLBase();
	if (strncasecmp($tag, $config["tag_base_path"], strlen($config["tag_base_path"])) == 0)  $tag = substr($tag, strlen($config["tag_base_path"]));
	if ($tag === "" || $tag{0} !== "/")  $tag = "/" . $tag;

	// Redirect when there is no trailing slash for SEO purposes.
	if (substr($tag, -1) !== "/")
	{
		header("Location: " . Request::GetFullURLBase() . "/");

		exit();
	}

	if ($config["tag_base_prefix"] !== "")  $tag = $config["tag_base_prefix"] . $tag;

	// Create the options array.
	$aoptions = array(
		"tag" => $tag,
		"type" => "story"
	);

	// Load the content from the API if the refresh key is used.
	$refresh = BarebonesCMSLite::CanRefreshContent($config["refresh_key"]);
//	if ($refresh || @filemtime(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "static_one", $aoptions)) < time() - 5 * 60)
	if ($refresh)
	{
		// Redirect if it looks like a refresh key was submitted.
		if (isset($_GET["refresh"]) || isset($_POST["refresh"]))
		{
			header("Location: " . Request::GetFullURLBase());

			exit();
		}

		require_once $config["rootpath"] . "/support/sdk_barebones_cms_api.php";

		// Initialize the SDK.
		$cms = new BarebonesCMS();
		$cms->SetAccessInfo($config["read_url"], $config["read_apikey"]);

		// Retrieve the content.
		$result = $cms->GetAssets($aoptions, 1);
		if (!$result["success"])
		{
			echo "Failed to load assets.  Error:  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "<br>";

			exit();
		}

		$assets = $result["assets"];

		// Set up content defaults.
		$toptions = array(
			"maxwidth" => 920,
			"mimeinfomap" => $cms->GetDefaultMimeInfoMap(),
			"cachedir" => $config["file_cache_dir"],
			"cacheurl" => $config["file_cache_url"],
			"apidir" => $config["api_files_dir"],
			"apiurl" => $config["api_files_url"],
			"getfileurl" => $config["get_file_url"],
			"getfilesecret" => $config["get_file_secret"],
			"siteurl" => $config["rooturl"],
		);

		// Transform the content for viewing.
		foreach ($assets as $num => $asset)
		{
			$assets[$num] = $cms->TransformStoryAssetBody($asset, $toptions);
		}

		// Store the content for later.
		if (count($assets))  $cms->CacheAssets($config["content_dir"], "static_one", $aoptions, $assets);
		else  @unlink($cms->GetCachedAssetsFilename($config["content_dir"], "static_one", $aoptions));
	}

	// Load the layout.
	require_once $config["rootpath"] . "/layout.php";

	// Load the content.
	$assets = BarebonesCMSLite::LoadCachedAssets($config["content_dir"], "static_one", $aoptions);
	if (!count($assets))  Output404();

	$asset = $assets[0];

	// Select language.
	$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);
	$title = $asset["langinfo"][$lang]["title"];

	// Handle redirect.
	if (strncasecmp($title, "redirect:", 9) == 0)
	{
		header("Location: " . trim(substr($title, 9)));

		exit();
	}

	// Output site header.
	OutputHeader($title);

	// Display the page content.
	if ($refresh)
	{
		BarebonesCMS::OutputHeartbeat();
		if ($config["admin_url"] !== false)  BarebonesCMS::OutputPageAdminEditButton($config["admin_url"], $asset, $lang);
	}

	if (isset($asset["langinfo"][$lang . "-hero-top"]))
	{
		echo $asset["langinfo"][$lang . "-hero-top"]["body"];

?>
<div class="contentwrap fancycontent">
<div class="contentwrapinner">
<?php
	}
	else
	{
?>
<div class="contentwrap">
<div class="contentwrapinner">
<?php
		if ($tag !== $config["tag_base_prefix"] . "/")
		{
?>
<h1><?=htmlspecialchars($title)?></h1>
<?php
		}
	}
?>

<?=$asset["langinfo"][$lang]["body"]?>
</div>
</div>
<?php

	// Output site footer.
	OutputFooter();
?>
```

Next, create a file called 'layout.php' in the same directory:

```php
<?php
	function OutputHeader($title = "YourWebsiteHere", $desc = "", $img = false)
	{
		global $config;

		header("Content-Type: text/html; UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
<title><?=htmlspecialchars($title)?></title>
<meta charset="utf-8">
<meta http-equiv="x-ua-compatible" content="ie=edge">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<link rel="stylesheet" href="<?=$config["rooturl"]?>/main.css" type="text/css" media="all">
<link rel="icon" type="image/png" sizes="256x256" href="<?=$config["rooturl"]?>/icon_256x256.png">
<link rel="shortcut icon" type="image/x-icon" href="/favicon.ico">
<?php
		if ($desc !== "")
		{
			if ($img === false)  $img = Request::PrependHost($config["rooturl"]) . "/icon_256x256.png";

?>
<meta name="description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@YourTwitterHandleHere">
<meta name="twitter:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:title" content="<?=htmlspecialchars($title)?>">
<meta name="og:type" content="website">
<meta name="twitter:description" content="<?=htmlspecialchars($desc)?>">
<meta name="og:description" content="<?=htmlspecialchars($desc)?>">
<meta name="twitter:image" content="<?=htmlspecialchars($img)?>">
<meta name="og:image" content="<?=htmlspecialchars($img)?>">
<?php
		}
?>
</head>
<body>

<!-- Put your header/menu here. -->

<?php
	}

	function OutputFooter()
	{
		global $config;

?>

<!-- Put your footer/copyright here. -->

</body>
</html>
<?php
	}

	function OutputBasicHeader($title, $header)
	{
		OutputHeader($title . " | YourWebsiteHere");

?>
<div class="contentwrap">
<div class="contentwrapinner">
<h1><?=htmlspecialchars($header)?></h1>
<?php
	}

	function OutputBasicFooter()
	{
?>
</div>
</div>
<?php

		OutputFooter();

		exit();
	}

	function OutputPage($title, $header, $message)
	{
		OutputBasicHeader($title, $header);

		echo $message;

		OutputBasicFooter();

		exit();
	}

	function Output404()
	{
		http_response_code(404);

		OutputPage("Invalid Resource", "Invalid Resource", "<p>The requested resource does not exist, was unpublished, or has moved.  Unfortunately, this is a 404 so there's nothing to do.</p>");
	}
?>
```

To apply this pattern to all paths along the current path, create a '.htaccess' file in the same directory (for Apache):

```
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
```

Don't forget to enable the `mod_rewrite` module and set `AllowOverride All` or `AllowOverride FileInfo Options` in the Apache configuration to allow the '.htaccess' file to be processed by the web server.

Or use the following rule for Nginx:

```
# Replace the '/' with the root URL.
location / {
	try_files $uri $uri/ /index.php$is_args$args;
}
```

This pattern, by default, caches content indefinitely until a refresh key is used.  For example:

`http://yourdomain.com/?refresh=...`

Where the '...' is the value of 'refresh_key' from the configuration.  Create a bookmark in your web browser to the URL for forced reloading of fresh content from the API and/or use the Cloud Storage Server /feeds extension to trigger a refresh.

News Pattern
------------

The news pattern is for stream-of-consciousness sections of a website.  The main page shows a list of assets ordered from most recent to oldest with a link to each asset for viewing the whole story.

Example news pattern usage:

* News articles.
* Announcements.
* A corporate or personal blog for posting updates.

```php
<?php
	// News pattern.

	// Load the configuration.
	require_once "../config.php";

	// Load the SDK.
	require_once $config["rootpath"] . "/support/sdk_barebones_cms_lite.php";

	// Calculate the tag to retrieve based on the current URL.
	$tag = Request::GetURLBase();
	if (strncasecmp($tag, $config["tag_base_path"], strlen($config["tag_base_path"])) == 0)  $tag = substr($tag, strlen($config["tag_base_path"]));
	if ($tag === "" || $tag{0} !== "/")  $tag = "/" . $tag;

	if ($config["tag_base_prefix"] !== "")  $tag = $config["tag_base_prefix"] . $tag;

	// Create the options array.
	if (substr($tag, -1) === "/")
	{
		// Display a list.
		$mode = "list";
		$aoptions = array(
			"tag" => "~" . $tag,
			"type" => "story"
		);
	}
	else
	{
		// Extract the file extension (if any).
		$pos = strrpos($tag, "/");
		$ext = substr($tag, $pos + 1);
		$pos = strpos($ext, ".");
		if ($pos === false)  $ext = "";
		else
		{
			$ext = substr($ext, $pos + 1);
			$tag = substr($tag, 0, -(strlen($ext) + 1));
		}

		// Redirect if the request does not have a file extension.
		if ($ext === "")
		{
			header("Location: " . Request::GetFullURLBase() . ".html");

			exit();
		}

		// Display a single asset by UUID.
		$mode = "asset";
		$pos = strrpos($tag, "/");
		$uuid = substr($tag, $pos + 1);
		$tag = substr($tag, 0, $pos + 1);

		$aoptions = array(
			"uuid" => $uuid,
			"type" => "story"
		);
	}

	// Load the content from the API if the refresh key is used.
	$refresh = BarebonesCMSLite::CanRefreshContent($config["refresh_key"]);
//	if ($refresh || @filemtime(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "news", $aoptions)) < time() - 5 * 60)
	if ($refresh)
	{
		// Redirect if it looks like a refresh key was submitted.
		if (isset($_GET["refresh"]) || isset($_POST["refresh"]))
		{
			header("Location: " . Request::GetFullURLBase());

			exit();
		}

		require_once $config["rootpath"] . "/support/sdk_barebones_cms_api.php";

		// Initialize the SDK.
		$cms = new BarebonesCMS();
		$cms->SetAccessInfo($config["read_url"], $config["read_apikey"]);

		// Retrieve the content.
		$result = $cms->GetAssets($aoptions, ($mode === "list" ? (int)$config["max_assets"] : 1));
		if (!$result["success"])
		{
			echo "Failed to load assets.  Error:  " . htmlspecialchars($result["error"] . " (" . $result["errorcode"] . ")") . "<br>";

			exit();
		}

		$assets = $result["assets"];

		// Set up content defaults.
		$toptions = array(
			"maxwidth" => ($mode === "list" ? 250 : 920),
			"mimeinfomap" => $cms->GetDefaultMimeInfoMap(),
			"cachedir" => $config["file_cache_dir"],
			"cacheurl" => $config["file_cache_url"],
			"apidir" => $config["api_files_dir"],
			"apiurl" => $config["api_files_url"],
			"getfileurl" => $config["get_file_url"],
			"getfilesecret" => $config["get_file_secret"],
			"siteurl" => $config["rooturl"],
		);

		$toptions2 = $toptions;
		$toptions2["maxwidth"] = 250;

		$soptions = array(
		);

		// Transform the content for viewing.
		$processed = array(
			"/" => true
		);
		foreach ($assets as $num => $asset)
		{
			$asset = $cms->TransformStoryAssetBody($asset, $toptions);

			if ($mode === "list")
			{
				$asset = $cms->GenerateStoryAssetSummary($asset, $soptions);
				$asset["preftag"] = $cms->GetPreferredTag($asset["tags"], "/news/", "*/news/");
			}
			else if ($mode === "asset")
			{
				// Rebuild all associated sections.
				foreach ($asset["tags"] as $tag2)
				{
					if ($tag2{0} !== "/")  continue;

					while (!isset($processed[$tag2]))
					{
						$aoptions2 = array(
							"tag" => "~" . $tag2,
							"type" => "story"
						);

						$result = $cms->GetAssets($aoptions2, (int)$config["max_assets"]);
						if ($result["success"])
						{
							$assets2 = $result["assets"];

							foreach ($assets2 as $num2 => $asset2)
							{
								$asset2 = $cms->TransformStoryAssetBody($asset2, $toptions);
								$asset2 = $cms->GenerateStoryAssetSummary($asset2, $soptions);
								$asset2["preftag"] = $cms->GetPreferredTag($asset2["tags"], "/news/", "*/news/");

								$assets2[$num2] = $asset2;
							}

							// Store the content for later.
							$cms->CacheAssets($config["content_dir"], "news", $aoptions2, $assets2);

							$processed[$tag2] = true;

							// Go up one level.
							$tag2 = rtrim($tag2, "/");
							$pos = strrpos($tag2, "/");
							if ($pos === false)  break;
							$tag2 = substr($tag2, 0, $pos + 1);
						}
					}
				}
			}

			$assets[$num] = $asset;
		}

		// Store the content for later.
		if (count($assets) || $mode === "list")  $cms->CacheAssets($config["content_dir"], "news", $aoptions, $assets);
		else  @unlink($cms->GetCachedAssetsFilename($config["content_dir"], "news", $aoptions));
	}

	// Load the layout.
	require_once $config["rootpath"] . "/layout.php";

	// Load the content.
	$assets = BarebonesCMSLite::LoadCachedAssets($config["content_dir"], "news", $aoptions);

	$dispmap = array(
		"" => array(
			"_title" => "News",
			"_read_more" => "Read story",
			"_no_assets" => "No stories found.",
			"_publish_format" => function($ts) { return date("n/j/Y @ g:i a", $ts); }
		)
	);

	if ($mode === "list")
	{
		// Select page language.
		$pagelang = BarebonesCMSLite::GetPreferredLanguage((isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);

		// Select a language mapping to use for the page based on language code.
		if ($pagelang !== "" && isset($dispmap[$pagelang]))
		{
			foreach ($dispmap[""] as $key => $val)
			{
				if (!isset($dispmap[$pagelang][$key]))  $dispmap[$pagelang][$key] = $val;
			}

			$dispmap = $dispmap[$pagelang];
		}
		else
		{
			$dispmap = $dispmap[""];
		}

		// Process format options here (e.g. "rss" could return the assets as a RSS feed, "json" for JSON, etc).
//		if (isset($_REQUEST["format"]) && $_REQUEST["format"] === "rss")  {  }

		$parts = explode("/", $tag);
		$breadcrumbs = array();
		$parts2 = array();
		$title = $dispmap["_title"];
		foreach ($parts as $part)
		{
			$part = trim($part);
			if ($part !== "")
			{
				$parts2[] = $part;
				$part = (isset($dispmap[$part]) ? $dispmap[$part] : ucfirst($part));
				$breadcrumbs[] = "<a href=\"" . htmlspecialchars($config["rooturl"] . "/" . implode("/", $parts2)) . "/\">" . htmlspecialchars($part) . "</a>";
				$title = $part;
			}
		}

		if (count($breadcrumbs))  array_pop($breadcrumbs);

		// Output site header.
		OutputHeader($title);

		// Display the page content.
		if ($refresh)  BarebonesCMS::OutputHeartbeat();

?>
<div class="contentwrap">
<div class="contentwrapinner">
<?php
		if (count($breadcrumbs))  echo "<div class=\"breadcrumbs\">" . implode(" &raquo; ", $breadcrumbs) . "</div>\n";

		if ($tag !== $config["tag_base_prefix"] . "/")
		{
?>
<h1><?=htmlspecialchars($title)?></h1>
<?php
		}

		if (count($assets))
		{
?>
<div class="assetswrap">
<?php
			foreach ($assets as $asset)
			{
				$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);

				$url = $config["rooturl"] . $asset["preftag"] . $asset["uuid"] . ".html";

?>
	<div class="assetwrap">
		<?php if ($asset["langinfo"][$lang]["img"] !== false)  echo "<div class=\"assetimage\">" . $asset["langinfo"][$lang]["img"] . "</div>\n"; ?>
		<div class="assettitle"><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($asset["langinfo"][$lang]["title"])?></a></div>
		<div class="assetpublished"><?=htmlspecialchars($dispmap["_publish_format"]($asset["publish"]))?></div>

		<?=implode("\n", $asset["langinfo"][$lang]["summary"])?>

		<div class="assetreadlink"><a href="<?=htmlspecialchars($url)?>"><?=htmlspecialchars($dispmap["_read_more"])?></a></div>
	</div>
<?php
			}
?>
</div>
<?php
		}
		else
		{
			echo "<p>" . $dispmap["_no_assets"] . "</p>\n";
		}
?>
</div>
</div>
<?php

		// Output site footer.
		OutputFooter();
	}
	else if ($mode === "asset")
	{
		if (!count($assets))
		{
			// If this wasn't actually an asset but a section that already exists, then redirect.
			$aoptions = array(
				"tag" => "~" . $tag . $aoptions["uuid"] . "/",
				"type" => "story"
			);

			if (file_exists(BarebonesCMSLite::GetCachedAssetsFilename($config["content_dir"], "news", $aoptions)))
			{
				header("Location: " . Request::GetFullURLBase() . "/");

				exit();
			}

			Output404();
		}

		$asset = $assets[0];

		// Handle redirect.
		if (isset($asset["langinfo"]["redirect"]))
		{
			header("Location: " . trim($asset["langinfo"]["redirect"]["title"]));

			exit();
		}

		// Handle permalink resolution.
		$preftag = BarebonesCMSLite::GetPreferredTag($asset["tags"], "/news/", "*/news/");
		if ($preftag === false)  Output404();
		if ($tag !== $preftag)
		{
			header("Location: " . Request::GetHost() . $config["rooturl"] . $preftag . $asset["uuid"] . ".html");

			exit();
		}

		// Select language.
		$lang = BarebonesCMSLite::GetPreferredAssetLanguage($asset, (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? $_SERVER["HTTP_ACCEPT_LANGUAGE"] : ""), $config["default_lang"]);
		$title = $asset["langinfo"][$lang]["title"];

		if ($lang !== "" && isset($dispmap[$lang]))
		{
			foreach ($dispmap[""] as $key => $val)
			{
				if (!isset($dispmap[$lang][$key]))  $dispmap[$lang][$key] = $val;
			}

			$dispmap = $dispmap[$lang];
		}
		else
		{
			$dispmap = $dispmap[""];
		}

		// Process file extension options here (e.g. "json" could return the asset as JSON).
//		if ($ext === "json")  {  }

		// Output site header.
		OutputHeader($title);

		// Display the page content.
		if ($refresh)
		{
			BarebonesCMS::OutputHeartbeat();
			if ($config["admin_url"] !== false)  BarebonesCMS::OutputPageAdminEditButton($config["admin_url"], $asset, $lang);
		}

		if (isset($asset["langinfo"][$lang . "-hero-top"]))
		{
			echo $asset["langinfo"][$lang . "-hero-top"]["body"];

?>
<div class="contentwrap fancycontent">
<div class="contentwrapinner">
<?php
		}
		else
		{
?>
<div class="contentwrap">
<div class="contentwrapinner">
<?php
			if ($tag !== $config["tag_base_prefix"] . "/")
			{
?>
<h1><?=htmlspecialchars($title)?></h1>
<?php
			}
?>
<div class="assetpublished"><?=htmlspecialchars($dispmap["_publish_format"]($asset["publish"]))?></div>
<?php
		}
?>

<?=$asset["langinfo"][$lang]["body"]?>
</div>
</div>
<?php

		// Output site footer.
		OutputFooter();
	}
?>
```

The example assumes assets for this section will be tagged with a tag that starts with the string `/news/`.

To apply this pattern to all paths along the current path, create a '.htaccess' file in the same directory (for Apache):

```
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
```

Don't forget to enable the `mod_rewrite` module and set `AllowOverride All` or `AllowOverride FileInfo Options` in the Apache configuration to allow the '.htaccess' file to be processed by the web server.

Or use the following rule for Nginx:

```
# Replace the '/news/' with the correct URL path.
location /news/ {
	try_files $uri $uri/ /news/index.php$is_args$args;
}
```

This pattern, by default, caches content indefinitely until a refresh key is used.  For example:

`http://yourdomain.com/news/?refresh=...`

Where the '...' is the value of 'refresh_key' from the configuration.  Create a bookmark in your web browser to the URL for forced reloading of fresh content from the API and/or use the Cloud Storage Server /feeds extension to trigger a refresh.
