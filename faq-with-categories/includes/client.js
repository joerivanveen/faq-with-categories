function Ruigehond010(list) {
    const max_for_more = list.getAttribute('data-max'),
        max_ignore = list.hasAttribute('data-max_ignore'), // the server sends this when relevant
        more_button_text = list.getAttribute('data-more_button_text');
    this.max = (this.isInt(max_for_more)) ? parseInt(max_for_more) : 5;
    this.max_ignore = max_ignore; // when true ignore the maximum amount, never display the more button
    this.more_button_text = more_button_text || 'Show more';
    this.open_first_faq_on_page = '1' === list.getAttribute('data-open_first_faq_on_page')
    this.timeout = null;
    this.showing_more = false;
    this.post_ids = []; // caches the post_ids currently selected for display (used by method showMore());
    this.list = list;
    this.start();
}

Ruigehond010.prototype.start = function () {
    const post_ids = [], term_items = [], lists_by_parent = {}, self = this;
    let options, option, i, len, parent_id, list, lists, maybe_done, search_input, header_tag, pos, post, post_id,
        term_id, src, more_btn, selected_list = null, list_item, max_height = 300, test_height, test_element;
    /**
     * first get the lists in order: sort them from parent to child and remember if any is pre-checked by php
     */
    if ((lists = document.getElementsByClassName('ruigehond010 choose-category'))) {
        for (i = 0, len = lists.length; i < len; ++i) {
            list = lists[i];
            list.addEventListener('change', function () {
                self.filter(this);
            });
            lists_by_parent[list.getAttribute('data-ruigehond010_parent')] = list;
            if ((option = list.querySelector('[selected]'))) selected_list = option.parentElement;
        }
    }
    // TODO use data-ruigehond010_count to only show options with items, and whose descendants have items
    if ((options = document.querySelectorAll('[data-ruigehond010_term_id]'))) {
        // add category with faq items
        function add(key) {
            if (!term_items.hasOwnProperty(key)) {
                term_items[key] = true;
            }
        }

        for (i = 0, len = options.length; i < len; ++i) {
            option = options[i];
            if ((term_id = option.getAttribute('data-ruigehond010_term_id'))
                && (option.hasAttribute('data-ruigehond010_has_items'))) {
                add(term_id);
                if ((parent_id = option.parentElement.getAttribute('data-ruigehond010_parent'))) {
                    add(parent_id);
                }
            }
        }
        // remove options without items
        for (i = 0, len = options.length; i < len; ++i) {
            option = options[i];
            if (!term_items[option.getAttribute('data-ruigehond010_term_id')]) {
                option.parentElement.removeChild(option);
            }
        }
        // sort the lists
        while (true) {
            maybe_done = true; // until proven otherwise
            for (i = 0, len = options.length; i < len; ++i) {
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
        // remove lists without options, by looping in reverse!
        for (i = lists.length - 1; i >= 0; --i) {
            list = lists[i];
            if (list.options.length < 2) list.parentElement.removeChild(list);
        }
    }
    /**
     * set up the search field
     */
    if ((search_input = document.getElementById('ruigehond010_search'))) {
        search_input.addEventListener('keyup', function () {
            self.search(this.value);
        });
        search_input.addEventListener('change', function () {
            self.search(this.value);
        });
        search_input.addEventListener('focus', function () {
            self.resetLists();
        });
    }
    /**
     * set up the accordion, this includes the show_more button and the showing of a single post when requested
     */
    list = this.list;
    // @since 1.1.3 use test-element to record the highest faq answer and use that for height for the accordion
    if (!(test_element = this.list.querySelector('.ruigehond010.test'))) {
        test_element = document.createElement('li');
        test_element.classList.add('ruigehond010', 'test');
        list.appendChild(test_element);
    }
    if ((lists = list.querySelectorAll('.ruigehond010_post'))) {
        // this activates the open / close links and collects the post_ids for displaying
        for (i = 0, len = lists.length; i < len; ++i) {
            list_item = lists[i];
            test_element.innerHTML = list_item.innerHTML;
            if ((test_height = parseInt(window.getComputedStyle(test_element).getPropertyValue('height'))) > max_height)
                max_height = test_height;
            post_ids.push(list_item.getAttribute('data-post_id'));
            if ((header_tag = list_item.querySelector('.faq-header'))) {
                header_tag.addEventListener('click', function () {
                    if (self.timeout) clearTimeout(self.timeout);
                    self.toggle(this.parentElement);
                });
            }
        }
        // @since 1.1.3 set the max height style accordingly TODO check again after resize of the window
        test_height = '.ruigehond010.faq.posts .ruigehond010_post.open .faq-header+div { max-height: ' + max_height + 'px; }';
        list.removeChild(test_element);
        const head = document.head,
            style = document.createElement('style');
        head.appendChild(style);
        style.setAttribute('type', 'text/css');
        style.appendChild(document.createTextNode(test_height));
        // done adding style to head
        if (this.max_ignore) {
            this.showing_more = true;
        } else {
            // and the show more button
            more_btn = document.createElement('button');
            more_btn.classList.add('ruigehond010', 'more');
            more_btn.innerText = this.more_button_text;
            more_btn.addEventListener('click', function () {
                self.showMore(this);
            });
            list.insertAdjacentElement('beforeend', more_btn);
        }
        // when a post_id is in the querystring, open that one only
        if ((pos = (src = document.location.search).indexOf('post_id=')) > -1) {
            post_id = parseInt(src.slice(pos + 8));
            if ((post = list.querySelector('[data-post_id="' + post_id.toString() + '"]'))) {
                self.showDomElement(post);
                self.toggle(post);
                for (i = 0, len = lists.length; i < len; ++i) {
                    if ((list = lists[i]) !== post) {
                        self.hideDomElement(list);
                    }
                }
            } else {
                console.warn('faq-with-categories: requested post_id not found in faqs list.');
                pos = -1;
            }
        }
        // show them (only if not a single entry is shown yet)
        if (-1 === pos) {
            self.showPostsById(post_ids);
        }
    }
    // run the filter for the first time
    self.filter(selected_list);
}
Ruigehond010.prototype.search = function (search_string) {
    let post, posts, i, len;
    const post_ids = [];
    search_string = search_string.toLowerCase();
    posts = this.list.getElementsByClassName('ruigehond010_post');
    // collect the post_ids for displaying
    for (i = 0, len = posts.length; i < len; ++i) {
        if ((post = posts[i]).innerText.toLowerCase().indexOf(search_string) !== -1) {
            post_ids.push(post.getAttribute('data-post_id'))
        }
    }
    this.showPostsById(post_ids);
}
Ruigehond010.prototype.showPostsById = function (post_ids, leave_toggle_state_alone) {
    const self = this;
    let post, posts, i, len, count = 0;
    this.post_ids = post_ids; // cache them
    posts = this.list.getElementsByClassName('ruigehond010_post');
    for (i = 0, len = posts.length; i < len; ++i) {
        if (post_ids.indexOf((post = posts[i]).getAttribute('data-post_id')) === -1) {
            this.hideDomElement(post);
        } else {
            if (this.showing_more || count < this.max) {
                this.showDomElement(post);
            } else {
                this.hideDomElement(post);
            }
            count++;
        }
    }
    if ((len = this.list.querySelector('.ruigehond010.more'))) {
        if (count <= this.max) {
            len.style.display = 'none';
        } else {
            this.showing_more = false;
            len.style.display = 'block';
        }
    }
    if (post_ids.length === 0) {
        this.toggleNoResultsWarning(true);
    } else {
        this.toggleNoResultsWarning(false);
        if (!leave_toggle_state_alone && this.open_first_faq_on_page) {
            // open the first faq item
            self.timeout = setTimeout(function () {
                if (self.timeout) clearTimeout(self.timeout);
                self.timeout = setTimeout(function () {
                    self.toggleFirst();
                }, 500);
            }, 500); // wait for the showDomElement and hideDomElement to finish
        }
    }
}
Ruigehond010.prototype.toggleNoResultsWarning = function (show) {
    const el = this.list.querySelector('.no-results-warning');
    if (el && (el.style.display === 'none') === show) {
        show ? this.showDomElement(el) : this.hideDomElement(el);
    }
}
Ruigehond010.prototype.showMore = function (button) {
    this.showing_more = true;
    this.showPostsById(this.post_ids, true); // true means don’t toggle the first one perse
    button.style.display = 'none';
}

Ruigehond010.prototype.showDomElement = function (element) {
    //element.style.display = 'block';
    element.style.position = 'inherit';
    (function ($) {
        $(element).fadeIn();
    })(jQuery);
}

Ruigehond010.prototype.hideDomElement = function (element) {
    //element.style.display = 'none';
    element.style.top = element.getBoundingClientRect().top.toString() + 'px';
    //element.style.position = 'fixed';
    (function ($) {
        $(element).fadeOut();
    })(jQuery);
}

Ruigehond010.prototype.toggleFirst = function () {
    const posts = this.list.querySelectorAll('.ruigehond010_post'), len = posts.length;
    let i, post, rect;
    for (i = 0; i < len; ++i) {
        if ((rect = (post = posts[i]).getBoundingClientRect()).top > 0 && rect.left > 0) {
            this.toggle(post);
            return;
        }
    }
}

Ruigehond010.prototype.toggle = function (li) {
    // walk through all the elements to close them, only open the chosen one (li)
    const posts = this.list.querySelectorAll('.ruigehond010_post'), len = posts.length;
    let i, post, post_contents, already_opened = false;
    for (i = 0; i < len; ++i) {
        if ((post = posts[i]) === li) {
            post.classList.add('open');
            already_opened = true;
        } else {
            // please scroll the page so we can see...
            if (false === already_opened && post.classList.contains('open')) {
                if ((post_contents = post.querySelector('div'))) {
                    window.scrollBy(0, -1 * post_contents.offsetHeight);
                }
            }
            post.classList.remove('open');
        }
    }
}

Ruigehond010.prototype.resetLists = function () {
    const list = document.querySelector('[data-ruigehond010_parent="0"]');
    this.hideSubLists();
    // set the first list to 'choose'
    if (list) {
        list.selectedIndex = 0;
    }
}

Ruigehond010.prototype.resetSearch = function () {
    const search_input = document.getElementById('ruigehond010_search');
    if (search_input) search_input.value = '';
}

Ruigehond010.prototype.hideSubLists = function () {
    const lists = document.getElementsByClassName('ruigehond010 choose-category'),
        len = lists.length;
    let list, i;
    if (lists) {
        for (i = 0; i < len; ++i) {
            (list = lists[i]).style.display =
                (list.hasAttribute('data-ruigehond010_parent') &&
                    list.getAttribute('data-ruigehond010_parent') === '0') ? 'block' : 'none';
        }
    }
}

Ruigehond010.prototype.getAllOptionValues = function (list) {
    const options = list.options, len = options.length;
    let arr = [], i, option, parent_id, sub_list;
    // start at i = 1 because you can skip the hidden 'choose' entry
    for (i = 1; i < len; ++i) {
        arr.push((option = options[i]).value.toLowerCase());
        // if this option has a sublist, get all those options as well
        if (option.hasAttribute('data-ruigehond010_term_id')) {
            parent_id = option.getAttribute('data-ruigehond010_term_id')
            if ((sub_list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                arr = arr.concat(this.getAllOptionValues(sub_list));
            }
        }
    }
    return arr;
}

Ruigehond010.prototype.filter = function (select) {
    const self = this, post_ids = [];
    let list, options, option, parent_id, i, len, terms, posts, post, class_names, count = 0;
    this.resetSearch();
    if (null === select) { // only display the parent and set it to first option
        this.resetLists();
    } else {
        this.hideSubLists();
        // display a child list of the selected option if it exists
        if ((option = select.options[select.selectedIndex])) {
            if (option.hasAttribute('data-ruigehond010_term_id')) {
                terms = [option['value'].toLowerCase()];
                parent_id = option.getAttribute('data-ruigehond010_term_id');
                if ((list = document.querySelector('[data-ruigehond010_parent="' + parent_id + '"]'))) {
                    list.selectedIndex = 0;
                    this.showDomElement(list);
                    terms = terms.concat(this.getAllOptionValues(list));
                }
            } else {
                terms = this.getAllOptionValues(select)
                // add the parent term id as well
                if ((parent_id = select.getAttribute('data-ruigehond010_parent')) > 0) {
                    terms.push('term-' + parent_id.toString());
                }
            }
        }
        // travel up the chain making the lists visible until you reach data-ruigehond010_parent="0"
        while (select.hasAttribute('data-ruigehond010_parent') &&
        (parent_id = select.getAttribute('data-ruigehond010_parent')) !== '0') {
            this.showDomElement(select);
            if ((option = document.querySelector('[data-ruigehond010_term_id="' + parent_id + '"]'))) {
                select = option.parentElement;
                for (i = 0, len = (options = select.options).length; i < len; ++i) {
                    if (options[i] === option) {
                        select.selectedIndex = i;
                        this.showDomElement(select);
                        break;
                    }
                }
            } else {
                console.error('faq-with-categories: something is wrong with the select lists');
                break;
            }
        }
        // filter the faqs
        posts = this.list.getElementsByClassName('ruigehond010_post');
        for (i = 0, len = posts.length; i < len; ++i) {
            post = posts[i];
            class_names = post.className;
            // check if there are overlapping classes
            if (terms.filter(function (n) {
                return class_names.indexOf(n) !== -1;
            }).length > 0) {
                post_ids.push(post.getAttribute('data-post_id'));
            }
        }
        this.showPostsById(post_ids);
    }
}
/* ponyfills */
Ruigehond010.prototype.isInt = function (value) {
    if (isNaN(value)) {
        return false;
    }
    const x = parseFloat(value);
    return (x | 0) === x;
}

Ruigehond010.prototype.cloneShallow = function (obj) {
    try {
        return Object.assign({}, obj); // <-- way faster if it's available, even including the try / catch
    } catch (e) {
        return JSON.parse(JSON.stringify(obj));
    }
}

function ruigehond010_start() {
    const lists = document.querySelectorAll('.ruigehond010.faq.posts');
    lists.forEach(function (list) {
        new Ruigehond010(list);
    })
}

/* only after everything is locked and loaded we’re initialising */
if (document.readyState === 'complete') {
    ruigehond010_start();
} else {
    window.addEventListener('load', function () {
        ruigehond010_start();
    });
}