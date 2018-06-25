Asset Tagging
=============

The assets in Barebones CMS are incredibly flexible and Barebones CMS easily supports handling anything from a few assets to millions.  Tags help organize and group assets into sensible structures.

Tags can be anything.  Sections, categories, keywords, etc.

The Barebones CMS API has special high-performance logic for looking up assets based on tag searches.  Optimizing tag structures for the API helps to maximize website performance.

You can choose to tag your assets in whatever way you want.  The following are just suggestions for tag structures based on extensive experience with working with millions of assets across various CMS products.

Tag Prefixes
------------

Let's say you are writing a story about gardening and how much fun it is.  An example tag might be:

`/gardening/fun/`

For this tag, the prefix is the '/' character.  Another choice might be:

`s:gardening/fun/`

Where 's:' might mean "section".  Choosing what prefix is used is entirely up to whoever is writing content but tag prefixes should be consistent across all of the content in the system.

What's the purpose of all of this?  Well, let's say a URL exists on the website like:

`http://yourdomain.com/gardening/`

When a user visits the URL, the code for the page uses the Barebones CMS SDK to run a prefix query against the API for:

`~/gardening/`

The tilde '~' character at the beginning of that string tells the API to perform a "starts with" match against all of the tags in the system.  The above will match anything that starts with the string `/gardening/`, including the story with the `/gardening/fun/` tag.

Databases are optimized around shortcut logic.  The fewer comparisons that a database has to do, the faster it will operate.  The same is true for tags and tag prefixes.

Common Prefixes
---------------

* '/' - Use for defining a section or path in a URL (e.g. `/gardening/fun/`).
* '#' - A keyword or hashtag (e.g. `#firewalls`).
* '\*' - A special flag that might change how the asset is shown to the user (e.g. `*sponsored`).
* '\*/' - Used when multiple sections are defined to define which section should be the one used for a permalink.
* '@' - An author (e.g. `@MarkMatthews`).
* 'u:' - Asset owner for use with extensions that restrict asset access in a shared system (e.g. 'u:1234').
* 'g:' - Asset group for use with extensions that restrict asset access in a shared system (e.g. 'g:finance').

Reserved Characters
-------------------

* '~' - Not allowed at the start of a tag.  Used for "starts with" tag prefix matching.
* '!' - Not allowed at the start of a tag.  Used for "is not" tag matching.
* '\_' - Not allowed in a tag for SQL performance reasons (i.e. wildcard query).  Automatically converted to a hyphen '-' character.
* '%' - Not allowed in a tag for SQL performance reasons (i.e. wildcard query).  Automatically converted to a hyphen '-' character.
