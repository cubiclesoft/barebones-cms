Creating Extensions
===================

Barebones CMS is extensible through plugins, language packs, and even extending the [Barebones CMS API](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/api.md).

This document covers the creation of new extensions for the Barebones CMS administrative interface.

When you are finished creating your new Barebones CMS extension, consider contributing it so that others can benefit.  See the extensions list page for instructions on [publishing extensions](https://github.com/cubiclesoft/barebones-cms-extensions#publishing-extensions).

Plugins
-------

The administrative interface has extensive support for plugins.  Almost every operation has "init" and "post" execution hooks and usually one or two more hooks per operation.

Plugins register to be notified for specific events.  This allows the Barebones CMS admin interface to be extended without modifying the product itself, which allows it to be easily upgraded in the future.

Example plugin that adds a custom "Notes" field to story assets:

```php
<?php
	// Adds a language-agnostic Notes field to the story type.
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	class Plugin_Asset_Story_Notes
	{
		// Adds the Notes field after the Unpublish field but before the main body.
		public static function Display(&$contentopts)
		{
			global $asset;

			if (!isset($asset["protected"]["notes"]))  $asset["protected"]["notes"] = "";

			$contentopts["fields"][] = array(
				"title" => "Notes",
				"type" => "textarea",
				"name" => "plugin_notes",
				"default" => $asset["protected"]["notes"]
			);
		}

		// Stores the value of the field with the asset.
		public static function Save($assetid, &$asset)
		{
			if (isset($_REQUEST["plugin_notes"]) && is_string($_REQUEST["plugin_notes"]))  $asset["protected"]["notes"] = $_REQUEST["plugin_notes"];
		}

		// Adds a 'diff' to the asset History view whenever the field changes.
		public static function History(&$prevasset, &$asset, &$val)
		{
			if (isset($prevasset["protected"]["notes"]) && isset($asset["protected"]["notes"]) && $prevasset["protected"]["notes"] !== $asset["protected"]["notes"])
			{
				ViewAssetHistory_AddContextDiff($val, "plugin_asset_story_notes", $prevasset["protected"]["notes"], $asset["protected"]["notes"], "%s in notes.", "");
			}
		}
	}

	// Register to receive callbacks for the various events.
	if (class_exists("EventManager", false) && isset($em))
	{
		$em->Register("addeditasset_story_contentopts_pre_body", "Plugin_Asset_Story_Notes::Display");
		$em->Register("saveasset_ready", "Plugin_Asset_Story_Notes::Save");
		$em->Register("viewassethistory_process_pre_lang", "Plugin_Asset_Story_Notes::History");
	}
?>
```

Example plugin that adds selectable CSS classes to the story asset content editor:

```php
<?php
	class Plugin_CustomCSSClasses
	{
		public static function OutputCSS()
		{
?>
<style type="text/css">
p.box { border: 1px solid #CCCCCC; background-color: #EEEEEE; padding: 0.5em; }
</style>
<?php
		}

		public static function PreEditor()
		{
?>
ContentTools.StylePalette.add([
	new ContentTools.Style('Box', 'box', ['p'])
]);
<?php
		}
	}

	// Register to receive callbacks for the various events.
	if (class_exists("EventManager", false) && isset($em))
	{
		$em->Register("addeditasset_story_css", "Plugin_CustomCSSClasses::OutputCSS");
		$em->Register("addeditasset_story_pre_editor", "Plugin_CustomCSSClasses::PreEditor");
	}
?>
```

Example plugin that adds a custom template to the Embed dialog:

```php
	class Plugin_CustomEmbedTemplate
	{
		public static function PreEditor()
		{
			// Demonstrates all of the possible field types for custom embed dialog templates.
			// Doesn't do anything particularly useful.
?>
ContentTools.EMBED_TEMPLATES.push({
	'name': 'Test template',
	'fields': [
		{ 'title': 'Text box', 'type': 'text', 'name': 'test1', 'default': '' },
		{ 'title': 'Switch', 'type': 'switch', 'name': 'test2', 'default': true },
		{ 'title': 'Select', 'type': 'select', 'name': 'test3', 'options': { 'opt_1': 'Option 1', 'opt_2': 'Option 2', 'opt_3': 'Option 3' }, 'default': 'opt_2' },
		{ 'title': 'Textarea', 'type': 'textarea', 'name': 'test4', 'default': '', 'size': 'large' },
		{ 'title': 'File', 'type': 'file', 'name': 'test5', 'default': false, 'required': true }
	],
	'content': function(fieldmap) {
		var html = '<div class="test">\n';
		html += 'Test 1:  ' + fieldmap['test1'].value + '\n';
		html += 'Test 2:  ' + (fieldmap['test2'].value ? 'true' : 'false') + '\n';
		html += 'Test 3:  ' + fieldmap['test3'].value + '\n';
		html += 'Test 4:  ' + fieldmap['test4'].value + '\n';
		html += 'Test 5:  ' + fieldmap['test5'].value.label + '\n';
		html += '</div>';

		return html;
	}
});
<?php
		}
	}

	// Register to receive callbacks for the various events.
	if (class_exists("EventManager", false) && isset($em))
	{
		$em->Register("addeditasset_story_pre_editor", "Plugin_CustomEmbedTemplate::PreEditor");
	}
?>
```

See the [EventManager documentation](https://github.com/cubiclesoft/php-misc/blob/master/docs/event_manager.md) for more information on registering and managing events.

Language Packs
--------------

The Barebones CMS administrative interface also has multilingual support built-in.  The software comes with a small translation file for U.S. English dates and times in `lang/en_us/main.php`.

```php
<?php
	// The U.S. English default translation map.  Necessary for some international-specific display options (e.g. 12- versus 24-hour clock).
	// (C) 2018 CubicleSoft.  All Rights Reserved.

	$bb_langmap[$lang]["Y-m-d"] = "m/d/y";
	$bb_langmap[$lang]["H:i"] = "g:i a";
?>
```

For translating the entire interface, it is recommended to write a global function called `BB_Untranslated($args)` in an 'index_hook.php' file that writes untranslated strings to disk.  From there, map each untranslated string to the correct translation in a similar fashion to the above.  When no more strings are written out to disk, the new language will probably have around 80% code coverage.
