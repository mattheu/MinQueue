Simple Minify

I couldn't get any other minify plugins working correctly, and could not work out what was going at all by looking at the code.

I wanted to do something much simpler. Just a bit of a proof of concept really. What do you reckon?

* Hook in on wp_enqueue_scripts - but really late.
* Work out the order they assets need to be enqueued in.
* Get the paths to all the files (relative to root)
* Dequeue the requested scripts
* Minify - currently done on the fly - but will do caching in the future.
* Enqueue minified


