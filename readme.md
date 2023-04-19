# FAQ with categories
Easy to manage FAQ with categories, including accordion, filter, search and show more functionality.

Get it on WordPress: <https://wordpress.org/plugins/faq-with-categories/>

## Description

FAQs are great for your visitors and when implemented correctly also for SEO.

This simple FAQ plugin creates a new post-type. This is straightforward and flexible: you can now create and manage FAQs like any other post type in Wordpress.

The FAQs can be summoned using shortcodes, the default for the central FAQ page is [faq-with-categories]. More options are explained below and on the settings page.

FAQs are always sorted by published-date descending, so newest entries are first (you can manipulate the published date of each post).

You can choose a taxonomy, the default is ‘category’, to attach your FAQ posts to. You can now summon FAQs for a specific category (including sub categories) on a page. The plugin also uses it to filter the FAQs if you place the filter on your central FAQ page.

If you want some FAQs in other locations that do not appear on the central page you can use an ‘exclusive’ tag.

When there are many entries, a ‘Show more’ button appears automatically (configurable in the settings)

## Pros

- Easy to manage the FAQs

- Tidy accordion display and smooth filtering / searching

- The ‘exclusive’ short_code and the central FAQ page output FAQ snippet schema data as ld+json in the head

- Supports direct linking to pre-select the filters (faq-page?category=name%20of%20category)

- Automatically follows (chosen) taxonomy hierarchy (infinite depth) with added option to order the categories

## Cons

- Only 1 central FAQ list is supported (though you can display subsets of the FAQs anywhere you want)

- Currently only with shortcodes, no widgets yet

- Filtering and searching the FAQs only work with javascript enabled (but then again, so does most of Wordpress)

- The filter displays all the categories currently, also the categories without faqs, this will be fixed in a future release

## Short codes

You may use the following shortcodes, of course certain combinations do not make sense and may produce erratic behaviour.

**[faq-with-categories]** (use only ONCE) produces the default list with all the faqs and outputs FAQ snippets schema in the head.

**[faq-with-categories-filter]** produces a filter menu according to the chosen taxonomy using the specified order (only works when default shortcode is also on that page).

**[faq-with-categories-search]** produces a search box that will perform client-side lookup through the faqs (only works when default shortcode is also on that page).

[faq-with-categories **quantity="5"**] *(1)* limits the quantity of the faqs to 5, or use another number. Can be combined with the other settings.

[faq-with-categories **category="category name"**] *(1)* display only faqs for the specified category (case insensitive). This will NOT output FAQ snippets schema in the head.

[faq-with-categories **exclusive="your tag"**] *(1)* (use only ONCE for every tag) any tag you specified under a faq entry in the box, will gather all faqs with that tag for display.

[faq-with-categories **title-only="any value"**] outputs the list as links rather than as an accordion.

*(1)* NOTE: only a limited number of faqs will be present on the page so search and filter will not work.

## Template

The post-type is called ruigehond010_faq, so you can create a single-ruigehond010_faq.php and archive-ruigehond010_faq.php template should you want to format the display in more detail.

Have fun. Let me know if you have a question!
