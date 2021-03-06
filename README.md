# PHP Documentation Parser

[![Build Status](https://travis-ci.org/martinsik/php-doc-parser.svg?branch=master)](https://travis-ci.org/martinsik/php-doc-parser)

This package downloads gziped documentation from php.net, parses it and outputs all found functions as JSON with Markdown syntax. It comes with CLI interface for comfortable usage. 

[![](https://raw.githubusercontent.com/martinsik/php-doc-parser/master/doc/animation.gif)](https://raw.githubusercontent.com/martinsik/php-doc-parser/master/doc/animation.gif)

## Installation

Add `martinsik/php-doc-parser` to your `composer.json` dependencies:
  
  ```
  "require": {
      ...
      "martinsik/php-doc-parser": "~2.0"
  }
  ```

Then run `composer.phar install`.

## Usage

### As a CLI script
  
Composer adds `doc-parser` file to your directory with binaries (`vendor/bin` by default). Run it and follow the instructions on the screen.
  
    $ vendor/bin/doc-parser

Results are saved into `output` directory by default. This creates following files (names are generated by selected language and mirror):

- `en_php_net.json` - Very large associative array with all parsed functions and their data. See [sample output bellow](https://github.com/martinsik/php-doc-parser#sample-output).
- `en_php_net.list.json` - List of all function names in lowercase.
- `en_php_net.examples.json` (optional) - If you chose to export examples it'll put them into a separate file.

For full list of options run:
  
    $ vendor/bin/doc-parser help parser:run
  
### As a 3rd party package

Create an instance of `DocParser\Package` class to set language and mirror you want to parse and it'll download and unpack the documentation for you.
Then give the `DocParser\Parser` directory with files you want to parse and it'll return a `DocParser\ParserResult` object with all data as arrays.

```php
use DocParser\Package;
use DocParser\Parser;

$package = new Package('en', 'php.net');
$tmpFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $package->getOrigFilename();
$package->download($tmpFile);
$unpackedDir = $package->unpack();

$result = $parser->processDir($unpackedDir, Parser::EXPORT_EXAMPLES);
// you can parse just a single file with: $parser->processFile('file.html');

foreach ($result->getResult() as $funcName => $funcData) {
    // Note that all function names used as keys are lowercase.
    // Proper function names are in parameter lists (see [sample bellow](https://github.com/martinsik/php-doc-parser#sample-output)).
    // eg.: $funcData['params'][0]['name']
    
    // Get all examples for this function.
    // $result->getExamples($funcName);
    
    // If you used Parser::IMPORT_EXAMPLES then examples are right in $funcData.
    // With Parser::SKIP_EXAMPLES they're not parsed at all.
}

// Remove all temporary files
$package->cleanup();
```

## Sample output

This is what `DateTime::setDate` looks like deep inside `en_php_net.json`.

    {
        "abs": { ... },
        "array_pop": { ... },
        ...
        "datetime::add": { ... },
        "datetime::setdate": {
            "desc": "Sets the date.",
            "long_desc": "Resets the current date of the DateTime object to a different date.",
            "ver": "PHP 5 >= 5.2.0",
            "ret_desc": "Returns the DateTime object for method chaining or FALSE on failure.",
            "seealso": [
                "DateTime::setISODate",
                "DateTime::setTime"
            ],
            "filename": "datetime.setdate",
            "params": [
                {
                    "list": [
                        {
                            "type": "int",
                            "var": "$year",
                            "beh": "required",
                            "desc": "Year of the date."
                        },
                        {
                            "type": "int",
                            "var": "$month",
                            "beh": "required",
                            "desc": "Month of the date."
                        },
                        {
                            "type": "int",
                            "var": "$day",
                            "beh": "required",
                            "desc": "Day of the date."
                        }
                    ],
                    "name": "DateTime::setDate",
                    "ret_type": "DateTime"
                },
                {
                    "list": [
                        {
                            "type": "DateTime",
                            "var": "$object",
                            "beh": "required",
                            "desc": "Procedural style only: A DateTime object returned by date\\_create(). The function modifies this object."
                        },
                        {
                            "type": "int",
                            "var": "$year",
                            "beh": "required",
                            "desc": "Year of the date."
                        },
                        {
                            "type": "int",
                            "var": "$month",
                            "beh": "required",
                            "desc": "Month of the date."
                        },
                        {
                            "type": "int",
                            "var": "$day",
                            "beh": "required",
                            "desc": "Day of the date."
                        }
                    ],
                    "name": "date_date_set",
                    "ret_type": "DateTime"
                }
            ],
            "examples": [
                {
                    "title": "DateTime::setDate() example",
                    "source": "$date = new DateTime();\n$date->setDate(2001, 2, 3);\necho $date->format('Y-m-d');",
                    "output": "2001-02-03"
                },
                {
                    "title": "Values exceeding ranges are added to their parent values",
                    "source": "$date = new DateTime();\n\n$date->setDate(2001, 2, 28);\necho $date->format('Y-m-d') . \"\\n\";\n\n$date->setDate(2001, 2, 29);\necho $date->format('Y-m-d') . \"\\n\";\n\n$date->setDate(2001, 14, 3);\necho $date->format('Y-m-d') . \"\\n\";",
                    "output": "2001-02-28\n2001-03-01\n2002-02-03"
                }
            ]
        },
        "date_date_set": "DateTime::setDate",
        "datedime::createfromformat": { ... },
        "date_create_from_format": "DateTime::createFromFormat",
        ...
        "strpos": { ... }
        "tempnam": { ... }
        ...
    }

Note that this function has two different definitions, `DateTime::setDate` and `date_date_set`, where each takes different parameters. In order to be able to search both functions there are two keys for this function, where the second key, `date_date_set`, is just a reference to the first one. Also, all keys are lowercase.

## Why?

I use this script to generate "database" for my Google Chrome Extension called [PHP Ninja Manual](https://chrome.google.com/webstore/detail/clbhjjdhmgeibgdccjfoliooccomjcab "PHP Ninja Manual").

By the way there's an official [PHP Documentation generator](https://wiki.php.net/doc/articles/phd_ide) for IDEs, but when I started developing my extension it didn't exist. I don't know what are its capabilities now but maybe it's worth a try.

## Known limitations

  * There are no PHP statements (for, if, while, ...)
  * It's not able to recognize objective or procedural style in classes like in `mysqli`.

## Testing

This package uses [Behat](https://github.com/Behat/Behat) for testing. Run tests with:

    $ bin/behat

## License

PHP Documentation Parser (this package) is licensed under MIT license.

PHP Documentation pages ([php.net/docs.php](http://php.net/docs.php)) are licensed under [Creative Commons Attribution 3.0 License](http://creativecommons.org/licenses/by/3.0/legalcode).
