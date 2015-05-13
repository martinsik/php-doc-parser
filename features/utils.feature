Feature: Utils
  In order to do some common things when parsing manual pages
  As a developer
  I need to have a set of static methods that can be used independently

  Scenario:
    When I have a very large multi-dimensional array:
      | test-array-as-json                                           |
      | [ "a", "b", ["c", ["d"]], "e"]                               |
      | { "k1": "a", "k2": { "k3": [ 1, 2, 3 ] }, "k4": [ 4, 5 ] }   |
    Then I need to convert it recursively to a low level more efficient SplFixedArray