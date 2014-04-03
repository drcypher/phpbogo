<?php
/*
 */

namespace Bogo\Yii\Http;

/**
 * Buffered http call executor.
 *
 * A buffered executor maintains an internal, finite-size buffer of submitted calls. Every time
 * you submit() a call for execution, one of the following may happen:
 *
 * <ul>
 * <li>If the buffer is full, concurrent execution of all buffered calls starts and submit()
 * returns when all calls are completed.</li>
 *
 * <li> If the buffer is not full, the submitted call is appended to the buffer.</li>
 * </ul>
 *
 * To avoid leaving submitted calls unexecuted follow the usage guidelines explained in
 * Executor.
 *
 * @since 2.0
 * @package Components
 * @author Konstantinos Filios <konfilios@gmail.com>
 */
class Buffered extends Executor
{
	/**
	 * Number of calls to buffer before invokeAll().
	 *
	 * @var integer
	 */
	private $bufferSize = 0;

	/**
	 * Buffer of submit()ed calls.
	 *
	 * @var Call[]
	 */
	private $bufferedCalls = array();

	/**
	 * Construct new buffered executor.
	 *
	 * @param integer $bufferSize
	 */
	public function __construct($bufferSize)
	{
		$this->bufferSize = $bufferSize;
	}

	/**
	 * Submit call for execution.
	 *
	 * If capacity of buffer has been reached, invokeAll() is called.
	 *
	 * @param Call $call Call to be executed.
	 * @return Call[] List of completed calls.
	 */
	public function submit(SingleRunner $call)
	{
		// Create call and push to queue
		$this->bufferedCalls[] = $call;

		if (count($this->bufferedCalls) >= $this->bufferSize) {
			return $this->invokeAll();
		} else {
			return array();
		}
	}

	/**
	 * Invoke all pending submitted calls.
	 *
	 * Buffered calls are executed concurrently using a MultiCallCurlParallel.
	 *
	 * @return Call[] List of completed calls.
	 */
	public function invokeAll()
	{
		if (empty($this->bufferedCalls)) {
			return array();
		}

		// Trigger events
		if ($this->hasEventHandler('onBeforeInvokeAll')) {
			$this->onBeforeInvokeAll(new CEvent($this));
		}

		// Create multi-call
		$multiCall = new MultiCallCurlParallel($this->bufferedCalls);
		/* @var $multiCall MultiCallCurlParallel */

		$executedCalls = $multiCall->exec()->getCalls();

		// Trigger events
		if ($this->hasEventHandler('onAfterCompleteCall')) {
			foreach ($executedCalls as $call) {
				/* @var $call Call */
				$this->onAfterCompleteCall(new CallEvent($this, null, $call));
			}
		}

		// Keep statistics
		$this->incrementTotalExecutedCallCount(count($this->bufferedCalls));
		$this->incrementTotalCallExecutionSeconds($multiCall->getExecutionSeconds());

		// Reset buffer
		$this->bufferedCalls = array();

		// Trigger events
		if ($this->hasEventHandler('onAfterInvokeAll')) {
			$this->onAfterInvokeAll(new CEvent($this));
		}

		return $executedCalls;
	}
}
