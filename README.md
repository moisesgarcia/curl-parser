# curl-parser
This class handles the hassle to read a cURL command and retrieve the information (URL, method, headers, ...) parsed as an array

# How to use it

```php
<?php

include 'CurlParser.php';

$curl = "curl -X POST https://www.mydomain.com/mywebservice/ \
  -u 'user1:sdhgsdjgsjhgd' \
  -H 'Accept: text/xml' \
  -d 'myvariable=1112121' \
  -d 'another[one]=sample text'";


$curl_parser = new CurlParser($curl);
$curl_data = $curl_parser->getData();
```
