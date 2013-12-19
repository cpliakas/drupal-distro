Feature: Site installation

  Scenario: Installation succeeded
    Given I am on "/"
    Then I should see "Welcome to {{ site.name }}"

  @javascript
  Scenario: Installation succeeded with js enabled
    Given I am on "/"
    Then I should see "Welcome to {{ site.name }}"
