<?php

/**
 * This file is part of the NasExt extensions of Nette Framework
 *
 * Copyright (c) 2013 Dusan Hudak (http://dusan-hudak.com)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 */

namespace NasExt\Forms\Controls;

use NasExt;
use Nette;


/**
 * @author Jáchym Toušek
 * @author Dusan Hudak
 * @author Ales Wita
 * @license MIT
 */
class DependentSelectBox extends Nette\Forms\Controls\SelectBox implements Nette\Application\UI\ISignalReceiver
{
	use NasExt\Forms\DependentTrait;

	/** @var string */
	const SIGNAL_NAME = 'load';


	/**
	 * @param string $label
	 * @param array<int, Nette\Forms\IControl> $parents
	 */
	public function __construct($label, array $parents)
	{
		$this->parents = $parents;
		$this->dependentCallbackParams = $this->parents;//default
		parent::__construct($label);
	}


	/**
	 * @param string $signal
	 * @return void
	 */
	public function signalReceived(string $signal) : void
	{
		$presenter = $this->lookup('Nette\\Application\\UI\\Presenter');

		if ($presenter->isAjax() && $signal === self::SIGNAL_NAME && !$this->isDisabled()) {
			$cbParamNames = [];
			foreach ($this->dependentCallbackParams as $param) {
				$value = $presenter->getParameter($this->getNormalizeName($param));

				if ($param instanceof Nette\Forms\Controls\MultiChoiceControl) {
					if (is_string($value)) {
						$value = explode(',', $value);
					}

					if ($value !== null) {
						$value = array_filter($value, static function ($val) {return !in_array($val, [null, '', []], true);});
					}
				}

				$param->setValue($value);

				$cbParamNames[$param->getName()] = $param->getValue();
			}

			$data = $this->getDependentData([$cbParamNames]);
			$presenter->payload->dependentselectbox = [
				'id' => $this->getHtmlId(),
				'items' => $data->getPreparedItems(!is_array($this->disabled) ?: $this->disabled),
				'value' => $data->getValue(),
				'prompt' => $data->getPrompt() === null ? $this->translate($this->getPrompt()) : $this->translate($data->getPrompt()),
				'disabledWhenEmpty' => $this->disabledWhenEmpty,
			];
			$presenter->sendPayload();
		}
	}


	/**
	 * @return void
	 */
	private function tryLoadItems()
	{
		if ($this->dependentCallbackParams === array_filter($this->dependentCallbackParams, function ($p) {return !$p->hasErrors();})) {
			$cbParamValues = [];
			foreach ($this->dependentCallbackParams as $param) {
				$cbParamValues[$param->getName()] = $param->getValue();
			}

			$data = $this->getDependentData([$cbParamValues]);
			$items = $data->getItems();


			if ($this->getForm()->isSubmitted()) {
				$this->setValue($this->value);

			} elseif ($this->tempValue !== null) {
				$this->setValue($this->tempValue);

			} else {
				$this->setValue($data->getValue());
			}


			$this->loadHttpData();
			$this->setItems($items)
				->setPrompt($data->getPrompt() === null ? $this->getPrompt() : $data->getPrompt());

			if (count($items) === 0) {
				if ($this->disabledWhenEmpty === true && !$this->isDisabled()) {
					$this->setDisabled();
				}
			}
		}
	}
}
