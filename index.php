<?php

include 'config.php'; // mail server config
require_once 'lib/ImapMailbox.php'; // License: GNU-GPL https://github.com/barbushin/php-imap

define('ICS_FILENAME', 'calendar.ics');
define('ICS_TEMPLATE_FILENAME', 'calendar.template.txt');

define('RECHECK_FREQUENCY_MINUTES', 3*60);

///

$icsTemplate = implode("", file(ICS_TEMPLATE_FILENAME));
$icsPrevious = file(ICS_FILENAME);
$lastChecked = $icsPrevious[count($icsPrevious)-1]; // stored on final line

// need to refresh?
if (!$lastChecked
	|| time() - $lastChecked > 60*RECHECK_FREQUENCY_MINUTES
	|| $_GET['debug']) {

	$mailbox = new ImapMailbox('{'.MAIL_SERVER.':993/imap/ssl}INBOX',
		MAIL_LOGIN, MAIL_PASSWORD);

	// Get some mail
	$mailsIds = $mailbox->searchMailBox('ALL'); //TODO UNSEEN?
	if($mailsIds) { // We have new mail

		$icsEvents = '';

		foreach ($mailsIds as $mailId) {
			$mail = $mailbox->getMail($mailId);

			if ($mail->textHtml) {
				// it's an invitation: we've got ical already (maybe)
				$sourceText = $mail->textHtml;

				$start = strpos($sourceText, "BEGIN:VCALENDAR");
				$sourceText = substr($sourceText, $start, strpos($sourceText, "END:VCALENDAR")+13-$start);
			} else {
				// it's a forward: have to pick out the ical among other stuff
				$b64token = "Content-Transfer-Encoding: base64";
				if (strpos($mail->textPlain, $b64token)) {
					$b64start = strpos($mail->textPlain, $b64token)+strlen($b64token)+2;
					$mailFragment = substr($mail->textPlain, $b64start, 9999);
					$b64text = substr($mailFragment, 0, strpos($mailFragment, "==")+2);
					$sourceText = base64_decode($b64text);
				} else {
					$sourceText = '';
				}
			}

			if (strpos($sourceText, "BEGIN:VCALENDAR") === 0) {
				//we have ical
				
				// it's the single event that interests us
				$start = strpos($sourceText, "BEGIN:VEVENT");
				$vEvent = substr($sourceText, $start, strpos($sourceText, "END:VEVENT")+10-$start);

				// collapse [Outlook?] ICS forced newlines after 75 chars
				$vEventFullLines = str_replace("\n ", "", $vEvent);
				
				$vEventProperties = explode("\n", $vEventFullLines);
				
				// filter out any props that contain our secret mail address
				$vEventProperties = array_filter($vEventProperties, function($p) {
					return !strpos($p, MAIL_LOGIN);
				});

				// restore 75 char line maximum
				$vEventProperties = array_map(function($p) {
					return ics_split($p);
				}, $vEventProperties);

				// TODO mask email addresses
				// it's not preg_replace('/(?<=[=:].).*(?=[^=:]*@)/u','*',$attendee) :(

				$vEvent = implode("\n", $vEventProperties);
				$icsEvents .= $vEvent."\n";
				
			}
				
			//TODO? $mailbox->markMailAsRead($mailId);

		}
		// (re)generate ICS file
		$icsFull = str_replace('***', $icsEvents, $icsTemplate);
		$icsFull .= "\n".time(); // = lastChecked;
		$icsFile = fopen(ICS_FILENAME, 'w');
		fwrite($icsFile, $icsFull);
		fclose($icsFile);

	}


}

// output ICS file
if (!$_GET['debug']) { header("Content-Type: text/calendar"); }
readfile(ICS_FILENAME);



////// helpers

function ics_split($string) { // enforce ical max line length
	$length = strlen($string);
	if ($length > 75) {
		return rtrim(chunk_split($string, 75, "\n ")); // the space is important
	} else {
		return $string;
	}
}

?>