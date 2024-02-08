function Ruigehond010setup() {
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
                    'nonce': Ruigehond010_global.nonce
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
        // enhance the input elements to Ruigehond010input elements
        $.each($('input[type="checkbox"].ruigehond010.ajaxupdate, input[type="text"].ruigehond010.ajaxupdate, textarea.ruigehond010.ajaxupdate, input[type="button"].ruigehond010.ajaxupdate'), function (key, value) {
            value.prototype = new Ruigehond010_input($, value);
        });
// okipokoi
    })(jQuery);
}

/**
 *  copied from ruigehond008..., make this better please
 */
function Ruigehond010_input($, HTMLElement) {
    this.input = HTMLElement;
    this.$input = $(HTMLElement);
    this.$ = $; // cache jQuery to stay compatible with everybody
    this.id = parseInt(this.$input.attr('data-id'));
    this.ajax = new Ruigehond010Ajax(this);
    // suggestions are disabled when input lacks class ajaxsuggest
    this.suggest = new Ruigehond010InputSuggestions(this);
    var _this = this;
    if (HTMLElement.type === 'button') {
        // currently only a delete button exists, so you can assume this is it
        this.$input.off('.ruigehond010').on('click.ruigehond010', function () {
            _this.delete();
        });
    } else if (HTMLElement.type === 'checkbox') {
        this.$input.off('.ruigehond010').on('change.ruigehond010', function () {
            if (this.getAttribute('data-column_name') === 'review_online') {
                _this.toggleReviewOnline(this.checked);
            } else if (this.getAttribute('data-handle') === 'upsert_option') {
                _this.saveBooleanOption(this.checked);
            } else {
                console.error('doesn\'t work... value = ' + this.checked);
            }
        })
    } else { // text or textarea
        this.$input.off('.ruigehond010').on('blur.ruigehond010', function (event) {
            _this.save(event);
        }).on('keyup.ruigehond010', function (event) {
            _this.typed(event);
        }).on('keydown.ruigehond010', function (event) { // prevent form from submitting
            if (event.which === 13 && !event.shiftKey) {
                return false; // jQuery way to stopPropagation and preventDefault at the same time.
            }
        });
    }
}

Ruigehond010_input.prototype.typed = function (e) {
    //console.log(e.which);
    switch (e.which) {
        case 13: // enter
            if (!e.shiftKey) {
                if (this.id === 0) {
                    this.$input.blur(); // when no specific focus, ruigehond will focus on the new row after ajax return
                } else {
                    this.focusNext(this.$('.ruigehond010.tabbed')); // will cause blur on this element which causes save
                }
            }
            break;
        case 27: // escape
            this.escape();
            break;
        case 38: // arrow up
            this.suggest.previous();
            break;
        case 40: // arrow down
            this.suggest.next();
            break;
        default:
            this.suggest.filter();
    }
    this.checkChanged();
};

Ruigehond010_input.prototype.getData = function () {
    // returns an object containing all 'data' attributes
    var _this = this,
        data = {};
    this.$input.each(function () {
        _this.$.each(this.attributes, function () {
            // this.attributes is not a plain object, but an array
            // of attribute nodes, which contain both the name and value
            if (this.specified && this.name.substr(0, 5) === 'data-') {
                data[this.name.substr(5)] = this.value;
            }
        });
        // get index from the parent row, if you have it // TODO this is bad programming but quick for now...
        var rows, current_row, idx, len;
        if ((current_row = _this.$input.parents('.row')).length === 1) {
            // console.log(_this.$(current_row).parents('.global_option.'+data['option_name']).find('.row'));
            if ((rows = _this.$(current_row).parents('.global_option.'+data['option_name']).find('.row'))) {
                for (idx = 0, len = rows.length; idx < len; ++idx) {
                    if (rows[idx] === current_row.get(0)) {
                        data['index'] = idx;
                        break;
                    }
                }
            }
        }
    });
    return data;
};

Ruigehond010_input.prototype.delete = function () { // this can actually delete several things, depending on handle
    var data = this.getData(),
        _this = this;
    this.ajax.call(data, function (json) {
        if (data.handle === 'clear_offer') {
            // clear all the values...
            document.querySelectorAll('input[data-handle="update_offer"]').forEach(function (el) {
                el.value = '';
            });
        } else if (data.handle === 'undelete') {
            _this.$input.parents('.' + data.table_name + '_row').removeClass('marked-for-deletion');
        } else if (data.handle === 'delete_permanently') {
            _this.$input.parents('.' + data.table_name + '_row').remove();
        } else if (data.handle === 'delete_array_option') {
            _this.$input.parents('.row').remove();
        } else {
            _this.$input.parents('.' + data.table_name + '_row').addClass('marked-for-deletion'); // <- indicate it's deleting at the moment
        }
    });
};
Ruigehond010_input.prototype.saveBooleanOption = function () {
    var data = this.getData(),
        _this = this;
    _this.input.classList.add('unsaved');
    data.value = (this.input.checked ? 1 : 0);
    this.ajax.call(data, function (json) {
        if (json.success === true) _this.input.classList.remove('unsaved');
    });
};
Ruigehond010_input.prototype.toggleReviewOnline = function (flag) {
    var data = this.getData(),
        _this = this;
    data.value = (this.input.checked ? 1 : 0);
    this.ajax.call(data, function (json) {
        if (json.success === true) {
            data = json.data;
            if (data.value === '1') {
                _this.$input.parents('.' + data.table_name + '_row').removeClass('offline');
            } else {
                _this.$input.parents('.' + data.table_name + '_row').addClass('offline');
            }
        } else {
            console.error('Update error, returned json:');
            console.log(json);
        }
    });

};

Ruigehond010_input.prototype.save = function (e) {
    this.suggest.remove();
    if (this.hasChanged()) {
        console.log('Send update to server.');
        // handle input based on data
        var data = this.getData();
        data.disable = true;
        data.value = this.$input.val();
        var _this = this;
        this.ajax.call(data, function (json) {
            _this.suggest.remove();
            if (json.data) {
                if (_this.id === 0) {
                    // new id is returned by server
                    _this.id = json.data.id;
                    // add at the end
                    _this.$input.parent('.review_tag_row').before(json.data.html);
                    // clear input
                    _this.$input.val('');
                    _this.$input.removeClass('unsaved');
                    _this.$input.removeAttr('disabled');
                    // (re-)activate handlers for input
                    Ruigehond010setup(); // TODO you could only assign the prototypes to the new input elements
                    // if there is no focus yet, focus on the value of the new row
                    if (document.activeElement.tagName === 'BODY') { // there is no specific focus
                        _this.$('.ruigehond010.input.tag[data-id="' + _this.id.toString() + '"]').focus();
                    }
                } else { // update existing
                    _this.updateInput(json.data.value);
                    if (json.data.nonexistent) {
                        _this.$input.addClass('nonexistent');
                    } else {
                        _this.$input.removeClass('nonexistent');
                    }
                }
            } else {
                console.error('Expected object "data" in response, but not found');
            }
        })
    }
    this.checkChanged();
};

Ruigehond010_input.prototype.updateInput = function (value) {
    this.$input.attr({
        'value': value,
        //placeholder: value,
        'data-value': value,
    });
    this.checkChanged();
};

Ruigehond010_input.prototype.escape = function () {
    this.suggest.remove();
    this.$input.val(this.$input.attr('data-value'));
};

Ruigehond010_input.prototype.focusNext = function ($tabbed) {
    // focus on the next .tabbed item
    var found = false, i, len;
    for (i = 0, len = $tabbed.length; i < len; ++i) {
        if (found === true) {
            $tabbed[i].focus();
            return;
        } else if ($tabbed[i] === this.input) {
            found = true;
        }
    }
    this.input.blur();
};
Ruigehond010_input.prototype.checkChanged = function () {
    if (this.hasChanged()) {
        this.$input.addClass('unsaved'); // class will only be added once, no need to check if it's present already
    } else {
        this.$input.removeClass('unsaved');
    }
};
Ruigehond010_input.prototype.hasChanged = function () {
    if (this.$input.attr('data-value') === this.$input.val()) {
        return false;
    } else if (this.$input.attr('data-id') === '0' && this.$input.val() === '') {
        return false; // new property with value of '' means no change
    } else if (!this.$input.attr('data-value') && !this.$input.val()){
        return false; // everything's empty
    } else {
        return true;
    }
};

function Ruigehond010Ajax(ruigehond_input) {
    // it receives a Ruigehond010_input instance, you can get all info from there
    this.hond = ruigehond_input;
    this.post_id = ruigehond_input.$("#post_ID").val();
}

Ruigehond010Ajax.prototype.call = function (data, callback) {
    var $input = this.hond.$input;
    var hond = this.hond;
    // keep track of ajax communication
    var timestamp = Date.now();
    data.action = 'ruigehond010_handle_input';
    data.post_id = this.post_id;
    data.timestamp = timestamp;
    data.nonce = Ruigehond010_global.nonce;
    $input.attr({'data-timestamp': timestamp});
    if (data.disable === true) {
        $input.attr({'disabled': 'disabled'});
    }
    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
    jQuery.ajax({
        url: ajaxurl,
        data: data,
        dataType: 'JSON',
        method: 'POST',
        success: function (json) {
            if (!json.data || json.data.timestamp === $input.attr('data-timestamp')) { // only current (ie last) ajax call is valid
                if (json.success) { // update succeeded
                    if (typeof callback === 'function') {
                        callback(json);
                        // TODO might be cleaner to use "this" in the calling code, using callbackObj like suggest.filter
                    }
                } else { // update failed
                    // show fail messages, maybe a confirmation is needed?
                    console.warn('No success.');
                }
                $input.removeAttr('disabled');
                $input.removeAttr('data-timestamp');
                // returnobject can have 1 question which requires feedback with 2 or more answers
                if (json.question) {
                    var p = new RuigehondModal(hond, json.question);
                    p.popup();
                }
                for (var i = 0; i < json.messages.length; ++i) {
                    var msg = json.messages[i],
                        ntc = new RuigehondNotice(msg.text);
                    ntc.set_level(msg.level);
                    ntc.popup();
                }
            } else {
                console.warn('timestamp ' + json.data.timestamp + ' incorrect, need: ' + $input.attr('data-timestamp'))
            }
        },
        error: function (thrownError) {
            $input.removeAttr('disabled');
            $input.removeAttr('timestamp');
            // TODO error handling nicely
            console.log(thrownError);
        }
    });
};

function Ruigehond010InputSuggestions(ruigehond_input) {
    // it receives a Ruigehond010_input instance, you can get all info from there
    this.hond = ruigehond_input;
    this.disabled = !this.hond.$input.hasClass('ajaxsuggest');
    if (!this.disabled) {
        this.suggest_column = this.hond.$input.attr('data-column_name');
        this.suggest_id = 'datalist_' + this.suggest_column + '_' + this.hond.id;
        this.lastTyped = '';
    }
    // don't initialize here, for all the ajax calls slow down, initialize JIT
}

Ruigehond010InputSuggestions.prototype.hasDatalist = function () {
    return (this.hond.$('#' + this.suggest_id).length === 1);
};
Ruigehond010InputSuggestions.prototype.next = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.next, this);
    } else {
        var $current_suggestion = this.hond.$('#' + this.suggest_id + ' li.selecting');
        if ($current_suggestion.length) {
            var $next = $current_suggestion.nextAll(':visible').first();
            if ($next) {
                this.hond.$('#' + this.suggest_id + ' li').removeClass('selecting');
                $next.addClass('selecting');
            }
        } else {
            this.hond.$('#' + this.suggest_id + ' li:visible').first().addClass('selecting');
        }
        this.hond.$input.val(this.getCurrent() || this.lastTyped);
    }
};
Ruigehond010InputSuggestions.prototype.previous = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.previous, this);
    } else {
        var $current_suggestion = this.hond.$('#' + this.suggest_id + ' li.selecting');
        if ($current_suggestion.length) {
            var $prev = $current_suggestion.prevAll(':visible').first();
            if ($prev) {
                this.hond.$('#' + this.suggest_id + ' li').removeClass('selecting');
                $prev.addClass('selecting');
            }
        } else {
            this.hond.$input.focus();
        }
        this.hond.$input.val(this.getCurrent() || this.lastTyped);
    }
};
Ruigehond010InputSuggestions.prototype.getCurrent = function () {
    return this.hond.$('#' + this.suggest_id + ' li.selecting input').val();
};
Ruigehond010InputSuggestions.prototype.filter = function () {
    if (this.disabled) return;
    if (!this.hasDatalist()) {
        this.initialize(this.filter, this);
    } else {
        var value = this.hond.$input.val(),
            _this = this;
        this.lastTyped = value;
        this.hond.$('#' + this.suggest_id + ' li').css({'display': 'none'}).filter(function () {
            return _this.hond.$(this).find('input').val().toLowerCase().indexOf(value.toLowerCase()) >= 0;
        }).css({'display': 'block'});
    }
    // if no suggestions are visible, hide the list, scrollbars remain visible otherwise
    if (this.hond.$('#' + this.suggest_id + ' li:visible').length === 0) {
        this.hond.$('#' + this.suggest_id).css({'visibility': 'hidden'});
    } else {
        this.hond.$('#' + this.suggest_id).css({'visibility': 'visible'});
    }
};
Ruigehond010InputSuggestions.prototype.remove = function () {
    try {
        this.hond.$('#' + this.suggest_id).remove();
    } catch (e) {
    }
};
Ruigehond010InputSuggestions.prototype.initialize = function (callback, callbackObj) {
    if (this.disabled) return;
    // you can fetch the whole list just once, so no repeated ajax calls for suggestions please, just wait for the first one to come back
    if (this.calling) return;
    this.calling = true;
    var data = this.hond.getData();
    data.handle = 'suggest_' + this.suggest_column;
    var self = this;
    this.hond.ajax.call(data, function (json) {
        if (self.hond.input !== document.activeElement) return; // too late, user moved on
        if (!self.hasDatalist()) { // if not exists, add the datalist
            // TODO possible bug when busy and another ajax call comes back right before the id is added to the dom
            var $input = self.hond.$input;
            console.log(json);
            $input.before($input, '<ul id="' + self.suggest_id + '" class="ruigehond datalist"></ul>');
            // now add suggestions received by server as options to the list
            var $datalist = self.hond.$('#' + self.suggest_id);
            if (json.data.suggestions) {
                var handle = json.data.column_name; //self.hond.handle;
                var suggestions = json.data.suggestions, suggestion;
                for (var i = 0, len = suggestions.length; i < len; ++i) {
                    suggestion = suggestions[i][handle];
                    if ('' === suggestion) {
                        console.warn('Empty string for suggestion ' + handle);
                    } else {
                        // the added input element is because of utf-8 symbols being rendered as emojis in plain html
                        // https://stackoverflow.com/questions/32915485/how-to-prevent-unicode-characters-from-rendering-as-emoji-in-html-from-javascrip
                        $datalist.append('<li><input value="' + suggestion.replaceAll('"', '&quot;') + '"/></li>');
                    }
                }
            }
            $datalist.css({
                'left': Math.floor($input.position().left) + 'px',
                'top': Math.floor($input.position().top + $input.height()) + 'px'
            });
            self.hond.$('#' + self.suggest_id + ' li').off('mousedown').on('mousedown', function () {
                $input.val(self.hond.$(this).find('input').val()).blur(); // here this is the li element
                return false; // prevent default etc.
            });
            // make the list disappear if the user clicks somewhere else
            self.hond.$(document).off('.ruigehond010.datalist.' + self.suggest_id).on('mouseup.ruigehond010.datalist.' + self.suggest_id, function () {
                self.remove();
            });
        }
        if (typeof callback === 'function') {
            callback.apply(callbackObj);
        }
        self.calling = false;
    });
};

function RuigehondModal(ruigehond_input, question_as_json) {
    this.q = {};
    this.answers = [];
    this.question = 'Modal';
    this.hond = ruigehond_input; // the ruigehond that called this modal
    if (typeof question_as_json === 'object') {
        this.set_question(question_as_json);
    }
}

RuigehondModal.prototype.set_question = function (question_as_json) {
    this.q = question_as_json;
    this.question = this.q.text;
    var a = this.q.answers;
    for (var i = 0; i < a.length; ++i) {
        this.answers[i] = a[i];
    }
};
RuigehondModal.prototype.popup = function () {
    var _this = this,
        $w = this.hond.$(document.createElement('div')), // wrapper
        i, len;
    _this.close();
    $w.attr({
        class: 'ruigehond modal wrapper'
    });
    $w.on('click', function () {
        _this.close();
    });
    var $d = this.hond.$(document.createElement('div')); // dialog
    $d.attr({
        id: 'RuigehondModal',
        class: 'ruigehond modal dialog'
    });
    var $c = this.hond.$(document.createElement('button'));
    $c.attr({
        type: 'button',
        class: 'notice-dismiss'
    });
    $c.on('click', function () {
        _this.close();
    });
    $d.append($c);
    $d.append('<h1>' + this.question.toString() + '</h1>');
    for (i = 0, len = this.answers.length; i < len; ++i) {
        $d.append(this.answer_element(this.answers[i]));
    }
    this.hond.$('body').append($w, $d);
    if (i > 0) { // focus on the last answer button so a simple 'enter' suffices for the default action
        $d.children(i).focus();
    }
};
RuigehondModal.prototype.answer_element = function (answer) {
    var _this = this;
    var $b = this.hond.$(document.createElement('input'));
    $b.attr({
        type: 'button',
        value: answer.text,
        class: 'button',
    });
    if (answer.data) {
        $b.attr({
            'data-id': answer.data.id,
            'data-handle': answer.data.handle
        });
        $b.on('mouseup keyup', function (event) {
            if (event.type === 'keyup' && event.key !== 'Enter') return;
            //console.log(_this.hond.$input);
            _this.hond.ajax.call(answer.data, function (json) {
                if (json.data) {
                    if (json.data.handle === 'undelete') {
                        _this.hond.$input.parents('.' + json.data.table_name + '_row').removeClass('marked-for-deletion');
                    } else if (json.data.handle === 'delete_permanently') {
                        _this.hond.$input.parents('.' + json.data.table_name + '_row').css({'display': 'none'});
                    } else {
                        _this.hond.updateInput(json.data.value);
                    }
                } else {
                    console.error('Expected object "data" in response, but not found');
                }
                _this.close();
            });
            //ruigehond008_handleinput(event, _this.hond.$input, answer.data)
        });
    } else {
        $b.on('mouseup', function () { // always close the dialog
            _this.close();
        });
    }
    return $b;
};

RuigehondModal.prototype.close = function () {
    var _this = this;
    this.hond.$('.ruigehond.modal').fadeOut(300, function () {
        _this.hond.$(this).remove();
        _this.hond.$input.focus();
    });
};

function RuigehondNotice(text_as_string) {
    this.text = text_as_string;
    this.level = 'log';
    var _this = this;
    (function ($) {
        _this.$ = $;
    })(jQuery);
}

RuigehondNotice.prototype.popup = function () {
    var $n = this.$('.ruigehond.notices').first();
    if ($n.length === 0) { // create notices container if not present
        $n = this.$(document.createElement('div'));
        $n.attr({
            'class': 'ruigehond notices'
        });
        this.$('body').append($n);
    }
    // display this notice
    var $d = this.$(document.createElement('div'));
    $d.attr({
        'class': 'ruigehond notice ' + this.level
    });
    $d.html(this.text);
    // show the message
    this.$($n).append($d);
    this.$element = $d;
    var _this = this;
    if (this.level === 'log') { // hide ok messages after a while
        setTimeout(function () {
            _this.hide();
        }, 2000);
    } else { // TODO make message dismissible with a button
        setTimeout(function () {
            _this.hide();
        }, 3000);
    }
};
RuigehondNotice.prototype.hide = function () {
    try {
        var _this = this;
        this.$element.fadeOut(300, function () {
            _this.$(this).remove();
        });
    } catch (e) {
        // fail silently
    }
};
RuigehondNotice.prototype.set_level = function (level) {
    this.level = level;
};
/**
 * end of copied from ruigehond008
 */

/* only after everything is locked and loaded we’re initialising */
if (document.readyState === 'complete') {
    Ruigehond010setup();
} else {
    window.addEventListener('load', function (event) {
        Ruigehond010setup();
    });
}