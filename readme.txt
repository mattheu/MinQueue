MPH Simple Minify

This plugin is aimed at developers looking for to minify and concatenate JS and CSS files semi-automatically.

Only scripts and styles that are added using the WordPress dependency enqueueing system will be processed by the plugin. Note they must be added correctly: using the wp_enqueue_scripts action (or earlier - init is OK).
Note wp_print_scripts and wp_print_styles is not the correct action to use when enqueueing scripts.
This is not a completely automatic soloution, users must manually specify a list of assets to be processed.

Reasons for writing this plugin:

* I couldn't get any other minify plugins working correctly when WordPress is installed in a subdirectory.
* WordPress script enqueuing system is really good. We should use this, and only this.
* Auto Minification & Concatenation of all scripts & styles on a page is flawed. It can lead to multiple large minified files causing the user to download even more than before minification & concatenation.
* We need a really easy way for users to know what is enqueued, where it is outputted in the HTML and also what they are minifying. 

How it works.

* Hook in on wp_enqueue_scripts - but really late. Could do on print_scripts & print styles?
* Work out queue to be processed
* Get the paths to all the files (relative to root)
* Get cached file or minify & generate cache file.
* Mark the requested scripts as done
* Enqueue minified
