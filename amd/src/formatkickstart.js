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
 define(['core/loadingicon'], function(Loadingicon) {

    /**
     * Controls kicstart javascript.
    */
    var Formatkickstart = function() {
        var buttonelement = document.querySelectorAll("#modal-footer .buttons")[0];
        if (buttonelement) {
            buttonelement.insertAdjacentHTML("beforebegin", "<span id='load-action'></span>");
        }
        document.querySelectorAll(this.confirmform)[0].addEventListener("submit", this.importInstrctions.bind(this));
    };

    Formatkickstart.prototype.confirmform = ".buttons .singlebutton form";
    Formatkickstart.prototype.confirmbutton = ".buttons .singlebutton form button";
    Formatkickstart.prototype.loadiconElement = "#modal-footer span#load-action";

    Formatkickstart.prototype.importInstrctions = function() {
        var buttons = document.querySelectorAll(this.confirmbutton);
        for (let $i in buttons) {
            buttons[$i].disabled = true;
        }
        Loadingicon.addIconToContainer(this.loadiconElement);
    };

    return {
        init: function() {
            return new Formatkickstart();
        }
    };
});