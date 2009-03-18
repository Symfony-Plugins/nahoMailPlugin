<?php

// Configuration needed to run this unit tests
$app = 'frontend';
$env = 'dev';
$options = array(
  'connection' => array('type' => 'native'),
  'from'       => 'your@mail.com',
);

// Include required files
include dirname(__FILE__).'/../../../../test/bootstrap/unit.php' ;
require_once dirname(__FILE__).'/../../lib/nahoMail.php';
require_once dirname(__FILE__).'/../../lib/vendor/swift/Swift.php';
// Application configuration
require_once(dirname(__FILE__).'/../../../../config/ProjectConfiguration.class.php');
$configuration = ProjectConfiguration::getApplicationConfiguration($app, $env, true);
sfContext::createInstance($configuration);

// Start tests
$t = new lime_test(6, new lime_output_color());

// Most simple call : body as simple string
$t->ok(nahoMail::send('My Subject', 'My Body', 'his@mail.com', $options), 'Body as simple string');

// Most simple call : body as simple string and recipient with a name as a string
$t->ok(nahoMail::send('My Subject', 'My Body', 'him <his@mail.com>', $options), 'Body as simple string and recipient with a name as a string');

// Most simple call : body as simple string and recipient with a name as an array
$t->ok(nahoMail::send('My Subject', 'My Body', array('him', 'his@mail.com'), $options), 'Body as simple string and recipient with a name as an array');

// A bit more complex call : body as an array
$t->ok(nahoMail::send('My Subject', array('content' => 'My Body'), 'his@mail.com', $options), 'Body as array content => body');

// Body get embedded image in the body
$options2 = array_merge($options, array(
  'embed-images' => array(
    'one' => dirname(__FILE__).'/one.gif',
  ),
));
$t->ok(nahoMail::send('My Subject', array(
  'content' => '<img src="%%IMG_one%%" alt="My Body">', 
  'content-type' => 'text/html'), 
'his@mail.com', $options2), 'Embedded images');

// Multipart mail
$body1 = 'My Body 1';
$body2 = 'My Body 2';
$body3 = '<strong>My Body 3</strong>';
$options2 = array_merge($options, array(
  'parts' => array(
    array('content' => $body2),
    array('content' => $body3, 'content-type' => 'text/html'),
  ),
));
$t->ok(nahoMail::send('My Subject', 'My Body', 'his@mail.com', $options2), 'Multipart message');
