<?php
/*
 */

namespace Bogo\Yii\DynSchema\Core;

use \Bogo\Yii\DynSchema\Service\Engine;
use \Bogo\Yii\DynSchema\Core\TBaseComponent;

/**
 * Base widget.
 *
 * @since 2.0
 * @package Components
 * @author Konstantinos Filios <konfilios@gmail.com>
 */
abstract class Widget implements \Bogo\DynSchema\Core\IWidget
{
	use TBaseComponent;

	/**
	 * Owner engine.
	 *
	 * @var Engine 
	 */
	private $engine;

	/**
	 * Owner attribute.
	 *
	 * @var Attribute
	 */
	public $attribute;

	/**
	 * Widget name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Master html entity html options.
	 *
	 * @var array
	 */
	public $htmlOptions = array();

	public function __construct($engine, $attribute, $spec)
	{
		$this->engine = $engine;
		$this->attribute = $attribute;
		$this->spec = $spec;
	}

	public function setName($name)
	{
		$this->name = $name;
		return $this;
	}

	public function setHtmlOptions($htmlOptions)
	{
		$this->htmlOptions = $htmlOptions;
		return $this;
	}
}
