<?php

/**
 * @see Rediska_Connection_Exception
 */
require_once 'Rediska/Connection/Exception.php';

/**
 * Rediska connection
 * 
 * @author Ivan Shumkov
 * @package Rediska
 * @version 0.2.1
 * @link http://code.google.com/p/rediska
 * @licence http://opensource.org/licenses/gpl-3.0.html
 */
class Rediska_Connection
{
	const DEFAULT_HOST   = '127.0.0.1';
    const DEFAULT_PORT   = 6379;
    const DEFAULT_WEIGHT = 1;
    
    protected $_socket;

	protected $_options = array(
	   'host'     => self::DEFAULT_HOST,
	   'port'     => self::DEFAULT_PORT,
	   'weight'   => self::DEFAULT_WEIGHT,
	   'password' => null,
	);

	/**
     * Contruct Rediska connection
     * 
     * @param array $options Options (see $_options description)
     */
	public function __construct(array $options = array())
	{
		$options = array_change_key_case($options, CASE_LOWER);
        $options = array_merge($this->_options, $options);

		$this->setOptions($options);
	}

	/**
	 * Disconnect on destrcuct connection object
	 */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Set options array
     * 
     * @param array $options Options (see $_options description)
     * @return Rediska_Connection
     */
    public function setOptions(array $options)
    {
        foreach($options as $name => $value) {
            if (method_exists($this, "set$name")) {
                call_user_func(array($this, "set$name"), $value);
            } else {
                $this->setOption($name, $value);
            }
        }

        return $this;
    }

    /**
     * Set option
     * 
     * @throws Rediska_Connection_Exception
     * @param string $name Name of option
     * @param mixed $value Value of option
     * @return Rediska_Connection
     */
    public function setOption($name, $value)
    {
        if (!array_key_exists($name, $this->_options)) {
            throw new Rediska_Connection_Exception("Unknown option '$name'");
        }

        $this->_options[$name] = $value;

        return $this;
    }

    /**
     * Get option
     * 
     * @throws Rediska_Connection_Exception 
     * @param string $name Name of option
     * @return mixed
     */
    public function getOption($name)
    {
        if (!array_key_exists($name, $this->_options)) {
            throw new Rediska_Connection_Exception("Unknown option '$name'");
        }

        return $this->_options[$name];
    }

    /**
     * Connect to redis server
     * 
     * @throws Rediska_Connection_Exception
     * @return boolean
     */
    public function connect() 
    {
        if (!is_resource($this->_socket)) {
	        $this->_socket = @fsockopen($this->getHost(), $this->getPort(), $errno, $errmsg);
	
	        if (!is_resource($this->_socket)) {
	            $msg = "Can't connect to Redis server on {$this->getHost()}:{$this->getPort()}";
	            if ($errno || $errmsg) {
	                $msg .= "," . ($errno ? " error $errno" : "") . ($errmsg ? " $errmsg" : "");
	            }

	            throw new Rediska_Connection_Exception($msg);
	        }

	        if ($this->getPassword() != '') {
	        	$this->write("AUTH {$this->getPassword()}");
	        	$reply = $this->readLine();
	        }

	        return true;
        } else {
        	return false;
        }
    }

    /**
     * Write to connection stream
     * 
     * @param $string
     * @return boolean
     */
    public function write($string) 
    {
        if (is_string($string) && $string != '') {
            $string .= Rediska::EOL;
            
            $this->connect();

	        while ($string) {
	            $bytes = @fwrite($this->_socket, $string);
	
	            if ($bytes === false) {
	                $this->disconnect();
	                throw new Rediska_Connection_Exception("Can't write to socket.");
	            }
	
	            if ($bytes == 0) {
	                return true;
	            }
	
	            $string = substr($string, $bytes);
	        }

	        return true;
        } else {
        	return false;
        }
    }

    /**
     * Read line from connection stream
     * 
     * @throws Rediska_Connection_Exception
     * @return string
     */
    public function readLine()
    {
    	if (!is_resource($this->_socket)) {
            throw new Rediska_Connection_Exception("Can't read without connection to Redis server. Do connect or write first.");
    	}

    	$string = @fgets($this->_socket);

        if ($string === false) {
        	$this->disconnect();
            throw new Rediska_Connection_Exception("Can't read from socket.");
        }

        return trim($string);
    }

    /**
     * Read length bytes from connection stram
     * 
     * @throws Rediska_Connection_Exception
     * @param integer $length
     * @return boolean
     */
    public function read($length)
    {
    	$buffer = '';

    	while ($length) {
            $data = @fread($this->_socket, $length);
            if ($data === false) {
            	$this->disconnect();
                throw new Rediska_Connection_Exception("Can't read from socket.");
            }
            $length -= strlen($data);
            $buffer .= $data;
        }

        $eof = @fread($this->_socket, 2);
        if ($eof === false) {
            $this->disconnect();
            throw new Rediska_Connection_Exception("Can't read from socket.");
        }

        return $buffer;
    }

    /**
     * Disconnect
     * 
     * @return boolean
     */
    public function disconnect() 
    {
    	if (!is_null($this->_socket)) {
    		if (is_resource($this->_socket)) {
    			$this->write('QUIT');
	            @fclose($this->_socket);
	        }

	        $this->_socket = null;

	        return true;
    	} else {
    		return false;
    	}
    }

    /**
     * Get option host
     * 
     * @return string
     */
    public function getHost()
    {
        return $this->_options['host'];
    }

    /**
     * Get option port
     * 
     * @return string
     */
    public function getPort()
    {
    	return $this->_options['port'];
    }

    /**
     * Get option weight
     * 
     * @return string
     */
    public function getWeight()
    {
    	return $this->_options['weight'];
    }

    /**
     * Get option password
     * 
     * @return string
     */
    public function getPassword()
    {
        return $this->_options['password'];
    }

    public function __toString()
    {
    	return $this->getHost() . ':' . $this->getPort();
    }
}