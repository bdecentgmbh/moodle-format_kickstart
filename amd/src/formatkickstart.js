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
 * Defines Kicstart javascript.
 * @module   format_kickstart/formatkickstart
 * @category  Classes - autoloading
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 define(['core/str', 'core/notification', 'core/config', 'core/ajax'],
 function(str, notification, Config, Ajax) {

    /**
     * Controls kicstart javascript.
     * @param {int} contextid
     * @param {int} courseid
     * @return {void}
    */
    var Formatkickstart = function(contextid, courseid) {
        var self = this;
        var useTemplate = document.querySelectorAll(".templates-block .use-template");
        self.contextId = contextid;
        self.courseId = courseid;
        if (useTemplate) {
            useTemplate.forEach((element) => {
                element.addEventListener('click', self.templateHandler.bind(this));
            });
        }
    };


    Formatkickstart.prototype.confirmbutton = ".buttons .singlebutton form button";

    Formatkickstart.prototype.loadiconElement = "#modal-footer span#load-action";

    Formatkickstart.prototype.contextId = null;

    Formatkickstart.prototype.courseId = null;


    Formatkickstart.prototype.templateHandler = function(event) {
        var self = this;
        event.preventDefault();
        let templateName = event.target.getAttribute("data-templatename");
        let templateId = event.target.getAttribute("data-template");
        self.confirmImportTemplate(templateId, templateName);
    };

    Formatkickstart.prototype.confirmImportTemplate = function(templateId, templateName) {
        let self = this;
        var plugindata = {
            name: templateName
        };
        str.get_strings([
            {key: 'confirm', component: 'core'},
            {key: 'confirmtemplate', param: plugindata, component: 'format_kickstart'},
            {key: 'import'},
            {key: 'no'}
        ]).done(function(s) {
                notification.confirm(s[0], s[1], s[2], s[3], function() {
                    document.querySelectorAll("body")[0].classList.add("kickstart-icon");
                    Ajax.call([{
                        methodname: 'format_kickstart_import_template',
                        args: {templateid: templateId, courseid: self.courseId},
                        done: function(response) {
                            if (response) {
                                let redirect = Config.wwwroot + "/course/view.php?id=" + self.courseId;
                                window.location.assign(redirect);
                            }
                        }
                    }]);
                });
            }
        );
    };

    return {
        init: function(contextid, courseid) {
            return new Formatkickstart(contextid, courseid);
        }
    };
});