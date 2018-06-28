<?php
	// Barebones CMS Admin Installer
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (file_exists("config.php") && (!isset($_REQUEST["action"]) || $_REQUEST["action"] != "done"))  exit();

	require_once "support/str_basics.php";
	require_once "support/flex_forms.php";
	require_once "support/random.php";
	require_once "support/sdk_barebones_cms_api.php";

	Str::ProcessAllInput();

	session_start();
	if (!isset($_SESSION["bb_cms_admin_install"]))  $_SESSION["bb_cms_admin_install"] = array();
	if (!isset($_SESSION["bb_cms_admin_install"]["secret"]))
	{
		$rng = new CSPRNG();
		$_SESSION["bb_cms_admin_install"]["secret"] = $rng->GetBytes(64);
	}

	$ff = new FlexForms();
	$ff->SetSecretKey($_SESSION["bb_cms_admin_install"]["secret"]);
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
<title><?=htmlspecialchars($title)?> | Barebones CMS Admin Installer</title>
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
<div id="headerwrap"><div id="header">Barebones CMS Admin Installer</div></div>
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
		if (!isset($_SESSION["bb_cms_admin_installed"]) || !$_SESSION["bb_cms_admin_installed"])  exit();

		OutputHeader("Installation Finished");

		$ff->OutputMessage("success", "The installation completed successfully.");

?>
<p>The Barebones CMS admin interface was successfully installed.  You now have a powerful, easy to use content creation toolkit at your disposal.</p>

<p>What's next?  Secure the admin interface if it sits on Internet-facing infrastructure.  Write some content and develop the frontend using the SDK to display your content to website visitors.</p>
<?php

		OutputFooter();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "step2")
	{
		if (!isset($_SESSION["bb_cms_admin_install"]["readwrite_url"]))  $_SESSION["bb_cms_admin_install"]["readwrite_url"] = "";
		if (!isset($_SESSION["bb_cms_admin_install"]["readwrite_apikey"]))  $_SESSION["bb_cms_admin_install"]["readwrite_apikey"] = "";
		if (!isset($_SESSION["bb_cms_admin_install"]["readwrite_secret"]))  $_SESSION["bb_cms_admin_install"]["readwrite_secret"] = "";

		$message = "";
		if (isset($_REQUEST["readwrite_url"]))
		{
			if ($_REQUEST["readwrite_url"] == "")  $errors["readwrite_url"] = "Please fill in this field.";
			else if ($_REQUEST["readwrite_apikey"] == "")  $errors["readwrite_apikey"] = "Please fill in this field.";
			else if ($_REQUEST["readwrite_secret"] == "")  $errors["readwrite_secret"] = "Please fill in this field.";
			else
			{
				require_once "support/sdk_barebones_cms_api.php";

				$cms = new BarebonesCMS();
				$cms->SetAccessInfo($_REQUEST["readwrite_url"], $_REQUEST["readwrite_apikey"], $_REQUEST["readwrite_secret"]);

				$result = $cms->CheckAccess();
				if (!$result["success"])  $errors["readwrite_url"] = "Unable to check access.  " . $result["error"] . " (" . $result["errorcode"] . ")";
				else if (!$result["write"])  $errors["readwrite_apikey"] = "Access check succeeded but the API key or secret are not read/write OR server system clocks are not in sync.";
				else  $message .= "API access information looks okay.<br><b>Connected to the Barebones CMS API and verified read/write access.</b><br>";
			}

			if (count($errors))  $errors["msg"] = "Please correct the errors below and try again.";
			else if (isset($_REQUEST["next"]))
			{
				$rng = new CSPRNG(true);

				$config = array(
					"rootpath" => str_replace("\\", "/", dirname(__FILE__)),
					"rooturl" => dirname($ff->GetFullRequestURLBase()) . "/",
					"token_secret" => $rng->GenerateString(64),
					"readwrite_url" => $_REQUEST["readwrite_url"],
					"readwrite_apikey" => $_REQUEST["readwrite_apikey"],
					"readwrite_secret" => $_REQUEST["readwrite_secret"],
					"default_crop_ratio" => "",
					"crop_ratios" => array("", "16:9", "3:4", "1:1")
				);

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

				// Create the 'files' cache directory.
				if (!count($errors))
				{
					if (!is_dir($config["rootpath"] . "/files") && @mkdir($config["rootpath"] . "/files") === false)  $errors["msg"] = "Unable to create 'files' subdirectory.";
					else if (@file_put_contents($config["rootpath"] . "/files/index.html", "") === false)  $errors["msg"] = "Unable to create the file '" . htmlspecialchars($config["rootpath"] . "/files/index.html") . "'.";
				}

				if (!count($errors))
				{
					$_SESSION["bb_cms_admin_installed"] = true;

					header("Location: " . $ff->GetFullRequestURLBase() . "?action=done&sec_t=" . $ff->CreateSecurityToken("done"));

					exit();
				}
			}
		}

		OutputHeader("Step 2:  API Access Information");

		if (count($errors))  $ff->OutputMessage("error", "Please correct the errors below to continue.");
		else if ($message != "")  $ff->OutputMessage("info", $message);

		$contentopts = array(
			"fields" => array(
				array(
					"title" => "* Root URL",
					"type" => "text",
					"name" => "readwrite_url",
					"default" => $_SESSION["bb_cms_admin_install"]["readwrite_url"],
					"desc" => "The URL of the Barebones CMS API.  This field should be the 'rooturl' value from 'config.php'."
				),
				array(
					"title" => "* Read/Write API Key",
					"type" => "text",
					"name" => "readwrite_apikey",
					"default" => $_SESSION["bb_cms_admin_install"]["readwrite_apikey"],
					"desc" => "The read/write API key for the Barebones CMS API.  This field must be the 'readwrite_apikey' value from 'config.php'."
				),
				array(
					"title" => "* Read/Write Secret",
					"type" => "text",
					"name" => "readwrite_secret",
					"default" => $_SESSION["bb_cms_admin_install"]["readwrite_secret"],
					"desc" => "The read/write secret for the Barebones CMS API.  This field must be the 'readwrite_secret' value from 'config.php'."
				),
			),
			"submit" => array("test" => "Test API Access", "next" => "Install")
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

		if (file_put_contents("test.dat", "a") === false)  $errors["createfiles"] = "Unable to create 'test.dat'.  Running chmod 777 on the directory may fix the problem.";
		else if (!unlink("test.dat"))  $errors["createfiles"] = "Unable to delete 'test.dat'.  Running chmod 777 on the directory may fix the problem.";

		if (mkdir("test") === false)  $errors["createdirectories"] = "Unable to create 'test'.  Running chmod 777 on the directory may fix the problem.";
		else if (!rmdir("test"))  $errors["createdirectories"] = "Unable to remove 'test'.  Running chmod 777 on the directory may fix the problem.";

		if (!isset($_SERVER["REQUEST_URI"]))  $errors["requesturi"] = "The server does not appear to support this feature.  The installation may fail and the software might not work.";

		if (!$ff->IsSSLRequest())  $errors["ssl"] = "The admin interface should be installed over SSL if used on public infrastructure.  SSL/TLS certificates can be obtained for free.  Proceed only if this major security risk is acceptable.";

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
					"title" => "Able to create directories in ./",
					"type" => "static",
					"name" => "createdirectories",
					"value" => (isset($errors["createdirectories"]) ? "No.  Test failed." : "Yes.  Test passed.")
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
			"stream_socket_client" => "the Barebones CMS API (probably critical!)",
			"json_encode" => "JSON encoding/decoding (critical!)"
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

		$extensions = array(
			"imagick" => "ImageMagick to scale/crop images (if GD is available, this won't be a problem)",
			"gd" => "GD to scale/crop images (if ImageMagick is available, this won't be a problem)"
		);

		foreach ($extensions as $extension => $info)
		{
			if (!extension_loaded($extension))  $errors["extension|" . $extension] = "The software will be unable to use " . $info . ".  The installation might succeed but the product may not function at all.";

			$contentopts["fields"][] = array(
				"title" => "'" . $extension . "' available",
				"type" => "static",
				"name" => "extension|" . $extension,
				"value" => (isset($errors["extension|" . $extension]) ? "No.  Test failed." : "Yes.  Test passed.")
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
			if (!isset($_SESSION["bb_cms_admin_install"][$key]))  $_SESSION["bb_cms_admin_install"][$key] = (string)$val;
		}

?>
<p>You are about to install the Barebones CMS admin interface:  A powerful content authoring system for the Barebones CMS API.</p>

<p>If the admin interface is installed on Internet-facing infrastructure, you will need to connect it to a login system to properly secure it after installation.  The admin interface can be hosted anywhere as long as it can communicate with the Barebones CMS API, including a local computer or an isolated LAN.</p>

<p><a href="<?=$ff->GetRequestURLBase()?>?action=step1&sec_t=<?=$ff->CreateSecurityToken("step1")?>">Start installation</a></p>
<?php

		OutputFooter();
	}
?>