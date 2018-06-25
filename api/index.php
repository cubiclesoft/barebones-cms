<?php
	// Barebones CMS API
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	require_once "config.php";
	require_once $config["rootpath"] . "/support/bb_functions.php";

	function SendOutput($result, $bulk, &$opts)
	{
		if (!headers_sent())  header("Content-type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		if ($bulk)  echo "\n";
	}

	function SendError($msg, $msgcode, $info = false)
	{
		$result = array(
			"success" => false,
			"error" => $msg,
			"errorcode" => $msgcode
		);
		if ($info !== false)  $result["info"] = $info;

		$opts = false;
		SendOutput($result, false, $opts);

		exit();
	}

	// Load data to verify.
	$reqmethod = strtoupper($_SERVER["REQUEST_METHOD"]);
	if (isset($_GET["rest_data"]))  $data = @json_decode($_GET["rest_data"], true);
	else if ($reqmethod === "GET")  $data = $_GET;
	else if (isset($_POST["rest_data"]))  $data = @json_decode($_POST["rest_data"], true);
	else if (isset($_SERVER["CONTENT_TYPE"]) && strtolower(substr($_SERVER["CONTENT_TYPE"], 0, 16)) === "application/json")  $data = @json_decode(file_get_contents("php://input"), true);
	else if (isset($_SERVER["HTTP_CONTENT_TYPE"]) && strtolower(substr($_SERVER["HTTP_CONTENT_TYPE"], 0, 16)) === "application/json")  $data = @json_decode(file_get_contents("php://input"), true);
	else  $data = @array_merge($_GET, $_POST);

	if (!is_array($data))  SendError("Invalid input.", "invalid_input");

	// Credentials check.
	if (!isset($_SERVER["HTTP_X_APIKEY"]) || !is_string($_SERVER["HTTP_X_APIKEY"]))  $_SERVER["HTTP_X_APIKEY"] = "";
	if (BB_CTstrcmp($config["read_apikey"], $_SERVER["HTTP_X_APIKEY"]) == 0)
	{
		$write = false;
	}
	else if (BB_CTstrcmp($config["readwrite_apikey"], $_SERVER["HTTP_X_APIKEY"]) == 0)
	{
		// Check signature for write access.  Allow for system clock drift of 5 minutes.
		if (!isset($_SERVER["HTTP_X_SIGNATURE"]) || !is_string($_SERVER["HTTP_X_SIGNATURE"]))  $_SERVER["HTTP_X_SIGNATURE"] = "";
		$sig = base64_encode(hash_hmac("sha256", json_encode($data, JSON_UNESCAPED_SLASHES), $config["readwrite_secret"], true));
		$write = (BB_CTstrcmp($sig, $_SERVER["HTTP_X_SIGNATURE"]) == 0 && isset($data["ts"]) && (int)$data["ts"] >= time() - 300 && (int)$data["ts"] < time() + 300);
		unset($sig);
	}
	else
	{
		SendError("Missing or invalid API key.", "missing_or_invalid_apikey");
	}

	// Verify version compatibility.
	if (!isset($data["ver"]))  SendError("Missing API version.", "missing_ver");
	if ($data["ver"] != 1)  SendError("Expected API version 1.", "expected_ver_1");

	// Verify that the 'api' variable exists and is a string.
	if (!isset($data["api"]) || !is_string($data["api"]))  SendError("Missing 'api' or not a string.", "missing_or_invalid_api");

	// Instantiate the reusable API Helper.
	if (file_exists($config["rootpath"] . "/bb_api_helper_override.php"))
	{
		require_once $config["rootpath"] . "/bb_api_helper_override.php";

		$apihelper = new BB_API_Helper_Override($config, $write);
	}
	else
	{
		$apihelper = new BB_API_Helper($config, $write);
	}

	// Connect to the database.
	$result = $apihelper->ConnectDB();
	if (!$result["success"])  SendError($result["error"], $result["errorcode"], $result["info"]);

	$opts = false;
	if ($reqmethod === "GET")
	{
		switch ($data["api"])
		{
			case "assets":  $result = $apihelper->GetAssets($data, "SendOutput", $opts);  break;
			case "tags":  $result = $apihelper->GetTags($data, "SendOutput", $opts);  break;
			case "revisions":
			{
				if (!$write)  SendError("Access denied for revisions list.  Possible causes:  Using a read only API key or missing/using an invalid signature or timestamp.", "access_denied");

				$result = $apihelper->GetRevisions($data, "SendOutput", $opts);

				break;
			}
			case "file":
			{
				$result = $apihelper->DownloadFile($data);
				if (!$result["success"])  http_response_code(404);

				break;
			}
			case "access":  $result = $apihelper->GetAccessInfo("SendOutput", $opts);  break;
			case "delete_upload":
			{
				if (!$write)  SendError("Access denied for delete operation.  Possible causes:  Using a read only API key or missing/using an invalid signature or timestamp.", "access_denied");

				$result = $apihelper->DeleteUpload($data, "SendOutput", $opts);

				break;
			}
			case "delete_asset":
			{
				if (!$write)  SendError("Access denied for delete operation.  Possible causes:  Using a read only API key or missing/using an invalid signature or timestamp.", "access_denied");

				$result = $apihelper->DeleteAsset($data, "SendOutput", $opts);

				break;
			}
			default:
			{
				$result = $apihelper->UnknownAPI($reqmethod, $data, "SendOutput", $opts);

				break;
			}
		}

		if (!$result["success"])  SendError($result["error"], $result["errorcode"], $result["info"]);
	}
	else if ($reqmethod === "DELETE")
	{
		if (!$write)  SendError("Access denied for delete operation.  Possible causes:  Using a read only API key or missing/using an invalid signature or timestamp.", "access_denied");

		switch ($data["api"])
		{
			case "upload":  $result = $apihelper->DeleteUpload($data, "SendOutput", $opts);  break;
			case "asset":  $result = $apihelper->DeleteAsset($data, "SendOutput", $opts);  break;
			default:
			{
				$result = $apihelper->UnknownAPI($reqmethod, $data, "SendOutput", $opts);

				break;
			}
		}

		if (!$result["success"])  SendError($result["error"], $result["errorcode"]);
	}
	else if ($reqmethod === "POST")
	{
		if (!$write)  SendError("Write access denied.  Possible causes:  Using a read only API key or missing/using an invalid signature or timestamp.", "access_denied");

		switch ($data["api"])
		{
			case "asset":  $result = $apihelper->StoreAsset($data, "SendOutput", $opts);  break;
			case "upload_start":  $result = $apihelper->StartUpload($data, "SendOutput", $opts);  break;
			case "upload":  $result = $apihelper->ProcessUpload($data, "SendOutput", $opts);  break;
			case "upload_done":  $result = $apihelper->UploadDone($data, "SendOutput", $opts);  break;
			default:
			{
				$result = $apihelper->UnknownAPI($reqmethod, $data, "SendOutput", $opts);

				break;
			}
		}

		if (!$result["success"])  SendError($result["error"], $result["errorcode"], (isset($result["info"]) ? $result["info"] : false));
	}
	else
	{
		SendError("Unrecognized HTTP method.", "unrecognized_http_method");
	}
?>