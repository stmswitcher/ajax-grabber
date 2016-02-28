# PHP/JS AJAX file grabber and archiver
PHP class and a little nit of JS to collect multiple files by their URL's and archive 'em in a single ZIP.

# Basic usage
1. Fill the files' URLs ti the `urls.txt` file. One line - one file.
2. Open `index.php` and click begin. The textarea will be filled with log messages.

# How it works
The `js/script.js` sends GET request to the `req.php` file. Based on `req`'s response there'll be next request or result message.
The `req.php` file triggers the `Grabber` class to process with the next URL in the list. Check `Grabber.php` comments to get more on it's work.
