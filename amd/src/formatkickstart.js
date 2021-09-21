define(['core/loadingicon'], function(Loadingicon) {

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