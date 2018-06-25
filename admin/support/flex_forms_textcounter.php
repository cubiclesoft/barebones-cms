<?php
	// Add a text counter option to text elements.
	// (C) 2017 CubicleSoft.  All Rights Reserved.

	class FlexForms_TextCounter
	{
		public static function Init(&$state, &$options)
		{
			if (!isset($state["modules_textcounter"]))  $state["modules_textcounter"] = false;
		}

		public static function FieldType(&$state, $num, &$field, $id)
		{
			if (($field["type"] === "text" || $field["type"] === "textarea") && isset($field["counter"]) && (is_int($field["counter"]) || $field["counter"]))
			{
				// Alter the field.
				if (isset($field["desc"]))
				{
					$field["htmldesc"] = htmlspecialchars($field["desc"]);
					unset($field["desc"]);
				}

				if (isset($field["htmldesc"]))  $field["htmldesc"] .= "<br>";
				else  $field["htmldesc"] = "";

				$field["htmldesc"] .= "<span id=\"" . $id . "_textcounter\"></span>";

				if ($state["modules_textcounter"] === false)
				{
					$state["css"]["modules-textcounter"] = array("mode" => "link", "dependency" => false, "src" => $state["supporturl"] . "/jquery.textcounter.css");
					$state["js"]["modules-textcounter"] = array("mode" => "src", "dependency" => "jquery", "src" => $state["supporturl"] . "/jquery.textcounter.js", "detect" => "jQuery.fn.TextCounter");

					$state["modules_textcounter"] = true;
				}

				$options = array(
					"target" => "#" . $id . "_textcounter"
				);

				if (is_int($field["counter"]))  $options["limit"] = $field["counter"];

				// Allow each TextCounter instance to be fully customized beyond basic support.
				// Valid options:  See 'jquery.textcounter.js' file.
				if (isset($field["counter_options"]))
				{
					foreach ($field["counter_options"] as $key => $val)  $options[$key] = $val;
				}

				// Queue up the necessary Javascript for later output.
				ob_start();
?>
			jQuery(function() {
				var options = <?php echo json_encode($options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>;

<?php
				if (isset($field["counter_callbacks"]))
				{
					foreach ($field["counter_callbacks"] as $key => $val)
					{
?>
				options['<?php echo $key; ?>'] = <?php echo $val; ?>;
<?php
					}
				}
?>

				jQuery('#<?php echo FlexForms::JSSafe($id); ?>').TextCounter(options);
			});
<?php
				$state["js"]["modules-textcounter|" . $id] = array("mode" => "inline", "dependency" => "modules-textcounter", "src" => ob_get_contents());
				ob_end_clean();
			}
		}
	}

	// Register form handlers.
	if (is_callable("FlexForms::RegisterFormHandler"))
	{
		FlexForms::RegisterFormHandler("init", "FlexForms_TextCounter::Init");
		FlexForms::RegisterFormHandler("field_type", "FlexForms_TextCounter::FieldType");
	}
?>