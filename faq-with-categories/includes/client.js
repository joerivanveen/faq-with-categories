function ruigehond010_showDomElement(element) {
    element.style.display = 'block';
}
function ruigehond010_hideDomElement(element) {
    element.style.display = 'none';
}

function ruigehond010_resetLists() {
    var list;
    ruigehond010_hideSubLists();
    // set the first list to 'choose'
    if ((list = document.querySelector('[data-ruigehond010_parent="0"]'))) list.selectedIndex = 0;
}
function ruigehond010_resetSearch() {
    var search_input;
    if ((search_input = document.getElementById('ruigehond010_search'))) search_input.value = '';
}

function ruigehond010_hideSubLists() {
    var lists, list, i, len;
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            (list = lists[i]).style.display =
                (list.hasAttribute('data-ruigehond010_parent') &&
                    list.getAttribute('data-ruigehond010_parent') === '0') ? 'block' : 'none';
        }
    }
}

function ruigehond010_getAllOptionValues(list) {
    var arr = [], i, len, options, option, parent_id, sub_list;
    // start at i = 1 because you can skip the hidden 'choose' entry
    for (i = 1, len = (options = list.options).length; i < len; ++i) {
        arr.push((option = options[i]).value.toLowerCase());
        // if this option has a sublist, get all those options as well
        if (option.hasAttribute('data-ruigehond010_term_taxonomy_id')) {
            parent_id = option.getAttribute('data-ruigehond010_term_taxonomy_id')
            if ((sub_list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                arr = arr.concat(ruigehond010_getAllOptionValues(sub_list));
            }
        }
    }
    return arr;
}

function ruigehond010_filter(select) {
    var list, options, option, parent_id, i, len, terms, posts, post;
    ruigehond010_resetSearch();
    if (null === select) { // only display the parent and set it to first option (which is hidden)
        ruigehond010_resetLists();
    } else {
        ruigehond010_hideSubLists();
        // display a child list of the selected option if it exists
        if (select.selectedIndex > 0 && (option = select.options[select.selectedIndex])) {
            if (option.hasAttribute('data-ruigehond010_term_taxonomy_id')) {
                terms = [option['value'].toLowerCase()];
                parent_id = option.getAttribute('data-ruigehond010_term_taxonomy_id');
                if ((list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                    list.selectedIndex = 0;
                    ruigehond010_showDomElement(list);
                    terms = terms.concat(ruigehond010_getAllOptionValues(list));
                }
            }
        }
        // travel up the chain making the lists visible until you reach data-ruigehond010_parent="0"
        while (select.hasAttribute('data-ruigehond010_parent') &&
        (parent_id = select.getAttribute('data-ruigehond010_parent')) !== '0') {
            ruigehond010_showDomElement(select);
            if ((option = document.querySelector('[data-ruigehond010_term_taxonomy_id="' + parent_id + '"]'))) {
                select = option.parentElement;
                for (i = 0, len = (options = select.options).length; i < len; ++i) {
                    if (options[i] === option) {
                        select.selectedIndex = i;
                        ruigehond010_showDomElement(select);
                        break;
                    }
                }
            } else {
                console.error('faq-with-categories: something is wrong with the select lists');
                break;
            }
        }
        // filter the faqs
        if ((posts = document.getElementById('ruigehond010_faq'))) {
            posts = posts.getElementsByClassName('ruigehond010_post');
            var class_names;
            for (i = 0, len = posts.length; i < len; ++i) {
                post = posts[i];
                class_names = post.className;
                // check if there are overlapping classes
                // todo make it animatable / nicer or something
                if (terms.filter(function (n) {
                    return class_names.indexOf(n) !== -1;
                }).length > 0) {
                    ruigehond010_showDomElement(post);
                } else {
                    ruigehond010_hideDomElement(post);
                }
            }
        } else {
            console.error('faq-with-categories: #ruigehond010_faq not found for filtering...');
        }
    }

}

function ruigehond010_start() {
    var options, option, i, len, parent_id, list, lists, lists_by_parent = {}, selected_list = null, maybe_done,
        search_input;
    /**
     * first get the lists in order: sort them from parent to child and remember if any is pre-checked by php
     */
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            list = lists[i];
            lists_by_parent[list.getAttribute('data-ruigehond010_parent')] = list;
            if ((option = list.querySelector('[selected]'))) selected_list = option.parentElement;
        }
    }
    if ((options = ruigehond_cloneShallow(document.querySelectorAll('[data-ruigehond010_term_taxonomy_id]')))) {
        // sort the lists
        while (true) {
            maybe_done = true; // until proven otherwise
            for (i in options) {
                if ((list = lists_by_parent[(parent_id = options[i].getAttribute('data-ruigehond010_term_taxonomy_id'))])) {
                    // put the list after the list this option is in, only if it's not already later in the DOM, in which case all is ok
                    if ((option = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"] ~ select > [data-ruigehond010_term_taxonomy_id="' + parent_id + '"]'))) {
                        option.parentElement.insertAdjacentElement('afterend', list);
                        maybe_done = false;
                    }
                }
            }
            if (maybe_done) break;
        }
    }
    // run the filter for the first time
    ruigehond010_filter(selected_list);
    /**
     * setup the search field
     */
    if ((search_input = document.getElementById('ruigehond010_search'))) {
        search_input.addEventListener('keyup', function () {
            var post, posts, search_string = this.value.toLowerCase(), i, len;
            if ((posts = document.getElementById('ruigehond010_faq'))) {
                posts = posts.getElementsByClassName('ruigehond010_post');
                for (i = 0, len = posts.length; i < len; ++i) {
                    if ((post = posts[i]).innerText.toLowerCase().indexOf(search_string) !== -1) {
                        ruigehond010_showDomElement(post);
                    } else {
                        ruigehond010_hideDomElement(post);
                    }
                }
            }
        });
        search_input.addEventListener('focus', function() {
            ruigehond010_resetLists();
        });
    }
}

/* ponyfills */
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