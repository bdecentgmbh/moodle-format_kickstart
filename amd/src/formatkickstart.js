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
 * Defines Kickstart javascript.
 * @module   format_kickstart/formatkickstart
 * @category  Classes - autoloading
 * @copyright 2021, bdecent gmbh bdecent.de
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import $ from 'jquery';
import * as Str from 'core/str';
import Notification from 'core/notification';
import Config from 'core/config';
import Ajax from 'core/ajax';
import Fragment from 'core/fragment';
import Templates from 'core/templates';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Toast from 'core/toast';

/**
 * Controls Kickstart javascript.
 */
class Formatkickstart {
    /**
     * @param {int} contextid
     * @param {int} courseid
     * @param {int} menuid
     * @param {boolean} filteroptions
     * @return {void}
     */
    constructor(contextid, courseid, menuid, filteroptions) {
        this.contextId = contextid;
        this.courseId = courseid;
        this.menuid = menuid;
        this.confirmbutton = ".buttons .singlebutton form button";
        this.loadiconElement = "#modal-footer span#load-action";
        this.fullDescription = ".list-library-courses .trim-summary .section-summary-action";
        this.trimDescription = ".list-library-courses .fullcontent-summary .section-summary-action";

        this.registerEventListeners(filteroptions);
    }

    /**
     * Bind the page event listeners.
     *
     * @param {boolean} filteroptions Whether the course library filters are shown.
     * @return {void}
     */
    registerEventListeners(filteroptions) {
        const useTemplate = document.querySelectorAll(".templates-block .use-template");
        useTemplate.forEach((element) => {
            element.addEventListener('click', (e) => this.templateHandler(e));
        });

        if (filteroptions) {
            const templateview = document.querySelectorAll(".kickstart-page .listing-view-block a");
            templateview.forEach((element) => {
                element.addEventListener('click', (e) => this.templateviewHandler(e));
            });

            const templatesearch = document.querySelectorAll(".kickstart-page #search-template");
            templatesearch.forEach((element) => {
                element.addEventListener('change', (e) => this.templateSearchHandler(e));
            });

            const librarycourse = document.querySelectorAll(".librarycourse-filter-item .filter-item");
            librarycourse.forEach((element) => {
                element.addEventListener('change', (e) => this.libraryCourseHandler(e));
            });

            const librarysort = document.querySelectorAll(".kickstart-courselibrary-sort.sort-options a");
            librarysort.forEach((element) => {
                element.addEventListener('click', (e) => {
                    // Remove active class from all sort links.
                    librarysort.forEach(link => link.classList.remove('sort-active'));
                    // Add active class to clicked element.
                    element.classList.add('sort-active');
                    // Call the original handler.
                    this.libraryCourseHandler(e);
                });
            });
        }

        const pagination = document.querySelectorAll(".kickstart-page .pagination li");
        pagination.forEach((element) => {
            element.addEventListener('click', (e) => this.libraryCourseHandler(e));
        });

        const showcontentHandler = document.querySelectorAll(".import-course-list-section .show-content-button");
        showcontentHandler.forEach((element) => {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleCourseContents(element);
            });
        });

        const importActivity = document.querySelectorAll(".import-course-list-section .activity-items .import-activity");
        importActivity.forEach((element) => {
            element.addEventListener('click', (e) => this.importActivityHandler(e));
        });

        $('body').delegate(this.fullDescription, "click", (e) => this.fullmodcontentHandler(e));
        $('body').delegate(this.trimDescription, "click", (e) => this.trimmodcontentHandler(e));
    }

    /**
     * Toggle the course-contents accordion next to a "Show contents" button.
     *
     * The accordion is rendered empty by the server template; on first click we
     * fetch the rendered partial via the get_library_coursecontents fragment and
     * inject it. Subsequent clicks only flip visibility (and the button label).
     *
     * @param {HTMLElement} button The .show-content-button that was clicked.
     * @return {void}
     */
    toggleCourseContents(button) {
        const courseid = button.getAttribute('data-courseid');
        const maincourse = button.getAttribute('data-maincourse') || this.courseId;
        const card = button.closest('.card-body');
        if (!card) {
            return;
        }
        const accordion = card.querySelector('.list-library-courses');
        if (!accordion) {
            return;
        }

        // Label toggle is the same as before (just expressed via state, not by
        // comparing translated strings).
        Str.get_strings([
            {key: 'showcontents', component: 'format_kickstart'},
            {key: 'hidecontents', component: 'format_kickstart'}
        ]).then((strings) => {
            const willShow = accordion.classList.contains('d-none');
            button.textContent = willShow ? strings[1] : strings[0];
            return willShow;
        }).then((willShow) => {
            if (button.getAttribute('data-loaded') === '1' || !willShow) {
                // Already populated, or we're hiding -- no fetch needed.
                accordion.classList.toggle('d-none');
                return null;
            }
            // First-time expand: lazy-fetch the partial.
            const args = {
                courseid: courseid,
                maincourse: maincourse,
            };
            return this.loadCourseContents(accordion, button, args);
        }).catch(Notification.exception);
    }

    /**
     * Lazy-fetch and inject the contents of a course into its accordion.
     *
     * @param {HTMLElement} accordion The accordion container to populate.
     * @param {HTMLElement} button The "Show contents" button.
     * @param {Object} args Fragment arguments (courseid, maincourse).
     * @return {Promise}
     */
    loadCourseContents(accordion, button, args) {
        return Fragment.loadFragment(
            'format_kickstart',
            'get_library_coursecontents',
            this.contextId,
            args
        ).then((html, js) => {
            Templates.appendNodeContents(accordion, html, js);
            button.setAttribute('data-loaded', '1');
            accordion.classList.remove('d-none');
            // Wire up the activity-import buttons in the freshly loaded partial.
            accordion.querySelectorAll('.import-activity').forEach((el) => {
                el.addEventListener('click', (e) => this.importActivityHandler(e));
            });
            return null;
        });
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    importActivityHandler(event) {
        event.preventDefault();
        const courseid = event.currentTarget.getAttribute('data-course');
        const cmid = event.currentTarget.getAttribute('data-module');
        const maincourse = event.currentTarget.getAttribute('data-maincourse');
        const modname = event.currentTarget.getAttribute('data-modname');
        const args = {
            courseid: courseid,
            cmid: cmid,
            maincourse: maincourse,
            modname: modname,
        };

        ModalSaveCancel.create({
            title: Str.get_string('importactivity', 'format_kickstart'),
            body: Fragment.loadFragment('format_kickstart', 'get_import_module_box', this.contextId, args),
        }).then((modal) => {
            modal.setButtonText('save', Str.get_string('importandview', 'format_kickstart'));
            modal.setButtonText('cancel', Str.get_string('importandreturn', 'format_kickstart'));
            // Handle form submission.
            modal.getRoot().on(ModalEvents.save, () => {
                const sectionId = $('#import-module-section').val();
                args.sectionid = sectionId;
                args.action = 'view';
                // Perform import.
                this.importCourse(args);
                modal.destroy();
            });

            modal.getRoot().on(ModalEvents.cancel, () => {
                const sectionId = $('#import-module-section').val();
                args.sectionid = sectionId;
                args.action = 'return';
                // Perform import.
                this.importCourse(args);
                modal.destroy();
            });

            modal.show();
            return null;
        }).catch(Notification.exception);
    }

    /**
     * @param {Object} args
     * @return {void}
     */
    importCourse(args) {
        Fragment.loadFragment(
            'format_kickstart',
            'import_activity_courselib',
            this.contextId,
            args
        ).then((viewurl) => {
            if (args.action == 'view') {
                window.location.href = viewurl;
                return null;
            }
            return this.notifyImportSuccess();
        }).catch(Notification.exception);
    }

    /**
     * @return {Promise}
     */
    notifyImportSuccess() {
        return Str.get_string(
            'importactivitysuccessfully',
            'format_kickstart'
        ).then((string) => {
            Toast.add(string, {type: 'success'});
            return null;
        });
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    fullmodcontentHandler(event) {
        const target = $(event.currentTarget);
        const fullContent = target.closest('.accordion-item').find('.fullcontent-summary');
        const trimcontent = target.closest('.accordion-item').find('.trim-summary');
        if (trimcontent.hasClass('summary-show')) {
            trimcontent.removeClass('summary-show');
            fullContent.addClass('summary-show');
        }
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    trimmodcontentHandler(event) {
        const target = $(event.currentTarget);
        const fullContent = target.closest('.accordion-item').find('.fullcontent-summary');
        const trimcontent = target.closest('.accordion-item').find('.trim-summary');
        if (fullContent.hasClass('summary-show')) {
            fullContent.removeClass('summary-show');
            trimcontent.addClass('summary-show');
        }
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    libraryCourseHandler(event) {
        event.preventDefault();
        let page = event.currentTarget.getAttribute('data-page-number');
        page = page ? page - 1 : 0;
        let sort = event.currentTarget.getAttribute('data-sort');
        if (!sort) {
            const sorthandler = document.querySelector(".kickstart-courselibrary-sort .sort-link.sort-active");
            if (sorthandler) {
                sort = sorthandler.getAttribute('data-sort');
            }
        }
        const searchcourse = document.querySelector("#search-course-library").value;
        const customfieldsitems = document.querySelectorAll(".library-customfield-field.librarycourse-filter-item .filter-item");
        const customvalues = {};
        customfieldsitems.forEach((element) => {
            customvalues[element.getAttribute("data-value")] = element.value;
        });
        this.getLibraryCourse(searchcourse, customvalues, sort, page);
    }

    /**
     * @param {string} searchcourse
     * @param {Object} customvalues
     * @param {string} sort
     * @param {int} page
     * @return {void}
     */
    getLibraryCourse(searchcourse, customvalues, sort, page) {
        const courselist = document.querySelector(".import-course-list-section");
        if (courselist) {
            const args = {
                contextid: this.contextId,
                courseid: this.courseId,
                menuid: this.menuid,
                searchcourse: searchcourse,
                customvalues: JSON.stringify(customvalues),
                sort: sort,
                page: page,
            };

            Fragment.loadFragment(
                'format_kickstart',
                'get_library_courselist',
                this.contextId,
                args
            ).then((html, js) => {
                Templates.replaceNode(courselist, html, js);
                return null;
            }).catch(Notification.exception);
        }
    }

    /**
     * @param {string} action
     * @param {string} value
     * @return {void}
     */
    getKickstartTemplate(action, value) {
        const templatelist = document.querySelector(".template-list");
        const searchBox = document.querySelector(".kickstart-page #search-template");
        const searchvalue = (searchBox != undefined) ? searchBox.value : '';
        if (templatelist) {
            const args = {
                contextid: this.contextId,
                courseid: this.courseId,
                menuid: this.menuid,
                action: action,
                value: value,
                search: searchvalue,
            };

            Fragment.loadFragment(
                'format_kickstart',
                'get_kickstart_templatelist',
                this.contextId,
                args
            ).then((html, js) => {
                Templates.replaceNode(templatelist, html, js);
                return null;
            }).catch(Notification.exception);
        }
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    templateSearchHandler(event) {
        const value = event.currentTarget.value;
        this.getKickstartTemplate('searchtemplate', value);
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    templateviewHandler(event) {
        const value = event.currentTarget.getAttribute("data-value");
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
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    templateHandler(event) {
        event.preventDefault();
        const templateName = event.target.getAttribute("data-templatename");
        const templateId = event.target.getAttribute("data-template");
        this.confirmImportTemplate(templateId, templateName);
    }

    /**
     * @param {string} templateId
     * @param {string} templateName
     * @return {void}
     */
    confirmImportTemplate(templateId, templateName) {
        const plugindata = {
            name: templateName
        };
        Str.get_strings([
            {key: 'confirm', component: 'core'},
            {key: 'confirmtemplate', param: plugindata, component: 'format_kickstart'},
            {key: 'import'},
            {key: 'no'}
        ]).then((s) => {
            Notification.confirm(s[0], s[1], s[2], s[3], () => {
                document.body.classList.add("kickstart-icon");
                Ajax.call([{
                    methodname: 'format_kickstart_import_template',
                    args: {templateid: templateId, courseid: this.courseId},
                    done: (response) => {
                        if (response) {
                            const redirect = Config.wwwroot + "/course/view.php?id=" + this.courseId;
                            window.location.assign(redirect);
                        }
                    },
                }]);
            });
            return null;
        }).catch(Notification.exception);
    }
}

export const init = (contextid, courseid, menuid, filteroptions) => {
    new Formatkickstart(contextid, courseid, menuid, filteroptions);
};
