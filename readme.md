# MPH Simple Minify _(Beta)_ #

Minification & concatenation of JS and CSS can reduce the file size of your assets, and reduce the number of HTTP requests, helping improve page load times.

Unlike other similar plugins, this is not a completely automatic soloution. Instead it takes a manually defined list of script/style handles, and minifies and concatenates them into a single file which is then cached for future use.

## Features. ##

* Minify & concatenate scripts and styles added using the WordPress dependency enqueueing system. Note they must be correctly enqueued.
* Handles scripts loaded in the footer. These are minified and concatenated separately.
* Works if WordPress is installed in a subdirectory.
* Complete control - only a manually defined list of files is processed.
* Multiple, independently processed files.
* Minified files are only loaded if at least one component file should be loaded.
* Compatable with localized scripts.
* Compatable with LESS: use wp-less plugin: https://github.com/sanchothefat/wp-less.git
* Debugger tool - displays a list of scripts and styles enqueued on each page on the front end of the site.

## Instructions ##

* Install & Activate the plugin.

### Basic Use ###

* Enable the plugin front end tool to check what scripts and styles are minified on each page.
* Copy the file handles you want to minify, and paste them into the minify queue textarea on the settings page.
* Save the settings and view the front end of your site. The processed files are generated on page load. Check that everything is working correctly.
* Uncheck the show debugger option and save the settings again.

### Advanced Use ###

* Multiple independantly proccessed files. You can specify several lists of file handles to be minified and concatenated into separate files. Useful if you have scripts that are loaded conditionally on certain pages and should be handled separately rather than minified and concatenated into one large file.
* Options can be defined in your config file. This then disables access to the settings page in the admin.
* Install MPH Minfiy outside of plugins directory? eg as an mu-plugin or in a theme? You will need to filter the plugins_url using 'mph_minify_plugin_dir' filter to give the root relative path to the location of the plugin.

## Problems? ##

* Only minifies and concatenates scripts and styles loaded using wp_enqueue_scripts and wp_enqueue_styles.
* Does not work if you enqueue your styles using the wp_print_styles action. I know this sounds like the right place to do it but you should be using the wp_enqueue_scripts action instead! see http://codex.wordpress.org/Plugin_API/Action_Reference/wp_print_styles
* Minify removes spaces and line breaks. Javascript that relies on these may break. Twitter Bootstrap javascript is not compatable.
* Be careful of errors in your CSS and JS that may not be apparent when they are loaded separately. eg code comments that are not closed correctly, when concatenated, can comment out all subsequent files.
* Be careful of dependencies & the order things will be processed. See Troubleshooting, Fatal error: Allowed memory size...

## Troubleshooting ##

### Fatal error: Allowed memory size... ###

You have probably created an infinite loop when working out the order of depencies.

_Example:_
Files 1, 2 and 3 are enqueued. File 1 is a dependency of 2, which is a dependency of 3.
If only scripts 1 and 3 are minified and concatenated into 1 file, it will fail.
This is because the processed file is now a dependency of file 2, but file 2 is also a dependency of it.
You must either process all files, or process 1 and 3 in separately.
