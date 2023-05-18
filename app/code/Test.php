<?php



// Variable assignment
$firstName = "John";
$lastName = "Doe";
$age = 25;

// Printing the variable values
echo "Name: " . $firstName . " " . $lastName . "<br>";
echo "Age: " . $age;
error_log("The value of the variable is: " . $firstName);
// $writer = new \Zend_Log_Writer_Stream(BP . '/custom.log');
//      $logger = new \Zend_Log();
//      $logger->addWriter($writer);
    
//      $logger->info("**************************************************");
//      $logger->info($firstName);
//      $logger->info($lastName);
//      $logger->info($age);
    
//      $logger->info("**************************************************");

class Test {
    
   
    
    // $writer = new \Laminas\Log\Writer\Stream(BP . '/var/log/custom.log');
    // $logger = new \Laminas\Log\Logger();
    // $logger->addWriter($writer);
    
    // $logger has log methods for different priorities like warning, notice, error, etc.
    // $logger->info('Hello World!');
    public function TestLog($firstName, $lastName, $age ) {
     $writer = new \Zend_Log_Writer_Stream(BP . '/custom.log');
     $logger = new \Zend_Log();
     $logger->addWriter($writer);
    
     $logger->info("**************************************************");
     $logger->info($firstName);
     $logger->info($lastName);
     $logger->info($age);
    
     $logger->info("**************************************************");

    
    }
      
  
     
}


// Creating an object
$obj = new Test();

// Calling a method on the object
$obj->TestLog($firstName, $lastName,  $age);
// $obj->TestLog("Rahul", "Sharma",  28);

 ?>