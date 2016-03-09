<?php
/* For licensing terms, see /license.txt */

/**
 * Prestashop - Chamilo 1.10.x module integration
 * Sell Chamilo courses in your Prestashop
 *
 * @author Julio Montoya <gugli100@gmail.com> Beeznest 2016
 *
 */

if (!defined('_PS_VERSION_'))
    exit;

require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.nusoap_base.php';
require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.soap_parser.php';
require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.xmlschema.php';
require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.soap_transport_http.php';
require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.wsdl.php';
require_once _PS_ROOT_DIR_.'/modules/prestashopchamilo/nusoap/class.soapclient.php';

/**
 * Class chamilo
 */
class PrestashopChamilo extends Module
{
    public $chamilo_url;
    public $chamilo_security_key;
    public $encrypt_method;
    public $ip;
    public $sha1;

    public function __construct()
    {
        $this->refreshChamiloValues();

        // Module configuration
        $this->name = 'prestashopchamilo';
        $this->tab = 'others';
        $this->version = "1.4";
        $this->author = 'Chamilo';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->wsdl = $this->chamilo_url.'/main/webservices/registration.soap.php?wsdl';
        $this->debug = true;
        parent::__construct();


        $this->displayName = $this->l('Chamilo 1.10.x Module');
        $this->description = $this->l('Let users buy Chamilo courses in your PS platform!');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Refresh values
     */
    public function refreshChamiloValues()
    {
        $parameters = Configuration::getMultiple(
            array(
                'PS_CHAMILO_URL',
                'PS_CHAMILO_SECRET_KEY',
                'PS_CHAMILO_ENCRYPT_METHOD',
                'PS_CHAMILO_HOST_IP',
            )
        );

        // Chamilo configuration

        // Your chamilo URL
        $this->chamilo_url = $parameters['PS_CHAMILO_URL'];
        // Check the configuration.php file
        $this->chamilo_security_key = $parameters['PS_CHAMILO_SECRET_KEY'];
        $this->encrypt_method = $parameters['PS_CHAMILO_ENCRYPT_METHOD'];
        $this->ip = $parameters['PS_CHAMILO_HOST_IP'];
        $this->sha1 = sha1($this->ip.$this->chamilo_security_key);
    }

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install() || !$this->installDB() ||
            !$this->registerHook('newOrder') ||
            !$this->registerHook('paymentConfirm') ||
            !$this->registerHook('OrderDetail')
         ) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function installDB()
    {
        //Creating chamilo - prestashop tables
        /*$create_table = '
        CREATE TABLE '._DB_PREFIX_.'customer_chamilo (
            id          INT NOT NULL  AUTO_INCREMENT
            id_customer INT UNSIGNED NOT NULL,
            id_chamilo  INT UNSIGNED NOT NULL,
            created_at  DATETIME,
            PRIMARY KEY (`id`)
        ) DEFAULT CHARSET=utf8;';
        Db::getInstance()->Execute($create_table);*/

        //Creating a new feature
        $id = Feature::addFeatureImport('CHAMILO_CODE');
        //Creating configuration value
        Configuration::updateValue('CHAMILO_FEATURE_ID', $id);

        return true;
    }

    /**
     * @return bool
     */
    public function uninstall()
    {
        if (!parent::uninstall() || !$this->uninstallDB())
            return false;
        return true;
    }

    /**
     * @return bool
     */
    function uninstallDB()
    {
        //Dropping table
        //Db::getInstance()->Execute('DROP TABLE `'._DB_PREFIX_.'customer_chamilo `;');
        //Removing feature
        $chamilo_feature_id = Configuration::get('CHAMILO_FEATURE_ID');
        $feature = new Feature($chamilo_feature_id);
        $feature->delete();

        //Removing configuration
        Configuration::deleteByName('CHAMILO_FEATURE_ID');
        Configuration::deleteByName('PS_CHAMILO_URL');
        Configuration::deleteByName('PS_CHAMILO_SECRET_KEY');
        Configuration::deleteByName('PS_CHAMILO_ENCRYPT_METHOD');
        Configuration::deleteByName('PS_CHAMILO_HOST_IP');

        return true;
    }

    /**
     * @return string
     */
     public function displayForm()
     {
         $client = new nusoap_client($this->wsdl, true);
         $params = array(
             'secret_key' => $this->sha1,
             'from' => 0,
             'to' => 10
         );

         $courseList = $client->call(
             'WSListCourses',
             array('listCourseInput' => $params)
         );

        //$output .= 'To edit this values go to the modules/chamilo/chamilo.php file<br /><br />';
        $output = '<fieldset><legend>'.$this->l('Chamilo Settings').'</legend>';

        if (!empty($this->chamilo_url)) {
            $output .= '<label>'.$this->l('Chamilo URL').'</label><div class="margin-form"><a href="'.$this->chamilo_url.'" target="_blank">'.$this->chamilo_url.'</a></div>';
            $output .= '<label>'.$this->l('Chamilo WSDL').'</label><div class="margin-form"><a href="'.$this->wsdl.'" target="_blank">'.$this->wsdl.'</a></div>';
        }
        $chamilo_host_ip = Configuration::get('PS_CHAMILO_HOST_IP');
        if (empty($chamilo_host_ip)) {
            $chamilo_host_ip = $_SERVER['SERVER_ADDR'];
        }
        $output .='
            <form action="'.$_SERVER['REQUEST_URI'].'" method="post">
            <div class="margin-form">
                <br class="clear"/>
                <label for="chamilo_url">'.$this->l('Chamilo URL').'&nbsp;&nbsp;</label>
                <input size="50px" type="text" name="chamilo_url" value="'.stripslashes(html_entity_decode(Configuration::get('PS_CHAMILO_URL'))).'" />
                <br class="clear"/><br />
                <label for="chamilo_secret_key">'.$this->l('Chamilo Security key').'&nbsp;&nbsp;</label>
                <input size="50px" type="text" name="chamilo_secret_key" value="'.stripslashes(html_entity_decode(Configuration::get('PS_CHAMILO_SECRET_KEY'))).'" />
                <br /><br class="clear"/><br />
                <label for="chamilo_encrypt_method">'.$this->l('Chamilo encrypted method').'&nbsp;&nbsp;</label>
                <input type="text" name="chamilo_encrypt_method" value="'.stripslashes(html_entity_decode(Configuration::get('PS_CHAMILO_ENCRYPT_METHOD'))).'" />
                <i>(sha1 or md5)</i><br class="clear"/><br />
                <label for="chamilo_host_ip">'.$this->l('Your public IP').'&nbsp;&nbsp;</label>
                <input type="text" name="chamilo_host_ip" value="'.stripslashes(html_entity_decode($chamilo_host_ip)).'" />
            </div>
            <center><input type="submit" name="submitChamilo" value="'.$this->l('Save').'" class="button" /></center>
            </form>';

        $output .= '</fieldset><br />';

        if (!empty($courseList)) {
            $output .= '<fieldset><legend>'.$this->l('Chamilo first 10 courses').'</legend>';
            $output .= '<table class="table"><tr>';
            $output .= '<th>'.$this->l('Title').'</th>';
            $output .= '<th>'.$this->l('Code').'</th>';
            $output .= '<th>'.$this->l('Language').'</th>';
            $output .= '</tr>';
            foreach ($courseList as $course) {
                $output .= '<tr><td>'.$course['title'].'</td>';
                $output .= '<td>'.$course['code'].'</td>';
                $output .= '<td>'.$course['language'].'</td></tr>';
            }
            $output .= '</table>';
        } else {
            $output .= $this->l('No Chamilo courses found, you need to create a Chamilo course. If you already have a Chamilo course you should check the Chamilo settings');
        }

        $output .= '</fieldset>';
        $soapError = $client->getError();

        if (!empty($soapError)) {
            $output .= $soapError;
        }

        return $output;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $output = '<h2>'.$this->displayName.'</h2>';
        if (Tools::isSubmit('submitChamilo')) {

            Configuration::updateValue('PS_CHAMILO_URL', htmlentities(str_replace(array("\r\n", "\n"), '', Tools::getValue('chamilo_url'))));
            Configuration::updateValue('PS_CHAMILO_SECRET_KEY', htmlentities(str_replace(array("\r\n", "\n"), '', Tools::getValue('chamilo_secret_key'))));
            Configuration::updateValue('PS_CHAMILO_ENCRYPT_METHOD', htmlentities(str_replace(array("\r\n", "\n"), '', Tools::getValue('chamilo_encrypt_method'))));
            Configuration::updateValue('PS_CHAMILO_HOST_IP', htmlentities(str_replace(array("\r\n", "\n"), '', Tools::getValue('chamilo_host_ip'))));

            $output .= '<div class="conf confirm"><img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />'.$this->l('Settings updated').'</div>';

            /*
            $nbr = intval(Tools::getValue('nbr'));
                if (!$nbr OR $nbr <= 0 OR !Validate::isInt($nbr))
                    $errors[] = $this->l('Invalid number of product');
                else
                    Configuration::updateValue('HOME_FEATURED_NBR', $nbr);
            if (isset($errors) AND sizeof($errors))
                $output .= $this->displayError(implode(' ', $errors));
            else
                $output .= $this->displayConfirmation($this->l('Settings updated'));*/

        }
        $this->refreshChamiloValues();

        return $output.$this->displayForm();
    }

    /**
     * @param $params
     * @return mixed
     */
    public function hookHome($params)
    {
		global $smarty;
		/*$category = new Category(1);
		$nb = intval(Configuration::get('HOME_FEATURED_NBR'));
		$products = $category->getProducts(intval($params['cookie']->id_lang), 1, ($nb ? $nb : 10), 'date_add', 'DESC');
		$smarty->assign(array(
			'allow_buy_when_out_of_stock'   => Configuration::get('PS_ORDER_OUT_OF_STOCK', false),
			'max_quantity_to_allow_display' => Configuration::get('PS_LAST_QTIES'),
			'category' => $category,
			'products' => $products,
			'currency' => new Currency(intval($params['cart']->id_currency)),
			'lang' => Language::getIsoById(intval($params['cookie']->id_lang)),
			'productNumber' => sizeof($products)
		));*/
		return $this->display(__FILE__, 'content.tpl');
	}

    /**
     * @param $params
     * @return mixed
     */
    public function hookRightColumn($params)
    {
	    return $this->hookHome($params);
    }

    /**
     * @param $params
     */
    public function hookOrderDetail($params)
    {
      //  var_dump($params);exit;
    }

    /**
     * @param $params
     * @return bool
     */
    public function hookPaymentConfirm($params)
    {
        $order = new Order($params['id_order']);

        if ($this->debug) error_log('hookPaymentConfirm');
        if ($this->debug) error_log(print_r($params,1));

        $client = new nusoap_client($this->wsdl, true);

        //Getting PS customer
        $customer = new Customer($order->id_customer);

        //Getting default PS parameters
        $parameters = Configuration::getMultiple(array('PS_LANG_DEFAULT','CHAMILO_FEATURE_ID'));
        $lang_id = $parameters['PS_LANG_DEFAULT'];
        $chamilo_feature_id = $parameters['CHAMILO_FEATURE_ID'];

        //Getting Chamilo course code in the product list
        $product_list = $order->getProducts();
        $course_code_list = array();
        foreach ($product_list  as $product) {
            $my_product = new Product($product['product_id']);
            $features   = $my_product->getFeatures();
            if (!empty($features)) {
                foreach($features as $feature) {
                    if ($feature['id_feature'] == $chamilo_feature_id ) {
                        $feature_value = new FeatureValue($feature['id_feature_value']);
                        $course_code_list[] = $feature_value->value[$lang_id];
                        break;
                    }
                }
            }
        }

        if (empty($course_code_list)) {
            if ($this->debug) error_log('Course code list is empty nothing to create');
            return true;
        }

        if ($this->debug) error_log(print_r($course_code_list, 1));

        // Check if the PS customer have already an account in Chamilo
        $chamilo_params = array(
            'original_user_id_name' => 'prestashop_customer_id',
            //required, field name about original user id
            'original_user_id_value' => $customer->id,
            //required, field value about original user id
            'secret_key' => $this->sha1
            //required, secret key ("your IP address and security key from chamilo") encrypted with sha1
        );
        $chamilo_user_data = $client->call('WSGetUser',array('GetUser'=>$chamilo_params));
        $chamilo_user_id = null;
        if (!empty($chamilo_user_data)) {
            $chamilo_user_id = $chamilo_user_data['user_id'];
        }

        //Login generation - firstname (30 len char) + PS customer id
        $parts = explode("@", $customer->email);
        $username = $parts[0];
        $login = substr(strtolower($username),0,30).$customer->id;
                // User does not have a Chamilo account we proceed to create it
        if (empty($chamilo_user_id)) {
            if ($this->debug) error_log('PS Customer does not exist in Chamilo proceed the creation of the Chamilo user');

            // Password generation
            $password = $clean_password = self::generate_password();
            switch($this->encrypt_method) {
                case 'md5':
                    $password = md5($password);
                    break;
                case 'sha1':
                    $password = sha1($password);
                    break;
            }

            // Default account validity in chamilo.
            $expirationDate = date('Y-m-d H:i:s', strtotime("+3660 days"));

            // Setting params
            $chamilo_params =
                array(
                    'firstname' => $customer->firstname,   // required
                    'lastname' => $customer->lastname,    // required
                    'status' => '5',                    // required, (1 => teacher, 5 => learner)
                    'email' => $customer->email,       // optional, follow the same format (example@domain.com)
                    'loginname' => $login,                 // required
                    'password' => $password,              // required, it's important to define the salt into an extra field param
                    'encrypt_method' => $this->encrypt_method,  // required, check if the encrypt is the same than dokeos configuration
                    'language' => 'english',              // optional
                    'phone' => '',                     // optional
                    'expiration_date' => $expirationDate,  // optional, follow the same format
                    'original_user_id_name' => 'prestashop_customer_id',  //required, field name about original user id
                    'original_user_id_value' => $customer->id,          //required, field value about original user id
                    'secret_key' => $this->sha1                   //required, secret key ("your IP address and security key from chamilo") encrypted with sha1
                );

            // Creating a Chamilo user, calling the webservice
            $chamilo_user_id = $client->call(
                'WSCreateUserPasswordCrypted',
                array('createUserPasswordCrypted' => $chamilo_params)
            );

            if (!empty($chamilo_user_id)) {
                if ($this->debug) error_log('User is subscribed');
                global $cookie;

                /* Email generation */
                $subject = Configuration::get('PS_SHOP_NAME').' [Campus - Chamilo]';
                $templateVars = array(
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{email}' => $customer->email,
                    '{login}' => $login,
                    '{password}' => $clean_password,
                    '{chamilo_url}' => $this->chamilo_url,
                    '{site}' => Configuration::get('PS_SHOP_NAME'),
                );

                /* Email sending */
                if ($this->debug) error_log('Sending message');

                $mailResult = Mail::Send(
                    (int)($cookie->id_lang),
                    'chamilo',
                    $subject,
                    $templateVars,
                    $customer->email,
                    null,
                    Configuration::get('PS_SHOP_EMAIL'),
                    Configuration::get('PS_SHOP_NAME'),
                    null,
                    null,
                    dirname(__FILE__).'/mails/'
                );

                if ($this->debug) {
                    error_log(print_r($mailResult, 1));
                }
            } else {
                if ($this->debug) {
                    error_log($client->getError());
                }
            }
            if ($this->debug) error_log('WSCreateUserPasswordCrypted was called this is the result: '.print_r($chamilo_user_id, 1));
        } else {
            if ($this->debug) error_log('User have already a chamilo account associated with the current PS customer. Chamilo user_id = '.$chamilo_user_id);

            if (!empty($chamilo_user_id)) {
                if ($this->debug) error_log('User is subscribed');
                global $cookie;

                /* Email generation */
                $subject = Configuration::get('PS_SHOP_NAME').' [Campus - Chamilo]';
                $templateVars = array(
                    '{firstname}' => $customer->firstname,
                    '{lastname}' => $customer->lastname,
                    '{chamilo_url}' => $this->chamilo_url,
                    '{site}'        => Configuration::get('PS_SHOP_NAME'),
                );
                /* Email sending */
                if ($this->debug) error_log('Sending message');
                $mailResult = Mail::Send(
                    (int)($cookie->id_lang),
                    'chamilo_already_registered',
                    $subject,
                    $templateVars,
                    $customer->email,
                    null,
                    Configuration::get('PS_SHOP_EMAIL'),
                    Configuration::get('PS_SHOP_NAME'),
                    null,
                    null,
                    dirname(__FILE__).'/mails/'
                );
                if ($this->debug) {
                    error_log(print_r($mailResult, 1));
                }
            }
        }

        if (!empty($chamilo_user_id)) {
            foreach($course_code_list as $course_code) {
                if ($this->debug) error_log('Subscribing user to the course : '.$course_code);
                //if ($this->debug) error_log('Chamilo user was registered with user_id = '.$chamilo_user_id);
                $chamilo_params = array (
                    'course'     => $course_code,
                    'user_id'    => $chamilo_user_id,
                    'secret_key' => $this->sha1 //required, secret key ("your IP address and security key from dokeos") encrypted with sha1
                );
                $result = $client->call(
                    'WSSubscribeUserToCourseSimple',
                    array('subscribeUserToCourseSimple' => $chamilo_params)
                );

                if ($client->fault) {
                    if ($this->debug) error_log('Fault');
                    if ($this->debug) error_log(print_r($result,1));
                } else {
                    // Check for errors
                    $err = $client->getError();
                    if ($err) {
                        // Display the error
                        if ($this->debug) error_log('Error');
                        if ($this->debug) error_log(print_r($err,1));
                    } else {
                        if ($this->debug) error_log('Ok');
                        // Display the result
                        if ($this->debug) error_log(print_r($result,1));
                    }
                }
            }
        } else {
            if ($this->debug) error_log('Error while trying to create a Chamilo user :  '.print_r($chamilo_params, 1));
        }

       /* global $cookie;

        // Email generation
        $subject = '[Prestashop - Chamilo] '.Configuration::get('PS_SHOP_NAME');

        $templateVars = array (
            '{firstname}'   => $params['customer']->firstname,
            '{lastname}'    => $params['customer']->lastname,
            '{email}'       => $params['customer']->email,
            '{id_order}'    => $params['order']->id
        );

        // Email sending
        if (!Mail::Send((int)($cookie->id_lang), 'ekomi', $subject, $templateVars, Configuration::get('PS_SHOP_EMAIL'), NULL, $params['customer']->email, Configuration::get('PS_SHOP_NAME'), NULL, NULL, dirname(__FILE__).'/mails/'))
            return false;*/
        return true;
    }

    /**
     * Executes hook when a order is created
     * @param $params
     */
    public function hookNewOrder($params)
    {

    }

    /**
     * Returns a difficult to guess password.
     * @param int $length, the length of the password
     * @todo implement this in Chamilo
     * @return string the generated password
     */
    public function generate_password($length = 8)
    {
        $characters = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        if ($length < 2) {
            $length = 2;
        }
        $password = '';
        for ($i = 0; $i < $length; $i ++) {
            $password .= $characters[rand() % strlen($characters)];
        }
        return $password;
    }
}
