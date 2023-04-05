=== FAQ with categories ===
Contributors: ruigehond
Tags: faq, categories, frequently, asked, questions, answers
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=hallo@ruigehond.nl&lc=US&item_name=FAQ+with+categories+plugin&no_note=0&cn=&currency_code=EUR&bn=PP-DonationsBF:btn_donateCC_LG.gif:NonHosted
Requires at least: 4.5
Tested up to: 6.2
Requires PHP: 5.4
Stable tag: 1.1.6
License: GPLv3

Easy to manage FAQ with categories, including accordion, filter, search and show more functionality.

== Description ==

FAQs are great for your visitors and when implemented correctly also for SEO.

This simple FAQ plugin creates a new post-type. This is straightforward and flexible: you can now create and manage FAQs like any other post type in Wordpress.

The FAQs can be summoned using shortcodes, the default for the central FAQ page is [faq-with-categories]. More options are explained below and on the settings page.

FAQs are always sorted by published-date descending, so newest entries are first (you can manipulate the published date of each post).

You can choose a taxonomy, the default is ‘category’, to attach your FAQ posts to. You can now summon FAQs for a specific category (including sub categories) on a page. The plugin also uses it to filter the FAQs if you place the filter on your central FAQ page.

If you want some FAQs in other locations that do not appear on the central page you can use an ‘exclusive’ tag.

When there are many entries, a ‘Show more’ button appears automatically (configurable in the settings)

= Pros =

- Easy to manage the FAQs

- Tidy accordion display and smooth filtering / searching

- The ‘exclusive’ short_code and the central FAQ page output FAQ snippet schema data as ld+json in the head

- Supports direct linking to pre-select the filters (faq-page?category=name%20of%20category)

- Automatically follows (chosen) taxonomy hierarchy (infinite depth) with added option to order the categories

= Cons =

- Only 1 central FAQ list is supported (though you can display subsets of the FAQs anywhere you want)

- Currently only with shortcodes, no widgets yet

- Filtering and searching the FAQs only work with javascript enabled (but then again, so does most of Wordpress)

- The filter displays all the categories currently, also the categories without faqs, this will be fixed in a future release

= Short codes =

You may use the following shortcodes, of course certain combinations do not make sense and may produce erratic behaviour.

**[faq-with-categories]** (use only ONCE) produces the default list with all the faqs and outputs FAQ snippets schema in the head.

**[faq-with-categories-filter]** produces a filter menu according to the chosen taxonomy using the specified order (only works when default shortcode is also on that page).

**[faq-with-categories-search]** produces a search box that will perform client-side lookup through the faqs (only works when default shortcode is also on that page).

[faq-with-categories **quantity="5"**] *(1)* limits the quantity of the faqs to 5, or use another number. Can be combined with the other settings.

[faq-with-categories **category="category name"**] *(1)* display only faqs for the specified category (case insensitive). This will NOT output FAQ snippets schema in the head.

[faq-with-categories **exclusive="your tag"**] *(1)* (use only ONCE for every tag) any tag you specified under a faq entry in the box, will gather all faqs with that tag for display.

[faq-with-categories **title-only="any value"**] outputs the list as links rather than as an accordion.

*(1)* NOTE: only a limited number of faqs will be present on the page so search and filter will not work.

= Template =

The post-type is called ruigehond010_faq, so you can create a single-ruigehond010_faq.php and archive-ruigehond010_faq.php template should you want to format the display in more detail.

Have fun. Let me know if you have a question!

Regards,
Joeri (ruige hond)

== Installation ==

1. Install the plugin by clicking ‘Install now’ below, or the ‘Download’ button, and put the faq-with-categories folder in your plugins folder.

2. Activate it on the plugins page

3. Click on FAQ in your admin menu to create your first FAQ.

Upon uninstall FAQ with categories removes its own options and taxonomy sorting table. However, it leaves the FAQ posts in the database currently. If you are positive you don’t need the FAQ posts anymore, bulk-delete them before uninstalling the plugin.

== Screenshots ==
1. Default view on the frontend with a search bar

2. ruigehond010_faq post-type lets you easily manage the faqs

3. Customize the plugin using the options

4. The categories can be coupled to a page, and you can order them as well

== Changelog ==

1.1.6: automatic deploy, no functional changes

1.1.5: improved accordion handling, removed animation

1.1.4: added screenshots, released on Wordpress.org

1.1.3: plugin determines max-height of faq posts on client itself, fix apply_filters would sometimes not work the first time

1.1.2: you can now decide which html tag is used for the header of each faq on the faq page, fix ordering taxonomy would delete coupled page

1.1.1: small fixes and updated translation, added admin warning message for duplicate short codes

1.1.0: added page linking to taxonomy for automatic selection of the corresponding faqs on them, option to output faq snippet on single pages

1.0.7: added no results warning message

1.0.6: fixed faq display with limited quantity and / or title-only could register as the main faq page

1.0.5: improved accordion, added option to only use the ‘more’ button on the central faq page, improved explanations + translations

1.0.4: updated translations

1.0.3: refactored javascript to OOP so it works more reliably with less code, you can specify button text + max in settings

1.0.2: updated link on plugins page, updated readme

1.0.1: short_code ‘category’ now selects posts the same way as querystring (specifically also all the posts belonging to children)

1.0.0: Release

