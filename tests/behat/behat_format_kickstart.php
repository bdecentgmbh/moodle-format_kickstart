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
     * Creates a new template with the provided table data matching template settings names with the desired values.
     *
     * @Given /^I create a kickstart template with:$/
     * @param TableNode $table The course data
     */
    public function i_create_a_kickstart_template_with(TableNode $table) {
        $url = new moodle_url('/course/format/kickstart/template.php', array('action' => 'create', 'sesskey' => sesskey()));
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
            array("Plugins" . ' > ' . "Course formats". '>'."General settings")
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
}
