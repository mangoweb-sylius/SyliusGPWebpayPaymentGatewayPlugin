<?php

declare(strict_types=1);

namespace MangoSylius\SyliusGPWebpayPaymentGatewayPlugin\Model\WebpaySdk;

class PaymentResponse
{
	/** @var array */
	protected $params = [];

	/** @var string */
	protected $digest;

	/** @var string */
	protected $digest1;

	/**
	 * @param string $operation
	 * @param string $ordernumber
	 * @param string $merordernum
	 * @param int $prcode
	 * @param int $srcode
	 * @param string $resulttext
	 * @param string $digest
	 * @param string $digest1
	 */
	public function __construct(string $operation, string $ordernumber, ?string $merordernum, int $prcode, int $srcode, string $resulttext, string $digest, string $digest1)
	{
		$this->params['operation'] = $operation;
		$this->params['ordermumber'] = $ordernumber;
		if ($merordernum !== null) {
			$this->params['merordernum'] = $merordernum;
		}
		$this->params['prcode'] = $prcode;
		$this->params['srcode'] = $srcode;
		$this->params['resulttext'] = $resulttext;
		$this->digest = $digest;
		$this->digest1 = $digest1;
	}

	/**
	 * @return array
	 */
	public function getParams(): array
	{
		return $this->params;
	}

	/**
	 * @return string
	 */
	public function getDigest(): string
	{
		return $this->digest;
	}

	/**
	 * @return bool
	 */
	public function hasError(): bool
	{
		return (bool) $this->params['prcode'] || (bool) $this->params['srcode'];
	}

	/**
	 * @return string
	 */
	public function getDigest1(): string
	{
		return $this->digest1;
	}
}
