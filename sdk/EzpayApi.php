<?php

abstract class AbstractInvoice
{
    /**
     * 套件設定檔
     *
     * @var array
     */
    public $config;

    /**
     * 運行環境
     *
     * @var boolean
     */
    public $isProduction;

    /**
     * API response
     *
     * @var object|null
     */
    public $response = null;

    /**
     * Response data is ready
     *
     * @var object|null
     */
    private $responseIsReady = false;

    /**
     * Class constructor.
     */
    public function __construct($isProduction)
    {
        self::exceptionHandler();
        self::setProduction($isProduction);
    }

    protected function setProduction(bool $isProduction = true)
    {
        return $this->isProduction = $isProduction;
    }

    abstract protected function sendRequest($postData, $api, $doCheckCode = true, $invoiceData = null);

    protected function setResponse($response, $code)
    {
        $this->response = (object)[
            'success' => $this->isOK($code),
            'message' => $response->message,
            'result' => $response->result,
            'raw' => $response,
        ];

        $this->responseIsReady = true;
    }

    /**
     * return $this
     */
    public function getResponse()
    {
        $this->checkHasResponse();

        return $this->response;
    }

    /**
     * return $this
     */
    public function getResult($property = null)
    {
        $this->checkHasResponse();

        $result = $this->response->result;

        return $property
            ? $result->{$property}
            : $result;
    }

    /**
     * 是否成功
     * return boolean
     */
    public function isOK($code = null)
    {
        if ($code) {
            return $code === $this->config['success-code'];
        }

        $this->checkHasResponse();

        if (isset($this->response->success) && is_bool($this->response->success)) {
            return $this->response->success;
        }

        throw new \Exception('不明錯誤');
    }

    public function getErrorMessage()
    {
        $this->checkHasResponse();

        return $this->isOK()
            ? null
            : [
                'message' => $this->response->message,
            ];
    }

    protected function checkHasResponse()
    {
        if ($this->response === null) {
            throw new \Exception('未進行發票動作，請先進行開立開票或查詢等動作');
        }

        if (!$this->responseIsReady) {
            throw new \Exception('未進行 API 回應的資料設置');
        }

        return true;
    }

    /**
     * Exception handler
     *
     * @return string
     */
    protected function exceptionHandler()
    {
        set_exception_handler(function ($exception) {
            http_response_code(500);
            echo "Error: {$exception->getMessage()},"
                . "File: {$exception->getFile()} Line: {$exception->getLine()}";
        });
    }
}

class EzpayInvoice2 extends AbstractInvoice
{
    /**
     * 商店代號
     *
     * @var string
     */
    public $merchantID;

    /**
     * hashKey
     *
     * @var string
     */
    public $hashKey;

    /**
     * hashIV
     *
     * @var string
     */
    public $hashIV;

    /**
     * Class constructor
     *
     * @param array $account
     * @param bool $isProduction
     */
    public function __construct(array $account, $isProduction = true)
    {
        parent::__construct($isProduction);

        $this->config = (require_once dirname(dirname(__FILE__)) . '/config/config.php')['ezpay'];

        $this->merchantID = $account['merchantID'];
        $this->hashKey = $account['hashKey'];
        $this->hashIV = $account['hashIV'];
    }

    public function create($postData)
    {
        $api = [
            'uri' => 'invoice_issue',
            'version' => '1.4',
        ];
        self::sendRequest($postData, $api);
    }

    public function info($postData)
    {
        $api = [
            'uri' => 'invoice_search',
            'version' => '1.2',
        ];

        self::sendRequest($postData, $api);

        return $this;
    }

    public function invalid($postData)
    {
        /**
         * 測試用資料
         * MerchantID: 32365158
         * MerchantOrderNo: 1589331622
         * InvoiceNumber: AA00000076
         * TotalAmt: 500
         * InvoiceTransNo: 20051309002377869
         * RandomNum: 0991
         */

        /**
         * 作廢發票預設不檢查 checkcode，因官方回應缺少屬性，
         * 需預先做次發票查詢，取回需要的參數，
         * 若 $postData 中有「發票隨機碼 RandomNum」時，則進行 checkcode 檢查
         */

        $api = [
            'uri' => 'invoice_invalid',
            'version' => '1.0',
        ];

        $doCheckCode = false;
        $invoiceData = null;

        if (array_key_exists('RandomNum', $postData)) {
            // 先查詢發票資訊，用於帶入檢查 checkcode 時的參數
            $invoiceData = $this->info([
                // 使用發票號碼及隨機碼查詢
                'SearchType' => 0,
                'InvoiceNumber' => $postData['InvoiceNumber'],
                'RandomNum' => $postData['RandomNum'],
            ])->getResult();
        }

        if ($invoiceData) {
            $doCheckCode = true;
        }

        self::sendRequest($postData, $api, $doCheckCode, $invoiceData);

        return $this;
    }

    protected function sendRequest($postData, $api, $doCheckCode = true, $invoiceData = null)
    {
        $postData = $this->mergeCommonPostData($postData, $api['version']);
        $response = (new EZPay_IO2($this))->ServerPost($postData, $api['uri']);
        //$response = (new EzpayApi($this))->send($api['uri'], $postData, $doCheckCode, $invoiceData);

        self::handleResponse($response);
        return $response;
    }

    protected function mergeCommonPostData($postData, $apiVersion)
    {
        $default = [
            'Version' => $apiVersion, // 每支 API 不同
            'RespondType' => $this->config['response-type'],
            'TimeStamp' => time(), // 請以 time() 格式
        ];

        return array_merge($default, $postData);
    }

    protected function handleResponse($response)
    {
        $rawResponse = (object)[
            'raw' => $response,
            'message' => $response->message,
            'result' => $response->result,
        ];

        parent::setResponse($rawResponse, $code = $response->status);
    }
}

class EZPay_IO2
{
    public function ServerPost($parameters, $ServiceURL)
    {

        $sSend_Info = '';

        // 組合字串
        foreach ($parameters as $key => $value) {

            if ($sSend_Info == '') {
                $sSend_Info .= $key . '=' . $value;

            } else {
                $sSend_Info .= '&' . $key . '=' . $value;
            }
        }

        $ch = curl_init();

        if (FALSE === $ch) {
            throw new Exception('curl failed to initialize');
        }

        $transaction_data_array = array(//送出欄位
            'MerchantID_' => '32414695',
            'PostData_' => $this->postDataEncrypt($parameters)
        );
        $transaction_data_str = http_build_query($transaction_data_array);
        //error_log("transaction_data_str:" . $transaction_data_str);
        curl_setopt($ch, CURLOPT_URL, 'https://cinv.ezpay.com.tw/Api/invoice_issue');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, False); //這個是重點,規避ssl的證書檢查。
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, False); // 跳過host驗證
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $transaction_data_str);
        $rs = curl_exec($ch);

        if (FALSE === $rs) {
            throw new Exception(curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return $rs;
    }

    /**
     * 加密所需
     *
     * @return string
     */
    private function postDataEncrypt(array $postData)
    {
        $postDataStr = http_build_query($postData); // 轉成字串排列

        if (phpversion() > 7) {
            // php 7 以上版本加密
            //$postData = trim(bin2hex(openssl_encrypt($this->strAddPadding($postDataStr),'AES-256-CBC', $this->instance->hashKey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->instance->hashIV)));
            $postData = trim(bin2hex(openssl_encrypt($this->strAddPadding($postDataStr),
                'AES-256-CBC', '20kgSxuBUFkagUfZPbcwjkTywTZJZVW3', OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, 'CATDz1LUiQdFAQ6P')));
        } else {
            // php 7 之前版本加密
            $postData = trim(bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->instance->hashKey,
                $this->instance->strAddPadding($postDataStr), MCRYPT_MODE_CBC, $this->instance->hashIV)));
        }
        //error_log($postData);
        return $postData;
    }

    /**
     * 加密所需
     *
     * @return string
     */
    private function strAddPadding($string, $blocksize = 32)
    {
        $len = strlen($string);
        $pad = $blocksize - ($len % $blocksize);
        $string .= str_repeat(chr($pad), $pad);

        return $string;
    }

    private function validateCheckCode($result, $invoiceData)
    {
        $responseChcekCode = $result->CheckCode;

        $result = $invoiceData ? $invoiceData : $result; // 若經由查詢發票帶入，使用查詢發票的資料

        $checkCode = [
            'MerchantID' => $this->instance->merchantID,
            'MerchantOrderNo' => $result->MerchantOrderNo,
            'InvoiceTransNo' => $result->InvoiceTransNo,
            'TotalAmt' => $result->TotalAmt,
            'RandomNum' => $result->RandomNum,
        ];

        ksort($checkCode);
        $checkStr = http_build_query($checkCode);
        $checkCode = strtoupper(hash(
            'sha256',
            "HashIV={$this->instance->hashIV}&" . $checkStr . "&HashKey={$this->instance->hashKey}"
        ));

        if ($checkCode !== $responseChcekCode) {
            throw new \Exception('check code 檢查錯誤');
        }
    }
}

?>