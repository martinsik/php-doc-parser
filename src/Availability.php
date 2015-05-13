<?php

namespace DocParser;


class Availability {

    const DOWNLOADS_URL = 'http://php.net/download-docs.php';
    const TH_PATTERN = 'Many HTML files';
    const TD_PATTERN = 'tar.gz';

    /**
     * @return array Returns all found language => URL pairs available on php.net
     * @throws \Exception
     */
    public function listPackages() {
        $curl = curl_init(self::DOWNLOADS_URL);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if (200 != $statusCode) {
            throw new \Exception("Invalid status lang ${statusCode} for page " . self::DOWNLOADS_URL);
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($response);
        $xpath = new \DOMXPath($dom);

        // Find table column with the type of manual pages we need.
        // Note: Xpath indices start from 1.
        $colIndex = null;
        foreach ($xpath->query('//section[@id="layout-content"]/table//tr[1]/th/text()') as $index => $thText) {
            if (false !== strpos($thText->wholeText, self::TH_PATTERN)) {
                $colIndex = $index;
                break;
            }
        }

        if (null == $colIndex) {
            throw new \Exception('Unable to find table column with "' . self::TH_PATTERN . '" manual.');
        }

        $rows = $xpath->query('//section[@id="layout-content"]/table//tr[position() > 1]');

        // Make language => url pairs
        $langs = [];
        foreach ($rows as $row) {
            $td = $xpath->query("td[${colIndex}]", $row)->item(0);
            $tdText = $xpath->query('a/text()', $td)->item(0)->wholeText;
            if (strpos($tdText, self::TD_PATTERN) !== false) {
                $title = trim($xpath->query('th/text()', $row)->item(0)->wholeText);
                $uri = trim($xpath->query('a/@href', $td)->item(0)->textContent);
                preg_match('/\/php_manual_(.*)\./U', $uri, $matches);

                // language lang
                $langs[$matches[1]] = $title;
            }
        }
        return $langs;
    }

}