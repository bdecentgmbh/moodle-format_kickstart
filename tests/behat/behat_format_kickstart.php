<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat kickstart course format steps definitions.
 *
 * @package    format_kickstart
 * @category   test
 * @copyright  2020 bdecent gmbh <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode as TableNode;

/**
 * Kickstart course format steps definitions.
 *
 * @package    format_kickstart
 * @category   test
 * @copyright  2012 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_kickstart extends behat_base {

    /**
     * Click the disable link for single activity course format.
     * @Given /^I click on disable link single activity$/
     */
    public function i_click_on_disable_link_single_activity() {
        global $CFG;
        if ($CFG->branch <= '403') {
            $this->execute('behat_general::i_click_on_in_the', ["Disable", "link", "Single activity format", "table_row"]);
        } else {
            $this->execute('behat_general::i_click_on_in_the', ["Disable", "link", "Single activity", "table_row"]);
        }
    }

    /**
     * Click the ecit link for custom sections course format.
     * @Given /^I click on enable link custom sections$/
     */
    public function i_click_on_edit_link_custom_sections() {
        global $CFG;
        if ($CFG->branch <= '403') {
            $this->execute('behat_general::i_click_on_in_the', ["Edit", "button", "Topics format", "table_row"]);
        } else {
            $this->execute('behat_general::i_click_on_in_the', ["Edit", "button", "Custom sections", "table_row"]);
        }
    }

    /**
     * Click the edit link for single activity course format.
     * @Given /^I click on enable link single activity$/
     */
    public function i_click_on_edit_link_single_activity() {
        global $CFG;
        if ($CFG->branch <= '403') {
            $this->execute('behat_general::i_click_on_in_the', ["Edit", "button", "Single activity format", "table_row"]);
        } else {
            $this->execute('behat_general::i_click_on_in_the', ["Edit", "button", "Single activity", "table_row"]);
        }
    }

    /**
     * Check the single activity condition.
     * @Given /^I check single activity condition kickstart:$/
     * @param TableNode $table The course data
     */
    public function i_check_single_activity_condition_kickstart(TableNode $table) {
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', $table);
        $this->execute('behat_forms::press_button', "Save and display");
        $this->execute('behat_general::assert_page_contains_text', "There are no discussion topics yet in this forum");
    }

    /**
     * I should see the course format.
     * @Given /^I should see course format "(?P<element_string>(?:[^"]|\\")*)"$/
     * @param string $format The course data
     */
    public function i_should_see_define_course_format($format) {
        global $CFG;
        if ($CFG->branch <= '403') {
            switch($format) {
                case 'Single activity':
                    $format = 'Single activity format';
                    break;
                case 'Social':
                    $format = 'Social format';
                    break;
                case 'Custom sections':
                    $format = 'Topics format';
                    break;
                case 'Weekly sections':
                    $format = 'Weekly format';
                    break;
            }
        }
        $this->execute('behat_general::assert_page_contains_text', [$format]);
    }


    /**
     * I should not see the course format.
     * @Given /^I should not see course format "(?P<element_string>(?:[^"]|\\")*)"$/
     * @param string $format The course data
     */
    public function i_should_not_see_define_course_format($format) {
        global $CFG;
        if ($CFG->branch <= '403') {
            switch($format) {
                case 'Single activity':
                    $format = 'Single activity format';
                    break;
                case 'Social':
                    $format = 'Social format';
                    break;
                case 'Custom sections':
                    $format = 'Topics format';
                    break;
                case 'Weekly sections':
                    $format = 'Weekly format';
                    break;
            }
        }
        $this->execute('behat_general::assert_page_not_contains_text', [$format]);
    }

    /**
     * Creates a new template with the provided table data matching template settings names with the desired values.
     *
     * @Given /^I create a kickstart template with:$/
     * @param TableNode $table The course data
     */
    public function i_create_a_kickstart_template_with(TableNode $table) {
        $url = new moodle_url('/course/format/kickstart/template.php', ['action' => 'create', 'sesskey' => sesskey()]);
        $this->execute('behat_general::i_visit', [$url]);
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $table);
        $this->execute("behat_forms::press_button", get_string('savechanges'));
    }

    /**
     * Set the kickstart format plugins settings.
     *
     * @Given /^I set kickstart format setting with:$/
     * @param TableNode $table The course data
     */
    public function i_set_kickstart_format_setting_with(TableNode $table) {

        $this->execute("behat_general::i_am_on_homepage");
        $this->execute("behat_navigation::i_navigate_to_in_site_administration",
            ["Plugins" . ' > ' . "Course formats". '>'."General settings"],
        );
        $this->execute("behat_forms::i_set_the_following_fields_to_these_values", $table);
        $this->execute("behat_forms::press_button", get_string('savechanges'));
    }

    /**
     * Set the kickstart format plugins settings.
     *
     * @Given /^I click kickstart template "(?P<element_string>(?:[^"]|\\")*)"$/
     * @param string $selector Selector
     */
    public function i_click_kickstart_template($selector) {
        $script = "
            return (function() {
                var element = document.querySelectorAll('$selector')[0];
                return element.click();
            })();
        ";
        $config = $this->evaluate_script($script);
        if ($config === false) {
            throw new ExpectationException("Doesn't working correct", $this->getSession());
        }
    }


    /**
     * Set the kickstart course format plugins settings.
     *
     * @Given /^I click kickstart single activity format template$/
     */
    public function i_click_kickstart_single_activity_format_template() {
        global $CFG;
        if ($CFG->branch <= '403') {
            $this->i_click_kickstart_template(".use-template[data-templatename=\"Single activity format\"]");
        } else {
            $this->i_click_kickstart_template(".use-template[data-templatename=\"Single activity\"]");
        }
    }
}
