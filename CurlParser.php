<?php

/**
 * CurlParser
 * This class handles the hassle to read a cURL command and retrieve the information (URL, method, headers, ...) parsed as an array
 *
 * Created by Moises Garcia <moises@moisesgarcia.es>.
 * v.1.0
 */
class CurlParser
{

    public $url = '';
    public $method = 'GET';
    public $headers = array();
    public $data = '';
    public $files = array();
    public $basic_auth = array();
    public $content_type = '';

    private $curl_array = array();
    private $cursor = 0;
    private $input = '';
    private $input_len = 0;

    /**
     * @property $bool_options
     * List of curl flags that are boolean typed; this helps with parsing
     * a command like `curl -abc value` to know whether 'value' belongs to '-c'
     * or is just a positional argument instead.
     */
    private $bool_options = array('#', 'progress-bar', '-', 'next', '0', 'http1.0', 'http1.1', 'http2',
        'no-npn', 'no-alpn', '1', 'tlsv1', '2', 'sslv2', '3', 'sslv3', '4', 'ipv4', '6', 'ipv6',
        'a', 'append', 'anyauth', 'B', 'use-ascii', 'basic', 'compressed', 'create-dirs',
        'crlf', 'digest', 'disable-eprt', 'disable-epsv', 'environment', 'cert-status',
        'false-start', 'f', 'fail', 'ftp-create-dirs', 'ftp-pasv', 'ftp-skip-pasv-ip',
        'ftp-pret', 'ftp-ssl-ccc', 'ftp-ssl-control', 'g', 'globoff', 'G', 'get',
        'ignore-content-length', 'i', 'include', 'I', 'head', 'j', 'junk-session-cookies',
        'J', 'remote-header-name', 'k', 'insecure', 'l', 'list-only', 'L', 'location',
        'location-trusted', 'metalink', 'n', 'netrc', 'N', 'no-buffer', 'netrc-file',
        'netrc-optional', 'negotiate', 'no-keepalive', 'no-sessionid', 'ntlm', 'O',
        'remote-name', 'oauth2-bearer', 'p', 'proxy-tunnel', 'path-as-is', 'post301', 'post302',
        'post303', 'proxy-anyauth', 'proxy-basic', 'proxy-digest', 'proxy-negotiate',
        'proxy-ntlm', 'q', 'raw', 'remote-name-all', 's', 'silent', 'sasl-ir', 'S', 'show-error',
        'ssl', 'ssl-reqd', 'ssl-allow-beast', 'ssl-no-revoke', 'socks5-gssapi-nec', 'tcp-nodelay',
        'tlsv1.0', 'tlsv1.1', 'tlsv1.2', 'tr-encoding', 'trace-time', 'v', 'verbose', 'xattr',
        'h', 'help', 'M', 'manual', 'V', 'version');


    /**
     * CurlParser constructor.
     *
     * @param string $input
     */
    public function __construct($input)
    {
        $this->input = trim(preg_replace('/\s+/', ' ', $input));;
        $this->input_len = strlen($this->input);
        $this->parse();
    }

    /**
     * Method that parses the cURL text into an array with the flags
     */
    private function parse()
    {
        try {

            // trim leading $ or # that may have been left in
            if ( $this->input_len > 2 && ($this->input[0] == '$' || $this->input[0] == '#') && $this->whitespace($this->input[1]) ) {
                $this->input = trim(substr($this->input, 1));
                $this->input_len = strlen($this->input);
            }

            for ($this->cursor = 0; $this->cursor < $this->input_len; $this->cursor++) {
                $this->skipWhitespace();
                $current_char = $this->input[$this->cursor];
                if ( $current_char == "-" ) {
                    if ( $this->cursor < $this->input_len - 1 && $this->input[$this->cursor + 1] == "-" ) {
                        $this->longFlag();
                    } else {
                        $this->shortFlag();
                    }

                } else {
                    $this->unflagged();
                }
            }

        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

    }

    /**
     * shortFlag handles flags and it assumes the current $this->cursor points to a first dash.
     */
    function shortFlag()
    {
        try {

            // parse short flag form
            $this->cursor++; // skip leading dash
            while ($this->cursor < $this->input_len && !$this->whitespace($this->input[$this->cursor])) {
                $flagName = $this->input[$this->cursor];
                if ( empty($this->curl_array[$flagName]) ) {
                    $this->curl_array[$flagName] = array();
                }
                $this->cursor++; // skip the flag name
                if ( $this->boolFlag($flagName) ) {
                    $this->curl_array[$flagName] = true;
                } else if ( is_array($this->curl_array[$flagName]) ) {
                    $this->curl_array[$flagName][] = $this->nextString();
                }
            }

        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * longFlag consumes a "--long-flag" sequence and stores it in result.
     */
    private function longFlag()
    {
        try {

            $this->cursor += 2; // skip leading dashes
            $flagName = $this->nextString("=");
            if ( $this->boolFlag($flagName) ) {
                $this->curl_array[$flagName] = true;
            } else {
                if ( empty($this->curl_array[$flagName]) ) {
                    $this->curl_array[$flagName] = array();
                }
                if ( is_array($this->curl_array[$flagName]) ) {
                    $this->curl_array[$flagName][] = $this->nextString();
                }
            }

        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

    }

    /**
     * unflagged consumes the next string as an unflagged value, storing it in the result.
     */
    private function unflagged()
    {
        $this->curl_array["_"][] = $this->nextString();
    }

    /**
     * boolFlag returns whether a flag is known to be boolean type
     *
     * @param string $flag
     *
     * @return bool
     * @throws Exception
     */
    private function boolFlag($flag)
    {
        $output = false;

        try {

            if ( is_array($this->bool_options) ) {
                foreach ($this->bool_options as $bool_option) {
                    if ( $bool_option == $flag ) {
                        $output = true;
                        break;
                    }
                }
            }

        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

        return $output;
    }

    /**
     * nextString skips any leading whitespace and consumes the next
     * space-delimited string value and returns it. If endChar is set,
     * it will be used to determine the end of the string. Normally just
     * unescaped whitespace is the end of the string, but endChar can
     * be used to specify another end-of-string. This function honors \
     * as an escape character and does not include it in the value, except
     * in the special case of the \$ sequence, the backslash is retained
     * so other code can decide whether to treat as an env var or not.
     *
     * @param string $endChar
     *
     * @return string
     * @throws Exception
     */
    private function nextString($endChar = '')
    {

        $str = '';
        $quoted = false;
        $quoteCh = '';
        $escaped = false;

        try {

            $this->skipWhitespace();

            $current_char = $this->input[$this->cursor];

            if ( $current_char == '"' || $current_char == "'" ) {
                $quoted = true;
                $quoteCh = $current_char;
                $this->cursor++;
            }

            for (; $this->cursor < $this->input_len; $this->cursor++) {
                $current_char = $this->input[$this->cursor];
                if ( $quoted ) {
                    if ( $current_char == $quoteCh && !$escaped ) {
                        $quoted = false;
                        continue;
                    }
                }
                if ( !$quoted ) {
                    if ( !$escaped ) {
                        if ( $this->whitespace($current_char) ) {
                            return $str;
                        }
                        if ( $endChar && $current_char == $endChar ) {
                            $this->cursor++; // skip the $endChar

                            return $str;
                        }
                    }
                }
                if ( !$escaped && $current_char == "\\" ) {
                    $escaped = true;
                    // skip the backslash unless the next character is $
                    if ( !($this->cursor < $this->input_len - 1 && $this->input[$this->cursor + 1] == '$') ) {
                        continue;
                    }
                }

                $str .= $current_char;
                $escaped = false;
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

        return $str;
    }

    /**
     * skipWhitespace skips whitespace between tokens, taking into account escaped whitespace.
     * @throws Exception
     */
    private function skipWhitespace()
    {
        try {
            for (; $this->cursor < $this->input_len; $this->cursor++) {
                $current_char = $this->input[$this->cursor];
                $sig_char = $this->input[$this->cursor + 1];
                while (
                    $current_char == "\\"
                    && (
                        $this->cursor < $this->input_len - 1
                        && $this->whitespace($sig_char)
                    )
                ) {
                    $this->cursor++;
                    if ( !$this->whitespace($this->input[$this->cursor]) ) {
                        break;
                    }
                }
                if ( !$this->whitespace($current_char) ) {
                    break;
                }
            }
        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }

    }

    /**
     * whitespace returns true if ch is a whitespace character.
     *
     * @param string $ch
     *
     * @return bool
     */
    private function whitespace($ch)
    {
        return $ch == " " || $ch == "\t" || $ch == "\n" || $ch == "\r";
    }

    /**
     * @param array $data_array
     *
     * @throws Exception
     */
    private function joinData($data_array = array())
    {
        try {

            if ( empty($this->data) ) {
                $explode_data = array();
            } else {
                $explode_data = explode('&', $this->data);
            }

            foreach ($data_array as $data) {
                if ( !empty($data[0]) && '@' == $data[0]) {
                    $this->files[] = substr($data, 1);
                    continue;
                }
                $explode_data[] = $data;
            }
            if ( !empty($explode_data) && !empty($explode_data[0]) ) {
                $glue = '';
                if ( stripos($this->data, '=') !== false ) {
                    $glue = '&';
                }
                $this->data = implode($glue, $explode_data);
            }


        } catch (Exception $ex) {
            throw new Exception($ex->getMessage(), $ex->getCode());
        }
    }

    /**
     * getData returns an object with relevant pieces
     * extracted from curl_array, the parsed command. This accounts for
     * multiple flags that do the same thing and return structured data.
     *
     * @return array
     */
    public function getData()
    {

        try {

            // prefer --url over unnamed parameter, if it exists; keep first one only
            if ( !empty($this->curl_array['url'][0]) ) {
                $this->url = array_shift($this->curl_array['url']);
            } else if ( !empty($this->curl_array['_']) && !empty($this->curl_array['_'][1]) ) {
                $this->url = $this->curl_array['_'][1]; // position 1 because index 0 is the curl command itself
            }

            // Data
            if ( !empty($this->curl_array['d']) ) {
                $this->joinData($this->curl_array['d']);
            }
            if ( !empty($this->curl_array['data']) ) {
                $this->joinData($this->curl_array['data']);
            }
            if ( !empty($this->curl_array['data-binary']) ) {
                $this->joinData($this->curl_array['data-binary']);
            }

            // gather the headers together
            $headers = array();
            if ( !empty($this->curl_array['H']) ) {
                $headers = array_merge($this->headers, $this->curl_array['H']);
            }
            if ( !empty($this->curl_array['header']) ) {
                $headers = array_merge($this->headers, $this->curl_array['header']);
            }

            $this->content_type = '';
            foreach ($headers as $header) {
                $token = explode(':', $header);
                $this->headers[] = array(
                    'key'   => trim($token[0]),
                    'value' => !empty($token[1]) ? trim($token[1]) : ''
                );
                if ( stripos($token[0], 'Content-Type') !== false ) {
                    $this->content_type = $token[1];
                }
            }

            if ( empty($this->content_type) ) {
                if ( Tools::checkIsJson($this->data) ) {
                    $this->content_type = 'application/json';

                } elseif (Tools::checkIsSoap($this->data) ) {
                    $this->content_type = 'text/xml';

                } elseif (Tools::checkIsXML($this->data) ) {
                    $this->content_type = 'text/xml';
                } else {
                    $this->content_type = 'application/x-www-form-urlencoded';
                }
                $this->headers[] = array(
                    'key'   => 'Content-Type',
                    'value' => $this->content_type
                );
            }

            // set method to HEAD
            if ( !empty($this->curl_array['I']) || !empty($this->curl_array['head']) ) {
                $this->method = 'HEAD';
            }

            // if multiple, use last (according to curl docs)
            if ( !empty($this->curl_array['request']) ) {
                $this->method = strtoupper(array_pop($this->curl_array['request']));
            } else if ( !empty($this->curl_array['X']) ) {
                $this->method = strtoupper(array_pop($this->curl_array['X']));
            }

            // between -u and --user, choose the long form...
            $basic_auth = '';
            if ( !empty($this->curl_array['user']) ) {
                $basic_auth = array_pop($this->curl_array['user']);
            } else if ( !empty($this->curl_array['u']) ) {
                $basic_auth = array_pop($this->curl_array['u']);
            }

            $basic_auth_token = explode(':', $basic_auth);
            if ( !empty($basic_auth_token) ) {
                $this->basic_auth = array(
                    'user' => $basic_auth_token[0],
                    'pass' => (!empty($basic_auth_token[1])) ? $basic_auth_token[1] : '',
                );
            }

        } catch (Exception $ex) {
            // throw new Exception($ex->getMessage(), $ex->getCode());
        }

        return array(
            'url' => $this->url,
            'method' => $this->method,
            'headers' => $this->headers,
            'basic_auth' => $this->basic_auth,
            'data' => $this->data,
            'files' => $this->files,
            'content_type' => $this->content_type
        );

    }
}