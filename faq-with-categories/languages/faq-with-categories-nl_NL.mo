��    5      �  G   l      �  m   �  J   �  F   B  9   �  U   �  O     b   i     �  p   �     A  )   O     y     �  ;   �  �   �  m   s  d   �  c   F	     �	     �	  ]   �	  3   %
  ?   Y
  +   �
     �
     �
     �
  >   �
     )  3   7     k     y     �  ,   �  	   �  /   �  u   �  [   k  9   �  /     �   1  '   �  >   �  ;   5  b   q  5   �  5   
  Z   @  4   �  j   �  y   ;     �  �  �  z   n  c   �  S   M  9   �  [   �  M   7  m   �     �  u   �  
   n  .   y     �     �  T   �  �   (  ~   �  {   S  \   �     ,     <  n   L  >   �  V   �  (   Q     z     �     �  B   �     �  ;   �     8     M     T  .   a  	   �  :   �  �   �  l   m  F   �  2   !  �   T  $     <   ;  9   x  [   �  5     @   D  c   �  H   �  �   2  t   �     0      ,         .      3         /                   "   	             2              (      !       +   '              *             &      -   5           %   0   4               
   1          #          )          $                                        %1$s %2$s any tag you specified under a faq entry in the box, will gather all faqs with that tag for display. %1$s %2$s display only faqs for the specified category (case insensitive). %1$s %2$s limits the quantity of the faqs to 5, or use another number. %s outputs the list as links rather than as an accordion. %s produces a filter menu according to the chosen taxonomy using the specified order. %s produces a search box that will perform client-side lookup through the faqs. %s produces the default list for the central FAQ page and outputs FAQ snippets schema in the head. All By default a small css-file is output to the frontend to format the entries. Uncheck to handle the css yourself. Choose option Could not find post_id based on title: %s Did not understand handle %s FAQ FAQ posts will not count towards total posts in taxonomies. FAQS are always sorted by published date descending, so newest entries are first. By default they are output as an accordion list with the first one opened. If you assign a page to a taxonomy, the FAQ shortcode on that page will display FAQ-posts from that taxonomy. If you do not output schema on single FAQ pages you can check here which pages should output schema. NOTE: only a limited number of faqs will be present on the page so search and filter will not work. No results found Not updated Note that outputting duplicate FAQs in schema may result in them not being considered at all. Number of faqs shown before ‘Show more’ button. Only use the more button on the central FAQ page, nowhere else. Open the first FAQ in a list automatically. Options Order taxonomy Output schema Output the faq schema on individual page rather than overview. Page settings Please indicate the main FAQ page in page settings. Save Settings Search faqs Settings Settings for pages the shortcode is used on. Show more Slug for the individual faq entries (optional). Some pages may not be able to output schema, check if you use a specific category or limited quantity on those pages. Tag used for the header on faq page (e.g. h4), invalid input may cause errors on your page. Text shown when search or filter results in 0 faqs found. The placeholder in the search bar for the faqs. The tag this FAQ entry is exclusive to, use it in a shortcode to summon the entry. Note that it will still be displayed for the taxonomies that are checked. The text on the ‘Show more’ button. The ‘choose / show all’ option in subsequent select lists. The ‘choose / show all’ option in top most select list. This page only concerns itself with the order. The hierarchy is determined by the taxonomy itself. This will NOT output FAQ snippets schema in the head. Type the taxonomy you want to use for the categories. When using title-only in shortcodes, link to the overview rather than individual FAQ page. Will exclude the FAQ posts from site search queries. You can link to your general FAQ page with a category in the querystring (e.g. %s) to pre-filter the faqs. You may use the following shortcodes, of course certain combinations do not make sense and may produce erratic behaviour. post_id %d not found Project-Id-Version: faq-with-categories
PO-Revision-Date: 2025-02-19 17:32+0100
Last-Translator: 
Language-Team: ruige hond
Language: nl
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
Content-Transfer-Encoding: 8bit
Plural-Forms: nplurals=2; plural=(n != 1);
X-Generator: Poedit 3.5
X-Poedit-Basepath: ..
X-Poedit-KeywordsList: __;esc_html__
X-Poedit-SearchPath-0: .
X-Poedit-SearchPathExcluded-0: languages
 %1$s %2$s een tag die je onder een veelgestelde vragen bericht hebt gespecificeerd, zal alle vragen met die tag weergeven. %1$s %2$s toont alleen veelgestelde vragen voor de specifieke categorie (niet hoofdlettergevoelig). %1$s %2$s beperkt het aantal veelgestelde vragen tot 5, of gebruik een ander getal. %s geeft de lijst weer als links in plaats van accordion. %s produceert een filter menu voor de gekozen taxonomy in de gespecificeerde rangschikking. %s produceert een zoekvak dat client-side door de veelgestelden vragen zoekt. %s toont te hele lijst voor de centrale veelgestelde vragen pagina en plaatst FAQ snippets schema in de head. Alle Standaard wordt er een klein css bestand meegestuurd om de vragen vorm te geven. Vink uit als je de css zelf beheert. Kies optie Kon geen post_id vinden op basis van titel: %s Begreep handle %s niet Veelgestelde vragen Veelgestelde Vragen tellen niet mee in het totaal van de berichten bij een taxonomy. Veelgestelde Vragen worden altijd gesorteerd per publicatie datum, nieuwste berichten eerst. Standaard worden zij in een accordion lijst weergegeven met de eerste geopend. Wanneer je een pagina aan een taxonomy toewijst toont de FAQ shortcode op die pagina standaard FAQ-berichten van die taxonomy. Als je de schema niet op enkele FAQ pagina’s uitvoert kan je hieronder aangeven welke pagina’s schema moeten uitvoeren. NB: een beperkt aantal vragen is op de pagina aanwezig zodat zoeken en filteren niet werken. Geen resultaten Niet bijgewerkt Let op dat FAQs meerdere keren genereren in de schema kan resulteren in dat ze helemaal niet opgenomen worden. Aantal vragen die worden getoond voor de ‘Toon meer’ knop. Gebruik de meer-knop alleen op de centrale veelgestelde vragen pagina, verder nergens. Open de eerste FAQ in een lijst vanzelf. Opties Rangschik taxonomy Schema genereren Genereer faq schema op individuele pagina in plaats van overzicht. Pagina instellingen Geef de hoofdpagina voor FAQ aan in de pagina instellingen. Instellingen opslaan Zoeken Instellingen Instellingen voor pagina’s met de shortcode. Toon meer Slug voor de losse veelgestelde vragen pagina (optioneel). Sommige pagina’s kunnen mogelijk geen schema genereren, controleer of je een specifieke categorie of beperkte hoeveelheid gebruikt op die pagina’s. Html tag gebruikt voor de header op de fax pagina (bv h4), ongeldige waarde kan een fout op de pagina geven. Tekst wordt getoond wanneer zoek of filter resulteert in 0 resultaten. De placeholder in de veelgestelde vragen zoekbalk. De tag waar deze Veelgestelde Vragen vermelding exclusief voor is, gebruik in een shortcode om de vermelding op te roepen. Let op dat hij ook nog wordt weergegeven bij aangevinkte taxonomieën. De tekst op de ‘Toon meer’ knop. De ‘kies / toon alles’ optie in opvolgende keuzelijsten. De ‘kies / toon alles’ optie in de eerste keuzelijst. Deze pagina bepaalt alleen de volgorde. De hiërarchie wordt bepaald door de taxonomy zelf. Dit zal GEEN FAQ snippets schema in de head plaatsen. Typ de taxonomy-naam die je wilt gebruiken voor de categorieën. Bij gebruik van title-only in shortcodes, link naar de Veelgestelde Vragen pagina ipv de post zelf. Sluit de Veelgestelde Vragen berichten uit van de standaard zoekfunctie. Je kunt naar je algemene veelgestelde vragen pagina linken met een categorie in de querystring (bv. %s) om de vragen alvast te filteren. Je kunt de volgende shortcodes gebruiken, sommige combinaties slaan nergens op en zullen grillig gedrag veroorzaken. post_id %d niet gevonden 