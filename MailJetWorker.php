<?php

class MailJetWorker {

    private $cwfConfig;
    private $api_secret;

    public function __construct($cwfConfig) {
        $this->cwfConfig = $cwfConfig;
    }

    public function start() {
        echo "Starting Mailjet Client ..." . PHP_EOL;
        $mj = new \Mailjet\Client($this->cwfConfig['mailer']['apikey'], $this->cwfConfig['mailer']['apisecret'], true, ['version' => 'v3.1']);

        echo "Connecting DB ..." . PHP_EOL;
        $dbInfo = $this->cwfConfig['dbInfo'];
        $cn = new \PDO('pgsql:host=' . $dbInfo['dbServer'] . ' dbname=' . $dbInfo['dbMain'] .
                ' user=' . $dbInfo['suName'] . ' password=' . $dbInfo['suPass'] .
                (array_key_exists('port', $dbInfo) ? ' port=' . $dbInfo['port'] : ''));

        while (true) {

            $sql = "Select * from sys.notification_mail where is_send=0 and notification_mail_id = 884 -- order by notification_mail_id asc limit 5";
            $query = $cn->query($sql);
            $rows = $query->fetchAll();
            $query->closeCursor();
            echo "Querying Notification Mail: " . count($rows) . " rows found" . PHP_EOL;
            if (count($rows) == 0) {
                break;
            }

            foreach ($rows as $row) {
                $mail_id = $row['notification_mail_id'];
                if ($this->validate($row)) {
                    $msg = [];
                    $msg['From'] = [
                        'Email' => trim($row['mail_from']),
                        'Name' => "CoreERP Notifications"
                    ];
                    $msg['To'] = [
                        ['Email' => trim($row['mail_to'])]
                    ];
                    if (trim($row['cc']) !== '') {
                        $msg['Cc'] = [
                            ['Email' => trim($row['cc'])]
                        ];
                    }
                    $msg['Subject'] = $row['subject'];
                    $msg['HTMLPart'] = $row['body'];
                    // TODO: Attachments
                    // Send Mail
                    $msgBody = [
                        'Messages' => [
                            $msg
                        ]
                    ];
                    $resp = $mj->post(\Mailjet\Resources::$Email, ['body' => $msgBody]);

                    if ($resp->success()) {
                        $stmt = $cn->prepare("Update sys.notification_mail SET is_send = 1, send_result = :presp_data WHERE notification_mail_id = :pmail_id");
                        $stmt->execute([
                            'pmail_id' => $mail_id,
                            'presp_data' => json_encode($resp->getData(), JSON_HEX_APOS)
                        ]);
                        echo 'Mail ID: ' . $mail_id . " Result: Success" . PHP_EOL;
                    } else {
                        $stmt = $cn->prepare("Update sys.notification_mail SET is_send = 99, send_result = :presp_data WHERE notification_mail_id = :pmail_id");
                        $stmt->execute([
                            'pmail_id' => $mail_id,
                            'presp_data' => json_encode($resp->getData(), JSON_HEX_APOS)
                        ]);
                        throw new Exception('Mail ID: ' . $mail_id . " Result: Error [" . $resp->getStatus() . ":" . $resp->getReasonPhrase() . "]" . json_encode($resp->getBody()));
                    }
                } else {
                    $stmt = $cn->prepare("Update sys.notification_mail SET is_send = 99, send_result = :presp_data WHERE notification_mail_id = :pmail_id");
                    $stmt->execute([
                        'pmail_id' => $mail_id,
                        'presp_data' => 'Validation Failed [Email id]'
                    ]);
                    throw new Exception('Mail ID: ' . $mail_id . " Result: Validation Failed [Email id]");
                }
            }
        }
    }

    private function validate($row) {
        $mailTo = explode(',', trim($row['mail_to']));
        $mailFrom = trim($row['mail_from']);
        $reply_to = $row['reply_to'];
        $cc = ($row['cc'] == NULL || $row['cc'] == '') ? [] : explode(',', trim($row['cc']));
        $bcc = ($row['bcc'] == NULL || $row['bcc'] == '') ? [] : explode(',', trim($row['bcc']));
        foreach ($mailTo as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in MailTo :' . $email . "\n";
                return FALSE;
            }
        }
        if (!filter_var($mailFrom, FILTER_VALIDATE_EMAIL)) {
            echo 'invalid email in MailFrom :' . $mailFrom . "\n";
            return FALSE;
        }
        if ($reply_to != '') {
            if (!filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in ReplyTo :' . $reply_to . "\n";
                return FALSE;
            }
        }
        foreach ($cc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in CC :' . $email . "\n";
                return FALSE;
            }
        }
        foreach ($bcc as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo 'invalid email in BCC :' . $email . "\n";
                return FALSE;
            }
        }
        return TRUE;
    }

}
