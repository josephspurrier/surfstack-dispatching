SurfStack Dispatching in PHP
========================

Single class that calls either a class method or a function and returns the
result in an easy to use object (second class).

This Dispatcher class is a more robust version of the dispatcher included in the
SurfStack Router class. This class includes output buffering, plenty of pre
and post calls, and error logging capabilities that work in conjunction with
the SurfStack Error Handler class. The Error Handler class is not required to
use this class and those pieces of code are gently ignored when the class is
used by itself.

The second class is called DispatcherResult and allows you to interact more
efficiently with the Dispatcher. One of the differences I noticed between other
frameworks is how the controllers will either print/echo content directly to the
screen or will return the content. Since the Dispatcher class utilizes output
buffering, no content will be written to the screen until you are ready to do
so. If you prefer for the controllers to use echo, then you can call
DispatcherResult->getEcho() to get all the content that was echoed. If you
prefer for the controller to return all the content, then you can call
DispatcherResult->getReturn() to get all the content that was returned. You can
even do a mix or a dynamic approach where if any content is echoed, then ignore
the content that is returned or vice-versa.

For this example, we'll assume you have a class called: SurfStack\Test\TestClass.
The class will have one public method called: foo.
There will also be a user-defined function and an anonymous function at the
bottom.

```php
<?php
namespace SurfStack\Test;

class TestClass
{
    function foo()
    {
        echo 'Hello world!';
    }
}

function testFunction()
{
    echo 'Hello galaxy!';
}

$anonymousFunction = function () { echo 'Hello universe!'; };
```

In your main application file (index.php), follow these instructions.

```php
<?php

// Create an instance of the Dispatcher
$dispatcher = new SurfStack\Dispatching\Dispatcher();

// Pass the class and method
$dispatcher->setRouteClassMethod('SurfStack\Test\TestClass', 'foo');

// Or pass the function
//$dispatcher->setRouteFunction('testFunction');

// Or pass the anonymous function
//$dispatcher->setRouteFunction($anonymousFunction);

// Pass the route parameters
$dispatcher->setParameters(array());

// Call the class and method logic
$content = $dispatcher->invoke();

// If the content resulted in an error
if (!$content->isOK())
{
    // Set the header
    header('HTTP/1.0 500 Internal Server Error');
    
    // Output the error
    echo 'Error: '.$content->getError();
}
// Else if no error occurred and headers have not been sent
elseif (!headers_sent())
{
    // Send the response code
    http_response_code(200);

    // Output the content
    echo $content->getEcho();

    // Or output the return
    //echo $content->getReturn();
    
    // Or send the return if it's an instance of Symfony\Component\HttpFoundation\Response
    // $content->getReturn()->send();
}
// Else if the headers have already been sent
else
{
    // Do nothing
}

```

Advanced Functionality
----------------------

The Dispatcher class supports pre and post calls. Any parameters that are
passed using $dispatcher->setParameters() will be available for use by the 
class method and function. If you are passing 2 parameters, then the method or
function should suport two parameters like: $class->method($param1, $param2).

If a class and method is passed to the Dispatcher, the following public methods,
will be called in this order (if they exist):
* $class->beforeMethod()
* (class method will be called here)
* $class->beforeRender()
* $class->render()
* $class->afterRender()

If you are using SurfStack Error Handler class and an error occurs, the error will
be passed to the following class method (if it exists) prior to calling render():
* $class->set('errorMessage', $errorMessage);

That way, if an error occurred on the previous page, you have the ability to
display the error the way you choose in your class.

Also, in order for the $errorMessage to actually be returned, you'll need to
create a global function called isAdmin() and return true.
If the function doesn't exist or returns false, the $errorMessage variable
will always be empty.

If a user-defined function or an anonymous function is passed to the Dispatcher,
the following global functions will be called in this order (if they exist):
* beforeFunction()
* afterFunction()
* renderError($errorMessage)

Again, if you are using the SurfStack Error Handler class, the error will be
available for you to display, but it will instead be passed directly to the
global renderError() function.

You can extend the Dispatcher class and then replace any method, but the main one is:
* getErrorMessage

This method handles the logging, retrieval, and resetting of the last error
message. It is designed to work with the SurfStack Error Handler class, but
you can tailor it to fit your error handling needs. You can also utilize the
method yourself by saving any message you want into $_SESSION['error'] and
handling it exactly the same. It looks like this:

```php
<?php
    /**
     * Log and return newest error
     * @return string
     */
    private function getErrorMessage()
    {
        $eMessage = '';
        
        // If an error occurred
        if (isset($_SESSION['error']))
        {
            // Log the error
            if ($this->logger)
            {
                $this->logger->error($_SESSION['error']);
            }
    
            // Only show the error message to the admin
            if (function_exists('isAdmin') && isAdmin())
            {
                // Get the error
                $eMessage = $_SESSION['error'];
            }
            
            // Clear the error
            unset($_SESSION['error']);
            
            // Clear the error backlog
            if (isset($_SESSION['errorBacklog']))
            {
                unset($_SESSION['errorBacklog']);
            }
        }
        
        // Return the message
        return $eMessage;
    }
```
