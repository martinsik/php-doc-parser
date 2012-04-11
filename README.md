# PHP Documentation Parser

This is a standalone script that takes entire PHP documentation in "many HTML files" version and generates single JSON file with all standard classes and functions.

## Try it

This repository comes out of the box with already parsed JSON output for English documentation in [`output\en`](https://github.com/martinsik/php-doc-parser/tree/master/output/en) so if you just want to see whether it's useful for you, you can try it right away.

By the way, at the bottom of this page there's parsed `str_replace` function in prettified JSON.

## Usage

  1. **Download documentation**  
     Choose language you prefer, download "Many HTML files" documetation from http://php.net/download-docs.php and unpack it wherever you want.

  2. **Run the parser script:**

        php parser.php unpacked_documentation output_directory

When it's finished you should see in your `output_directory` three files: `database.json`, `functions.json` and `stats.json`.

For more information about available parameters type: `php parser.php --help`.

Output directory should contain three files:

  1. `database.json` - entire parsed documentation as JSON.
  2. `stats.json` - contains 3 variables. `methods` and `examples` are just for debugging. The first one means the total number of functions/classes parsed from the source documentation and saved in `database.json`. `examples` is number of functions with code snippets. The last one `timestamp` means when was the `database.json` generated and is used to upgrade the Web SQL database when a new version of PHP Ninja Manual is released.
  3. `functions.json` - one big array of all parsed functions (useful for autocomplete).

## What is it good for?

IDEs, tools that need to use somehow structured PHP documentation.

## Why?

I use this script to generate "database" for my Google Chrome Extension called [PHP Ninja Manual](https://chrome.google.com/webstore/detail/clbhjjdhmgeibgdccjfoliooccomjcab "PHP Ninja Manual"). It takes all classes and functions in `database.json` and indexes Web SQL database which is very fast and easy to use.

By the way there's an official [PHP Documentation generator](https://wiki.php.net/doc/articles/phd_ide) for IDEs, but when I started developing my extension it didn't suit my needs. I don't know what are its capabilities now but maybe it's worth a try.

## What it looks like

Structure of `database.json` is pretty straight forward.

This is how `str_replace` looks like deep inside in [`output\en`](https://github.com/martinsik/php-doc-parser/tree/master/output/en).

    {
      ...
      "str_pad": { ... }
      "str_repeat": { ... }
      "str_replace":
        {
          "desc":"Replace all occurrences of the search string with the replacement string",
          "long_desc":"This function returns a string or an array with all occurrences of search in subject replaced with the given replace value. If you don't need fancy replacing rules (like regular expressions), you should always use this function instead of preg_replace().",
          "ver":"PHP 4, PHP 5",
          "ret_desc":"This function returns a string or an array with the replaced values.",
          "seealso":[
            {
              "name":"str_ireplace",
              "desc":"Case"
            },
            {
              "name":"substr_replace",
              "desc":"Replace text within a portion of a string"
            },
            {
              "name":"preg_replace",
              "desc":"Perform a regular expression search and replace"
            },
            {
              "name":"strtr",
              "desc":"Translate characters or replace substrings"
            }
          ],
          "url":"function.str-replace",
          "name":"str_replace",
          "params":[
            {
              "list":[
                {
                  "type":"mixed",
                  "var":"$search",
                  "beh":0,
                  "desc":"The value being searched for, otherwise known as the needle. An array may be used to designate multiple needles."
                },
                {
                  "type":"mixed",
                  "var":"$replace",
                  "beh":1,
                  "desc":"The replacement value that replaces found search values. An array may be used to designate multiple replacements."
                },
                {
                  "type":"mixed",
                  "var":"$subject",
                  "beh":1,
                  "desc":"The string or array being searched and replaced on, otherwise known as the haystack. If subject is an array, then the search and replace is performed with every entry of subject, and the return value is an array as well."
                },
                {
                  "type":"int",
                  "var":"&$count",
                  "beh":1,
                  "desc":"If passed, this will be set to the number of replacements performed."
                }
              ],
              "ret_type":"mixed"
            }
          ]
        },
      "str_rot13": { ... },
      "str_shuffle": { ... },
      ....
    }

## Known limitations

  * Not all standard classes are included by the parser (eg. Exceptions are missing).
  * There are no PHP statements (for, if, while, ...)
  * "see also" part is not 100% reliable

## License

PHP Documentation Parser is licensed under the Beerware license.
