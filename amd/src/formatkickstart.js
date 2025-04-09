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
 define(['jquery', 'core/str', 'core/notification', 'core/config', 'core/ajax', 'core/fragment', 'core/templates',
    'core/modal_events', 'core/modal_factory', 'core/toast'],
 function($, str, notification, Config, Ajax, Fragment, Templates, ModalEvents, ModalFactory, Toast) {

    /**
     * Controls kicstart javascript.
     * @param {int} contextid
     * @param {int} courseid
     * @param {int} menuid
     * @param {boolean} filteroptions
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

            var librarycourse = document.querySelectorAll(".librarycourse-filter-item .filter-item");
            if (librarycourse) {
                librarycourse.forEach((element) => {
                    element.addEventListener('change', self.libraryCourseHandler.bind(this));
                });
            }

            var librarysort = document.querySelectorAll(".kickstart-courselibrary-sort.sort-options a");
            if (librarysort) {
                librarysort.forEach((element) => {
                    element.addEventListener('click', (e) => {
                        // Remove active class from all sort links
                        librarysort.forEach(link => link.classList.remove('sort-active'));
                        // Add active class to clicked element
                        element.classList.add('sort-active');
                        // Call the original handler
                        self.libraryCourseHandler.bind(this)(e);
                    });
                });
            }

        }


        var pagination = document.querySelectorAll(".kickstart-page .pagination li");
        if (pagination) {
            pagination.forEach((element) => {
                element.addEventListener('click', self.libraryCourseHandler.bind(this));
            });
        }

        var showcontentHandler = document.querySelectorAll(".import-course-list-section .show-content-button");
            if (showcontentHandler) {
                showcontentHandler.forEach((element) => {
                    element.addEventListener('click', () => {
                        str.get_strings([
                            {key: 'showcontents', component: 'format_kickstart'},
                            {key: 'hidecontents', component: 'format_kickstart'}
                        ]).then(function(strings) {
                            if (element.textContent.trim() === strings[0]) {
                                element.textContent = strings[1];
                            } else if (element.textContent.trim() === strings[1]) {
                                element.textContent = strings[0];
                            }
                        });
                    });
                });
            }

        var importActivity = document.querySelectorAll(".import-course-list-section .activity-items .import-activity");
        if (importActivity) {
            importActivity.forEach((element) => {
                element.addEventListener('click', self.importActivityHandler.bind(this));
            });
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


    Formatkickstart.prototype.importActivityHandler = function(event) {
        event.preventDefault();
        let self = this;
        var courseid = event.currentTarget.getAttribute('data-course');
        var cmid = event.currentTarget.getAttribute('data-module');
        var maincourse = event.currentTarget.getAttribute('data-maincourse');
        var modname = event.currentTarget.getAttribute('data-modname');
        var args = {
            courseid: courseid,
            cmid: cmid,
            maincourse: maincourse,
            modname: modname,
        };

        ModalFactory.create({
            type: ModalFactory.types.SAVE_CANCEL,
            title: str.get_string('importactivity', 'format_kickstart'),
            body: Fragment.loadFragment('format_kickstart', 'get_import_module_box', self.contextId, args),
        }).then(function(modal) {
            modal.setButtonText('save', str.get_string('importandview', 'format_kickstart'));
            modal.setButtonText('cancel', str.get_string('importandreturn', 'format_kickstart'));
            // Handle form submission.
            modal.getRoot().on(ModalEvents.save, function() {
                var sectionId = $('#import-module-section').val();
                args.sectionid = sectionId;
                args.action = 'view';
                // Perform import.
                self.importCourse(args);
                modal.destroy();
            }.bind(this));

            modal.getRoot().on(ModalEvents.cancel, function() {
                var sectionId = $('#import-module-section').val();
                args.sectionid = sectionId;
                args.action = 'return';
                // Perform import.
                self.importCourse(args);
                modal.destroy();
            }.bind(this));

            modal.show();
        }.bind(this));
    };


    Formatkickstart.prototype.importCourse = function(args) {
        var self = this;
        var promise = Fragment.loadFragment('format_kickstart', 'import_activity_courselib', self.contextId, args);
        promise.then((viewurl) => {
            if (args.action == 'view') {
                window.location.href = viewurl;
            } else {
                str.get_string('importactivitysuccessfully', 'format_kickstart').then(function(string) {
                    Toast.add(string, {type: 'success'});
                });
            }
        }).catch();
    };


    Formatkickstart.prototype.fullmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('.accordion-item').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('.accordion-item').find('.trim-summary');
        if (trimcontent.hasClass('summary-show')) {
            trimcontent.removeClass('summary-show');
            fullContent.addClass('summary-show');
        }
    };

    Formatkickstart.prototype.trimmodcontentHandler = function(event) {
        var THIS = $(event.currentTarget);
        let fullContent = $(THIS).closest('.accordion-item').find('.fullcontent-summary');
        let trimcontent = $(THIS).closest('.accordion-item').find('.trim-summary');
        if (fullContent.hasClass('summary-show')) {
            fullContent.removeClass('summary-show');
            trimcontent.addClass('summary-show');
        }
    };

    Formatkickstart.prototype.libraryCourseHandler = function(event) {
        event.preventDefault();
        let page = event.currentTarget.getAttribute('data-page-number');
        page = page ? page - 1 : 0;
        let sort = event.currentTarget.getAttribute('data-sort');
        if (!sort) {
           var sorthandler = document.querySelector(".kickstart-courselibrary-sort .sort-link.sort-active");
           if (sorthandler) {
                sort = sorthandler.getAttribute('data-sort');
           }
        }
        let searchcourse = document.querySelector("#search-course-library").value;
        let customfieldsitems = document.querySelectorAll(".library-customfield-field.librarycourse-filter-item .filter-item");
        let customvalues = {};
        if (customfieldsitems) {
            customfieldsitems.forEach((element) => {
                customvalues[element.getAttribute("data-value")] = element.value;
            });
        }
        this.getlibarycourse(searchcourse, customvalues, sort, page);
    };


    Formatkickstart.prototype.getlibarycourse = function(searchcourse, customvalues, sort, page) {
        let courselist = document.querySelector(".import-course-list-section");
        if (courselist) {
            let self = this;
            let args = {
                contextid: self.contextId,
                courseid: self.courseId,
                menuid: self.menuid,
                searchcourse: searchcourse,
                customvalues : JSON.stringify(customvalues),
                sort: sort,
                page: page,
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

    return {
        init: function(contextid, courseid, menuid, filteroptions) {
            return new Formatkickstart(contextid, courseid, menuid, filteroptions);
        }
    };
});