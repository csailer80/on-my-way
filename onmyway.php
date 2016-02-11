<?php
    // Error logging
    // error_reporting(E_ALL);  // Turn off error logging for production
    // ini_set("display_errors", 1);

    function getData() {
        try {

            // Require relevant dependencies
            require('twilio/Twilio.php');    // The Twilio PHP Library
            require('auth.php');             // Set Auth variables

            // Get postcode and phone number
            $userName = (string)$_POST["userName"];
            $userX    = (string)$_POST["userX"];
            $userY    = (string)$_POST["userY"];
            $postCode = (string)$_POST["postCode"];
            $phoneNum = (string)$_POST["phoneNum"];
            $mode     = (string)$_POST["mode"];

            $invalidNumbers = array("999", "911", "+999", "+911", "112", "101", "111"); // Numbers we don't want to be accepted

            // Check that they're all valid
            if ($userX && $userY && $postCode && $phoneNum && $mode && in_array($phoneNum, $invalidNumbers) == false && in_array("null", $returnData) == false) {

                //ArcGIS Token URL and Token Retrieval
                $token = request('https://www.arcgis.com/sharing/rest/oauth2/token',
                                array(
                                    "client_id"     => $arcgisAppId,       // Stored in auth.php
                                    "client_secret" => $arcgisAppSecret,   // Stored in auth.php
                                    "expiration"    => "1440",
                                    "grant_type"    => "client_credentials",
                                    "f"             => "json"
                                )
                        );
                // Set out access token
                $access_token = $token->access_token;

                // Geocode users Postcode
                $geocodeResp = request("http://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/find",
                                       array(
                                            "text" => $postCode,
                                            "f"    => "json"
                                        )
                                );

                // Set target location (x, y)
                $targetX = (string)$geocodeResp->locations[0]->feature->geometry->x;
                $targetY = (string)$geocodeResp->locations[0]->feature->geometry->y;
                $stops   = $userX . "," . $userY . ";" . $targetX . "," . $targetY;
                // http://resources.arcgis.com/en/help/arcgis-rest-api/#/Route_service_with_synchronous_execution/02r300000036000000/

                // Return the data
                $data        = getReturnData($mode, $stops, $userName, $access_token);
                $returnData  = $data["returnData"];
                $messageText = $data["message"];

                // TWILIO CODE --------------
                // We store $twilioSid and $twilioToken in auth.php
                // Send text message to your friend
                if ($messageText && $returnData) {

                    $sid          =  $twilioSid;    // Your Account SID from www.twilio.com/user/account
                    $token        =  $twilioToken;  // Your Auth Token from www.twilio.com/user/account
                    $client       = new Services_Twilio($sid, $token);
                    $twilioNumber = ''; // Your twilio phone number

                    $message = $client->account->messages->sendMessage(
                        $twilioNumber, // Send from a valid Twilio number
                        $phoneNum,     // Text this number!
                        $messageText   // The final message to send
                    );

                }

            }
            else if (in_array($phoneNum, $invalidNumbers) == true) {
                throw new Exception('Invalid number; this number is blacklisted');
            }
            else {
                throw new Exception('Something was wrong with the input variables');
            }

            // Return Data
            return $returnData;

        } catch (Exception $e) {
            // We return a useful error message if something goes wrong.
            $error        = $e->getMessage();
            $errorMessage = json_encode(array( "error" => $error ));

            return $errorMessage;
        }

    }

    function request($request_url, $request_parameters) {

        // Do a cURL POST Request; takes a URL and plain Array of name value pairs
        try {
            $curl = curl_init();
            // Set some options - we are passing in a useragent too here
            curl_setopt_array($curl, array(
                CURLOPT_URL => $request_url,
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => http_build_query($request_parameters),
                CURLOPT_HEADER => 0,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FAILONERROR => 1,
                CURLOPT_CAINFO => '..\..\cacert.pem',
                CURLOPT_SSL_VERIFYPEER => true
            ));

            // Send the request & assign response to $resp
            $resp = curl_exec($curl);
            if (FALSE === $resp) {
                throw new Exception(curl_error($curl), curl_errno($curl));
            }

            else {
                $json = json_decode($resp);
                return $json;
            }
            curl_close($curl); // Close request
        }

        catch(Exception $e) {

           trigger_error(sprintf(
               'Curl failed with error #%d: %s',
               $e->getCode(), $e->getMessage()),
               E_USER_ERROR);

        }

    }

    function getReturnData($mode, $stops, $userName, $token){

        // All the logic responsible for getting the message and return object
        $routingUrl     = "http://route.arcgis.com/arcgis/rest/services/World/Route/NAServer/Route_World/solve";
        $routeWalkTime  = "";
        $routeDriveTime = "";

        if ($mode == "driving") {
            $data           = getRouteData($stops, $token, "TravelTime", $routingUrl, $userName, $mode); // TravelTime NOT DriveTime (???)
            $routeDriveTime = $data["routeTime"]; // getRouteData returns associative array
        }
        else if ($mode == "walking") {
            $data          = getRouteData($stops, $token, "WalkTime", $routingUrl, $userName, $mode);
            $routeWalkTime = $data["routeTime"];  // getRouteData returns associative array
        }

        $returnData = json_encode(array(
            "routeLength"    => $data["routeLength"],
            "routeWalkTime"  => $routeWalkTime,
            "routeDriveTime" => $routeDriveTime,
            "routePolyline"  => $data["routePolyline"]
        ));

        return array(
                    "message"    => $data["messageText"],
                    "returnData" => $returnData
                );

    }

    function getRouteData($stops, $token, $modeAttribute, $routingUrl, $userName, $mode) {

        $routeParameters = setRouteParameters($stops, $token, $modeAttribute);
        $route           = request($routingUrl, $routeParameters);
        $routeTime       = (string)$route->directions[0]->summary->totalTime;
        $routeLength     = (string)$route->directions[0]->summary->totalLength;

        return array(
            "routeParameters" => $routeParameters,
            "route"           => $route,
            "routeTime"       => $routeTime,
            "routeLength"     => $routeLength, //Length in KM
            "messageText"     => setMessageText($userName, $routeLength, $routeTime, $mode),
            "routePolyline"   => json_encode($route->routes->features[0]->geometry->paths)
        );
    }

    function setRouteParameters($stops, $token, $modeAttribute){

        // Setup the route parameters for the routing API
        return array(
            "stops"                       => $stops,
            "token"                       => $token,
            "directionsTimeAttributeName" => $modeAttribute,
            "f"                           => "json"
        );

    } 

    function convertToHours($minutes) {

        // Convert minutes into hours and minutes
        $hours = floor($minutes / 60);
        $mins = floor($minutes % 60);
        if ($hours == 0) {
            return (string)round($minutes) . " minutes";
        }
        else {
            return (string)$hours . " hours and " . (string)$mins . " minutes";
        }

    }

		function formatDistance($distance){
			
			// Format the distance by rounding and dealing with sub 1 mile results
			if ($distance < 1){
				
				return "less than 1 mile away.";
				
			} else {
				
				return (string)round($distance, 0, PHP_ROUND_HALF_UP) . " miles away.";
				
			}
		}

    function setMessageText($userName, $routeLength, $routeTime, $mode) {

        return "Your friend $userName is on their way! They are " . $mode . " and are currently " . formatDistance($routeLength) .
               " They will be with you in about " . convertToHours($routeTime);

    }

    echo getData();

?>
