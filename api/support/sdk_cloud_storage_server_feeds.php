<?php
	// Cloud Storage Server feeds SDK class.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	// Load dependency.
	if (!class_exists("CloudStorageServer_APIBase", false))  require_once str_replace("\\", "/", dirname(__FILE__)) . "/sdk_cloud_storage_server_api_base.php";

	// This class only supports the /feeds API.
	class CloudStorageServerFeeds extends CloudStorageServer_APIBase
	{
		public function __construct()
		{
			parent::__construct();

			$this->apiprefix = "/feeds/v1";
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
				"api_path" => $this->apiprefix . "/monitor",
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
	}
?>