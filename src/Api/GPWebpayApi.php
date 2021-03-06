<?php

declare(strict_types=1);

namespace MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Api;

use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\Api;
use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\PaymentRequest;
use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\PaymentResponse;
use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\PaymentResponseException;
use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk\Signer;
use Payum\ISO4217\ISO4217;
use Psr\Log\LoggerInterface;
use Sylius\Component\Core\Context\ShopperContextInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;

class GPWebpayApi implements GPWebpayApiInterface
{
	/** @var ShopperContextInterface */
	protected $shopperContext;

	/** @var TranslatorInterface */
	protected $translator;

	/**
	 * @var LoggerInterface
	 */
	protected $logger;
	/**
	 * @var RequestStack
	 */
	protected $requestStack;

	public function __construct(
		TranslatorInterface $translator,
		ShopperContextInterface $shopperContext,
		LoggerInterface $logger,
		RequestStack $requestStack
	) {
		$this->translator = $translator;
		$this->shopperContext = $shopperContext;
		$this->logger = $logger;
		$this->requestStack = $requestStack;
	}

	protected function createApi(bool $sandbox, string $clientPrivateKey, string $keyPassword, string $merchantNumber): Api
	{
		$serverCert = $sandbox
			? __DIR__ . '/../Resources/keys/serverKeys/sandbox/gpe.signing_test.pem'
			: __DIR__ . '/../Resources/keys/serverKeys/prod/gpe.signing_prod.pem';

		$apiEndpoint = $sandbox
			? 'https://test.3dsecure.gpwebpay.com/pgw/order.do'
			: 'https://3dsecure.gpwebpay.com/pgw/order.do';

		$signer = new Signer($clientPrivateKey, $keyPassword, $serverCert);

		return new Api($merchantNumber, $apiEndpoint, $signer);
	}

	protected function getCurrency(string $currencyCode): int
	{
		$iso4217 = new ISO4217();
		$currency = $iso4217->findByAlpha3($currencyCode);

		return (int) $currency->getNumeric();
	}

	public function create(array $order, string $merchantNumber, bool $sandbox, string $clientPrivateKey, string $keyPassword, ?string $preferredPaymentMethod, ?array $allowedPaymentMethods): array
	{
		$api = $this->createAPI($sandbox, $clientPrivateKey, $keyPassword, $merchantNumber);

		$orderNumber = (int) $order['orderNumber'];
		$amount = $order['amount'] / 100;
		$currency = $this->getCurrency($order['currency']);
		$depositFlag = 1;
		$url = $order['returnUrl'];
		$merOrderNumber = null;

		$request = new PaymentRequest($orderNumber, $amount, $currency, $depositFlag, $url, $merOrderNumber);
		if ($preferredPaymentMethod !== null && $preferredPaymentMethod !== '') {
			$request->setPreferredPaymentMethod($preferredPaymentMethod);
		}
		if ($allowedPaymentMethods !== null && count($allowedPaymentMethods) > 0) {
			$request->setAllowedPaymentMethods(implode(',', $allowedPaymentMethods));
		}

		return [
			'orderId' => $order['orderNumber'],
			'gatewayLocationUrl' => $api->createPaymentRequestUrl($request),
		];
	}

	public function retrieve(string $merchantNumber, bool $sandbox, string $clientPrivateKey, string $keyPassword): string
	{
		$request = $this->requestStack->getMasterRequest();
		assert($request !== null);

		$operation = $request->get('OPERATION');
		$ordernumber = $request->get('ORDERNUMBER');
		$merordernum = $request->get('MERORDERNUM');
		$prcode = (int) $request->get('PRCODE');
		$srcode = (int) $request->get('SRCODE');
		$resulttext = $request->get('RESULTTEXT');
		$digest = $request->get('DIGEST');
		$digest1 = $request->get('DIGEST1');

		$response = new PaymentResponse($operation, $ordernumber, $merordernum, $prcode, $srcode, $resulttext, $digest, $digest1);

		try {
			$api = $this->createAPI($sandbox, $clientPrivateKey, $keyPassword, $merchantNumber);
			$api->verifyPaymentResponse($response);
		} catch (PaymentResponseException $e) {
			$this->logger->error($e->getMessage());

			return GPWebpayApiInterface::CANCELED;
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage());

			return GPWebpayApiInterface::CANCELED;
		}

		if ($response->hasError()) {
			return GPWebpayApiInterface::CANCELED;
		}

		return GPWebpayApiInterface::PAID;
	}
}
