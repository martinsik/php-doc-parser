Feature: ParserResult
  In order to work with parser
  As a developer
  I need to merge multiple parser results

  Scenario:
    When I have multiple parser results like:
      | test-file | warning    | skip   | result | examples |
      | file1     | msg1       |        | res1   | ex1, ex2 |
      | file2     | msg2, msg3 | 1      | res2   | ex4      |
      | file1     |            |        | res1   | ex3      |
      | file2     | msg4       |        | res4   |          |
    Then after merging them I'm expecting one result with:
      | test-file | warning          | skip  | result  | examples      |
      | file1     | msg1             |       | res1    | ex1, ex2, ex3 |
      | file2     | msg2, msg3, msg4 | 1     | res4    | ex4           |