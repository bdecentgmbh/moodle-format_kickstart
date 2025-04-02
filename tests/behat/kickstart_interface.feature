@format @format_kickstart @kickstart_page @_file_upload @javascript
Feature: Check the kickstart interface.
  Background: Create users to check the visbility.
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | coursecreator1 | Coursecreator   | user1   | coursecreator1@test.com  |
      | coursecreator2 | Coursecreator   | User2   | coursecreator2@test.com  |
      | user1    | User      | One      | one@example.com |
      | user2    | User      | Two      | two@example.com |
      | user3    | User      | Three    | thr@example.com |
    And the following "courses" exist:
      | fullname | shortname | category | enablecompletion | showcompletionconditions | format    | newsitems |
      | Course 1 | C1        | 0        | 1                | 1                        | kickstart | 1         |
      | Course 2 | C2        | 0        | 1                | 1                        | kickstart | 1         |
      | Course 3 | C3        | 0        | 1                | 1                        | kickstart | 1         |
      | Course 4 | C4        | 0        | 1                | 1                        | kickstart | 1         |
    And the following "categories" exist:
      | name  | category | idnumber |
      | Cat 1 | 0        | CAT1     |
      | Cat 2 | 0        | CAT2     |
    And the following "role assigns" exist:
      | user           | role                | contextlevel | reference |
      | coursecreator1 | coursecreator       | Category     | CAT1      |
      | coursecreator1 | coursecreator       | Category     | CAT2      |
      | coursecreator1 | coursecreator       | Category     | CAT1      |
    And the following "activities" exist:
      | activity | name      | course | idnumber  | content               |
      | page     | TestPage1 | C3     | page1     | Example page1 content |
      | page     | TestPage2 | C3     | page2     | Example page2 content |
    And the following "course enrolments" exist:
      | user  | course | role           |
      | user1 | C1     | editingteacher |
      | user2 | C1     | student        |
    And I log in as "admin"
    And I create a kickstart template with:
      | Title | Test template 1 |
      | Tags  | template 1      |
      | Course backup file (.mbz) | /course/format/kickstart/tests/course.mbz|
    Then I should see "Template successfully created"
    Then I should see "Test template 1" in the "template 1" "table_row"
    And I click on "Edit" "button" in the "Test template 1" "table_row"
    And I set the following fields to these values:
      | Title | Demo template 1|
    And I press "Save changes"
    And I should see "Template successfully edited"
    Then I should see "Demo template 1" in the "template 1" "table_row"
    And I click on "Delete" "button" in the "Demo template 1" "table_row"
    And I press "Delete"
    And I should see "Template successfully deleted"

  Scenario: New interface for course templates
    # Admin view.
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I should see "Course template" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"

    # Course view page
    # Check the List view
    And I click on "#list-view" "css_element" in the ".listing-view-block" "css_element"
    And ".template-list.kickstart-list-view" "css_element" should exist in the ".kickstart-page" "css_element"

    # Check the Tile view
    And I click on "#tile-view" "css_element" in the ".listing-view-block" "css_element"
    And ".template-list.kickstart-tile-view" "css_element" should exist in the ".kickstart-page" "css_element"

    # Check the search template
    And I set the following fields to these values:
      | search-template    | Weekly sections |
    And I should see "Weekly sections" in the ".kickstart-tile-view" "css_element"
    And I should not see "Kickstart format" in the ".kickstart-tile-view" "css_element"

    # Check the manage templates.
    Then I navigate to "Plugins > Course formats > Manage templates" in site administration
    And I click on "Create template" "button"
    And I set the following fields to these values:
      | Title | Test template 2 |
      | Tags  | template2 |
      | Course backup file (.mbz) | /course/format/kickstart/tests/course-10-online.mbz |
      | Preview URL  | https://www.example.com |
    And I press "Save changes"
    And I should see "Test template 2" in the ".generaltable" "css_element"

    # Using the template
    And I am on "Course 1" course homepage
    And I should see "Test template 2" in the ".kickstart-tile-view" "css_element"
    And I click on ".use-template[data-templatename=\"Test template 2\"]" "css_element" in the ".template-list" "css_element"
    And I click on "Import" "button" in the ".modal-dialog" "css_element"
    And I wait "30" seconds
    And I should see "General" in the ".section .course-section-header .sectionname" "css_element"

    # Course kickstart page
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I click on "Kickstart" "link" in the ".secondary-navigation" "css_element"
    And I should see "Course template" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"

    # List view
    And I click on "#list-view" "css_element" in the ".listing-view-block" "css_element"
    And ".template-list.kickstart-list-view" "css_element" should exist in the ".kickstart-page" "css_element"

    # Tile view
    And I click on "#tile-view" "css_element" in the ".listing-view-block" "css_element"
    And ".template-list.kickstart-tile-view" "css_element" should exist in the ".kickstart-page" "css_element"

    # Template search
    And I set the following fields to these values:
    | search-template    | Single activity |
    And I should see "Single activity" in the ".kickstart-tile-view" "css_element"
    And I should not see "Test template 2" in the ".kickstart-tile-view" "css_element"
    And I log out

  Scenario: kickstart student view and help documentation page
    Given I log in as "admin"
    And I am on "Course 1" course homepage
    And I should see "Course template" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"

    # Admin sees the "Student view" page in the course page
    And I click on ".dropdown-toggle" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I click on ".dropdown .dropdown-menu .dropdown-item:nth-child(2)" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I should see "Student view" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I should see "Your teacher has not added any content into this course (yet)."
    And I should see "If you have any questions, contact your teacher."

    # Help documentation page
    And I click on ".dropdown-toggle" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I click on ".dropdown .dropdown-menu .dropdown-item:nth-child(3)" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I should see "Help" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I should see "Kickstart Features" in the ".kickstart-block h3" "css_element"

    # Change the Student & Teacher instructions
    And I am on "Course 1" course homepage
    And I click on "Settings" "link" in the ".secondary-navigation" "css_element"
    And I expand all fieldsets
    And I set the following fields to these values:
    | Format | Kickstart format |
    And I wait until the page is ready
    And I expand all fieldsets
    And I set the following fields to these values:
    |  Student instructions  |  I’m excited to have you in this course! Over the next few weeks, we’ll explore brief course topic, engage in interactive discussions, and work on practical assignments to enhance your learning. |
    |  Teacher instructions  |  As a teacher, you can use this space to provide a warm welcome to your students. A well-crafted welcome message helps set the tone for the course and guides students on what to expect. |
    And I press "Save and display"

    # Admin view the student's instruction in the Student view page within the course page
    And I am on "Course 1" course homepage
    And I should see "Course template" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I click on ".dropdown-toggle" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I click on ".dropdown .dropdown-menu .dropdown-item:nth-child(2)" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I should see "Student view" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I should see "I’m excited to have you in this course! Over the next few weeks, we’ll explore brief course topic, engage in interactive discussions, and work on practical assignments to enhance your learning."

    # Admin view the student's instruction in the Student view page within the kickstart page
    And I am on "Course 1" course homepage
    And I click on "Settings" "link" in the ".secondary-navigation" "css_element"
    And I expand all fieldsets
    And I set the following fields to these values:
    | Format | Custom sections |
    And I press "Save and display"
    And I click on "More" "link" in the ".secondary-navigation" "css_element"
    And I click on "Kickstart" "link" in the ".secondary-navigation" "css_element"
    And I should see "Course template" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I click on ".dropdown-toggle" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I click on ".dropdown .dropdown-menu .dropdown-item:nth-child(2)" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I should see "Student view" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I should see "Student view is not available for this course."

    # Help documentation page in the kickstart page
    And I click on ".dropdown-toggle" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I click on ".dropdown .dropdown-menu .dropdown-item:nth-child(3)" "css_element" in the ".tertiary-navigation-selector" "css_element"
    And I should see "Help" in the ".tertiary-navigation-selector .dropdown-toggle" "css_element"
    And I should see "Kickstart Features" in the ".kickstart-block h3" "css_element"
    And I log out
