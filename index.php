<?php
  
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *");
    header('Access-Control-Allow-Credentials: true');
    //magic code thank you for removing this error
    // The 'Access-Control-Allow-Origin' header contains multiple values '*, *', but only one is allowed.
    header_remove('Access-Control-Allow-Credentials');
    header_remove('Access-Control-Allow-Origin');
    //end magic code
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
    }
    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])){
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])){
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
}


require 'config.php';
require 'Slim/Slim.php';

\Slim\Slim::registerAutoloader();
$app = new \Slim\Slim();

$app->post('/login','login'); /* User login */
$app->post('/signup','signup'); /* User Signup  */
$app->get('/getFeed','getFeed'); /* User Feeds  */
$app->post('/feed','feed'); /* User Feeds  */
$app->post('/feedUpdate','feedUpdate'); /* User Feeds  */
$app->post('/feedDelete','feedDelete'); /* User Feeds  */
$app->post('/getImages', 'getImages');
$app->post('/pay','makePayment'); /* Make Payment */
$app->post('/account','createAccount'); /*Use account number to link with created user*/
$app->post('/transactions','getTransactions'); /*Use user_id to*/



$app->run();

/************************* USER LOGIN *************************************/
/* ### User login ### */
$con = mysqli_connect ("localhost","root","","micropay");

function getTransactions(){
    $postdata = file_get_contents("php://input");
    if (isset($postdata)) {
        $request = json_decode($postdata);
        $clientID = $request->clientID;

        if ($clientID != "") {
            
        $userData ='';
        $sql = "SELECT * FROM transactions WHERE client_id ='$clientID'";
        $con = mysqli_connect ("localhost","root","","micropay");
        $result_set=mysqli_query($con,$sql);
        $row=mysqli_fetch_array($result_set);
        //Collective array for total database dataset
        $json_array =array();
        while ($row=mysqli_fetch_array($result_set)){
                        array_push($json_array, array (
                                  'ID' => $row['trans_ID'],
                                  'client_Id' => $row['client_id'],
                                  'merch_Id' => $row['client_id'],
                                  'merch_name' => $row['merch_name'],
                                  'client_name' => $row['client_name'],
                                  'amount' => $row['amount'],
                                  'date_created' => $row['date_created'],
                                  'status' => $row['status'],

                        ));
//    $json_array[] =$row;
                    }
                    $json = array ("transactions"=> $json_array);
                    echo json_encode($json, JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK);

                        
    


         
        }
        else{
            echo 'no client id';
        }
       
      
    
    }
    else {
        echo "Request Failed";
    }
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());   

}

function createAccount(){

    $postdata = file_get_contents("php://input");
    if (isset($postdata)) {
        $request = json_decode($postdata);
        $user_id = $request->user_id;
        $account_number = $request->account;
        if ($user_id != "") {
           
        }
        else {
            echo '{"ResponseData":{"success":"false","reason":"user_id not supplied"}}';           
        }
    }
    else {
        echo '{"ResponseData":{"success":"false","reason":"user_id not supplied"}}';  
    }
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    try{

        //check if entry already exists in database
         $check ="SELECT * FROM accounts WHERE user_id='$user_id'";
         $con = mysqli_connect ("localhost","root","","micropay");
         $check_result = mysqli_query($con,$check);
         $row_check=mysqli_fetch_array($check_result);
         if(strlen($row_check['user_id'])> 0){
             //if user already exists sent error message
             echo $row_check['user_id'];
        
         }
         else{
             //if user doesnt exist in database create new user
             try {
                 $con = mysqli_connect ("localhost","root","","micropay");
                 $sql = "INSERT INTO accounts (user_id,DateCreated,AccountNumber) VALUES ('$user_id','','$account_number')";
                 if($result_set=mysqli_query($con,$sql)){
                     echo '{"responseData": {"success":"true"}}';
                 }
                 else{
                     echo '{"responseData": {"success":"false"}}';
                 }


             }
             catch(PDOException $e) {
                 echo '{"error":{"text":'. $e->getMessage() .'}}';
             }
         }


    }
    catch(Exception $e) {
        echo 'Message: ' .$e->getMessage();
      }


}
function makePayment(){
  
    $postdata = file_get_contents("php://input");
    if (isset($postdata)) {
        $request = json_decode($postdata);

        $clientID = $request->clientID;
        $amount = $request->amount;
        $merchant=$request->merchantID;
       
        if ($clientID != "") {
        
            $sql="SELECT * FROM user_client INNER JOIN accounts ON user_client.user_id=accounts.user_id WHERE user_client.user_id=$clientID";
            $con = mysqli_connect ("localhost","root","","micropay");
            
            $result_set=mysqli_query($con,$sql);
            $row=mysqli_fetch_array($result_set);
            //Collective array for total database dataset
            $json_array =array();
            $balance = $row['Balance'];
            if($balance > $amount){

                $updated_balance = $balance - $amount;
              
                $con = mysqli_connect ("localhost","root","","micropay");
                //update transaction records
                $date = date();
                $insert = "INSERT INTO transactions(client_id,merch_id,merch_name,client_name,amount,date_created,status)
                            VALUES('$clientID','$merchant','bj','kj','$amount','$date','455')";
                            $insert_result=mysqli_query($con,$insert);
                            $row_insert=mysqli_fetch_array($insert_result);
                            $new_balance =$row_insert['merch_id'];
                            // $row_balance=mysqli_fetch_array($result_set_balance);
                            // $new_balance =$row_balance['Balance'];
                            
                $update_query = "UPDATE accounts SET Balance=$updated_balance WHERE user_id=$clientID";
                $result_set_balance=mysqli_query($con,$update_query);
                $row_balance=mysqli_fetch_array($result_set_balance);
                $new_balance =$row_balance['Balance'];
                echo $new_balance;
                
                echo '{"ResponseData":{"confirmed":"True","new_balance":'.$updated_balance.'}}';

            }
            else{
                echo '{"ResponseData":{"confirmed":"false"}}';
            }
            
            
        }
        else {
            echo "Empty client_id parameter!";
        }
    }
    else {
        echo "Not called properly with username parameter!";
    }
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    




}


function login() {
     
    $postdata = file_get_contents("php://input");
    if (isset($postdata)) {
        $request = json_decode($postdata);
        $username = $request->username;
 
        if ($username != "") {
            // echo "Server returns: " . $username;
           
        }
        else {
            echo "Empty username parameter!";
        }
    }
    else {
        echo "Not called properly with username parameter!";
    }
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());

    try {

        $db = getDB();
        $userData ='';
        $sql = "SELECT user_client.user_id, name, email, username,Balance FROM user_client INNER JOIN accounts ON user_client.user_id=accounts.user_id
         WHERE (username=:username or email=:username) and password=:password ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam("username", $data->username, PDO::PARAM_STR);
        $password=hash('sha256',$data->password);
        $stmt->bindParam("password", $password, PDO::PARAM_STR);
        $stmt->execute();
        $mainCount=$stmt->rowCount();
        $userData = $stmt->fetch(PDO::FETCH_OBJ);
        // echo $mainCount;

        if(!empty($userData))
        {
            $user_id=$userData->user_id;
            $userData->token = apiToken($user_id);
        }

        $db = null;
         if($userData){
               $userData = json_encode($userData);
                echo '{"userData": ' .$userData . '}';
            } else {
               echo '{"error":{"text":"Bad request wrong username and password"}}';
            }


    }
    catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}


/* ### User registration ### */
function signup() {
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $email=$data->email;
    $name=$data->name;
    $username=$data->username;
    $password=$data->password;

    try {

        // $username_check = preg_match('~^[A-Za-z0-9_]{3,20}$~i', $username);
        // $email_check = preg_match('~^[a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.([a-zA-Z]{2,4})$~i', $email);
        // $password_check = preg_match('~^[A-Za-z0-9!@#$%^&*()_]{6,20}$~i', $password);
        
        // echo $email_check.'<br/>'.$email;

        if ( !empty($username) )
        {
            
            $db = getDB();
            $userData = '';
            $sql = "SELECT user_id FROM user_client WHERE username=:username or email=:email";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("username", $username,PDO::PARAM_STR);
            $stmt->bindParam("email", $email,PDO::PARAM_STR);
            $stmt->execute();
            $mainCount=$stmt->rowCount();
            $created=time();
            if($mainCount==0)
            {

                /*Inserting user values*/
                $sql1="INSERT INTO user_client(username,password,email,name)VALUES(:username,:password,:email,:name)";
                $stmt1 = $db->prepare($sql1);
                $stmt1->bindParam("username", $username,PDO::PARAM_STR);
                $password=hash('sha256',$data->password);
                $stmt1->bindParam("password", $password,PDO::PARAM_STR);
                $stmt1->bindParam("email", $email,PDO::PARAM_STR);
                $stmt1->bindParam("name", $name,PDO::PARAM_STR);
                $stmt1->execute();

                $userData=internalUserDetails($email);

            }
            else{
                echo '{"error":{"text":"User exists"}}';
            }


            $db = null;


            if($userData){
               $userData = json_encode($userData);
               $userID = $userData->user_id;
               echo $userID;
                echo '{"userData": ' .$userData . '}';

            } else {
               echo '{"error":{"text":"Enter valid data"}}';
            }


        }
        else{
            echo '{"error":{"text":"You have entered invalid data"}}';
        }
    }
    catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

function email() {
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $email=$data->email;

    try {

        $email_check = preg_match('~^[a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+\.([a-zA-Z]{2,4})$~i', $email);

        if (strlen(trim($email))>0 && $email_check>0)
        {
            $db = getDB();
            $userData = '';
            $sql = "SELECT user_id FROM emailUsers WHERE email=:email";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("email", $email,PDO::PARAM_STR);
            $stmt->execute();
            $mainCount=$stmt->rowCount();
            $created=time();
            if($mainCount==0)
            {

                /*Inserting user values*/
                $sql1="INSERT INTO emailUsers(email)VALUES(:email)";
                $stmt1 = $db->prepare($sql1);
                $stmt1->bindParam("email", $email,PDO::PARAM_STR);
                $stmt1->execute();


            }
            $userData=internalEmailDetails($email);
            $db = null;
            if($userData){
               $userData = json_encode($userData);
                echo '{"userData": ' .$userData . '}';
            } else {
               echo '{"error":{"text":"Enter valid dataaaa"}}';
            }
        }
        else{
            echo '{"error":{"text":"Enter valid data"}}';
        }
    }

    catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}


/* ### internal Username Details ### */
function internalUserDetails($input) {

    try {
        $db = getDB();
        $sql = "SELECT user_id, name, email, username FROM user_client WHERE username=:input or email=:input";
        $stmt = $db->prepare($sql);
        $stmt->bindParam("input", $input,PDO::PARAM_STR);
        $stmt->execute();
        $usernameDetails = $stmt->fetch(PDO::FETCH_OBJ);
        $usernameDetails->token = apiToken($usernameDetails->user_id);
        $db = null;
        return $usernameDetails;

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}

function getFeed(){


    try {

        if(1){
            $feedData = '';
            $db = getDB();

                $sql = "SELECT * FROM feed  ORDER BY feed_id DESC LIMIT 15";
                $stmt = $db->prepare($sql);
                $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
                $stmt->bindParam("lastCreated", $lastCreated, PDO::PARAM_STR);

            $stmt->execute();
            $feedData = $stmt->fetchAll(PDO::FETCH_OBJ);

            $db = null;

            if($feedData)
            echo '{"feedData": ' . json_encode($feedData) . '}';
            else
            echo '{"feedData": ""}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}

function feed(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $lastCreated = $data->lastCreated;
    $systemToken=apiToken($user_id);

    try {

        if($systemToken == $token){
            $feedData = '';
            $db = getDB();
            if($lastCreated){
                $sql = "SELECT * FROM feed WHERE user_id_fk=:user_id AND created < :lastCreated ORDER BY feed_id DESC LIMIT 5";
                $stmt = $db->prepare($sql);
                $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
                $stmt->bindParam("lastCreated", $lastCreated, PDO::PARAM_STR);
            }
            else{
                $sql = "SELECT * FROM feed WHERE user_id_fk=:user_id ORDER BY feed_id DESC LIMIT 5";
                $stmt = $db->prepare($sql);
                $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $feedData = $stmt->fetchAll(PDO::FETCH_OBJ);

            $db = null;

            if($feedData)
            echo '{"feedData": ' . json_encode($feedData) . '}';
            else
            echo '{"feedData": ""}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}

function feedUpdate(){

    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $feed=$data->feed;

    $systemToken=apiToken($user_id);

    try {

        if($systemToken == $token){


            $feedData = '';
            $db = getDB();
            $sql = "INSERT INTO feed ( feed, created, user_id_fk) VALUES (:feed,:created,:user_id)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("feed", $feed, PDO::PARAM_STR);
            $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $created = time();
            $stmt->bindParam("created", $created, PDO::PARAM_INT);
            $stmt->execute();



            $sql1 = "SELECT * FROM feed WHERE user_id_fk=:user_id ORDER BY feed_id DESC LIMIT 1";
            $stmt1 = $db->prepare($sql1);
            $stmt1->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $stmt1->execute();
            $feedData = $stmt1->fetch(PDO::FETCH_OBJ);


            $db = null;
            echo '{"feedData": ' . json_encode($feedData) . '}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}



function feedDelete(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $feed_id=$data->feed_id;

    $systemToken=apiToken($user_id);

    try {

        if($systemToken == $token){
            $feedData = '';
            $db = getDB();
            $sql = "Delete FROM feed WHERE user_id_fk=:user_id AND feed_id=:feed_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam("feed_id", $feed_id, PDO::PARAM_INT);
            $stmt->execute();


            $db = null;
            echo '{"success":{"text":"Feed deleted"}}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }

    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }

}
$app->post('/userImage','userImage'); /* User Details */
function userImage(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;
    $imageB64=$data->imageB64;
    $systemToken=apiToken($user_id);
    try {
        if(1){
            $db = getDB();
            $sql = "INSERT INTO imagesData(b64,user_id_fk) VALUES(:b64,:user_id)";
            $stmt = $db->prepare($sql);
            $stmt->bindParam("user_id", $user_id, PDO::PARAM_INT);
            $stmt->bindParam("b64", $imageB64, PDO::PARAM_STR);
            $stmt->execute();
            $db = null;
            echo '{"success":{"status":"uploaded"}}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}

$app->post('/getImages', 'getImages');
function getImages(){
    $request = \Slim\Slim::getInstance()->request();
    $data = json_decode($request->getBody());
    $user_id=$data->user_id;
    $token=$data->token;

    $systemToken=apiToken($user_id);
    try {
        if(1){
            $db = getDB();
            $sql = "SELECT b64 FROM imagesData";
            $stmt = $db->prepare($sql);

            $stmt->execute();
            $imageData = $stmt->fetchAll(PDO::FETCH_OBJ);
            $db = null;
            echo '{"imageData": ' . json_encode($imageData) . '}';
        } else{
            echo '{"error":{"text":"No access"}}';
        }
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
?>
