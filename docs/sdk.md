Barebones CMS SDK Documentation
===============================

The Barebones CMS SDK is the swiss army knife of tools for accessing the Barebones CMS API.  It's lightweight but contains powerful functionality for creating and managing assets stored in the API.

The SDK is utilized heavily by the Barebones CMS administrative interface to talk to the API.

The official Barebones CMS PHP SDK supports [Remoted API Server](https://github.com/cubiclesoft/remoted-api-server).  The PHP SDK class can also be extended to provide additional functionality (i.e. most functions are only protected).  The default behavior of the PHP SDK is to remain connected to the server so that multiple requests to the API can be handled efficiently.

BarebonesCMS::__construct()
---------------------------

Access:  public

Parameters:  None.

Returns:  Nothing.

This function initializes the BarebonesCMS class.  Some additional classes may be loaded here.  Note that not all of the SDK requires classes to be loaded (e.g. various static functions).

BarebonesCMS::SetAccessInfo($host, $apikey, $apisecret = false)
---------------------------------------------------------------

Access:  public

Parameters:

* $host - A string containing the host to connect to.
* $apikey - A string containing the API key to use.
* $apisecret - A string containing the API secret to use to sign requests or a boolean of false for read-only API keys (Default is false).

Returns:  Nothing.

This function sets the access information for later use with API calls.

Some functions such as `GetAssets()` only require read only access while other functions such as `GetRevisions()` and `StoreAsset()` require write access.  See the [Barebones CMS API documentation](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/api.md).

This function also reinitializes internal structures, which will trigger a disconnect from the server if an API call has already been made.

BarebonesCMS::CheckAccess()
---------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function retrieves information about the API related to the credentials used in `SetAccessInfo()`.

Example:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	var_dump($cms->CheckAccess());
?>
```

BarebonesCMS::NormalizeAsset($info)
-----------------------------------

Access:  public static

Parameters:

* $info - An array containing an asset to normalize.

Returns:  An array containing a normalized asset.

This static function normalizes an asset for use by an application.  It is recommended to call this function for every asset retrieved by `GetAssets()` and `GetRevisions()` to avoid having to check for the existence of specific keys.

BarebonesCMS::GetAssets($options, $limit = false, $bulkcallback = false)
------------------------------------------------------------------------

Access:  public

Parameters:

* $options - An array containing options.
* $limit - An integer containing the number of assets to retrieve or a boolean of false (Default is false).
* $bulkcallback - A valid callback function for processing a single asset (Default is false).  The callback function must accept one parameter - callback($data).

Returns:  A standard array of information.

This function retrieves selected assets based on the input options.  When a bulk callback is supplied, it is called for each asset as it arrives over the network, which allows for efficient processing of the data.  When a limit is not supplied, the API returns 25 assets.

The `$options` array accepts the following options:

* tag - A string or an array of tags to search for.  Prefix ~ to perform a partial match.  Prefix ! to not allow the tag to match.
* id - A string or array of IDs to retrieve.  Prefix ! to not match a given ID.
* uuid - A string or array of UUIDs to retrieve.  Prefix ! to not match a given UUID.
* type - A string or array of types to retrieve.  Prefix ! to not match a given type.
* q - A string or array of strings to search for a phrase match in the data blobs (can be slow).  Prefix ! to not match a given query string.
* qe - A string or array of strings to search for an exact match in the data blobs (can be slow).  Prefix ! to not match a given query string.
* start - An integer containing a UNIX timestamp of the minimum publish time.
* end - An integer containing a UNIX timestamp of the maximum publish time.
* order - A string containing one of "publish", "lastupdated", or "unpublish".

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	// Find the most recently published assets with a tag that starts with "/gardening/".
	$options = array(
		"tag" => "~/gardening/",
		"start" => 1,
		"end" => time()
	);

	$result = $cms->GetAssets($options, 50);
	if (!$result["success"])
	{
		echo "Failed to load assets.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}

	$assets = $result["assets"];

	foreach ($assets as $asset)
	{
		$asset = $cms->NormalizeAsset($asset);

		$lang = $cms->GetPreferredAssetLanguage($asset, "", "en-us");
		echo $asset["id"] . " | " . $asset["uuid"] . " | " . $asset["langinfo"][$lang]["title"] . "\n";
	}
?>
```

BarebonesCMS::StoreAsset($asset, $makerevision = true)
------------------------------------------------------

Access:  public

Parameters:

* $asset - An array containing at least 'type' and one valid 'langinfo' array.
* $makerevision - A boolean indicating whether or not to create a revision (Default is true).

Returns:  A standard array of information.

This function stores an asset.

An asset contains the following standard options:

* type - A string containing the type of asset to store (e.g. 'story').
* langinfo - An array of key-value pairs where the keys are IANA language codes and the values are arrays containing language-specific information.  This array is reordered with the default language first, if it exists, and all other languages sorted alphabetically.
* langorder - An array that maintains the correct language order of langinfo.  Useful for languages that do not maintain the order of keys in JSON decoded objects.
* publish - An integer containing a UNIX timestamp of when to publish this asset (0 = Draft).
* unpublish - An integer containing a UNIX timestamp of when to unpublish this asset after it is published (0 = Never unpublish).
* tags - An array containing the tags for the asset.
* lastupdated - An immutable integer containing a UNIX timestamp of the last time this asset was updated.
* lastupdatedby - A string containing information about the user (e.g. an e-mail address) who last updated the asset.
* lastip - An immutable string containing the IP address of the user who last updated the asset.
* files - An immutable array containing file reference information about uploaded files.
* filesinfo - An array containing extra file metadata in relation to uploaded files (e.g. saved crop ratios for images, CDN tokens).
* protected - An array containing protected information.  Mostly plugins use this array and is removed by the SDK when caching content to disk.
* uuid - A string containing a UUID for the asset that's also filesystem safe.  Can be used to construct URL paths.
* id - A string containing the numeric asset ID.

The 'langinfo' array in an asset contains the following standard options:

* title - A string containing the language-specific title.
* body - A string containing the language-specific body content.
* protected - An array containing protected information.  Mostly plugins create this array and is removed by the SDK when caching content to disk.

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	// Create and publish a new story asset.
	$asset = array(
		"type" => "story",
		"langinfo" => array(
			"en-us" => array(
				"title" => "Gardening is fun!",
				"body" => "<p>Working in the dirt, planting, and even the dreaded weed pulling.  It's addictive and fun and we love it.</p>"
			)
		),
		"publish" => time(),
		"tags" => array(
			"/gardening/addict/",
			"/gardening/fun/"
		)
	);

	$result = $cms->StoreAsset($asset);
	var_dump($result);
?>
```

BarebonesCMS::GetRevisions($id, $revision = false, $bulkcallback = false, $limit = false, $offset = false)
----------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $revision - An integer containing the specific revision to retrieve or a boolean of false to retrieve multiple revisions (Default is false).
* $bulkcallback - A valid callback function for processing a single revision (Default is false).  The callback function must accept one parameter - callback($data).
* $limit - An integer containing the number of revisions to retrieve or a boolean of false (Default is false).
* $offset - An integer containing the offset or a boolean of false (Default is false).

Returns:  A standard array of information.

This function retrieves selected revisions for the specified asset.  When a bulk callback is supplied, it is called for each revision as it arrives over the network, which allows for efficient processing of the data.  When a limit is not supplied, the API returns all available revisions for the asset.

Note that the Barebones CMS API is configured to only save a certain number of revisions to keep storage requirements down.  The recommended and default number of revisions is 50 but could be more or less.

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	$result = $cms->GetRevisions(1);
	if (!$result["success"])
	{
		echo "Failed to load revisions.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}

	$revisions = $result["revisions"];

	foreach ($revisions as $revrow)
	{
		$revnum = (int)$revrow["revision"];
		$asset = $cms->NormalizeAsset($revrow["info"]);

		echo "Revision " . $revnum . ":\n";
		var_dump($asset);
	}
?>
```

BarebonesCMS::DeleteAsset($id)
------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.

Returns:  A standard array of information.

This function deletes an asset.  However, only the asset and its tags is deleted.  Associated files and revisions are left behind.  It is possible to recover a deleted asset by loading a revision and storing the asset from the revision.

It is usually a better option to set an unpublish time than to delete an asset.

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	$result = $cms->DeleteAsset(1);
	var_dump($result);
?>
```

BarebonesCMS::GetTags($options, $limit = false, $offset = false, $bulkcallback = false)
---------------------------------------------------------------------------------------

Access:  public

Parameters:

* $options - An array containing options.
* $bulkcallback - A valid callback function for processing a single revision (Default is false).  The callback function must accept one parameter - callback($data).
* $limit - An integer containing the number of revisions to retrieve or a boolean of false (Default is false).
* $offset - An integer containing the offset or a boolean of false (Default is false).

Returns:  A standard array of information.

This function retrieves matching tags and counts.  When a limit is not supplied, the API returns all results for the query.  When a bulk callback is supplied, it is called for each tag as it arrives over the network.

The `$options` array accepts the following options:

* tag - A string or an array of tags to search for.  Prefix ~ to perform a partial match.
* start - An integer containing a UNIX timestamp of the minimum publish time.
* end - An integer containing a UNIX timestamp of the maximum publish time.
* order - A string containing one of "tag", "publish", or "numtags".

BarebonesCMS::StartUpload($id, $filename, $lastupdatedby = "", $makerevision = true)
------------------------------------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing the filename to store.
* $lastupdatedby - A string containing information about the user (e.g. an e-mail address) who last updated the asset (Default is "").
* $makerevision - A boolean indicating whether or not to create a revision (Default is true).

Returns:  A standard array of information.

This function starts a file upload to the API.  Note that the input filename may not be the actual final filename.  The returned information contains the real filename to pass to `UploadFile()` and `UploadDone()`.

The specified filename must have one of the file extensions that the API is configured to allow.  For example, attempting to upload a PDF when the API only allows image file extensions will result in an error.

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	$id = 1;

	$result = $cms->StartUpload($id, "logo.png");
	if (!$result["success"])
	{
		echo "Failed to start the file upload.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}

	$filename = $result["filename"];
	$data = file_get_contents("logo.png");

	$result = $cms->UploadFile($id, $filename, 0, $data);
	if (!$result["success"])
	{
		echo "Failed to upload the file data.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}

	$result = $cms->UploadDone($id, $filename);
	if (!$result["success"])
	{
		echo "Failed to finalize the upload.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}

	var_dump($result);
?>
```

BarebonesCMS::UploadFile($id, $filename, $start, $data)
-------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing the filename to store.  Must be the filename returned from `StartUpload()`.
* $start - An integer containing the starting position to write the data.
* $data - A string containing the data to upload.

Returns:  A standard array of information.

This function continues a file upload at the specified position.  The offical Barebones CMS API by default can write data almost anywhere at any time (even after `UploadDone()` is called).  This is done for idempotent purposes, not to implement a general-purpose file system (for that, see [Cloud Storage Server](https://github.com/cubiclesoft/cloud-storage-server)).  However, for consistency across all platforms, uploading parts of files should be done in sequence from beginning to end.

BarebonesCMS::UploadDone($id, $filename)
----------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing the filename to store.  Must be the filename returned from `StartUpload()`.

Returns:  A standard array of information.

This function finishes a file upload.  The offical Barebones CMS API does no actual work here.  However, if the API has been extended with support for a Content Delivery Network (CDN), this may do something important such as notify the CDN that the file is complete.

The returned value contains the final file information, which the Barebones CMS administrative interface uses to add an item to the "Insert file" dialog.

BarebonesCMS::DownloadFile($id, $filename, $fpcallback = false, $callbackopts = false, $start = 0, $size = false, $recvratelimit = false)
-----------------------------------------------------------------------------------------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing the filename to retrieve.
* fpcallback - A valid file resource handle to write the file to, a valid callback function, or a boolean of false (Default is false).  When a callback function is used, it must accept two parameters - callback($opts, $body).
* $callbackopts - Data to pass as the first parameter to the callback function (Default is false).
* $start - An integer containing the starting position to start reading data (Default is 0).
* $size - An integer containing the amount of data to read (Default is false).
* $recvratelimit - An integer containing the amount of data per second to receive over the network (Default is false).

Returns:  A standard array of information.

This function downloads a file or part of a file via the API.  The download can be rate limited for handling tasks such as delivering large files to users.  Depending on what happens with file uploads on the API end of things (e.g. a process that moves uploaded files to a CDN), this function may or may not work.

This function can be used for dealing with files managed by Remoted API Server or where additional processing is required (e.g. resizing/cropping images).

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	$id = 1;
	$filename = "logo.png";

	$fp = fopen("logo-2.png", "wb");
	$result = $cms->DownloadFile($id, $filename, $fp);
	fclose($fp);

	if (!$result["success"])
	{
		@unlink("logo-2.png");

		echo "Failed to download the file.  Error:  " . $result["error"] . " (" . $result["errorcode"] . ")\n";

		exit();
	}
?>
```

A local file cache is highly recommended to avoid using the API for repeated binary data retrieval.

BarebonesCMS::DeleteUpload($id, $filename, $lastupdatedby = "", $makerevision = true)
-------------------------------------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing the filename of the file to delete.
* $lastupdatedby - A string containing information about the user (e.g. an e-mail address) who last updated the asset (Default is "").
* $makerevision - A boolean indicating whether or not to create a revision (Default is true).

Returns:  A standard array of information.

This function deletes a previously uploaded file.  This is a permanent operation that cannot be undone.  If any assets reference the deleted file, they will likely show broken images.

Example usage:

```php
<?php
	require_once "support/sdk_barebones_cms_api.php";

	$cms = new BarebonesCMS();
	$cms->SetAccessInfo("http://localhost/api/", "[API key here]", "[API secret here]");

	$id = 1;
	$filename = "logo.png";

	$result = $cms->DeleteUpload($id, $filename);
	var_dump($result);
?>
```

BarebonesCMS::GetPreferredLanguage($acceptlangs, $defaultlang)
--------------------------------------------------------------

Access:  public static

Parameters:

* $acceptlangs - A string in the sytle of the `Accept-Language` HTTP header.
* $defaultlangs - A string containing a default language.

Returns:  A string containing a language code.

This static function returns the preferred IANA language code based on the input information.  This function is intended for use for page level operations rather than the asset level operations.  For assets, use `GetPreferredAssetLanguage()` instead.

BarebonesCMS::GetPreferredAssetLanguage($asset, $acceptlangs, $defaultlangs, $fallbackfirstlang = true, $removestrs = array())
------------------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $asset - An array containing a normalized asset.
* $acceptlangs - A string in the sytle of the `Accept-Language` HTTP header.
* $defaultlangs - A string containing a default language or an array containing a list of default languages in order of preference.
* $fallbackfirstlang - A boolean that indicates whether or not to return the first language in the asset if no other language match exists (Default is true).
* $removestrs - An array containing a set of strings to remove from `$acceptlangs` (Default is array()).  Useful for creating inaccessible languages.

Returns:  A string containing a language code on success, a boolean of false otherwise.

This static function attempts to identify the preferred IANA language code to use based on what language options are available in the asset as well as user input via a best-match algorithm.  This function assumes a normalized asset as input.

BarebonesCMS::GetPreferredTag($tags, $prefix, $overrideprefix = false)
----------------------------------------------------------------------

Access:  public static

Parameters:

* $tags - An array of asset tags.
* $prefix - A string containing the prefix to look for.
* $overrideprefix - A string containing an override prefix to look for or a boolean of false (Default is false).

Returns:  A string containing a matching tag for the prefix (or override prefix) on success, a boolean of false otherwise.

This static function returns the first matching tag based on the input prefix(es).  The override prefix takes precedence over any previously found tag.  Used primarily for permalink calculations.

BarebonesCMS::GetDefaultMimeInfoMap()
-------------------------------------

Access:  public static

Parameters:  None.

Returns:  An array containing key-value pairs.

This static function returns a mapping between file extensions and MIME types and whether or not most web browsers can natively view the file via simple DOM tags (e.g. 'img', 'audio', 'video').

BarebonesCMS::GetFileExtension($filename)
-----------------------------------------

Access:  public static

Parameters:

* $filename - A string containing a filename.

Returns:  A string containing the file extension.

This static function returns the file extension of a filename.

BarebonesCMS::GetFileSignature($id, $path, $filename, $crop, $maxwidth, $secret)
--------------------------------------------------------------------------------

Access:  public static

Parameters:

* $id - A string containing a numeric asset ID.
* $path - A string containing a path.
* $filename - A string containing a filename.
* $crop - A string containing crop information.
* $maxwidth - A string containing a maximum width.
* $secret - A string containing a secret.

Returns:  A string containing a HMAC-SHA1 of the input information.

This static function generates a digital signature to prevent abuse of the system resources of the host that the SDK runs on.  Image manipulation operations use a lot of CPU and cache storage might be limited.

BarebonesCMS::IsValidFileSignature($id, $path, $filename, $crop, $maxwidth, $signature, $secret)
------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $id - A string containing a numeric asset ID.
* $path - A string containing a path.
* $filename - A string containing a filename.
* $crop - A string containing crop information.
* $maxwidth - A string containing a maximum width.
* $signature - A string containing a signature from user input.
* $secret - A string containing a secret.

Returns:  A boolean of true if the signature is valid, false otherwise.

This static function compares a user-supplied digital signature to the expected signature.  Can be used to prevent abuse of the system resources of the host that the SDK runs on.

BarebonesCMS::SanitizeFilename($filename)
-----------------------------------------

Access:  public static

Parameters:

* $filename - A string containing a filename to sanitize.

Returns:  A string containing a sanitized filename.

This static function sanitizes input filenames in a way that produces similar results to the API.  This function helps reduce the chance of API failures.

BarebonesCMS::CanResizeImage($mimeinfomap, $fileext)
----------------------------------------------------

Access:  public static

Parameters:

* $mimeinfomap - An array containing the result of the `GetDefaultMimeInfoMap()` function.
* $fileext - A string containing a file extension.

Returns:  A boolean of true if it is an image that the SDK can resize, false otherwise.

This static function determines whether or not a file extension represents an image file format and whether or not the SDK can resize the image.

BarebonesCMS::GetCroppedAndScaledFilename($filename, $fileext, $crop, $maxwidth)
--------------------------------------------------------------------------------

Access:  public static

Parameters:

* $filename - A string containing a filename.
* $fileext - A string containing the file extension of the filename.
* $crop - A comma-separated string containing a crop rectangle.
* $maxwidth - An integer containing the maximum width of the image.

Returns:  A string containing a new filename.

This static function calculates a filename to use for caching a cropped and resized image to a local file cache.

BarebonesCMS::GetDestCropAndSize(&$cropx, &$cropy, &$cropw, &$croph, &$destwidth, &$destheight, $srcwidth, $srcheight, $crop, $maxwidth)
----------------------------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $cropx - The variable that will store an integer containing the final upper-left corner x coordinate.
* $cropy - The variable that will store an integer containing the final upper-left corner y coordinate.
* $cropw - The variable that will store an integer containing the final crop width.
* $croph - The variable that will store an integer containing the final crop height.
* $destwidth - The variable that will store an integer containing the final image width.
* $destheight - The variable that will store an integer containing the final image height.
* $srcwidth - An integer containing the source image width.
* $srcheight - An integer containing the source image height.
* $crop - A comma-separated string containing a crop rectangle.
* $maxwidth - An integer containing the maximum image width of the final image.

Returns:  Nothing.

This static function performs calculations of the final image width and cropping region based on input image width and height, cropping region, and maximum width.

BarebonesCMS::CropAndScaleImage($data, $crop, $maxwidth)
--------------------------------------------------------

Access:  public static

Parameters:

* $data - A string containing an image to crop and scale.
* $crop - A comma-separated string containing a crop rectangle.
* $maxwidth - An integer containing the maximum image width of the final image.

Returns:  A standard array of information.

This static function uses the best available image library (PECL Imagick, GD) to scale and crop SDK supported image formats.  It is recommended to cache the resulting image and prevent unauthorized use of this function to preserve system resources.

BarebonesCMS::DeliverFile($id, $filename, $options)
---------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing a filename.
* $options - An array containing options.

Returns:  A standard array of information when using the 'cacheonly' option, nothing otherwise.

This function delivers a file from the API to a web browser with support for local caching, cropping/scaling (images), partial data (Range requests), and rate limiting.

The `$options` array accepts the following options:

* cachedir - A string containing a valid directory path on the local system for caching (Default is false).
* apidir - A string containing a valid directory path on the local system (Default is false).  Only useful if the SDK is being used on the same system as the API and the "path" option is also used.
* maxcachefilesize - An integer containing the maximum size of files on disk when caching locally (Default is 10000000).
* path - A string containing a numeric value of the subdirectory to store/retrieve the cached file to/from (Default is "").
* download - A string that contains the filename to use for the download or a boolean of false (Default is false).
* $crop - A comma-separated string containing a crop rectangle (Default is "").
* $maxwidth - An integer containing the maximum image width of the final image (Default is -1).
* $mimeinfomap - An array containing the result of the `GetDefaultMimeInfoMap()` function (Default is `self::GetDefaultMimeInfoMap()`).
* $recvratelimit - An integer containing the amount of data per second to receive over the network (Default is false).
* $cacheonly - A boolean that indicates whether or not to only cache the retrieved file(s) locally (Default is false).

See the [Frontend Patterns documentation](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/frontend-patterns.md) for example usage.

BarebonesCMS::PrecacheDeliverFile($id, $filename, $options)
-----------------------------------------------------------

Access:  public

Parameters:

* $id - A string containing a numeric asset ID.
* $filename - A string containing a filename.
* $options - An array containing options.

Returns:  A standard array of information.

This function precaches a file locally for direct delivery to the web browser later.  It returns a URL that may bypass using the API and use direct delivery, which results in fewer requests to both the API and PHP.

The `$options` array accepts the following options:

* cachedir - A string containing a valid directory path on the local system for caching (Default is false).
* cacheurl - A string containing a base URL that points at the same directory as 'cachedir' (Default is false).
* apidir - A string containing a valid directory path on the local system (Default is false).  Only useful if the SDK is being used on the same system as the API and the "path" option is also used.
* apiurl - A string containing a base URL that points at the same directory as 'apidir' (Default is false).
* getfileurl - A string containing a base URL that handles file delivery via the API (Default is false).
* getfilesecret - A string containing a secret to use for digital signatures for file URLs (Default is false).
* download - A string that contains the filename to use for the download or a boolean of false (Default is false).

Used by the `TransformStoryAssetBody()` function to generate correct URLs for files stored in the API given an input context.

BarebonesCMS::CleanFileCache($cachedir, $keepfor = 259200)
----------------------------------------------------------

Access:  public static

Parameters:

* $cachedir - A string containing a path to a local file cache used by `DeliverFile()`.
* $keepfor - An integer representing the number of seconds to keep files in the local file cache (Default is 3 days, 3 * 24 * 60 * 60 = 259200).

Returns:  Nothing.

This static function is intended to be used periodically (e.g. a cron job/scheduled task).  This function should probably not be called when using `PrecacheDeliverFile()` as it may delete file data that is referenced by cached content.

Used by the Barebones CMS administrative interface.

BarebonesCMS::Internal_TransformStoryAssetBodyCallback($stack, &$content, $open, $tagname, &$attrs, $options)
-------------------------------------------------------------------------------------------------------------

Access:  _internal_ public

Parameters:  Standard TagFilterStream 'tag_callback' parameters.

Returns:  An array indicating whether or not to keep a tag and/or its interior content.

This internal function is a standard TagFilterStream 'tag_callback' callback that processes the input tag to resolve it against standard Barebones CMS administrative interface output and frontend usage.

Correctly handles 'div-embed' tags and 'data-src-info' attributes to generate the final HTML suitable for display in a web browser.  Also removes any unwanted content and tags.

BarebonesCMS::TransformStoryAssetBody($asset, $options = array(), $lang = false)
--------------------------------------------------------------------------------

Access:  public

Parameters:

* $asset - An array containing a normalized story asset.
* $options - An array containing options for transforming story bodies (Default is array()).
* $lang - A string containing a specific language in the asset 'langinfo' array to translate or a boolean of false to translate all languages (Default is false).

Returns:  A modified asset ready for displaying HTML in a web browser.

This function transforms story asset body HTML into a format suitable for display in a web browser.  The format stored by the Barebones CMS administrative interface requires transformation since there no guarantees about where content will be displayed.  Things such as images require different URL structures for serving them to the Barebones CMS administrative interface which are different from what the public will see.

This function also cleans up and removes unwanted content and tags for clean output to the web browser.

The `$options` array accepts the following options:

* trimcontent - A comma-separated string of tags to trim content of spurious whitespace (Default is "p,h1,h2,h3,h4,h5,h6,blockquote,pre,li").
* removeempty - A comma-separated string of tags to remove if they are empty - that is, no tags or content (Default is "p,h1,h2,h3,h4,h5,h6,blockquote,pre,ol,ul,li,table,thead,tbody,tr").
* processdivembed - A boolean that indicates whether or not to process 'div-embed' tags (Default is true).
* keepsrcinfo - A boolean that indicates whether or not to keep 'data-src-info' attributes (Default is false).
* usedatamaxwidth - A boolean that indicates whether or not to use 'data-max-width' when processing images (Default is true).
* maxwidth - An integer containing the maximum image width of images (Default is -1).
* mimeinfomap - An array containing the result of the `GetDefaultMimeInfoMap()` function (Default is `self::GetDefaultMimeInfoMap()`).
* cachedir - A string containing a valid directory path on the local system for caching (Default is false).
* cacheurl - A string containing a base URL that points at the same directory as 'cachedir' (Default is false).
* apidir - A string containing a valid directory path on the local system (Default is false).  Only useful if the SDK is being used on the same system as the API and the "path" option is also used.
* apiurl - A string containing a base URL that points at the same directory as 'apidir' (Default is false).
* getfileurl - A string containing a base URL that handles file delivery via the API (Default is false).
* getfilesecret - A string containing a secret to use for digital signatures for file URLs (Default is false).
* em - An instance of the EventManager class (Default is null).
* emfire - A string containing the prefix to use when firing events (Default is "").
* siteurl - A string containing a URL to replace "site://" prefixes with (Default is false).

The modified asset should never be stored via the API unless you really know what you are doing.

See the [Frontend Patterns documentation](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/frontend-patterns.md) for example usage.

BarebonesCMS::GenerateStoryAssetSummary($asset, $options = array(), $lang = false)
----------------------------------------------------------------------------------

Access:  public static

Parameters:

* $asset - An array containing a normalized, transformed story asset.
* $options - An array containing options for generating the summary (Default is array()).
* $lang - A string containing a specific language in the asset 'langinfo' array to generate a summary for or a boolean of false to generate summaries for all languages (Default is false).

Returns:  A modified asset ready for displaying a summary in a web browser.

This static function extracts the first image and first couple of paragraphs for use as a summary in a list view of assets.  The input story asset should have been transformed previously with `TransformStoryAssetBody()`.

The `$options` array accepts the following options:

* paragraphs - An integer specifying the number of paragraphs to extract (Default is 2).
* html - A boolean that indicates whether or not to extract the paragraphs as HTML or plain text (Default is true).
* keepbody - A boolean that indicates whether or not to keep the body after the summary has been extracted (Default is false).

The modified asset should never be stored via the API.

See the [Frontend Patterns documentation](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/frontend-patterns.md) for example usage.

BarebonesCMS::CanRefreshContent($validtoken, $requestkey = "refresh")
---------------------------------------------------------------------

Access:  public static

Parameters:

* $validtoken - A string containing a token that allows for live content refreshing.
* $requestkey - A string containing the refresh key to look for across superglobals and an active session (Default is "refresh").

Returns:  A boolean indicating whether or not the user can refresh content via the API.

This static function is used to check for a valid content refresh token for performing cache busting on a website frontend.  It's a fast, efficient function that bails out as soon as it is obvious that the request doesn't include the expected information.  Also handles heartbeat logic for those who do have a valid token to keep their session alive on a regular basis.

BarebonesCMS::OutputHeartbeat($every = 300)
-------------------------------------------

Access:  public static

Parameters:

* $every - An integer representing the interval, in seconds, at which to send a heartbeat to the server.

Returns:  Nothing.

This function outputs inline Javascript if a previous call to `CanRefreshContent()` succeeded.  The generated code periodically sends a heartbeat to keep the underlying PHP session alive.

BarebonesCMS::GetAdminEditURL($adminurl, &$asset, $lang = false)
----------------------------------------------------------------

Access:  public static

Parameters:

* $adminurl - A string containing a URL to the Barebones CMS administrative interface.
* $asset - An array containing a normalized asset.
* $lang - A string containing a specific language in the asset 'langinfo' array to link to or a boolean of false to use whatever the admin decides to use (Default is false).

Returns:  A string containing a URL suitable for use in a link.

This static function generates a 'wantaction' based Barebones CMS administrative interface URL that safely routes the request to the correct location if the user clicks the link.

BarebonesCMS::OutputPageAdminEditButton($adminurl, &$asset, $lang = false, $vertpos = "bottom", $horzpos = "right")
-------------------------------------------------------------------------------------------------------------------

Access:  public static

Parameters:

* $adminurl - A string containing a URL to the Barebones CMS administrative interface.
* $asset - An array containing a normalized asset.
* $lang - A string containing a specific language in the asset 'langinfo' array to link to or a boolean of false to use whatever the admin decides to use (Default is false).
* $vertpos - A string containing one of "top" or "bottom" (Default is "bottom").
* $horzpos - A string containing one of "left" or "right" (Default is "right").

Returns:  Nothing.

This static function outputs a link to the Barebones CMS administrative interface that looks like a clickable button using inline CSS styles.  It's not meant to be fancy, just meant to work.  The default button appears in the lower right corner of the screen to stay out of the way of the content as best as possible.

BarebonesCMS::CacheAssets($contentdir, $type, $key, $assets)
------------------------------------------------------------

Access:  public static

Parameters:

* $contentdir - A string containing a valid directory path on the local system to use for an asset content cache.
* $type - A string containing the type of page that retrieved the content.
* $key - An array containing the key to the content (e.g. the options passed to the API).
* $assets - An array containing a set of assets to cache to disk.

Returns:  Nothing.

This static function removes sensitive information from the assets and stores them to disk for later retrieval with `LoadCachedAssets()`.

The specified content directory should be different from a directory used for files retrieved from the API and cached.

BarebonesCMS::GetCachedAssetsFilename($contentdir, $type, $key)
---------------------------------------------------------------

Access:  public static

Parameters:

* $contentdir - A string containing a valid directory path on the local system to use for an asset content cache.
* $type - A string containing the type of page that retrieved the content.
* $key - An array containing the key to the content (e.g. the options passed to the API).

Returns:  A string containing the full path and filename to the associated cached file.

This static function calculates and returns the final path and filename for a cached file location.

BarebonesCMS::LoadCachedAssets($contentdirfilename, $type = false, $key = false)
--------------------------------------------------------------------------------

Access:  public static

Parameters:

* $contentdirfilename - A string containing a valid directory path on the local system to use for an asset content cache or a string containing the result of `GetCachedAssetsFilename()`.
* $type - A string containing the type of page that retrieved the content or a boolean of false (Default is false).
* $key - An array containing the key to the content (e.g. the options passed to the API) or a boolean of false (Default is false).

Returns:  An array containing a set of assets retrieved from the associated disk cache on success, an empty array otherwise.

This static function loads cached assets into memory.  Each asset is checked against its publish/unpublish times before including the asset in the resulting array.

The `$type` and `$key` options should be false when `$contentdirfilename` is the result of `GetCachedAssetsFilename()`.

BarebonesCMS::Internal_BulkCallback($response, $body, &$opts)
-------------------------------------------------------------

Access:  _internal_

Parameters:

* $response - An array containing the current HTTP response line information.
* $body - A string containing the retrieve body content.
* $opts - An array containing callback information.

Returns:  A boolean of true.

This internal function handles bulk callback data as it arrives.  As soon as each item becomes available, the user supplied bulk callback is called with the decoded JSON data.

BarebonesCMS::ProcessInfoDefaults($info, $defaults)
---------------------------------------------------

Access:  public static

Parameters:

* $info - An array to merge defaults into if keys are missing.
* $defaults - An array containing default key-value pairs.

Returns:  A merged array of key-value pairs.

This function merges missing defaults into an array of information.  This function is useful for loading serialized data from a database into a consistent format.  The use of this type of approach reduces potential code breakage when new fields are added.

BarebonesCMS::Internal_AppendGetAssetsOption(&$apipath, &$options, $key)
------------------------------------------------------------------------

Access:  private static

Parameters:

* $apipath - A string containing an API path/key.
* $options - An array of options.
* $key - A string containing a key to look for and process in the options array.

Returns:  Nothing.

This internal function is called by `GetAssets()` to simplify the call to the API and support a wide variety of inputs.

BarebonesCMS::Internal_DownloadCallback($response, $body, &$opts)
-----------------------------------------------------------------

Access:  _internal_

Parameters:

* $response - An array containing the current HTTP response line information.
* $body - A string containing the retrieve body content.
* $opts - An array containing file resource or callback information.

Returns:  A boolean of true.

This internal function processes downloaded file data as it is retrieved from the API.

BarebonesCMS::Internal_DeliverFileDownloadCallback($state, $body)
-----------------------------------------------------------------

Access:  _internal_

Parameters:

* $state - An object containing state information.
* $body - A string containing retreived data.

Returns:  Nothing.

This internal function handles incoming download data for `DeliverFile()`.  Depending on the state object, the data may be appended to a string, output to the browser, and/or saved to disk.

BarebonesCMS::ProcessRateLimit($size, $start, $limit, $async)
-------------------------------------------------------------

Access:  private static

Parameters:

* $size - An integer containing the number of bytes transferred.
* $start - A numeric value containing a UNIX timestamp of a start time.
* $limit - An integer representing the maximum acceptable rate in bytes/sec.
* $async - A boolean indicating whether or not the function should not sleep (async caller).

Returns:  An integer containing the amount of time to wait for (async only), -1 otherwise.

This internal static function calculates the current rate at which bytes are being transferred over the network.  If the rate exceeds the limit, it calculates exactly how long to wait and then sleeps for that amount of time so that the average transfer rate is within the limit.

BarebonesCMS::CMS_Translate($format, ...)
-----------------------------------------

Access:  _internal_ static

Parameters:

* $format - A string containing valid sprintf() format specifiers.

Returns:  A string containing a translation.

This internal static function takes input strings and translates them from English to some other language if CS_TRANSLATE_FUNC is defined to be a valid PHP function name.

BarebonesCMS::RunAPI($method, $apipath, $options = array(), $expected = 200, $encodejson = true, $decodebody = true)
--------------------------------------------------------------------------------------------------------------------

Access:  protected

Parameters:

* $method - A string containing a HTTP method.
* $apipath - An array containing API information to send.
* $options - An array containing JSON-safe options to send or WebBrowser options, depending on the value of `$encodejson` and the request method (Default is array()).
* $expected - An integer containing the expected server response code to the request (Default is 200).
* $encodejson - A boolean that indicates whether or not to encode the `$options` array as JSON (Default is true).  Only valid for non-GET requests.
* $decodebody - A boolean that indicates whether or not to decode the body response as JSON (Default is true).

Returns:  A standard array of information.

This protected function runs a query against the API.  This is the network workhorse for the rest of the SDK.
