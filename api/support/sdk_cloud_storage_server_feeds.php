<?php
	// Cloud Storage Server feeds SDK class.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	// This class only supports the /feeds API.
	class CloudStorageServerFeeds
	{
		private $web, $fp, $host, $apikey, $cafile, $cacert, $cert, $neterror;

		public function __construct()
		{
			if (!class_exists("WebBrowser", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/web_browser.php";

			$this->web = new WebBrowser();
			$this->fp = false;
			$this->host = false;
			$this->apikey = false;
			$this->cafile = false;
			$this->cacert = false;
			$this->cert = false;
		}

		public function SetAccessInfo($host, $apikey, $cafile, $cert)
		{
			$this->web = new WebBrowser();
			if (is_resource($this->fp))  @fclose($this->fp);
			$this->fp = false;
			if (substr($host, -1) === "/")  $host = substr($host, 0, -1);
			$this->host = $host;
			$this->apikey = $apikey;
			$this->cafile = $cafile;
			$this->cert = $cert;
		}

		public function GetSSLInfo()
		{
			if ($this->host === false)  return array("success" => false, "error" => self::CSS_Translate("Missing host."), "errorcode" => "no_access_info");

			if ($this->cafile !== false && $this->cacert === false)  $this->cacert = @file_get_contents($this->cafile);

			if (substr($this->host, 0, 7) === "http://")
			{
				$this->cacert = "";
				$this->cert = "";
			}
			else if ($this->cacert === false || $this->cert === false)
			{
				$this->cacert = false;
				$this->cert = false;

				$this->neterror = "";

				$options = array(
					"peer_cert_callback" => array($this, "Internal_PeerCertificateCheck"),
					"peer_cert_callback_opts" => "",
					"sslopts" => self::InitSSLOpts(array("verify_peer" => false, "capture_peer_cert_chain" => true))
				);

				$result = $this->web->Process($this->host . "/", $options);

				if (!$result["success"])
				{
					$result["error"] .= "  " . $this->neterror;

					return $result;
				}
			}

			if ($this->cert === false)  return array("success" => false, "error" => self::CSS_Translate("Unable to retrieve server certificate."), "errorcode" => "cert_retrieval_failed");
			if ($this->cacert === false)  return array("success" => false, "error" => self::CSS_Translate("Unable to retrieve server CA certificate."), "errorcode" => "cacert_retrieval_failed");

			return array("success" => true, "cacert" => $this->cacert, "cert" => $this->cert);
		}

		public function InitSSLCache($host, $cafile, $certfile)
		{
			if (!file_exists($cafile) || !file_exists($certfile))
			{
				$this->SetAccessInfo($host, false, false, false);

				$result = $this->GetSSLInfo();
				if (!$result["success"])  return array("success" => false, "error" => "Unable to get SSL information.", "errorcode" => "get_ssl_info_failed", "info" => $result);

				file_put_contents($cafile, $result["cacert"]);
				file_put_contents($certfile, $result["cert"]);
			}

			return array("success" => true);
		}

		public function Notify($name, $type, $id, $data = array(), $queue = false, $queuesize = -1)
		{
			$options = array(
				"name" => $name,
				"type" => $type,
				"id" => (string)$id,
				"data" => $data,
				"queuesize" => $queuesize
			);

			if ($queue !== false)  $options["queue"] = (int)$queue;

			return $this->RunAPI("POST", "notify", $options);
		}

		public function InitMonitor()
		{
			return $this->InitWebSocket();
		}

		public static function AddMonitor($ws, $sequence, $name, $filters = array())
		{
			$options = array(
				"api_method" => "GET",
				"api_path" => "/feeds/v1/monitor",
				"api_sequence" => $sequence,
				"name" => $name,
				"filters" => $filters
			);

			return $ws->Write(json_encode($options), WebSocket::FRAMETYPE_TEXT);
		}

		public function CreateGuest($name, $notify, $monitor, $expires)
		{
			$options = array(
				"name" => $name,
				"notify" => (int)(bool)$notify,
				"monitor" => (int)(bool)$monitor,
				"expires" => (int)$expires
			);

			return $this->RunAPI("POST", "guest/create", $options);
		}

		public function GetGuestList()
		{
			return $this->RunAPI("GET", "guest/list");
		}

		public function DeleteGuest($id)
		{
			return $this->RunAPI("DELETE", "guest/delete/" . $id);
		}

		private static function CSS_Translate()
		{
			$args = func_get_args();
			if (!count($args))  return "";

			return call_user_func_array((defined("CS_TRANSLATE_FUNC") && function_exists(CS_TRANSLATE_FUNC) ? CS_TRANSLATE_FUNC : "sprintf"), $args);
		}

		// Internal function to retrieve a X509 SSL certificate during the initial connection to confirm that this is the correct target server.
		public function Internal_PeerCertificateCheck($type, $cert, $opts)
		{
			if (is_array($cert))
			{
				// The server is incorrectly configured if it doesn't have the self-signed root certificate in the chain.
				if (count($cert) < 2)
				{
					$this->neterror = "Certificate chain is missing the root certificate.  Remote host is incorrectly configured.";

					return false;
				}

				// The last entry is the root cert.
				if (!openssl_x509_export($cert[count($cert) - 1], $str))
				{
					$this->neterror = "Certificate chain contains an invalid root certificate.  Corrupted on remote host?";

					return false;
				}

				$this->cacert = $str;
			}
			else
			{
				if (!openssl_x509_export($cert, $str))
				{
					$this->neterror = "Server certificate is invalid.  Corrupted on remote host?";

					return false;
				}

				// Initial setup automatically trusts the SSL/TLS certificate of the host.
				if ($this->cert === false)  $this->cert = $str;
				else if ($str !== $this->cert)
				{
					$this->neterror = "Certificate does not exactly match local certificate.  Your client is either under a MITM attack or the remote host changed certificates.";

					return false;
				}
			}

			return true;
		}

		private static function InitSSLOpts($options)
		{
			$result = array_merge(array(
				"ciphers" => "ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA:ECDHE-RSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-RSA-AES256-SHA256:DHE-RSA-AES256-SHA:ECDHE-ECDSA-DES-CBC3-SHA:ECDHE-RSA-DES-CBC3-SHA:EDH-RSA-DES-CBC3-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:DES-CBC3-SHA:!DSS",
				"disable_compression" => true,
				"allow_self_signed" => false,
				"verify_peer_name" => false,
				"verify_depth" => 3,
				"capture_peer_cert" => true,
			), $options);

			return $result;
		}

		private function InitWebSocket()
		{
			if ($this->host === false || $this->apikey === false)  return array("success" => false, "error" => self::CSS_Translate("Missing host or API key."), "errorcode" => "no_access_info");
			if ($this->cafile === false || $this->cert === false)  return array("success" => false, "error" => self::CSS_Translate("Missing SSL Certificate or Certificate Authority filename.  Call GetSSLInfo() to initialize for the first time and be sure to save the results."), "errorcode" => "critical_ssl_info_missing");

			$options = array(
				"headers" => array(
					"X-APIKey" => $this->apikey
				),
				"peer_cert_callback" => array($this, "Internal_PeerCertificateCheck"),
				"peer_cert_callback_opts" => "",
				"sslopts" => self::InitSSLOpts(array("cafile" => $this->cafile, "verify_peer" => true))
			);

			if (!class_exists("WebSocket", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/websocket.php";

			$ws = new WebSocket();

			$url = str_replace(array("https://", "http://"), array("wss://", "ws://"), $this->host);
			$result = $ws->Connect($url . "/feeds", $this->host, $options);
			if (!$result["success"])  return $result;

			$result["ws"] = $ws;

			return $result;
		}

		private function RunAPI($method, $apipath, $options = array(), $expected = 200, $encodejson = true, $decodebody = true)
		{
			if ($this->host === false || $this->apikey === false)  return array("success" => false, "error" => self::CSS_Translate("Missing host or API key."), "errorcode" => "no_access_info");
			if ($this->cafile === false || $this->cert === false)  return array("success" => false, "error" => self::CSS_Translate("Missing SSL Certificate or Certificate Authority filename.  Call GetSSLInfo() to initialize for the first time and be sure to save the results."), "errorcode" => "critical_ssl_info_missing");

			if ($this->fp !== false)
			{
				$options2 = array(
					"fp" => $this->fp,
					"method" => $method,
					"headers" => array(
						"Connection" => "keep-alive",
					)
				);
			}
			else
			{
				$options2 = array(
					"method" => $method,
					"headers" => array(
						"Connection" => "keep-alive",
						"X-APIKey" => $this->apikey
					),
					"peer_cert_callback" => array($this, "Internal_PeerCertificateCheck"),
					"peer_cert_callback_opts" => "",
					"sslopts" => self::InitSSLOpts(array("cafile" => $this->cafile, "verify_peer" => true))
				);
			}

			if ($encodejson && $method !== "GET")
			{
				$options2["headers"]["Content-Type"] = "application/json";
				$options2["body"] = json_encode($options);
			}
			else
			{
				$options2 = array_merge($options2, $options);
			}

			$result = $this->web->Process($this->host . "/feeds/v1/" . $apipath, $options2);

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

			// Cloud Storage Server always responds with 400 Bad Request for errors.  Attempt to decode the error.
			if ($result["response"]["code"] == 400)
			{
				$error = @json_decode($result["body"], true);
				if (is_array($error) && isset($error["success"]) && !$error["success"])  return $error;
			}

			if ($result["response"]["code"] != $expected)  return array("success" => false, "error" => self::CSS_Translate("Expected a %d response from Cloud Storage Server.  Received '%s'.", $expected, $result["response"]["line"]), "errorcode" => "unexpected_cloud_storage_server_response", "info" => $result);

			if ($decodebody)  $result["body"] = json_decode($result["body"], true);

			return $result;
		}
	}
?>