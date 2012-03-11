<?php

/**
 * Only files starting with these prefixes will be parsed by the parser
 */

/** @TODO: There are still many prefixes to be added. But it's necessary
 *         to manually check all new methods added because it might unintenional
 *         include some installation or configiration documentation.
 */
$groups = array (
    'function',
    'reflectionclass',
    'reflectionextension',
    'reflectionclass',
    'reflectionfunction',
    'reflectionfunctionabstract',
    'reflectionmethod',
    'reflectionobject',
    'reflectionparameter',
    'reflectionproperty',
    'domdocument',
    'domattr',
    'domelement',
    'domnode',
    'domentity',
    'domcomment',
    'domxpath',
    'domnodelist',
    'xmlreader',
    'cairocontext',
    'xpathobject',
    'samconnection',
    'datetime',
    'exception',
    'directoryiterator',
    'datetimezone',
    'dateinterval',
    'arrayiterator',
    'arrayaccess',
    'countable',
    'swfsound',
    'simplexmlelement',
    'simplexmliterator',
    'mysqli',
    'mysqli-stmt',
    'splfixedarray',
    'splfileobject',
    'splfileinfo',
    'splfloat',
    'spldoublylinkedlist',
    'splheap',
    'splobjectstorage',
    'splpriorityqueue',
    'sqlite3',
    'sqlite3result',
    'sqlite3stmt',
    'splstack',
    'arrayobject',
    'seekableiterator',
    'recursiveiterator',
    'outeriterator',
    'class.overflowexception',
);
