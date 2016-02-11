$(function(){
    console.log("On My Way!");

    // Attach the validation engine to the form
    $("#form-sm, #form-lg").validationEngine('attach', {
        promptPosition: "topRight"
    });

    // On Walking / Driving button click - handle
    $("input[type=button]").click(function(){

        $("#record-slide, .btn").css("cursor","wait"); // Show waiting cursor
        $("input[type=button]").prop("disabled", true); // Disables button
        var mode = $(this).val().toLowerCase(); // Set mode variable to either driving or walking
        var formData = { mode : mode }; // Make the formData object with mode set

        // If large form is visible (i.e. on a desktop) than use that form, if not then use small form (mobile etc)
        var form = $("#large-form").css("display") !== "none" ? $("#form-lg") : $("#form-sm");
        // Serialize all the inputs into an Object rather than Array
        var serializedInputs = form.serializeArray().map(function(x){formData[x.name] = x.value;});
        var error;
        if (navigator.geolocation) { // Check for HTML5 Geolocation
            navigator.geolocation.getCurrentPosition(
                function(position) { // Callback
                    console.log(position);
                    if (!position.coords.longitude) {
                        error = "Sorry, we were unable to determine your current location.";
                        handleError(error);
                    }
                    else {
                        formData.userX = position.coords.longitude; // Set the users starting coordinates
                        formData.userY = position.coords.latitude;
                        sendRequest(formData);
                    }

                }, function(browserError) {
                    console.log(browserError);

                    if (browserError.PERMISSION_DENIED) {
                        error = "Looks like you have declined geolocation, which this app needs to work!";
                        handleError(error);
                    }
                    else if (browserError.POSITION_UNAVAILABLE) {
                        error = "Sorry, we were unable to determine your location.";
                        handleError(error);
                    }
                    else if (browserError.POSITION_TIMEOUT) {
                        error = "Sorry, the app timed out whilst trying to determine your location.";
                        handleError(error);
                    }

                } // End of browser error block
            ); // End of getCurrentPosition
        } // End of browser geolocation check block
        else {
            error = "Geolocation is not enabled in your browser";
            handleError(error);
        }
    });

    function sendRequest(formData) {
        var href = document.location.href; // Get the URL
        var proxy = href.substr(0, href.indexOf('wmt') + 3) + "/proxy?";  // Get proxy url

        // This is the server side PHP script which returns the route information and sends the text (onmyway.php)
        var serviceUrl = "...";

        $.ajax({
            method: "POST", // NOT GET!
            url: proxy + serviceUrl, // Make use of the proxy
            data: formData,
            dataType: "json",
            success: function(result) {
                try {
                    if (!result.error && result.routeLength>0) {
                        var travelTime = result.routeWalkTime !== "" ?
                                         convertToHours(result.routeWalkTime) :
                                         convertToHours(result.routeDriveTime);

                        var templateVars = {
                                            name : formData.userName,
                                            mobileNumber : formData.phoneNum,
                                            mode : formData.mode,
                                            travelTime : travelTime,
                                            travelDistance : String(Number(result.routeLength).toFixed(2))
                                        };

                        var source   = $("#response-template").html();
                        var template = Handlebars.compile(source); // Returns a function
                        var html     = template(templateVars);

                        $("#content").html(html); // Insert the completed template
                        $("#record-slide, .btn").css("cursor","default"); //
                        $("input[type=button]").prop("disabled", false);

                        require([
                        "esri/map",
                        "esri/geometry/Polyline",
                        "esri/Color",
                        "esri/symbols/SimpleLineSymbol",
                        "esri/graphic",
												"esri/graphicsUtils",
                        "dojo/on",
                        "dojo/domReady!"
                        ], function(Map, Polyline, Color, SimpleLineSymbol, Graphic, graphicsUtils, on) {

                            // Create the map with center at the user's X and Y coordinate
                            var map = new Map("mapdesktop", {
                                center  : [formData.userX, formData.userY],
                                zoom    : 11,
                                basemap : "dark-gray"
                            });

                            // When the map loads, push in the route symbol
                            map.on("load", function(){
                                console.log("Adding line to map");
                                var polyline = JSON.parse(result.routePolyline);
                                var route = { "paths": polyline, "spatialReference": {"wkid":4326} };
                                var routeGeometry = new Polyline(route); // Take the route and generate Polyline
                                var routeSymbol = new SimpleLineSymbol( SimpleLineSymbol.STYLE_LONGDASH, new Color([255, 222, 0]), 3);
                                var routeGraphic = new Graphic(routeGeometry, routeSymbol); // Create route graphic
                                map.graphics.add(routeGraphic); // Add route to map
																//Set map extent to encompass the whole route
																map.setExtent(routeGeometry.getExtent());
                            });

                        }); // End of require block
                    }
                    else {
											if (result.error){
												handleError(result.error);
											}
											else {
												handleError("Sorry, we couldn't locate your postcode.")
											}
                        
                    }

                } // End of try

                catch(error) {
                    handleError(error);
                } // End of catch

            } // End of success
        });
    }

    function handleError(error){
        // Handle errors thrown and provide a completed error message template
        console.log("Error: ", error);
        var source   = $("#error-template").html();
        var template = Handlebars.compile(source);
        var html     = template({error : error});
        $("#content").html(html);
        $("#record-slide, .btn").css("cursor","default");
    }

    function convertToHours(minutes) {
        // Convert minutes into hours and minutes
        var hours = Math.floor(Number(minutes) / 60);
        var mins = minutes % 60;
        if (hours === 0) {
            return Number(minutes).toFixed(0) + " minutes.";
        }
        else {
            return hours + " hours and " + mins.toFixed(0) + " minutes";
        }
    }

    $(window).resize(function() {
        $('.content').height($(window).height());
    });

    $(window).trigger('resize');

}); // End of IIFE
