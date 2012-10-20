# MPH Simple Minify _(Beta)_ #

Minification & concatenation of JS and CSS can reduce the file size of your assets, and reduce the number of HTTP requests.

This is not a completely automatic soloution, instead it takes a manually defined list of script/style handles, and minifies and concatenates them into a single file which is then cached for future use.

## Features. ##

* Minify & concatenate scripts and styles added using the WordPress dependency enqueueing system. Note they must be correctly enqueued.
* Manually defined list to process. These are then always processed as long as at least one item is enqueued.
* Multiple minify files. Can specify several lists of handles to be minified. Useful if you have scripts that are loaded conditionally and should be handled separetly rather thatn minified and concatenated into one large file.
* Handles scripts loaded in the footer. These are minified and concatenated separately.
* Compatable with localized scripts.
* Compatable with LESS: use wp-less plugin: https://github.com/sanchothefat/wp-less.git
* Debugger tool - displays a list of scripts and styles enqueued on each page on the front end of the site.

## Problems? ##

* Only minifies and concatenates scripts and styles loaded using wp_enqueue_scripts and wp_enqueue_styles.
* Does not work if you enqueue your styles using the wp_print_styles action. I know this sounds like the right place to do it but you should be using the wp_enqueue_scripts action instead! see http://codex.wordpress.org/Plugin_API/Action_Reference/wp_print_styles
* Be careful of errors in your CSS and JS that may not be apparent when they are loaded separately. eg code comments that are not closed correctly, when concatenated, can comment out all subsequent files.
* Be careful of dependencies.

## Reasons for writing this plugin. ##

I know there are already some great minification plugins out there - so why write my own?

* Didn't work when WordPress is installed in a subdirectory.
* WordPress script enqueuing system is really good and wanted something that used this.
* Auto Minification & Concatenation of all scripts & styles on a page is potentially flawed. It can lead to multiple large minified files causing the user to download even more than before minification & concatenation.
* Wanted a really easy way to find out what is enqueued on each page.