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

            $sql = "Select * from sys.notification_mail where is_send = 0 order by notification_mail_id asc limit 5"; //
            $query = $cn->query($sql);
            $rows = $query->fetchAll();
            $query->closeCursor();
            echo "Querying Notification Mail: " . count($rows) . " rows found" . PHP_EOL;
            if (count($rows) == 0) {
                break;
            }

            foreach ($rows as $row) {
                $mail_id = $row['notification_mail_id'];
                $addrs = $this->validate($row);
                if (count($addrs['errors']) == 0) {
                    $msg = [];
                    $msg['From'] = [
                        'Email' => trim($row['mail_from']),
                        'Name' => "CoreERP Notifications"
                    ];
                    $msg['To'] = [];
                    foreach ($addrs['mail_to'] as $mailTo) {
                        $msg['To'][] = ['Email' => $mailTo];
                    }
                    if (count($addrs['cc']) > 0) {
                        $msg['Cc'] = [];
                        foreach ($addrs['cc'] as $mailcc) {
                            $msg['Cc'][] = ['Email' => $mailcc];
                        }
                    }
                    $msg['Subject'] = $row['subject'];
                    $msg['HTMLPart'] = $row['body'];
                    //Attachments
                    if ($row['attachment_path'] !== '') {
                        $fileInfo = new \SplFileInfo($row['attachment_path']);
                        $fileData = base64_encode(file_get_contents($row['attachment_path']));
                        $msg['Attachments'] = [
                            [
                                'ContentType' => 'application/' . $fileInfo->getExtension(),
                                'FileName' => $fileInfo->getBasename(),
                                'Base64Content' => $fileData
                            ]
                        ];
                    }
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
                        'presp_data' => 'Validation Failed' . PHP_EOL . implode(PHP_EOL, $addrs['errors'])
                    ]);
                    throw new Exception('Mail ID: ' . $mail_id . " Result: Validation Failed" . PHP_EOL . implode(PHP_EOL, $addrs['errors']));
                }
            }
        }
    }

    private function validate($row): array {
        $addrs = [
            'mail_to' => [],
            'reply_to' => '',
            'cc' => [],
            'bcc' => [],
            'errors' => []
        ];
        // Resolve Mail To
        $mailTo = explode(',', trim($row['mail_to']));
        foreach($mailTo as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $addrs['mail_to'][] = trim($addr);
            } else {
                $addrs['errors'][] = "Mail To has Invalid Email Address [$addr]";
            }
        }
        // Resolve Reply-To
        $addrs['reply_to'] = trim($row['reply_to']);
        //Resolve CC
        $cc = $row['cc'] == '' ? [] : explode(',', trim($row['cc']));
        foreach($cc as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $addrs['cc'][] = trim($addr);
            } else {
                $addrs['errors'][] = "Mail Cc has Invalid Email Address [$addr]";
            }
        }
        // Resolve BCC
        $bcc = $row['bcc'] == '' ? [] : explode(',', trim($row['bcc']));
        foreach($bcc as $addr) {
            if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $addrs['bcc'][] = trim($addr);
            } else {
                $addrs['errors'][] = "Mail Bcc has Invalid Email Address [$addr]";
            }
        }
        return $addrs;
    }

}
