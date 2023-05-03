<?php

/** @deprecated since introduction of MailJetWorker */
class MailSenderWorker {

    // method declaration
    public function Start($cwfConfig) {

        try {

            $dbInfo = $cwfConfig['dbInfo'];
            $cn = new \PDO('pgsql:host=' . $dbInfo['dbServer'] . ' dbname=' . $dbInfo['dbMain'] .
                    ' user=' . $dbInfo['suName'] . ' password=' . $dbInfo['suPass'] .
                    (array_key_exists('port', $dbInfo) ? ' port=' . $dbInfo['port'] : ''));

            echo "Reading data..\n";
            while (true) {

                $sql = "Select * from sys.notification_mail where is_send=0 order by notification_mail_id asc limit 5";

                $query = $cn->query($sql);
                $rows = $query->fetchAll();
                $query->closeCursor();
                if (count($rows) == 0) {
                    break;
                }
                // Create Transport
                $transport = (new Swift_SmtpTransport($cwfConfig['mailer']['host'], $cwfConfig['mailer']['port'], isset($cwfConfig['mailer']['enc']) ? $cwfConfig['mailer']['enc'] : 'ssl'))
                        ->setUsername($cwfConfig['mailer']['username'])
                        ->setPassword($cwfConfig['mailer']['password']);
                // Create Mailer
                $mailer = new Swift_Mailer($transport);

                foreach ($rows as $row) {
                    $mail_id = $row['notification_mail_id'];
                    if ($this->validate($row)) {

                        echo "Sending Mail...\n";
                        echo "mailFrom: " . trim($row['mail_from']) . "\n";
                        echo "mailTo: " . trim($row['mail_to']) . "\n";
                        echo "reply_to: " . $row['reply_to'] . "\n";
                        echo "cc_to: " . trim($row['cc']) . "\n";
                        echo "\n";
                        
                        try {
                            // Create a message
                            $email = (new Swift_Message($row['subject']))
                                ->setFrom(trim($row['mail_from']))
                                ->setTo(explode(',', trim($row['mail_to'])))
                                ->setBody($row['body'], 'text/html');

                            if ($row['reply_to'] != '') {
                                $email = $email->setReplyTo($row['reply_to']);
                            }
                            if (trim($row['cc']) != '') {
                                $email = $email->setCc(explode(',', trim($row['cc'])));
                            }
                            if (trim($row['bcc']) != '') {
                                $email = $email->setBcc(explode(',', trim($row['bcc'])));
                            }
                            if ($row['attachment_path'] != '') {
                                $email->attach(Swift_Attachment::fromPath($row['attachment_path']));
                            }
                            $mailer->send($email);
                            
                            $update = 'Update sys.notification_mail SET is_send=1 WHERE notification_mail_id= ' . $mail_id;
                            $cn->exec($update);
                            echo 'Sent Notification Mail ID - ' . $mail_id . "\n";
                            // provide proper breaks before sending next mail. Else, server may term it as spam
                            usleep(50000);
                        } catch (\Swift_TransportException $ex) {
                            echo $ex->getMessage() . PHP_EOL;
                            echo 'Transport exception. Email not sent. notification_mail_id= ' . $mail_id . PHP_EOL;
                        } catch (\Swift_SwiftException $ex) {
                            $update = 'Update sys.notification_mail SET is_send=501 WHERE notification_mail_id= ' . $mail_id;
                            $cn->exec($update);
                            echo 'Not Sent Notification Mail ID - ' . $mail_id . ' err:' . $ex->getMessage()  . PHP_EOL;
                        } catch (\Exception $ex) {
                            $update = 'Update sys.notification_mail SET is_send=99 WHERE notification_mail_id= ' . $row['notification_mail_id'];
                            $cn->exec($update);
                            echo 'Not Sent Notification Mail ID - ' . $mail_id . ' err:' . $ex->getMessage()  . PHP_EOL;
                        }
                    } else {
                        $update = 'Update sys.notification_mail SET is_send=99 WHERE notification_mail_id= ' . $row['notification_mail_id'];
                        $cn->exec($update);
                    }
                }
            }
            $cn = null;
        } catch (\Exception $e) {//catches exceptions when connecting to database
            if ($cn !== null) {
                $query = null;
                $cn = null;
            }
            throw $e;
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
