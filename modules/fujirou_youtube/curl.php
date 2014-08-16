<?php
/**
 * Reference: http://github.com/shuber/curl/
 */

class Curl
{
	private $response = NULL;

	public function __construct($url, $data=NULL, $headers=NULL, $proxy=NULL, $cookiePath='tmp_cookie.txt')
	{
		$method = 'get';

		if (empty($url)) {
			throw new Exception('url cannot be empty.');
		}

		$sent_headers = array();
		if (NULL == $headers) {
			$userAgent = 'Mymedia Get 2012-0904';
			if (defined('DOWNLOAD_STATION_USER_AGENT')) {
				$userAgent = DOWNLOAD_STATION_USER_AGENT;
			}
			$sent_headers[] = sprintf('User-Agent: %s', $userAgent);
		} else {
			// Format custom headers for cURL option
			foreach ($headers as $key => $value) {
				$sent_headers[] = $key.': '.$value;
			}
		}
		if (isset($data) && NULL != $data) {
			$method = 'post';
			if ($headers && !array_key_exists('Content-Type', $headers)) {
				$sent_headers[] = 'Content-Type: application/x-www-form-urlencoded';
			}
		}

		$ch = curl_init();
//  curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
		curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept-Encoding. Empty sent all supported encoding types
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); // Do not follow any "Location: " header
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // skip verifying peer's certificate
		curl_setopt($ch, CURLOPT_HEADER, TRUE); // output header
		curl_setopt($ch, CURLOPT_HTTPHEADER, $sent_headers); // array of HTTP headers
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // not directly output
		curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath); // set load cookie file
		curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiePath); // set save cookie file
		curl_setopt($ch, CURLOPT_URL, $url); // set url
		if ('get' == $method) {
			curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
		} else if ('post' == $method) {
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		if (!empty($proxy)) {
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
		}

		$response = curl_exec($ch);
		if ($response) {
			$this->response = new CurlResponse($response);
		}
		curl_close($ch);
	}

	public function get_content()
	{
		return $this->response->body;
	}

	public function get_info()
	{
		return $this->response->headers;
	}

	public function get_header($key)
	{
		return $this->response->headers[$key];
	}
}

class CurlResponse
{
	public $body = '';
	public $headers = array();

	public function __construct($response)
	{
		# Extract headers from response
		$pattern = '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims';
		preg_match_all($pattern, $response, $matches);
		$headers = explode("\r\n", str_replace("\r\n\r\n", '', array_pop($matches[0])));
		
		# Extract the version and status from the first header
		$version_and_status = array_shift($headers);
		preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches);
		$this->headers['Http-Version'] = $matches[1];
		$this->headers['Status-Code'] = $matches[2];
		$this->headers['Status'] = $matches[2].' '.$matches[3];
		
		# Convert headers into an associative array
		foreach ($headers as $header) {
			preg_match('#(.*?)\:\s(.*)#', $header, $matches);
			if (array_key_exists($matches[1], $this->headers)) {
				$this->headers[$matches[1]] .= ','.$matches[2];
			} else {
				$this->headers[$matches[1]] = $matches[2];
			}
		}
		
		# Remove the headers from the response body
		$this->body = preg_replace($pattern, '', $response);
	}

	public function __toString()
	{
		return $this->body;
	}

	public function get_content()
	{
		return $this->body;
	}

	public function get_headers()
	{
		return $this->headers;
	}

	public function get_header($key)
	{
		if (isset($this->headers[$key])) {
			return $this->headers[$key];
		} else {
			// throw exception?
			return '';
		}
	}
}
?>
