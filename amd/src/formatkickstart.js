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
 define(['jquery', 'core/str', 'core/notification', 'core/config', 'core/ajax', 'core/fragment', 'core/templates'],
 function($, str, notification, Config, Ajax, Fragment, Templates) {

    /**
     * Controls kicstart javascript.
     * @param {int} contextid
     * @param {int} courseid
     * @param {int} menuid
     * @return {void}
    */
    var Formatkickstart = function(contextid, courseid, menuid, filteroptions) {
        var self = this;
        var useTemplate = document.querySelectorAll(".templates-block .use-template");
        self.contextId = contextid;
        self.courseId = courseid;
        self.menuid = menuid;
        if (useTemplate) {
            useTemplate.forEach((element) => {
                element.addEventListener('click', self.templateHandler.bind(this));
            });
        }

        if (filteroptions) {

            var templateview = document.querySelectorAll(".kickstart-page .listing-view-block a");
            if (templateview) {
                templateview.forEach((element) => {
                    element.addEventListener('click', self.templateviewHandler.bind(this));
                });
            }


            var templatesearch = document.querySelectorAll(".kickstart-page #search-template");
            if (templatesearch) {
                templatesearch.forEach((element) => {
                    element.addEventListener('change', self.templateSearchHandler.bind(this));
                });
            }

            var librarycourse = document.querySelectorAll(".librarycourse-filter .filter-item");
            if (librarycourse) {
                librarycourse.forEach((element) => {
                    element.addEventListener('change', self.libraryCourseHandler.bind(this));
                });
            }
        }

        $('body').delegate(self.fullDescription, "click", self.fullmodcontentHandler.bind(this));
        $('body').delegate(self.trimDescription, "click", self.trimmodcontentHandler.bind(this));

    };


    Formatkickstart.prototype.confirmbutton = ".buttons .singlebutton form button";

    Formatkickstart.prototype.loadiconElement = "#modal-footer span#load-action";

    Formatkickstart.prototype.fullDescription = ".list-library-courses .trim-summary .section-summary-action";

    Formatkickstart.prototype.trimDescription = ".list-library-courses .fullcontent-summary .section-summary-action";

    Formatkickstart.prototype.contextId = null;

    Formatkickstart.prototype.courseId = null;

    Formatkickstart.prototype.menuid = null;


    Formatkickstart.prototype.fullmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('.accordion-item').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('.accordion-item').find('.trim-summary');
        if (trimcontent.hasClass('summary-hide')) {
            trimcontent.removeClass('summary-hide');
            fullContent.addClass('summary-hide');
        }
    };

    Formatkickstart.prototype.trimmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('.accordion-item').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('.accordion-item').find('.trim-summary');
        if (fullContent.hasClass('summary-hide')) {
            fullContent.removeClass('summary-hide');
            trimcontent.addClass('summary-hide');
        }
    };

    Formatkickstart.prototype.libraryCourseHandler = function(event) {
        let searchcourse = document.querySelector("#search-course-library").value;
        let customfieldsitems = document.querySelectorAll(".librarycourse-filter .customfield-filter .filter-item");
        let customvalues = {};
        if (customfieldsitems) {
            customfieldsitems.forEach((element) => {
                customvalues[element.getAttribute("data-value")] = element.value;
            });
        }
        this.getlibarycourse(searchcourse, customvalues);
    };


    Formatkickstart.prototype.getlibarycourse = function(searchcourse, customvalues) {
        let courselist = document.querySelector(".list-library-courses");
        if (courselist) {
            let self = this;
            let args = {
                contextid: self.contextId,
                courseid: self.courseId,
                menuid: self.menuid,
                searchcourse: searchcourse,
                customvalues : JSON.stringify(customvalues),
            };

            const promise = Fragment.loadFragment(
                'format_kickstart',
                'get_library_courselist',
                self.contextId,
                args
            );

            promise.then((html, js) => {
                Templates.replaceNode(courselist, html, js);
            }).catch();
        }
    };

    Formatkickstart.prototype.getKickstartTemplate = function(action, value) {
        let templatelist = document.querySelector(".template-list");
        let searchBox = document.querySelector(".kickstart-page #search-template");
        let searchvalue = (searchBox != undefined) ? searchBox.value : '';
        if (templatelist) {
            let self = this;
            let args = {
                contextid: self.contextId,
                courseid: self.courseId,
                menuid: self.menuid,
                action: action,
                value: value,
                search: searchvalue,
            };

            const promise = Fragment.loadFragment(
                'format_kickstart',
                'get_kickstart_templatelist',
                self.contextId,
                args
            );

            promise.then((html, js) => {
                Templates.replaceNode(templatelist, html, js);
            }).catch();
        }
    };

    Formatkickstart.prototype.templateSearchHandler = function(event) {
        let value = event.currentTarget.value;
        this.getKickstartTemplate('searchtemplate', value);
    };

    Formatkickstart.prototype.templateviewHandler = function(event) {
        let value = event.currentTarget.getAttribute("data-value");
        const tileView = document.getElementById('tile-view');
        const listView = document.getElementById('list-view');

        if (value === 'tile') {
            tileView.classList.add('active');
            listView.classList.remove('active');
        } else {
            listView.classList.add('active');
            tileView.classList.remove('active');
        }
        this.getKickstartTemplate('changetemplate', value);
    };

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

    const moveOutMoreMenu = (navMenu) => {

        if (navMenu === null) {
            return;
        }

        var menu = navMenu.querySelector('a.kickstart-nav');

        if (menu === null) {
            return;
        }

        menu = menu.parentNode;
        menu.dataset.forceintomoremenu = false;
        menu.querySelector('a').classList.remove('dropdown-item');
        menu.querySelector('a').classList.add('nav-link');
        menu.parentNode.removeChild(menu);

        var moreMenu = navMenu.querySelector('li.dropdownmoremenu a.dropdown-toggle');
        if (moreMenu) {
            moreMenu.classList.remove('active');
        }

        // Insert the stored menus before the more menu.
        navMenu.insertBefore(menu, navMenu.children[1]);
        window.dispatchEvent(new Event('resize')); // Dispatch the resize event to create more menu.
    };

    return {
        init: function(contextid, courseid, menuid, filteroptions) {
            return new Formatkickstart(contextid, courseid, menuid, filteroptions);
        },

        instanceMenuLink: function () {
            var primaryNav = document.querySelector('.secondary-navigation ul.more-nav');
            moveOutMoreMenu(primaryNav);
        },
    };
});