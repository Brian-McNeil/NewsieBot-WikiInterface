This is a starting README

TO-DO:

Complete WikiBot.class.php with basic page functions as-follows:
A   Page write functions
B   Additional page fetch/read functions

A.1     Write complete new page. {{done}}
A.2     Append new section to a page. {{done}}
A.3     Update a section in a page. {{done}}
- Must handle edit conflict in A.1 and A.3 {{done}}
- Must have option to have A.2 fail if trying to add to a nonexistent page {{done}}, untested

B.1 Fetch a section from a page.

--- Extra classes ---

Extend the base WikiBot.class.php with:
* Category handling
* Image handling
* Template handling
* Links/Where used
* Non-editing functions (eg, emailing users).
* Administrative functions
* 'Crat functions

--- Workarounds ---
* Links in DPL and some ParserFunction conditional code don't end up in the link table
  Should be able to check what extensions in the wiki, and extend any link/W.U. functions
  to pull said data by rendering page and crawling for links (expensive, ugly, might nudge MW
  devs to fix it).
