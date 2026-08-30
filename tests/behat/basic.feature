@block @block_fastlearning
Feature: Basic tests for Fast learning

  @javascript
  Scenario: Plugin block_fastlearning appears in the list of installed additional plugins
    Given I log in as "admin"
    When I navigate to "Plugins > Plugins overview" in site administration
    And I follow "Additional plugins"
    Then I should see "Fast learning"
    And I should see "block_fastlearning"
