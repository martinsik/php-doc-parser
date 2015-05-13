<?php

namespace DocParser;


class ParserResult {

    private $warnings = [];
    private $skipped = [];
    private $results = [];
    private $examples = [];

    public function addWarning($funcName, $message) {
        if (!isset($this->warnings[$funcName])) {
            $this->warnings[$funcName] = [];
        }

        $this->warnings[$funcName][] = $message;
    }

    public function getWarnings($funcName = null) {
        return ($funcName && array_key_exists($funcName, $this->warnings)) ? $this->warnings[$funcName] : $this->warnings;
    }

    public function hasWarnings($funcName = null) {
        return (boolean)$this->getWarnings($funcName);
    }

    public function countAllWarnings($funcName = null) {
        $count = 0;
        foreach ($this->getWarnings($funcName) as $ex) {
            $count += count($ex);
        }
        return $count;
    }

    public function addSkipped($funcName) {
        $this->skipped[$funcName] = true;
    }

    public function getSkipped() {
        return $this->skipped;
    }

    public function isSkipped($funcName = null) {
        return isset($this->skipped[$funcName]);
    }

    public function mergeWithResult(ParserResult $results) {
        $this->warnings = array_merge_recursive($this->warnings, $results->getWarnings());
        $this->skipped = array_merge_recursive($this->skipped, $results->getSkipped());
        $this->examples = array_merge_recursive($this->examples, $results->getExamples());
        $this->results = array_replace($this->results, $results->getResult());
    }

    public function setResult($name, $content) {
        $this->results[$name] = $content;
    }

    public function getResult($funcName = null) {
        if ($funcName) {
            return array_key_exists($funcName, $this->results) ? $this->results[$funcName] : [];
        } else {
            return $this->results;
        }
    }

    public function addExample($funcName, $example) {
        if (!isset($this->examples[$funcName])) {
            $this->examples[$funcName] = [];
        }

        $this->examples[$funcName][] = $example;
    }

    public function getExamples($funcName = null) {
        if ($funcName) {
            return array_key_exists($funcName, $this->examples) ? $this->examples[$funcName] : [];
        } else {
            return $this->examples;
        }
    }

    public function hasExamples($funcName = null) {
        return (boolean)$this->getExamples($funcName);
    }

    public function countAllExamples($funcName = null) {
        $count = 0;
        foreach ($this->getExamples($funcName) as $ex) {
            $count += count($ex);
        }
        return $count;
    }

    public function getFuncNames() {
        return array_keys($this->results);
    }
}
