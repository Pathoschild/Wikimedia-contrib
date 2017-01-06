$(function () {
    "use strict";

    function animation() {
        this.animate({
            opacity: 'toggle',
            height: 'toggle'
        }, 200);
    }

    $('form').collapse({
        head: 'h3',
        group: '.extended-search',
        show: animation,
        hide: animation
    });

    $('#filters').multiSelect({
        noneSelected: 'no filters',
        oneOrMoreSelected: '% filters',
        selectAll: false
    });
});