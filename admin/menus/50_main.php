<?php
	// Barebones CMS main menu.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	if (!isset($menuopts))  exit();

	if (!isset($menuopts["Options"]))  $menuopts["Options"] = array();

	$menuopts["Options"]["List Assets"] = BB_GetRequestURLBase() . "?action=listassets&sec_t=" . BB_CreateSecurityToken("listassets");
	$menuopts["Options"]["Find Assets"] = BB_GetRequestURLBase() . "?action=findassets&sec_t=" . BB_CreateSecurityToken("findassets");
	$menuopts["Options"]["New Story"] = BB_GetRequestURLBase() . "?action=addeditasset&type=story&sec_t=" . BB_CreateSecurityToken("addeditasset");
?>