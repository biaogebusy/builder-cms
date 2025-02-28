<?php

namespace Drupal\swiftmailer\Plugin\Mail;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Random;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Asset\AssetResolverInterface;
use Drupal\Core\Asset\AttachedAssets;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Site\Settings;
use Drupal\swiftmailer\TransportFactoryInterface;
use Drupal\swiftmailer\Utility\Conversion;
use Exception;
use Html2Text\Html2Text;
use Psr\Log\LoggerInterface;
use stdClass;
use Swift_Attachment;
use Swift_Image;
use Swift_Mailer;
use Swift_Message;
use Symfony\Component\DependencyInjection\ContainerInterface;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\mailsystem\MailsystemManager;

/**
 * Provides a 'Swift Mailer' plugin to send emails.
 *
 * @Mail(
 *   id = "swiftmailer",
 *   label = @Translation("Swift Mailer"),
 *   description = @Translation("Swift Mailer Plugin.")
 * )
 */
class SwiftMailer implements MailInterface, ContainerFactoryPluginInterface {

  /**
   * An array containing configuration settings.
   *
   * @var array
   */
  protected $config;

  /**
   * The logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The transport factory service.
   *
   * @var \Drupal\swiftmailer\TransportFactoryInterface
   */
  protected $transportFactory;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The asset resolver.
   *
   * @var \Drupal\Core\Asset\AssetResolverInterface
   */
  protected $assetResolver;

  /**
   * SwiftMailer constructor.
   *
   * @param \Drupal\swiftmailer\TransportFactoryInterface $transport_factory
   *   The transport factory service.
   * @param \Drupal\Core\Config\ImmutableConfig $message
   *   The swiftmailer message configuration.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager.
   * @param \Drupal\Core\Asset\AssetResolverInterface $asset_resolver
   *   The asset resolver.
   */
  public function __construct(TransportFactoryInterface $transport_factory, ImmutableConfig $message, LoggerInterface $logger, RendererInterface $renderer, ModuleHandlerInterface $module_handler, MailManagerInterface $mail_manager, ThemeManagerInterface $theme_manager, AssetResolverInterface $asset_resolver) {
    $this->transportFactory = $transport_factory;
    $this->config['message'] = $message->get();
    $this->logger = $logger;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->mailManager = $mail_manager;
    $this->themeManager = $theme_manager;
    $this->assetResolver = $asset_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('swiftmailer.transport'),
      $container->get('config.factory')->get('swiftmailer.message'),
      $container->get('logger.factory')->get('swiftmailer'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('plugin.manager.mail'),
      $container->get('theme.manager'),
      $container->get('asset.resolver')
    );
  }

  /**
   * Formats a message composed by drupal_mail().
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return array
   *   The message as it should be sent.
   */
  public function format(array $message) {
    // Get content type.
    $is_html = ($this->getContentType($message) == SWIFTMAILER_FORMAT_HTML);

    // Determine if a plain text alternative is required. The message parameter
    // takes priority over config. Support the alternate parameter 'convert'
    // for back-compatibility.
    $generate_plain = $message['params']['generate_plain'] ?? $message['params']['convert'] ?? $this->config['message']['generate_plain'];

    if ($generate_plain && empty($message['plain']) && $is_html) {
      // Generate plain text alternative. This must be done first with the
      // original message body, before overwriting it with the HTML version.
      $saved_body = $message['body'];
      $this->massageMessageBody($message, FALSE);
      $message['plain'] = $message['body'];
      $message['body'] = $saved_body;
    }

    $this->massageMessageBody($message, $is_html);

    // We replace all 'image:foo' in the body with a unique magic string like
    // 'cid:[randomname]' and keep track of this. It will be replaced by the
    // final "cid" in ::embed().
    $random = new Random();
    $embeddable_images = [];
    $processed_images = [];
    preg_match_all('/"image:([^"]+)"/', $message['body'], $embeddable_images);
    for ($i = 0; $i < count($embeddable_images[0]); $i++) {
      $image_id = $embeddable_images[0][$i];
      if (isset($processed_images[$image_id])) {
        continue;
      }
      $image_path = trim($embeddable_images[1][$i]);
      $image_name = basename($image_path);

      if (mb_substr($image_path, 0, 1) == '/') {
        $image_path = mb_substr($image_path, 1);
      }

      $image = new \stdClass();
      $image->uri = $image_path;
      $image->filename = $image_name;
      $image->filemime = \Drupal::service('file.mime_type.guesser')->guess($image_path);
      $image->cid = $random->name(8, TRUE);
      $message['params']['images'][] = $image;
      $message['body'] = preg_replace($image_id, 'cid:' . $image->cid, $message['body']);
      $processed_images[$image_id] = 1;
    }

    return $message;
  }

  /**
   * Sends a message composed by drupal_mail().
   *
   * @param array $message
   *   A message array holding all relevant details for the message.
   *
   * @return bool
   *   TRUE if the message was successfully sent, and otherwise FALSE.
   */
  public function mail(array $message) {
    try {

      // Create a new message.
      $m = new Swift_Message($message['subject']);

      // Not all Drupal headers should be added to the e-mail message.
      // Some headers must be suppressed in order for Swift Mailer to
      // do its work properly.
      $suppressable_headers = swiftmailer_get_supressable_headers();

      // Process headers provided by Drupal. We want to add all headers which
      // are provided by Drupal to be added to the message. For each header we
      // first have to find out what type of header it is, and then add it to
      // the message as the particular header type.
      if (!empty($message['headers']) && is_array($message['headers'])) {
        foreach ($message['headers'] as $header_key => $header_value) {

          // Check whether the current header key is empty or represents
          // a header that should be suppressed. If yes, then skip header.
          if (empty($header_key) || in_array($header_key, $suppressable_headers)) {
            continue;
          }

          // Skip 'Content-Type' header if the message is a multipart message.
          if ($header_key == 'Content-Type' && swiftmailer_is_multipart($message)) {
            continue;
          }

          // Get header type.
          $header_type = Conversion::swiftmailer_get_headertype($header_key, $header_value);

          // Add the current header to the e-mail message.
          switch ($header_type) {
            case SWIFTMAILER_HEADER_ID:
              Conversion::swiftmailer_add_id_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_PATH:
              Conversion::swiftmailer_add_path_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_MAILBOX:
              Conversion::swiftmailer_add_mailbox_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_DATE:
              Conversion::swiftmailer_add_date_header($m, $header_key, $header_value);
              break;

            case SWIFTMAILER_HEADER_PARAMETERIZED:
              Conversion::swiftmailer_add_parameterized_header($m, $header_key, $header_value);
              break;

            default:
              Conversion::swiftmailer_add_text_header($m, $header_key, $header_value);
              break;

          }
        }
      }

      // \Drupal\Core\Mail\Plugin\Mail\PhpMail respects $message['to'] but for
      // 'from' and 'reply-to' it uses the headers (which are set in
      // MailManager::doMail). Replicate that behavior here.
      Conversion::swiftmailer_add_mailbox_header($m, 'To', $message['to']);

      // Parse Cc and Bcc headers from the message and the message params.
      $this->handleCarbonCopy($message, $m);

      // Get content type.
      $content_type = $this->getContentType($message);

      // Get applicable character set.
      $applicable_charset = $this->getApplicableCharset($message);

      // Set body.
      $m->setBody($message['body'], $content_type, $applicable_charset);

      // Add alternative plain text version if format is HTML and plain text
      // version is available.
      if ($content_type == SWIFTMAILER_FORMAT_HTML && !empty($message['plain'])) {
        $m->addPart($message['plain'], SWIFTMAILER_FORMAT_PLAIN, $applicable_charset);
      }

      // Validate that $message['params']['files'] is an array.
      if (empty($message['params']['files']) || !is_array($message['params']['files'])) {
        $message['params']['files'] = [];
      }

      // Let other modules get the chance to add attachable files.
      $files = $this->moduleHandler->invokeAll('swiftmailer_attach', ['key' => $message['key'], 'message' => $message]);
      if (!empty($files) && is_array($files)) {
        $message['params']['files'] = array_merge(array_values($message['params']['files']), array_values($files));
      }

      // Attach files.
      if (!empty($message['params']['files']) && is_array($message['params']['files'])) {
        $this->attach($m, $message['params']['files']);
      }

      // Attach files (provide compatibility with mimemail)
      if (!empty($message['params']['attachments']) && is_array($message['params']['attachments'])) {
        $this->attachAsMimeMail($m, $message['params']['attachments']);
      }

      // Embed images.
      if (!empty($message['params']['images']) && is_array($message['params']['images'])) {
        $this->embed($m, $message['params']['images']);
      }

      // Get the configured transport type.
      $transport_type = $this->transportFactory->getDefaultTransportMethod();
      $transport = $this->transportFactory->getTransport($transport_type);

      /** @var \Swift_Mailer $mailer */
      $mailer = new Swift_Mailer($transport);

      // Allows other modules to customize the message.
      $this->moduleHandler->alter('swiftmailer', $mailer, $m, $message);

      // Send the message.
      Conversion::swiftmailer_filter_message($m);
      return (bool) $mailer->send($m);
    }
    catch (Exception $e) {
      $headers = !empty($m) ? $m->getHeaders() : '';
      $headers = !empty($headers) ? nl2br($headers->toString()) : 'No headers were found.';
      $this->logger->error(
        'An attempt to send an e-mail message failed, and the following error
        message was returned : @exception_message<br /><br />The e-mail carried
        the following headers:<br /><br />@headers',
        ['@exception_message' => $e->getMessage(), '@headers' => $headers]);
    }
    return FALSE;
  }

  /**
   * Handles the Cc and Bcc headers.
   *
   * @param array $message
   *   The message array.
   * @param \Swift_Message $m
   *   The swift message object.
   */
  protected function handleCarbonCopy(array $message, Swift_Message $m) {
    foreach (['Cc', 'Bcc'] as $header) {
      $copy = [];
      if (isset($message['headers'][$header]) && is_string($message['headers'][$header])) {
        $copy = Conversion::swiftmailer_parse_mailboxes($message['headers'][$header]);
      }

      if (!empty($copy)) {
        $function = 'set' . $header;
        $m->$function($copy);
      }
    }
  }

  /**
   * Process attachments.
   *
   * @param \Swift_Message $m
   *   The message which attachments are to be added to.
   * @param array $files
   *   The files which are to be added as attachments to the provided message.
   *
   * @internal
   */
  protected function attach(Swift_Message $m, array $files) {

    // Iterate through each array element.
    foreach ($files as $file) {

      if ($file instanceof stdClass) {

        // Validate required fields.
        if (empty($file->uri) || empty($file->filename) || empty($file->filemime)) {
          continue;
        }

        // Get file data from local file, stream, or remote (e.g. http(s)) uri.
        $content = file_get_contents($file->uri);

        $filename = $file->filename;
        $filemime = $file->filemime;

        // Attach file.
        $m->attach(new Swift_Attachment($content, $filename, $filemime));
      }
    }

  }

  /**
   * Process MimeMail attachments.
   *
   * @param \Swift_Message $m
   *   The message which attachments are to be added to.
   * @param array $attachments
   *   The attachments which are to be added message.
   *
   * @internal
   */
  protected function attachAsMimeMail(Swift_Message $m, array $attachments) {
    // Iterate through each array element.
    foreach ($attachments as $a) {
      if (is_array($a)) {
        // Validate that we've got either 'filepath' or 'filecontent.
        if (empty($a['filepath']) && empty($a['filecontent'])) {
          continue;
        }

        // Validate required fields.
        if (empty($a['filename']) || empty($a['filemime'])) {
          continue;
        }

        // Attach file (either using a static file or provided content).
        if (!empty($a['filepath'])) {
          $file = new stdClass();
          $file->uri = $a['filepath'];
          $file->filename = $a['filename'];
          $file->filemime = $a['filemime'];
          $this->attach($m, [$file]);
        }
        else {
          $m->attach(new Swift_Attachment($a['filecontent'], $a['filename'], $a['filemime']));
        }
      }
    }
  }

  /**
   * Process inline images..
   *
   * @param \Swift_Message $m
   *   The message which inline images are to be added to.
   * @param array $images
   *   The images which are to be added as inline images to the provided
   *   message.
   *
   * @internal
   */
  protected function embed(Swift_Message $m, array $images) {

    // Iterate through each array element.
    foreach ($images as $image) {

      if ($image instanceof stdClass) {

        // Validate required fields.
        if (empty($image->uri) || empty($image->filename) || empty($image->filemime) || empty($image->cid)) {
          continue;
        }

        // Keep track of the 'cid' assigned to the embedded image.
        $cid = NULL;

        // Get image data.
        if (UrlHelper::isValid($image->uri, TRUE)) {
          $content = file_get_contents($image->uri);
        }
        else {
          $content = file_get_contents(\Drupal::service('file_system')->realpath($image->uri));
        }

        $filename = $image->filename;
        $filemime = $image->filemime;

        // Embed image.
        $cid = $m->embed(new Swift_Image($content, $filename, $filemime));

        // The provided 'cid' needs to be replaced with the 'cid' returned
        // by the Swift Mailer library.
        $body = $m->getBody();
        $body = preg_replace('/cid:' . $image->cid . '/', $cid, $body);
        $m->setBody($body);
      }
    }
  }

  /**
   * Returns the message content type.
   *
   * @param array $message
   *   The message for which the applicable format is to be determined.
   *
   * @return string
   *   A string being the applicable format.
   *
   * @internal
   */
  protected function getContentType(array $message) {
    // The message parameter takes priority over config. Support the alternate
    // parameter 'format' for back-compatibility.
    $content_type = $message['params']['content_type'] ?? $message['params']['format'] ?? $this->config['message']['content_type'];
    // 1) check the message parameters.
    if ($content_type) {
      return $content_type;
    }

    // Then check the Content-Type header.
    if (isset($message['headers']['Content-Type'])) {
      return explode(';', $message['headers']['Content-Type'])[0];
    }

    // Drupal sets the header by default, but add a fallback just in case.
    return 'text/plain';
  }

  /**
   * Returns the applicable charset.
   *
   * @param array $message
   *   The message for which the applicable charset is to be determined.
   *
   * @return string
   *   A string being the applicable charset.
   *
   * @internal
   */
  protected function getApplicableCharset(array $message) {
    // Check if a charset has been provided particularly for this message. If
    // that is the case, then apply that format instead of the default format.
    return $message['params']['charset'] ?? $this->config['message']['character_set'];
  }

  /**
   * Massages the message body into the format expected for rendering.
   *
   * @param array $message
   *   The message.
   * @param boolean $is_html
   *   True if generating HTML output, false for plain text.
   *
   * @internal
   */
  protected function massageMessageBody(array &$message, $is_html) {
    $text_format = $message['params']['text_format'] ?? $this->config['message']['text_format'] ?: NULL;
    $line_endings = Settings::get('mail_line_endings', PHP_EOL);
    $body = [];

    foreach ($message['body'] as $part) {
      if (!($part instanceof MarkupInterface)) {
        if ($is_html) {
          // Convert to HTML. The default 'plain_text' format escapes markup,
          // converts new lines to <br> and converts URLs to links.
          $body[] = check_markup($part, $text_format);
        }
        else {
          // The body will be plain text. However we need to convert to HTML
          // to render the template then convert back again. Use a fixed
          // conversion because we don't want to convert URLs to links.
          $body[] = preg_replace("|\n|", "<br />\n", HTML::escape($part)) . "<br />\n";
        }
      }
      else {
        $body[] = $part . $line_endings;
      }
    }

    // Merge all lines in the e-mail body and treat the result as safe markup.
    $message['body'] = Markup::create(implode('', $body));

    // Attempt to use the mail theme defined in MailSystem.
    if ($this->mailManager instanceof MailsystemManager) {
      $mail_theme = $this->mailManager->getMailTheme();
    }
    // Default to the active theme if MailsystemManager isn't used.
    else {
      $mail_theme = $this->themeManager->getActiveTheme()->getName();
    }

    $render = [
      '#theme' => $message['params']['theme'] ?? 'swiftmailer',
      '#message' => $message,
      '#is_html' => $is_html,
    ];

    if ($is_html) {
      $render['#attached']['library'] = ["$mail_theme/swiftmailer"];
    }

    $message['body'] = $this->renderer->renderPlain($render);

    if ($is_html) {
      // Process CSS from libraries.
      $assets = AttachedAssets::createFromRenderArray($render);
      $css = '';
      // Request optimization so that the CssOptimizer performs essential
      // processing such as @include.
      foreach ($this->assetResolver->getCssAssets($assets, TRUE) as $css_asset) {
        $css .= file_get_contents($css_asset['data']);
      }

      if ($css) {
        $message['body'] = (new CssToInlineStyles())->convert($message['body'], $css);
      }
    }
    else {
      // Convert to plain text.
      $message['body'] = (new Html2Text($message['body']))->getText();
    }
  }

}
