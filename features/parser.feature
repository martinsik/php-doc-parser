Feature: Parser
  In order to get a manual page in JSON format
  As a developer
  I need to run series of XPath queries that process the HTML page

  Scenario:
    Given these test files:
      | source-filename              | expected-json-result          |
      | datetime.setdate.html        | datetime.setdate.json         |
      | eventhttp.setcallback.html   | eventhttp.setcallback.json    |
      | function.array-diff.html     | function.array-diff.json      |
      | function.chown.html          | function.chown.json           |
      | function.json-encode.html    | function.json-encode.json     |
      | function.str-replace.html    | function.str-replace.json     |
      | function.strrpos.html        | function.strrpos.json         |
      | reflectionclass.getname.html | reflectionclass.getname.json  |
      | splfileobject.fgetcsv.html   | splfileobject.fgetcsv.json    |
    Then match them with files from "test-manual-files"
    Then download manual from "en" package from "cz1.php.net"
    And test downloaded files against them as well
