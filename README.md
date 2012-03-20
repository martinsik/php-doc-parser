# PHP Documentation Parser

This is a standalone script that takes entire PHP documentation in "many HTML files" version and generates single JSON file with all standard classes and functions.

## Try it

This repository comes out of the box with already parsed JSON output for English documentation in [`output\en`](https://github.com/martinsik/php-doc-parser/tree/master/output/en) so if you just want to see whether it's useful for you, you can try it right away.

## Usage

  1. **Download documentation**  
     Choose language you prefer, download "Many HTML files" documetation from http://php.net/download-docs.php and unpack it wherever you want.

  2. **Run the parser script:**

        php parser.php unpacked_documentation output_directory

It takes a while but when it's finished you should see in your `output_directory` two files: `database.json` and `stats.json`.

For more information about available parameters type: `php parser.php --help`.

## What is it good for?

IDEs, tools that need to use somehow structured PHP documentation.

## Why?

I use this script to generate "database" for my Google Chrome Extension called [PHP Ninja Manual](https://chrome.google.com/webstore/detail/clbhjjdhmgeibgdccjfoliooccomjcab "PHP Ninja Manual"). It takes all classes and functions in `database.json` and creates Web SQL database which is very fast and easy to use.

In `stats.json` there are three variables. `methods` and `examples` are just for debugging. The first one means the total number of functions/classes parsed from the source documentation and saved in `database.json`. `examples` is number of functions with code snippets. The last one `timestamp` means when was the `database.json` generated and is used to upgrade the Web SQL database when a new version of PHP Ninja Manual is released.

By the way there's an official [PHP Documentation generator](https://wiki.php.net/doc/articles/phd_ide) for IDEs, but when I started developing my extension it didn't suit my needs. I don't know what are its capabilities now but maybe it's worth a try.

## Known limitations

  * Not all standard classes are included by the parser (eg. Exceptions are missing).
  * By now, parser includes only short descriptions, not long
  * There are no PHP statements (for, if, while, ...)

## License

PHP Doc Parser is licensed under the Beerware license.
