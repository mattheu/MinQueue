# MPH Simple Minify _(Beta)_ #

Minification & Concatenation plugin aimed at developers looking to minify and concatenate JS and CSS files.

## Features. ##

* Minify & concatenate scripts and styles added using the WordPress dependency enqueueing system. Note they must be correctly enqueued. Also note that you should be using the wp_enqueue_scripts action (or earlier - init is OK).
* This is not a completely automatic soloution, users must manually specify a list of assets to be processed.
* Scripts that should be placed in the footer are minified & concatenated separately and the minified file is placed in the footer.
* Compatable with localized scripts.

## Issues ##

* Does not work if you enqueue your styles using the wp_print_styles action. I know this sounds like the right place to do it but you should be using the wp_enqueue_scripts action instead! see http://codex.wordpress.org/Plugin_API/Action_Reference/wp_print_styles

## Reasons for writing this plugin. ##

I know there are already some great minification plugins out there - so seriously why write my own?

* I couldn't get any other minify plugins working correctly when WordPress is installed in a subdirectory.
* WordPress script enqueuing system is really good. We should use this, and only this.
* Auto Minification & Concatenation of all scripts & styles on a page is potentially flawed. It can lead to multiple large minified files causing the user to download even more than before minification & concatenation.
* We need to know what is enqueued, where it is placed in the HTML, and I wanted a really easy way to find this.
* Confidence - knowing exactly what files are minified and concatenated.

## How it works. ##

* Hook in on wp_enqueue_scripts - but really late. Could do on print_scripts & print styles?
* Work out queue to be processed
* Get the paths to all the files (relative to root)
* Get cached file or minify & generate cache file.
* Mark the requested scripts as done
* Enqueue minified
