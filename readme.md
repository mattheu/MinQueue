# MPH Simple Minify _(Beta)_ #

Minification & concatenation of JS and CSS can reduce the file size of your assets, and reduce the number of HTTP requests, helping improve page load times.

Unlike other similar plugins, this is not a completely automatic soloution. Instead it takes a manually defined list of script/style handles, and minifies and concatenates them into a single file which is then cached for future use.

## Features. ##

* Minify & concatenate scripts and styles added using the WordPress dependency enqueueing system. Note they must be correctly enqueued.
* Handles scripts loaded in the footer. These are minified and concatenated separately.
* Works if WordPress is installed in a subdirectory.
* Complete control - only a manually defined list of files is processed.
* Multiple minified files. Can specify several lists of handles to be minified and concatenated into separate files. Useful if you have scripts that are loaded conditionally and should be handled separately rather than minified and concatenated into one large file.
* Minified files are only loaded if at least one component file should be loaded.
* Compatable with localized scripts.
* Compatable with LESS: use wp-less plugin: https://github.com/sanchothefat/wp-less.git
* Debugger tool - displays a list of scripts and styles enqueued on each page on the front end of the site.

## Problems? ##

* Only minifies and concatenates scripts and styles loaded using wp_enqueue_scripts and wp_enqueue_styles.
* Does not work if you enqueue your styles using the wp_print_styles action. I know this sounds like the right place to do it but you should be using the wp_enqueue_scripts action instead! see http://codex.wordpress.org/Plugin_API/Action_Reference/wp_print_styles
* Minify removes spaces and line breaks. Javascript that relies on these may break. Twitter Bootstrap javascript is not compatable.
* Be careful of errors in your CSS and JS that may not be apparent when they are loaded separately. eg code comments that are not closed correctly, when concatenated, can comment out all subsequent files.
* Be careful of dependencies. Avoid infinite loops.