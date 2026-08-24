<?php

namespace Eep\Service;

use Zend\Mail\Message;
use Zend\Mail\Transport\Smtp as SmtpTransport;
use Zend\Mail\Transport\SmtpOptions;
use Zend\Mime\Message as MimeMessage;
use Zend\Mime\Part as MimePart;

/**
 * Wrapper para el envio de correos electronicos via SMTP.
 * El envio se realiza en segundo plano mediante register_shutdown_function
 * para no bloquear la respuesta HTTP al usuario.
 */
class MailManager {

    /** @var array */
    private $config;

    /** @var string|null */
    private $footerImagePath;

    /**
     * @param array  $config Configuracion SMTP proveniente de local.php
     * @param string $footerImagePath Ruta absoluta a la imagen del footer
     */
    public function __construct(array $config, $footerImagePath = null) {
        $this->config = $config;
        $this->footerImagePath = $footerImagePath;
    }

    /**
     * Programa el envio de un mensaje HTML al destinatario indicado.
     * El envio real se ejecuta en segundo plano (register_shutdown_function)
     * para evitar bloquear la respuesta HTTP.
     *
     * @param string $to             Direccion de correo del destinatario
     * @param string $subject        Asunto del correo
     * @param string $html           Cuerpo del mensaje en HTML
     * @param array  $inlineImages   Imagenes incrustadas [cid => ruta_absoluta]
     * @param array  $cc             Correos en copia [correo1, correo2, ...]
     * @return bool True si se programo correctamente, false en caso contrario
     */
    public function sendHtmlMessage($to, $subject, $html, array $inlineImages = [], array $cc = []) {
        if (empty($to) || empty($this->config['from'])) {
            return false;
        }

        // Agregar footer automatico
        $html .= '<p style="font-size:12px;color:#666;margin-top:16px;">Este es un mensaje automatico, por favor no responda a este correo.</p>'
               . '<p style="margin-top:8px;"><img src="cid:footer-image" alt="Firma" width="600" height="auto" style="max-width:600px;width:100%;display:block;"></p>';

        // Agregar imagen del footer automaticamente si existe
        if ($this->footerImagePath && file_exists($this->footerImagePath)) {
            $inlineImages['footer-image'] = $this->footerImagePath;
        }

        $from = $this->config['from'];
        $fromName = $this->config['from_name'];
        $transportConfig = [
            'host'              => $this->config['host'],
            'port'              => $this->config['port'],
            'connection_class'  => $this->config['connection_class'],
            'connection_config' => $this->config['connection_config'],
        ];

        register_shutdown_function(function () use ($to, $subject, $html, $inlineImages, $cc, $from, $fromName, $transportConfig) {
            // Aislar completamente el envio de correo para que no corrompa
            // la respuesta HTTP que ya fue enviada al navegador
            $previousErrorReporting = error_reporting(0);
            @ini_set('display_errors', 0);
            ob_start();

            try {
                $message = new Message();
                $message->setEncoding('UTF-8')
                    ->addTo($to)
                    ->addFrom($from, $fromName)
                    ->setSubject($subject);

                // Agregar CC (copia a examinadores)
                foreach ($cc as $ccEmail) {
                    if (!empty($ccEmail)) {
                        $message->addCc($ccEmail);
                    }
                }

                // Construir cuerpo HTML con Mime
                $htmlPart = new MimePart($html);
                $htmlPart->type = 'text/html';
                $htmlPart->charset = 'utf-8';

                $body = new MimeMessage();
                $body->addPart($htmlPart);

                // Adjuntar imagenes inline
                foreach ($inlineImages as $cid => $imagePath) {
                    if (!file_exists($imagePath)) {
                        continue;
                    }
                    $imageContent = @file_get_contents($imagePath);
                    if ($imageContent === false) {
                        continue;
                    }
                    $imagePart = new MimePart($imageContent);
                    $imagePart->type = @mime_content_type($imagePath);
                    $imagePart->disposition = 'inline';
                    $imagePart->encoding = \Zend\Mime\Mime::ENCODING_BASE64;
                    $imagePart->filename = basename($imagePath);
                    $imagePart->id = $cid;
                    $body->addPart($imagePart);
                }

                $message->setBody($body);

                // Sobrescribir multipart/mixed por multipart/related para que
                // los clientes de correo reconozcan las imagenes como inline
                if ($body->isMultiPart()) {
                    $header = $message->getHeaders()->get('content-type');
                    if ($header) {
                        $header->setType('multipart/related');
                    }
                }

                // Configurar transporte SMTP
                $transport = new SmtpTransport();
                $options = new SmtpOptions($transportConfig);
                $transport->setOptions($options);
                $transport->send($message);
            } catch (\Throwable $e) {
                error_log('[MailManager] Error al enviar correo: ' . $e->getMessage());
            }

            // Descartar cualquier output generado durante el envio
            ob_end_clean();
            error_reporting($previousErrorReporting);
        });

        return true;
    }
}
