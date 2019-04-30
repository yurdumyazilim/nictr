<?php
/**
 * https://nic.tr web sitesi uzerinden 
 * domain listesi alma, domain yenileme, dns guncelleme, domain bilgilerini sorgulama,
 * domainin dnslerini alma islevlerini yapar.
 * 
 * @author Atıf Ceylan <atifceylan@gmail.com>
 * @license GPLv3
 */

class NicTR {
    
    public  $is_logged;
    private $base_url = 'https://nic.tr/index.php';
    private $username;
    private $password;
    private $session;
    private $cookie_file;

    /**
     * __construct
     *
     * @param string $username
     * @param string $password
     * @param string $cookie_file
     * @throws Exception
     */
    public function __construct($username, $password, $cookie_file = '/tmp/nictrcookie.json')
    {
        if (!$username && !$password) {
            $this->error('Kullanici adi ve parola bos gecilemez!');
        }

        /**
         * Oturum verisi bu dosyaya yazilir. 
         * Dikkat edilmesi gereken nokta, ayni anda ayni tur islem birden 
         * fazla kez yapilirsa en son islem 2 kez tekrar eder. Cunku post verisi 
         * once sunucuda oturuma kaydediliyor sonra bir baska post islemi ile tekrar kullaniliyor. 
         */
        $this->cookie_file  = $cookie_file;
        
        $this->username     = $username;
        $this->password     = $password;        
    }


    /**
     * HTTP POST
     *
     * @param string $action
     * @param array $fields
     * @return string
     * @throws Exception
     */
    private function _post($action, $fields = array()) {
        if ($this->session) {
            $session = '?PHPSESSID='.$this->session;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url.$session);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    
        $html = curl_exec($ch);
        if(!$html) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->error($error);
        }else{
            curl_close($ch);
            $result = mb_convert_encoding($html,'UTF-8', 'ISO-8859-9');
            if (strstr($result,'<span class="warning">Program Hatası</span>')){
                $this->error('NicTR Sistem Hatasi');
            }
            return $result;
        }     
    }


    /**
     * HTTP GET
     *
     * @param string $action
     * @param array $fields
     * @return string
     * @throws Exception
     */
    private function _get($action, $fields = array()) {
        if ($this->session) {
            $fields['PHPSESSID'] = $this->session;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->base_url);
        curl_setopt($ch, CURLOPT_POST, 0);
        curl_setopt($ch, CURLOPT_HTTPGET, 1);
        if (count($fields)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        $html = curl_exec($ch);
        $html = curl_exec($ch);
        if(!$html) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->error($error);
        }else{
            curl_close($ch);
            $result = mb_convert_encoding($html,'UTF-8', 'ISO-8859-9');
            if (strstr($result,'<span class="warning">Program Hatası</span>')){
                $this->error('NicTR Sistem Hatasi');
            }
            return $result;
        }
    }

    /**
     * Oturum ID login formdan alan method 
     *
     * @param string $html
     * @return string|void
     */
    private function _getFormSessionVal($html) {
        $strstr = strstr($html, 'name="PHPSESSID" value="');
        if ($strstr) {
            return $this->htmlSelector('value="', '"', $strstr);
        }
    }

    public function error($message, $raw = '') {
        //TODO: raw html ciktisi loglanacak

        throw new Exception($message);
    }

    /**
     * Acik oturum olup olmadigini kontrol eden method
     *
     * @return boolean
     */
    public function isLoggedIn() {
        if (!$this->getSession()) {
            return false;
        }
        $is_logged = $this->_get('isloggedin');
        if (strstr($is_logged, 'name="loginFORM"') ){
            $this->session = false;
            return false;
        }
        return true;
    }

    /**
     * Yeni oturum acma veya var olani kullanma methodu
     *
     * @return void|boolean
     */
    public function login() {
        if (!$this->isLoggedIn()) {
            $login = $this->_get('login');
            $form_session = $this->_getFormSessionVal($login);
            if (strlen($form_session) == 26) {
                $login_content = $this->_post('login', array('PHPSESSID'=>$form_session, 'newHandle'=>$this->username,'password'=>$this->password,'mainLoginSubmit'=>true));

                if (strstr($login_content, '<strong>Yönetim Paneli</strong>')) {
                    $this->setSession($form_session);
                }else{
                    $this->error($this->getLoginError($login_content));
                }
            }else{
                $this->error('Form session input verisi alinamadi');
            }
        }
    }

    /**
     * Oturum acilirken olusan hatayi donen method
     *
     * @param string $html
     * @return string|void
     * @throws Exception
     */
    public function getLoginError($html) {
        $delimiter = 'class="warningLogin">';
        $strstr = strstr($html, $delimiter);
        if ($strstr) {
            return $this->htmlSelector($delimiter, '</td>', $strstr);
        }else{
            $this->error('Bilinmeyen bir hata meydana geldi :/');
        }        
    }

    /**
     * 10dk icinde acilmis bir oturum olup olmadigini kontrol eden method
     *
     * @return string|boolean
     */
    public function getSession() {
        if (is_file($this->cookie_file)) {
            $cookie = json_decode(file_get_contents($this->cookie_file));
            if ($cookie->last_time) {
                $now = time(); 
                $last_time = $cookie->last_time;
                $diff = ceil(($now - $last_time) / 60);
                if ($diff <= 10) {
                    if ($cookie->session) {
                        return $this->session = $cookie->session;
                    }
                }
            }
        }
        $this->session = false;
        return false;
    }

    /**
     * Oturum degiskenini ve zaman bilgisini kaydeden method
     *
     * @param string $session
     * @return boolean
     * @throws Exception
     */
    public function setSession($session) {
        $this->session = $session;
        if (!file_put_contents($this->cookie_file, json_encode( array('last_time'=>time(), 'session'=>$session) ) )){
            $this->error('Session dosyasi yazilamadi');
        }
        return true;
    }

    /**
     * Domain listesini html sayfa icerisindeki JS Array icinden alan method
     *
     * @param string $html
     * @return array
     */
    public function getDomainJsArray($html) {
        $delimiter = 'var aw_raw_data = [';
        $result = array();
        if (strstr($html, $delimiter)) {
            
            $domain_list = $this->htmlSelector($delimiter,'];', $html);
            $js_array    = $this->htmlSelector(']', false, str_replace(array("\n", "'"), '', $domain_list), false);

            foreach ($js_array as $array_element) {
                $exp1 = $this->htmlSelector('[', false, $array_element, false);                
                $exp2 = $this->htmlSelector(',', false, $exp1[1], false);

                if (count($exp2) > 2) {
                    $domain                        = trim($exp2[1]);
                    $result[$domain]['domain_id']  = trim($exp2[0]);
                    $result[$domain]['domain']     = $domain;
                    $result[$domain]['expires_on'] = trim($exp2[3]);
                }
            }
        }

        return $result;
    }
    
    /**
     * Hesapta bulunan tum domainleri listeleyen method
     *
     * @return array
     */
    public function getDomainList() {
        $domain_list_html = $this->_post('domainlist', array('USRACTN'=>'BCNINVPYMNT', 'domainName'=>'', 'ownerName'=>'', 'dateLast'=>'31/12/2099', 'orderBy'=>'0', 'receivedSearch'=>true));
        return $this->getDomainJsArray($domain_list_html);
    }

    /**
     * Hesapta bulunan domainlerden filtrelenen bir tanesini donen method
     *
     * @param string $domain
     * @return array
     */
    public function getDomain($domain) {
        $domain_list = $this->getDomainList();
        return $domain_list[$domain];
    }

    /**
     * Domaine ait dns kayitlarini donen method
     *
     * @param string $domain
     * @return array
     * @throws Exception
     */
    public function getDomainDNS( $domain ) {
        $dns_html = $this->_post('getdomaindns', array('USRACTN'=>'DOMAINSERVERCHNGAPP','domainName[]'=>$domain, 'nameSelect'=>true));
        $strstr = strstr($dns_html,'NAME="dns1Name"');
        $dns = array();

        if ($strstr) {
            $dns[]   = $this->htmlSelector('NAME="dns1Name" MAXLENGTH="50" VALUE="','"', $strstr);
            $dns[]   = $this->htmlSelector('NAME="dns2Name" MAXLENGTH="50" VALUE="','"', $strstr);        
            $dns[]   = $this->htmlSelector('NAME="dns3Name" MAXLENGTH="50" VALUE="','"', $strstr);
            $dns[]   = $this->htmlSelector('NAME="dns4Name" MAXLENGTH="50" VALUE="','"', $strstr);
            $dns[]   = $this->htmlSelector('NAME="dns5Name" MAXLENGTH="50" VALUE="','"', $strstr);

            return $dns;
        }else{
            $this->error('DNS verisi alinamadi');
        }        
    }

    /**
     * Domainin DNS kayitlarini degistiren method
     *
     * @param string $domain
     * @param array $new_dns
     * @return boolean
     * @throws Exception
     */
    public function changeDomainDNS( $domain, $new_dns = array() ) {
        $dns = $this->getDomainDNS( $domain );
        if ($dns) {
            foreach($dns as $key => $val) {                
                if ( isset($new_dns['ns'][$key]) && $new_dns['ns'][$key] == $val ){
                    $this->error('NS kayitlari mevcuttan farkli olmalidir');
                }
            }
            
            $modify_dns_html = $this->_post('modifydomaindns', array(  
            'dns1Name'=>$new_dns['ns'][0], 
            'dns2Name'=>$new_dns['ns'][1],
            'dns3Name'=>$new_dns['ns'][2],
            'dns4Name'=>$new_dns['ns'][3],
            'dns5Name'=>$new_dns['ns'][3],

            'dns1IP'=>$new_dns['ipv4'][0],
            'dns2IP'=>$new_dns['ipv4'][1],
            'dns3IP'=>$new_dns['ipv4'][2],
            'dns4IP'=>$new_dns['ipv4'][3],
            'dns5IP'=>$new_dns['ipv4'][4],

            'dns1IPv6'=>$new_dns['ipv6'][0],
            'dns2IPv6'=>$new_dns['ipv6'][1],
            'dns3IPv6'=>$new_dns['ipv6'][2],
            'dns4IPv6'=>$new_dns['ipv6'][3],
            'dns5IPv6'=>$new_dns['ipv6'][4],

            'modifyServerSubmit'=>true));

            if (strstr($modify_dns_html,'NAME="dateSubmit"')) {

                $confirm_modify_dns = $this->_post('modifydomaindns', array('effectDate'=>'NOW','dateSubmit'=>true) );
                if (strstr($confirm_modify_dns,'DNS sunucu değişikliği başvurunuz tamamlanmıştır')) {
                    return true;
                }else{           
                    $this->error('Form session input verisi alinamadi');
                }
            }else{                
                $this->error('DNS değişim onayı alınamadı');
            }
        }else{            
            $this->error('Domaine ait DNS bilgileri alinamadi');
        }
    }

    /**
     * Domain yenileme methodu
     *
     * @param string $domain
     * @param array $cc_params
     * @param integer $year
     * @return void
     * @throws Exception
     */
    public function renewDomain( $domain, $cc_params = array(), $year = 1 ) {
        $domain_details = $this->getDomain( $domain );
        if ($domain_details) {
            $payment_confirm_html = $this->_post('renewdomain', array('USRACTN'=>'BCNINVPYMNT', 'invoiceList_table[0]' => $domain_details['domain_id'], 'duration_table[0]'=> $year, 'invoiceList[0]'=>$domain_details['domain_id'], 'duration[0]'=>$year, 'pages'=>'0', 'paymentSelectSubmit'=>true) );
            
            if (strstr($payment_confirm_html,'paymentConfirmSubmit')){
                
                $payment_form_html = $this->_post('renewdomain', array('invoiceList[0]'=>$domain_details['domain_id'], 'duration[0]'=>$year, 'pages'=>'0', 'paymentConfirmSubmit'=>true) );
                $strstr = strstr($payment_form_html,'document.paymentForm.paymentType[0].disabled');
                
                if ($strstr) {
                    $form_params = $this->getPaymentFormParams($strstr);
                    if (strlen($form_params['clientid']) > 5 && strlen($form_params['hash']) > 5 ) {
                        if (count($cc_params) == 6) {

                            $form_params['ccOwner']                         = $cc_params['ccOwner'];
                            $form_params['pan']                             = $cc_params['pan'];
                            $form_params['cv2']                             = $cc_params['cv2'];
                            $form_params['ccIssuerId']                      = $cc_params['ccIssuerId']; //1 master card, 2 visa
                            $form_params['Ecom_Payment_Card_ExpDate_Month'] = $cc_params['Ecom_Payment_Card_ExpDate_Month'];
                            $form_params['Ecom_Payment_Card_ExpDate_Year']  = $cc_params['Ecom_Payment_Card_ExpDate_Year'];
                            
                            $pay_html  = $this->_post('pay',  $form_params);

                            if (strstr($pay_html,'Ödeme İşlemi Yapılamıyor!')){
                                $this->error('Kredi kart bilgileriniz hatali veya limitiniz yetersiz olabilir');
                            }

                            if (strstr($pay_html,'Ödeme işleminiz tamamlanmıştır')) {
                                return true;
                            }else{
                                $this->error('Domain yenileme sırasında bilinmeyen bir hata meydana geldi');
                            }

                        }else{
                            $this->error('Domain yenileme icin kredi karti parametreleri eksik');
                        }

                    }else{
                        $this->error('Domain yenileme odeme formu parametreleri alinamadi');
                    }

                }else{
                    $this->error('Domain yenileme odeme formu sayfa hatasi');
                }

            }else{
                $this->error('Domain yenileme secim sayfa hatasi');
            }
            
        }else{
            $this->error('Yenilenecek domain bilgileri alinamadi veya domain yok');
        }
    }

    /**
     * Domain yenileme icin odeme formu verilerini doplayan method
     *
     * @param string $html
     * @return array
     */
    public function getPaymentFormParams($html) {
        $params                     = array();

        $params['islemtipi']        = 'Auth';
        $params['currency']         = '949';
        $params['lang']             = 'tr';
        $params['description']      = 'alan adi odemesi';
        $params['storetype']        = '3d';
        $params['fromCCInfoForm']   = 'fromCCInfoForm';
        $params['okUrl']            = 'https://www.nic.tr/index.php?PHPSESSID=' . $this->session;
        $params['failUrl']          = 'https://www.nic.tr/index.php?PHPSESSID=' . $this->session;
        $params['ccInfoSubmit']     = true;
        $params['paymentType']      = 'withVPOS';
        $params['amount']           = $this->htmlSelector('<INPUT NAME="amount" TYPE="HIDDEN" VALUE="', '"', $html);
        $params['Md']               = $this->htmlSelector('<INPUT type="HIDDEN" name="Md" value="', '"', $html);
        $params['clientid']         = $this->htmlSelector('<INPUT NAME="clientid" TYPE="HIDDEN" VALUE="', '"', $html);
        $params['hash']             = $this->htmlSelector('<input type="hidden" name="hash" value="', '"/>', $html);
        $params['rnd']              = $this->htmlSelector('<INPUT NAME="rnd" TYPE="HIDDEN" VALUE="', '"', $html);

        return $params;
    }

    /**
     * Belirtilen ayraclara gore stringi bolen method
     * Eger suffix verilmezse tek ayraca gore boler ve bir veya birden fazla elemanli
     * bir dizi dondurur. Eger is_string true ise donen dizinin ilk elemani string olarak dondurulur
     *
     * @param string $prefix
     * @param string $suffix
     * @param string $element
     * @param boolean $is_string
     * @return string|boolean
     */
    public function htmlSelector($prefix, $suffix, $element, $is_string = true) {
        $explode = explode($prefix, $element);
        if ($suffix) {
            $result = explode($suffix, $explode[1]);
        }else{
            $result = $explode;
        }

        return $is_string ? $result[0] : $result;
    }
}