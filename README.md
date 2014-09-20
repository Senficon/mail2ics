Script that checks an email account and generates an iCalendar feed (ICS file) from all iCalendar data it finds in mails.

Intended to work with events that the mail account was either invited to or forwarded from Microsoft Outlook. Also includes past events.

If you have a different usecase, your mileage may vary.

Setup
-----
1. Make sure calendar.ics is writable
2. Rename config.dist.php to config.php
2. Enter your mail server data there

License
-------
lib/ImapMailbox.php: [LGPLv3](https://www.gnu.org/licenses/lgpl.html)
Everything else: [Creative Commons Zero](http://creativecommons.org/publicdomain/zero/1.0/) - do what you want.

