Barebones CMS API Documentation
===============================

This is the in-depth technical documentation for the Barebones CMS API.  The API is, for all intents and purposes, Barebones CMS and is responsible for storing and retrieving assets, revisions, tags, and files.

To install Barebones CMS API, see the [Installation Guide](install.md).

Basic Usage
-----------

An example retrieving assets with cURL from a Linux command-line:

```
curl -H 'X-APIKey: APIKEY' 'https://demo.barebonescms.com/sessions/0.0.0.0/some-words/api/?ver=1&api=assets&start=1&end=`date +%s`&limit=50'
```

A more complex request/response cycle:

```
------- RAW SEND START -------
GET /sessions/0.0.0.0/some-words/api/?ver=1&ts=1530330685&api=assets&start=1&end=1530330685&limit=50 HTTP/1.1
Host: demo.barebonescms.com
Connection: keep-alive
Accept: text/html, application/xhtml+xml, */*
Accept-Language: en-us,en;q=0.5
Cache-Control: max-age=0
User-Agent: Mozilla/5.0 (Windows NT 6.1; WOW64; rv:38.0) Gecko/20100101 Firefox/38.0
X-Apikey: WsXEd3vBXYSTaORT2iBfj6ENkpRHpCJtyJ7PyfEMrf5FW7GBNyyLQ01EcRxS6IpN

------- RAW SEND END -------

------- RAW RECEIVE START -------
HTTP/1.1 200 OK
Server: nginx/1.14.0
Date: Sat, 30 Jun 2018 03:51:25 GMT
Content-Type: application/json
Transfer-Encoding: chunked
Connection: keep-alive
X-Powered-By: PHP/7.2.7

650
{"success":true,"assets":[{"type":"story","langinfo":{"en-us":{"title":"A good story needs a good headline like this one!","body":"<p>\n    Good stories start with good headlines. For instance, if you want to share about gardening adventures, you might choose a headline like, \"End your brown thumb with these gardening success stories!\" and use a photo like:\n</p>\n<img data-src-info=\"{&quot;id&quot;:1,&quot;file&quot;:{&quot;path&quot;:&quot;17712&quot;,&quot;filename&quot;:&quot;back-yard-250890_1920.jpg&quot;,&quot;origfilename&quot;:&quot;back-yard-250890_1920.jpg&quot;,&quot;size&quot;:1577040,&quot;created&quot;:1530330596,&quot;modified&quot;:1530330605},&quot;fileinfo&quot;:{&quot;crops&quot;:{&quot;&quot;:{&quot;x&quot;:0,&quot;y&quot;:0,&quot;x2&quot;:1,&quot;y2&quot;:1},&quot;16:9&quot;:{&quot;x&quot;:0.5495283018867925,&quot;y&quot;:0.0809748427672956,&quot;x2&quot;:1,&quot;y2&quot;:0.41882861635220126},&quot;3:4&quot;:{},&quot;1:1&quot;:{}}},&quot;cropselected&quot;:&quot;16:9&quot;,&quot;crop&quot;:&quot;0.5495283018867925,0.0809748427672956,1,0.41882861635220126&quot;}\" src=\"//0.0.0.0/transform.gif\">"}},"publish":1530330540,"unpublish":0,"tags":["/gardening/"],"lastupdated":1530330630,"lastupdatedby":"","lastip":"000.000.000.0","filesinfo":{"back-yard-250890_1920.jpg":[]},"protected":[],"uuid":"17712-a-good-story-needs-a-good-headline-like-this","langorder":["en-us"],"id":"1","files":{"back-yard-250890_1920.jpg":{"path":"17712","filename":"back-yard-250890_1920.jpg","origfilename":"back-yard-250890_1920.jpg","size":1577040,"created":1530330596,"modified":1530330605}}}]}
0

------- RAW RECEIVE END -------
```

[Using an SDK](sdk.md) is the preferred method to access the Barebones CMS API from an application development perspective.  Simple GET requests can be performed using tools such as cURL as seen above but many request types require digital signatures.  In addition, assets created by the Barebones CMS admin interface generally require a final transformation prior to delivery to the client.

Architecture
------------

[![Architecture overview diagram](docs/images/architecture_diagram.png?raw=true "Barebones CMS architecture diagram")](https://www.youtube.com/watch?v=uybGZ0V-tYY "Barebones CMS Architecture Overview")

The API is the core of the product.  The [Barebones CMS SDK](sdk.md) accesses the API to perform various tasks, including [content delivery](frontend-patterns.md) to website visitors.  The [Barebones CMS administrative interface](overview.md) obviously steals the spotlight from a content creation and editing perspective as it transparently uses the Barebones CMS SDK to access the API.

How It Works
------------

The API accepts inputs a variety of different ways.  Standard GET, standard POST (either `application/x-www-form-urlencoded` or `multipart/form-data`), `Content-type: application/json`, and "rest_data".  All input data is normalized.  The last option allows for a single parameter named "rest_data" to be a valid JSON encoded object.  If there is a problem with the data, the response error code is `invalid_input`.

The next section of the API verifies credentials by checking for and validating the "X-APIKey" and optional "X-Signature" headers.  The API supports two different API keys:

* Read-only access.  The read-only API key grants an unlimited number requests to the API but is restricted to retrieving assets and files.
* Read-write access.  The read-write API key plus a digital signature for the request using the read-write secret grants the same rights as read only access and also full access to manage and modify assets, tags, and files.

If an invalid API key is specified, the response error code is 'missing_or_invalid_apikey'.

The input data requires 'ver' to be specified.  The current version of the API is 1.  If the version does not match, the error code is 'expected_ver_1'.

When a digital signature is used, the input data requires 'ts' to be an integer containing the current UNIX timestamp.  There is an allowed five minutes of system clock drift in either direction.  Requiring a timestamp prevents replay attacks.

The input data requires 'api' to be a string.  If the 'api' is missing or invalid, the error code is 'missing_or_invalid_api'.

The rest of the API is encapsulated in the `BB_API_Helper` class, which processes the appropriate API call.  This class can also be used from a command-line script to manage assets directly on the server without making API calls.

Most of the functionality for the API is found in 'support/bb_functions.php'.

Some APIs have bulk retrieval support, which can be used to efficiently retrieve all of the information in the system at once.  Each JSON encoded item is returned on its own line of output when bulk retrieval is used.

Overriding BB_API_Helper
------------------------

For small to medium sites, the default `BB_API_Helper` class is fine as-is.  For larger sites, overriding the default behavior of the API can be done for things such as moving file uploads to a CDN.  Overriding the API is a matter of creating a new file called "bb_api_helper_override.php" in the same directory as the API and creating a class called `BB_API_Helper_Override`:

```php
	class BB_API_Helper_Override extends BB_API_Helper
	{
		// Override existing methods here as needed.
	}
```

Configuration File Options:  config.php
---------------------------------------

* rootpath - A string containing an absolute path to the base API directory.  Should not contain a trailing slash.
* rooturl - A string containing an absolute URL to the base API.  Should contain a trailing slash.
* read_apikey - A string containing a randomly generated token to use for read-only access to the API.
* readwrite_apikey - A string containing a randomly generated token to use for read-write access to the API.  Note that only correctly signed requests using the read-write secret can successfully alter content.
* readwrite_secret - A string containing a randomly generated token to use for verifying digitally signed requests to the API.
* db_select - A string containing a valid database string from `BB_GetSupportedDatabases()`.
* db_dsn - A string containing a valid DSN for the database.
* db_login - A boolean that determines whether or not login information is to be used for connecting to the database.
* db_user - A string containing the username for the database.
* db_pass - A string containing the password for the database.
* db_name - A string containing the database name to USE.
* db_table_prefix - A string containing the table prefix in the database to use.
* db_master_dsn - A string containing a valid DSN for the master database.
* db_master_user - A string containing the username for the master database.
* db_master_pass - A string containing the password for the master database.
* files_path - A string containing an absolute path to the base directory to store files.  Should not contain a trailing slash.
* files_url - An optional string containing an absolute URL to the base directory where files are stored.  Should not contain a trailing slash.
* file_exts - A string of semicolon-separated file extensions to accept for file uploads.
* default_lang - A string containing the default IANA language code to use.  This impacts the ordering of languages in multilingual assets.
* max_revitions - An integer containing the maximum number of revisions to keep per asset.
* css_feeds_host - An optional string containing a URL to a Cloud Storage Server /feeds host.
* css_feeds_apikey - A string containing the API key of the user with the /feeds extension enabled.
* css_feeds_name - A string containing the name to trigger for /feeds notifications.

Web Server Compatability
------------------------

While Apache natively and correctly routes requests for non-GET/POST methods to PHP, not all web servers do so.  Specifically, the API itself supports DELETE requests to appropriate endpoints but some web servers will return errors for RESTful requests to PHP.

Where a DELETE request is specified in this documentation, there is also an equivalent GET request API prefixed with 'delete_'.

BB_GetSupportedDatabases()
--------------------------

Access:  global

Parameters:  None.

Returns:  An array of key-value pairs containing basic CSDB mapping information.

This global function returns the list of supported databases and sample DSN strings for each database.  Used by the installer to select a database and show the correct fields.

BB_SanitizeStr($str)
--------------------

Access:  global

Parameters:

* $str - A string to sanitize.

Returns:  The sanitized string.

This global function sanitizes strings for the API for a variety of purposes.  This function only allows lowercase letters, numbers, and hyphens through.

BB_ExtractLanguage($str, $default)
----------------------------------

Access:  global

Parameters:

* $str - A string containing an Accept-Language header.
* $default - A string containing a default IANA language code.

Returns:  A string containing the first non-empty IANA language code or `$default` if no match was found.

This global function attempts to parse an Accept-Language header to determine the desired language.  Used by the installer to select the default IANA language code for the API.

BB_CloudStorageServerFeedsInit($config)
---------------------------------------

Access:  global

Parameters:

* $config - An array containing key-value configuration information.

Returns:  A standard array of information.

This global function initializes an object with Cloud Storage Server /feeds access information.

See the Configuration File Options section for details on the 'css_feeds_host' and 'css_feeds_apikey' options.

BB_CloudStorageServerFeedsNotify($css, $feedname, $type, $id, $data, $ts)
-------------------------------------------------------------------------

Access:  global

Parameters:

* $css - The return value from a `BB_CloudStorageServerFeedsInit()` call.
* $feedname - A string containing the feed to notify (usually the value of the configuration option 'css_feeds_name').
* $type - A string containing one of "insert", "update", or "delete".
* $id - A string containing the asset ID.
* $data - An array containing the new asset information and optional previous asset information.
* $ts - An integer containing the UNIX timestamp of when to send the notification.

Returns:  A standard array of information.

This global function sends a notification to the Cloud Storage Server /feeds API.

BB_CTstrcmp($secret, $userinput)
--------------------------------

Access:  global

Parameters:

* $secret - A string containing the secret (e.g. a hash).
* $userinput - A string containing user input.

Returns:  An integer of zero if the two strings match, non-zero otherwise.

This global function performs a constant-time strcmp() operation.  Constant-time string compares are used in timing-attack defenses - that is, where comparing two strings with normal functions is a security vulnerability.

BB_ConnectDB($config, $write, $full = false)
--------------------------------------------

Access:  global

Parameters:

* $config - An array containing key-value configuration information.
* $write - A boolean that indicates whether or not the database will be written to.
* $full - A boolean that indicates whether or not to load the full CSDB class (Default is false).

Returns:  A standard array of information.

This global function connects to the database specified by the configuration.  When `$full` is false, the "lite" version of the CSDB class is loaded for a lower memory footprint.  When `$write` is true and there is a master database specified, it is always connected to.

BB_API_Helper::__construct($config, $write)
-------------------------------------------

Access:  public

Parameters:

* $config - An array containing key-value configuration information.
* $write - A boolean that indicates whether or not write access to the API is allowed.

Returns:  Nothing.

This function initializes the class with configuration and write access information.

BB_API_Helper::ConnectDB()
--------------------------

Access:  public

Parameters:  None.

Returns:  A standard array of information.

This function connects to the database using the configuration and write access information supplied during the constructor and initializes table names.  Internally, this function calls `BB_ConnectDB()`.

BB_API_Helper::CreateRevision($row)
-----------------------------------

Access:  public

Parameters:

* $row - An object containing an asset row to create a revision from.

Returns:  A standard array of information.

This function creates a new revision from an asset.

BB_API_Helper::ForwardFileSeek($fp, $size)
------------------------------------------

Access:  public static

Parameters:

* $fp - A resource handle to a file.
* $size - An integer specifying the number of bytes to seek forward in the file.

Returns:  Nothing.

This static function attempts to seek forward in a file.  Depending on the platform, 32-bit PHP may or may not seek forward the proper amount in large files over 2GB.  Using 64-bit PHP is recommended.

BB_API_Helper::GetAssets($data, $outputcallback, &$outputcallbackopts)
----------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles GET requests to retrieve a set of assets.  This API has optional bulk retrieval support.

The `$data` array accepts the following options:

* api - A string containing "assets".
* limit - An integer containing the number of assets to retrieve (Default is 25).
* tag - A string containing a single tag or an array of strings containing multiple tags (Default is array()).
* id - A comma-separated string or an array containing asset IDs (Default is array()).
* uuid - A comma-separated string or an array containing asset UUIDs (Default is array()).
* type - A comma-separated string or an array containing asset types (Default is array()).
* q - A string or an array of strings containing a split phrase query to search for (Default is array()).
* qe - A string or an array of strings containing an exact query to search for (Default is array()).
* start - An integer containing the UNIX timestamp of the minimum publish time (Default is null).
* end - An integer containing the UNIX timestamp of the maximum publish time (Default is null).
* order - A string containing one of "publish", "lastupdated", or "unpublish".
* bulk - An integer indicating whether or not bulk retrieval mode should be used.

Wherever possible, use tag queries for a fast reduction of the amount of data that has to be searched.  Tags can be prefixed with "~" for a partial match (e.g. "~/gardening/" will match the tags "/gardening/tools" and "/gardening/tips").

When using read-only access credentials, this API disallows access to assets in Draft status and assets that have been unpublished.

All options can be prefixed with "!" for a negative search.  Note that negative tag searches may result in fewer than requested rows as the negative portion is only handled when processing query results.  Negative searches may also be slower and consume more resources.

All assets are returned with their associated asset ID included.  This is the only modification to the assets that is made by this API.  Clients are responsible for normalizing assets before using them.

BB_API_Helper::GetTags($data, $outputcallback, &$outputcallbackopts)
--------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles GET requests to retrieve a set of tags.  This API has optional bulk retrieval support.

The `$data` array accepts the following options:

* api - A string containing "tags".
* tag - A string containing the tag to search for.
* start - An integer containing the UNIX timestamp of the minimum asset publish time (Default is null).
* end - An integer containing the UNIX timestamp of the maximum asset publish time (Default is null).
* order - A string containing one of "tag", "publish", or "numtags".
* limit - An integer containing the maximum number of tags to retrieve.
* offset - An integer containing the offset for the results.
* bulk - An integer indicating whether or not bulk retrieval mode should be used.

When specified, the tag can be prefixed with "~" for a partial match (e.g. "~/gardening/" will match the tags "/gardening/tools" and "/gardening/tips").

When using read-only access credentials, this API disallows access to asset tags in Draft status.

BB_API_Helper::GetRevisions($data, $outputcallback, &$outputcallbackopts)
-------------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles GET requests to retrieve a set of revisions.  This API has optional bulk retrieval support.  The official Barebones CMS API requires write access credentials to retrieve revisions.

The `$data` array accepts the following options:

* api - A string containing "revisions".
* id - A string containing an asset ID.
* revision - An integer containing the exact revision number to retrieve.
* limit - An integer containing the maximum number of revisions to retrieve.
* offset - An integer containing the offset for the results.
* bulk - An integer indicating whether or not bulk retrieval mode should be used.

BB_API_Helper::DownloadFile($data)
----------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.

Returns:  A standard array of information.

This function handles GET requests to retrieve a file or a part of a file.  A file may be able to be downloaded directly without using the API for performance gains.  However, the API may be more reliable depending on usage.

The `$data` array accepts the following options:

* api - A string containing "file".
* id - A string containing an asset ID.
* filename - A string containing a filename reference to a previously uploaded file.
* start - An integer containing the position to start in the file.
* size - An integer containing the size of the data to retrieve.

The returned `Content-Type` is always `application/octet-stream` on success.

BB_API_Helper::GetMaxUploadFileSize()
-------------------------------------

Access:  public static

Parameters:  None.

Returns:  An integer containing the maximum upload file size allowed in a single request to PHP.

This static function determines the maximum allowed file/chunk upload size that PHP allows on the current host.

BB_API_Helper::ConvertUserStrToBytes($str)
------------------------------------------

Access:  public static

Parameters:

* $str - A string containing a size.

Returns:  An integer containing the expanded number of bytes.

This static function converts a string from a compact size format (e.g. "12MB") to an integer value (e.g. 12582912).  Useful for expanding values stored in the PHP INI file.

BB_API_Helper::GetAccessInfo($outputcallback, &$outputcallbackopts)
-------------------------------------------------------------------

Access:  public

Parameters:

* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles GET requests to retrieve API access information.

The 'api' value for this call is a string containing "access".

BB_API_Helper::DeleteUpload($data, $outputcallback, &$outputcallbackopts)
-------------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles DELETE requests to remove an uploaded file.  Requires write access.

The `$data` array accepts the following options:

* api - A string containing "upload".
* id - A string containing an asset ID.
* filename - A string containing a filename reference to a previously uploaded file.
* makerevision - An integer that indicates whether or not to create a revision.
* lastupdatedby - A string containing information about the user (e.g. an e-mail address).

Note that Cloud Storage Server /feeds is not called with updated information for file upload management.

BB_API_Helper::DeleteAsset($data, $outputcallback, &$outputcallbackopts)
------------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles DELETE requests to remove an asset.  Requires write access.  Does not delete files.

The `$data` array accepts the following options:

* api - A string containing "asset".
* id - A string containing an asset ID.
* makerevision - An integer that indicates whether or not to create a revision.

Prefer setting an unpublish date/time or removing the publish date/time and reverting to Draft status over deletion.  Assets are not actually deleted and can be recovered using the revision system.  To truly delete assets, create a cron job that periodically locates orphaned revisions and also deletes associated files if desired.

BB_API_Helper::StoreAsset($data, $outputcallback, &$outputcallbackopts)
-----------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles POST requests to store an asset.  Requires write access.

The `$data` array accepts the following options:

* api - A string containing "asset".
* asset - An array containing an asset.
* makerevision - An integer that indicates whether or not to create a revision.

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

Validates and normalizes each asset as best as possible prior to storing it.  It is up to the client to normalize the asset when retrieving assets so that the data is in a consistent format for the application.

BB_API_Helper::StartUpload($data, $outputcallback, &$outputcallbackopts)
------------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles POST requests to start file uploads.  Requires write access.

The `$data` array accepts the following options:

* api - A string containing "upload_start".
* id - A string containing an asset ID.
* filename - A string containing a filename with an allowed file extension.
* makerevision - An integer that indicates whether or not to create a revision.
* fileinfo - An array containing extra file metadata in relation to the new file being uploaded (e.g. EXIF data).
* lastupdatedby - A string containing information about the user (e.g. an e-mail address).

Also creates the necessary directory structure and a zero byte file for later API calls to the upload handler.

BB_API_Helper::ProcessUpload($data, $outputcallback, &$outputcallbackopts)
--------------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles POST requests to store file upload data.  Requires write access.

The `$data` array accepts the following options:

* api - A string containing "upload".
* id - A string containing an asset ID.
* filename - A string containing a filename of a started file upload.
* start - An integer containing the position to start in the file.

This function is intended to be idempotent.  The official Barebones CMS API supports chunked uploads but that behavior can be overridden if necessary.  Use `GetAccessInfo()` to determine whether or not chunked uploads are allowed.  The Barebones CMS administrative interface supports both chunked and full file uploads.

BB_API_Helper::UploadDone($data, $outputcallback, &$outputcallbackopts)
-----------------------------------------------------------------------

Access:  public

Parameters:

* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function handles POST requests to finalize upload data.  Requires write access.

The `$data` array accepts the following options:

* api - A string containing "upload_done".
* id - A string containing an asset ID.
* filename - A string containing a filename of a started file upload.

The default function runs database queries but does not actually do any significant work.  However, the final output is expected by the Barebones CMS administrative interface to fill in the "Insert file" dialog.  When using a CDN, this function might be overridden to initiate a push to the CDN or make an appropriate finalization API call.

BB_API_Helper::UnknownAPI($reqmethod, $data, $outputcallback, &$outputcallbackopts)
-----------------------------------------------------------------------------------

Access:  public

Parameters:

* $reqmethod - A string containing the HTTP request method - one of "GET", "POST", or "DELETE".  Other request methods are not supported.
* $data - The unsanitized user input data array.
* $outputcallback - A valid callback function for sending output.  The callback function must accept three parameters - callback($result, $bulk, &$opts).
* $outputcallbackopts - Data to pass as the third parameter to the function specified by the `$outputcallback` option.

Returns:  A standard array of information.

This function returns a failure condition by default.  It is intended to be overridden by a derived class if such functionality is needed.
