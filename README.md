![osticket](https://demo.osticket.com.br/scp/images/ost-logo.png)

sendfy-osTicket for osTicket 1.17, for osticket 1.15 or less [use this one](https://github.com/luispimenta/sendfy-osticket)
==============
An plugin for [osTicket](https://osticket.com) to send Whatsapp message with [Sendfy](https://sendfy.app/)

Info
------
This plugin uses CURL and was designed/tested with osTicket-1.17

## Requirements
- php_curl

## Install
--------
1. Create your account on (https://app.sendfy.app)
2. Create your Whatsapp instance and connect with QRCODE
3. Get your Whatsapp instance KEY (https://app.sendfy.app/whatsapp_instances)
4. Get your x-api-key (https://app.sendfy.app/users/edit)
5. [Download](https://github.com/luispimenta/sendfy-osticket-1-17/releases/latest) the zip file, unzip inside a folder like name sendfy-osticket and place the contents into your `include/plugins`.
6. Now the plugin needs to be enabled & configured, select "Admin Panel" then "Manage -> Plugins" you should be seeing the list of currently installed plugins.
7. Click in Add New Plugin button, you will se the plugin name Sendfy Whatsapp Notification and click in Install.
8. Click to edit the plugin and on `Sendfy Whatsapp Notification >= 1.17`
9. Change for enable
10. Add new instance with name: Sendfy
11. State enable
12. In configuration paste your x-api-key and whatsapp-key ( step 3 and 4 )
13. Click `Add instance`! (If you get an error about curl, you will need to install the Curl module for PHP).
14. After that, go back to the list of plugins and tick the checkbox next to "Sendfy Whatsapp Notification >= 1.17" and select the "Enable" button.
15. That's it

Note: only tickets with Agents assigned and with full phone number (must have DDI)

## License

This plugin is released under the MIT license. See the file [LICENSE](LICENSE).
