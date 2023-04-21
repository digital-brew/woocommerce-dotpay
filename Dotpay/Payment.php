<?php
/**
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tech@dotpay.pl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce to newer
 * versions in the future. If you wish to customize WooCommerce for your
 * needs please refer to http://www.dotpay.pl for more information.
 *
 *  @author    Dotpay Team <tech@dotpay.pl>
 *  @copyright Dotpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 */

/**
 * / class/ of skeleton of Dotpay gateway plugin
 */
class Dotpay_Payment extends WC_Payment_Gateway
{
    // Dotpay IP addresses

    const DOTPAY_IP_WHITE_LIST = array(
                                        '195.150.9.37',
                                        '91.216.191.181',
                                        '91.216.191.182',
                                        '91.216.191.183',
                                        '91.216.191.184',
                                        '91.216.191.185',
                                        '5.252.202.254',
                                        '5.252.202.255',
                                      );



    // Office Dotpay IP address
    const OFFICE_IP = '77.79.195.34';
    // Dotpay URL
    const DOTPAY_URL = 'https://ssl.dotpay.pl/t2/';
    // Dotpay Proxy in Przelewy24 URL
    const DPROXY_URL = 'https://dproxy.przelewy24.pl/t2/';
    // Dotpay URL TEST
    const DOTPAY_URL_TEST = 'https://ssl.dotpay.pl/test_payment/';
    // Dotpay Seller Api URL
    const DOTPAY_SELLER_API_URL = 'https://ssl.dotpay.pl/s2/login/';

    // Dotpay Proxy in Przelewy24 Seller Api URL
    const DPROXY_SELLER_API_URL = 'https://dproxy.przelewy24.pl/s2/login/';
    // Dotpay Seller Api URL test
    const DOTPAY_TEST_SELLER_API_URL = 'https://ssl.dotpay.pl/test_seller/';

    // Module version
    const MODULE_VERSION = '3.7.2';


    public static $ocChannel = '248';
    public static $pvChannel = '248';
    public static $ccChannel = '248'; // or 246
    public static $blikChannel = '73';
    public static $transferChannel = '11';
    public static $mpChannel = '71';
    public static $paypoChannel = '95';


    private $orderObject = null;
    private $orderId = null;

    public static $api_username;


    public function __construct() {
        $this->api_username = $this->get_option('api_username');

    }


	/**
     * Returns correct SERVER NAME or HOSTNAME
     * @return string
     */
    public function getHost()
    {

		$possibleHostSources = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME', 'SERVER_ADDR');
		$sourceTransformations = array(
			"HTTP_X_FORWARDED_HOST" => function($value) {
				$elements = explode(',', $value);
				return trim(end($elements));
			}
		);
		$host = '';
		foreach ($possibleHostSources as $source)
		{
			if (!empty($host)) break;
			if (empty($_SERVER[$source])) continue;
			$host = $_SERVER[$source];
			if (array_key_exists($source, $sourceTransformations))
			{
				$host = $sourceTransformations[$source]($host);
			}
		}

		// Remove port number from host
		$host = preg_replace('/:\d+$/', '', $host);

		return trim($host);

    }

	 /**
	 * The validator checks if the given URL address is correct.
	 */
	public function validateHostname($value)
    {
        return (bool) preg_match('/^[a-z0-9]+([\-\.]{1}[a-z0-9]+)*\.[a-z]{2,10}$/', $value);
    }


     /**
     * Return real HOSTNAME this server
     * @return string
     */   
    public function realHostName() 
    {
        $server_name = '';

        if ($this->validateHostname($this->getHost()))
        {
            $server_name = $this->getHost();
        } else {
            $server_name = "HOSTNAME";
        }

        return $server_name;

    }


    /**
     * Return version of this module
     * @return string
     */
    public static function getModuleVersion()
    {
        return self::MODULE_VERSION;
    }



    /**
     * Return API username
     * @return string
     */
    public function getApiUsername()
    {
        return $this->get_option('api_username');
    }


    /**
     * Return channel number of credit card
     * @return string
     */
    public function getCCnumber()
    {
        if (($this->get_option('credit_card_channel_number')) && is_numeric($this->get_option('credit_card_channel_number'))) {
            return $this->get_option('credit_card_channel_number');
        } else {
            return self::$ccChannel;
        }
    }

    /**
     * Return channel name visibility
     * @return boolean
     */
    public function getChannelNameVisiblity()
    {
        $result = 0;
        if ('yes' == $this->get_option('channel_name_show')) {
            $result = true;
        }
        return $result;
    }

    /**
     * Return API password
     * @return string
     */
    public function getApiPassword()
    {
        return $this->get_option('api_password');
    }

    /**
     * Return seller id
     * @return int
     */
    public function getSellerId()
    {
        return $this->get_option('id');
    }

    /**
     * Return seller id for another ID (currency)
     * @return int
     */
    public function getSellerIdPV()
    {
        return $this->get_option('id2');
    }


	/**
	 * Return delivery type for specific shipping
	 * @return int
	 */
	public function getShippingMapping($id)
	{
		return $this->get_option('shipping_mapping_'.$id);
	}

    /**
     * Return seller pin
     * @return string
     */
    protected function getSellerPin()
    {
        return $this->get_option('pin');
    }

    public static function getDotpayChannelsList()
    {
        return array(
            'Gateway_OneClick',
            'Gateway_PV',
            'Gateway_Card',
            'Gateway_Blik',
            'Gateway_Transfer',
            'Gateway_MasterPass',
            'Gateway_PayPo',
            'Gateway_Dotpay'
        );
    }

    /**
     * Return class name of the gateway, dedicated for selected channel id
     * @param int $channel channel id
     * @return string
     */
    public static function getGatewayClassNameByChannelId($channel)
    {
        switch ($channel) {
            case self::$blikChannel:
                return 'Gateway_Blik';
            case self::$transferChannel:
                return 'Gateway_Transfer';
            case self::$pvChannel:
                return 'Gateway_PV';
            case self::$ocChannel:
                return 'Gateway_OneClick';
            case self::$ccChannel:
                return 'Gateway_CC';
            case self::$mpChannel:
                return 'Gateway_MasterPass';
            case self::$paypoChannel:
                return 'Gateway_PayPo';
            default:
                return 'Gateway_Dotpay';
        }
    }

    /**
     * Return flag, if test mode is enabled
     * @return boolean
     */
    public function isTestMode()
    {
        $result = false;
        if ('yes' == $this->get_option('test')) {
            $result = true;
        }

        return $result;
    }



    /**
     * Checks if this account was migrated from Dotpay to Przelewy24 Api
     * @return boolean
     */
    public function isMigratedtoP24()
    {
        $result = false;
        if ('yes' == $this->get_option('dproxy_migrated')) {
            $result = true;
        }

        return $result;
    }
    

    /**
     * Return flag, if show payment instructions for Transfers method is enabled
     * @return boolean
     */
    public function isTransferInstruction()
    {
        $result = false;
        if ('yes' == $this->get_option('transfer_instruction')) {
            $result = true;
        }

        return $result;
    }


    /**
     * Return flag, if shop ID is correct pattern
     * @return boolean
     */
    public function isIDshopCorrectPattern()
    {
        $result = false;
        if (preg_match("/^\d{6}$/", trim($this->get_option('id')))) { 
            $result = true;
        }

        return $result;
    }


    /**
     * Return flag, if proxy server mode is disabled
     * @return boolean
     */
    public function isProxyNotUses()
    {
        $result = false;
        if ('yes' == $this->get_option('proxy_server')) {
            $result = true;
        }

        return $result;
    }


    /**
     * Return flag, if Turn it on if the order id should be added to the return url
     * @return boolean
     */
    public function isCheckStatusURLwithIdOrder()
    {
        $result = false;
        if ('yes' == $this->get_option('CheckStatusURLwithIdOrder')) {
            $result = true;
        }

        return $result;
    }


    /**
     * Return url to Dotpay payment server
     * @return string
     */
    public function getPaymentUrl()
    {
        if($this->isMigratedtoP24() == false){
            $dotpay_url = self::DOTPAY_URL;
        }else{
            $dotpay_url = self::DPROXY_URL;
        }

        if ($this->isTestMode()) {
            $dotpay_url = self::DOTPAY_URL_TEST;
        }

        return $dotpay_url;
    }

    /**
     * Return value for control field
     * @return string
     * @param full|null $full - set 'control' to sent
     */
    function getControl($full = null)
    {
        $order = $this->getOrder();
        if ($full == 'full') {
            return $this->getLegacyOrderId($order) . '|domain:' . $this->realHostName() . '|WooCommerce module ' . self::MODULE_VERSION . ', dp-p24 migrated '.(int)$this->isMigratedtoP24();
        } else {
            return $this->getLegacyOrderId($order);
        }
    }

    /**
     * Return value for p_info field
     * @return string
     */
    public function getPinfo()
    {
        return __('Shop - ', 'dotpay-payment-gateway') . $this->realHostName();
    }

    /**
     * Return amount of order
     * @return float
     */
    public function getOrderAmount()
    {
        return $this->getFormatAmount($this->getOrder()->get_total());
    }

    /**
     * Return amount of cart
     * @return float
     */
    public function getCartAmount()
    {
        global $woocommerce;
        return $this->getFormatAmount($woocommerce->cart->total);
    }

    /**
     * Return amount of order or card if it's available
     * @return float
     */
    public function getAmountForWidget()
    {
        $session_total_amount = WC()->session->get('cart_totals')['total'];
        $orderPay = get_query_var('order-pay');
        $order = $this->getOrder();
        $id = $this->getLegacyOrderId($order);
        if ($id == null && !empty($orderPay)) {
            $this->setOrderId(get_query_var('order-pay'));
        }
        if( $session_total_amount && $session_total_amount >0){
            return $this->normalizeDecimalAmount($session_total_amount);
        }
        elseif ($id != null) {
            return $this->getOrderAmount();
        } else {
            return $this->getCartAmount();
        }
    }

    /**
     * Return currency name
     * @return string
     */
    public function getCurrency()
    {
        return get_woocommerce_currency();
    }




/**
 *  Return the product's title. For products this is the product name.
 * @return array
 */

public function CartProductName(){

    global $woocommerce;
    $items = $woocommerce->cart->get_cart();
    $product_title = array();
        foreach($items as $item => $values) { 
            $_product =  wc_get_product( $values['data']->get_id() );
            $product_title[] = $_product->get_title(); 
        }
        

        return  $product_title;

} 

/**
 * Return  Product formatted name for only 1 product in the cart
 * @return string
 */
public function FormattItemName($name_1) {
    $name_2 = preg_replace('/[^\p{L}0-9\s\-_\/\(){}\.;]/u','',$name_1);
    $name_3 = html_entity_decode($name_2, ENT_QUOTES, 'UTF-8');
    $name3 = str_replace('times;','x',$name_3 );

    $name = " - ( ".$this->encoded_substrParams($name3,0,90,60) ." )";
    
    return $name;
}


/**
 * Return  Product name for only 1 product in the cart
 * @return string
 */
public function getProductName()
{
    $name = "";

    // Loop though shipping packages
    foreach ( WC()->shipping->get_packages() as $key => $package ) {
        // Loop through Shipping rates
        foreach($package['rates'] as $rate_id => $rate ){
            $Items[] = $rate->get_meta_data();
        }
    }  
    if(isset($Items) && !empty($Items[0]['Pozycje'])) {             
        $Items_shipping = $Items[0]['Pozycje']; 
    } else if(isset($Items) && !empty($Items[0]['Items'])) {
        $Items_shipping = $Items[0]['Items']; 
    }else {
        $Items_shipping = null;
    }
    
if( null !== $Items_shipping){
    $Items_shipping_array = explode(',',$Items_shipping);
    $Items_shipping_first = $Items_shipping_array[0];

    if(count($Items_shipping_array) == 1 ){
        $name = $this->FormattItemName($Items_shipping_first);
    }else {
        $name = "";
    }

}else{

    if(is_array($this->CartProductName()))
    {
        if(count($this->CartProductName()) == 1 && isset($this->CartProductName()[0])) {
            
            $name_1 = esc_attr($this->CartProductName()[0]);
            $name = $this->FormattItemName($name_1);

        } else {
            $name = "";
        }
    }else {
        $name = "";
    }

}
    return $name;
}


    /**
     * Return payment description
     * @return string
     */
    public function getDescription()
    {   
        $description = __('Order ID: ', 'dotpay-payment-gateway') . esc_attr($this->getLegacyOrderId($this->getOrder())).$this->getProductName();

        return $description;
    }

    /**
     * Return payment language name
     * @return string
     */
    protected function getPaymentLang()
    {

        $language = get_bloginfo('language');
        $wp_dotpay_lang = '';

        if (is_string($language)) {
            $languageArray = explode('-', $language);
            if (isset($languageArray[0])) {
                $languageLower = strtolower($languageArray[0]);
                $wp_dotpay_lang = $languageLower;
            }
        }

        if ($wp_dotpay_lang == 'pl') {
            $dotpay_lang = 'pl';
        } else {
            if (!in_array($languageLower, $this->getAcceptLang())) {
                $dotpay_lang = 'en';
            } else {
                $dotpay_lang = $languageLower;
            }
        }

        return $dotpay_lang;
    }


    /**
     * Return url where Dotpay could do a redirection after payment making
     * @return string
     */
    public function getUrl()
    {
        $page = new Dotpay_Page(DOTPAY_STATUS_PNAME);
        return $page->getUrl();
    }

    /**
     * Return url for page with order summary
     * @return string
     */
    public function getOrderSummaryUrl()
    {
        return $this->get_return_url($this->getOrder());
    }

    /**
     * Return url to payment confirmation by Dotpay
     * @return string
     */
    public function getUrlc()
    {
        $http = 'http:';
        if (isset($_SERVER['HTTPS']) && (strtolower($_SERVER['HTTPS'] == "on" || $_SERVER['HTTPS']) == "1")) {
            $http = 'https:';
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            $http = 'https:';
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $http = 'https:';
        }
        return str_replace('https:', $http, add_query_arg('wc-api', $this->id . '_confirm', home_url('/')));
    }

    /**
     * Return Dotpay api version
     * @return string
     */
    public function getApiVersion()
    {
        return 'next'; 
    }

    /**
     * Return ip address from is the confirmation request.
     */

    public function getClientIp($list_ip = null)
    {
        $ipaddress = '';
        // CloudFlare support
        if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
            // Validate IP address (IPv4/IPv6)
            if (filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
                $ipaddress = $_SERVER['HTTP_CF_CONNECTING_IP'];
                return $ipaddress;
            }
        }
        if (array_key_exists('X-Forwarded-For', $_SERVER)) {
            $_SERVER['HTTP_X_FORWARDED_FOR'] = $_SERVER['X-Forwarded-For'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            if (strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')) {
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ipaddress = $ips[0];
            } else {
                $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
        } else {
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }


        if (isset($list_ip) && $list_ip != null) {
            if (array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)) {
                return  $_SERVER["HTTP_X_FORWARDED_FOR"];
            } else if (array_key_exists('HTTP_CF_CONNECTING_IP', $_SERVER)) {
                return $_SERVER["HTTP_CF_CONNECTING_IP"];
            } else if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
                return $_SERVER["REMOTE_ADDR"];
            }
        } else {
            return $ipaddress;
        }
    }


    /**
         * Returns if the given ip is on the given whitelist.
         *
         * @param string $ip        The ip to check.
         * @param array  $whitelist The ip whitelist. An array of strings.
         *
         * @return bool
     */
       public function isAllowedIp($ip, array $whitelist)
        {
            $ip = (string)$ip;
            if (in_array($ip, $whitelist, true)) {
                return true;
            }

            return false;
        }



        /**
    	 * checks and crops the size of a string
    	 * the $special parameter means an estimate of how many urlencode characters can be used in a given field
    	 * e.q. 'ż' (1 char) -> '%C5%BC' (6 chars)
    	 * replacing removing double or more special characters that appear side by side by space from: firstname, lastname, city, street, p_info...
    	 */
    	public function encoded_substrParams($string, $from, $to, $special=0)
    		{
    			$string2 = preg_replace('/(\s{2,}|\.{2,}|@{2,}|\-{2,}|\/{3,} | \'{2,}|\"{2,}|_{2,})/', ' ', $string);
    			$s = html_entity_decode($string2, ENT_QUOTES, 'UTF-8');
    			$sub = mb_substr($s, $from, $to,'UTF-8');
    			$sum = strlen(urlencode($sub));
    			if($sum  > $to)
    				{
    					$newsize = $to - $special;
    					$sub = mb_substr($s, $from, $newsize,'UTF-8');
    				}
    			return trim($sub);
    		}


    /**
     * Return customer firstname
     * @return string
     */
    public function getFirstname()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_first_name')) {
            $firstName = esc_attr($order->get_billing_first_name());
        } else {
            $firstName = esc_attr($order->billing_first_name);
        }
        //allowed only: letters, digits, spaces, symbols _-.,'
        $firstName = preg_replace('/[^\w _-]/u', '', $firstName);
        $firstName1 = html_entity_decode($firstName, ENT_QUOTES, 'UTF-8');


        $NewPersonName1 = preg_replace('/[^\p{L}0-9\s\-_]/u',' ',$firstName1);
        return $this->encoded_substrParams($NewPersonName1,0,49,24);
    }

    /**
     * Return customer lastname
     * @return string
     */
    public function getLastname()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_last_name')) {
            $lastName = esc_attr($order->get_billing_last_name());
        } else {
            $lastName = esc_attr($order->billing_last_name);
        }
        //allowed only: letters, digits, spaces, symbols _-.,'
        $lastName = preg_replace('/[^\w _-]/u', '', $lastName);
        $lastName1 = html_entity_decode($lastName, ENT_QUOTES, 'UTF-8');

        $NewPersonName2 = preg_replace('/[^\p{L}0-9\s\-_]/u',' ',$lastName1);
        return $this->encoded_substrParams($NewPersonName2,0,49,24);
    }

    /**
     * Return customer email
     * @return string
     */
    public function getEmail()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_email')) {
            $email = esc_attr($order->get_billing_email());
        } else {
            $email = esc_attr($order->billing_email);
        }
        return $email;
    }

    /**
     * Return customer phone
     * @return string
     */
    public function getPhone()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_phone')) {
            $phone = esc_attr($order->get_billing_phone());
        } else {
            $phone = esc_attr($order->billing_phone);
        }
        $phone = str_replace(' ', '', $phone);
        $phone = str_replace('+', '', $phone);

        $NewPhone1 = preg_replace('/[^\+\s0-9\-_]/','',$phone);
      	return $this->encoded_substrParams($NewPhone1,0,19,6);
    }

    /**
     * Return customer city
     * @return string
     */
    public function getCity()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_city')) {
            $city = esc_attr($order->get_billing_city());
        } else {
            $city = esc_attr($order->billing_city);
        }
        //allowed only: letters, digits, spaces, symbols _-.,'
        $city = preg_replace('/[^.\w \'_-]/u', '', $city);
        $city1 = html_entity_decode($city, ENT_QUOTES, 'UTF-8');

        return $this->encoded_substrParams($city1,0,49,24);

    }

    /**
     * Return customer postcode
     * @return string
     */
    public function getPostcode()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_postcode')) {
            $postcode = esc_attr($order->get_billing_postcode());
        } else {
            $postcode = esc_attr($order->billing_postcode);
        }
        if (empty($postcode)) {
            return $postcode;
        }
        if (preg_match('/^\d{2}\-\d{3}$/', $postcode) == 0 && strtolower($this->getCountry()) == 'pl') {
            $postcode = str_replace('-', '', $postcode);
            $postcode = substr($postcode, 0, 2) . '-' . substr($postcode, 2, 3);
        }

        $NewPostcode1 = preg_replace('/[^\d\w\s\-]/','',$postcode);
        return $this->encoded_substrParams($NewPostcode1,0,19,6);

    }

    /**
     * Return customer country
     * @return string
     */
    public function getCountry()
    {
        $order = $this->getOrder();
        if (method_exists($order, 'get_billing_country')) {
            $country = $order->get_billing_country();
        } else {
            $country = $order->billing_country;
        }

        if (preg_match('/^[a-zA-Z]{2,3}$/', trim($country)) == 0) {
            $country_check = null;
         }else{
            $country_check = trim($country);
         }

        return esc_attr(strtoupper($country_check));
    }

    /**
     * Return customer street and house number
     * @return array
     */
    public function getStreetAndStreetN1()
    {
        $order = $this->getOrder();
        $street1 = '';
        $building_numberRO = '';

        if (method_exists($order, 'get_billing_address_1')) {
            $street = esc_attr($order->get_billing_address_1());
        } else {
            $street = esc_attr($order->billing_address_1);
        }
        //allowed only: letters, digits, spaces, symbols _-.,'
        $street = preg_replace('/[^.\w \'_-]/u', '', $street);
        $street1 = html_entity_decode($street, ENT_QUOTES, 'UTF-8');

        if (method_exists($order, 'get_billing_address_2')) {
            $street_n1 = esc_attr($order->get_billing_address_2());
        } else {
            $street_n1 = esc_attr($order->billing_address_2);
        }

        if (empty($street_n1)) {

            preg_match("/\s[\w\d\/_\-]{0,30}$/", $street1, $matches);
            
            if (count($matches) > 0) {
                $street_n1 = trim($matches[0]);
                $street1 = str_replace($matches[0], '', $street1);
            }else {
                 $street_n1 = "0";
                 $street1 = trim($street1);
            }
          
            $building_numberRO = preg_replace('/[^\p{L}0-9\s\-_\/]/u',' ',$street_n1);
         
        } else{

            //allowed only: letters, digits, spaces, symbols _-/'
            $NewStreet_n1a = preg_replace('/[^\p{L}0-9\s\-_\/]/u',' ',$street_n1);


            if (!empty($NewStreet_n1a)) {
                $building_numberRO = $NewStreet_n1a;
            } else {
                $building_numberRO = "0";  //this field may not be blank in register order
            }

        }


        return array(
            'street' => $this->encoded_substrParams($street1,0,99,50),
            'street_n1' => $this->encoded_substrParams($building_numberRO,0,29,24)
        );
    }

	/**
	 * Return customer shipping city
	 * @return string
	 */
	public function getShippingCity()
	{
		$order = $this->getOrder();
		if (method_exists($order, 'get_shipping_city')) {
			$city = esc_attr($order->get_shipping_city());
		} else {
			$city = esc_attr($order->shipping_city);
		}
		//allowed only: letters, digits, spaces, symbols _-.,'
		$city = preg_replace('/[^.\w \'_-]/u', '', $city);
        $city1 = html_entity_decode($city, ENT_QUOTES, 'UTF-8');

        return $this->encoded_substrParams($city1,0,49,24);
	}

	/**
	 * Return customer shipping postcode
	 * @return string
	 */
	public function getShippingPostcode()
	{
		$order = $this->getOrder();
		if (method_exists($order, 'get_shipping_postcode')) {
			$postcode = esc_attr($order->get_shipping_postcode());
		} else {
			$postcode = esc_attr($order->shipping_postcode);
        }

        $postcode1 = trim(str_replace(' ', '', $postcode));
        $postcode1 = str_replace('-', '', $postcode);

		if (empty($postcode1)) {
			return null;
        }

        if (preg_match('/^\d{3,10}$/', $postcode1) == 0)
	    {
			    return null;
		}else{

            if (preg_match('/^\d{2}\-\d{3}$/', $postcode1) == 0 && strtolower($this->getShippingCountry()) == 'pl') {
                $postcode1 = str_replace('-', '', $postcode1);
                $postcode1 = substr($postcode1, 0, 2) . '-' . substr($postcode1, 2, 3);
            }


        $NewPostcode1 = preg_replace('/[^\d\w\s\-]/','',$postcode1);

        return $this->encoded_substrParams($NewPostcode1,0,19,6);

        }
	}

	/**
	 * Return customer shipping country
	 * @return string
	 */
	public function getShippingCountry()
	{
		$order = $this->getOrder();
		if (method_exists($order, 'get_shipping_country')) {
			$country = $order->get_shipping_country();
		} else {
			$country = $order->shipping_country;
		}      
                
        if (preg_match('/^[a-zA-Z]{2,3}$/', trim($country)) == 0) {
            $country_check = null;
         }else{
            $country_check = trim($country);
         }

		return esc_attr(strtoupper($country_check));
	}

	/**
	 * Return customer shipping street and house number
	 * @return array
	 */
	public function getShippingStreetAndStreetN1()
	{
		$order = $this->getOrder();
		if (method_exists($order, 'get_shipping_address_1')) {
			$street = esc_attr($order->get_shipping_address_1());
		} else {
			$street = esc_attr($order->shipping_address_1);
		}
		//allowed only: letters, digits, spaces, symbols _-.,'
		$street = preg_replace('/[^.\w \'_-]/u', '', $street);
		$street1 = html_entity_decode($street, ENT_QUOTES, 'UTF-8');

        if (method_exists($order, 'get_shipping_address_2')) 
        {
			$street_n1 = esc_attr($order->get_shipping_address_2());
		} else {
			$street_n1 = esc_attr($order->shipping_address_2);
		}

        if (empty($street_n1)) 
        {

			preg_match("/\s[\w\d\/_\-]{0,30}$/", $street1, $matches);
            
            if (count($matches) > 0) {
				$street_n1 = trim($matches[0]);
				$street1 = str_replace($matches[0], '', $street1);
			} else {
                $street_n1 = "0";
                $street1 = trim($street1);
            }

            $building_numberRO = preg_replace('/[^\p{L}0-9\s\-_\/]/u',' ',$street_n1);
        
        } else {


            $NewStreet_n1a = preg_replace('/[^\p{L}0-9\s\-_\/]/u',' ',$street_n1);

            if (!empty($NewStreet_n1a)) {
            $building_numberRO = $NewStreet_n1a;
            }else{
            $building_numberRO = "0";
            }

}

		return array(
          'street' => $this->encoded_substrParams($street1,0,99,50),
          'street_n1' => $this->encoded_substrParams($building_numberRO,0,29,24)
		);
	}

    /**
     * Return array of languages that are accepted by Dotpay
     * @return array
     */
    public function getAcceptLang()
    {
        return array(
            'pl',
            'en',
            'de',
            'it',
            'fr',
            'es',
            'cz',
            'cs',
            'ru',
            'hu',
            'ro',
            'uk',
            'lt',
            'lv'
        );
    }

    /**
     * Returns Dotpay seller Api url
     * @return string
     */
    public function getSellerApiUrl()
    {
        if($this->isMigratedtoP24() == false){
            $dotSellerApi = self::DOTPAY_SELLER_API_URL;
        }else{
            $dotSellerApi = self::DPROXY_SELLER_API_URL;
        }


        if ($this->isTestMode()) {
            $dotSellerApi = self::DOTPAY_TEST_SELLER_API_URL;
        }

        return $dotSellerApi;
    }

    /**
     * Returns Dotpay payment Api url
     * @return string
     */
    public function getPaymentChannelsUrl()
    {
        return $this->getPaymentUrl() . 'payment_api/v1/channels/';
    }

    /**
     *
     * @param float $amount
     * @return string
     */
    public function getFormatAmount($amount)
    {
        return number_format(preg_replace('/[^0-9.]/', '', str_replace(',', '.', $amount)), 2, '.', '');
    }



    /**
     * Convert original amount using a dot as a decimal place regardless of the locale.
     * @param float $amount
     * @return string
     * 
     */

    public function normalizeDecimalAmount($val)
    {
        
        $input = str_replace(' ', '', $val);
        $number = str_replace(',', '.', $input);
        if (strpos($number, '.')) {
            $groups = explode('.', str_replace(',', '.', $number));
            $lastGroup = array_pop($groups);
            $number = implode('', $groups) . '.' . $lastGroup;
        }
        return bcadd($number, 0, 2);
    }


    /**
     * Check, if currently currency is exist in the prameter
     * @param string $allow_currency_form list of currencies
     * @return boolean
     */
    protected function isDotSelectedCurrency($allow_currency_form)
    {
        $result = false;
        $payment_currency = $this->getCurrency();
        $allow_currency = str_replace(';', ',', $allow_currency_form);
        $allow_currency = strtoupper(str_replace(' ', '', $allow_currency));
        $allow_currency_array =  explode(",", trim($allow_currency));

        if (in_array(strtoupper($payment_currency), $allow_currency_array)) {
            $result = true;
        }

        return $result;
    }





    /**
     * Return Dotpay channels, which are availaible for the given amount as a parameter
     * @param float $amount amount
     * @return boolean
     */
    public function getDotpayChannels($amount,$refresh=false,$dp_id2=false)
    {   
        if($dp_id2 == false){
            $dotpay_id = $this->get_option('id');
        }else{
            $dotpay_id = $this->get_option('id2');
        }

		if($amount == 0 || preg_match('/^\d{6}$/', trim($dotpay_id)) == 0)
		{
		    $resultJson = false;
        }else{
            $dotpay_url = $this->getPaymentChannelsUrl();
            $payment_currency = $this->getCurrency();
    
            $order_amount = $this->getFormatAmount($amount);

            if(!empty(WC()->session->get('dotpay_payment_channels_cache_'.$order_amount.'_'.$payment_currency.'_'.$dotpay_id)) && $refresh == false)
            {
                $resultJson = WC()->session->get('dotpay_payment_channels_cache_'.$order_amount.'_'.$payment_currency.'_'.$dotpay_id);
                
            }else{

                $dotpay_lang = $this->getPaymentLang();

                $curl_url = "{$dotpay_url}";
                $curl_url .= "?currency={$payment_currency}";
                $curl_url .= "&id={$dotpay_id}";
                $curl_url .= "&amount={$order_amount}";
                $curl_url .= "&lang={$dotpay_lang}";
                $curl_url .= "&format=json";
                /**
                 * curl
                 */
                try {
        
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_URL, $curl_url);
                    curl_setopt($ch, CURLOPT_REFERER, $curl_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 100);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                                        'Accept: application/json; indent=4',
                                        'Content-type: application/json; charset=utf-8',
                                        'User-Agent: DotpayWooCommerce-channels id:'.$dotpay_id
                                      ));
                    $resultJson = curl_exec($ch);
        
                } catch (Exception $exc) {
                    $resultJson = false;
                }
        
                if ($ch) {
                    curl_close($ch);
                }
                if($resultJson !== false) {

                    WC()->session->set('dotpay_payment_channels_cache_'.$order_amount.'_'.$payment_currency.'_'.$dotpay_id, $resultJson);

                }else{
                    $resultJson = false;
                }

            }
            
        }
 
        return $resultJson;
    }

    /**
     * Returns channel data, if payment channel is active for order data
     * @param type $id channel id
     * @return array|false
     */
    public function getChannelData($id)
    {
        $resultJson = $this->getDotpayChannels($this->getAmountForWidget());
        if (false != $resultJson) {
            $result = json_decode($resultJson, true);
            if (isset($result['channels']) && is_array($result['channels'])) {
                foreach ($result['channels'] as $channel) {
                    if (isset($channel['id']) && $channel['id'] == $id) {
                        return $channel;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Return Dotpay agreement for the given amount and type
     * @param float $amount amount
     * @param string $what type of agreements
     * @return string
     */
    protected function getDotpayAgreement($amount, $what)
    {
        $resultStr = '';

        $resultJson = $this->getDotpayChannels($amount);

        if (false != $resultJson) {
            $result = json_decode($resultJson, true);

            if (isset($result['forms']) && is_array($result['forms'])) {
                foreach ($result['forms'] as $forms) {
                    if (isset($forms['fields']) && is_array($forms['fields'])) {
                        foreach ($forms['fields'] as $forms1) {
                            if ($forms1['name'] == $what) {
                                $resultStr = $forms1['description_html'];
                            }
                        }
                    }
                }
            }
        }

        return $resultStr;
    }


    /**
     * Returns channel name
     * @param type $id channel id
     * @return array|false
     */

    public function getChannelName($id)
    {
        $resultJson = $this->getDotpayChannels('333',false,false);
        if (false != $resultJson) {
            $result = json_decode($resultJson, true);
            if (isset($result['channels']) && is_array($result['channels'])) {
                foreach ($result['channels'] as $channel) {
                    if (isset($channel['id']) && $channel['id'] == $id) {
                        return $channel;
                    }
                }
            }
        }
        return false;
    }


    /**
     * Returns info, check channel: is_disable and disable_message, count available channels
     * @param type $id channel id
     * @return array|false
     */

    public function CheckChannelDisable($id,$pv=false)
    {
        if($this->getAmountForWidget() > 0) { $amountforchannels = $this->getAmountForWidget();}else{ $amountforchannels = '100.00';} 
        
        $getdata = $this->getDotpayChannels($amountforchannels,false,$pv);

		if( isset($getdata) && !empty($getdata) )
			{               
			$session_channels = array($getdata);
			$resultJson = $session_channels[0];
			$result = json_decode($resultJson, true);

                if (isset($result['channels']) && is_array($result['channels'])) {
                    foreach ($result['channels'] as $channel) {
                        if (isset($channel['id']) && $channel['id'] == $id) {
                            
                            if (isset($channel['disable_message'])) {
                                
                                $disabled_message = $channel['disable_message'];
                            }else{ 
                                $disabled_message = "";
                            }
                    return array(
                                'is_disable' => strtolower($channel['is_disable']),
                                'disable_message' => $disabled_message,
                                'id' => $id,
                                'amount' => $amountforchannels
                                );
                            }
                    }
                }else{
                    return false;
                }
				
			}else {
				
				 return false;
			}
       
    }

    /**
     * Returns count available channels for order
     * @return num|Array|false
     */

    public function CheckChannelEnable($count=1,$amountforchannels='100.00',$refresh=false,$dp_id2=false)
    {
        $getdata = $this->getDotpayChannels($amountforchannels,$refresh,$dp_id2);

		if( isset($getdata) && !empty($getdata) )
			{
            
                $session_channels = array($getdata);
                $resultJson = $session_channels[0];
                $result = json_decode($resultJson, true);
                
                $channels = array();
                
                if (isset($result['channels']) && is_array($result['channels'])) {
                    foreach ($result['channels'] as $channel) {
                        if (isset($channel['is_disable']) && (strtolower($channel['is_disable']) === 'false')) {
                            $channels[] = $channel['id'];
                            }
                    }

                    if($count == 1){
                        return count($channels);
                    }else {
                        return $channels;
                    }
                

                }else{
                    return false;
                }
				
			}else {
				
				 return false;
			}   
    }


    /**
     * Return path to file with payment form
     * @return string
     */
     public function getFormPath()
     {
         if(str_replace('Dotpay_', '', $this->id) == "dotpay"){
             $methodname = "standard";
         }else {
             $methodname = str_replace('Dotpay_', '', $this->id);
         }

         return WOOCOMMERCE_DOTPAY_GATEWAY_DIR . 'form/' . $methodname . '.phtml';
     }


     public function getFullFormPath()
     {
         if(str_replace('Dotpay_', '', $this->id) == "dotpay"){
             $methodname = "standard";
         }else {
             $methodname = str_replace('Dotpay_', '', $this->id);
         }

         return $_SERVER['HTTP_ORIGIN'] . WOOCOMMERCE_DOTPAY_GATEWAY_DIR . 'form/' . $methodname . '.phtml';
     }



       /**
        * Return path to template dir
        * @return string
        */
       public function getTemplatesPath()
       {
           return WOOCOMMERCE_DOTPAY_GATEWAY_DIR . 'templates/';
       }



    /**
     * Return path to resource dir
     * @return strin
     */
    public function getResourcePath()
    {
        return WOOCOMMERCE_DOTPAY_GATEWAY_URL . 'resources/';
    }

    /**
     * Return rendered HTML from tamplate file
     * @param string $file name of template file
     * @return string
     */
    public function render($file)
    {
        ob_start();
        include($this->getTemplatesPath() . $file);
        return ob_get_clean();
    }

    /**
     * Persist order id
     * @param int $orderId order id
     */
    protected function setOrderId($orderId)
    {   
        $this->orderId = $orderId;
        WC()->session->set( 'dotpay_payment_order_id', $orderId );
    }

        /**
     * Persist one product name
     * @param int $productName - name of 1 product
     */
    protected function setOneProductName($productName)
    {
        WC()->session->set('dotpay_payment_one_product_name',$productName);
    }


    

    /**
     * Return order object with last order
     * @return WC_Order
     */
    protected function getOrder()
    {

        if ($this->orderObject == null || $this->getLegacyOrderId($this->orderObject) == null) {
            if ($this->orderId == null) {
                 $get_id_session = WC()->session->get('dotpay_payment_order_id');
                if (isset($get_id_session)) {
                    $this->orderId = WC()->session->get('dotpay_payment_order_id');
                }
            }
            $this->orderObject = new WC_Order($this->orderId);
        }
        return $this->orderObject;
    }

    /**
     * Forget saved order
     */
    protected function forgetOrder()
    {
       WC()->session->__unset( 'dotpay_payment_order_id' );
       WC()->session->__unset( 'dotpay_payment_one_product_name' );


        $this->orderObject = null;
        $this->orderId = null;
    }

    /**
     * Return currently cart
     * @global type $woocommerce WOOCOMMERCE object
     * @return WC_Cart
     */
    protected function getCart()
    {
        global $woocommerce;
        return $woocommerce->cart;
    }

    /**
     * Return param, which was sending to page by GET or POST method
     * @param string $name name of param
     * @param mixed $default default value
     * @return boolean
     */
    public function getParam($name, $default = false)
    {
        if (!isset($name) || empty($name) || !is_string($name)) {
            return false;
        }
        $ret = (isset($_POST[$name]) ? $_POST[$name] : (isset($_GET[$name]) ? $_GET[$name] : $default));
        if (is_string($ret)) {
            return addslashes($ret);
        }
        return $ret;
    }

    private function getLegacyOrderId($orderObject)
    {
        if (method_exists($orderObject, 'get_id')) {
            if ((null != $orderObject->get_order_number() || !empty($orderObject->get_order_number()) || is_string($orderObject->get_order_number())) && $orderObject->get_order_number() <> $orderObject->get_id() ) {
                return '#'.$orderObject->get_order_number().'/id:'.$orderObject->get_id();
            } else{
                return $orderObject->get_id();
            }

        } else {
            return $orderObject->id;
        }
    }

}
