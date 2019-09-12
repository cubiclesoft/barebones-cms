<?php
	// Barebones CMS main actions.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($menuopts))  exit();

	if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "viewassethistory")
	{
		// Asset revisions/history view.
		$em->Fire("viewassethistory_init", array($assetid, &$asset));

		unset($_REQUEST["bb_msgtype"]);
		unset($_REQUEST["bb_msg"]);

		// This allows the revision history to be viewed for deleted assets.
		$id = (isset($_REQUEST["id"]) ? (int)$_REQUEST["id"] : 0);
		if (!$id)  BB_RedirectPage("error", "No asset ID.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));

		$result = $cms->GetRevisions($id);
		if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Unable to get revisions.  %s (%s)", $result["error"], $result["errorcode"]), array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));

		$desc = "<br>";
		ob_start();
?>
<style type="text/css">
.contextlineswrap { display: none; }
.contextlineswrapinner { display: table; border: 1px solid #CCCCCC; border-collapse: collapse; word-break: break-word; }
.contextlineitemwrap { display: table-row; }
.contextlineitem { display: table-cell; vertical-align: top; padding: 0.2em 0.5em; }
.contextlineitem-gutter { border-right: 1px solid #CCCCCC; background-color: #EEEEEE; font-weight: bold; text-align: center; }
.contextlineitem-unmodified { border-top: 1px solid #EEEEEE; background-color: #FCFCFC; color: #888888; }
.contextlineitem-inserted { border-top: 1px solid #B2DBA1; border-bottom: 1px solid #B2DBA1; background-color: #DFF0D8; color: #3C763D; }
.contextlineitem-deleted { border-top: 1px solid #DCA7A7; border-bottom: 1px solid #DCA7A7; background-color: #F2DEDE; color: #A94442; }
.contextlineitemwrap:first-child .contextlineitem { border-top: 1px solid #CCCCCC; }
.contextlineitemwrap:last-child .contextlineitem { border-bottom: 1px solid #CCCCCC; }

@media (max-width: 525px) {
	.contextlineswrapinner { word-break: break-all; hyphens: auto; }
}
</style>
<?php
		$desc .= ob_get_contents();
		ob_end_clean();

		$contentopts = array(
			"desc" => BB_Translate("View the revision history for '%s'.", ($asset["langinfo"][$assetlang]["title"] != "" ? $asset["langinfo"][$assetlang]["title"] : BB_Translate("(Untitled)"))),
			"htmldesc" => $desc,
			"fields" => array(
			)
		);

		// Set up a reusable function for displaying string diffs.
		require_once $config["rootpath"] . "/support/str_diff.php";

		$nextcontextdiff = 1;
		function ViewAssetHistory_AddContextDiff(&$val, $newkey, $oldstr, $newstr, $displaystr, $displayarg, $extra = 2)
		{
			global $nextcontextdiff;

			$diff = StrDiff::Compare($oldstr, $newstr);

			$numinsert = 0;
			$numdelete = 0;
			$diff2 = "";
			$x = 0;
			$y = count($diff);
			$state = "unmodified";
			while ($x < $y)
			{
				for (; $x < $y && $diff[$x][1] !== StrDiff::UNMODIFIED && $diff[$x][1] !== StrDiff::DELETED && $diff[$x][1] !== StrDiff::INSERTED; $x++);

				if ($state === "unmodified")
				{
					for ($x2 = $x; $x2 < $y && $diff[$x2][1] === StrDiff::UNMODIFIED; $x2++);

					$x2 = ($x2 < $y ? $x2 - $extra : $y);
					if ($x < $x2)  $x = $x2;

					$state = "modified";
				}
				else
				{
					for (; $x < $y && $diff[$x][1] === StrDiff::UNMODIFIED; $x++)  $diff2 .= "<div class=\"contextlineitemwrap\"><div class=\"contextlineitem contextlineitem-gutter\"></div><div class=\"contextlineitem contextlineitem-unmodified\">\n" . str_replace(";", ";&#8203;", htmlspecialchars($diff[$x][0])) . "</div></div>\n";

					for (; $x < $y && $diff[$x][1] === StrDiff::DELETED; $x++)
					{
						$diff2 .= "<div class=\"contextlineitemwrap\"><div class=\"contextlineitem contextlineitem-gutter\">&ndash;</div><div class=\"contextlineitem contextlineitem-deleted\">\n" . str_replace(";", ";&#8203;", htmlspecialchars($diff[$x][0])) . "</div></div>\n";
						$numdelete++;
					}

					for (; $x < $y && $diff[$x][1] === StrDiff::INSERTED; $x++)
					{
						$diff2 .= "<div class=\"contextlineitemwrap\"><div class=\"contextlineitem contextlineitem-gutter\">+</div><div class=\"contextlineitem contextlineitem-inserted\">\n" . str_replace(";", ";&#8203;", htmlspecialchars($diff[$x][0])) . "</div></div>\n";
						$numinsert++;
					}

					$y2 = $x + $extra;

					for (; $x < $y && $x < $y2 && $diff[$x][1] === StrDiff::UNMODIFIED; $x++)  $diff2 .= "<div class=\"contextlineitemwrap\"><div class=\"contextlineitem contextlineitem-gutter\"></div><div class=\"contextlineitem contextlineitem-unmodified\">\n" . str_replace(";", ";&#8203;", htmlspecialchars($diff[$x][0])) . "</div></div>\n";

					$state = "unmodified";
				}
			}

			$disp = array();
			if ($numinsert > 1)  $disp[] = BB_Translate("%u lines added", $numinsert);
			else if ($numinsert == 1)  $disp[] = BB_Translate("One line added");

			if ($numdelete > 1)  $disp[] = BB_Translate("%u lines removed", $numdelete);
			else if ($numdelete == 1)  $disp[] = ($numinsert ? BB_Translate("one line removed") : BB_Translate("One line removed"));

			if ($numinsert || $numdelete)
			{
				$val[$newkey] = htmlspecialchars(BB_Translate($displaystr, implode(BB_Translate(" and "), $disp), $displayarg));
				$val[$newkey] .= " <a href=\"#\" onclick=\"$('#" . htmlspecialchars(BB_JSSafe("context_diff_" . $nextcontextdiff)) . "').show();  $(this).remove();  return false;\">" . htmlspecialchars(BB_Translate("Show")) . "</a>\n";
				$val[$newkey] .= "<div id=\"context_diff_" . $nextcontextdiff . "\" class=\"contextlineswrap\"><div class=\"contextlineswrapinner\">\n" . $diff2 . "</div></div>\n";

				$nextcontextdiff++;
			}
		}

		// Inject a loaded asset (if any) at the end of the list of revisions then loop over the revision list.
		$prevasset2 = false;
		if ($assetid > 0)  $result["revisions"][] = array("aid" => $assetid, "created" => $asset["lastupdated"], "info" => $asset, "revision" => 0);
		foreach ($result["revisions"] as $revision)
		{
			$asset2 = $cms->NormalizeAsset($revision["info"]);

			$lang = $cms->GetPreferredAssetLanguage($asset2, $assetlang, $_SESSION[$sessionkey]["api_info"]["default_lang"]);
			if ($lang === false)  continue;

			$val = array();
			$val["main_revision"] = "<a href=\"" . BB_GetRequestURLBase() . "?action=addeditasset&id=" . (int)$revision["aid"] . "&type=" . urlencode($asset2["type"]) . ((int)$revision["revision"] > 0 ? "&revision=" . (int)$revision["revision"] : "") . "&lang=" . urlencode($lang) . "&sec_t=" . BB_CreateSecurityToken("addeditasset") . "\">" . ((int)$revision["revision"] > 0 ? htmlspecialchars(BB_Translate("Revision %u", $revision["revision"])) : htmlspecialchars(BB_Translate("Current"))) . "</a>";

			// Compare the previous revision to the current revision and generate a human-readable diff.
			if ($prevasset2 !== false)
			{
				if ($prevasset2["type"] !== $asset2["type"])  $val["main_type"] = htmlspecialchars(BB_Translate("Asset type changed to '%s'.", $asset2["type"]));

				$diff = BB_GetIDDiff(array_flip($prevasset2["tags"]), array_flip($asset2["tags"]));
				if (count($diff["remove"]) > 1)  $val["main_tags_removed"] = htmlspecialchars(BB_Translate("Removed tags:  %s", implode("; ", array_keys($diff["remove"]))));
				else if (count($diff["remove"]) == 1)  $val["main_tags_removed"] = htmlspecialchars(BB_Translate("Removed tag:  %s", implode("; ", array_keys($diff["remove"]))));

				if (count($diff["add"]) > 1)  $val["main_tags_added"] = htmlspecialchars(BB_Translate("Added tags:  %s", implode("; ", array_keys($diff["add"]))));
				else if (count($diff["add"]) == 1)  $val["main_tags_added"] = htmlspecialchars(BB_Translate("Added tag:  %s", implode("; ", array_keys($diff["add"]))));

				if ($prevasset2["publish"] !== $asset2["publish"])
				{
					if ($asset2["publish"])  $val["main_publish"] = htmlspecialchars(BB_Translate("Publish date/time changed to %s %s.", BB_FormatTimestamp("Y-m-d", $asset2["publish"]), BB_FormatTimestamp("H:i", $asset2["publish"])));
					else  $val["main_publish"] = htmlspecialchars(BB_Translate("Asset reverted to Draft status."));
				}

				if ($prevasset2["unpublish"] !== $asset2["unpublish"])
				{
					if ($asset2["unpublish"])  $val["main_unpublish"] = htmlspecialchars(BB_Translate("Unpublish date/time changed to %s %s.", BB_FormatTimestamp("Y-m-d", $asset2["unpublish"]), BB_FormatTimestamp("H:i", $asset2["unpublish"])));
					else  $val["main_unpublish"] = htmlspecialchars(BB_Translate("Asset unpublish date/time removed."));
				}

				if ($prevasset2["uuid"] !== $asset2["uuid"])  $val["main_uuid"] = htmlspecialchars(BB_Translate("UUID changed to '%s'.", $asset2["uuid"]));

				$files = array();
				foreach ($prevasset2["files"] as $info)  $files[$info["filename"]] = BB_Translate("%s (%s)", $info["origfilename"], Str::ConvertBytesToUserStr($info["size"]));
				$files2 = array();
				foreach ($asset2["files"] as $info)  $files2[$info["filename"]] = BB_Translate("%s (%s)", $info["origfilename"], Str::ConvertBytesToUserStr($info["size"]));

				$diff = BB_GetIDDiff($files, $files2);
				if (count($diff["remove"]) > 1)  $val["main_files_removed"] = str_replace("_", "_&#8203;", htmlspecialchars(BB_Translate("Deleted files:  %s", implode("; ", $diff["remove"]))));
				else if (count($diff["remove"]) == 1)  $val["main_files_removed"] = str_replace("_", "_&#8203;", htmlspecialchars(BB_Translate("Deleted file '%s'.", implode("; ", $diff["remove"]))));

				if (count($diff["add"]) > 1)  $val["main_files_added"] = str_replace("_", "_&#8203;", htmlspecialchars(BB_Translate("Uploaded files:  %s", implode("; ", $diff["add"]))));
				else if (count($diff["add"]) == 1)  $val["main_files_added"] = str_replace("_", "_&#8203;", htmlspecialchars(BB_Translate("Uploaded file '%s'.", implode("; ", $diff["add"]))));

				$em->Fire("viewassethistory_process_pre_lang", array(&$prevasset2, &$asset2, &$val));

				$diff = BB_GetIDDiff($prevasset2["langinfo"], $asset2["langinfo"]);
				if (count($diff["remove"]) > 1)  $val["main_langs_removed"] = htmlspecialchars(BB_Translate("Removed languages:  %s", implode("; ", array_keys($diff["remove"]))));
				else if (count($diff["remove"]) == 1)  $val["main_langs_removed"] = htmlspecialchars(BB_Translate("Removed language '%s'.", implode("; ", array_keys($diff["remove"]))));

				if (count($diff["add"]) > 1)  $val["main_langs_added"] = htmlspecialchars(BB_Translate("Added languages:  %s", implode("; ", array_keys($diff["add"]))));
				else if (count($diff["add"]) == 1)  $val["main_langs_added"] = htmlspecialchars(BB_Translate("Added language '%s'.", implode("; ", array_keys($diff["add"]))));

				// Language-specific changes are last.
				foreach ($prevasset2["langinfo"] as $lang => $info)
				{
					if (isset($asset2["langinfo"][$lang]))
					{
						$info2 = $asset2["langinfo"][$lang];

						if ($info["title"] !== $info2["title"])  $val["main_lang_title_" . $lang] = htmlspecialchars(BB_Translate("Title for '%s' changed to '%s'.", $lang, $info2["title"]));

						// For built-in story assets, calculate a minimal diff of changes.
						if ($prevasset2["type"] === "story" && $asset2["type"] === "story")
						{
							ViewAssetHistory_AddContextDiff($val, "main_lang_body_" . $lang, $info["body"], $info2["body"], "%s in '%s'.", $lang);
						}

						$em->Fire("viewassethistory_process_langinfo", array(&$prevasset2, &$asset2, $lang, $info, $info2, &$val));
					}
				}
			}

			$em->Fire("viewassethistory_process_diff", array(&$prevasset2, &$asset2, &$val));

			if ($prevasset2 !== false && count($val) == 1 && isset($val["main_revision"]))  $val["main_no_changes"] = htmlspecialchars(BB_Translate("No significant changes."));

			$contentopts["fields"][] = array(
				"title" => ($prevasset2 === false || date("Y-m-d", $prevasset2["lastupdated"]) !== date("Y-m-d", $asset2["lastupdated"]) ? BB_FormatTimestamp("Y-m-d", $asset2["lastupdated"]) . " " : "") . BB_FormatTimestamp("H:i", $asset2["lastupdated"]),
				"type" => "custom",
				"value" => "<div class=\"staticwrap\">" . implode("<br>\n", $val) . "</div>",
				"htmldesc" => "<i>" . ($asset2["lastupdatedby"] != "" ? htmlspecialchars($asset2["lastupdatedby"]) : htmlspecialchars(BB_Translate("Unknown"))) . ($asset2["lastip"] != "" ? " / " . htmlspecialchars($asset2["lastip"]) : "") . "</i>"
			);

			$prevasset2 = $asset2;
		}

		$em->Fire("viewassethistory_contentopts", array(&$contentopts));

		BB_GeneratePage("View History", $menuopts, $contentopts);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "deleteassetfile")
	{
		// Delete a file from an asset.
		$em->Fire("deleteassetfile_init", array($assetid, &$asset));

		unset($_REQUEST["bb_msgtype"]);
		unset($_REQUEST["bb_msg"]);

		if (!$assetid || $revnum)  BB_RedirectPage("error", "No asset or attempting to manage files for a revision.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))  BB_RedirectPage("error", "Missing 'filename'.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($asset["filesinfo"][$_REQUEST["filename"]]))  BB_RedirectPage("error", "Invalid 'filename' specified.", array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));

		$result = $cms->DeleteUpload($assetid, $_REQUEST["filename"], $bb_username);

		$em->Fire("deleteassetfile_result", array($assetid, &$asset, &$result));

		if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Unable to delete the file.  %s (%s)", $result["error"], $result["errorcode"]), array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));

		BB_RedirectPage("success", "Successfully removed the file.", array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "removeassetdefaultcrop")
	{
		// Remove a saved crop for a specific file.
		$em->Fire("removeassetdefaultcrop_init", array($assetid, &$asset));

		unset($_REQUEST["bb_msgtype"]);
		unset($_REQUEST["bb_msg"]);

		if (!$assetid || $revnum)  BB_RedirectPage("error", "No asset or attempting to manage files for a revision.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))  BB_RedirectPage("error", "Missing 'filename'.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($_REQUEST["ratio"]) || !is_string($_REQUEST["ratio"]))  BB_RedirectPage("error", "Missing 'ratio'.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($asset["filesinfo"][$_REQUEST["filename"]]))  BB_RedirectPage("error", "Invalid 'filename' specified.", array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));

		if (isset($asset["filesinfo"][$_REQUEST["filename"]]["crops"]))
		{
			unset($asset["filesinfo"][$_REQUEST["filename"]]["crops"][$_REQUEST["ratio"]]);

			if (!count($asset["filesinfo"][$_REQUEST["filename"]]["crops"]))  unset($asset["filesinfo"][$_REQUEST["filename"]]["crops"]);
		}

		$result = $cms->StoreAsset($asset, false);

		$em->Fire("removeassetdefaultcrop_result", array($assetid, &$asset, &$result));

		if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Unable to remove the crop.  %s (%s)", $result["error"], $result["errorcode"]), array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));

		BB_RedirectPage("success", "Successfully removed the crop.", array("action=manageassetfiles&id=" . $assetid . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles")));
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "manageassetfiles")
	{
		// Manage files for an asset.
		$em->Fire("manageassetfiles_init", array($assetid, &$asset));

		if (!$assetid || $revnum)
		{
			unset($_REQUEST["bb_msgtype"]);
			unset($_REQUEST["bb_msg"]);

			BB_RedirectPage("error", "No asset or attempting to manage files for a revision.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		}

		$rows = array();
		foreach ($asset["files"] as $filename => $info)
		{
			$fileext = $cms->GetFileExtension($filename);
			$image = $cms->CanResizeImage($mimeinfomap, $fileext);

			$url = BB_GetRequestURLBase() . "?action=getfile&id=" . $assetid . "&path=" . urlencode($info["path"]) . "&filename=" . urlencode($info["filename"]) . "&sec_t=" . BB_CreateSecurityToken("getfile");

			$filelinks = array(
				"main_link" => "<a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\"" . (isset($mimeinfomap[$fileext]) && $mimeinfomap[$fileext]["preview"] ? ($image ? " data-preview-url=\"" . htmlspecialchars($url . "&maxwidth=800") . "\"" : "") . " data-preview-type=\"" . htmlspecialchars($mimeinfomap[$fileext]["type"]) . "\"" : "") . ">" . str_replace("_", "_&#8203;", htmlspecialchars($info["origfilename"])) . "</a>"
			);

			if ($image && isset($asset["filesinfo"][$filename]["crops"]))
			{
				$croplinks = array();
				$crops = $asset["filesinfo"][$filename]["crops"];
				ksort($crops, SORT_NATURAL);
				foreach ($crops as $ratio => $crop)
				{
					$crop2 = $crop["x"] . "," . $crop["y"] . "," . $crop["x2"] . "," . $crop["y2"];

					$croplinks[] = "<span class=\"croplinkwrap\"><a href=\"" . htmlspecialchars($url . "&maxwidth=800&crop=" . urlencode($crop2)) . "\" target=\"_blank\"" . (isset($mimeinfomap[$fileext]) && $mimeinfomap[$fileext]["preview"] ? " data-preview-type=\"" . htmlspecialchars($mimeinfomap[$fileext]["type"]) . "\"" : "") . ">" . htmlspecialchars(($ratio != "" ? BB_Translate("%s crop", $ratio) : BB_Translate("Default crop"))) . "</a><a class=\"deletecrop\" href=\"" . BB_GetRequestURLBase() . "?action=removeassetdefaultcrop&id=" . $assetid . "&filename=" . urlencode($info["filename"]) . "&ratio=" . urlencode($ratio) . "&sec_t=" . BB_CreateSecurityToken("removeassetdefaultcrop") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Removing a crop does not affect existing assets using the crop.  Are you sure you want to remove this crop?"))) . "');\" title=\"" . htmlspecialchars(BB_Translate("Delete crop")) . "\">&times;</a></span>";
				}

				if (count($croplinks))  $filelinks["main_crops"] = implode("", $croplinks);
			}

			$em->Fire("manageassetfiles_filelinks", array($assetid, &$info, &$filelinks));

			$row = array(implode("<br>", $filelinks), Str::ConvertBytesToUserStr($info["size"]), htmlspecialchars(BB_FormatTimestamp("Y-m-d", $info["modified"]) . " " . BB_FormatTimestamp("H:i", $info["modified"])));

			$options = array(
				"main_download" => "<a href=\"" . htmlspecialchars($url . "&download=" . urlencode($info["origfilename"])) . "\">" . htmlspecialchars(BB_Translate("Download")) . "</a>",
				"main_delete" => "<a href=\"" . BB_GetRequestURLBase() . "?action=deleteassetfile&id=" . $assetid . "&filename=" . urlencode($info["filename"]) . "&sec_t=" . BB_CreateSecurityToken("deleteassetfile") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Deleting a file is a permanent operation.  Are you sure you want to delete this file?"))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>",
			);

			$em->Fire("manageassetfiles_process_file", array($assetid, &$info, &$row, &$options));

			$row[] = implode("&nbsp;| ", $options);

			$rows[] = $row;
		}

		$desc = "<br>";
		ob_start();
?>
<style type="text/css">
.croplinkwrap { display: inline-block; white-space: nowrap; margin-top: 0.3em; margin-right: 0.5em; border: 1px solid #9ACFEA; border-radius: 3px; padding: 0.2em 0.5em; background-color: #D9EDF7; color: #31708F; font-size: 0.9em; }
.croplinkwrap a.deletecrop, .croplinkwrap a.deletecrop:hover, .croplinkwrap a.deletecrop:visited, .croplinkwrap a.deletecrop:link { display: inline-block; margin-left: 0.5em; border-left: 1px solid #9ACFEA; padding-left: 0.5em; color: #A94442; }
.croplinkwrap a.deletecrop:hover { color: #BF4D4B; }
.nowrap { white-space: nowrap; }
</style>
<?php
		$desc .= ob_get_contents();
		ob_end_clean();

		require_once $config["rootpath"] . "/support/flex_forms_previewurl.php";

		$contentopts = array(
			"desc" => BB_Translate("Manage the files for '%s'.", ($asset["langinfo"][$assetlang]["title"] != "" ? $asset["langinfo"][$assetlang]["title"] : BB_Translate("(Untitled)"))),
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"type" => "table",
					"cols" => array("File", "Size", "Uploaded", "Options"),
					"nowrap" => array("Size", "Uploaded"),
					"rows" => $rows,
					"card" => array(
						"width" => 700,
						"head" => "Info/Options",
						"body" => "<b>%1</b><br><span class=\"nowrap\">%2 |</span> <span class=\"nowrap\">%3</span><br>%4"
					),
					"previewurl" => true
				)
			)
		);

		$em->Fire("manageassetfiles_contentopts", array(&$contentopts));

		BB_GeneratePage("Manage Files", $menuopts, $contentopts);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "deleteasset")
	{
		// Deletes an asset.  Can be recovered by viewing history by asset ID, editing a revision, and saving.
		$em->Fire("deleteasset_init", array($assetid, &$asset));

		$result = $cms->DeleteAsset($assetid);

		$em->Fire("deleteasset_result", array(&$result));

		$retopts = array();
		foreach ($_GET as $key => $val)
		{
			if ($key !== "action" && $key !== "id" && $key !== "sec_t" && is_string($val))  $retopts[] = urlencode($key) . "=" . urlencode($val);
		}
		$retopts = implode("&", $retopts);
		if ($retopts !== "")  $retopts = "&" . $retopts;

		unset($_REQUEST["bb_msgtype"]);
		unset($_REQUEST["bb_msg"]);

		if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Unable to delete the asset.  %s (%s)", $result["error"], $result["errorcode"]), array("action=listassets" . $retopts . "&sec_t=" . BB_CreateSecurityToken("listassets")));

		BB_RedirectPage("success", "Successfully deleted the asset.", array("action=listassets" . $retopts . "&sec_t=" . BB_CreateSecurityToken("listassets")));
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "listassets")
	{
		// Lists assets by most recently updated.  Also used by Find Assets for search results.
		$em->Fire("listassets_init", array());

		$rows = array();
		$ts = time();
		$retopts = array();

		// Specifies supported edit type targets.
		$edittypes = array();
		$em->Fire("listassets_types", array(&$types));

		// Callback function for bulk processing of assets.
		function ListAssets_ProcessAsset($result)
		{
			global $em, $cms, $assetlang, $sessionkey, $rows, $ts, $edittypes, $retopts;

			if (!$result["success"] || !isset($result["asset"]))  return;

			$asset = $cms->NormalizeAsset($result["asset"]);

			$lang = $cms->GetPreferredAssetLanguage($asset, $assetlang, $_SESSION[$sessionkey]["api_info"]["default_lang"]);
			if ($lang === false)  return;

			if ($asset["publish"] == 0)  $status = "<span class=\"draft\">&bull; " . htmlspecialchars(BB_Translate("Draft - %s at %s", BB_FormatTimestamp("Y-m-d", $asset["lastupdated"]), BB_FormatTimestamp("H:i", $asset["lastupdated"]))) . "</span>";
			else if ($asset["publish"] > $ts)  $status = "<span class=\"future-publish\">&#x23F3; " . htmlspecialchars(BB_Translate("Publishes %s at %s", BB_FormatTimestamp("Y-m-d", $asset["publish"]), BB_FormatTimestamp("H:i", $asset["publish"]))) . "</span>";
			else if ($asset["unpublish"] > $ts)  $status = "<span class=\"published future-unpublish\">&#x2714; " . htmlspecialchars(BB_Translate("Unpublishes %s at %s", BB_FormatTimestamp("Y-m-d", $asset["unpublish"]), BB_FormatTimestamp("H:i", $asset["unpublish"]))) . "</span>";
			else if ($asset["unpublish"] > 0 && $asset["unpublish"] <= $ts)  $status = "<span class=\"unpublished\">&times; " . htmlspecialchars(BB_Translate("Unpublished %s at %s", BB_FormatTimestamp("Y-m-d", $asset["unpublish"]), BB_FormatTimestamp("H:i", $asset["unpublish"]))) . "</span>";
			else  $status = "<span class=\"published\">&#x2714; " . htmlspecialchars(BB_Translate("Published %s at %s", BB_FormatTimestamp("Y-m-d", $asset["publish"]), BB_FormatTimestamp("H:i", $asset["publish"]))) . "</span>";

			$row = array("<a href=\"" . BB_GetRequestURLBase() . "?action=addeditasset&id=" . (int)$asset["id"] . "&type=story&lang=" . htmlspecialchars(urlencode($lang)) . "&sec_t=" . BB_CreateSecurityToken("addeditasset") . "\"" . (isset($edittypes[$asset["type"]]) ? $edittypes[$asset["type"]] : "") . ">" . htmlspecialchars($asset["langinfo"][$lang]["title"] != "" ? $asset["langinfo"][$lang]["title"] : BB_Translate("(Untitled)")) . "</a>", $status);

			$options = array(
			);

			$options["main_history"] = "<a href=\"" . BB_GetRequestURLBase() . "?action=viewassethistory&id=" . (int)$asset["id"] . "&sec_t=" . BB_CreateSecurityToken("viewassethistory") . "\">" . htmlspecialchars(BB_Translate("History")) . "</a>";
			if (count($asset["files"]))  $options["main_files"] = "<a href=\"" . BB_GetRequestURLBase() . "?action=manageassetfiles&id=" . (int)$asset["id"] . "&sec_t=" . BB_CreateSecurityToken("manageassetfiles") . "\">" . htmlspecialchars(BB_Translate("Files")) . "</a>";
			$options["main_delete"] = "<a href=\"" . BB_GetRequestURLBase() . "?action=deleteasset&id=" . (int)$asset["id"] . $retopts . "&sec_t=" . BB_CreateSecurityToken("deleteasset") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Unpublishing is usually a better option over deleting an asset.  Are you sure you want to delete this asset?"))) . "');\">" . htmlspecialchars(BB_Translate("Delete")) . "</a>";

			$em->Fire("listassets_process_asset", array(&$asset, $lang, &$row, &$options));

			$row[] = implode("&nbsp;| ", $options);

			$rows[] = $row;
		}

		$extras = array();
		if (isset($_REQUEST["limit"]))
		{
			$limit = (int)$_REQUEST["limit"];

			if (!$limit)
			{
				// Search drafts.
				$options = array("start" => 0, "end" => 0);
				$extras[] = htmlspecialchars(BB_Translate("Drafts only."));
			}
			else if ($limit < 0)
			{
				// Search all assets.  Probably fairly slow with large databases.
				$options = array();
			}
			else
			{
				// Published in the last 'x' days.
				$options = array("start" => time() - ($limit * 24 * 60 * 60));
				$extras[] = htmlspecialchars(BB_Translate("Published in the last %u days.", $limit));
			}

			$retopts[] = "limit=" . $limit;
		}
		else
		{
			$options = array();
		}

		if (isset($_REQUEST["type"]) && is_string($_REQUEST["type"]) && $_REQUEST["type"] != "")
		{
			$options["type"] = $_REQUEST["type"];
			$retopts[] = "type=" . urlencode($_REQUEST["type"]);

			$extras[] = htmlspecialchars(BB_Translate("Assets with type '%s'.", $_REQUEST["type"]));
		}

		if (isset($_REQUEST["search"]) && is_string($_REQUEST["search"]) && $_REQUEST["search"] != "")
		{
			// Set search parameters.
			$options["q"] = $_REQUEST["search"];
			$retopts[] = "search=" . urlencode($_REQUEST["search"]);

			$title = "Find Assets";
			$maindesc = BB_Translate("Search results for '%s'.", $_REQUEST["search"]);
		}
		else
		{
			$title = "List Assets";
			$maindesc = "Recently updated assets.";
		}

		$options["order"] = "lastupdated";
		$limit = 300;

		$retopts = implode("&", $retopts);
		if ($retopts !== "")  $retopts = "&" . $retopts;

		// Let plugins have a chance to update the query.
		$em->Fire("listassets_query", array(&$options, &$limit, &$extras));

		$cms->GetAssets($options, $limit, "ListAssets_ProcessAsset");

		$desc = "<br>";
		if (count($extras))  $desc .= "<ul><li>" . implode("</li><li>", $extras) . "</li></ul>";
		ob_start();
?>
<style type="text/css">
.draft { font-style: italic; }
.draft::first-letter { font-style: normal; }
.future-publish { font-style: italic; }
.future-publish::first-letter { font-style: normal; }
.published { }
.published::first-letter { font-style: normal; color: #3C763D; }
.future-unpublish { }
.unpublished { font-style: italic; }
.unpublished::first-letter { font-style: normal; color: #A94442; }
.nowrap { white-space: nowrap; }
</style>
<?php
		$desc .= ob_get_contents();
		ob_end_clean();

		$contentopts = array(
			"desc" => $maindesc,
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"type" => "table",
					"cols" => array("Title", "Status", "Options"),
					"nowrap" => "Status",
					"rows" => $rows,
					"card" => array(
						"width" => 765,
						"head" => "Info/Options",
						"body" => "<b>%1</b><br><span class=\"nowrap\">%2</span><br>%3"
					)
				)
			)
		);

		$em->Fire("listassets_contentopts", array(&$contentopts));

		BB_GeneratePage($title, $menuopts, $contentopts);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "findassets")
	{
		// Find Assets.  List Assets does the heavy lifting.
		$em->Fire("findassets_init", array());

		if (isset($_REQUEST["search"]))
		{
			if (BB_GetPageMessageType() != "error")
			{
				$redirect = "action=listassets&search=" . urlencode($_REQUEST["search"]) . "&limit=" . (int)$_REQUEST["limit"] . "&type=" . urlencode($_REQUEST["type"]) . "&sec_t=" . BB_CreateSecurityToken("listassets");

				$em->Fire("findassets_redirect", array(&$redirect));

				BB_RedirectPage("", "", array($redirect));
			}
		}

		$contentopts = array(
			"desc" => "Search for assets.",
			"fields" => array(
				array(
					"title" => "Search Terms",
					"width" => "38em",
					"type" => "text",
					"name" => "search",
					"default" => ""
				),
				"startrow",
				array(
					"title" => "Limit",
					"width" => "19em",
					"type" => "select",
					"name" => "limit",
					"options" => array("7" => "7 days", "14" => "14 days", "31" => "31 days", "-1" => "All", "0" => "Drafts"),
					"default" => "7"
				),
				array(
					"title" => "Type",
					"width" => "18em",
					"type" => "select",
					"name" => "type",
					"options" => array("" => "All", "story" => "Story"),
					"default" => ""
				),
				"endrow",
			),
			"submit" => "Search"
		);

		$em->Fire("findassets_contentopts", array(&$contentopts));

		BB_GeneratePage("Find Assets", $menuopts, $contentopts);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "findtags")
	{
		// For AJAX calls to retrieve a set of tags (e.g. jQuery UI autocomplete for a field).
		$em->Fire("findtags_init", array());

		$result = $cms->GetTags(array("tag" => "~" . $_REQUEST["tag"]), 25);
		if ($result["success"])  $result["tags"] = array_keys($result["tags"]);

		sort($result["tags"], SORT_NATURAL);

		$em->Fire("findtags_result", array(&$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "findassetfiles")
	{
		// For AJAX calls to retrieve a set of assets containing files that match input search criteria.
		$em->Fire("findassetfiles_init", array());

		if (isset($_REQUEST["search"]) && is_string($_REQUEST["search"]) && $_REQUEST["search"] != "" && isset($_REQUEST["limit"]))
		{
			$limit = (int)$_REQUEST["limit"];

			if (!$limit)
			{
				// Search drafts.
				$options = array("start" => 0, "end" => 0);
			}
			else if ($limit < 0)
			{
				// Search all assets.  Probably fairly slow with large databases.
				$options = array();
			}
			else
			{
				// Published in the last 'x' days.
				$options = array("start" => time() - ($limit * 24 * 60 * 60));
			}

			// Set search parameters.
			$options["q"] = $_REQUEST["search"];

			// Exclude assets that don't have any files.
			$options["qe"] = "!\"files\":[]";

			$result2 = $cms->GetAssets($options, 50);
			if (!$result2["success"])  $result = $result2;
			else
			{
				$result = array("success" => true, "assets" => array());

				foreach ($result2["assets"] as $asset2)
				{
					$asset2 = $cms->NormalizeAsset($asset2);

					$lang = $cms->GetPreferredAssetLanguage($asset2, $assetlang, $_SESSION[$sessionkey]["api_info"]["default_lang"]);

					$title = ($lang !== false ? $asset2["langinfo"][$lang]["title"] : BB_Translate("(Untitled)"));

					$result["assets"][] = array("id" => $asset2["id"], "title" => $title, "files" => $asset2["files"], "filesinfo" => $asset2["filesinfo"]);
				}
			}
		}
		else if ($assetid > 0)
		{
			$result = array("success" => true, "assets" => array());
			$title = $asset["langinfo"][$assetlang]["title"];

			$result["assets"][] = array("id" => $assetid, "title" => $title, "files" => $asset["files"], "filesinfo" => $asset["filesinfo"]);
		}
		else
		{
			$result = array("success" => false, "error" => BB_Translate("Bad request.  Missing 'search', 'limit', or 'id'."), "errorcode" => "missing_search_limit_id");
		}

		$em->Fire("findassetfiles_result", array(&$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "uploadassetfile")
	{
		// For AJAX calls using FlexForms File Uploader.
		$em->Fire("uploadassetfile_init", array($assetid, &$asset));

		if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))  $result = array("success" => false, "error" => BB_Translate("Missing 'filename'."), "errorcode" => "missing_filename");
		else if ($assetid > 0 && !$revnum)
		{
			require_once $config["rootpath"] . "/support/flex_forms_fileuploader.php";

			$chunked = $_SESSION[$sessionkey]["api_info"]["chunked_uploads"];

			// Calculate the maximum upload limit for both hosts.
			$apiuploadsize = $_SESSION[$sessionkey]["api_info"]["max_chunk_size"];
			if ($apiuploadsize < 1)  $apiuploadsize = ($chunked ? 1048576 : 1073741824);

			$uploadsize = FlexForms_FileUploader::GetMaxUploadFileSize();
			if ($uploadsize < 1)  $uploadsize = ($chunked ? 1048576 : 1073741824);

			$chunksize = floor(min($apiuploadsize, $uploadsize));

			if (isset($_REQUEST["fileuploader"]))
			{
				$files = BB_NormalizeFiles("file");
				if (!isset($files[0]))  $result = array("success" => false, "error" => BB_Translate("File data was submitted but is missing."), "errorcode" => "bad_input");
				else if (!$files[0]["success"])  $result = $files[0];
				else
				{
					$start = FlexForms_FileUploader::GetFileStartPosition();
					$data = file_get_contents($files[0]["file"]);

					if (!$chunked && $start)  $result = array("success" => false, "error" => BB_Translate("A chunked upload was requested but the destination does not support chunked uploads."), "errorcode" => "chunked_uploads_not_supported");
					else if (strlen($data) > $chunksize)  $result = array("success" => false, "error" => BB_Translate("File data was submitted but is too large.  Maximum allowed size is %u bytes.", $chunksize), "errorcode" => "file_too_large");
					else  $result = $cms->UploadFile($assetid, $_REQUEST["filename"], $start, $data);
				}
			}
			else if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] === "start")
			{
				if (!isset($_REQUEST["filesize"]) || !is_numeric($_REQUEST["filesize"]))  $result = array("success" => false, "error" => BB_Translate("Missing 'filesize'."), "errorcode" => "missing_filesize");
				else if (!$chunked && $_REQUEST["filesize"] > $chunksize)  $result = array("success" => false, "error" => BB_Translate("File is too large to process.  Maximum allowed size is %u bytes.", $chunksize), "errorcode" => "file_too_large");
				else  $result = $cms->StartUpload($assetid, $_REQUEST["filename"], $bb_username, ($bb_username !== $asset["lastupdatedby"]));
			}
			else if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] === "cancel")
			{
				$result = $cms->DeleteUpload($assetid, $_REQUEST["filename"]);
			}
			else if (isset($_REQUEST["mode"]) && $_REQUEST["mode"] === "done")
			{
				$result = $cms->UploadDone($assetid, $_REQUEST["filename"]);
			}
			else
			{
				$result = array("success" => false, "error" => BB_Translate("Bad request.  Missing 'fileuploader' or a 'mode' of 'start', 'cancel', or 'done'."), "errorcode" => "missing_fileuploader_mode");
			}
		}
		else
		{
			$result = array("success" => false, "error" => BB_Translate("Bad request.  No asset ID or attempting to upload to a revision."), "errorcode" => "missing_valid_asset_id");
		}

		$em->Fire("uploadassetfile_result", array(&$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "getfile")
	{
		// Get a file via the API, optionally size and crop it (images only), and return the result to the caller.
		$em->Fire("getfile_init", array());

		if (!isset($_REQUEST["id"]) || !is_string($_REQUEST["id"]))
		{
			http_response_code(404);

			echo "Missing asset ID.";

			exit();
		}

		if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))
		{
			http_response_code(404);

			echo "Missing 'filename'.";

			exit();
		}

		$options = array(
			"cachedir" => $config["rootpath"] . "/files",
			"path" => (isset($_REQUEST["path"]) && is_string($_REQUEST["path"]) ? $_REQUEST["path"] : ""),
			"download" => (isset($_REQUEST["download"]) && is_string($_REQUEST["download"]) ? $_REQUEST["download"] : false),
			"maxwidth" => (isset($_REQUEST["maxwidth"]) && is_numeric($_REQUEST["maxwidth"]) ? (int)$_REQUEST["maxwidth"] : -1),
			"crop" => (isset($_REQUEST["crop"]) && is_string($_REQUEST["crop"]) ? $_REQUEST["crop"] : ""),
			"mimeinfomap" => $mimeinfomap
		);

		$em->Fire("getfile_options", array($_REQUEST["id"], $_REQUEST["filename"], &$options));

		// Prevent session locking.
		@session_write_close();

		// Remove session-related headers so that content will cache.
		header_remove("Cache-Control");
		header_remove("Expires");
		header_remove("Pragma");

		$cms->DeliverFile($_REQUEST["id"], $_REQUEST["filename"], $options);
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "setassetdefaultcrop")
	{
		// For AJAX calls to set a default crop ratio for a specific file in an asset.
		$em->Fire("setassetdefaultcrop_init", array($assetid, &$asset));

		if (!isset($_REQUEST["filename"]) || !is_string($_REQUEST["filename"]))  $result = array("success" => false, "error" => BB_Translate("Missing 'filename'."), "errorcode" => "missing_filename");
		else if (!isset($_REQUEST["ratio"]) || !is_string($_REQUEST["ratio"]))  $result = array("success" => false, "error" => BB_Translate("Missing 'ratio'."), "errorcode" => "missing_ratio");
		else if (!isset($_REQUEST["crop_x"]) || !is_numeric($_REQUEST["crop_x"]) || $_REQUEST["crop_x"] < 0)  $result = array("success" => false, "error" => BB_Translate("Missing 'crop_x'."), "errorcode" => "missing_crop_x");
		else if (!isset($_REQUEST["crop_y"]) || !is_numeric($_REQUEST["crop_y"]) || $_REQUEST["crop_y"] < 0)  $result = array("success" => false, "error" => BB_Translate("Missing 'crop_y'."), "errorcode" => "missing_crop_y");
		else if (!isset($_REQUEST["crop_x2"]) || !is_numeric($_REQUEST["crop_x2"]) || $_REQUEST["crop_x2"] < 0)  $result = array("success" => false, "error" => BB_Translate("Missing 'crop_x2'."), "errorcode" => "missing_crop_x2");
		else if (!isset($_REQUEST["crop_y2"]) || !is_numeric($_REQUEST["crop_y2"]) || $_REQUEST["crop_y2"] < 0)  $result = array("success" => false, "error" => BB_Translate("Missing 'crop_y2'."), "errorcode" => "missing_crop_y2");
		else if ($assetid > 0 && !$revnum)
		{
			if (!isset($asset["filesinfo"][$_REQUEST["filename"]]))  $result = array("success" => false, "error" => BB_Translate("Invalid 'filename'."), "errorcode" => "invalid_filename");
			else
			{
				if (!isset($asset["filesinfo"][$_REQUEST["filename"]]["crops"]))  $asset["filesinfo"][$_REQUEST["filename"]]["crops"] = array();

				$asset["filesinfo"][$_REQUEST["filename"]]["crops"][$_REQUEST["ratio"]] = array(
					"x" => (double)$_REQUEST["crop_x"],
					"y" => (double)$_REQUEST["crop_y"],
					"x2" => (double)$_REQUEST["crop_x2"],
					"y2" => (double)$_REQUEST["crop_y2"]
				);

				$result = $cms->StoreAsset($asset, false);
			}
		}
		else
		{
			$result = array("success" => false, "error" => BB_Translate("Bad request.  No asset ID or attempting to modify a revision."), "errorcode" => "missing_valid_asset_id");
		}

		$em->Fire("setassetdefaultcrop_result", array(&$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "editassetstart")
	{
		// For AJAX calls when starting to edit an asset.
		$em->Fire("editassetstart_init", array($assetid, &$asset));

		if ($assetid > 0 && !$revnum && $bb_username != "")
		{
			$asset["curruser"] = $bb_username;

			$result = $cms->StoreAsset($asset, false);
		}
		else
		{
			$result = array("success" => true);
		}

		$em->Fire("editassetstart_result", array($assetid, &$asset, &$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "editassetend")
	{
		// For AJAX calls when no longer editing an asset (usually the edit was cancelled).
		$em->Fire("editassetend_init", array($assetid, &$asset));

		if ($assetid > 0 && !$revnum && isset($asset["curruser"]))
		{
			unset($asset["curruser"]);

			$result = $cms->StoreAsset($asset, false);
		}
		else
		{
			$result = array("success" => true);
		}

		$em->Fire("editassetend_result", array($assetid, &$asset, &$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && isset($_REQUEST["type"]) && $_REQUEST["action"] == "saveasset")
	{
		// For AJAX calls to save an asset.
		$em->Fire("saveasset_init", array($assetid, &$asset));

		// Resolve language issues.
		if (isset($_REQUEST["origlang"]) && is_string($_REQUEST["origlang"]) && $_REQUEST["origlang"] !== "" && isset($asset["langinfo"][$_REQUEST["origlang"]]))
		{
			$asset["langinfo"][$assetlang] = $asset["langinfo"][$_REQUEST["origlang"]];
		}

		$em->Fire("saveasset_lang", array($assetid, &$asset));

		// Merge submitted content into the asset.
		if (isset($_REQUEST["title"]) && is_string($_REQUEST["title"]))
		{
			if ($_REQUEST["title"] === "")  $_REQUEST["title"] = BB_Translate("(Untitled)");

			$asset["langinfo"][$assetlang]["title"] = $_REQUEST["title"];
		}

		if (isset($_REQUEST["body"]) && is_string($_REQUEST["body"]))
		{
			// Traverse story asset body looking for tags with JSON encoded 'data-src-info' attributes.
			if ($_REQUEST["type"] === "story")
			{
				function SaveAsset_TransformStoryAssetCallback($stack, &$content, $open, $tagname, &$attrs, $options)
				{
					global $em;

					if ($open && isset($attrs["data-src-info"]))
					{
						$data = @json_decode($attrs["data-src-info"], true);
						if (!is_array($data))  unset($attrs["data-src-info"]);
						else
						{
							foreach ($options["process_attrs"] as $attr => $type)
							{
								if ($type === "uri" && isset($attrs[$attr]))  $attrs[$attr] = "//0.0.0.0/transform.gif";
							}

							$em->Fire("saveasset_transformstoryasset_datasrc", array($tagname, &$attrs, $data));
						}
					}

					$results = $em->Fire("saveasset_transformstoryasset", array($stack, &$content, $open, $tagname, &$attrs, $options));

					$result = array();
					foreach ($results as $result2)
					{
						$result = array_replace($result, $result2);
					}

					// Drop 'width' and 'height' attributes on images.  Use CSS classes instead.
					if ($tagname === "img")
					{
						unset($attrs["width"]);
						unset($attrs["height"]);
					}

					// Remove transform error attributes.
					unset($attrs["data-transform-error"]);

					return $result;
				}

				require_once $config["rootpath"] . "/support/tag_filter.php";

				$htmloptions = TagFilter::GetHTMLOptions();
				$htmloptions["tag_callback"] = "SaveAsset_TransformStoryAssetCallback";

				$_REQUEST["body"] = TagFilter::Run($_REQUEST["body"], $htmloptions);
			}

			$asset["langinfo"][$assetlang]["body"] = $_REQUEST["body"];
		}

		if (isset($_REQUEST["tags"]))
		{
			if (!is_array($_REQUEST["tags"]))  $_REQUEST["tags"] = array();

			$asset["tags"] = $_REQUEST["tags"];
		}

		if (isset($_REQUEST["publish_date"]) && is_string($_REQUEST["publish_date"]) && isset($_REQUEST["publish_time"]) && is_string($_REQUEST["publish_time"]))
		{
			$ts = FlexFormsExtras::ParseDateTime($_REQUEST["publish_date"], $_REQUEST["publish_time"]);
			if ($ts !== false)
			{
				// Have the API recalculate the UUID whenever the asset goes from unpublished to published.
				if (!$asset["publish"])  unset($asset["uuid"]);

				$asset["publish"] = $ts;
			}
		}

		if (isset($_REQUEST["unpublish_date"]) && is_string($_REQUEST["unpublish_date"]) && isset($_REQUEST["unpublish_time"]) && is_string($_REQUEST["unpublish_time"]))
		{
			$ts = FlexFormsExtras::ParseDateTime($_REQUEST["unpublish_date"], $_REQUEST["unpublish_time"]);
			if ($ts !== false)  $asset["unpublish"] = $ts;
		}

		// Set last updated user for revision history tracking.
		unset($asset["curruser"]);
		$asset["lastupdatedby"] = $bb_username;

		$em->Fire("saveasset_ready", array($assetid, &$asset));

		// Save the asset.
		$result = $cms->StoreAsset($asset);

		$em->Fire("saveasset_result", array($assetid, &$asset, &$result));

		header("Content-Type: application/json");

		echo json_encode($result, JSON_UNESCAPED_SLASHES);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "removeassetlang")
	{
		// Removes a language/translation from an asset.
		$em->Fire("removeassetlang_init", array($assetid, &$asset));

		unset($_REQUEST["bb_msgtype"]);
		unset($_REQUEST["bb_msg"]);

		if (!$assetid || $revnum)  BB_RedirectPage("error", "No asset or attempting to remove a language from a revision.", array("action=listassets&sec_t=" . BB_CreateSecurityToken("listassets")));
		if (!isset($_REQUEST["retlang"]) || !is_string($_REQUEST["retlang"]))  BB_RedirectPage("error", "Missing 'retlang'.", array("action=addeditasset&id=" . $assetid . "&type=" . urlencode($asset["type"]) . "&lang=" . urlencode($assetlang) . "&sec_t=" . BB_CreateSecurityToken("addeditasset")));
		if (!isset($_REQUEST["lang"]) || !is_string($_REQUEST["lang"]))  BB_RedirectPage("error", "Missing 'lang'.", array("action=addeditasset&id=" . $assetid . "&type=" . urlencode($asset["type"]) . "&lang=" . urlencode($_REQUEST["retlang"]) . "&sec_t=" . BB_CreateSecurityToken("addeditasset")));

		// Remove the language.
		unset($asset["langinfo"][$_REQUEST["lang"]]);

		// Set last updated user for revision history tracking.
		$curruser = (isset($asset["curruser"]) ? $asset["curruser"] : false);
		unset($asset["curruser"]);
		$asset["lastupdatedby"] = $bb_username;

		// Save the asset.
		$result = $cms->StoreAsset($asset);

		$em->Fire("removeassetlang_result", array($assetid, &$asset, &$result));

		if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Unable to remove the language from the asset.  %s (%s)", $result["error"], $result["errorcode"]), array("action=addeditasset&id=" . $assetid . "&type=" . urlencode($asset["type"]) . "&lang=" . urlencode($_REQUEST["retlang"]) . "&sec_t=" . BB_CreateSecurityToken("addeditasset")));

		// Restore the current user.
		if ($result["success"] && $curruser !== false)
		{
			$asset["curruser"] = $curruser;

			$result = $cms->StoreAsset($asset, false);

			$em->Fire("removeassetlang_result2", array($assetid, &$asset, &$result));

			if (!$result["success"])  BB_RedirectPage("error", BB_Translate("Successfully removed the language from the asset but was unable to restore the current user to the asset.  %s (%s)", $result["error"], $result["errorcode"]), array("action=addeditasset&id=" . $assetid . "&type=" . urlencode($asset["type"]) . "&lang=" . urlencode($_REQUEST["retlang"]) . "&sec_t=" . BB_CreateSecurityToken("addeditasset")));
		}

		BB_RedirectPage("success", "Successfully removed the language from the asset.", array("action=addeditasset&id=" . $assetid . "&type=" . urlencode($asset["type"]) . "&lang=" . urlencode($_REQUEST["retlang"]) . "&sec_t=" . BB_CreateSecurityToken("addeditasset")));
	}
	else if (isset($_REQUEST["action"]) && isset($_REQUEST["type"]) && $_REQUEST["action"] == "addeditasset" && $_REQUEST["type"] == "story")
	{
		// Displays the built-in add/edit story content editor.
		$em->Fire("addeditasset_story_init", array($assetid, &$asset));

		if ($revnum)  BB_SetPageMessage("info", BB_Translate("Viewing revision %d of this story.", $revnum));
		else if (isset($asset["curruser"]) && $asset["curruser"] !== $bb_username)  BB_SetPageMessage("info", BB_Translate("%s is currently editing this story.", $asset["curruser"]));

		require_once $config["rootpath"] . "/support/flex_forms_textcounter.php";
		require_once $config["rootpath"] . "/support/flex_forms_fileuploader.php";

		// Merge tags.
		$tags = $_SESSION[$sessionkey]["recent_tags"];
		foreach ($asset["tags"] as $tag)  $tags[$tag] = $tag;
		ksort($tags);
		$_SESSION[$sessionkey]["recent_tags"] = $tags;

		// Generate correct URLs.
		$options = array(
			"processdivembed" => false,
			"keepsrcinfo" => true,
			"usedatamaxwidth" => false,
			"maxwidth" => 400,
			"mimeinfomap" => $mimeinfomap,
			"getfileurl" => BB_GetRequestURLBase() . "?action=getfile&sec_t=" . BB_CreateSecurityToken("getfile"),
			"em" => $em,
			"emfire" => "addeditasset_story_transform"
		);

		$asset = $cms->TransformStoryAssetBody($asset, $options, $assetlang);

		// Force early jQuery UI output.
		$desc = "<br>";
		ob_start();
		$bb_flexforms->SetState(array("jqueryuitheme" => "adminpack"));
		$bb_flexforms->SetJSOutput("jquery");
		$bb_flexforms->OutputJQueryUI();
?>
<noscript><h3 class="propmessagewrap propmessageerror">This page requires Javascript to be enabled to function properly.</h3></noscript>

<link rel="stylesheet" href="support/content-tools/content-tools.min.css" type="text/css" media="all" />
<link rel="stylesheet" href="support/content-tools/barebones.css" type="text/css" media="all" />
<?php
		$em->Fire("addeditasset_story_css", array());

		// Available language options list.
		$options = array();
		foreach ($asset["langinfo"] as $lang => &$info)
		{
			if ($lang !== $assetlang)
			{
				if ($revnum)  $options[] = "<a href=\"" . BB_GetRequestURLBase() . "?action=addeditasset&id=" . $assetid . "&revision=" . $revnum . "&type=story&lang=" . htmlspecialchars(urlencode($lang)) . "&sec_t=" . BB_CreateSecurityToken("addeditasset") . "\" style=\"white-space: nowrap;\">" . htmlspecialchars(BB_Translate("Edit %s", $lang)) . "</a>";
				else  $options[] = "<span class=\"langlinkwrap\"><a href=\"" . BB_GetRequestURLBase() . "?action=addeditasset&id=" . $assetid . "&type=story&lang=" . htmlspecialchars(urlencode($lang)) . "&sec_t=" . BB_CreateSecurityToken("addeditasset") . "\">" . htmlspecialchars(BB_Translate("Edit %s", $lang)) . "</a><a class=\"deletelang\" href=\"" . BB_GetRequestURLBase() . "?action=removeassetlang&id=" . $assetid . "&lang=" . urlencode($lang) . "&retlang=" . urlencode($assetlang) . "&sec_t=" . BB_CreateSecurityToken("removeassetlang") . "\" onclick=\"return confirm('" . htmlspecialchars(BB_JSSafe(BB_Translate("Are you sure you want to remove this language from the story?"))) . "');\" title=\"" . htmlspecialchars(BB_Translate("Delete lang")) . "\">&times;</a></span>";
			}
		}
		if ($revnum)  echo implode("&nbsp;| ", $options);
		else  echo implode("", $options);
?>

<script type="text/javascript" src="support/content-tools/<?php echo (file_exists($config["rootpath"] . "/support/content-tools/content-tools.js") ? "content-tools.js" : "content-tools.min.js"); ?>"></script>

<script type="text/javascript">
function AddNewTag_OK()
{
	var newval = $('input[name=newtag]').val();
	if (newval !== '')
	{
		var currsel = $('select[name="tags[]"]').val();
		currsel.push(newval);
		$('select[name="tags[]"]').append($("<option/>", { value: newval, text: newval })).val(currsel).change();
	}

	return AddNewTag_Cancel();
}

function AddNewTag_Cancel()
{
	$('input[name=newtag]').val('');
	$('#addnewtag').hide();

	return false;
}

function SetDateTimeElements(newdate, dateelem, timeelem)
{
	if (dateelem)
	{
		var year = '' + newdate.getFullYear();

		var month = '' + (newdate.getMonth() + 1);
		if (month.length == 1)  month = '0' + month;

		var day = '' + newdate.getDate();
		if (day.length == 1)  day = '0' + day;

		dateelem.val(year + '-' + month + '-' + day);
	}

	if (timeelem)
	{
		var hour = newdate.getHours();
		var ampm = '';
<?php
		// If a 12-hour clock is in use for the active language, output appropriate code.
		$timestr = BB_Translate("H:i");
		if (strpos($timestr, "g:") !== false || strpos($timestr, "h:") !== false)
		{
?>
		if (hour == 0)
		{
			hour = 12;
			ampm = ' am';
		}
		else if (hour == 12)
		{
			ampm = ' pm';
		}
		else if (hour < 12)
		{
			ampm = ' am';
		}
		else
		{
			hour -= 12;
			ampm = ' pm';
		}
<?php
		}
?>
		hour = '' + hour;
<?php
			if (strpos($timestr, "h:") !== false)
			{
?>
		if (hour.length == 1)  hour = '0' + hour;
<?php
			}
?>

		var minute = '' + newdate.getMinutes();
		if (minute.length == 1)  minute = '0' + minute;

		timeelem.val(hour + ':' + minute + ampm);
	}
}

$(function() {
	var assetid = <?php echo $assetid; ?>;
	var revnum = <?php echo $revnum; ?>;
	var lang = '<?php echo BB_JSSafe((isset($_REQUEST["lang"]) ? $_REQUEST["lang"] : $assetlang)); ?>';
	var adminlang = '<?php echo BB_JSSafe($_SESSION[$sessionkey]["lang"]); ?>';
	var inittitle = $('title').html();
	var editing = false;

	var mimeinfomap = <?php echo json_encode($mimeinfomap, JSON_UNESCAPED_SLASHES); ?>;

	var UpdatePageTitle = function() {
		var title = $('input[name=title]').val().trim();
		if (title === '')  title = '<?php echo BB_JSSafe(BB_Translate("(Untitled)")); ?>';

		var lang2 = $('input[name=lang]').val().trim().toLowerCase();
		lang2 = lang2.replace(/[^a-z0-9]/g, ' ').trim().replace(/\s+/g, '-');

		$('title').html((editing ? '&#x270E; ' : '') + inittitle + ' | ' + title + (adminlang !== lang2 ? ' (' + lang2 + ')' : ''));
	};

	$('input[name=title]').keydown(UpdatePageTitle).keyup(UpdatePageTitle).change(UpdatePageTitle);
	$('input[name=lang]').keydown(UpdatePageTitle).keyup(UpdatePageTitle).change(UpdatePageTitle);
	UpdatePageTitle();

	var EscapeHTML = function(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	};

	var FormatStr = function(format) {
		var args = Array.prototype.slice.call(arguments, 1);

		return format.replace(/{(\d+)}/g, function(match, number) {
			return (typeof args[number] != 'undefined' ? args[number] : match);
		});
	};

	var SetPageMessage = function(type, msg) {
		$('#bb_cms_contentmessage').remove();

		if (type !== '')
		{
			var html = '<div id="bb_cms_contentmessage" class="propmessagewrap propmessage' + type + '"><div class="propmessage"><div class="message"><div class="' + type + '">' + EscapeHTML(msg) + '</div></div></div></div>';

			$('.proptitlewrap').after(html);

			if (type !== 'success')  new ContentTools.FlashUI('no');
		}
	};

	$('input[name=newtag]').autocomplete({
		minLength: 3,
		delay: 250,
		source: function(request, response) {
			$.ajax({
				'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
				'method': 'POST',
				'dataType': 'json',
				'data': {
					'action': 'findtags',
					'tag': request.term,
					'sec_t': '<?php echo BB_CreateSecurityToken("findtags")?>'
				},
				'success': function(data) {
					if (data.success)  response(data.tags);
					else  response([]);
				},
				'error': function() {
					response([]);
				}
			});
		}
	});

	// Read only fields until edit is clicked.
	$('input, textarea, select').prop('disabled', true);

	ContentEdit.RESIZE_CORNER_SIZE = 0;

	// Implement image cropping.
	ContentTools.CROP_IMAGE = {
		instancenum: 1,

		init: function(runinfo) {
			ContentTools.CROP_IMAGE.runinfo = runinfo;

			// Initialize image.
			var previewurl = '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>?action=getfile&id=' + encodeURIComponent(runinfo.info.id) + '&path=' + encodeURIComponent(runinfo.info.file.path) + '&filename=' + encodeURIComponent(runinfo.info.file.filename) + '&maxwidth=600&sec_t=<?php echo BB_CreateSecurityToken("getfile")?>';
			runinfo.img = $('<img>').attr('src', previewurl);
			$(runinfo.crop).append(runinfo.img);

			runinfo.cropratio_x = 0;
			runinfo.cropratio_y = 0;
			runinfo.initcropinfo = null;
			runinfo.cropper = null;
			runinfo.ready = false;

			// Load Cropper JS.
			FlexForms.LoadCSS('modules-imagecrop', 'support/cropperjs/cropper.css');

			FlexForms.jsqueue['modules-imagecrop'] = { 'mode': 'src', 'dependency': false, 'loading': false, 'src': 'support/cropperjs/cropper.min.js', 'detect': 'Cropper' };

			var instancenum = ContentTools.CROP_IMAGE.instancenum;

			FlexForms.jsqueue['modules-imagecrop-barebones-cms|' + instancenum] = {
				'mode': 'inline',
				'dependency': 'modules-imagecrop',
				'loading': false,
				'src': function() {
					// Initialize ratios.
					var ratios = <?php echo json_encode($config["crop_ratios"], JSON_UNESCAPED_SLASHES); ?>;
					for (var x = 0; x < ratios.length; x++)
					{
						var initcrop = (runinfo.info.fileinfo.crops && runinfo.info.fileinfo.crops[ratios[x]] ? runinfo.info.fileinfo.crops[ratios[x]] : {});

						if (ratios[x] === '')  runinfo.dialog.addCropRatio.call(runinfo.dialog, '', '<?php echo BB_JSSafe(BB_Translate("Any")); ?>', initcrop);
						else  runinfo.dialog.addCropRatio.call(runinfo.dialog, ratios[x], ratios[x], initcrop);
					}

					runinfo.ready = true;

					if (runinfo.info.cropselected)  runinfo.dialog.selectCropRatio.call(runinfo.dialog, runinfo.info.cropselected);
					else  runinfo.dialog.selectCropRatio.call(runinfo.dialog, '<?php echo BB_JSSafe($config["default_crop_ratio"]); ?>');
				}
			};

			ContentTools.CROP_IMAGE.instancenum++;

			FlexForms.ProcessJSQueue.call(FlexForms);
		},

		ratio: function(newratio, cropinfo) {
			var runinfo = ContentTools.CROP_IMAGE.runinfo;

			if (runinfo.cropper)
			{
				runinfo.cropper.destroy();
				runinfo.cropper = null;
			}

			if (newratio === '')
			{
				runinfo.cropratio_x = 0;
				runinfo.cropratio_y = 0;
			}
			else
			{
				var ratio = newratio.split(':');

				runinfo.cropratio_x = ratio[0];
				runinfo.cropratio_y = ratio[1];
			}

			runinfo.initcropinfo = cropinfo;

			ContentTools.CROP_IMAGE.resize();
		},

		resize: function() {
			var runinfo = ContentTools.CROP_IMAGE.runinfo;

			// Change image max height.
			runinfo.img.css('max-height', $(runinfo.crop).parent().height() + 'px');

			if (!runinfo.ready)  return;

			if (runinfo.cropper)  runinfo.cropper.destroy();

			// Create the cropper.
			var options = {
				viewMode: 1,
				movable: false,
				zoomable: false,
				rotatable: false,
				scalable: false,
				autoCropArea: 1,
				crop: function(data) {
					var imagedata = runinfo.cropper.getImageData();

					var cropinfo = {
						x: data.detail.x / imagedata.naturalWidth,
						y: data.detail.y / imagedata.naturalHeight,
						x2: (data.detail.x + data.detail.width) / imagedata.naturalWidth,
						y2: (data.detail.y + data.detail.height) / imagedata.naturalHeight
					};

					runinfo.initcropinfo = cropinfo;

					runinfo.dialog.updateCropRatioInfo.call(runinfo.dialog, cropinfo);
				},
				ready: function() {
					if (runinfo.initcropinfo.hasOwnProperty('x'))
					{
						var data = runinfo.cropper.getCanvasData();

						runinfo.cropper.setCropBoxData({ left: data.left + (data.width * runinfo.initcropinfo.x), top: data.top + (data.height * runinfo.initcropinfo.y), width: data.width * Math.abs(runinfo.initcropinfo.x2 - runinfo.initcropinfo.x), height: data.height * Math.abs(runinfo.initcropinfo.y2 - runinfo.initcropinfo.y) });
					}
				}
			};

			if (runinfo.cropratio_x > 0 && runinfo.cropratio_y > 0)  options.aspectRatio = runinfo.cropratio_x / runinfo.cropratio_y;

			runinfo.cropper = new Cropper(runinfo.img[0], options);
		},

		setdefault: function(ratio, cropinfo) {
			var runinfo = ContentTools.CROP_IMAGE.runinfo;

			$.ajax({
				'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
				'method': 'POST',
				'dataType': 'json',
				'data': {
					'action': 'setassetdefaultcrop',
					'id': runinfo.info.id,
					'filename': runinfo.info.file.filename,
					'ratio': ratio,
					'crop_x': cropinfo.x,
					'crop_y': cropinfo.y,
					'crop_x2': cropinfo.x2,
					'crop_y2': cropinfo.y2,
					'sec_t': '<?php echo BB_CreateSecurityToken("setassetdefaultcrop")?>'
				}
			});
		},

		save: function(ratio, crops) {
			var runinfo = ContentTools.CROP_IMAGE.runinfo;

			if (runinfo.info.fileinfo.constructor === Array)  runinfo.info.fileinfo = {};
			runinfo.info.fileinfo.crops = crops;
			runinfo.info.cropselected = ratio;
			runinfo.info.crop = crops[ratio].x + ',' + crops[ratio].y + ',' + crops[ratio].x2 + ',' + crops[ratio].y2;

			var previewurl = '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>?action=getfile&id=' + encodeURIComponent(runinfo.info.id) + '&path=' + encodeURIComponent(runinfo.info.file.path) + '&filename=' + encodeURIComponent(runinfo.info.file.filename) + '&maxwidth=400&crop=' + encodeURIComponent(runinfo.info.crop) + '&sec_t=<?php echo BB_CreateSecurityToken("getfile")?>';

			var result = {
				src: previewurl,
				width: '400',
				height: '300',
				info: runinfo.info
			};

			return result;
		}
	};

	// Implement file finder.
	ContentTools.INSERT_FILE_FIND = {
		uploadedtitle: false,
		audioelem: document.createElement('audio'),
		videoelem: document.createElement('video'),

		// Designed to be overridden to add custom preview support for other file types.
		getiteminfo: function(id, file, fileinfo) {
			var pos = file.filename.lastIndexOf('.');
			var fileext = (pos > -1 ? file.filename.substring(pos).toLowerCase() : '');

			var previewurl = '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>?action=getfile&id=' + encodeURIComponent(id) + '&path=' + encodeURIComponent(file.path) + '&filename=' + encodeURIComponent(file.filename) + '&sec_t=<?php echo BB_CreateSecurityToken("getfile")?>';

			// Note that the preview URL is not valid for site visitors.  All URLs must be properly transformed before final delivery.
			var info = {
				id: id,
				file: file,
				fileinfo: fileinfo
			};

			var downloadinfo = {
				id: id,
				file: file,
				fileinfo: fileinfo,
				download: true
			};

			result = {
				'link': $('<a>').attr('href', previewurl).attr('target', '_blank'),
				'modes': [
					{ 'title': '<?php echo BB_JSSafe(BB_Translate("Download")); ?>', 'icon': 'download', 'element': 'a', 'url': previewurl + '&download=' + encodeURIComponent(file.origfilename), 'info': downloadinfo }
				]
			};

			if (mimeinfomap[fileext])  result.modes.unshift({ 'title': '<?php echo BB_JSSafe(BB_Translate("Link")); ?>', 'icon': 'link', 'element': 'a', 'url': previewurl, 'info': info });

			if (mimeinfomap[fileext] && mimeinfomap[fileext].preview)
			{
				if (mimeinfomap[fileext].type.lastIndexOf('image/', 0) > -1)
				{
					result.preview = function() { return $('<img>').attr('src', previewurl + '&maxwidth=800').get(0); };
					result.modes.unshift({ 'title': '<?php echo BB_JSSafe(BB_Translate("Image")); ?>', 'icon': 'image', 'element': 'img', 'url': previewurl + '&maxwidth=400', 'width': '400', 'height': '300', 'info': info });
				}
				else if (mimeinfomap[fileext].type.lastIndexOf('audio/', 0) > -1 && ContentTools.INSERT_FILE_FIND.audioelem.canPlayType && ContentTools.INSERT_FILE_FIND.audioelem.canPlayType(mimeinfomap[fileext].type))
				{
					result.preview = function() { return $('<audio>').attr('src', previewurl).prop('controls', true).get(0); };
					result.modes.unshift({ 'title': '<?php echo BB_JSSafe(BB_Translate("Embed")); ?>', 'icon': 'embed', 'element': 'div-embed', 'html': '<audio src="' + EscapeHTML(previewurl) + '" controls data-src-info="' + EscapeHTML(JSON.stringify(info)) + '"></audio>', 'info': info });
				}
				else if (mimeinfomap[fileext].type.lastIndexOf('video/', 0) > -1 && ContentTools.INSERT_FILE_FIND.videoelem.canPlayType && ContentTools.INSERT_FILE_FIND.videoelem.canPlayType(mimeinfomap[fileext].type))
				{
					result.preview = function() { return $('<video>').attr('src', previewurl).prop('controls', true).get(0); };
					result.modes.unshift({ 'title': '<?php echo BB_JSSafe(BB_Translate("Embed")); ?>', 'icon': 'embed', 'element': 'div-embed', 'html': '<video src="' + EscapeHTML(previewurl) + '" controls data-src-info="' + EscapeHTML(JSON.stringify(info)) + '"></video>', 'info': info });
				}
			}

			result.label = file.origfilename.replace('_', '_\u200B');

			return result;
		},

		// Called by the file uploader.  Inserts each uploaded file into the find list and automatically selects the first uploaded file.
		adduploadedfile: function(result) {
			var runinfo = ContentTools.INSERT_FILE_FIND.runinfo;

			if (!ContentTools.INSERT_FILE_FIND.uploadedtitle)  runinfo.dialog.addResultTitle.call(runinfo.dialog, '<?php echo BB_JSSafe(BB_Translate("Uploaded")); ?>');

			var iteminfo = ContentTools.INSERT_FILE_FIND.getiteminfo(assetid, result.file, result.fileinfo);

			runinfo.dialog.addResultItem.call(runinfo.dialog, iteminfo, !ContentTools.INSERT_FILE_FIND.uploadedtitle);

			ContentTools.INSERT_FILE_FIND.uploadedtitle = true;
		},

		init: function(runinfo) {
			ContentTools.INSERT_FILE_FIND.runinfo = runinfo;

			var html = '';
			html += '<option value="7"><?php echo htmlspecialchars(BB_Translate("7 days")); ?></option>';
			html += '<option value="14"><?php echo htmlspecialchars(BB_Translate("14 days")); ?></option>';
			html += '<option value="31"><?php echo htmlspecialchars(BB_Translate("31 days")); ?></option>';
			html += '<option value="-1"><?php echo htmlspecialchars(BB_Translate("All")); ?></option>';
			html += '<option value="0"><?php echo htmlspecialchars(BB_Translate("Drafts")); ?></option>';

			$(runinfo.searchlimit).append(html);
		},

		run: function() {
			var runinfo = ContentTools.INSERT_FILE_FIND.runinfo;

			var data = {
				'action': 'findassetfiles',
				'sec_t': '<?php echo BB_CreateSecurityToken("findassetfiles")?>'
			};

			var search = $(runinfo.searchinput).val();
			var limit = $(runinfo.searchlimit).val();

			if (search === '')
			{
				if (!assetid)  return;

				data.id = assetid;
				data.revision = revnum;
			}
			else
			{
				data.search = search;
				data.limit = limit;
			}

			$.ajax({
				'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
				'method': 'POST',
				'dataType': 'json',
				'data': data,
				'success': function(result) {
					if (!result.success)  runinfo.dialog.addResultError.call(runinfo.dialog, result.error + " (" + result.errorcode + ")");
					else
					{
						ContentTools.INSERT_FILE_FIND.uploadedtitle = false;

						runinfo.dialog.clearResults.call(runinfo.dialog, false);

						var num = 0;
						for (var x = 0; x < result.assets.length; x++)
						{
							if (Object.keys(result.assets[x].files).length)
							{
								runinfo.dialog.addResultTitle.call(runinfo.dialog, result.assets[x].title);

								for (var filename in result.assets[x].files)
								{
									var iteminfo = ContentTools.INSERT_FILE_FIND.getiteminfo(result.assets[x].id, result.assets[x].files[filename], result.assets[x].filesinfo[filename]);
									runinfo.dialog.addResultItem.call(runinfo.dialog, iteminfo);

									num++;
								}

								if (num >= 50)  break;
							}
						}
					}
				},
				'error': function() {
					runinfo.dialog.addResultError.call(runinfo.dialog, '<?php echo BB_JSSafe(BB_Translate("A network error occurred while searching.  Try again later.")); ?>');
				}
			});
		}
	};

<?php
		// Configure the tools for an asset.  Sending files to the API isn't possible until an asset exists.
		// An asset is created when saving for the first time.
		if ($assetid > 0 && !$revnum)
		{
			// Calculate the list of acceptable file types based on the API configuration information.
			$accept = array();
			$exts = explode(";", $_SESSION[$sessionkey]["api_info"]["file_exts"]);
			foreach ($exts as $ext)
			{
				$ext = trim($ext);
				if ($ext !== "")
				{
					$accept[$ext] = $ext;

					if (isset($mimeinfomap[$ext]) && isset($mimeinfomap[$ext]["type"]))  $accept[$mimeinfomap[$ext]["type"]] = $mimeinfomap[$ext]["type"];
				}
			}

			// The official Barebones CMS API always supports chunked uploads.
			// Chunked uploads result in smoother host-to-host data transfers by rate limiting the client side.
			$chunked = $_SESSION[$sessionkey]["api_info"]["chunked_uploads"];

			// Calculate the maximum upload limit for both hosts.
			$apiuploadsize = $_SESSION[$sessionkey]["api_info"]["max_chunk_size"];
			if ($apiuploadsize < 1)  $apiuploadsize = ($chunked ? 1048576 : 1073741824);

			$uploadsize = FlexForms_FileUploader::GetMaxUploadFileSize();
			if ($uploadsize < 1)  $uploadsize = ($chunked ? 1048576 : 1073741824);

			$chunksize = floor(min($apiuploadsize, $uploadsize));

			// Utilize FlexForms to only include the necessary CSS and Javascript one time.
?>
	ContentTools.INSERT_FILE_UPLOADER = {
		instancenum: 1,

		init: function(runinfo) {
			ContentTools.INSERT_FILE_UPLOADER.runinfo = runinfo;

			FlexForms.LoadCSS('modules-fileuploader', 'support/fancy-file-uploader/fancy_fileupload.css');

			FlexForms.jsqueue['modules-fileuploader-base'] = { 'mode': 'src', 'dependency': 'jqueryui', 'loading': false, 'src': 'support/fancy-file-uploader/jquery.fileupload.js', 'detect': 'jQuery.fn.fileupload' };
			FlexForms.jsqueue['modules-fileuploader-iframe'] = { 'mode': 'src', 'dependency': 'modules-fileuploader-base', 'loading': false, 'src': 'support/fancy-file-uploader/jquery.iframe-transport.js', 'detect': 'jQuery.fn.FancyFileUpload' };
			FlexForms.jsqueue['modules-fileuploader-fancy'] = { 'mode': 'src', 'dependency': 'modules-fileuploader-iframe', 'loading': false, 'src': 'support/fancy-file-uploader/jquery.fancy-fileupload.js', 'detect': 'jQuery.fn.FancyFileUpload' };

			var instancenum = ContentTools.INSERT_FILE_UPLOADER.instancenum;
			FlexForms.jsqueue['modules-fileuploader-barebones-cms|' + instancenum] = {
				'mode': 'inline',
				'dependency': 'modules-fileuploader-fancy',
				'loading': false,
				'src': function() {
					var sectionwrap = $('<div>').addClass('ct-section-wrap');

					var autostart = $('<div>').addClass('ct-section').addClass('ct-section--applied');
					autostart.append($('<div>').addClass('ct-section__label').text('<?php echo BB_JSSafe(BB_Translate("Auto-upload"));?>'));
					autostart.append($('<div>').addClass('ct-section__switch-wrap').append($('<div>').addClass('ct-section__switch')));
					autostart.click(function() {
						$(this).toggleClass('ct-section--applied');
					});

					sectionwrap.append(autostart);
					$(runinfo.upload).append(sectionwrap);

					var wrapper = $('<div>').addClass('barebones_cms_upload');
					$(runinfo.upload).append(wrapper);

					var inputfile = $('<input>').addClass('barebones_cms_fileuploader').prop('multiple', true).attr({
						'type': 'file',
						'id': 'barebones-cms-fileuploader-' + instancenum,
						'name': 'file',
						'accept': '<?php echo BB_JSSafe(implode(", ", $accept)); ?>'
					});

					wrapper.append(inputfile);

					// Handle the autostart switch for uploading on file selection.
					var BB_AddedFile = function(e, data) {
						// Simulate clicking the start upload button to trigger the upload.
						if (autostart.hasClass('ct-section--applied'))  this.find('.ff_fileupload_actions button.ff_fileupload_start_upload').click();
					}

					// Disable the parent dialog when showing the preview dialog.
					var BB_ShowPreview = function(data, preview, previewclone) {
						runinfo.dialog.busy.call(runinfo.dialog, true);
					};

					var BB_HidePreview = function(data, preview, previewclone) {
						runinfo.dialog.busy.call(runinfo.dialog, false);
					};

					// Upload logic swiped from Cool File Transfer.
					var BB_SubmitUpload = function(SubmitUpload, e, data) {
						var $this = this;

						$this.find('.ff_fileupload_fileinfo').text(data.ff_info.displayfilesize + ' | <?php echo BB_JSSafe(BB_Translate("Starting upload..."));?>');

						var failed = function(result) {
							if (typeof(result.error) !== 'string')  result.error = '<?php echo BB_JSSafe(BB_Translate("The server indicated that the upload was not able to be started.  No additional information is available."));?>';
							if (typeof(result.errorcode) !== 'string')  result.errorcode = 'server_response';

							data.ff_info.errors.push(FormatStr('<?php echo BB_JSSafe(BB_Translate("The upload failed.  {0} ({1})"));?>', EscapeHTML(result.error), EscapeHTML(result.errorcode)));

							this.find('.ff_fileupload_errors').html(data.ff_info.errors.join('<br>')).removeClass('ff_fileupload_hidden');

							this.removeClass('ff_fileupload_starting');

							// Hide the progress bar.
							this.find('.ff_fileupload_progress_background').addClass('ff_fileupload_hidden');

							// Alter remove buttons.
							this.find('button.ff_fileupload_remove_file').attr('aria-label', '<?php echo BB_JSSafe(BB_Translate("Remove from list"));?>');
						};

						data.cms_upload_info = {
							'retries' : 5
						};

						// Start a file upload.
						var options = {
							'url' : '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
							'method': 'POST',
							'dataType' : 'json',
							'data' : {
								'action': 'uploadassetfile',
								'id': assetid,
								'mode': 'start',
								'filename': data.files[0].uploadName || data.files[0].name,
								'filesize' : data.files[0].size,
								'sec_t': '<?php echo BB_CreateSecurityToken("uploadassetfile")?>'
							},
							'success' : function(result) {
								if (data.cms_upload_info)
								{
									if (data.cms_upload_info.ajax)  delete data.cms_upload_info.ajax;

									if (!result.success)  failed.call($this, result);
									else
									{
										// Request approved.  Set up the required parameters.  Note that the storage filename has possibly been altered by the API.
										data.files[0].uploadName = result.filename;

										data.formData = {
											'action': 'uploadassetfile',
											'id': assetid,
											'fileuploader': '1',
											'filename': result.filename,
											'sec_t': '<?php echo BB_CreateSecurityToken("uploadassetfile")?>'
										};

										// For later use.
										data.cms_upload_info.startinfo = result;

										SubmitUpload();
									}
								}
							},
							'error' : function() {
								if (data.cms_upload_info && data.cms_upload_info.retries > 1)
								{
									data.cms_upload_info.retries--;

									setTimeout(function() { if (data.cms_upload_info)  data.cms_upload_info.ajax = jQuery.ajax(options); }, 1000);
								}
								else if (data.ff_info)
								{
									$this.find('.ff_fileupload_fileinfo').text(data.ff_info.displayfilesize + ' | <?php echo BB_JSSafe(BB_Translate("Failed"));?>');

									var result = {
										'success' : false,
										'error' : '<?php echo BB_JSSafe(BB_Translate("A permanent network or data error occurred."));?>'
									};

									failed.call($this, result);
								}
							}
						};

						data.cms_upload_info.ajax = jQuery.ajax(options);
					};

					var BB_UploadCancelled = function(e, data) {
						if (data.cms_upload_info)
						{
							data.cms_upload_info.retries = 0;
							if (data.cms_upload_info.ajax)  data.cms_upload_info.ajax.abort();

							// Trigger a send to the server to delete the file.
							if (data.cms_upload_info.startinfo)
							{
								jQuery.ajax({
									'url' : '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
									'method': 'POST',
									'dataType' : 'json',
									'data' : {
										'action': 'uploadassetfile',
										'id': assetid,
										'mode': 'cancel',
										'filename': data.cms_upload_info.startinfo.filename,
										'sec_t': '<?php echo BB_CreateSecurityToken("uploadassetfile")?>'
									}
								});
							}

							delete data.cms_upload_info;
						}
					};

					var BB_UploadCompleted = function(e, data) {
						if (data.cms_upload_info)
						{
							// Trigger a send to the server to notify the API that the file is finished.
							if (data.cms_upload_info.startinfo)
							{
								var retries = 5;

								var options = {
									'url' : '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
									'method': 'POST',
									'dataType' : 'json',
									'data' : {
										'action': 'uploadassetfile',
										'id': assetid,
										'mode': 'done',
										'filename': data.cms_upload_info.startinfo.filename,
										'sec_t': '<?php echo BB_CreateSecurityToken("uploadassetfile")?>'
									},
									'success': function(result) {
										if (result.success)
										{
											// Add the file to the selection list.
											ContentTools.INSERT_FILE_FIND.adduploadedfile(result);

											// Remove the file from the upload list.
											if (data.ff_info)  data.ff_info.RemoveFile();
										}
									},
									'error': function() {
										if (retries > 1)
										{
											retries--;

											setTimeout(function() { jQuery.ajax(options); }, 1000);
										}
									}
								};

								jQuery.ajax(options);
							}

							delete data.cms_upload_info;
						}
					};

					var options = {
						'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
						'params': {
						},
						'fileupload': {
						},
						'added': BB_AddedFile,
						'showpreview': BB_ShowPreview,
						'hidepreview': BB_HidePreview,
						'startupload': BB_SubmitUpload,
						'uploadcancelled': BB_UploadCancelled,
						'uploadcompleted': BB_UploadCompleted
					};

<?php
			// Different upload types depending on whether or not the API supports chunked uploads.
			if ($chunked)
			{
?>
					options.fileupload.origMaxChunkSize = <?php echo $chunksize; ?>;

					options.fileupload.maxChunkSize = function(options) {
						if (options._progress.loaded === 0)  return 65536;

						var size = Math.floor(options._progress.bitrate * 1.25 / 8);

						if (size < 65536)  size = 65536;

						return Math.min(options.origMaxChunkSize, size);
					};
<?php
			}
			else
			{
?>
					options.fileupload.limitMultiFileUploadSize = <?php echo $chunksize; ?>;
<?php
			}
?>

					inputfile.FancyFileUpload(options);
				}
			};

			ContentTools.INSERT_FILE_UPLOADER.instancenum++;

			FlexForms.ProcessJSQueue.call(FlexForms);
		}
	};
<?php
		}
		else
		{
?>
	ContentTools.INSERT_FILE_UPLOADER = {
		init: function(runinfo) {
			$(runinfo.upload).html('<div class="barebones_cms_upload"><p><?php echo htmlspecialchars(BB_Translate("Unable to upload files at this time.  Save the asset first by closing this dialog and clicking the green checkmark in the upper right corner.")); ?></p></div>');
		}
	};
<?php
		}


		// Allow custom styles to be added, add custom embed templates, adjust the available tools, etc.
		// See:  http://getcontenttools.com/getting-started
		$em->Fire("addeditasset_story_pre_editor", array());
?>

	// Initialize the ContentTools editor.
	var editor = ContentTools.EditorApp.get();
	editor.init('*[data-editable]', 'data-name');

	var button = $('<div>').addClass('ct-ignition__button').addClass('ct-ignition__button--barebones-cms-home').click(function() {
		var url = '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>';

		if ($('.ct-app .ct-ignition.ct-ignition--ready').length)  location.href = url;
		else  window.open(url, '_blank');
	});
	$('.ct-app .ct-ignition').append(button);

	editor.addEventListener('start', function(ev) {
		editing = true;
		UpdatePageTitle();

		$('input, textarea, select').prop('disabled', false);

		$('[data-editable] img').attr('width', '400').attr('height', '300');

<?php
		// Only generate the AJAX call if it will probably do something.
		if ($assetid > 0 && !$revnum && $bb_username != "")
		{
			// Let other users know this asset is being edited.
?>
		$.ajax({
			'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
			'method': 'POST',
			'dataType': 'json',
			'data': {
				'action': 'editassetstart',
				'id': assetid,
				'sec_t': '<?php echo BB_CreateSecurityToken("editassetstart")?>'
			}
		});
<?php
		}
?>
	});

	editor.addEventListener('stop', function(ev) {
		editing = false;
		UpdatePageTitle();

		$('input, textarea, select').prop('disabled', true);

		setTimeout(function() {
			$('[data-editable] img').removeAttr('width').removeAttr('height');
		}, 0);

<?php
		// Only generate the AJAX call if it will probably do something.
		if ($assetid > 0)
		{
			// Let other users know that this asset is no longer being edited.
			// The 'saveasset' call also removes the user so there isn't a need to call 'editassetend' when saving.
?>
		if (!ev.detail().save)
		{
			$.ajax({
				'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
				'method': 'POST',
				'dataType': 'json',
				'data': {
					'action': 'editassetend',
					'id': assetid,
					'sec_t': '<?php echo BB_CreateSecurityToken("editassetend")?>'
				}
			});
		}
<?php
		}
?>

		// Verify IANA language field.
		var lang2 = $('input[name=lang]').val().trim().toLowerCase();
		var lang3 = lang2.replace(/[^a-z0-9]/g, ' ').trim().replace(/\s+/g, '-');
		$('input[name=lang]').val(lang3);
		if (ev.detail().save)
		{
			if (lang3 === '')
			{
				SetPageMessage('error', '<?php echo BB_JSSafe(BB_Translate("Content not saved.  Please enter a valid IANA language code.")); ?>');

				return false;
			}
			else if (lang2 !== lang3)
			{
				SetPageMessage('error', '<?php echo BB_JSSafe(BB_Translate("Content not saved.  The new IANA language code was invalid.  Try again.")); ?>');

				return false;
			}
		}

		SetPageMessage('', '');
	});

	editor.addEventListener('saved', function(ev) {
		var regions = ev.detail().regions;

		editor.busy(true);

		// Construct the POST request.
		var data = {
			'action': 'saveasset',
			'id': assetid,
			'revision': revnum,
			'type': 'story',
			'sec_t': '<?php echo BB_CreateSecurityToken("saveasset")?>'
		};

		// Append form elements.
		$('input[name], textarea[name]').each(function() {
			data[$(this).attr('name')] = $(this).val();
		});

		$('select[name]').each(function() {
			data[$(this).attr('name')] = $(this).val() || [];
		});

		// Handle new language creation.
		if (data.lang !== lang)  data.origlang = lang;

		// Handle empty tags list.
		if (!data['tags[]'])  data.tags = '';

		// Append body content.  Note that this only sends the body if the body has changed.
		for (var x in regions)
		{
			if (regions.hasOwnProperty(x))  data[x] = regions[x];
		}

		// Save the asset.
		$.ajax({
			'url': '<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>',
			'method': 'POST',
			'dataType': 'json',
			'data': data,
			'success': function(result) {
				editor.busy(false);

				// Handle the response.  Redirects if this is a brand new asset, the asset was a revision, or the IANA language code changed.
				if (!result.success)  SetPageMessage('error', result.error + " (" + result.errorcode + ")");
				else if (!assetid || revnum || data.lang !== lang)  location.replace('<?php echo BB_JSSafe(BB_GetRequestURLBase()); ?>?action=addeditasset&id=' + result.id + '&type=story&lang=' + encodeURIComponent(data.lang) + '&sec_t=<?php echo BB_CreateSecurityToken("addeditasset"); ?>');
			},
			'error': function() {
				editor.busy(false);

				SetPageMessage('error', '<?php echo BB_JSSafe(BB_Translate("A network error occurred while saving the asset.  Try again later.")); ?>');
			}
		});
	});
});
</script>
<?php
		$desc .= ob_get_contents();
		ob_end_clean();

		$contentopts = array(
			"desc" => ($assetid ? "Edit the story." : "Add a new story.") . "  Use the blue pencil icon in the upper right corner to begin editing.",
			"htmldesc" => $desc,
			"fields" => array(
				array(
					"title" => "IANA Language Code",
					"type" => "text",
					"name" => "lang",
					"default" => $assetlang,
					"htmldesc" => ($assetid && !$revnum && isset($_REQUEST["lang"]) && $_REQUEST["lang"] !== "" ? "<a href=\"" . BB_GetRequestURLBase() . "?action=addeditasset&id=" . $assetid . "&type=story&lang=&sec_t=" . BB_CreateSecurityToken("addeditasset") . "\" target=\"_blank\">" . htmlspecialchars(BB_Translate("Start new translation")) . "</a>" : "")
				),
				array(
					"title" => "Title",
					"type" => "text",
					"name" => "title",
					"default" => (isset($_REQUEST["lang"]) && $_REQUEST["lang"] === "" ? "" : $asset["langinfo"][$assetlang]["title"]),
					"counter" => true,
					"counter_options" => array(
						"mainMsg" => "{x} characters entered.",
						"mainMsgOne" => "{x} character entered."
					)
				),
				array(
					"title" => "Tags",
					"type" => "select",
					"multiple" => true,
					"mode" => "tags",
					"name" => "tags",
					"options" => $tags,
					"default" => $asset["tags"],
					"htmldesc" => "<a class=\"editonly\" href=\"#\" onclick=\"$('#addnewtag').show(); return false;\">" . htmlspecialchars(BB_Translate("Add tag")) . "</a>"
				),
				"html:<div id=\"addnewtag\" style=\"display: none; margin-top: 1.0em;\">",
				array(
					"title" => "New Tag",
					"type" => "text",
					"name" => "newtag",
					"default" => "",
					"htmldesc" => "<a href=\"#\" onclick=\"return AddNewTag_OK();\">" . htmlspecialchars(BB_Translate("OK")) . "</a>&nbsp;&nbsp;&nbsp;<a href=\"#\" onclick=\"return AddNewTag_Cancel();\">" . htmlspecialchars(BB_Translate("Cancel")) . "</a>"
				),
				"html:</div>",
				"startrow",
				array(
					"title" => "Publish Date",
					"width" => "20em",
					"type" => "date",
					"name" => "publish_date",
					"default" => ($asset["publish"] > 0 ? date("Y-m-d", $asset["publish"]) : ""),
					"htmldesc" => "<div class=\"editonly\"><a href=\"#\" onclick=\"SetDateTimeElements(new Date(), $('input[name=publish_date]'), $('input[name=publish_time]')); return false;\">" . htmlspecialchars(BB_Translate("Now")) . "</a> | <a href=\"#\" onclick=\"$('input[name=publish_date]').val(''); $('input[name=publish_time]').val(''); return false;\">" . htmlspecialchars(BB_Translate("Clear")) . "</a></div>"
				),
				array(
					"title" => "Publish Time",
					"width" => "10em",
					"type" => "text",
					"name" => "publish_time",
					"default" => ($asset["publish"] > 0 ? BB_FormatTimestamp("H:i", $asset["publish"]) : ""),
				),
				"startrow",
				array(
					"title" => "Unpublish Date",
					"width" => "20em",
					"type" => "date",
					"name" => "unpublish_date",
					"default" => ($asset["unpublish"] > 0 ? date("Y-m-d", $asset["unpublish"]) : ""),
					"htmldesc" => "<div class=\"editonly\"><a href=\"#\" onclick=\"SetDateTimeElements(new Date(), $('input[name=unpublish_date]'), $('input[name=unpublish_time]')); return false;\">" . htmlspecialchars(BB_Translate("Now")) . "</a> | <a href=\"#\" onclick=\"$('input[name=unpublish_date]').val(''); $('input[name=unpublish_time]').val(''); return false;\">" . htmlspecialchars(BB_Translate("Clear")) . "</a></div>"
				),
				array(
					"title" => "Unpublish Time",
					"width" => "10em",
					"type" => "text",
					"name" => "unpublish_time",
					"default" => ($asset["unpublish"] > 0 ? BB_FormatTimestamp("H:i", $asset["unpublish"]) : ""),
				),
				"endrow",
			)
		);

		$em->Fire("addeditasset_story_contentopts_pre_body", array(&$contentopts));

		$contentopts["fields"][] = "split";

		$contentopts["fields"][] = array(
			"type" => "custom",
			"value" => "<div data-editable data-name=\"body\">" . (isset($_REQUEST["lang"]) && $_REQUEST["lang"] === "" ? "" : $asset["langinfo"][$assetlang]["body"]) . "</div>"
		);

		$em->Fire("addeditasset_story_contentopts", array(&$contentopts));

		BB_GeneratePage(($assetid ? "Edit Story" : "Add Story"), array(), $contentopts);

		exit();
	}
	else if (isset($_REQUEST["action"]) && $_REQUEST["action"] == "setlanguage")
	{
		// Sets the user's default language for this session.
		$em->Fire("setlanguage_init", array());

		if (isset($_REQUEST["lang"]))
		{
			$_REQUEST["lang"] = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z]/', " ", strtolower(trim($_REQUEST["lang"])))));
			if ($_REQUEST["lang"] == "")  BB_SetPageMessage("error", "Please enter a valid 'IANA Language Code'.");

			if (BB_GetPageMessageType() != "error")
			{
				$_SESSION[$sessionkey]["lang"] = $_REQUEST["lang"];

				BB_RedirectPage("success", "Successfully updated the default language.", array("action=setlanguage&sec_t=" . BB_CreateSecurityToken("setlanguage")));
			}
		}

		// Extract possible languages from the user's browser (if any were submitted).
		$langs = array();
		if (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]))
		{
			$langs2 = explode(",", $_SERVER["HTTP_ACCEPT_LANGUAGE"]);
			foreach ($langs2 as $lang)
			{
				$lang = trim($lang);
				$pos = strpos($lang, ";");
				if ($pos !== false)  $lang = substr($lang, 0, $pos);
				$lang = preg_replace('/\s+/', "-", trim(preg_replace('/[^a-z]/', " ", strtolower(trim($lang)))));
				if ($lang !== "")  $langs[$lang] = "<a href=\"#\" onclick=\"$('input[name=lang]').val('" . htmlspecialchars($lang) . "');  return false;\">" . htmlspecialchars($lang) . "</a>";
			}
		}

		// Add the API default language.
		$lang = $_SESSION[$sessionkey]["api_info"]["default_lang"];
		$langs[$lang] = "<a href=\"#\" onclick=\"$('input[name=lang]').val('" . htmlspecialchars($lang) . "');  return false;\">" . htmlspecialchars($lang) . "</a>";

		// Add the user's current language.
		$lang = $_SESSION[$sessionkey]["lang"];
		$langs[$lang] = "<a href=\"#\" onclick=\"$('input[name=lang]').val('" . htmlspecialchars($lang) . "');  return false;\">" . htmlspecialchars($lang) . "</a>";

		$em->Fire("setlanguage_langs", array(&$langs));

		$contentopts = array(
			"desc" => "Set the default language for this session.",
			"fields" => array(
				array(
					"title" => "IANA Language Code",
					"type" => "text",
					"name" => "lang",
					"default" => $_SESSION[$sessionkey]["lang"],
					"htmldesc" => implode(", ", $langs)
				),
			),
			"submit" => "Save"
		);

		$em->Fire("setlanguage_contentopts", array(&$contentopts));

		BB_GeneratePage("Set Language", $menuopts, $contentopts);

		exit();
	}
	else if (isset($_REQUEST["wantaction"]) && $_REQUEST["wantaction"] == "addeditasset")
	{
		// Redirect the request to the correct location for editing the asset.
		$em->Fire("want_addeditasset_init", array());

		$redirect = "action=addeditasset";
		if (isset($_REQUEST["id"]))  $redirect .= "&id=" . (int)$_REQUEST["id"];
		if (isset($_REQUEST["type"]))  $redirect .= "&type=" . urlencode($_REQUEST["type"]);
		if (isset($_REQUEST["lang"]))  $redirect .= "&lang=" . urlencode($_REQUEST["lang"]);
		$redirect .= "&sec_t=" . BB_CreateSecurityToken("addeditasset");

		$em->Fire("want_addeditasset_redirect", array(&$redirect));

		BB_RedirectPage("", "", array($redirect));
	}
?>