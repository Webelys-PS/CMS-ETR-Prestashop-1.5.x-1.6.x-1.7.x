<?php
/**
* E-Transactions PrestaShop Module
*
* Feel free to contact E-Transactions at support@e-transactions.fr for any
* question.
*
* LICENSE: This source file is subject to the version 3.0 of the Open
* Software License (OSL-3.0) that is available through the world-wide-web
* at the following URI: http://opensource.org/licenses/OSL-3.0. If
* you did not receive a copy of the OSL-3.0 license and are unable
* to obtain it through the web, please send a note to
* support@e-transactions.fr so we can mail you a copy immediately.
*
*  @category  Module / payments_gateways
*  @version   3.0.10
*  @author    E-Transactions <support@e-transactions.fr>
*  @copyright 2012-2016 E-Transactions
*  @license   http://opensource.org/licenses/OSL-3.0
*  @link      http://www.e-transactions.fr/
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Code for Module installation
 */
class ETransactionsInstaller
{
    /**
     * Install module process
     *
     * @param  object $module Module instance
     * @return array
     */
    public function install($module)
    {
        if (!$this->installETransactionsTables($module)) {
            return array(
                'status' => false,
                'error' => 'Erreur lors de la création des tables du module (consulter les fichiers de log du module pour plus d\'informations)'
            );
        }

        if (!$this->installETransactionsOrderStates($module)) {
            return array(
                'status' => false,
                'error' => 'Erreur lors de la création des statuts de commande du module (consulter les fichiers de log du module pour plus d\'informations)'
            );
        }

        if (!$this->installETransactionsCard($module)) {
            return array(
                'status' => false,
                'error' => 'Erreur lors de la création des moyens de paiement du module (consulter les fichiers de log du module pour plus d\'informations)'
            );
        }

        $crypt = new ETransactionsEncrypt();
        $encryptedKeys = array(
            'ETRANS_KEYTEST',
            'ETRANS_PASS',
        );

        foreach ($module->getConfig()->getDefaults() as $name => $default) {
            if (!Configuration::hasKey($name)) {
                if (in_array($name, $encryptedKeys)) {
                    $default = $crypt->encrypt($default);
                }
                Configuration::updateValue($name, $default);
            }
        }

        $hooks = array('payment', 'paymentReturn', 'AdminOrder', 'updateOrderStatus', 'cancelProduct');
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            $hooks[] = 'actionObjectOrderUpdateAfter';
            // [2.2.0]
            $hooks[] = 'actionAdminControllerSetMedia';
            $hooks[] = 'actionOrderSlipAdd';
        }
        if (version_compare(_PS_VERSION_, '1.6', '>=') && version_compare(_PS_VERSION_, '1.7', '<')) {
            $hooks[] = 'displayPaymentEU';
        }
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $hooks[] = 'paymentOptions';
        }

        foreach ($hooks as $hook) {
            if (!$module->registerHook($hook)) {
                return array(
                    'status' => false,
                    'error' => 'Erreur lors de l\'association du module au Hook PrestaShop '.$hook
                );
            }
        }

        $this->migrateData();

        return array(
            'status' => true
        );
    }

    /**
     * Install Module tables
     *
     * @param  object $module Module instance
     * @return boolean
     */
    public function installETransactionsTables($module)
    {
        // Order table
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'etransactions_order` (
            `id_order` int(10) unsigned NOT NULL,
            `id_transaction` varchar(255) NOT NULL,
            `num_appel` varchar(255) NOT NULL,
            `ref_abo` varchar(255) NOT NULL,
            `payment_status` varchar(255) NOT NULL,
            `amount` int(10) unsigned NOT NULL,
            `initial_amount` int(10) unsigned NOT NULL,
            `currency` int(10) unsigned NOT NULL,
            `payment_by` varchar(255) NOT NULL,
            `method` varchar(30) NULL,
            `carte` varchar(255) NOT NULL,
            `carte_num` varchar(30) NOT NULL,
            `pays` varchar(255) NOT NULL,
            `ip` varchar(255) NOT NULL,
            `secure` varchar(255) NOT NULL,
            `date` varchar(255) NOT NULL,
            `refund_amount` int(10) NOT NULL,
            PRIMARY KEY (`id_order`))
            ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';
        if (!Db::getInstance()->execute($sql)
        ) {
            $module->logFatal('Table creation failed for "etransactions_order"');
            $module->logFatal('   SQL: '.$sql);
            return false;
        }

        // Check if column 'method' exists and create it if not
        if (!$this->_sqlColumnExists(_DB_PREFIX_.'etransactions_order', 'method')) {
            Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'etransactions_order` ADD `method` varchar(30) NULL');
        }
        // Check if column 'carte_num' exists and create it if not
        if (!$this->_sqlColumnExists(_DB_PREFIX_.'etransactions_order', 'carte_num')) {
            Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'etransactions_order` ADD `carte_num` varchar(30) NULL');
        }
        // Check if column 'initial_amount' exists and create it if not
        if (!$this->_sqlColumnExists(_DB_PREFIX_.'etransactions_order', 'initial_amount')) {
            Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'etransactions_order` ADD `initial_amount` int(10) unsigned NOT NULL');
        }

        // Order Recurring table
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'etransactions_recurring` (
            `id_etransactions_recurring` INT(10) NOT NULL AUTO_INCREMENT ,
            `id_order` INT(10) NOT NULL ,
            `status` VARCHAR(255) NOT NULL ,
            `number_term` INT(1) NOT NULL ,
            `amount_paid` INT(15) NOT NULL ,
            PRIMARY KEY (`id_etransactions_recurring`))
            ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';
        if (!Db::getInstance()->execute($sql)
        ) {
            $module->logFatal('Table creation failed for "etransactions_recurring"');
            $module->logFatal('   SQL: '.$sql);
            return false;
        }

        // CartLocker table
        $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'etransactions_cart_locker` (
            `id_cart` INT(10) unsigned NOT NULL,
            `id_transaction` VARCHAR(20) NOT NULL,
            `date_add` datetime NULL,
            PRIMARY KEY (`id_cart`, `id_transaction`))
            ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8';
        if (!Db::getInstance()->execute($sql)
        ) {
            $module->logFatal('Table creation failed for "etransactions_cart_locker"');
            $module->logFatal('   SQL: '.$sql);
            return false;
        }

        return true;
    }

    /**
     * Install Module OrderStates
     *
     * @param  object $module Module instance
     * @return boolean
     */
    public function installETransactionsOrderStates($module)
    {
        if (!$this->_isValidState(Configuration::get('ETRANS_ID_ORDER_STATE_NX')))
        {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'Payé partiellement via E-Transactions';
                } else {
                    $orderState->name[$language['id_lang']] = 'Partially paid through E-Transactions';
                }
            }
            $orderState->send_email = false;
            $orderState->color = '#BBDDEE';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = true;
            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $orderState->paid = true;
            }
            if ($orderState->add()) {
                $this->_copyOrderStateImage($orderState->id);
            } else {
                $module->logFatal('Order State creation failed for '.Configuration::get('ETRANS_ID_ORDER_STATE_NX'));
            }
            Configuration::updateValue('ETRANS_ID_ORDER_STATE_NX', (int)$orderState->id);
        }
        Configuration::updateValue('ETRANS_MIDDLE_STATE_NX', Configuration::get('ETRANS_ID_ORDER_STATE_NX'));

        if (!$this->_isValidState(Configuration::get('ETRANS_RECEIVE_PAY'))) {
            // Débit à l'expédition
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'Débit à l\'expédition';
                    $orderState->template[$language['id_lang']] = 'payment';
                } else {
                    $orderState->name[$language['id_lang']] = 'Receive and Pay';
                    $orderState->template[$language['id_lang']] = 'payment';
                }
            }
            $orderState->send_email = true;
            $orderState->color = '#FF70BD';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = true;
            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $orderState->paid = true;
            }

            if ($orderState->add()) {
                $this->_copyOrderStateImage($orderState->id);
            } else {
                $module->logFatal('Order State creation failed for '.Configuration::get('ETRANS_RECEIVE_PAY'));
            }
            Configuration::updateValue('ETRANS_RECEIVE_PAY', (int)$orderState->id);
        }

        if (!$this->_isValidState(Configuration::get('ETRANS_STATE_MIN_CAPTURE'))) {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'Encaissé partiellement';
                } else {
                    $orderState->name[$language['id_lang']] = 'Partially cashed';
                }
            }
            $orderState->send_email = false;
            $orderState->color = '#F2BFFF';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = true;
            if ($orderState->add()) {
                $this->_copyOrderStateImage($orderState->id);
            } else {
                $module->logFatal('Order State creation failed for '.Configuration::get('ETRANS_STATE_MIN_CAPTURE'));
            }
            Configuration::updateValue('ETRANS_STATE_MIN_CAPTURE', (int)$orderState->id);
        }

        if (!$this->_isValidState(Configuration::get('ETRANS_WEB_CASH_VALIDATION'))) {
            // Capture manuelle
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'Capture manuelle du paiement';
                } else {
                    $orderState->name[$language['id_lang']] = 'Manual capture of payment';
                }
            }
            $orderState->send_email = false;
            $orderState->color = '#DDEEFF';
            $orderState->hidden = true;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = true;
            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $orderState->paid = true;
            }

            if ($orderState->add()) {
                $this->_copyOrderStateImage($orderState->id);
            } else {
                $module->logFatal('Order State creation failed for '.Configuration::get('ETRANS_WEB_CASH_VALIDATION'));
            }
            Configuration::updateValue('ETRANS_WEB_CASH_VALIDATION', (int)$orderState->id);
        }
/*
        if (!$this->_isValidState(Configuration::get('ETRANS_KWIXO'))) {
            $orderState = new OrderState();
            $orderState->name = array();
            foreach (Language::getLanguages() as $language) {
                if (strtolower($language['iso_code']) == 'fr') {
                    $orderState->name[$language['id_lang']] = 'Payé via Kwixo';
                    $orderState->template[$language['id_lang']] = 'payment';
                } else {
                    $orderState->name[$language['id_lang']] = 'Paid with Kwixo';
                    $orderState->template[$language['id_lang']] = 'payment';
                }
            }
            $orderState->send_email = true;
            $orderState->color = '#e4ffb6';
            $orderState->hidden = false;
            $orderState->delivery = false;
            $orderState->logable = true;
            $orderState->invoice = true;
            if (version_compare(_PS_VERSION_, '1.5', '>=')) {
                $orderState->paid = true;
            }
            if ($orderState->add()) {
                $this->_copyOrderStateImage($orderState->id);
            }
            Configuration::updateValue('ETRANS_KWIXO', (int)$orderState->id);
        }
*/
        return true;
    }

    /**
     * Copy image of an OrderState
     *
     * @param  int $orderStateId
     * @return boolean
     */
    private function _copyOrderStateImage($orderStateId)
    {
        $src = dirname(dirname(__FILE__)).'/img/orderState.gif';
        $dst = dirname(dirname(__FILE__)).'/../../img/os/'.((int)$orderStateId).'.gif';
        return copy($src, $dst);
    }

    /**
     * Check if OrderState already exists
     *
     * @param  int $id
     * @return boolean
     */
    private function _isValidState($id)
    {
        if (empty($id)) {
            return false;
        }

        $value = Db::getInstance()->getValue('select 1 from `'._DB_PREFIX_.'order_state` WHERE id_order_state='.$id);
        return $value !== false;
    }

    /**
     * Install Module Payment cards
     *
     * @param  object $module Module instance
     * @return boolean
     */
    public function installETransactionsCard($module)
    {
        $db = new ETransactionsDb();

        $sql = 'CREATE TABLE IF NOT EXISTS `%setransactions_card` (
            `id_card` INT(2) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `type_payment` VARCHAR(12) NOT NULL ,
            `type_card` VARCHAR(30) NOT NULL,
            `label` VARCHAR(30) NOT NULL,
            `active` int(1) NULL,
            `debit_expedition` int(1) NULL,
            `debit_immediat` int(1) NULL,
            `debit_differe` int(1) NULL,
            `remboursement` int(1) NULL,
            `mixte` int(1) NULL DEFAULT 0,
            `3ds` int(1) NULL
        ) ENGINE = %s ;';
        $result = $db->execute(sprintf($sql, _DB_PREFIX_, _MYSQL_ENGINE_));

        if (!$result) {
            $module->logFatal('Table creation failed for "etransactions_card"');
            $module->logFatal('   SQL: '.$sql);
            return false;
        }

        $cards = array(
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'CB',
                'label' => 'Carte CB',
                'active' => 1,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'VISA',
                'label' => 'Carte Visa',
                'active' => 1,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'EUROCARD_MASTERCARD',
                'label' => 'Carte Mastercard',
                'active' => 1,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'E_CARD',
                'label' => 'e-Carte Bleue',
                'active' => 1,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'MAESTRO',
                'label' => 'Carte Maestro',
                'active' => 0,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 2,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'AMEX',
                'label' => 'Carte American Express',
                'active' => 0,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'PAYPAL',
                'type_card' => 'PAYPAL',
                'label' => 'Paypal',
                'active' => 0,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'LEETCHI',
                'type_card' => 'LEETCHI',
                'label' => 'Leetchi',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'COFINOGA',
                'label' => 'Carte Cofinoga',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'AURORE',
                'label' => 'Carte Aurore',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'CREDIT',
                'type_card' => 'UNEURO',
                'label' => '1euro.com',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'DINERS',
                'label' => 'Diners',
                'active' => 0,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'JCB',
                'label' => 'JCB',
                'active' => 0,
                'debit_expedition' => 1,
                'debit_immediat' => 1,
                'debit_differe' => 1,
                'remboursement' => 1,
                'mixte' => 0,
                '3ds' => 1,
            ),
            array(
                'type_payment' => 'CARTE',
                'type_card' => 'BCMC',
                'label' => 'Bancontact/Mistercash',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 2,
            ),
            array(
                'type_payment' => 'PREPAYEE',
                'type_card' => 'IDEAL',
                'label' => 'iDEAL',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'PAYBUTTONS',
                'type_card' => 'PAYBUTTING',
                'label' => 'Paybuttons ING',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'PREPAYEE',
                'type_card' => 'PSC',
                'label' => 'Paysafecard',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'PREPAYEE',
                'type_card' => 'CSHTKT',
                'label' => 'CashTicket',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'WALLET',
                'type_card' => 'PAYLIB',
                'label' => 'Paylib',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'WALLET',
                'type_card' => 'MASTERPASS',
                'label' => 'MasterPass',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'PREPAYEE',
                'type_card' => 'ILLICADO',
                'label' => 'Illicado',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'LIMONETIK',
                'type_card' => 'ANCV',
                'label' => 'ANCV',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                'mixte' => 1,
                '3ds' => 0,
            ),
/*
            array(
                'type_payment' => 'KWIXO',
                'type_card' => 'STANDARD',
                'label' => 'Kwixo standard',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'KWIXO',
                'type_card' => '1XRNP',
                'label' => 'Kwixo à réception',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                '3ds' => 0,
            ),
            array(
                'type_payment' => 'KWIXO',
                'type_card' => 'CREDIT',
                'label' => 'Kwixo credit',
                'active' => 0,
                'debit_expedition' => 0,
                'debit_immediat' => 1,
                'debit_differe' => 0,
                'remboursement' => 0,
                '3ds' => 0,
            ),
*/
        );

        foreach ($cards as $card) {
            if (!$db->insert('etransactions_card', $card)) {
                $module->logFatal('Table data creation failed for "etransactions_card" '.$card['label']);
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall module process
     *
     * @param  object $module Module instance
     * @return boolean
     */
    public function uninstall($module)
    {
        // $hooks = array('payment', 'paymentReturn', 'myAccountBlock', 'customerAccount', 'AdminOrder', 'header', 'updateOrderStatus', 'cancelProduct');
        // if (version_compare(_PS_VERSION_, '1.5', '>=')) {
        //     $hooks[] = 'actionObjectOrderUpdateAfter';
        //     // [2.2.0]
        //     $hooks[] = 'actionAdminControllerSetMedia';
        //     $hooks[] = 'actionOrderSlipAdd';
        // }
        // if (version_compare(_PS_VERSION_, '1.6', '>=')) {
        //     $hooks[] = 'displayPaymentEU';
        // }
        // if (version_compare(_PS_VERSION_, '1.7', '>=')) {
        //     $hooks[] = 'paymentOptions';
        // }
        // foreach ($hooks as $hook) {
        //     if (!$module->unregisterHook($hook)) {
        //         return false;
        //     }
        // }

        // $idOrderState = Configuration::get('ETRANS_ID_ORDER_STATE_NX');
        // if($idOrderState != '') {
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state` WHERE id_order_state='.$idOrderState);
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state_lang` WHERE id_order_state='.$idOrderState);
        //     @unlink(_PS_IMG_DIR_.'os/'.$idOrderState.'.gif');
        // }

        // $idOrderState = Configuration::get('ETRANS_RECEIVE_PAY');
        // if($idOrderState != '') {
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state` WHERE id_order_state='.$idOrderState);
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state_lang` WHERE id_order_state='.$idOrderState);
        //     @unlink(_PS_IMG_DIR_.'os/'.$idOrderState.'.gif');
        // }

        // $idOrderState = Configuration::get('ETRANS_STATE_MIN_CAPTURE');
        // if($idOrderState != '') {
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state` WHERE id_order_state='.$idOrderState);
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state_lang` WHERE id_order_state='.$idOrderState);
        //     @unlink(_PS_IMG_DIR_.'os/'.$idOrderState.'.gif');
        // }

        // $idOrderState = Configuration::get('ETRANS_KWIXO');
        // if($idOrderState != '') {
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state` WHERE id_order_state='.$idOrderState);
        //     Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_state_lang` WHERE id_order_state='.$idOrderState);
        //     @unlink(_PS_IMG_DIR_.'os/'.$idOrderState.'.gif');
        // }

        // Keep the following tables to not loose payment information and configuration
        // - etransactions_recurring
        // - etransactions_order
        $tables = array('etransactions_card', 'etransactions', 'etransactions_cart_locker');
        foreach ($tables as $table) {
            $sql = sprintf('DROP TABLE IF EXISTS `%s%s`;', _DB_PREFIX_, $table);
            Db::getInstance()->execute($sql);
        }

        $configurationToKeep = $this->configurationToKeep();
        foreach ($module->getConfig()->getDefaults() as $name => $default) {
            if (!in_array($name, $configurationToKeep)) {
                Configuration::deleteByName($name);
            }
        }

        /* Delete all configurations */
        // Configuration::deleteByName('ETRANS_ID_ORDER_STATE_NX');
        // Configuration::deleteByName('ETRANS_RECEIVE_PAY');
        // Configuration::deleteByName('ETRANS_STATE_MIN_CAPTURE');

        $this->migrateData(false);

        return true;
    }

    /**
     * Check if a column exists in a table
     *
     * @param  string $table
     * @param  string $column
     * @return boolean
     */
    public function _sqlColumnExists($table, $column)
    {
        $result = Db::getInstance()->getRow('SELECT *
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = "'._DB_NAME_.'" AND TABLE_NAME = "'.$table.'" AND COLUMN_NAME = "'.$column.'"');
        return ($result !== false);
    }

    /**
     * Migrate data from old tables structure
     *
     * @return void
     */
    private function migrateData($action = true)
    {
        if ($action) {
            $sql = 'SHOW TABLES LIKE "'._DB_PREFIX_.'ETRANS_order" ';
            $result = DB::getInstance()->executeS($sql);

            if (false != $result && 0 < count($result)) {
                // Migrate DB data for previous orders
                $sql = sprintf('INSERT IGNORE INTO %setransactions_order (`id_order`, `id_transaction`, `num_appel`, `ref_abo`, `payment_status`, `amount`, `initial_amount`, `currency`, `payment_by`, `carte`, `pays`, `ip`, `secure`, `date`, `refund_amount`)
                    SELECT t.`id_order`, t.`id_transaction`, t.`num_appel`, t.`ref_abo`, t.`payment_status`, t.`amount`, t.`amount`, t.`currency`, t.`payment_by`, t.`carte`, t.`pays`, t.`ip`, t.`secure`, t.`date`, t.`refund_amount` FROM %sETRANS_order t', _DB_PREFIX_, _DB_PREFIX_);

                if (Db::getInstance()->execute($sql)) {
                    $sql = sprintf('DROP TABLE IF EXISTS `%sETRANS_order`;', _DB_PREFIX_);
                    DB::getInstance()->execute($sql);
                }

                // Add Rewrite in .htaccess for old module name
                $path = _PS_ROOT_DIR_.'/.htaccess';
                if (file_exists($path)) {
                    if (!$htaccess = @fopen($path, 'r+')) {
                        return false;
                    }

                    $content = file_get_contents($path);
                    if (!preg_match('/\# E-Transactions start(.*?)\# E-Transactions end/s', $content, $m)) {
                        $append = "\n# E-Transactions start\nRewriteEngine on\nRewriteBase /\nRewriteRule modules/ETransactionsEpayment/$ modules/etransactions/ [QSA,L,R=301]\n# E-Transactions end\n\n";
                        fwrite($htaccess, $append.$content);
                    }
                    fclose($htaccess);
                }
            }
        } else {
            // Remove Rewrite in .htaccess for old module name
            $path = _PS_ROOT_DIR_.'/.htaccess';
            if (file_exists($path)) {
                if (!$htaccess = @fopen($path, 'a')) {
                    return false;
                }

                fclose($htaccess);

                $content = file_get_contents($path);
                if ($htaccess = fopen($path, 'w')) {
                    if (preg_match('/\# E-Transactions start(.*?)\# E-Transactions end/s', $content, $m)) {
                        $positionStart = strpos($content, '# E-Transactions start');
                        $positionEnd = strpos($content, '# E-Transactions end');
                        if (false !== $positionStart && false != $positionEnd) {
                            $contentNew = Tools::substr($content, 0, ($positionStart - 1));
                            $contentNew .= Tools::substr($content, $positionEnd + Tools::strlen('# E-Transactions end'));
                            fwrite($htaccess, $contentNew);
                        }
                    } else {
                        fwrite($htaccess, $content);
                    }
                }
                fclose($htaccess);
            }
        }
    }

    private function configurationToKeep() {
        return array(
            'ETRANS_ID_ORDER_STATE_NX',
            'ETRANS_MIDDLE_STATE_NX',
            'ETRANS_RECEIVE_PAY',
            'ETRANS_STATE_MIN_CAPTURE',
            'ETRANS_WEB_CASH_VALIDATION',
        );
    }
}
