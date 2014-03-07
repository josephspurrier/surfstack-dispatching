<?php

/**
 * This file is part of the SurfStack package.
 *
 * @package SurfStack
 * @copyright Copyright (C) Joseph Spurrier. All rights reserved.
 * @author Joseph Spurrier (http://josephspurrier.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0.html
 */

namespace SurfStack\Dispatching;

/**
 * Dispatcher Result
 *
 * Set and Get the ECHO content, RETURN object, and ERROR message
 */
class DispatcherResult
{
    /**
     * Echo content
     * @var string
     */
    private $strEcho = '';
    
    /**
     * Return object
     * @var object
     */
    private $strReturn = null;
    
    /**
     * Error message
     * @var string
     */
    private $strError = '';
    
    /**
     * Set the echo content
     * @param string $str
     */
    function setEcho($str)
    {
        $this->strEcho = $str;
    }
    
    /**
     * Set the return object
     * @param object $obj
     */
    function setReturn($obj)
    {
        $this->strReturn = $obj;
    }
    
    /**
     * Set the error message
     * @param string $str
     */
    function setError($str)
    {
        $this->strError = $str;
    }

    /**
     * Get the echo content
     * @return string
     */
    function getEcho()
    {
        return $this->strEcho;
    }

    /**
     * Get the return object
     * @return object
     */
    function getReturn()
    {
        return $this->strReturn;
    }
    
    /**
     * Get the error message
     * @return string
     */
    function getError()
    {
        return $this->strError;
    }
    
    /**
     * Did any errors occur?
     * @return boolean
     */
    function isOK()
    {
        if ($this->strError == '')
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}

?>