<?php
include __DIR__.'/src/nictr.php';

try{
    $nictr = new NicTR('XXX-metu','PAROLA');
    $nictr->login();
    
    /* domain yenileme icin kredi karti bilgileri gerekiyor.

    $cc_params['ccOwner']       = 'Kart Sahibi';
    $cc_params['pan']           = '5440xxxx1111';
    $cc_params['cv2']           = 123;
    $cc_params['ccIssuerId']    = 1; //1 master card, 2 visa
    $cc_params['Ecom_Payment_Card_ExpDate_Month'] = '01';
    $cc_params['Ecom_Payment_Card_ExpDate_Year']  = '2023';
    
    /**
     * @param string $domain
     * @param array $cc_params
     * @param integer $year
     */

    //$nictr->renewDomain('yy.com.tr', $cc_params, 1);

    //print_r($nictr->getDomain('yy.com.tr'));

    //print_r($nictr->getDomainDNS('yy.com.tr'));

    //$nictr->changeDomainDNS('yy.com.tr',array('ns'=>array('ns1.sitenizolsun.com','ns2.sitenizolsun.com')));
    print_r($nictr->getDomainList());

} catch (Exception $e) {
    echo $e->getMessage();
}