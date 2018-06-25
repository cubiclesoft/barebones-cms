Barebones CMS Installation Guide
================================

The Barebones CMS release distribution comes with two major installers:  The Barebones CMS API installer and the Barebones CMS administrative interface installer.

This guide provides instructions on installing Barebones CMS in the shortest amount of time possible.

Installing the Barebones CMS API
--------------------------------

Time required:  5-15 minutes

The Barebones CMS API optionally integrates with the [/feeds extension](https://github.com/cubiclesoft/cloud-storage-server-ext-feeds) of [Cloud Storage Server](https://github.com/cubiclesoft/cloud-storage-server) for realtime time-based content change notifications (e.g. the moment content reaches publish time).  When using /feeds, it is recommended to install and configure Cloud Storage Server and the /feeds extension first on the same host the API will be installed on before installing the Barebones CMS API.  Installing Cloud Storage Server and the /feeds extension can take up to one hour.

Upload the contents of the 'api' directory of the release distribution.  The Barebones CMS API can reside on the same server as the website it will be delivering content to or on a completely different server.

[Obfuscating the directory name](https://www.random.org/integers/?num=100&min=0&max=255&col=10&base=16&format=plain&rnd=new) with random content where the API is stored can help keep any would-be attackers out.  Using HTTPS is highly recommended too.

Run the installer (install.php) via a web browser and follow the instructions.

* Introduction - Nothing to do on this screen but click "Start installation".
* Environment Check - All tests should pass.  Reload the page after making changes to the server.
* Select Database - "SQLite via PDO" is the default (if enabled) but should only be used for smaller installations.  [CSDB databases](https://github.com/cubiclesoft/csdb) are supported.
* Configure Database - Enter appropriate information for the database selected from the previous screen.  The DSN string can be the hardest part to get right for non-default system setups.
* Configure Settings - This screen lets you configure the API for your desired setup.  The configuration can always be changed later by manually editing the generated 'config.php' file.
* Installation Finished - Follow any relevant directions on this screen (e.g. finishing up Cloud Storage Server /feeds connectivity).

Note that direct access to the Barebones CMS API should be limited to appropriate, trusted users.  For public and/or untrusted users of the API, setting up a different URL with per-user keys and rate limiting is recommended.  Setting up such a system is beyond the scope of this documentation.

Installing the Barebones CMS Admin Interface
--------------------------------------------

Time required:  5 minutes (for local computer/LAN installs) to 2 hours (Internet-facing installs)

Place the contents of the 'admin' directory of the release distribution wherever it should reside.  See the "Securing the Admin Interface" section below to help decide where the admin interface will be installed.

Run the installer (install.php) via a web browser and follow the instructions.

* Introduction - Nothing to do on this screen but click "Start installation".
* Environment Check - All tests should pass.  Reload the page after making changes to the server.
* API Access Information - Use the information stored in the 'config.php' file that the API installer created to fill in the appropriate fields.
* Installation Finished - Follow any relevant directions on this screen.

Once the admin interface is installed, it is time to write some content!

(demo video)

Securing the Admin Interface
----------------------------

The Barebones CMS administrative interface does not come with a login system nor does it have any concept of users and permissions.  This is intentional and by design since Barebones CMS can be entirely secure on a properly firewalled system that is used by just one or two people (e.g. a personal computer or a small LAN web server).

On an Internet-facing server or in an installation with multiple users, the admin interface will probably require a login system to properly protect it.  If you don't already have a login system, the [Barebones SSO server/client](https://github.com/cubiclesoft/sso-server) software is an excellent choice.

To integrate a login system, create a file called 'index_hook.php' in the root directory of the admin interface and write code that interfaces with your login system.  The following global variables should be set:

* $bb_usertoken - A string containing a unique token for the user that is never sent to the user's web browser.  Used for XSRF defenses.
* $bb_username - A string containing a way to identify the user later should the need arise.  This string will be stored with each changed asset and also stored in the revision system.

Example SSO client 'index_hook.php' file:

```php
<?php
	if (!isset($bb_admin_version))  exit();

	// Switch to SSL.
	if (!BB_IsSSLRequest())
	{
		header("Location: " . BB_GetFullRequestURLBase("https"));
		exit();
	}

	require_once "client/config.php";
	require_once SSO_CLIENT_ROOT_PATH . "/index.php";

	$sso_client = new SSO_Client;
	$sso_client->Init(array("sso_impersonate", "sso_remote_id"));

	$extra = array();
	if (isset($_REQUEST["sso_impersonate"]) && is_string($_REQUEST["sso_impersonate"]))  $extra["sso_impersonate"] = $_REQUEST["sso_impersonate"];
	if (!$sso_client->LoggedIn())  $sso_client->Login("", "You must login to use this system.", $extra);

	// Store user information for later.
	$sso_key = "sso_" . $sso_client->GetUserID();
	if ($sso_client->UserLoaded())
	{
		$_SESSION[$sso_key] = array();
		$_SESSION[$sso_key]["email"] = $sso_client->GetField("email");
	}

	// Send the browser cookies.
	$sso_client->SaveUserInfo();

	// Test permissions for the user.
	if (!$sso_client->IsSiteAdmin() && !SSO_HasTag("bb_cms_admin"))  SSO_Login("", "insufficient_permissions");

	// Set user information.
	$bb_usertoken = $sso_client->GetSecretToken();
	$bb_username = $_SESSION[$sso_key]["email"];
?>
```

Implementing per-user permissions (e.g. control who can delete uploaded files) is beyond the scope of this documentation.
