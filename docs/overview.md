Release Overview
================

Barebones CMS is a free and open source content management system.  There are thousands of CMS products out there, so thank you for taking the time to look at Barebones CMS.

There are three major components to the release distribution:  The Barebones CMS API, the Barebones CMS SDK, and the Barebones CMS administrative interface.

[![Barebones CMS Architecture Overview video](https://user-images.githubusercontent.com/1432111/41880502-399f51f8-7893-11e8-907d-18519c23c23c.png)](https://www.youtube.com/watch?v=uybGZ0V-tYY "Barebones CMS Architecture Overview")

Barebones CMS API
-----------------

Barebones CMS has always been about getting to the essence of content management.  As such, the [Barebones CMS API](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/api.md) is solely responsible for storing, retrieving, and managing content and files.

The API can be installed anywhere in the world and in a variety of configurations.  The API integrates with the [/feeds extension](https://github.com/cubiclesoft/cloud-storage-server-ext-feeds) of [Cloud Storage Server](https://github.com/cubiclesoft/cloud-storage-server) for realtime time-based content change notifications (e.g. the moment content reaches publish time).

The API can also be extended in a variety of ways such as transparent integration with a CDN.

Barebones CMS is actually the API.  However, an API by itself is not terribly useful.  It needs to be part of a larger ecosystem of tools such as SDKs and various user-friendly interfaces.

Barebones CMS SDK
-----------------

The [Barebones CMS SDK](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/sdk.md) communicates with the API to store, retrieve, and manage content.

A variety of [frontend patterns](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/frontend-patterns.md) are available to make it easy to build frontends that utilize the SDK to efficiently deliver content to website visitors.

The SDK also provides convenient routines for accessing binary data managed by the API including images, audio, and video and delivering them to a web browser or even caching them on the local file system for faster delivery later.

The SDK can be used for a number of purposes including creating assets automatically from data and syndication from other sources.

However, the Barebones CMS SDK by itself is also not terribly useful for most users.  Which brings us to the last component.

Barebones CMS Admin Interface
-----------------------------

The Barebones CMS administrative interface utilizes the Barebones CMS SDK to communicate with the Barebones CMS API.

![Screenshot of the Barebones CMS administrative interface](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/images/admin_interface_screenshot.png?raw=true "Barebones CMS administrative interface")

[Try the demo](http://barebonescms.com/demo/)

The admin interface can be [installed anywhere](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/docs/install.md), including a personal computer or behind a corporate firewall.

The included fullscreen content editor provides powerful editing tools in one compact fully responsive interface that works equally well on desktops and mobile devices.  The entire admin interface is also fully extensible via the [powerful plugin system](https://github.com/cubiclesoft/barebones-cms-docs/blob/master/docs/creating-extensions.md).
