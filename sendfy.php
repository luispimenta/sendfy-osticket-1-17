<?php

require_once(INCLUDE_DIR . 'class.signal.php');
require_once(INCLUDE_DIR . 'class.plugin.php');
require_once(INCLUDE_DIR . 'class.ticket.php');
require_once(INCLUDE_DIR . 'class.osticket.php');
require_once(INCLUDE_DIR . 'class.config.php');
require_once(INCLUDE_DIR . 'class.format.php');
require_once('config.php');

class SendfyPlugin extends Plugin {

    var $config_class = "SendfyPluginConfig";

    /**
     * The entrypoint of the plugin, keep short, always runs.
     */
    function bootstrap() {
        Signal::connect('ticket.created', array($this, 'onTicketCreated'));
        Signal::connect('threadentry.created', array($this, 'onTicketUpdated'));
    }

    /**
     * Retrieves configurations for all active/configured instances.
     * Compatible with osTicket 1.17 and 1.18.x.
     *
     * @return PluginConfig[]
     */
    function getPluginConfigs() {
        $configs = [];

        // 1. Try active instances (standard in osTicket 1.17+)
        $active_instances = $this->getActiveInstances();
        if ($active_instances && count($active_instances) > 0) {
            foreach ($active_instances as $instance) {
                if ($cfg = $instance->getConfig()) {
                    $configs[] = $cfg;
                }
            }
        }

        // 2. Fallback: all instances if none flagged active or getActiveInstances returned empty
        if (empty($configs) && $this->instances && count($this->instances) > 0) {
            foreach ($this->instances as $instance) {
                if ($cfg = $instance->getConfig()) {
                    $configs[] = $cfg;
                }
            }
        }

        // 3. Fallback: direct plugin config (if legacy or single-instance)
        if (empty($configs)) {
            if ($cfg = $this->getConfig()) {
                $configs[] = $cfg;
            }
        }

        return $configs;
    }

    /**
     * What to do with a new Ticket?
     *
     * @global OsticketConfig $cfg
     * @param Ticket $ticket
     * @return void
     */
    function onTicketCreated(Ticket $ticket) {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Sendfy plugin called too early.");
            return;
        }
        $status = "created";
        $this->sendToWebhook($ticket, $status);
    }

    /**
     * What to do with an Updated Ticket?
     *
     * @global OsticketConfig $cfg
     * @param ThreadEntry $entry
     * @return void
     */
    function onTicketUpdated(ThreadEntry $entry) {
        global $cfg;
        if (!$cfg instanceof OsticketConfig) {
            error_log("Sendfy plugin called too early.");
            return;
        }

        // Need to fetch the ticket from the ThreadEntry
        $ticket = $this->getTicket($entry);
        if (!$ticket instanceof Ticket) {
            return;
        }
        $status = "updated";
        $this->sendToWebhook($ticket, $status);
    }

    /**
     * A helper function that sends messages to webhook endpoints.
     *
     * @global osTicket $ost
     * @global OsticketConfig $cfg
     * @param Ticket $ticket
     * @param string $status
     * @throws \Exception
     */
    function sendToWebhook(Ticket $ticket, $status) {
        global $ost, $cfg;
        if (!$ost instanceof osTicket || !$cfg instanceof OsticketConfig) {
            error_log("Webhook plugin called too early.");
            return;
        }

        $configs = $this->getPluginConfigs();
        if (empty($configs)) {
            $ost->logError('Sendfy Plugin not configured', 'No active plugin instances found. Please configure the plugin in osTicket.');
            return;
        }

        $url = 'https://api.sendfy.app/webhook_osticket';

        foreach ($configs as $config) {
            $x_api_key    = $config->get('sendfy-x-api-key');
            $whatsapp_key = $config->get('sendfy-whatsapp-key');
            $send_link    = $config->get('sendfy-send-ticket-link');
            $msg_created  = $config->get('sendfy-message-created');
            $msg_updated  = $config->get('sendfy-message-updated');

            if (!$x_api_key) {
                $ost->logError('Sendfy x-api-key Plugin not configured', 'You need to read the Readme and configure before using this.');
                continue;
            }

            if (!$whatsapp_key) {
                $ost->logError('Sendfy Whatsapp Key Plugin not configured', 'You need to read the Readme and configure before using this.');
                continue;
            }

            // Build the payload with the formatted data:
            $staff      = $ticket->getStaff();
            $staff_name = $staff ? $staff->getUsername() : "";
            $number     = $ticket->getNumber();
            $subject    = $ticket->getSubject() ? $ticket->getSubject() : '';
            $help_topic = $ticket->getHelpTopic() ? $ticket->getHelpTopic() : '';
            $get_status = $ticket->getStatus();

            $params = [
                '{staff}'      => $staff_name,
                '{title}'      => $subject,
                '{number}'     => $number,
                '{subject}'    => $subject,
                '{status}'     => $get_status,
                '{help_topic}' => $help_topic,
            ];

            $custom_message = '';
            if ($status === 'created' && $msg_created) {
                $custom_message = strtr($msg_created, $params);
            } elseif ($status === 'updated' && $msg_updated) {
                $custom_message = strtr($msg_updated, $params);
            }

            $payload = [
                'body' => [
                    'staff'          => $staff_name,
                    'staff-mobile'   => $staff ? $staff->mobile : "",
                    'staff-phone'    => $staff ? $staff->phone : "",
                    'title'          => $subject,
                    'number'         => $number,
                    'status'         => $status,
                    'url'            => $cfg->getUrl(),
                    'x-api-key'      => $x_api_key,
                    'whatsapp-key'   => $whatsapp_key,
                    'closed'         => $ticket->isClosed(),
                    'subject'        => $subject,
                    'update_date'    => $ticket->getUpdateDate() ? Format::datetime($ticket->getUpdateDate()) : '',
                    'help_topic'     => $help_topic,
                    'user'           => $ticket->getOwner(),
                    'get_status'     => $get_status,
                    'get_state'      => $ticket->getState(),
                    'send_link'      => (bool) $send_link,
                    'custom_message' => $custom_message,
                    'ticket_id'      => $ticket->getId()
                ]
            ];

            // Format the payload safely for UTF-8 and PHP 8.1/8.2+:
            $data_string = json_encode($payload, JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            try {
                // Setup curl
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data_string))
                );

                // Actually send the payload to webhook:
                if (curl_exec($ch) === false) {
                    throw new \Exception($url . ' - ' . curl_error($ch));
                } else {
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($statusCode != 200) {
                        throw new \Exception(
                            'Error sending to: ' . $url
                            . ' Http code: ' . $statusCode
                            . ' curl-error: ' . curl_errno($ch)
                        );
                    }
                }
            } catch (\Exception $e) {
                $ost->logError('Webhook posting issue!', $e->getMessage(), true);
                error_log('Error posting to Webhook. ' . $e->getMessage());
            } finally {
                curl_close($ch);
            }
        }
    }

    /**
     * Fetches a ticket from a ThreadEntry
     *
     * @param ThreadEntry $entry
     * @return Ticket|null
     */
    function getTicket(ThreadEntry $entry) {
        if (method_exists($entry, 'getTicket') && ($ticket = $entry->getTicket())) {
            return $ticket;
        }

        if (method_exists($entry, 'getThread') && ($thread = $entry->getThread())) {
            if ($thread->getObjectType() === 'T' && ($ticket = $thread->getObject())) {
                return $ticket;
            }
        }

        try {
            $ticket_id = Thread::objects()->filter([
                'id' => $entry->getThreadId()
            ])->values_flat('object_id')->first()[0];

            if ($ticket_id) {
                return Ticket::lookup(array('ticket_id' => $ticket_id));
            }
        } catch (\Throwable $e) {
            error_log('Sendfy: error finding ticket for thread entry: ' . $e->getMessage());
        }

        return null;
    }
}
