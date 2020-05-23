<?php
try
{
	//根目錄設置
	$_SERVER['DOCUMENT_ROOT'];
	$basedir = $_SERVER['DOCUMENT_ROOT'];
	$sMsg = '';
	$sMsg = '' ;
// 1.載入SDK程式
	include_once($basedir . '/sdk/Ezpay_Invoice.php');
	$ecpay_invoice = new EzpayInvoice ;
// 2.寫入基本介接參數
	$ecpay_invoice->Invoice_Method 	= 'INVOICE_TRIGGER';
	$ecpay_invoice->Invoice_Url 	= 'https://einvoice-stage.ecpay.com.tw/Invoice/TriggerIssue' ;
	$ecpay_invoice->MerchantID 	= '2000132' ;
	$ecpay_invoice->HashKey 	= 'ejCk326UnaZWKisg' ;
	$ecpay_invoice->HashIV 		= 'q9jcZX8Ib9LM8wYk' ; 
// 3.寫入發票相關資訊
	$ecpay_invoice->Send['Tsr'] 	= 'ECPAY201511241023171381912849'; 	// 交易單號
	$ecpay_invoice->Send['PayType'] = '2'; 					// 交易類別
// 4.送出
	$aReturn_Info = $ecpay_invoice->Check_Out();
// 5.返回
	foreach($aReturn_Info as $key => $value)
	{
		$sMsg .=   $key . ' => ' . $value . '<br>' ;
	}
}
catch (Exception $e)
{
	// 例外錯誤處理。
	$sMsg = $e->getMessage();
}
echo $sMsg ;
?>
