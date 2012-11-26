=== MinQueue ===
Contributors: mattheu
Tags: minify, script, style, concatenate
Requires at least: 3.4
Tested up to: 3.5
Stable tag: 1.0

Minify & Concatenate Enqueued Scripts & Styles.

== Description ==

Minification & concatenation of JS and CSS files can reduce the file size of your assets, and reduce the number of HTTP requests, helping improve page load times.

The plugin takes a manually defined list of script/style handles, and minifies and concatenates them into a single file which is then cached for future use.

**Features.**

*   Minifies & concatenates scripts and styles loaded using the WordPress dependency enqueueing system.
*   Handles scripts loaded in the footer.
*   Works if WordPress is installed in a subdirectory.
*   Complete control - only a manually defined list of files is processed.
*   Multiple, independently processed files.
*   Minified files are only loaded if at least one component file should be loaded.
*   Compatable with localized scripts.
*   Compatable with LESS: use wp-less plugin: <a href=\"https://github.com/sanchothefat/wp-less.git\" target=\"_blank\" title=\"https://github.com/sanchothefat/wp-less.git\">https://github.com/sanchothefat/wp-less.git</a>
*   Helper tool - displays a list of scripts and styles enqueued on each page on the front end of the site.

== Installation ==

Install & Activate the plugin.

**Basic Use**

*   Enable the plugin front end tool to check what scripts and styles are minified on each page.
*   Copy the file handles you want to minify, and paste them into the minify queue textarea on the settings page.
*   Save the settings and view the front end of your site. The processed files are generated on page load. Check that everything is working correctly.
*   Uncheck the show helper option and save the settings again.

**Advanced use**

*   Multiple, independantly proccessed files. You can specify several lists of file handles to be minified and concatenated into separate files. Useful if you have scripts that are loaded conditionally on certain pages and should be handled separately rather than minified and concatenated into one large file.
*   Options can be defined in your config file. This then disables access to the settings page in the admin.