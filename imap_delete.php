<?php

//your server and folder (default is INBOX)
//example, without SSL {imap.example.com:143}INBOX
define('IMAP_HOST', '{imap.example.com:993/imap/ssl}INBOX');
//user
define('USERNAME', 'john@example.com');
//password (if you use two-factor authentication, generate key in your mail interface)
define('PASSWORD', 'qwerty123');

if (!function_exists('imap_open')) {
    die('imap extension is not loaded?');
}

if (php_sapi_name() !== 'cli') {
    die('run in console');
}

ini_set('display_errors', 'On');
error_reporting(E_ALL); //notices from imap_* functions could be important

//parse options
$cliOptions = getopt('f:t:ldv');
if (empty($cliOptions)) {
    $script = __FILE__;
    echo <<<EOL
-l Just list folders
-d Do delete letters
-f Filter from (newer than) YYYY-MM-DD date
-t Filter to (older than) YYYY-MM-DD date
-v Verbose - show letters

Examples:
php {$script} -l    List folders
php {$script} -v -f 2019-01-01 -t 2019-12-31    List letters
php {$script} -d -f 2019-01-01 -t 2019-12-31    Delete letters
EOL;

    exit(1);
}

/**
 * @return resource
 */
function connect()
{
    $inbox = imap_open(IMAP_HOST, USERNAME, PASSWORD);
    if (empty($inbox)) {
        die('Cannot connect to IMAP: ' . imap_last_error());
    }

    return $inbox;
}

//try to connect
$inbox = connect();

//list folders
if (isset($cliOptions['l'])) {
    $mailboxes = imap_list($inbox, IMAP_HOST, '*');
    foreach ($mailboxes as $mailbox) {
        $mailboxHuman = mb_convert_encoding($mailbox, "utf-8", "UTF7-IMAP");
        echo $mailbox . ($mailbox !== $mailboxHuman ? "\t (" . strrchr($mailboxHuman, '/') . ')' : '') . PHP_EOL;
    }

    exit;
}

if (!isset($cliOptions['f']) || !isset($cliOptions['t'])) {
    die('Provide either -l or -f and -t options');
}

if (!isset($cliOptions['d']) && !isset($cliOptions['v'])) {
    die('Provide -d and/or -v options');
}

$dtFrom = new DateTime($cliOptions['f']);
$dtTo = new DateTime($cliOptions['t']);

if (isset($cliOptions['d'])) {
    echo 'Deleting from ' . $dtFrom->format('Y-m-d') . ' to ' . $dtTo->format('Y-m-d') .
        ' (including; TZ is UTC). Ok?';
    fgets(STDIN);
}

$deleted = 0;
while ($dtFrom <= $dtTo) {
    $batchTo = clone $dtFrom;
    $batchTo->modify('+1 day');

    echo 'Processing ' . $dtFrom->format('Y-m-d') . '...' . PHP_EOL;

    /* grab emails */
    $emails = imap_search(
        $inbox,
        'SINCE ' . $dtFrom->format('d-M-Y') . ' BEFORE ' .
        $batchTo->format('d-M-Y')
    );

    if (!empty($emails)) {
        /* for every email... */
        foreach ($emails as $emailNumber) {
            $overview = imap_fetch_overview($inbox, $emailNumber, 0);
            $subj = iconv_mime_decode($overview[0]->subject, ICONV_MIME_DECODE_CONTINUE_ON_ERROR);
            $from = iconv_mime_decode($overview[0]->from);

            if (isset($cliOptions['v'])) {
                echo $overview[0]->date . "\t" .
                    $from . "\t" .
                    (mb_strlen($subj) > 50 ? mb_substr($subj, 0, 47) . '...' : $subj) . PHP_EOL;
            }

            //imap_setflag_full($inbox, $emailNumber, "\\Seen");
            //imap_mail_move($inbox, $emailNumber, 'Trash');

            if (isset($cliOptions['d'])) {
                if (!imap_delete($inbox, $emailNumber)) {
                    die('Cannot delete letter');
                }

                if (++$deleted % 100 === 0) {
                    echo 'Deleted ' . $deleted . PHP_EOL;
                }
            }
        }
    }

    if (!imap_ping($inbox)) {
        imap_close($inbox);
        $inbox = connect();
        echo 'Reconnect and process this day again!' . PHP_EOL;
    } else {
        echo 'Deleted ' . $deleted . PHP_EOL;
        $dtFrom = $batchTo;
    }

    imap_expunge($inbox); //Purge messages, scheduled for deletition
    imap_gc($inbox, IMAP_GC_ELT | IMAP_GC_ENV); //Garbage collection
}

/* close the connection */
imap_close($inbox);

echo PHP_EOL . 'Total ' . $deleted . PHP_EOL;