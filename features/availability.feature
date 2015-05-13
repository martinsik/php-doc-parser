Feature: availability
  In order to choose which languages for PHP manual
  As a parser script
  I want to download php.net/download-docs.php and list available languages

  Scenario: Run the CLI tool parser.php which lets you choose the languages you want
    When I want a list all available languages
    Then I should get these and more:
      | lang | title   |
      | en   | English |
      | es   | Spanish |
      | de   | German  |