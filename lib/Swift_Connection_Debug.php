<?php

/**
 * Swift Debug Connection for Symfony, linked to Symfony logger
 * @package Swift_Connection
 * @author Chris Corbyn <chris@w3style.co.uk>
 */
class Swift_Connection_Debug extends Swift_ConnectionBase
{

  /**
   * Is connection alive ?
   * @var boolean
   */
  protected $alive = false;
  
  /**
   * Logger
   * @var sfLogger
   */
  protected $log = null;
  
  /**
   * 
   * @var unknown_type
   */
  protected $log_level = null;
  
  /**
   * Current command
   * @var string
   */
  protected $request = null;
  
  /**
   * The full history of all commands written
   * @var string
   */
  protected $full_command = '';
  
  /**
   * Constructor
   * @param sfLogger $log
   */
  public function __construct(sfLogger $log = null, $log_level = null)
  {
    if (is_null($log))
    {
      $log = sfContext::getInstance()->getLogger();
    }
    
    $this->log = $log;
    $this->log_level = $log->getLogLevel();
  }
  
  /**
   * Adds a log message
   * @param string $message
   * @param int $priority
   */
  protected function doLog($message, $priority = sfLogger::DEBUG)
  {
    $log_level = $this->log->getLogLevel();
    if ($log_level != $this->log_level)
    {
      $this->log->setLogLevel($this->log_level);
    }
    
    $this->log->log('{nahoMail} '.$message, $priority);
    
    if ($log_level != $this->log_level)
    {
      $this->log->setLogLevel($log_level);
    }
  }
  
  /**
   * Read a full response from the buffer (this is spoofed if running in -t mode)
   * @return string
   * @throws Swift_ConnectionException Upon failure to read
   */
  public function read()
  {
    $this->doLog('READ');
  
    switch (strtolower($this->request))
    {
      case null:    return "220 Greetings";
      case "helo": 
      case "ehlo":  return "250 hello";
      case "mail":
      case "rcpt":
      case "rset":  return "250 ok";
      case "quit":  return "221 bye";
      case "data":  return "354 go ahead";
      default:      return "250 ok";
    }
  }
  
  /**
   * Write a command to the process (leave off trailing CRLF)
   * @param string The command to send
   * @throws Swift_ConnectionException Upon failure to write
   */
  public function write($command, $end="\r\n")
  {
    $this->doLog('WRITE '.$command);
    
    $this->full_command .= $command . $end;
    $this->request = $command;
  }
  
  /**
   * Try to start the connection
   * @throws Swift_ConnectionException Upon failure to start
   */
  public function start()
  {
    $this->doLog('OPEN debug connection.');
    
    $this->alive = true;
  }
  
  /**
   * Try to close the connection
   */
  public function stop()
  {
    $this->doLog('CLOSE debug connection.');
    $this->doLog($this->full_command, sfLogger::INFO);
    
    $this->alive = false;
  }
  
  /**
   * Check if the process is still alive
   * @return boolean
   */
  public function isAlive()
  {
    return $this->alive;
  }
  
  /**
   * 
   * @param string $class
   */
  public function setLogClass($class)
  {
    if (!class_exists($class))
    {
      throw new Exception('Class "'.$class.'" not found');
    }
    
    $this->log = new $class();
  }
  
  /**
   * 
   * @param int $log_level
   */
  public function setLogLevel($log_level)
  {
    $this->log_level = $log_level;
  }
  
}
