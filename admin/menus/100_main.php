<?php
	// Barebones CMS main menu.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($menuopts))  exit();

	if (!isset($menuopts["Options"]))  $menuopts["Options"] = array();

	$menuopts["Options"]["Set Language"] = BB_GetRequestURLBase() . "?action=setlanguage&sec_t=" . BB_CreateSecurityToken("setlanguage");
?>