#**On My Way**

#About

On My Way is a web application that allows you to send a text message to somebody to let them know how far away you are and how long it will take you to arrive. The application utilises HTML5 Geolocation, Esri's ArcGIS API for JavaScript and Twilio's text messaging services. 

#Sample
A live sample of the application is available [here] (http://bit.ly/1XciNq1)

#Configuring
You will need to set up a couple of things to get up and running:
- Download and configure the [Esri Resource Proxy] (https://github.com/Esri/resource-proxy)
- Point 'proxy' to the location of your proxy in onmyway.js (line 63)
- Host onmyway.php and auth.php on a PHP server
- Update 'serviceUrl' in onmyway.js to point towards your php file (line 66)
- Setup auth.php with your Twilio and ArcGIS credentials 

#Issues

Find a bug or want to request a new feature? Please let us know by submitting an issue.

#Licensing

Copyright 2016 ESRI (UK) Limited

Licensed under the Apache License, Version 2.0 (the "License"); you may not use this file except in compliance with the License. You may obtain a copy of the License at

http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software distributed under the License is distributed on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the License for the specific language governing permissions and limitations under the Licence.
