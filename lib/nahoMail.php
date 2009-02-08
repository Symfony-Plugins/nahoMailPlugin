<?php

class nahoMail
{
  
  /**
   * Send a mail
   *
   * Options :
   * - from 
   * - connection:
   *     type (*)
   *     params
   * - reply-to
   * - return-path
   * - cc
   * - bcc
   * - subject-template
   * - parts is an array of bodies
   * - attachments
   * - embed-images
   * 
   * $body can be a direct string (in this case, content type will be set as text/plain) or an associative array :
   * - content (*)
   * - content-type
   * - encoding
   * - charset
   * $body[content] can be a direct string (which will be the body's content), or an associative array :
   * - type (partial or component)
   * - name is the name of the partial or component, as "module/partialName" or "module/componentName"
   * - vars is an associative array of variables, passed to the view
   * 
   * attachments is an array of attachment, an attachement is either a string (path name), or a list of these options :
   * - path (*) is the real path on filesystem
   * - filename
   * - mime-type
   * 
   * embed-images is an associative array of key => image light path.
   * You can use "%%IMG_name-of-image%%" in the body or in any part to reference the corresponding embedded image.
   *  
   * @param string $subject
   * @param string|array $body
   * @param string|array $to
   * @param array $options
   * 
   * @return int The number of successful recipients 
   */
  public static function send($subject, $body, $to, array $options = array())
  {
    $options = array_merge($options, array(
      'subject' => $subject,
      'body'    => $body,
      'to'      => $to,
    ));
    
    return self::_send($options);
  }
  
  /**
   * Get the array describing your HTML body
   *
   * @param string $content_mode 'partial', 'component', or 'string'
   * @param string $body_html name of the partial, name of the component, or direct string
   * @param array $vars vars passed to the view (in case of partial or component)
   * @return array
   */
  public static function getBody($content_mode, $body_html, array $vars = array())
  {
    list($body, $part) = self::getBodyAndAlternate($content_mode, $body_html, null, $vars);
    
    return $body;
  }
  
  /**
   * Get the array describing your HTML body and the array describing the part for your alternate text/plain body
   *
   * @param string $content_mode 'partial', 'component', or 'string'
   * @param string $body_html name of the partial, name of the component, or direct string
   * @param string $body_plain name of the partial, name of the component, or direct string
   * @param array $vars vars passed to the view (in case of partial or component)
   * @return array (body, part)
   */
  public static function getBodyAndAlternate($content_mode, $body_html, $body_plain = null, array $vars = array())
  {
    $body = array('content-type' => 'text/html');
    $part = array('content-type' => 'text/plain');
    
    if ($content_mode == 'partial' || $content_mode == 'component') {
      
      $body['content'] = array(
        'type' => $content_mode, 
        'name' => $body_html, 
        'vars' => $vars
      );
      
      if ($body_plain) {
        $part['content'] = array(
          'type' => $content_mode,
          'name' => $body_plain,
          'vars' => $vars
        );
      }
      
    } elseif ($content_mode == 'string') {
      
      $body['content'] = $body_html;
      
      if ($body_plain) {
        $part['content'] = $body_plain;
      }
      
    }
    
    return array($body, @$part['content'] ? $part : null);
  }
  
  /**
   * Send a mail, using params passed :
   * 
   * - connection:
   *     type (*)
   *     params
   * - from (*)
   * - reply-to
   * - return-path
   * - to (*) is a string or array of string
   * - cc
   * - bcc
   * - subject-template
   * - subject (*)
   * - body (*) can also be a direct string
   *     content (*)
   *     content-type (default is text/plain)
   *     encoding
   *     charset
   * - parts is an array of bodies
   * - attachments
   * - embed-images
   * 
   * body[content] can be a direct string (which will be the body's content), or an associative array :
   * - type (partial or component)
   * - name is the name of the partial or component, as "module/partialName" or "module/componentName"
   * - vars is an associative array of variables, passed to the view
   * 
   * attachments is an array of attachment, an attachement is either a string (path name), or a list of these options :
   * - path (*) is the real path on filesystem
   * - filename
   * - mime-type
   * 
   * embed-images is an associative array of key => image light path.
   * You can use "%%IMG_name-of-image%%" in the body or in any part to reference the corresponding embedded image.
   *  
   * @param array $options
   * @throws Exception
   * @return int The number of successful recipients
   */
  protected static function _send(array $options)
  {
    $options = array_merge(sfConfig::get('app_mailer_defaults', array()), $options);
    
    // Mailer
    if (!isset($options['connection'])) {
      throw new Exception('Connection configuration required');
    }
    if (!isset($options['connection']['type'])) {
      throw new Exception('Connection type undefined');
    }
    if (!isset($options['connection']['params'])) {
      $options['connection']['params'] = array();
    }
    $connection = self::getConnection($options['connection']['type'], $options['connection']['params']);
    $mailer = new Swift($connection);
    
    // Basic elements
    $from = $options['from'];
    $to = $options['to'];
    if (!isset($options['subject'])) {
      throw new Exception('Subject required');
    }
    if (!isset($options['subject-template'])) {
      $options['subject-template'] = '%s';
    }
    if (!isset($options['i18n-catalogue'])) {
      $options['i18n-catalogue'] = 'messages';
    }
    $subject = self::getI18NString($options['subject'], $options['subject-template'], $options['i18n-catalogue']);
    
    // Message to be sent
    $mail = new Swift_Message($subject);
    
    // Embedded images
    if (isset($options['embed-images'])) {
      $embedded_images = self::embedImages($mail, @$options['embed-images']);
    } else {
      $embedded_images = array();
    }
    
    // Get body as the main part
    if (!isset($options['body'])) {
      throw new Exception('Body is required');
    }
    if (!is_array($options['body'])) {
      $options['body'] = array('content' => $options['body']);
    }
    $body = self::getPart($options['body'], $embedded_images);
    
    // Attach parts (body is the first one)
    if (isset($options['parts']) && is_array($options['parts'])) {
      $parts = self::getParts($options['parts'], $embedded_images);
      array_unshift($parts, $body);
      foreach ($parts as $part) {
        $mail->attach($part);
      }
    } 
    // No part, mail only has a body
    else {
      $mail->setBody($body->getData());
      $mail->setCharset($body->getCharset()); 
      $mail->setEncoding($body->getEncoding());
      $mail->setContentType($body->getContentType());
    }
    
    // Attach files
    if (isset($options['attachments']) && is_array($options['attachments'])) {
      foreach ($options['attachments'] as $attachment) {
        $mail->attach(self::getAttachment($attachment));
      }
    }
    
    // Handle other options
    if (isset($options['bcc'])) {
      $mail->setBcc($options['bcc']);
    }
    if (isset($options['cc'])) {
      $mail->setCc($options['cc']);
    }
    if (isset($options['reply-to'])) {
      $mail->setReplyTo($options['reply-to']);
    }
    if (isset($options['return-path'])) {
      $mail->setReturnPath($options['return-path']);
    }
    
    try {
      
      // Try to send the mail
      $result = $mailer->send($mail, $to, $from);
      $mailer->disconnect();
      
      return $result;
      
    } catch (Exception $e) {
      
      // An error occured, disconnect an eventual connection, and forwards the exception
      $mailer->disconnect();
      
      throw $e;
      
    }
  }
  
  /**
   * Returns a formatted string which format and base string will be translated using given i18n catalogue
   *
   * @param string $string
   * @param string $format
   * @param string $catalogue
   * @return string
   */
  protected static function getI18NString($string, $format = '%s', $catalogue = 'messages')
  {
    try {
      $context = sfContext::getInstance();
      if (is_callable(array($context, 'getI18N'))) {
        $i18n = $context->getI18N();
        $string = $i18n->__($string, array(), $catalogue);
        $format = $i18n->__($format, array(), $catalogue);
      }
    } catch (sfConfigurationException $e) {
      // I18N is not enabled, just bypass
    }
    
    return sprintf($format, $string);
  }
  
  /**
   * Images:
   *   name-of-image => path
   * 
   * @param Swift_Message $mail
   * @param array $images
   * 
   * return array
   */
  protected static function embedImages(Swift_Message $mail, array $images)
  {
    self::loadHelper('Asset');
    
    $cids = array();
    
    foreach ($images as $name => $image) {
      if (file_exists($image)) {
        // $image is already a valid path
        $path = $image;
      } else {
        // try to compute real image's path
        $path = sfConfig::get('sf_web_dir') . '/' . image_path($image, false);
      }
      $embed_image = new Swift_Message_Image(new Swift_File($path));
      $cids[$name] = $mail->attach($embed_image);
    }
    
    return $cids;
  }
  
  /**
   * Gets a content from a component, partial, or direct value.
   * Embedded images are replace by their CID
   * 
   * $content is either a string or a list of options :
   * - type (partial or component)
   * - name is the name of the partial or component, as "module/partialName" or "module/componentName"
   * - vars is an associative array of variables, passed to the view
   *
   * @param string|array $content
   * @param array $embedded_images
   * @return string
   */
  protected static function getContent($content, array $embedded_images = array())
  {
    if (is_array($content)) {
      self::loadHelper('Partial');
      
      if ($content['type'] == 'partial') {
        $string = get_partial($content['name'], $content['vars']);
      } else { // component
        list($module, $component) = explode('/', $content['name']);
        get_component($module, $component, $content['vars']);
      }
    } else {
      $string = $content;
    }
    
    self::replaceEmbeddedImages($string, $embedded_images);
    
    return $string;
  }
  
  /**
   * Replaces %%IMG_name%% by $embedded_images[name]
   *
   * @param string $string
   * @param array $embedded_images
   * 
   * @return string
   */
  protected static function replaceEmbeddedImages($string, array $embedded_images = array())
  {
    $transform_key = create_function('$key', 'return \'%%IMG_\'.$key.\'%%\';');
    $keys = array_map($transform_key, array_keys($embedded_images));
    $values = array_values($embedded_images);
    
    return str_replace($keys, $values, $string);
  }
  
  /**
   * $attachement is either a string (pathname), or a list of these options :
   * - path (*) is the real path on filesystem
   * - filename
   * - mime-type
   *
   * @param string|array $attachment
   * 
   * return Swift_Message_Attachment
   */
  protected static function getAttachment($attachment)
  {
    if (!is_array($attachment)) {
      $attachment = array('path' => $attachment);
    }
    if (!@$attachment['mime-type']) {
      $attachment['mime-type'] = mime_content_type($attachment['path']);
    }
    if (!@$attachment['filename']) {
      $attachment['filename'] = basename($attachment['path']);
    }
    
    $attach = new Swift_Message_Attachment(new Swift_File($attachment['path']));
    $attach->setContentType($attachment['mime-type']);
    $attach->setFileName($attachment['filename']);
    
    return $attach;
  }
  
  /**
   * Options :
   * - content-type (*)
   * - content (*)
   * - encoding
   * - charset
   *
   * @param array $options
   * @param array $embedded_images
   */
  protected static function getPart(array $options, array $embedded_images = array())
  {
    if ((is_array($options) && isset($options['type'])) || !is_array($options)) {
      // user directly passed $content as an array or a string
      $options = array('content' => $options);
    }
    
    $content = self::getContent($options['content'], $embedded_images);
    
    if (!@$options['content-type']) {
      $options['content-type'] = 'text/plain';
    }
    
    $part = new Swift_Message_Part($content, $options['content-type'], @$options['encoding'], @$options['charset']);
    
    return $part;
  }
  
  /**
   * Returns list of parts
   *
   * @param array $parts_options
   * @param array $embedded_images
   * @return unknown
   */
  protected static function getParts(array $parts_options, array $embedded_images = array())
  {
    $parts = array();
    foreach ($parts_options as $part_options) {
      $parts[] = self::getPart($part_options, $embedded_images);
    }
    
    return $parts;
  }
  
  /**
   * Builds a Swift_Connection depending on the type (native, smtp, sendmail, multi, rotator)
   * Params depend on the connection type
   * 
   * - native:
   *    additional_params
   * 
   * - smtp:
   *     server (*)
   *     port
   *     encryption (SSL, TLS, or OFF)
   *     authentication:
   *       username (*)
   *       password
   *     timeout
   *     requires_ehlo
   *   
   * - sendmail:
   *     command
   *     flags
   *     timeout
   *     requires_ehlo
   *   
   * - multi:
   *     connections:
   *       connection_name1:
   *         type
   *         params
   *       connection_name2:
   *         type
   *         params
   *       etc...
   *     requires_ehlo
   *   
   * - rotator:
   *     connections:
   *       connection_name1:
   *         type
   *         params
   *       connection_name2:
   *         type
   *         params
   *       etc...
   *     requires_ehlo
   *  
   * (*) Mandatory !
   *
   * @param string $type
   * @param array $params
   * @return Swift_Connection
   */
  protected static function getConnection($type, $params = array())
  {
    switch ($type) {
      
      case 'native':
        $connection = new Swift_Connection_NativeMail();
        if (@$params['additional_params']) {
          $connection->setAdditionalMailParams($params['additional_params']);
        }
        break;
        
      case 'smtp':
        if (!@$params['encryption']) {
          $params['encryption'] = 'OFF';
        }
        $encryption = constant('Swift_Connection_SMTP::ENC_'.$params['encryption']);
        $connection = new Swift_Connection_SMTP($params['server'], @$params['port'], $encryption);
        if (@$params['authentication']) {
          $connection->setUsername(@$params['authentication']['username']);
          $connection->setPassword(@$params['authentication']['password']);
        }
        if (@$params['timeout']) {
          $connection->setTimeout($params['timeout']);
        }
        if (@$params['requires_ehlo']) {
          $connection->setRequiresEHLO(true);
        }
        break;
        
      case 'sendmail':
        $connection = new Swift_Connection_Sendmail();
        if (@$params['command']) {
          $connection->setCommand($params['command']);
        }
        if (@$params['flags']) {
          $connection->setFlags($params['flags']);
        }
        if (@$params['timeout']) {
          $connection->setTimeout($params['timeout']);
        }
        if (@$params['requires_ehlo']) {
          $connection->setRequiresEHLO(true);
        }
        break;
        
      case 'multi':
        $connection = new Swift_Connection_Multi();
        foreach ($params['connections'] as $id => $conn_info) {
          $connection->addConnection(self::getConnection($conn_info['type'], $conn_info['params']));
        }
        if (@$params['requires_ehlo']) {
          $connection->setRequiresEHLO(true);
        }
        break;
        
      case 'rotator':
        $connection = new Swift_Connection_Multi();
        foreach ($params['connections'] as $id => $conn_info) {
          $connection->addConnection(self::getConnection($conn_info['type'], $conn_info['params']));
        }
        if (@$params['requires_ehlo']) {
          $connection->setRequiresEHLO(true);
        }
        break;
    }
    
    return $connection;
  }
  
  /**
   * Load a helper (uses a wrapper for eventual later change in Symfony's API)
   *
   * @param string $helper
   */
  protected static function loadHelper($helper)
  {
    sfLoader::loadHelpers(array($helper));
  }
  
}