var ruigehond010_i, // timeout to hold of toggleFirst during search and stuff
    ruigehond010_m = false; // tracks whether show more is activated
var min = 3, max = 5; //temp
function ruigehond010_showDomElement(element) {
    //element.style.display = 'block';
    element.style.position = 'inherit';
    (function ($) {
        $(element).fadeIn();
    })(jQuery);
}

function ruigehond010_hideDomElement(element) {
    //element.style.display = 'none';
    element.style.top = element.getBoundingClientRect().top.toString() + 'px';
    //element.style.position = 'fixed';
    (function ($) {
        $(element).fadeOut();
    })(jQuery);
}

function ruigehond010_toggleFirst() {
    var posts = document.getElementById('ruigehond010_faq').querySelectorAll('.ruigehond010_post'),
        i, len, post, rect;
    for (i = 0, len = posts.length; i < len; ++i) {
        if ((rect = (post = posts[i]).getBoundingClientRect()).top > 0 && rect.left > 0) {
            ruigehond010_toggle(post);
            return;
        }
    }
}

function ruigehond010_toggle(li) {
    // walk through all the elements to close them, only open the chosen one (li)
    var faq = document.getElementById('ruigehond010_faq'),
        posts = faq.querySelectorAll('.ruigehond010_post'),
        i, len, post;
    for (i = 0, len = posts.length; i < len; ++i) {
        if ((post = posts[i]) === li) {
            post.classList.add('open');
        } else {
            post.classList.remove('open');
        }
    }
}

function ruigehond010_resetLists() {
    var list;
    ruigehond010_hideSubLists();
    // set the first list to 'choose'
    if ((list = document.querySelector('[data-ruigehond010_parent="0"]'))) {
        list.selectedIndex = 0;
    }
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
        if (option.hasAttribute('data-ruigehond010_term_id')) {
            parent_id = option.getAttribute('data-ruigehond010_term_id')
            if ((sub_list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                arr = arr.concat(ruigehond010_getAllOptionValues(sub_list));
            }
        }
    }
    return arr;
}

function ruigehond010_filter(select) {
    var list, options, option, parent_id, i, len, terms, posts, post, class_names, count = 0;
    ruigehond010_resetSearch();
    if (null === select) { // only display the parent and set it to first option
        ruigehond010_resetLists();
    } else {
        ruigehond010_hideSubLists();
        // display a child list of the selected option if it exists
        if ((option = select.options[select.selectedIndex])) {
            if (option.hasAttribute('data-ruigehond010_term_id')) {
                terms = [option['value'].toLowerCase()];
                parent_id = option.getAttribute('data-ruigehond010_term_id');
                if ((list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                    list.selectedIndex = 0;
                    ruigehond010_showDomElement(list);
                    terms = terms.concat(ruigehond010_getAllOptionValues(list));
                }
            } else {
                terms = ruigehond010_getAllOptionValues(select)
                // add the parent term id as well
                if ((parent_id = select.getAttribute('data-ruigehond010_parent')) > 0) {
                    terms.push('term-' + parent_id.toString());
                }
            }
        }
        // travel up the chain making the lists visible until you reach data-ruigehond010_parent="0"
        while (select.hasAttribute('data-ruigehond010_parent') &&
        (parent_id = select.getAttribute('data-ruigehond010_parent')) !== '0') {
            ruigehond010_showDomElement(select);
            if ((option = document.querySelector('[data-ruigehond010_term_id="' + parent_id + '"]'))) {
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
            for (i = 0, len = posts.length; i < len; ++i) {
                post = posts[i];
                class_names = post.className;
                // check if there are overlapping classes
                if (terms.filter(function (n) {
                    return class_names.indexOf(n) !== -1;
                }).length > 0) {
                    // duplicate code / same as in search (below)
                    if (false === ruigehond010_m && count > max) {
                        ruigehond010_hideDomElement(post);
                    } else {
                        ruigehond010_showDomElement(post);
                    }
                    ++count;
                } else {
                    ruigehond010_hideDomElement(post);
                }
                // duplicate code / same as in search (below)
                if (false === ruigehond010_m && count > max + 1) {
                    ruigehond010_showDomElement(document.getElementById('ruigehond010_more'));
                } else {
                    ruigehond010_hideDomElement(document.getElementById('ruigehond010_more'));
                }
            }
        } else {
            console.error('faq-with-categories: #ruigehond010_faq not found for filtering...');
        }
        // open the first faq item
        if (ruigehond010_i) clearTimeout(ruigehond010_i);
        ruigehond010_i = setTimeout(ruigehond010_toggleFirst, 500);
    }

}

function ruigehond010_start() {
    var options, option, i, len, parent_id, list, lists, maybe_done, search_input, h4, pos, post, post_id, src,
        lists_by_parent = {}, selected_list = null, more_btn;

    /**
     * first get the lists in order: sort them from parent to child and remember if any is pre-checked by php
     */
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            list = lists[i];
            list.addEventListener('change', function () {
                ruigehond010_filter(this);
            });
            lists_by_parent[list.getAttribute('data-ruigehond010_parent')] = list;
            if ((option = list.querySelector('[selected]'))) selected_list = option.parentElement;
        }
    }
    if ((options = ruigehond_cloneShallow(document.querySelectorAll('[data-ruigehond010_term_id]')))) {
        // sort the lists
        while (true) {
            maybe_done = true; // until proven otherwise
            for (i in options) {
                if ((list = lists_by_parent[(parent_id = options[i].getAttribute('data-ruigehond010_term_id'))])) {
                    // put the list after the list this option is in, only if it's not already later in the DOM, in which case all is ok
                    if ((option = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"] ~ select > [data-ruigehond010_term_id="' + parent_id + '"]'))) {
                        option.parentElement.insertAdjacentElement('afterend', list);
                        maybe_done = false;
                    }
                }
            }
            if (maybe_done) break; // yeah, we’re definitely done
        }
    }
    /**
     * setup the search field
     */
    if ((search_input = document.getElementById('ruigehond010_search'))) {
        search_input.addEventListener('keyup', function () {
            var post, posts, search_string = this.value.toLowerCase(), i, len, count = 0;
            if ((posts = document.getElementById('ruigehond010_faq'))) {
                posts = posts.getElementsByClassName('ruigehond010_post');
                for (i = 0, len = posts.length; i < len; ++i) {
                    if ((post = posts[i]).innerText.toLowerCase().indexOf(search_string) !== -1) {
                        // duplicate code / same as in filter
                        if (false === ruigehond010_m && count > max) {
                            ruigehond010_hideDomElement(post);
                        } else {
                            ruigehond010_showDomElement(post);
                        }
                        ++count;
                    } else {
                        ruigehond010_hideDomElement(post);
                    }
                    // duplicate code / same as in filter
                    if (false === ruigehond010_m && count > max + 1) {
                        ruigehond010_showDomElement(document.getElementById('ruigehond010_more'));
                    } else {
                        ruigehond010_hideDomElement(document.getElementById('ruigehond010_more'));
                    }
                }
                // open the first faq item
                setTimeout(function () {
                    if (ruigehond010_i) clearTimeout(ruigehond010_i);
                    ruigehond010_i = setTimeout(ruigehond010_toggleFirst, 500);
                }, 500); // wait for the showDomElement and hideDomElement to finish
            }
        });
        search_input.addEventListener('focus', function () {
            ruigehond010_resetLists();
        });
    }
    /**
     * setup the accordion
     */
    if ((list = document.getElementById('ruigehond010_faq'))) {
        if ((lists = list.querySelectorAll('.ruigehond010_post'))) {
            for (i = 0, len = lists.length; i < len; ++i) {
                if ((h4 = lists[i].querySelector('h4'))) {
                    h4.addEventListener('click', function () {
                        ruigehond010_toggle(this.parentElement);
                    });
                }
            }
            // and the show more button
            more_btn = document.createElement('button');
            more_btn.id = 'ruigehond010_more';
            more_btn.addEventListener('click', function() {
                ruigehond010_m = true;
                // refilter / search immediately to take advantage of the new situation
                if ((src = document.getElementById('ruigehond010_search')).value !== '') {
                    src.dispatchEvent(new KeyboardEvent('keyup', {'key': 'Shift'}))
                } else { // just filter the lowest / latest select list
                    src = document.querySelectorAll('select.ruigehond010.choose-category');
                    for (i = src.length - 1; i>0;--i) {
                        if ((list = lists[i]).style.display !== 'none' && list.selectedIndex > 0){
                            ruigehond010_filter(list);
                            break;
                        }
                    }
                }
                ruigehond010_hideDomElement(this);
            });
            list.insertAdjacentElement('beforeend', more_btn);
            //list.insertAdjacentHTML('beforeend', '<button id="ruigehond010_more" class="button"></button>');
            // when a post_id is in the querystring, open that one only
            if ((pos = (src = document.location.search).indexOf('post_id=')) > -1) {
                post_id = parseInt(src.substr(pos + 8));
                if ((post = list.querySelector('[data-post_id="' + post_id.toString() + '"]'))) {
                    ruigehond010_toggle(post);
                    for (i = 0, len = lists.length; i < len; ++i) {
                        if ((list = lists[i]) !== post) {
                            ruigehond010_hideDomElement(list);
                        }
                    }
                } else {
                    console.warn('faq-with-categories: requested post_id not found in faqs list.');
                    pos = -1;
                }
            }
            // show the first entry (only if not a single entry is shown yet)
            if (-1 === pos) ruigehond010_toggleFirst();
        }
        // run the filter for the first time
        ruigehond010_filter(selected_list);
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