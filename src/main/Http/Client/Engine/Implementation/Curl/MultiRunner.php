<?php
/*
 */

namespace Bogo\Http\Client\Engine\Implementation\Curl;
use Bogo\Http\Client\Engine;

/**
 * Parallel cURL multi-call.
 *
 * @todo Constructor should not require CallCurl instances.
 * @since 2.0
 * @package Components
 * @author Konstantinos Filios <konfilios@gmail.com>
 */
class MultiRunner extends Engine\MultiRunner
{
	/**
	 * Construct.
	 *
	 * @param array $requestObjects
	 * @throws Exception
	 */
	function __construct(array $requestObjects)
	{
		foreach ($requestObjects as $key => $requestObject) {
			// Add every input object in queue

			if ($requestObject instanceof Message\Request) {
				// Request message, wrap it in a call
				$this->setCall($key, new SingleRunner($requestObject));

			} else if ($requestObject instanceof SingleRunner) {
				// A Curl call, keep it as is
				$this->setCall($key, $requestObject);

			} else {
				throw new CallException('Unexpected request object of type '.get_class($requestObject));
			}
		}
	}

	/**
	 * Execute all calls.
	 *
	 * @return MultiCallCurlParallel
	 */
	public function run()
	{
		//
		// Create get requests for each URL
		//
		$multiHandle = curl_multi_init();

		$calls = $this->getCalls();

		foreach ($calls as $key => $curlCall) {
			/* @var $curlCall CallCurl */

			// Open the handle
			$curlHandles[$key] = $curlCall->curlInit();

			// Add it to the set
			curl_multi_add_handle($multiHandle, $curlHandles[$key]);
		}


		//
		// Start performing the request
		//
		$this->startTimer();

		$runningHandles = 0;
		do {
			$execReturnValue = curl_multi_exec($multiHandle, $runningHandles);
		} while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);

		//
		// Loop and continue processing the requests
		//
		while ($runningHandles && ($execReturnValue == CURLM_OK)) {
			// Wait forever for network
			$numberReady = curl_multi_select($multiHandle);

			if ($numberReady != -1) {
				// Patch for windows
				// https://bugs.php.net/bug.php?id=63411 [2012-11-15 11:42 UTC] bfanger@gmail.com
				usleep(100000);
			}

			// Pull in any new data, or at least handle timeouts
			do {
				$execReturnValue = curl_multi_exec($multiHandle, $runningHandles);
			} while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);

			// Check if any request is completed
			// http://www.onlineaspect.com/2009/01/26/how-to-use-curl_multi-without-blocking/
			if ($execReturnValue == CURLM_OK) {
				while ($done = curl_multi_info_read($multiHandle)) {
					// A request was just completed -- find out which one
//					$info = curl_getinfo($done['handle']);

					$doneRequestKey = array_search($done['handle'], $curlHandles);
					$curlCall = $calls[$doneRequestKey];

					// Set error info. errno() does not work so we use $done['result'] :(
					$curlCall->setErrorCode($done['result']);
					$curlCall->setErrorMessage(curl_error($done['handle']));

					// Remove the handle
					curl_multi_remove_handle($multiHandle, $done['handle']);

					// Close it
					$curlCall->curlClose($done['handle']);
				}
			}
		}
		$this->stopTimer();

		// Clean up the curl_multi handle
		curl_multi_close($multiHandle);

		// Check for any errors
		$this->errorCode = $execReturnValue;

		return $this;
	}
}