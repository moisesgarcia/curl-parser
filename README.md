# curl-parser
This class handles the hassle to read a cURL command and retrieve the information (URL, method, headers, ...) parsed as an array

# How to use it
php

include 'CurlParser.php';

$curl = "curl -X POST https://www.mydomain.com/mywebservice/ \
  -u 'user1:sdhgsdjgsjhgd' \
  -H \'Accept: text/xml\' \
  -d \'tracker[tracking_code]=9400110898825022579493\' \
  -d \'tracker[carrier]=USPS\'';


$curl_parser = new CurlParser($curl);
$curl_parser->getData();
`
