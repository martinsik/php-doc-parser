<?php

namespace DocParser;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class Package {

    private $lang;
    private $mirror;
    private $filePath;
    private $unpackedDir;
    private $cleanupFiles = [];

    const ARCHIVE_INNER_DIR = 'php-chunked-xhtml';

    public function __construct($lang, $mirror) {
        $this->lang = $lang;
        $this->mirror = $mirror;
    }

    public function download($filePath, $progressCallback = null) {
        $this->filePath = $filePath;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->getUrl());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $f = fopen($this->filePath, 'w+');
        curl_setopt($ch, CURLOPT_FILE, $f);
        if ($progressCallback) {
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, $progressCallback);
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        }
        curl_exec($ch);
        curl_close($ch);

        fclose($f);

        $this->isPackageFileValid();

        $this->cleanupFiles[] = $this->filePath;
    }

    public function unpack($files = []) {
        $this->isPackageFileValid();

        // decompress from gz
        $tarFile = str_replace('.tar.gz', '.tar', $this->filePath);
        @unlink($tarFile);
        $gz = new \PharData($this->filePath);
        $gz->decompress();
        $this->cleanupFiles[] = $tarFile;

        $unzipDir = str_replace('.tar', '', $tarFile);
        // un-archive from the tar
        $phar = new \PharData($tarFile);
        $extractFiles = $files ? array_map(function($file) { return Package::ARCHIVE_INNER_DIR . DIRECTORY_SEPARATOR . $file; }, $files) : null;
        $phar->extractTo($unzipDir, $extractFiles, true);
        $this->cleanupFiles[] = $unzipDir;

        $this->unpackedDir = $unzipDir . DIRECTORY_SEPARATOR . self::ARCHIVE_INNER_DIR;
        return $this->unpackedDir;
    }

    public function cleanup() {
        $fs = new Filesystem();

        try {
            $fs->remove($this->cleanupFiles);
        } catch (IOExceptionInterface $e) {
            new IOException("Unable to remove files/directories: " . implode(', ', $this->cleanupFiles));
        }
    }

    private function isPackageFileValid() {
        if (!file_exists($this->filePath)) {
            throw new \Exception('File "' . $this->filePath . '" doesn\'t exist. Did you call Package::download() before Package::unpack()?');
        }

        if (0 == filesize($this->filePath)) {
            throw new \Exception('File "' . $this->filePath . '" has 0 length. You might have a wrong mirror site or language file. ' .
                'Try opening this this URL in a browser: ' . $this->getUrl());
        }
    }

    public function getUrl() {
        return 'http://' . $this->mirror . '/get/' . $this->getOrigFilename() . '/from/this/mirror';
    }

    public function getOrigFilename() {
        return 'php_manual_' . $this->lang . '.tar.gz';
    }

    public function getLang() {
        return $this->lang;
    }

    public function getMirror() {
        return $this->mirror;
    }

    public function getUnpackedDir() {
        return $this->unpackedDir;
    }
}