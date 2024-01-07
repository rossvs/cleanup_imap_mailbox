Utility to cleanup huge mailbox for chosen date period and folder. You can delete millions of e-mails with it.

### How to use

PHP 5.6+ and imap extendsion required

Run in CLI (Linux, Mac or Windows)

First you need to define your IMAP server, mailbox address and password in the scipt first lines.

**List folders** — if you want to clean folder other than INBOX. Note that non-ASCII names will be in UTF-7 encoding

(If using Windows cmd.exe, type: `chcp 65001`)

`php imap_delete.php -l`

**List letters** without deleting

`php imap_delete.php -v -f 2023-01-01 -t 2023-12-31`

**Delete letters**

`php imap_delete.php -d -v -f 2023-01-01 -t 2023-12-31`

### Options

`-l` Just list folders

`-d` Do delete letters

`-f` Filter from (newer than) YYYY-MM-DD date; timezone is UTC

`-t` Filter to (older than) YYYY-MM-DD date; timezone is UTC

`-v` Verbose - show "Date", "From", "Subject" headers

### Feedback

vladislav.ross@gmail.com
