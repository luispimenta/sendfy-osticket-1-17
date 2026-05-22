<?php

require_once INCLUDE_DIR . 'class.plugin.php';

class SendfyPluginConfig extends PluginConfig {

    // Provide compatibility function for versions of osTicket prior to
    // translation support (v1.9.4)
    function translate() {
        if (!method_exists('Plugin', 'translate')) {
            return array(
                function ($x) {
                    return $x;
                },
                function ($x, $y, $n) {
                    return $n != 1 ? $y : $x;
                }
            );
        }
        return Plugin::translate('sendfy');
    }

    function getOptions() {
        list ($__, $_N) = self::translate();

        return array(
          'sendfy-x-api-key' => new TextboxField(array(
            'label'           => $__('Sendfy x-api-key'),
            'required'        =>true,
            'default'         => '',
            'placeholder'     => "Sendfy x-api-key",
            'configuration' => array(
              'size'   => 130,
              'length' => 300
            )
          )),
          'sendfy-whatsapp-key' => new TextboxField(array(
            'label'           => $__('Sendfy Instance id'),
            'required'        =>true,
            'default'         => '',
            'placeholder'     => "Sendfy Instance id",
            'configuration' => array(
              'size'   => 130,
              'length' => 300
            )
          )),
          'sendfy-send-ticket-link' => new BooleanField(array(
            'label'       => $__('Enviar link do ticket na mensagem'),
            'default'     => false,
            'configuration' => array(
              'desc' => $__('Quando ativo, o link do ticket será incluído na mensagem enviada.')
            )
          )),
          'sendfy-message-created' => new TextareaField(array(
            'label'       => $__('Mensagem de abertura do ticket'),
            'required'    => false,
            'default'     => '',
            'placeholder' => $__('Ex: Olá {staff}, o ticket #{number} foi aberto. Assunto: {subject}'),
            'hint'        => $__('Parâmetros disponíveis: {staff}, {title}, {number}, {subject}, {status}, {help_topic}'),
            'configuration' => array(
              'rows'   => 4,
              'cols'   => 60,
              'length' => 1000
            )
          )),
          'sendfy-message-updated' => new TextareaField(array(
            'label'       => $__('Mensagem de atualização do ticket'),
            'required'    => false,
            'default'     => '',
            'placeholder' => $__('Ex: Olá {staff}, o ticket #{number} foi atualizado. Status: {status}'),
            'hint'        => $__('Parâmetros disponíveis: {staff}, {title}, {number}, {subject}, {status}, {help_topic}'),
            'configuration' => array(
              'rows'   => 4,
              'cols'   => 60,
              'length' => 1000
            )
          ))
        );
    }

}
