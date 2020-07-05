function ruigehond010_setup() {
    // sort functionality stolen from ruigehond008 / user-reviews
    (function ($) {
        $('.rows-sortable').sortable({
            opacity: 0.6,
            revert: true,
            cursor: 'grabbing',
            handle: '.sortable-handle',
            start: function () { // please blur any input before sorting to autosave them
                document.activeElement.blur();
            },
        }).droppable({
            greedy: true, // prevent propagation to parents
            drop: function (event, ui) {
                var target = event.target,
                    dropped_id = ui.draggable[0].getAttribute('data-id'),
                    children = target.childNodes,
                    order = {}, row, i, data;
                // disable further ordering until return
                $(target).sortable('disable');
                $('.sortable-handle').addClass('disabled');
                $(ui.draggable).addClass('unsaved');
                // get the new order to send to the server for update
                for (i = 0; i < children.length; ++i) {
                    row = children[i];
                    // apparently jquery ui drops a shadowcopy or something which doesn't contain anything useful
                    // and it keeps the original in the DOM, maybe there's a later event that has rearranged everything
                    // proper, but I want to initiate ajax asap, hence we look for the placeholder element where we
                    // put the id the sortable row has been dropped on. In hindsight also kind of logical
                    if (row.getAttribute('data-id') === dropped_id) continue;
                    if (row.getAttribute('data-id') === 0) continue;
                    if (row.getAttribute('data-id') === null) {
                        order[dropped_id] = i;
                    } else {
                        order[row.getAttribute('data-id')] = i;
                    }
                }
                data = {
                    'action': 'ruigehond010_handle_input',
                    'handle': 'order_taxonomy',
                    'order': order,
                    'id': dropped_id,
                };
                $.ajax({
                    url: ajaxurl,
                    data: data,
                    dataType: 'JSON',
                    method: 'POST',
                    success: function (json) {
                        if (json.success === true) {
                            //console.warn(json);
                            // restore sortability to the table and register save
                            $(target).sortable('enable');
                            $('.sortable-handle').removeClass('disabled');
                            if (json.hasOwnProperty('data') && json.data.hasOwnProperty('id')) {
                                $('[data-id="' + json.data.id + '"]').removeClass('unsaved');
                            }
                        } else {
                            /*var ntc = new RuigehondNotice('Order not saved, please refresh page');
                            ntc.set_level('error');
                            ntc.popup();*/
                            console.error(json);
                        }
                    }
                });
            },
        });
    })(jQuery);
}

/* only after everything is locked and loaded we’re initialising */
if (document.readyState === "complete") {
    ruigehond010_setup();
} else {
    window.addEventListener('load', function (event) {
        ruigehond010_setup();
    });
}