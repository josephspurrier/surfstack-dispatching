SurfStack Dispatching in PHP
========================

Single class that calls either a class method or a function and returns the
result in an easy to use object (second class).

This Dispatcher class is a more robust version of the dispatcher included in the
SurfStack Router class. This class includes output buffering and pre/post calls.

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

If a user-defined function or an anonymous function is passed to the Dispatcher,
the following global functions will be called in this order (if they exist):
* beforeFunction()
* afterFunction()

To install using composer, use the code from the Wiki page [Composer Wiki page](../../wiki/Composer).
