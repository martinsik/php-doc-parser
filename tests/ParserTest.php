<?php

class ParserTest extends \PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
	}

    /**
     * Test parsed output for strpos
     */ 
    public function testParserStrpos() {

        $obj = json_decode(shell_exec('php parser.php --include-examples --disable-progress --print-test=strpos php-chunked-xhtml/en tests'), true);

        // name
        $this->assertSame('strpos', $obj['name']);
        $this->assertFalse(isset($obj['class']));

        // description
        $this->assertSame('Find the position of the first occurrence of a substring in a string.', $obj['desc']);
        $this->assertSame('Find the numeric position of the first occurrence of needle in the haystack string.', $obj['long_desc']);

        // see also
        $seeAlsoExpected = array("stripos","strrpos","strripos","strstr","strpbrk","substr","preg_match");
        $this->assertTrue($seeAlsoExpected === $obj['seealso']);
        
        $paramsExpected = array(
            array(
                'type' => 'string',
                'var'  => '$haystack',
                'beh'  => 0,
                'desc' => 'The string to search in.',
            ), array(
                'type' => 'mixed',
                'var'  => '$needle',
                'beh'  => 0,
                'desc' => 'If needle is not a string, it is converted to an integer and applied as the ordinal value of a character.',
            ), array(
                'type' => 'int',
                'var'  => '$offset',
                'beh'  => 1,
                'desc' => 'If specified, search will start this number of characters counted from the beginning of the string. Unlike strrpos() and strripos(), the offset cannot be negative.',
                'def'  => '0',
            )
        );
        $this->assertTrue($paramsExpected === $obj['params'][0]['list']);


        // code snippets
        $this->assertCount(3, $obj['examples']);

    }

    /**
     * Test parsed output for DateTime::add
     */ 
    public function testParserDateTimeAdd() {
        $obj = json_decode(shell_exec('php parser.php --include-examples --disable-progress --print-test=DateTime::add php-chunked-xhtml/en tests'), true);

        $this->assertSame('DateTime', $obj['class']);
        $this->assertSame('add', $obj['name']);

        $this->assertSame('Adds an amount of days, months, years, hours, minutes and seconds to a DateTime object.', $obj['desc']);
        $this->assertSame('Adds the specified DateInterval object to the specified DateTime object.', $obj['long_desc']);
        
        $paramsExpected = array(
            array(
                'type' => 'DateInterval',
                'var'  => '$interval',
                'beh'  => 0,
                'desc' => 'Procedural style only: A DateTime object returned by date_create(). The function modifies this object.',
            )
        );
        $this->assertTrue($paramsExpected === $obj['params'][0]['list']);

        $this->assertCount(3, $obj['examples']);
    }

}


