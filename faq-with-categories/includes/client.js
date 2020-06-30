function ruigehond010_hideChildLists() {
    var lists, list, i, len;
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            (list = lists[i]).style.display =
                (list.hasAttribute('data-parent') &&
                    list.getAttribute('data-parent') === '0') ? 'block' : 'none';
        }
    }
}

function ruigehond010_filter(select) {
    var lists, list, options, option, parent_id, i, len;
    console.log(select);
    if (null === select) { // only display the parent, this is already done by php as well, but just to be sure
        ruigehond010_hideChildLists();
    } else {
        ruigehond010_hideChildLists();
        // display a child list of the selected option option if it exists
        if (select.selectedIndex > 0 && (option = select.options[select.selectedIndex])) {
            if (option.hasAttribute('data-ruigehond010_term_taxonomy_id')) {
                parent_id = option.getAttribute('data-ruigehond010_term_taxonomy_id');
                if ((list = document.querySelector('.ruigehond010[data-parent="' + parent_id + '"]'))) {
                    list.style.display = 'block';
                }
            }
        }
        // travel up the chain making the lists visible until you reach data-parent="0"
        while (select.hasAttribute('data-parent') &&
        (parent_id = select.getAttribute('data-parent')) !== '0') {
            select.style.display = 'block';
            if ((option = document.querySelector('[data-ruigehond010_term_taxonomy_id="' + parent_id + '"]'))) {
                select = option.parentElement;
                for (i = 0, len = (options = select.options).length; i < len; ++i) {
                    if (options[i] === option) {
                        select.selectedIndex = i;
                        select.style.display = 'block';
                        break;
                    }
                }
            } else {
                console.error('ruigehond010: something is wrong with the lists');
                break;
            }
        }
        // filter the faqs by the most specific term

    }

}

function ruigehond010_start() {
    var options, option, i, len, parent_id, list, lists, lists_by_parent = {}, selected_list = null;
    // sort the select lists also, from parent to child to grandchild etc.
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            list = lists[i];
            lists_by_parent[list.getAttribute('data-parent')] = list;
            if ((option = list.querySelector('[selected]'))) selected_list = option.parentElement;
        }
    }
    if ((options = ruigehond_cloneShallow(document.querySelectorAll('[data-ruigehond010_term_taxonomy_id]')))) {
        // sort the lists
        for (i in options){
            if ((list = lists_by_parent[(parent_id = options[i].getAttribute('data-ruigehond010_term_taxonomy_id'))])) {
                console.warn('ordering');
                console.log(list);
                // put the list after the list this option is in
                document.querySelector('[data-ruigehond010_term_taxonomy_id="' + parent_id + '"]').parentElement.insertAdjacentElement('afterend', list);
            }
        }
    }
    ruigehond010_filter(selected_list);
}

/* ponyfills :-) */
function ruigehond_isInt(value) {
    var x;
    if (isNaN(value)) {
        return false;
    }
    x = parseFloat(value);
    return (x | 0) === x;
}

function ruigehond_cloneShallow(obj) {
    try {
        return Object.assign({}, obj); // <-- way faster if it's available, even including the try / catch
    } catch (e) {
        return JSON.parse(JSON.stringify(obj));
    }
}

/* only after everything is locked and loaded we’re initialising */
if (document.readyState === "complete") {
    ruigehond010_start();
} else {
    window.addEventListener('load', function (event) {
        ruigehond010_start();
    });
}