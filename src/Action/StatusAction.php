<?php

declare(strict_types=1);

namespace MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Action;

use MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Api\GPWebpayApiInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;

class StatusAction implements ActionInterface
{
	/**
	 * {@inheritdoc}
	 *
	 * @param GetStatusInterface $request
	 */
	public function execute($request)
	{
		RequestNotSupportedException::assertSupports($this, $request);

		$model = ArrayObject::ensureArrayObject($request->getModel());
		$status = $model['gpWebPayStatus'] ?? null;

		if ($status === null || $status === GPWebpayApiInterface::CREATED) {
			$request->markNew();

			return;
		}

		if ($status === GPWebpayApiInterface::CANCELED) {
			$request->markCanceled();

			return;
		}

		if ($status === GPWebpayApiInterface::PAID) {
			$request->markCaptured();

			return;
		}

		$request->markUnknown();
	}

	/**
	 * {@inheritdoc}
	 */
	public function supports($request)
	{
		return
			$request instanceof GetStatusInterface &&
			$request->getModel() instanceof \ArrayAccess
		;
	}
}
