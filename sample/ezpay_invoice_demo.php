<?php
try {
    //根目錄設置
    $_SERVER['DOCUMENT_ROOT'];
    $basedir = $_SERVER['DOCUMENT_ROOT'];

    echo $basedir;
    $sMsg = '';
// 1.載入SDK程式
    include_once($basedir . '/sdk/EzpayApi.php');
    $account = (require '../config/ezpay-invoice.php')['testing'];
    $invoice = new EzpayInvoice2($account, $isProduction = false);

    $invoice->create([
        'Status' => '1', // 1=立即開立，0=待開立，3=延遲開立
        'CreateStatusTime' => null, // Status = 3 時設置
        'MerchantOrderNo' => time(),
        'BuyerName' => '停看聽',
        'BuyerUBN' => '54352706',
        'BuyerAddress' => '台北市南港區南港路二段 97 號 8 樓',
        'BuyerEmail' => '54352706@pay2go.com',
        'Category' => 'B2B', // 二聯 B2C，三聯 B2B
        'TaxType' => '1',
        'TaxRate' => '5',
        'Amt' => '490',
        'TaxAmt' => '10',
        'TotalAmt' => '500',
        'PrintFlag' => 'Y',
        'ItemName' => '商品一|商品二', // 多項商品時，以「|」分開
        'ItemCount' => '1|2', // 多項商品時，以「|」分開
        'ItemUnit' => '個|個', // 多項商品時，以「|」分開
        'ItemPrice' => '300|100', // 多項商品時，以「|」分開
        'ItemAmt' => '300|200', // 多項商品時，以「|」分開
        'Comment' => '備註',
    ]);

    $invoice->invalid([
        //'InvoiceNumber' => $invoice->getResult('InvoiceNumber'), // 發票號碼
        'InvoiceNumber' => 'GA00000016', // 發票號碼
        //'RandomNum' => $invoice->getResult('RandomNum'), // (選擇性) 若需檢查 checkcode，需帶入
        'InvalidReason' => '訂單取消', // 作廢原因
    ]);

} catch (Exception $e) {
    // 例外錯誤處理。
    $sMsg = $e->getMessage();
}
?>
