<?php

namespace Project\Payment\Dengi\Backend;

use Supra\Html\HtmlTag;
use Supra\Response\ResponseInterface;
use Supra\Response\HttpResponse;
use Supra\ObjectRepository\ObjectRepository;

class GenericType1Backend extends Type1Backend
{

	/**
	 * @return string
	 */
	protected function getTitle()
	{
		$localeManager = ObjectRepository::getLocaleManager($this);
		$currentLocale = $localeManager->getCurrent();
		
		$translator = ObjectRepository::getTranslator($this);

		$title = $translator->trans($this->getName(), array(), 'messages', $currentLocale->getId());

		return $title;
	}

	/**
	 * @return string
	 */
	public function getFormElements($isSelected = false)
	{
		$modeType = $this->getModeType();

		$formElements = array();

		$input = new HtmlTag('input');
		$input->setAttribute('type', 'radio');
		$input->setAttribute('id', 'mode_type_' . $modeType);
		$input->setAttribute('name', 'mode_type');
		$input->setAttribute('value', $modeType);

		if ($isSelected == $modeType) {
			$input->setAttribute('selected', 'selected');
		}
		$formElements[] = $input->toHtml();

		$label = new HtmlTag('label');
		$label->setAttribute('for', 'mode_type_' . $modeType);

		$title = $this->getTitle();

		$label->setContent($title);

		$formElements[] = $label->toHtml();

		$br = new HtmlTag('br');
		$formElements[] = $br->toHtml();

		return $formElements;
	}

	/**
	 * @param array $formData
	 * @return array
	 */
	public function validateForm($formData)
	{
		return array();
	}

	/**
	 * 
	 */
	public function notificationAction()
	{
		
	}

	/**
	 * 
	 */
	public function proxyAction($formData, ResponseInterface $response)
	{
		$provider = $this->getPaymentProvider();
		$order = $this->getOrder();

		$url = $provider->getRedirectUrl($order, $formData);

		if ($response instanceof HttpResponse) {
			$response->redirect($url);
		}
	}

	/**
	 * 
	 */
	public function returnAction()
	{
		
	}

}