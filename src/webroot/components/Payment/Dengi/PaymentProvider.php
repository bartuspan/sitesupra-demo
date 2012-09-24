<?php

namespace Project\Payment\Dengi;

use Supra\Payment\Provider\PaymentProviderAbstraction;
use Supra\Payment\Entity\Order;
use Supra\Payment\Entity\Order\ShopOrder;
use Supra\Payment\Entity\Order\RecurringOrder;
use Supra\Payment\Entity\Transaction\Transaction;
use Supra\Payment\Order\OrderStatus;
use Supra\Payment\Order\RecurringOrderPeriodDimension;
use Supra\Locale\Locale;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Payment\Transaction\TransactionType;
use Supra\Response\ResponseInterface;
use Supra\Payment\PaymentEntityProvider;
use Supra\Payment\SearchPaymentEntityParameter;
use Supra\Payment\Order\OrderProvider;
use Supra\Session\SessionManager;
use Supra\Session\SessionNamespace;
use Supra\Payment\Entity\Abstraction\PaymentEntity;
use Supra\Payment\Transaction\TransactionStatus;
use Supra\Response\TwigResponse;
use Supra\Controller\FrontController;

class PaymentProvider extends PaymentProviderAbstraction
{
	// Phase names used in Dengi context

	const PHASE_NAME_INITIALIZE_TRANSACTION = 'dengi-initialize';
	const PHASE_NAME_CHARGE_TRANSACTION = 'dengi-charge';

	// Phase names for transaction status storage
	const PHASE_NAME_STATUS_ON_RETURN = 'dengi-statusOnReturn';
	const PHASE_NAME_STATUS_ON_NOTIFICATION = 'dengi-statusOnNotification';

	/**
	 * @var PaymentEntityProvider
	 */
	protected $paymentEntityProvider;

	/**
	 * @var OrderProvider
	 */
	protected $orderProvider;

	/**
	 * @var string
	 */
	protected $projectId;

	/**
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * @var string
	 */
	protected $secret;

	/**
	 * @var string
	 */
	protected $returnHost;

	/**
	 * @var string
	 */
	protected $callbackHost;

	/**
	 * @var string
	 */
	protected $apiUrl;

	/**
	 * @var string
	 */
	protected $dataFormPath;

	/**
	 * @var string
	 */
	protected $userIpOverride;

	/**
	 * @var array
	 */
	protected $backends;

	/**
	 * @param string $projectId 
	 */
	public function setProjectId($projectId)
	{
		$this->projectId = $projectId;
	}

	/**
	 * @return string
	 */
	public function getProjectId()
	{
		return $this->projectId;
	}

	/**
	 * @return string
	 */
	public function getSource()
	{
		return $this->source;
	}

	/**
	 * @param string $source
	 */
	public function setSource($source)
	{
		$this->source = $source;
	}

	/**
	 * @param string $secret
	 */
	public function setSecret($secret)
	{
		$this->secret = $secret;
	}

	/**
	 * @return string
	 */
	public function getSecret()
	{
		return $this->secret;
	}

	/**
	 * @param string $returnHost
	 */
	public function setReturnHost($returnHost)
	{
		$this->returnHost = $returnHost;
	}

	/**
	 * @return string
	 */
	public function getReturnHost()
	{
		return $this->returnHost;
	}

	/**
	 * @param string $callbackHost
	 */
	public function setCallbackHost($callbackHost)
	{
		$this->callbackHost = $callbackHost;
	}

	/**
	 * @return string
	 */
	public function getCallbackHost()
	{
		return $this->callbackHost;
	}

	/**
	 * @return string
	 */
	public function getDataFormPath()
	{
		return $this->dataFormPath;
	}

	/**
	 * @return string
	 */
	public function setDataFormPath($formDataPath)
	{
		$this->dataFormPath = $formDataPath;
	}

	/**
	 * @return string
	 */
	public function getApiUrl()
	{
		return $this->apiUrl;
	}

	/**
	 * @param string $apiUrl
	 */
	public function setApiUrl($apiUrl)
	{
		$this->apiUrl = $apiUrl;
	}

	/**
	 * @return string
	 */
	public function getUserIpOverride()
	{
		return $this->userIpOverride;
	}

	/**
	 * @param string $userIpOverride
	 */
	public function setUserIpOverride($userIpOverride)
	{
		$this->userIpOverride = $userIpOverride;
	}

	/**
	 * @return string
	 */
	public function getNotificationUrl()
	{
		return $this->getCallbackHost() . $this->getBaseUrl() . '/' . self::PROVIDER_NOTIFICATION_URL_POSTFIX;
	}

	/**
	 * @return string
	 */
	public function getReturnUrl()
	{
		return $this->getReturnHost() . $this->getBaseUrl() . '/' . self::CUSTOMER_RETURN_URL_POSTFIX;
	}

	/**
	 * @return PaymentEntityProvider
	 */
	public function getPaymentEntityProvider()
	{
		if (empty($this->paymentEntityProvider)) {

			$em = $this->getEntityManager();

			$provider = new PaymentEntityProvider();
			$provider->setEntityManager($em);

			$this->paymentEntityProvider = $provider;
		}

		return $this->paymentEntityProvider;
	}

	/**
	 * @return string
	 */
	private function getUserIp()
	{
		$userIp = $this->getUserIpOverride();

		if (empty($userIp)) {
			$userIp = $_SERVER['REMOTE_ADDR'];
		}

		return $userIp;
	}

	/**
	 * @param Order\Order $order
	 * @return string 
	 */
	public function getDataFormUrl(Order\Order $order)
	{
		$queryData = array(
			PaymentProviderAbstraction::REQUEST_KEY_ORDER_ID => $order->getId()
		);

		$formDataUrl = $this->getDataFormPath() . '?' . http_build_query($queryData);

		return $formDataUrl;
	}

	/**
	 * @param Order\Order $order
	 * @return SessionNamespace
	 */
	public function getSessionForOrder(Order\Order $order)
	{
		$sessionManager = ObjectRepository::getSessionManager($this);
		$session = $sessionManager->getSessionNamespace($this->getId() . $order->getId());

		return $session;
	}

	/**
	 * @param Order\Order $order 
	 */
	public function updateShopOrder(Order\ShopOrder $order)
	{
		$paymentProviderOrderItem = $order->getOrderItemByPayementProvider();

		if ($paymentProviderOrderItem->getPaymentProviderId() != $this->getId()) {

			$order->removeOrderItem($paymentProviderOrderItem);

			$paymentProviderOrderItem = $order->getOrderItemByPayementProvider($this->getId());
		}

		$paymentProviderOrderItem->setPrice($order->getTotalForProductItems() * 0.11);
	}

	/**
	 * @param Order\ShopOrder $order 
	 * @return boolean
	 */
	public function validateShopOrder(Order\ShopOrder $order)
	{
		if ($order->getTotalForProductItems() < 20.00) {
			throw new Exception\RuntimeException('Total is too small!!!');
		}

		return true;
	}

	/**
	 * @param Order\ShopOrder $order
	 * @param ResponseInterface $response 
	 */
	public function processShopOrder(Order\ShopOrder $order, ResponseInterface $response)
	{
		parent::processShopOrder($order, $response);

		// This is Dengi specific behaviour.
		$proxyActionUrlQueryData = array(
			self::REQUEST_KEY_ORDER_ID => $order->getId()
		);

		$this->redirectToProxy($proxyActionUrlQueryData, $response);
	}

	/**
	 * @param Order\ShopOrder $order
	 * @param array $paymentCredentials
	 * @param ResponseInterface $response 
	 */
	public function processShopOrderDirect(Order\ShopOrder $order, $paymentCredentials)
	{
		$response = new \Supra\Response\HttpResponse();

		parent::processShopOrder($order, $response);

		$proxyActionQueryData = array(
			self::REQUEST_KEY_ORDER_ID => $order->getId(),
			Action\ProxyAction::REQUEST_KEY_RETURN_FROM_FORM => true
		);

		$request = new \Supra\Request\HttpRequest();
		$request->setPost($paymentCredentials);
		$request->setQuery($proxyActionQueryData);

		$lastRouter = new \Supra\Payment\PaymentProviderUriRouter();
		$lastRouter->setPaymentProvider($this);

		$request->setLastRouter($lastRouter);

		$proxyActionController = FrontController::getInstance()->runController(Action\ProxyAction::CN(), $request);

		$proxyResponse = $proxyActionController->getResponse();

		return $proxyResponse;
	}

	/**
	 * @param Order\ShopOrder $order
	 * @param array $transactionStatus
	 * @throws Exception\RuntimeException 
	 */
	public function updateShopOrderStatus(Order\ShopOrder $order, $transactionStatus)
	{
		if (empty($transactionStatus) || empty($transactionStatus['Status'])) {
			throw new Exception\RuntimeException('No transaction status.');
		}

		switch (strtolower($transactionStatus['Status'])) {

			case 'success': {
					$order->getTransaction()
							->setStatus(TransactionStatus::SUCCESS);
				} break;

			case 'failed': {
					$order->getTransaction()
							->setStatus(TransactionStatus::FAILED);
				} break;

			case 'pending': {

					throw new Exception\RuntimeException('Pending transaction handling not implemented yet.');
				} break;

			default: {

					throw new Exception\RuntimeException('Transaction status "' . $transactionStatus['Status'] . '" is not recognized.');
				}
		}
	}

	/**
	 * @param Order\Order $order
	 * @param Locale $locale 
	 * @return boolean
	 */
	public function getOrderItemDescription(Order\Order $order, Locale $locale = null)
	{
		return 'Dengi fee (' . $locale . ') - ' . ($order->getTotalForProductItems() * 0.10) . ' ' . $order->getCurrency()->getIso4217Code();
	}

	/**
	 * @param array
	 * @return string
	 */
	public function getCustomerReturnActionUrl($queryData)
	{
		$query = http_build_query($queryData);

		return $this->getReturnUrl() . '?' . $query;
	}

	/**
	 * @param array
	 * @return string
	 */
	public function getProviderNotificationActionUrl($queryData)
	{
		$query = http_build_query($queryData);

		return $this->getNotificationUrl() . '?' . $query;
	}

	/**
	 * @param Order\Order $order
	 * @return string
	 */
	public function getDataFormReturnUrl(Order\Order $order)
	{
		$queryData = array(
			Action\ProxyAction::REQUEST_KEY_RETURN_FROM_FORM => true,
			PaymentProviderAbstraction::REQUEST_KEY_ORDER_ID => $order->getId()
		);

		return $this->getProxyActionUrl($queryData);
	}

	/**
	 * @param string $backendId
	 * @return Backend\BackendAbstraction
	 */
	public function getBackend($backendId)
	{
		if ( ! isset($this->backends[$backendId])) {
			throw new Exception\RuntimeException('Backend "' . $backendId . '" not found.');
		}

		return $this->backends[$backendId];
	}

	/**
	 * 
	 * @param ShopOrder $order
	 * @param array $otherData
	 * @return string
	 */
	public function getRedirectUrl(ShopOrder $order, $otherData)
	{
		$urlBase = $this->getApiUrl();

		$backend = $this->getBackend($otherData['mode_type']);

		$queryData = array(
			'project' => $this->getProjectId(),
			'mode_type' => $otherData['mode_type'],
			'amount' => $order->getTotal(),
			'source' => $this->getSource(),
			'nicnkanme' => $order->getId(),
			'order_id' => $order->getId(),
			'paymentCurrency' => $backend->getCurrencyCode(),
		);

		$url = http_build_url($urlBase, array('query' => http_build_query($queryData)), HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY);

		return $url;
	}

	/**
	 * @return array
	 */
	public function getBackends()
	{
		return $this->backends;
	}

	/**
	 * @param array $backends
	 */
	public function setBackends($backends)
	{
		$this->backends = $backends;
	}

}
