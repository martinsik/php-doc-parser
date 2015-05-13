Feature: package
  In order to parse the documentation
  As a parser script
  I need to download and unpack the manual to a temporary directory

  Scenario:
    When I need "fr" manual from "cz1.php.net"
    Then The package can be downloaded to system's tmp dir
    And Unpack files to the same directory:
      """
      class.pdo.html
      class.splheap.html
      datetime.diff.html
      function.cos.html
      function.is-string.html
      function.print-r.html
      function.strcmp.html
      """
    Then Cleanup files when it's all done